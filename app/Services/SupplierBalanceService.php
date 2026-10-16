<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * The single supplier payable rule (owner decision D9-4).
 *
 *   payable = opening + unpaid part of posted purchases − posted supplier account payments
 *
 * Which branches count depends on the business setting `supplier_balance_mode`:
 * - branch:   only the given branch (its own opening, purchases and payments);
 * - business: every branch of the business, for business-wide suppliers only. A branch-only
 *             supplier always uses its own branch.
 *
 * Openings live in supplier_opening_balances (one row per supplier and branch).
 */
final class SupplierBalanceService
{
    public const BRANCH = 'branch';
    public const BUSINESS = 'business';

    private BaseConnection $db;
    /** @var array<int,string> */
    private array $modeByShop = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function mode(int $locationId): string
    {
        return $this->modeByShop[$locationId] ??=
            (string) (new ConfigurationService($this->db))->valueForShop('supplier_balance_mode', $locationId) === self::BUSINESS
                ? self::BUSINESS : self::BRANCH;
    }

    /** True when this supplier's balance is shared by every branch of the business. */
    public function isPooled(array $supplier, int $locationId): bool
    {
        return empty($supplier['location_id']) && $this->mode($locationId) === self::BUSINESS;
    }

    /**
     * Branches whose purchases, payments and openings count for this supplier at $locationId.
     * @return int[]
     */
    public function scopeLocationIds(array $supplier, int $locationId): array
    {
        if (!$this->isPooled($supplier, $locationId)) {
            return [$locationId];
        }
        $rows = $this->db->table('locations')->select('id')->where('business_id', (int) $supplier['business_id'])->get()->getResultArray();
        return array_map('intval', array_column($rows, 'id')) ?: [$locationId];
    }

    /** Opening payable that counts at $locationId (sum over the scope). */
    public function opening(array $supplier, int $locationId): float
    {
        $row = $this->db->table('supplier_opening_balances')->selectSum('amount', 'amount')
            ->where('supplier_id', (int) $supplier['id'])
            ->whereIn('location_id', $this->scopeLocationIds($supplier, $locationId))
            ->get()->getRowArray();
        return (float) ($row['amount'] ?? 0);
    }

    /** Opening recorded at exactly this branch (what the supplier form edits). */
    public function branchOpening(int $supplierId, int $locationId): float
    {
        $row = $this->db->table('supplier_opening_balances')->select('amount')
            ->where(['supplier_id' => $supplierId, 'location_id' => $locationId])->get()->getRowArray();
        return (float) ($row['amount'] ?? 0);
    }

    /** Sets the opening recorded at this branch; keeps suppliers.opening_balance as the total. */
    public function setBranchOpening(int $supplierId, int $locationId, float $amount, ?int $userId): void
    {
        $amount = round(max(0, $amount), 2);
        if ($amount == 0.0 && $this->branchOpening($supplierId, $locationId) == 0.0) {
            return; // nothing recorded and nothing to record
        }
        $this->db->query(
            'INSERT INTO supplier_opening_balances(supplier_id,location_id,amount,updated_by) VALUES(?,?,?,?)
             ON DUPLICATE KEY UPDATE amount=VALUES(amount),updated_by=VALUES(updated_by)',
            [$supplierId, $locationId, $amount, $userId ?: null]
        );
        $total = $this->db->table('supplier_opening_balances')->selectSum('amount', 'amount')->where('supplier_id', $supplierId)->get()->getRowArray();
        $this->db->table('suppliers')->where('id', $supplierId)->update(['opening_balance' => (float) ($total['amount'] ?? 0)]);
    }

    public function balance(array $supplier, int $locationId): float
    {
        $ids = $this->scopeLocationIds($supplier, $locationId);
        $credit = $this->db->table('purchases')->selectSum('credit_amount', 'v')
            ->where(['supplier_id' => (int) $supplier['id'], 'status' => 'posted'])->whereIn('location_id', $ids)->get()->getRowArray();
        $paid = $this->db->table('supplier_payments')->selectSum('amount', 'v')
            ->where(['supplier_id' => (int) $supplier['id'], 'status' => 'posted'])->whereIn('location_id', $ids)->get()->getRowArray();
        return $this->opening($supplier, $locationId) + (float) ($credit['v'] ?? 0) - (float) ($paid['v'] ?? 0);
    }

    public function balanceById(int $supplierId, int $locationId): float
    {
        $supplier = $this->db->table('suppliers')->where('id', $supplierId)->get()->getRowArray();
        return $supplier ? $this->balance($supplier, $locationId) : 0.0;
    }

    /**
     * balance() for a list of suppliers in a few grouped queries.
     * @param list<array> $suppliers rows with id, business_id and location_id
     * @return array<int,float>
     */
    public function balances(array $suppliers, int $locationId): array
    {
        if (!$suppliers) {
            return [];
        }
        $pooledMode = $this->mode($locationId) === self::BUSINESS;
        $businessIds = [];
        if ($pooledMode) {
            $businessId = (int) ($this->db->table('locations')->select('business_id')->where('id', $locationId)->get()->getRowArray()['business_id'] ?? 0);
            $businessIds = array_map('intval', array_column($this->db->table('locations')->select('id')->where('business_id', $businessId)->get()->getResultArray(), 'id'));
        }
        $ids = array_map(static fn ($s) => (int) $s['id'], $suppliers);
        $sum = function (string $table, string $column, array $where, array $locations) use ($ids): array {
            $out = [];
            $q = $this->db->table($table)->select('supplier_id,SUM(' . $column . ') v')->whereIn('supplier_id', $ids)->whereIn('location_id', $locations);
            if ($where) $q->where($where);
            foreach ($q->groupBy('supplier_id')->get()->getResultArray() as $r) $out[(int) $r['supplier_id']] = (float) $r['v'];
            return $out;
        };
        $branch = [
            'open' => $sum('supplier_opening_balances', 'amount', [], [$locationId]),
            'credit' => $sum('purchases', 'credit_amount', ['status' => 'posted'], [$locationId]),
            'paid' => $sum('supplier_payments', 'amount', ['status' => 'posted'], [$locationId]),
        ];
        $pooled = $pooledMode ? [
            'open' => $sum('supplier_opening_balances', 'amount', [], $businessIds),
            'credit' => $sum('purchases', 'credit_amount', ['status' => 'posted'], $businessIds),
            'paid' => $sum('supplier_payments', 'amount', ['status' => 'posted'], $businessIds),
        ] : null;
        $out = [];
        foreach ($suppliers as $s) {
            $id = (int) $s['id'];
            $src = ($pooled !== null && empty($s['location_id'])) ? $pooled : $branch;
            $out[$id] = ($src['open'][$id] ?? 0) + ($src['credit'][$id] ?? 0) - ($src['paid'][$id] ?? 0);
        }
        return $out;
    }
}
