<?php

namespace App\Services;

use RuntimeException;

/**
 * Rules for a new staff password (BL-003 / BL-004): the configured minimum length
 * (`password_min_length`), never a password the product or its seed data ever shipped with,
 * and never the current password again.
 */
class PasswordPolicy
{
    public const KNOWN_DEFAULTS = ['admin123', '1', 'pak', 'pak123', 'admin', 'password'];

    public static function minLength(): int
    {
        try {
            return max(1, (int) (new ConfigurationService())->resolve('password_min_length')['value']);
        } catch (\Throwable $e) {
            return 10;
        }
    }

    public static function isKnownDefault(string $password): bool
    {
        return in_array(strtolower(trim($password)), self::KNOWN_DEFAULTS, true);
    }

    /** Throws a message suitable for the user when the new password is not acceptable. */
    public static function assertAcceptable(string $new, ?string $currentHash = null): void
    {
        $min = self::minLength();
        if (mb_strlen($new) < $min) {
            throw new RuntimeException('The new password must be at least ' . $min . ' characters.');
        }
        if (self::isKnownDefault($new)) {
            throw new RuntimeException('This password is a known default. Choose a different password.');
        }
        if ($currentHash !== null && password_verify($new, $currentHash)) {
            throw new RuntimeException('The new password must be different from the current password.');
        }
    }
}
