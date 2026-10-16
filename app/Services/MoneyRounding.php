<?php

namespace App\Services;

/**
 * The business money-rounding rule (owner decision D10-2, setting `money_rounding_mode`).
 *
 * - document: line amounts keep full precision; only the document total is rounded to 2 decimals.
 * - line:     every line amount is rounded to 2 decimals and the document total is the sum of
 *             those rounded lines, so a printed invoice always adds up exactly.
 *
 * Every posting path (POS transaction types, customer-order quotes and fulfilment, purchases)
 * and the POS / Purchases screens apply the same rule, so the screen total always equals the
 * total the server posts.
 */
final class MoneyRounding
{
    public const DOCUMENT = 'document';
    public const LINE = 'line';

    private string $mode;

    public function __construct(string $mode = self::DOCUMENT)
    {
        $this->mode = $mode === self::LINE ? self::LINE : self::DOCUMENT;
    }

    public static function forShop(int $shopId): self
    {
        return new self((string) (new ConfigurationService())->valueForShop('money_rounding_mode', $shopId));
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /** A line amount under the business rule. */
    public function line(float $amount): float
    {
        return $this->mode === self::LINE ? round($amount, 2) : $amount;
    }

    /** A document total (always 2 decimals). */
    public function total(float $amount): float
    {
        return round($amount, 2);
    }
}
