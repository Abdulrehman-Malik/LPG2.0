<?php

namespace App\Libraries;

/**
 * Per-request correlation ID (BL-006). Taken from a well-formed incoming X-Request-Id header
 * (e.g. set by a proxy) or generated; echoed in the response header, every log line and every
 * "something went wrong" reference shown to users.
 */
final class RequestContext
{
    private static ?string $id = null;

    /** @var array{audience:string,id:int}|null authenticated API principal (BL-019) */
    private static ?array $apiPrincipal = null;

    public static function id(): string
    {
        if (self::$id === null) {
            $incoming = isset($_SERVER['HTTP_X_REQUEST_ID']) ? (string) $_SERVER['HTTP_X_REQUEST_ID'] : '';
            self::$id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) ? $incoming : bin2hex(random_bytes(8));
        }

        return self::$id;
    }

    public static function setApiPrincipal(string $audience, int $id): void
    {
        self::$apiPrincipal = $id > 0 ? ['audience' => $audience, 'id' => $id] : null;
    }

    /** @return array{audience:string,id:int}|null */
    public static function apiPrincipal(): ?array
    {
        return self::$apiPrincipal;
    }

    /** Signed-in user and branch without starting a session (the logger runs everywhere). */
    public static function actor(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['user_id' => null, 'branch_id' => null];
        }

        return [
            'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'branch_id' => isset($_SESSION['location_id']) ? (int) $_SESSION['location_id'] : null,
        ];
    }
}
