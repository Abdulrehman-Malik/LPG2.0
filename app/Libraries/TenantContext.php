<?php

namespace App\Libraries;

use Config\Database;
use RuntimeException;

/**
 * The tenant scope of the current request (BL-008): who is acting, in which branch, and so in
 * which business (business = tenant). Set once by AuthFilter (web) or ApiAuthFilter (API) from
 * an already-authorized identity; models and services read it instead of the session, and
 * fail closed when it is missing.
 */
final class TenantContext
{
    private static ?int $userId = null;
    private static ?int $branchId = null;
    private static ?int $businessId = null;
    private static string $source = 'none';

    /** Web request: the signed-in staff user and the branch AuthFilter has just verified. */
    public static function setWeb(int $userId, int $branchId): void
    {
        self::$userId = $userId > 0 ? $userId : null;
        self::setBranch($branchId);
        self::$source = 'web';
    }

    /** API request: the token's staff user (or null for a customer token); branch set per request. */
    public static function setApi(?int $userId, ?int $branchId = null): void
    {
        self::$userId = $userId !== null && $userId > 0 ? $userId : null;
        self::setBranch((int) $branchId);
        self::$source = 'api';
    }

    /** The branch an API request was authorized for (after its shop check), or a branch switch. */
    public static function setBranch(int $branchId): void
    {
        self::$branchId = $branchId > 0 ? $branchId : null;
        self::$businessId = null;
    }

    public static function clear(): void
    {
        self::$userId = self::$branchId = self::$businessId = null;
        self::$source = 'none';
    }

    public static function isSet(): bool
    {
        return self::$source !== 'none';
    }

    public static function source(): string
    {
        return self::$source;
    }

    public static function userId(): ?int
    {
        return self::$userId;
    }

    public static function branchId(): ?int
    {
        return self::$branchId;
    }

    public static function requireUserId(): int
    {
        if (self::$userId === null) {
            throw new RuntimeException('No signed-in user for this request.');
        }
        return self::$userId;
    }

    public static function requireBranchId(): int
    {
        if (self::$branchId === null) {
            throw new RuntimeException('No authorized branch for this request.');
        }
        return self::$branchId;
    }

    public static function businessId(): ?int
    {
        if (self::$businessId === null && self::$branchId !== null) {
            $row = Database::connect()->table('locations')->select('business_id')->where('id', self::$branchId)->get()->getRowArray();
            self::$businessId = isset($row['business_id']) ? (int) $row['business_id'] : null;
        }
        return self::$businessId;
    }

    public static function requireBusinessId(): int
    {
        $businessId = self::businessId();
        if ($businessId === null || $businessId <= 0) {
            throw new RuntimeException('No authorized business for this request.');
        }
        return $businessId;
    }
}
