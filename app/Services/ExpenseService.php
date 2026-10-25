<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * Expense posting (BL-017; moved unchanged from the Expenses controller). A cash expense is
 * paid out of the branch's open counter cash session.
 */
class ExpenseService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** Active expense categories of the branch's business. */
    public function activeCategories(int $locationId): array
    {
        return $this->db->table('expense_categories')
            ->where('business_id', BusinessContext::businessIdForLocation($locationId))
            ->where('is_active', 1)->orderBy('name')
            ->get()->getResultArray();
    }

    /** Latest expenses of the branch with their category name. */
    public function recent(int $locationId, int $limit = 50): array
    {
        return $this->db->table('expenses e')->select('e.*,c.name category_name')
            ->join('expense_categories c', 'c.id=e.category_id')
            ->where('e.location_id', $locationId)
            ->orderBy('e.id', 'DESC')->limit($limit)
            ->get()->getResultArray();
    }

    /**
     * @param array{category_id:int|string,amount:float|string,payment_mode:string,transaction_at?:string,reference_no?:string,description?:string} $input
     * @return array{id:int,expense_no:string}
     */
    public function post(array $input, int $userId, int $locationId): array
    {
        $db = $this->db;
        $at = (new TransactionDatePolicy())->resolve((string) ($input['transaction_at'] ?? ''), $locationId);
        $amount = (float) ($input['amount'] ?? 0);
        $mode = (string) ($input['payment_mode'] ?? '');
        $categoryId = (int) ($input['category_id'] ?? 0);
        if ($amount <= 0 || !in_array($mode, PaymentModes::payable($locationId), true)) {
            throw new RuntimeException('Invalid expense.');
        }
        PaymentModes::assertReference($mode, $input['reference_no'] ?? null, $locationId);
        if (!BusinessContext::expenseCategory($categoryId, $locationId)) {
            throw new RuntimeException('Select a valid expense category.');
        }

        $db->transBegin();
        try {
            $no = (new DocumentNumberService($db))->next('expense', $locationId, $at);
            $db->table('expenses')->insert([
                'expense_no' => $no, 'location_id' => $locationId, 'category_id' => $categoryId, 'amount' => $amount,
                'payment_mode' => $mode, 'expense_at' => $at,
                'reference_no' => trim((string) ($input['reference_no'] ?? '')) ?: null,
                'description' => trim((string) ($input['description'] ?? '')) ?: null,
                'created_by' => $userId,
            ]);
            $id = (int) $db->insertID();
            if ($mode === 'cash') {
                $cash = new CashService();
                $session = $cash->openSessionForLocation($locationId);
                if (!$session) {
                    throw new RuntimeException('Open the counter cash session before a cash expense.');
                }
                $cash->postGeneric((int) $session['id'], 'expense', 'out', $amount, 'expense', $id, $userId, 'Expense cash', $at);
            }
            if (!$db->transStatus()) {
                throw new RuntimeException('Expense posting failed.');
            }
            AuditService::log('CREATE', 'expense', $id, null, ['expense_no' => $no, 'category_id' => $categoryId, 'amount' => $amount, 'payment_mode' => $mode], $userId, $locationId);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return ['id' => $id, 'expense_no' => $no];
    }
}
