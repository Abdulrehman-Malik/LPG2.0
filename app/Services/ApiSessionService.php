<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

class ApiSessionService
{
    public const ACCESS_TTL_SECONDS = 900;        // 15 minutes
    public const IDLE_TTL_SECONDS = 2592000;      // 30 days
    public const ABSOLUTE_TTL_SECONDS = 7776000;  // 90 days

    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    protected function plusSeconds(int $seconds): string
    {
        return date('Y-m-d H:i:s', time() + $seconds);
    }

    protected function principalIsActive(string $audience, int $principalId): bool
    {
        if ($principalId <= 0) {
            return false;
        }

        if ($audience === 'customer') {
            $row = $this->db->table('customer_app_accounts ca')
                ->select('ca.id')
                ->join('customers c', 'c.id=ca.customer_id', 'inner')
                ->where('ca.id', $principalId)
                ->where('ca.status', 'approved')
                ->where('c.is_active', 1)
                ->get()->getRowArray();
            return $row !== null;
        }

        if ($audience === 'staff') {
            $row = $this->db->table('users')
                ->select('id')
                ->where(['id' => $principalId, 'is_active' => 1])
                ->get()->getRowArray();

            if (!$row) {
                return false;
            }

            return count((new ShopScopeService())->accessibleShopIds($principalId)) > 0;
        }

        return false;
    }

    protected function newRawToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function storeAccessToken(int $sessionId): array
    {
        $raw = $this->newRawToken();
        $expiresAt = $this->plusSeconds(self::ACCESS_TTL_SECONDS);

        $this->db->table('api_access_tokens')->insert([
            'api_session_id' => $sessionId,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => $expiresAt,
        ]);

        return [
            'access_token' => $raw,
            'access_token_expires_at' => $expiresAt,
            'expires_in' => self::ACCESS_TTL_SECONDS,
        ];
    }

    protected function storeRefreshToken(int $sessionId, string $absoluteExpiresAt): string
    {
        $raw = $this->newRawToken();
        $this->db->table('api_refresh_tokens')->insert([
            'api_session_id' => $sessionId,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => $absoluteExpiresAt,
        ]);

        return $raw;
    }

    protected function tokenResponse(int $sessionId, string $audience, int $principalId, string $absoluteExpiresAt, string $idleExpiresAt): array
    {
        $access = $this->storeAccessToken($sessionId);
        $refresh = $this->storeRefreshToken($sessionId, $absoluteExpiresAt);

        return [
            'token_type' => 'Bearer',
            'access_token' => $access['access_token'],
            'expires_in' => $access['expires_in'],
            'access_token_expires_at' => $access['access_token_expires_at'],
            'refresh_token' => $refresh,
            'session_expires_at' => $idleExpiresAt,
            'absolute_session_expires_at' => $absoluteExpiresAt,
            'audience' => $audience,
        ];
    }

