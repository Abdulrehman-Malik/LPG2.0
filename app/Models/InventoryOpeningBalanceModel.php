<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryOpeningBalanceModel extends Model
{
    protected $table='inventory_opening_balances';
    protected $primaryKey='id';
    protected $returnType='array';
    protected $useTimestamps=false;
    protected $allowedFields=['location_id','inventory_date','inventory_type','cylinder_type_id','quantity','comments','created_by','created_at'];

    public function gasStockByOpeningIds(int $locationId, array $openingIds): array
    {
        if (!$openingIds) {
            return [];
        }

        $rows = $this->db->table('cylinder_units')
            ->select('source_id, SUM(gas_weight_kg) AS gas_stock')
            ->where('location_id', $locationId)
            ->where('source_type', 'opening')
            ->whereIn('source_id', $openingIds)
            ->where('status', 'filled')
            ->groupBy('source_id')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['source_id']] = (float) $row['gas_stock'];
        }

        return $result;
    }
}