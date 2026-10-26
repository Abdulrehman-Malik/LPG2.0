<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Gives a newly created business its own master data (D9-1): the standard LPG cylinder
 * types and expense categories. The business can then edit, deactivate or extend them
 * without affecting any other business. Call inside the transaction that creates the business.
 */
class BusinessProvisioningService
{
    /**
     * The commercial sizes (Q-14): domestic 11.8 kg and commercial 45.4 kg, plus 6 and 15 kg.
     * The fresh-install seed in database/schema.sql keeps its older list (35, 45, 45.2 kg);
     * those existing rows are left untouched.
     */
    public const DEFAULT_CYLINDER_TYPES = [
        ['C6', '6 kg', 6, 1],
        ['C11_8', '11.8 kg', 11.8, 2],
        ['C15', '15 kg', 15, 3],
        ['C45_4', '45.4 kg', 45.4, 4],
    ];

    public const DEFAULT_EXPENSE_CATEGORIES = [
        ['BOWSER', 'Bowser / Transport'],
        ['SALARY', 'Salary / Wages'],
        ['UTILITIES', 'Utilities'],
        ['MAINTENANCE', 'Maintenance'],
        ['OTHER', 'Other'],
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function seedMasterData(int $businessId): void
    {
        foreach (self::DEFAULT_CYLINDER_TYPES as [$code, $name, $capacity, $sort]) {
            $this->db->table('cylinder_types')->ignore(true)->insert([
                'business_id' => $businessId,
                'code' => $code,
                'name' => $name,
                'capacity_kg' => $capacity,
                'sort_order' => $sort,
                'is_active' => 1,
            ]);
        }
        foreach (self::DEFAULT_EXPENSE_CATEGORIES as [$code, $name]) {
            $this->db->table('expense_categories')->ignore(true)->insert([
                'business_id' => $businessId,
                'code' => $code,
                'name' => $name,
                'is_active' => 1,
            ]);
        }
    }
}
