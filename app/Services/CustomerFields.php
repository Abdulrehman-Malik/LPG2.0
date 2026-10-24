<?php

namespace App\Services;

use RuntimeException;

/**
 * Customer profile fields beyond the original set (BL-031): name => kind ('text' | 'flag').
 */
final class CustomerFields
{
    public const EXTRA = [];

    public static function validate(array $data): void
    {
    }
}
