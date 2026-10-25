<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Client record UUIDs (BL-028). Clients may name a record with an RFC 9562 version 4 or 7 UUID;
 * the server otherwise lets the column default generate one.
 *
 * An API create that received a client UUID calls expect($table, $uuid) before running the
 * service; the application DB builder then puts it on the next INSERT into that table (the
 * header row the service writes), so no posting service needs to know about client UUIDs.
 */
final class Uuid
{
    /** @var array<string,string> table => client uuid waiting for its insert */
    private static array $pending = [];

    public static function isValid(?string $value): bool
    {
        return is_string($value)
            && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[47][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    /** Lower-cased valid UUID, null when none was sent; throws on an invalid one. */
    public static function fromInput($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!self::isValid(is_string($value) ? $value : null)) {
            throw new RuntimeException('uuid must be a version 4 or 7 UUID.');
        }
        return strtolower($value);
    }

    public static function v4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }

    public static function expect(string $table, ?string $uuid): void
    {
        if ($uuid === null) {
            unset(self::$pending[$table]);
        } else {
            self::$pending[$table] = $uuid;
        }
    }

    /** The waiting client UUID for a table, removed once taken. */
    public static function take(string $table): ?string
    {
        $uuid = self::$pending[$table] ?? null;
        unset(self::$pending[$table]);
        return $uuid;
    }
}
