<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

/**
 * Resolves the business that owns a branch and guards business-owned master data
 * (cylinder types, expense categories, suppliers — owner decisions D9-1 / D9-3).
 *
 * Authorization (may this user act for this branch?) stays in ShopScopeService; this
 * class only answers ownership questions for an already-authorized branch.
 */
class BusinessContext
{
    /** @var array<int,int> */
    private static array $businessByLocation = [];

    public static function businessIdForLocation(int $locationId): int
    {
        if ($locationId <= 0) {
            throw new RuntimeException('A valid branch is required.');
        }
        if (!isset(self::$businessByLocation[$locationId])) {
            $row = Database::connect()->table('locations')->select('business_id')->where('id', $locationId)->get()->getRowArray();
            if (!$row || (int) $row['business_id'] <= 0) {
                throw new RuntimeException('Branch is not assigned to a business.');
            }
            self::$businessByLocation[$locationId] = (int) $row['business_id'];
        }
        return self::$businessByLocation[$locationId];
    }

    /** Business of the branch currently selected in the browser session. */
    public static function currentBusinessId(): int
    {
        return self::businessIdForLocation((int) (session()->get('location_id') ?? 0));
    }

    /**
     * Loads a cylinder type only if it belongs to the business that owns the branch.
     * Returns null for another business's type, so callers report it as invalid.
     */
    public static function cylinderType(int $typeId, int $locationId, bool $activeOnly = false): ?array
    {
        if ($typeId <= 0) {
            return null;
        }
        $query = Database::connect()->table('cylinder_types')
            ->where(['id' => $typeId, 'business_id' => self::businessIdForLocation($locationId)]);
        if ($activeOnly) {
            $query->where('is_active', 1);
        }
        return $query->get()->getRowArray() ?: null;
    }

    public static function assertCylinderType(int $typeId, int $locationId): array
    {
        $type = self::cylinderType($typeId, $locationId);
        if (!$type) {
            throw new RuntimeException('Cylinder type not found for this business.');
        }
        return $type;
    }

    public static function expenseCategory(int $categoryId, int $locationId): ?array
    {
        if ($categoryId <= 0) {
            return null;
        }
        return Database::connect()->table('expense_categories')
            ->where(['id' => $categoryId, 'business_id' => self::businessIdForLocation($locationId), 'is_active' => 1])
            ->get()->getRowArray() ?: null;
    }

    /**
     * A supplier is visible to a branch when it belongs to the branch's business and is
     * either business-wide (location_id NULL) or assigned to that branch.
     */
    public static function supplierVisibleTo(int $supplierId, int $locationId, bool $activeOnly = true): ?array
    {
        if ($supplierId <= 0) {
            return null;
        }
        $query = Database::connect()->table('suppliers')
            ->where(['id' => $supplierId, 'business_id' => self::businessIdForLocation($locationId)])
            ->groupStart()->where('location_id', null)->orWhere('location_id', $locationId)->groupEnd();
        if ($activeOnly) {
            $query->where('is_active', 1);
        }
        return $query->get()->getRowArray() ?: null;
    }

    /** Applies supplier visibility for a branch to a query on `suppliers` (alias optional). */
    public static function scopeSuppliers($builder, int $locationId, string $alias = 'suppliers')
    {
        return $builder->where($alias . '.business_id', self::businessIdForLocation($locationId))
            ->groupStart()->where($alias . '.location_id', null)->orWhere($alias . '.location_id', $locationId)->groupEnd();
    }
}
