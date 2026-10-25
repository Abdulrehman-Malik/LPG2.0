<?php

namespace App\Controllers\Api\V1\Staff;

use App\Controllers\Api\V1\ApiController;
use App\Models\SupplierModel;
use App\Services\BusinessContext;
use App\Services\LedgerService;
use App\Services\PurchaseService;
use App\Services\SupplierBalanceService;
use App\Services\SupplierPaymentService;
use App\Services\SupplierService;
use Config\Database;

/** Staff API: suppliers, purchases and supplier payments of the selected shop (BL-025). */
class Purchasing extends ApiController
{
    public function suppliers()
    {
        $scope = $this->staffScope('SUPPLIER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $suppliers = (new SupplierModel())->visibleTo($shopId, (int) $this->request->getGet('active') === 1);
        $balances = (new SupplierBalanceService())->balances($suppliers, $shopId);
        foreach ($suppliers as &$s) {
            $s['payable'] = round((float) ($balances[(int) $s['id']] ?? 0), 2);
        }
        unset($s);
        [$page, $meta] = $this->paginateArray($suppliers);
        return $this->ok(['suppliers' => $page], $meta);
    }

    public function createSupplier()
    {
        return $this->saveSupplier(0);
    }

    public function updateSupplier(int $id)
    {
        return $this->saveSupplier($id);
    }

    private function saveSupplier(int $id)
    {
        $scope = $this->staffScope('SUPPLIER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $input = $this->payload();
        $balances = new SupplierBalanceService();
        if ($id > 0) {
            // An update sends only the fields it changes; the rest keep their stored values.
            $existing = BusinessContext::supplierVisibleTo($id, $shopId, false);
            if (!$existing) {
                return $this->fail(404, 'SUPPLIER_NOT_FOUND', 'Supplier not found for the selected shop.');
            }
            $input += $existing + ['opening_balance' => $balances->branchOpening($id, $shopId), 'supplier_scope' => $existing['location_id'] === null ? 'business' : 'branch'];
        } else {
            $input += ['is_active' => 1];
        }
        try {
            $result = (new SupplierService())->save($id, $input, $userId, $shopId);
        } catch (\RuntimeException $e) {
            return $this->fail(422, 'VALIDATION_FAILED', user_error_message($e));
        }
        $supplier = BusinessContext::supplierVisibleTo($result['id'], $shopId, false);
        $supplier['payable'] = round((float) ($balances->balances([$supplier], $shopId)[$result['id']] ?? 0), 2);
        return $this->ok(['supplier' => $supplier], null, $result['created'] ? 201 : 200);
    }
    public function supplierLedger(int $id)
    {
        $scope = $this->staffScope('SUPPLIER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $ledger = (new LedgerService())->supplierLedger($id, $shopId);
        return $ledger ? $this->ok($ledger) : $this->fail(404, 'SUPPLIER_NOT_FOUND', 'Supplier not found for the selected shop.');
    }

    public function purchases()
    {
        $scope = $this->staffScope('PURCHASE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $b = Database::connect()->table('purchases p')->select('p.*,s.name supplier_name,s.code supplier_code')
            ->join('suppliers s', 's.id=p.supplier_id', 'left')
            ->where('p.location_id', $shopId)->orderBy('p.transaction_at', 'DESC')->orderBy('p.id', 'DESC');
        if ($from = $this->request->getGet('from')) {
            $b->where('p.transaction_at >=', $from . ' 00:00:00');
        }
        if ($to = $this->request->getGet('to')) {
            $b->where('p.transaction_at <=', $to . ' 23:59:59');
        }
        [$rows, $meta] = $this->paginate($b);
        return $this->ok(['purchases' => $rows], $meta);
    }

    public function purchase(int $id)
    {
        $scope = $this->staffScope('PURCHASE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $db = Database::connect();
        $purchase = $db->table('purchases')->where(['id' => $id, 'location_id' => $shopId])->get()->getRowArray();
        if (!$purchase) {
            return $this->fail(404, 'PURCHASE_NOT_FOUND', 'Purchase not found in the selected shop.');
        }
        return $this->ok([
            'purchase' => $purchase,
            'items' => $db->table('purchase_items')->where('purchase_id', $id)->orderBy('line_no')->get()->getResultArray(),
            'payments' => $db->table('purchase_payments')->where('purchase_id', $id)->orderBy('id')->get()->getResultArray(),
        ]);
    }

    public function createPurchase()
    {
        $scope = $this->staffScope('PURCHASE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $p = $this->payload();
        if (!is_array($p['lines'] ?? null)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'lines must be an array.');
        }
        $input = array_intersect_key($p, array_flip(['supplier_id', 'lines', 'payments', 'discount_amount', 'notes', 'transaction_at', 'supplier_doc_no', 'empties_returned']));
        $input['payments'] = is_array($input['payments'] ?? null) ? $input['payments'] : [];
        try {
            $result = (new PurchaseService())->post($input, $userId, $shopId);
        } catch (\Throwable $e) {
            return $this->fail(422, 'PURCHASE_REJECTED', user_error_message($e));
        }
        return $this->ok($result, null, 201);
    }

    public function voidPurchase(int $id)
    {
        $scope = $this->staffScope('PURCHASE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        if (!Database::connect()->table('purchases')->where(['id' => $id, 'location_id' => $shopId])->countAllResults()) {
            return $this->fail(404, 'PURCHASE_NOT_FOUND', 'Purchase not found in the selected shop.');
        }
        try {
            $no = (new PurchaseService())->void($id, $userId, $shopId, (string) ($this->payload()['reason'] ?? ''));
        } catch (\Throwable $e) {
            return $this->fail(422, 'VOID_REJECTED', user_error_message($e));
        }
        return $this->ok(['id' => $id, 'purchase_no' => $no, 'status' => 'voided']);
    }

    public function createSupplierPayment()
    {
        $scope = $this->staffScope('PURCHASE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        try {
            $result = (new SupplierPaymentService())->post($this->payload(), $userId, $shopId);
        } catch (\Throwable $e) {
            return $this->fail(422, 'PAYMENT_REJECTED', user_error_message($e));
        }
        return $this->ok($result, null, 201);
    }
}
