<?php

namespace App\Models;

use CodeIgniter\Model;

class DailyEmptyStockModel extends Model
{
    protected $table         = 'daily_empty_stock';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'stock_date', 'cylinder_type_id', 'opening_stock', 'received_from_cust',
        'sent_for_refill', 'adjustment',
    ];
    // closing_stock is a generated column — never write to it directly.

    public function forDate(string $date): array
    {
        return $this->select('daily_empty_stock.*, cylinder_types.label, cylinder_types.capacity_kg')
            ->join('cylinder_types', 'cylinder_types.id = daily_empty_stock.cylinder_type_id')
            ->where('stock_date', $date)
            ->orderBy('cylinder_types.sort_order', 'ASC')
            ->findAll();
    }

    public function ensureOpeningRowsForDate(string $date, CylinderTypeModel $cylinderTypeModel): void
    {
        $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));

        foreach ($cylinderTypeModel->activeOrdered() as $type) {
            $exists = $this->where('stock_date', $date)
                ->where('cylinder_type_id', $type['id'])
                ->first();

            if ($exists) {
                continue;
            }

            $prevRow = $this->where('stock_date', $previousDate)
                ->where('cylinder_type_id', $type['id'])
                ->first();

            $this->insert([
                'stock_date'         => $date,
                'cylinder_type_id'   => $type['id'],
                'opening_stock'      => $prevRow['closing_stock'] ?? 0,
                'received_from_cust' => 0,
                'sent_for_refill'    => 0,
                'adjustment'         => 0,
            ]);
        }
    }
}
