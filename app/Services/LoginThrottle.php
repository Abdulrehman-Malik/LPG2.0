<?php

namespace App\Services;

/**
 * Brute-force throttle for every sign-in entry point (web login, staff/customer API login,
 * token refresh, customer signup). Counts failures per scope + client IP + login name in the
 * cache; once the limit is reached the identity is locked for the window, even with the right
 * password. Keys start with `login_fail_` (the QA suites clear them between runs).
 */
class LoginThrottle
{
    public const MAX_FAILURES = 5;
    public const WINDOW_SECONDS = 900;

    /** Per-IP limits for endpoints without a login name. */
    public const IP_LIMITS = [
        'api_refresh' => 20,
        'api_signup' => 10,
    ];

    private static function key(string $scope, string $ip, string $login = ''): string
    {
        return 'login_fail_' . $scope . '_' . sha1($ip . '|' . strtolower(trim($login)));
    }

    private static function limit(string $scope): int
    {
        return self::IP_LIMITS[$scope] ?? self::MAX_FAILURES;
    }

    public static function isLocked(string $scope, string $ip, string $login = ''): bool
    {
        return (int) (cache(self::key($scope, $ip, $login)) ?? 0) >= self::limit($scope);
    }

    public static function recordFailure(string $scope, string $ip, string $login = ''): void
    {
        $key = self::key($scope, $ip, $login);
        cache()->save($key, (int) (cache($key) ?? 0) + 1, self::WINDOW_SECONDS);
    }

    public static function clear(string $scope, string $ip, string $login = ''): void
    {
        cache()->delete(self::key($scope, $ip, $login));
    }
}
