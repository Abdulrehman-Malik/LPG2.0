<?php

namespace App\Controllers;

use App\Libraries\TenantContext;
use App\Models\SupplierModel;
use App\Services\PermissionService;
use App\Services\PurchaseService;
use App\Services\SupplierPaymentService;
use CodeIgniter\Controller;

/** Supplier Payments screen; posting lives in SupplierPaymentService (BL-017), shared with the API. */
class SupplierPayments extends Controller
{
    private function guard()
    {
        return PermissionService::allows('PURCHASE_MANAGE') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $locationId = TenantContext::requireBranchId();
        $suppliers = (new SupplierModel())->activeDirectory($locationId);
        $balances = array_map(static fn ($v) => round($v, 2), (new PurchaseService())->supplierBalances($suppliers, $locationId));
        return view('supplier_payments/index', ['title' => 'Supplier Payments', 'suppliers' => $suppliers, 'balances' => $balances]);
    }

    public function save()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        try {
            $result = (new SupplierPaymentService())->post($this->request->getPost(), (int) session()->get('user_id'), TenantContext::requireBranchId());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', user_error_message($e));
        }
        return redirect()->to('/supplier-payments')->with('success', 'Supplier payment ' . $result['payment_no'] . ' posted.');
    }
}
