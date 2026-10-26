<?php

namespace App\Services;

/**
 * BL-033: inventory_movements.from_state / to_state is the cylinder's state before and after
 * the movement. States are the cylinder_units statuses plus NONE (not in the business's stock:
 * before a purchase, opening or adjustment created it). A unit's status always equals the
 * to_state of its latest movement (checked by tests/qa/reconcile_units.sql).
 */
final class MovementStates
{
    public const NONE = 'none';

    /** A cylinder movement's own state: filled or empty (gas rows belong to a filled cylinder). */
    public static function of(string $inventoryType): string
    {
        return $inventoryType === 'empty_cylinder' ? 'empty' : 'filled';
    }

    /**
     * States of a reversal row (void): back from where the original went. A unit the original
     * brought into stock (from NONE) leaves stock as sold, matching CylinderUnitService::markSold.
     *
     * @return array{from_state: ?string, to_state: ?string}
     */
    public static function reverse(array $movement): array
    {
        $from = $movement['from_state'] ?? null;
        return [
            'from_state' => $movement['to_state'] ?? null,
            'to_state' => $from === self::NONE && !empty($movement['cylinder_unit_id']) ? 'sold' : $from,
        ];
    }
}
