<?php

namespace App\Services;

use App\Models\GasRateModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * Which rate applies (requirements.md "Rates and Customer Pricing").
 *
 * Rate types: gas_per_kg; filled_cylinder (gas in one full cylinder of a type);
 * cylinder_package (empty cylinder price of a type). Most specific first:
 *   customer rate  ->  branch rate  ->  global rate.
 * Gas in a cylinder (business setting filled_cylinder_pricing = cylinder_rate):
 *   customer filled rate -> customer per-KG rate x capacity -> branch/global filled rate
 *   -> per-KG rate x capacity. A partly filled cylinder costs rate x gas / capacity, which is
 *   the per-KG equivalent (rate / capacity) x gas, so posting keeps "gas KG x gas rate".
 */
final class RateService
{
    public const KG = 'gas_per_kg';
    public const FILLED = 'filled_cylinder';
    public const EMPTY = 'cylinder_package';
    public const TYPES = [self::KG, self::FILLED, self::EMPTY];

    private BaseConnection $db;
    private GasRateModel $cards;
    /** @var array<string,mixed> */
    private array $cache = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->cards = new GasRateModel();
    }

    public function pricingMode(int $locationId): string
    {
        return $this->cache['mode' . $locationId] ??=
            (string) (new ConfigurationService($this->db))->valueForShop('filled_cylinder_pricing', $locationId) === 'kg_rate' ? 'kg_rate' : 'cylinder_rate';
    }

    /** True when Issue Cylinder also charges the empty cylinder price. */
    public function issueChargesCylinder(int $locationId): bool
    {
        return (string) (new ConfigurationService($this->db))->valueForShop('issue_cylinder_charge', $locationId) === 'gas_and_cylinder';
    }

    /** Active customer rate, or null. */
    public function customerRate(?int $customerId, string $type, ?int $cylinderTypeId = null, ?string $asOf = null): ?float
    {
        if (!$customerId) {
            return null;
        }
        $asOf = $asOf ?? date('Y-m-d H:i:s');
        $q = $this->db->table('customer_rates')->select('rate_value')
            ->where(['customer_id' => $customerId, 'rate_type' => $type])
            ->where('effective_from <=', $asOf)
            ->groupStart()->where('ended_at', null)->orWhere('ended_at >', $asOf)->groupEnd();
        $cylinderTypeId === null ? $q->where('cylinder_type_id', null) : $q->where('cylinder_type_id', $cylinderTypeId);
        $row = $q->orderBy('effective_from', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray();
        return $row ? (float) $row['rate_value'] : null;
    }

    /** @return array{rate: ?float, source: ?string} rate per KG */
    public function kgRate(int $locationId, ?int $customerId = null, ?string $asOf = null): array
    {
        $customer = $this->customerRate($customerId, self::KG, null, $asOf);
        if ($customer !== null) {
            return ['rate' => $customer, 'source' => 'customer'];
        }
        $rate = $this->cards->currentKgRate($asOf, $locationId);
        return ['rate' => ($rate !== null && $rate > 0) ? $rate : null, 'source' => $rate !== null && $rate > 0 ? 'standard' : null];
    }

    /**
     * Price of the gas in one full cylinder of this type, and the equivalent rate per KG.
     * @param array $type cylinder_types row (id, capacity_kg)
     * @return array{rate: ?float, per_kg: ?float, source: ?string}
     */
    public function filledCylinderRate(int $locationId, ?int $customerId, array $type, ?string $asOf = null): array
    {
        $capacity = (float) ($type['capacity_kg'] ?? 0);
        $fromKg = function (array $kg, string $source) use ($capacity): array {
            return ['rate' => $kg['rate'] !== null && $capacity > 0 ? $kg['rate'] * $capacity : null, 'per_kg' => $kg['rate'], 'source' => $kg['rate'] !== null ? $source : null];
        };
        if ($this->pricingMode($locationId) === 'kg_rate' || $capacity <= 0) {
            return $fromKg($this->kgRate($locationId, $customerId, $asOf), 'per_kg');
        }
        $customerFilled = $this->customerRate($customerId, self::FILLED, (int) $type['id'], $asOf);
        if ($customerFilled !== null) {
            return ['rate' => $customerFilled, 'per_kg' => $customerFilled / $capacity, 'source' => 'customer'];
        }
        $customerKg = $this->customerRate($customerId, self::KG, null, $asOf);
        if ($customerKg !== null) {
            return $fromKg(['rate' => $customerKg], 'customer_per_kg');
        }
        $filled = $this->cards->currentFilledCylinderRate((int) $type['id'], $asOf, $locationId);
        if ($filled !== null && $filled > 0) {
            return ['rate' => $filled, 'per_kg' => $filled / $capacity, 'source' => 'standard'];
        }
        return $fromKg($this->kgRate($locationId, null, $asOf), 'per_kg');
    }

    /** Empty cylinder (shell) price: customer -> branch/global rate -> cylinder type master price. */
    public function emptyCylinderPrice(int $locationId, ?int $customerId, array $type, ?string $asOf = null): float
    {
        $customer = $this->customerRate($customerId, self::EMPTY, (int) $type['id'], $asOf);
        if ($customer !== null) {
            return $customer;
        }
        $configured = $this->cards->currentCylinderRate((int) $type['id'], $asOf, $locationId);
        return ($configured !== null && $configured > 0) ? $configured : (float) ($type['empty_cylinder_price'] ?? 0);
    }

    /**
     * Active special rates per customer at a branch, for the POS screen:
     * [customerId => ['gas_per_kg' => x, 'filled_cylinder' => [typeId => x], 'cylinder_package' => [typeId => x]]]
     */
    public function activeCustomerRates(int $locationId): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = $this->db->query(
            "SELECT r.customer_id, r.rate_type, r.cylinder_type_id, r.rate_value
             FROM customer_rates r JOIN customers c ON c.id=r.customer_id
             WHERE c.location_id=? AND r.effective_from<=? AND (r.ended_at IS NULL OR r.ended_at>?)
             ORDER BY r.effective_from, r.id",
            [$locationId, $now, $now]
        )->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $c = (int) $r['customer_id'];
            if ($r['rate_type'] === self::KG) {
                $out[$c][self::KG] = (float) $r['rate_value'];
            } else {
                $out[$c][$r['rate_type']][(int) $r['cylinder_type_id']] = (float) $r['rate_value'];
            }
        }
        return $out;
    }

    /** Rates of one customer (active and ended), newest first, for the Rates screen and ledger. */
    public function customerRateHistory(int $customerId): array
    {
        return $this->db->table('customer_rates r')
            ->select('r.*, ct.code AS cylinder_code, ct.name AS cylinder_name, u.full_name AS created_by_name')
            ->join('cylinder_types ct', 'ct.id=r.cylinder_type_id', 'left')
            ->join('users u', 'u.id=r.created_by', 'left')
            ->where('r.customer_id', $customerId)
            ->orderBy('r.ended_at IS NULL', 'DESC', false)->orderBy('r.effective_from', 'DESC')
            ->get()->getResultArray();
    }

    /** All special rates at a branch (active first). */
    public function branchCustomerRates(int $locationId): array
    {
        return $this->db->table('customer_rates r')
            ->select('r.*, c.code AS customer_code, c.name AS customer_name, ct.code AS cylinder_code, ct.name AS cylinder_name, u.full_name AS created_by_name')
            ->join('customers c', 'c.id=r.customer_id')
            ->join('cylinder_types ct', 'ct.id=r.cylinder_type_id', 'left')
            ->join('users u', 'u.id=r.created_by', 'left')
            ->where('c.location_id', $locationId)
            ->orderBy('r.ended_at IS NULL', 'DESC', false)->orderBy('c.name')->orderBy('r.effective_from', 'DESC')
            ->get()->getResultArray();
    }

    public function setCustomerRate(int $locationId, int $customerId, string $type, ?int $cylinderTypeId, float $value, string $effectiveFrom, ?string $notes, int $userId): int
    {
        if (!in_array($type, self::TYPES, true)) throw new RuntimeException('Choose a rate type.');
        if ($value < 0) throw new RuntimeException('A rate cannot be negative.');
        $customer = $this->db->table('customers')->select('id')->where(['id' => $customerId, 'location_id' => $locationId])->get()->getRowArray();
        if (!$customer) throw new RuntimeException('Choose a customer of this branch.');
        if ($type === self::KG) {
            $cylinderTypeId = null;
        } else {
            if (!$cylinderTypeId || !BusinessContext::cylinderType($cylinderTypeId, $locationId)) throw new RuntimeException('Choose a cylinder type for this rate.');
        }
        $this->db->table('customer_rates')->insert([
            'customer_id' => $customerId, 'rate_type' => $type, 'cylinder_type_id' => $cylinderTypeId,
            'rate_value' => round($value, 4), 'effective_from' => $effectiveFrom, 'notes' => $notes ?: null,
            'created_by' => $userId ?: null,
        ]);
        return (int) $this->db->insertID();
    }

    public function endCustomerRate(int $locationId, int $rateId, int $userId): void
    {
        $row = $this->db->table('customer_rates r')->select('r.id')->join('customers c', 'c.id=r.customer_id')
            ->where(['r.id' => $rateId, 'c.location_id' => $locationId])->where('r.ended_at', null)->get()->getRowArray();
        if (!$row) throw new RuntimeException('Customer rate not found or already ended.');
        $this->db->table('customer_rates')->where('id', $rateId)->update(['ended_at' => date('Y-m-d H:i:s'), 'ended_by' => $userId ?: null]);
    }
}
