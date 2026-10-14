<?php

namespace App\Models;

use CodeIgniter\Model;

class DailyFilledStockModel extends Model
{
    protected $table         = 'daily_filled_stock';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'stock_date', 'cylinder_type_id', 'opening_stock', 'received_stock',
        'sold_stock', 'adjustment',
    ];
    // closing_stock is a generated column — never write to it directly.

    public function forDate(string $date): array
    {
        return $this->select('daily_filled_stock.*, cylinder_types.label, cylinder_types.capacity_kg')
            ->join('cylinder_types', 'cylinder_types.id = daily_filled_stock.cylinder_type_id')
            ->where('stock_date', $date)
            ->orderBy('cylinder_types.sort_order', 'ASC')
            ->findAll();
    }

    /**
     * SUM(closing_stock) across all cylinder types for a date — dashboard's
     * "Cylinder Stock" KPI (unit count, not weight).
     */
    public function totalUnitsForDate(?string $date = null): int
    {
        $date = $date ?? date('Y-m-d');

        $row = $this->selectSum('closing_stock', 'total')
            ->where('stock_date', $date)
            ->first();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * SUM(closing_stock * capacity_kg) — dashboard's "Total Gas Stock (KG)" KPI.
     */
    public function totalWeightKgForDate(?string $date = null): float
    {
        $date = $date ?? date('Y-m-d');

        $row = $this->select('SUM(daily_filled_stock.closing_stock * cylinder_types.capacity_kg) AS total_kg')
            ->join('cylinder_types', 'cylinder_types.id = daily_filled_stock.cylinder_type_id')
            ->where('stock_date', $date)
            ->first();

        return (float) ($row['total_kg'] ?? 0);
    }

    /**
     * Carries yesterday's closing stock forward as today's opening stock for
     * every active cylinder type that doesn't already have a row for $date.
     * Call this once when a new business day's stock screen is first opened.
     */
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
                'stock_date'       => $date,
                'cylinder_type_id' => $type['id'],
                'opening_stock'    => $prevRow['closing_stock'] ?? 0,
                'received_stock'   => 0,
                'sold_stock'       => 0,
                'adjustment'       => 0,
            ]);
        }
    }
}
