<?php

namespace App\Services;

use App\Models\ShopSettingsModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Single-branch report data (BL-018; queries moved unchanged out of the Reports controller so
 * the web pages and the API return the same figures): custody and deposits, inventory detail
 * and the customer OS ledger.
 */
class BranchReportService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** Cylinders currently with customers (issued custody) and the branch's deposit balance. */
    public function custody(int $locationId): array
    {
        $rows = $this->db->table('cylinder_custody cc')
            ->select('cc.*,c.code customer_code,c.name customer_name,c.phone customer_phone,cu.unit_code,cu.gas_weight_kg,ct.code cylinder_code,ct.name cylinder_name')
            ->join('customers c', 'c.id=cc.customer_id')
            ->join('cylinder_units cu', 'cu.id=cc.cylinder_unit_id')
            ->join('cylinder_types ct', 'ct.id=cu.cylinder_type_id')
            ->where(['cc.location_id' => $locationId, 'cc.status' => 'issued'])
            ->orderBy('c.name')->orderBy('cu.unit_code')
            ->get()->getResultArray();
        $totals = $this->db->table('customer_security_deposits')
            ->select("SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END) balance")
            ->where(['location_id' => $locationId])
            ->get()->getRowArray();

        return ['rows' => $rows, 'depositBalance' => (float) ($totals['balance'] ?? 0)];
    }

    /** Filled/empty counts and gas per active cylinder type, plus partly used cylinders. */
    public function inventoryDetail(int $locationId): array
    {
        $types = $this->db->table('cylinder_types')
            ->where('business_id', BusinessContext::businessIdForLocation($locationId))
            ->where('is_active', 1)->orderBy('sort_order')
            ->get()->getResultArray();
        $summary = [];
        $partials = [];
        $companyTotalGas = 0.0;
        $companyFilled = 0;
        $companyEmpty = 0;
        foreach ($types as $t) {
            $typeId = (int) $t['id'];
            $filled = $this->db->table('cylinder_units')->where(['location_id' => $locationId, 'cylinder_type_id' => $typeId, 'status' => 'filled'])->get()->getResultArray();
            $empty = (int) $this->db->table('cylinder_units')->where(['location_id' => $locationId, 'cylinder_type_id' => $typeId, 'status' => 'empty'])->countAllResults();
            $gas = array_sum(array_map(static fn ($u) => (float) $u['gas_weight_kg'], $filled));
            $summary[] = ['id' => $typeId, 'code' => $t['code'], 'name' => $t['name'], 'capacity_kg' => (float) $t['capacity_kg'],
                'filled_cylinders' => count($filled), 'empty_cylinders' => $empty, 'filled_gas_kg' => $gas, 'partial_cylinders' => 0];
            $companyFilled += count($filled);
            $companyEmpty += $empty;
            $companyTotalGas += $gas;
            foreach ($filled as $u) {
                $remaining = (float) $u['gas_weight_kg'];
                $capacity = (float) $t['capacity_kg'];
                if ($remaining > 0.00001 && $remaining < $capacity - 0.00001) {
                    $partials[] = ['unit_code' => $u['unit_code'], 'type_code' => $t['code'], 'type_name' => $t['name'], 'capacity_kg' => $capacity,
                        'gas_weight_kg' => $remaining, 'used_kg' => max(0, $capacity - $remaining),
                        'used_percent' => $capacity > 0 ? max(0, min(100, (($capacity - $remaining) / $capacity) * 100)) : 0,
                        'updated_at' => $u['updated_at'] ?? $u['created_at']];
                    $summary[array_key_last($summary)]['partial_cylinders']++;
                }
            }
        }
        usort($partials, static fn ($a, $b) => strcmp($a['type_code'] . '|' . $a['unit_code'], $b['type_code'] . '|' . $b['unit_code']));

        return ['summary' => $summary, 'partials' => $partials, 'companyTotalGas' => $companyTotalGas, 'companyFilled' => $companyFilled, 'companyEmpty' => $companyEmpty];
    }

    /**
     * Credit sales and receipts of one customer of this branch with a running OS balance.
     * Null when the customer does not belong to the branch.
     */
    public function customerOsLedger(int $locationId, int $customerId): ?array
    {
        $c = $this->db->table('customers')->where('id', $customerId)->where('location_id', $locationId)->get()->getRowArray();
        if (!$c) {
            return null;
        }
        $sales = $this->db->table('sales')->where(['customer_id' => $customerId, 'status' => 'posted'])->where('location_id', $locationId)->orderBy('transaction_at')->get()->getResultArray();
        $receipts = $this->db->table('customer_receipts')->where(['customer_id' => $customerId, 'status' => 'posted'])->where('location_id', $locationId)->orderBy('receipt_at')->get()->getResultArray();
        $events = [];
        foreach ($sales as $s) {
            $events[] = ['date' => $s['transaction_at'], 'type' => 'Sale OS', 'reference' => $s['sale_no'], 'debit' => (float) $s['credit_amount'], 'credit' => 0.0];
        }
        foreach ($receipts as $rr) {
            $events[] = ['date' => $rr['receipt_at'], 'type' => 'Receipt / OS Settlement', 'reference' => $rr['receipt_no'], 'debit' => 0.0, 'credit' => (float) $rr['amount']];
        }
        usort($events, static fn ($a, $b) => strcmp($a['date'] . '|' . $a['reference'], $b['date'] . '|' . $b['reference']));
        $running = (float) $c['opening_balance'];
        foreach ($events as &$e) {
            $running += (float) $e['debit'] - (float) $e['credit'];
            $e['running_balance'] = $running;
        }
        unset($e);
        $settings = (new ShopSettingsModel())->forLocation($locationId);
        $mode = $settings['credit_limit_validation_mode'] ?? 'none';
        $limit = $mode === 'shop' ? (float) ($settings['shop_credit_limit'] ?? 0) : (float) $c['credit_limit'];

        return ['customer' => $c, 'events' => $events, 'openingBalance' => (float) $c['opening_balance'], 'creditLimitMode' => $mode,
            'effectiveCreditLimit' => $limit, 'currentBalance' => $running];
    }
}
