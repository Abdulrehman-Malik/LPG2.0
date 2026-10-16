<?php

namespace App\Services;

use App\Models\ShopSettingsModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * The single customer balance rule (Phase 10.2 / 10.3), computed for a whole branch in a few
 * grouped queries instead of one query set per customer.
 *
 *   gas balance     = opening balance + posted credit + posted return-gas ledger − posted receipts
 *   deposit balance = max(0, deposits held − deposits refunded)
 *   OS balance      = gas balance (+ deposit balance when the shop setting
 *                     include_security_deposit_in_os is on)
 *
 * SalesService::customerBalance() returns the same OS balance for one customer.
 */
class CustomerBalanceService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** @return array<int,float> customer id => gas balance, for every customer of the branch */
    public function gasBalances(int $locationId, ?array $customerIds = null): array
    {
        $ids = $customerIds === null ? null : array_values(array_filter(array_map('intval', $customerIds)));
        if ($ids === []) {
            return [];
        }
        $filter = $ids === null ? '' : ' AND c.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $rows = $this->db->query(
            "SELECT c.id, c.opening_balance + COALESCE(s.credit,0) + COALESCE(s.return_ledger,0) - COALESCE(r.paid,0) AS balance
             FROM customers c
             LEFT JOIN (SELECT customer_id, SUM(credit_amount) credit, SUM(return_gas_ledger_amount) return_ledger
                        FROM sales WHERE location_id=? AND status='posted' AND customer_id IS NOT NULL GROUP BY customer_id) s ON s.customer_id=c.id
             LEFT JOIN (SELECT customer_id, SUM(amount) paid
                        FROM customer_receipts WHERE location_id=? AND status='posted' AND customer_id IS NOT NULL GROUP BY customer_id) r ON r.customer_id=c.id
             WHERE c.location_id=?" . $filter,
            array_merge([$locationId, $locationId, $locationId], $ids ?? [])
        )->getResultArray();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = (float) $row['balance'];
        }
        return $out;
    }

    /** @return array<int,float> customer id => refundable security deposit (never negative) */
    public function depositBalances(int $locationId, ?array $customerIds = null): array
    {
        $ids = $customerIds === null ? null : array_values(array_filter(array_map('intval', $customerIds)));
        if ($ids === []) {
            return [];
        }
        $filter = $ids === null ? '' : ' AND customer_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $out = [];
        foreach ($this->db->query(
            "SELECT customer_id, COALESCE(SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END),0) balance
             FROM customer_security_deposits WHERE location_id=?" . $filter . ' GROUP BY customer_id',
            array_merge([$locationId], $ids ?? [])
        )->getResultArray() as $row) {
            $out[(int) $row['customer_id']] = max(0.0, (float) $row['balance']);
        }
        return $out;
    }

    /** @return array<int,float> customer id => OS balance as used by POS credit checks */
    public function osBalances(int $locationId, ?array $customerIds = null): array
    {
        $gas = $this->gasBalances($locationId, $customerIds);
        if (!$this->includesDeposit($locationId)) {
            return $gas;
        }
        $deposits = $this->depositBalances($locationId, $customerIds);
        foreach ($gas as $id => $balance) {
            $gas[$id] = $balance + ($deposits[$id] ?? 0.0);
        }
        return $gas;
    }

    public function osBalance(int $locationId, int $customerId): float
    {
        return $this->osBalances($locationId, [$customerId])[$customerId] ?? 0.0;
    }

    public function includesDeposit(int $locationId): bool
    {
        return (int) ((new ShopSettingsModel())->forLocation($locationId)['include_security_deposit_in_os'] ?? 0) === 1;
    }
}
