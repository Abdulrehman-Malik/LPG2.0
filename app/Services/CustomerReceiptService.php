<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * Customer receipt posting (BL-017; moved unchanged from the Receipts controller so the web
 * screen and the API share it). The receipt reduces the customer's OS at the branch; a cash
 * receipt is also posted into the branch's open counter cash session.
 */
class CustomerReceiptService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** Active customers of the branch for the receipt form. */
    public function activeCustomers(int $locationId): array
    {
        return $this->db->table('customers')->where(['location_id' => $locationId, 'is_active' => 1])->orderBy('name')->get()->getResultArray();
    }

    /**
     * Money received at the branch, newest first: receipts from the Receipts screen and POS OS
     * settlements, plus non-credit POS sale and deposit payments (with their source).
     */
    public function history(int $locationId, int $limit = 100): array
    {
        return $this->db->query("SELECT * FROM (
 SELECT cr.receipt_at,cr.receipt_no,c.name AS customer_name,cr.amount,cr.payment_mode,cr.reference_no,cr.notes,cr.status,
        CASE WHEN cr.notes LIKE 'OS settlement collected with sale %' THEN 'POS — OS Settlement' ELSE 'Customer Receipts screen' END AS source
 FROM customer_receipts cr
 LEFT JOIN customers c ON c.id=cr.customer_id
 WHERE cr.location_id=?
 UNION ALL
 SELECT sp.payment_at AS receipt_at,
        CONCAT(s.sale_no,CASE sp.payment_type WHEN 'security_deposit' THEN ' / Deposit' WHEN 'security_deposit_refund' THEN ' / Deposit Refund' ELSE ' / POS' END) AS receipt_no,
        c.name AS customer_name,sp.amount,sp.payment_mode,sp.reference_no,
        CASE sp.payment_type WHEN 'security_deposit' THEN CONCAT('POS security deposit payment for ',s.sale_no) WHEN 'security_deposit_refund' THEN CONCAT('POS security deposit refund for ',s.sale_no) ELSE CONCAT('POS sale payment for ',s.sale_no) END AS notes,
        s.status,
        CASE sp.payment_type WHEN 'security_deposit' THEN 'POS — Security Deposit' WHEN 'security_deposit_refund' THEN 'POS — Security Deposit Refund' ELSE CONCAT('POS — ',REPLACE(s.transaction_type,'_',' '),' / Sale Payment') END AS source
 FROM sale_payments sp
 JOIN sales s ON s.id=sp.sale_id
 LEFT JOIN customers c ON c.id=s.customer_id
 WHERE s.location_id=? AND sp.payment_mode <> 'credit'
) AS receipt_history
ORDER BY receipt_at DESC,receipt_no DESC
LIMIT " . max(1, $limit), [$locationId, $locationId])->getResultArray();
    }

    /**
     * @param array{customer_id:int|string,amount:float|string,payment_mode:string,transaction_at?:string,reference_no?:string,notes?:string} $input
     * @return array{id:int,receipt_no:string,outstanding:float,amount:float,balance_after:float}
     */
    public function post(array $input, int $userId, int $locationId): array
    {
        $db = $this->db;
        $at = (new TransactionDatePolicy())->resolve((string) ($input['transaction_at'] ?? ''), $locationId);
        $amount = (float) ($input['amount'] ?? 0);
        $mode = (string) ($input['payment_mode'] ?? '');
        $customer = (int) ($input['customer_id'] ?? 0);
        if ($amount <= 0 || !$customer || !in_array($mode, PaymentModes::receivable($locationId), true)) {
            throw new RuntimeException('Invalid receipt.');
        }
        PaymentModes::assertReference($mode, $input['reference_no'] ?? null, $locationId);

        $db->transBegin();
        try {
            $customerRow = $db->query('SELECT * FROM customers WHERE id=? AND location_id=? AND is_active=1 FOR UPDATE', [$customer, $locationId])->getRowArray();
            if (!$customerRow) {
                throw new RuntimeException('Customer is invalid or inactive.');
            }
            $creditRow = $db->table('sales')->selectSum('credit_amount', 'credit')->selectSum('return_gas_ledger_amount', 'return_ledger')
                ->where(['customer_id' => $customer, 'status' => 'posted', 'location_id' => $locationId])->get()->getRowArray();
            $paidRow = $db->table('customer_receipts')->selectSum('amount', 'paid')
                ->where(['customer_id' => $customer, 'status' => 'posted', 'location_id' => $locationId])->get()->getRowArray();
            $outstanding = (float) $customerRow['opening_balance'] + (float) ($creditRow['credit'] ?? 0) + (float) ($creditRow['return_ledger'] ?? 0) - (float) ($paidRow['paid'] ?? 0);

            $no = (new DocumentNumberService($db))->next('receipt', $locationId, $at);
            $db->table('customer_receipts')->insert([
                'receipt_no' => $no, 'location_id' => $locationId, 'customer_id' => $customer, 'status' => 'posted',
                'amount' => $amount, 'payment_mode' => $mode, 'receipt_at' => $at,
                'reference_no' => trim((string) ($input['reference_no'] ?? '')) ?: null,
                'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
                'created_by' => $userId,
            ]);
            $id = (int) $db->insertID();
            if ($mode === 'cash') {
                $cash = new CashService();
                $session = $cash->openSessionForLocation($locationId);
                if (!$session) {
                    throw new RuntimeException('Open the counter cash session before a cash receipt.');
                }
                $cash->postGeneric((int) $session['id'], 'customer_receipt', 'in', $amount, 'customer_receipt', $id, $userId, 'Customer cash receipt', $at);
            }
            if (!$db->transStatus()) {
                throw new RuntimeException('Receipt posting failed.');
            }
            AuditService::log('CREATE', 'customer_receipt', $id, null, ['receipt_no' => $no, 'customer_id' => $customer, 'amount' => $amount, 'payment_mode' => $mode], $userId, $locationId);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return ['id' => $id, 'receipt_no' => $no, 'outstanding' => $outstanding, 'amount' => $amount, 'balance_after' => $outstanding - $amount];
    }
}
