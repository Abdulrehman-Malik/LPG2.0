<?php

namespace App\Services;

use RuntimeException;

/**
 * The one list of payment methods (BL-017; extended by BL-049). Receipts, expenses and supplier
 * payments accept the non-credit methods; POS additionally accepts `credit`.
 */
final class PaymentModes
{
    /** Methods that move money (no credit). */
    public const MONEY = ['cash', 'cheque', 'online'];

    /** @return list<string> */
    public static function receivable(int $locationId): array
    {
        return self::MONEY;
    }

    /** @return list<string> */
    public static function payable(int $locationId): array
    {
        return self::MONEY;
    }

    public static function assertReference(string $mode, ?string $reference, int $locationId): void
    {
        // No method requires a reference number yet (BL-049 makes it configurable).
    }

    public static function label(string $mode): string
    {
        return ucwords(str_replace('_', ' ', $mode));
    }

    public static function assertKnown(string $mode, array $allowed): void
    {
        if (!in_array($mode, $allowed, true)) {
            throw new RuntimeException('Select a valid payment method.');
        }
    }
}
