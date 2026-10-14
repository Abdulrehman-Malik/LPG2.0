<?php

namespace App\Models;

use CodeIgniter\Model;

class ExpenseModel extends Model
{
    protected $table         = 'expenses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $allowedFields = [
        'expense_date', 'category', 'description', 'amount', 'created_by',
    ];

    public function totalForDate(?string $date = null, ?string $category = null): float
    {
        $date = $date ?? date('Y-m-d');

        $builder = $this->selectSum('amount', 'total')->where('expense_date', $date);

        if ($category !== null) {
            $builder->where('category', $category);
        }

        $row = $builder->first();

        return (float) ($row['total'] ?? 0);
    }
}
