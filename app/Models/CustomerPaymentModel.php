<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerPaymentModel extends Model
{
    protected $table         = 'customer_payments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
    protected $allowedFields = [
        'customer_id', 'payment_date', 'amount', 'payment_mode', 'notes', 'created_by',
    ];

    public function forCustomer(int $customerId): array
    {
        return $this->where('customer_id', $customerId)
            ->orderBy('payment_date', 'DESC')
            ->findAll();
    }

    /**
     * Total credit received for a date (feeds Daily Cash / Sale Report's
     * "Credit Received" column).
     */
    public function totalForDate(?string $date = null): float
    {
        $date = $date ?? date('Y-m-d');

        $row = $this->selectSum('amount', 'total')
            ->where('payment_date', $date)
            ->first();

        return (float) ($row['total'] ?? 0);
    }
}