    public function createSession(string $audience, int $principalId, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        if (!in_array($audience, ['customer', 'staff'], true) || !$this->principalIsActive($audience, $principalId)) {
            throw new RuntimeException('Account cannot start an API session.');
        }

        $now = $this->now();
        $idleExpiry = $this->plusSeconds(self::IDLE_TTL_SECONDS);
        $absoluteExpiry = $this->plusSeconds(self::ABSOLUTE_TTL_SECONDS);

        $this->db->transBegin();
        try {
            $record = [
                'audience' => $audience,
                'customer_app_account_id' => $audience === 'customer' ? $principalId : null,
                'user_id' => $audience === 'staff' ? $principalId : null,
                'created_at' => $now,
                'last_activity_at' => $now,
                'expires_at' => $idleExpiry,
                'absolute_expires_at' => $absoluteExpiry,
                'created_ip' => $ipAddress ? substr($ipAddress, 0, 45) : null,
                'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            ];
            $this->db->table('api_sessions')->insert($record);
            $sessionId = (int) $this->db->insertID();

            $tokens = $this->tokenResponse($sessionId, $audience, $principalId, $absoluteExpiry, $idleExpiry);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('API session creation failed.');
            }
            $this->db->transCommit();
            return $tokens;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    protected function revokeSession(int $sessionId): void
    {
        if ($sessionId <= 0) {
            return;
        }

        $now = $this->now();
        $this->db->table('api_sessions')
            ->where('id', $sessionId)
            ->where('revoked_at', null)
            ->update(['revoked_at' => $now]);
        $this->db->table('api_access_tokens')
            ->where('api_session_id', $sessionId)
            ->where('revoked_at', null)
            ->update(['revoked_at' => $now]);
        $this->db->table('api_refresh_tokens')
            ->where('api_session_id', $sessionId)
            ->where('revoked_at', null)
            ->update(['revoked_at' => $now]);
    }

    public function authenticateAccessToken(string $rawToken, string $expectedAudience): ?array
    {
        if (strlen($rawToken) !== 64 || !in_array($expectedAudience, ['customer', 'staff'], true)) {
            return null;
        }

        $now = $this->now();
        $row = $this->db->query(
            'SELECT at.id AS access_token_id, at.api_session_id, at.expires_at AS access_expires_at,
                    s.audience, s.customer_app_account_id, s.user_id, s.expires_at AS session_expires_at,
                    s.absolute_expires_at, s.revoked_at AS session_revoked_at
             FROM api_access_tokens at
             JOIN api_sessions s ON s.id=at.api_session_id
             WHERE at.token_hash=? AND at.revoked_at IS NULL AND at.expires_at>?
               AND s.audience=? AND s.revoked_at IS NULL AND s.expires_at>?
               AND s.absolute_expires_at>?
             LIMIT 1',
            [hash('sha256', $rawToken), $now, $expectedAudience, $now, $now]
        )->getRowArray();

        if (!$row) {
            return null;
        }

        $principalId = $expectedAudience === 'customer'
            ? (int) $row['customer_app_account_id']
            : (int) $row['user_id'];

        if (!$this->principalIsActive($expectedAudience, $principalId)) {
            $this->revokeSession((int) $row['api_session_id']);
            return null;
        }

        $idleExpiry = min(
            time() + self::IDLE_TTL_SECONDS,
            strtotime((string) $row['absolute_expires_at'])
        );
        $this->db->table('api_sessions')
            ->where('id', (int) $row['api_session_id'])
            ->where('revoked_at', null)
            ->update([
                'last_activity_at' => $now,
                'expires_at' => date('Y-m-d H:i:s', $idleExpiry),
            ]);

        return [
            'session_id' => (int) $row['api_session_id'],
            'audience' => $expectedAudience,
            'customer_app_account_id' => $expectedAudience === 'customer' ? $principalId : null,
            'user_id' => $expectedAudience === 'staff' ? $principalId : null,
        ];
    }

    public function refresh(string $rawRefreshToken, string $expectedAudience, ?string $ipAddress = null, ?string $userAgent = null): ?array
    {
        if (strlen($rawRefreshToken) !== 64 || !in_array($expectedAudience, ['customer', 'staff'], true)) {
            return null;
        }

        $hash = hash('sha256', $rawRefreshToken);
        $now = $this->now();

        $this->db->transBegin();
        try {
            $row = $this->db->query(
                'SELECT rt.id AS refresh_token_id, rt.api_session_id, rt.expires_at AS refresh_expires_at,
                        rt.consumed_at, rt.revoked_at AS token_revoked_at, s.audience,
                        s.customer_app_account_id, s.user_id, s.expires_at AS session_expires_at,
                        s.absolute_expires_at, s.revoked_at AS session_revoked_at
                 FROM api_refresh_tokens rt
                 JOIN api_sessions s ON s.id=rt.api_session_id
                 WHERE rt.token_hash=? AND s.audience=?
                 LIMIT 1 FOR UPDATE',
                [$hash, $expectedAudience]
            )->getRowArray();

            if (!$row) {
                $this->db->transRollback();
                return null;
            }

            $sessionId = (int) $row['api_session_id'];
            // Any attempt to reuse an already-consumed refresh token revokes the
            // entire session/device token family, not just that token.
            if (!empty($row['consumed_at'])) {
                $this->revokeSession($sessionId);
                $this->db->transCommit();
                return null;
            }

            if (!empty($row['token_revoked_at']) || !empty($row['session_revoked_at'])
                || strtotime((string) $row['refresh_expires_at']) <= time()
                || strtotime((string) $row['session_expires_at']) <= time()
                || strtotime((string) $row['absolute_expires_at']) <= time()) {
                $this->revokeSession($sessionId);
                $this->db->transCommit();
                return null;
            }

            $principalId = $expectedAudience === 'customer'
                ? (int) $row['customer_app_account_id']
                : (int) $row['user_id'];

            if (!$this->principalIsActive($expectedAudience, $principalId)) {
                $this->revokeSession($sessionId);
                $this->db->transCommit();
                return null;
            }

            $this->db->table('api_refresh_tokens')
                ->where('id', (int) $row['refresh_token_id'])
                ->where('consumed_at', null)
                ->where('revoked_at', null)
                ->update(['consumed_at' => $now]);

            if ($this->db->affectedRows() !== 1) {
                $this->revokeSession($sessionId);
                $this->db->transCommit();
                return null;
            }

            $absoluteExpiry = (string) $row['absolute_expires_at'];
            $idleExpiry = min(time() + self::IDLE_TTL_SECONDS, strtotime($absoluteExpiry));
            $idleExpiryAt = date('Y-m-d H:i:s', $idleExpiry);

            $this->db->table('api_sessions')
                ->where('id', $sessionId)
                ->where('revoked_at', null)
                ->update([
                    'last_activity_at' => $now,
                    'expires_at' => $idleExpiryAt,
                    'created_ip' => $ipAddress ? substr($ipAddress, 0, 45) : null,
                    'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
                ]);

            $tokens = $this->tokenResponse($sessionId, $expectedAudience, $principalId, $absoluteExpiry, $idleExpiryAt);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('API token rotation failed.');
            }

            $this->db->transCommit();
            return $tokens;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function revokeFromAccessToken(string $rawToken, string $expectedAudience): bool
    {
        $principal = $this->authenticateAccessToken($rawToken, $expectedAudience);
        if (!$principal) {
            return false;
        }

        $this->revokeSession((int) $principal['session_id']);
        return true;
    }

    public function revokeAllForCustomer(int $customerId): void
    {
        $this->revokePrincipalSessions('customer', $customerId);
    }

    public function revokeAllForStaff(int $userId): void
    {
        $this->revokePrincipalSessions('staff', $userId);
    }

    protected function revokePrincipalSessions(string $audience, int $principalId): void
    {
        if ($principalId <= 0 || !in_array($audience, ['customer', 'staff'], true)) {
            return;
        }

        $column = $audience === 'customer' ? 'customer_app_account_id' : 'user_id';
        $rows = $this->db->table('api_sessions')
            ->select('id')
            ->where($column, $principalId)
            ->where('audience', $audience)
            ->where('revoked_at', null)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $this->revokeSession((int) $row['id']);
        }
    }
}
