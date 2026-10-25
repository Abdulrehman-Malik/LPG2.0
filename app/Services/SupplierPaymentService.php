<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * Supplier account payment (BL-017; moved unchanged from the SupplierPayments controller).
 * The payment may not exceed the supplier's payable at the branch (SupplierBalanceService);
 * a cash payment goes out of the branch's open counter cash session.
 */
class SupplierPaymentService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param array{supplier_id:int|string,amount:float|string,payment_mode:string,transaction_at?:string,reference_no?:string,notes?:string} $input
     * @return array{id:int,payment_no:string}
     */
    public function post(array $input, int $userId, int $locationId): array
    {
        $db = $this->db;
        $at = (new TransactionDatePolicy())->resolve((string) ($input['transaction_at'] ?? ''), $locationId);
        $supplier = (int) ($input['supplier_id'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);
        $mode = (string) ($input['payment_mode'] ?? '');
        if (!$supplier || $amount <= 0 || !in_array($mode, PaymentModes::payable($locationId), true)) {
            throw new RuntimeException('Invalid supplier payment.');
        }
        PaymentModes::assertReference($mode, $input['reference_no'] ?? null, $locationId);

        $db->transBegin();
        try {
            $supplierRow = BusinessContext::supplierVisibleTo($supplier, $locationId)
                ? $db->query('SELECT * FROM suppliers WHERE id=? AND is_active=1 FOR UPDATE', [$supplier])->getRowArray()
                : null;
            if (!$supplierRow) {
                throw new RuntimeException('Supplier is invalid or inactive.');
            }
            $outstanding = (new SupplierBalanceService($db))->balance($supplierRow, $locationId);
            if ($amount > $outstanding + 0.01) {
                throw new RuntimeException('Supplier payment cannot exceed the outstanding balance of Rs. ' . number_format(max(0, $outstanding), 2) . '.');
            }
            $no = (new DocumentNumberService($db))->next('supplier_payment', $locationId, $at);
            $db->table('supplier_payments')->insert([
                'payment_no' => $no, 'location_id' => $locationId, 'supplier_id' => $supplier, 'status' => 'posted',
                'amount' => $amount, 'payment_mode' => $mode, 'payment_at' => $at,
                'reference_no' => trim((string) ($input['reference_no'] ?? '')) ?: null,
                'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
                'created_by' => $userId,
            ]);
            $id = (int) $db->insertID();
            if ($mode === 'cash') {
                $cash = new CashService();
                $session = $cash->openSessionForLocation($locationId);
                if (!$session) {
                    throw new RuntimeException('Open the counter cash session before a cash supplier payment.');
                }
                $cash->postGeneric((int) $session['id'], 'supplier_payment', 'out', $amount, 'supplier_payment', $id, $userId, 'Supplier cash payment', $at);
            }
            if (!$db->transStatus()) {
                throw new RuntimeException('Supplier payment failed.');
            }
            AuditService::log('CREATE', 'supplier_payment', $id, null, ['payment_no' => $no, 'supplier_id' => $supplier, 'amount' => $amount, 'payment_mode' => $mode], $userId, $locationId);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return ['id' => $id, 'payment_no' => $no];
    }
}
