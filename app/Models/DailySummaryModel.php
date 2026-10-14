<?php

namespace App\Models;

use CodeIgniter\Model;

class DailySummaryModel extends Model
{
    protected $table         = 'daily_summaries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'summary_date', 'opening_cash', 'cash_sale', 'credit_sale', 'online_sale',
        'credit_received', 'total_sale', 'bowser_expense', 'other_expense',
        'hand_over_cash', 'balance', 'closed_by', 'is_closed',
    ];

    public function forDate(string $date): ?array
    {
        return $this->where('summary_date', $date)->first();
    }

    /**
     * Upserts a day's summary row. Balance = opening_cash + cash_sale + credit_received
     * - bowser_expense - other_expense - hand_over_cash (the till reconciliation figure).
     */
    public function upsertForDate(string $date, array $fields): void
    {
        $fields['balance'] = ($fields['opening_cash'] ?? 0)
            + ($fields['cash_sale'] ?? 0)
            + ($fields['credit_received'] ?? 0)
            - ($fields['bowser_expense'] ?? 0)
            - ($fields['other_expense'] ?? 0)
            - ($fields['hand_over_cash'] ?? 0);

        $existing = $this->forDate($date);

        if ($existing) {
            $this->update($existing['id'], $fields);
            return;
        }

        $fields['summary_date'] = $date;
        $this->insert($fields);
    }
}
