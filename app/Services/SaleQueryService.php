<?php

namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Read side of POS sales for one branch (BL-022; queries moved from the Sales controller so the
 * Sale History screen and the API return the same rows).
 */
class SaleQueryService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Sale History query with the screen's filters: from/to (Y-m-d), status, customer (name or
     * code), transaction_type, sale_no.
     */
    public function historyBuilder(int $locationId, array $filters): BaseBuilder
    {
        [$from, $to] = self::range($filters['from'] ?? null, $filters['to'] ?? null);
        $b = $this->db->table('sales s')
            ->select('s.*,c.code customer_code,c.name customer_name,u.full_name created_by_name,vu.full_name voided_by_name')
            ->join('customers c', 'c.id=s.customer_id', 'left')
            ->join('users u', 'u.id=s.created_by', 'left')
            ->join('users vu', 'vu.id=s.voided_by', 'left')
            ->where('s.location_id', $locationId)
            ->where('s.transaction_at >=', $from . ' 00:00:00')
            ->where('s.transaction_at <=', $to . ' 23:59:59')
            ->orderBy('s.transaction_at', 'DESC')->orderBy('s.id', 'DESC');
        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, ['posted', 'voided'], true)) {
            $b->where('s.status', $status);
        }
        $customer = trim((string) ($filters['customer'] ?? ''));
        if ($customer !== '') {
            $b->groupStart()->like('c.name', $customer)->orLike('c.code', $customer)->groupEnd();
        }
        $type = trim((string) ($filters['transaction_type'] ?? ''));
        if ($type !== '') {
            $b->where('s.transaction_type', $type);
        }
        $saleNo = trim((string) ($filters['sale_no'] ?? ''));
        if ($saleNo !== '') {
            $b->like('s.sale_no', $saleNo);
        }
        return $b;
    }

    /** @return array{0:string,1:string} validated from/to dates, today by default */
    public static function range(?string $from, ?string $to): array
    {
        $from = trim((string) $from) ?: date('Y-m-d');
        $to = trim((string) $to) ?: $from;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $from;
        }
        return $from > $to ? [$to, $from] : [$from, $to];
    }

    /** Id of the branch's sale with this uuid (BL-028), null when there is none. */
    public function idForUuid(string $uuid, int $locationId): ?int
    {
        $row = $this->db->table('sales')->select('id')->where(['uuid' => strtolower($uuid), 'location_id' => $locationId])->get()->getRowArray();
        return $row ? (int) $row['id'] : null;
    }

    /** Sale header, lines, payments and inventory movements; null when not in this branch. */
    public function details(int $saleId, int $locationId): ?array
    {
        $sale = $this->db->table('sales s')
            ->select('s.*,c.code customer_code,c.name customer_name,c.phone customer_phone,u.full_name created_by_name,vu.full_name voided_by_name')
            ->join('customers c', 'c.id=s.customer_id', 'left')
            ->join('users u', 'u.id=s.created_by', 'left')
            ->join('users vu', 'vu.id=s.voided_by', 'left')
            ->where(['s.id' => $saleId, 's.location_id' => $locationId])
            ->get()->getRowArray();
        if (!$sale) {
            return null;
        }
        $items = $this->db->table('sale_items si')
            ->select('si.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg,cu.unit_code customer_unit_code')
            ->join('cylinder_types ct', 'ct.id=si.cylinder_type_id', 'left')
            ->join('cylinder_units cu', 'cu.id=si.customer_cylinder_unit_id', 'left')
            ->where('si.sale_id', $saleId)->orderBy('si.line_no')
            ->get()->getResultArray();
        $movements = $this->db->table('inventory_movements im')
            ->select('im.*,cu.unit_code,ct.code cylinder_code,ct.name cylinder_name')
            ->join('cylinder_units cu', 'cu.id=im.cylinder_unit_id', 'left')
            ->join('cylinder_types ct', 'ct.id=im.cylinder_type_id', 'left')
            ->where('im.source_id', $saleId)
            ->where('im.location_id', $locationId)
            ->whereIn('im.source_type', ['sale', 'sale_void', 'security_deposit', 'cylinder_return'])
            ->orderBy('im.id')->get()->getResultArray();
        $payments = $this->db->table('sale_payments')->where('sale_id', $saleId)->orderBy('id')->get()->getResultArray();

        return ['sale' => $sale, 'items' => $items, 'payments' => $payments, 'movements' => $movements];
    }

    /** Everything the receipt layouts print; null when the sale is not in this branch. */
    public function receiptData(int $saleId, int $locationId): ?array
    {
        $sale = $this->db->table('sales s')
            ->select('s.*,c.code customer_code,c.name customer_name,c.phone customer_phone')
            ->join('customers c', 'c.id=s.customer_id', 'left')
            ->where('s.id', $saleId)->where('s.location_id', $locationId)
            ->get()->getRowArray();
        if (!$sale) {
            return null;
        }
        return [
            'sale' => $sale,
            'items' => $this->db->table('sale_items')->where('sale_id', $saleId)->orderBy('line_no')->get()->getResultArray(),
            'payments' => $this->db->table('sale_payments')->where('sale_id', $saleId)->orderBy('id')->get()->getResultArray(),
            'shopSettings' => (new \App\Models\ShopSettingsModel())->forLocation($locationId),
            'location' => $this->db->table('locations')->where('id', $locationId)->get()->getRowArray() ?: [],
        ];
    }
}
