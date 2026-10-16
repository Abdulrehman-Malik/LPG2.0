<?php

namespace App\Services;

use App\Models\GasRateModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Setup checklist for a branch (Phase 9.3.3): what must exist before the branch can trade.
 * Uses the same lookups as posting (e.g. the POS gas-rate resolver), so "ready" here means
 * the POS will not reject a sale for missing setup.
 */
class BranchReadinessService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param list<int> $locationIds
     * @return array<int, list<array{key:string,label:string,ok:bool,hint:string,url:string}>>
     */
    public function forLocations(array $locationIds): array
    {
        $locationIds = array_values(array_unique(array_filter(array_map('intval', $locationIds))));
        if (!$locationIds) {
            return [];
        }
        $countBy = function (string $table, string $column, array $where = []) use ($locationIds): array {
            $builder = $this->db->table($table)->select($column . ' location_id, COUNT(*) n')->whereIn($column, $locationIds);
            foreach ($where as $field => $value) {
                is_array($value) ? $builder->whereIn($field, $value) : $builder->where($field, $value);
            }
            $counts = [];
            foreach ($builder->groupBy($column)->get()->getResultArray() as $row) {
                $counts[(int) $row['location_id']] = (int) $row['n'];
            }
            return $counts;
        };
        $settings = $countBy('shop_settings', 'location_id');
        $registers = $countBy('cash_registers', 'location_id', ['is_active' => 1]);
        $stock = $countBy('cylinder_units', 'location_id', ['status' => ['filled', 'empty']]);
        $staff = $countBy('user_shop_access', 'location_id');
        $rates = new GasRateModel();

        $result = [];
        foreach ($locationIds as $id) {
            $gasRate = $rates->currentKgRate(null, $id);
            $result[$id] = [
                ['key' => 'settings', 'label' => 'Shop settings', 'ok' => ($settings[$id] ?? 0) > 0, 'hint' => 'Branch POS, receipt and stock rules.', 'url' => 'configuration/shop/' . $id],
                ['key' => 'register', 'label' => 'Cash register', 'ok' => ($registers[$id] ?? 0) > 0, 'hint' => 'Needed to open a cash session and take cash.', 'url' => 'cash'],
                ['key' => 'gas_rate', 'label' => 'Gas rate', 'ok' => $gasRate !== null && $gasRate > 0, 'hint' => 'Without an effective gas/kg rate the POS asks for a rate on every line.', 'url' => 'rates'],
                ['key' => 'stock', 'label' => 'Cylinder stock', 'ok' => ($stock[$id] ?? 0) > 0, 'hint' => 'Record opening inventory, a purchase or a stock transfer.', 'url' => 'inventory/opening'],
                ['key' => 'staff', 'label' => 'Staff assigned', 'ok' => ($staff[$id] ?? 0) > 0, 'hint' => 'Give at least one user access in Users / Roles.', 'url' => 'users'],
            ];
        }
        return $result;
    }
}
