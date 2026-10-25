<?php

namespace App\Services;

use RuntimeException;

/**
 * Customer profile fields beyond the original set (BL-031): name => kind ('text' | 'flag' | 'type').
 * A field is only changed when the input contains it, so an API update may send a subset.
 */
final class CustomerFields
{
    public const EXTRA = [
        'customer_type' => 'type',
        'area' => 'text',
        'notes' => 'text',
        'is_blocked' => 'flag',
        'whatsapp_enabled' => 'flag',
    ];

    public const TYPES = [
        'domestic' => 'Domestic',
        'commercial' => 'Commercial',
        'retailer_dealer' => 'Retailer / Dealer',
    ];

    public static function validate(array $data): void
    {
        if (array_key_exists('customer_type', $data) && !isset(self::TYPES[$data['customer_type']])) {
            throw new RuntimeException('Customer type must be Domestic, Commercial or Retailer / Dealer.');
        }
        if (isset($data['notes']) && mb_strlen((string) $data['notes']) > 500) {
            throw new RuntimeException('Notes can be at most 500 characters.');
        }
        if (isset($data['area']) && mb_strlen((string) $data['area']) > 80) {
            throw new RuntimeException('Area can be at most 80 characters.');
        }
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type ?? 'domestic'] ?? ucfirst((string) $type);
    }
}
