<?php

namespace App\Controllers;

use App\Libraries\TenantContext;
use App\Services\CustomerBalanceService;
use App\Services\CustomerReceiptService;
use App\Services\PermissionService;
use CodeIgniter\Controller;

/** Customer Receipts screen; posting and history live in CustomerReceiptService (BL-017). */
class Receipts extends Controller
{
    private function guard()
    {
        return PermissionService::allows('POS_SALE') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $locationId = TenantContext::requireBranchId();
        $service = new CustomerReceiptService();
        $customers = $service->activeCustomers($locationId);
        $gasBalances = (new CustomerBalanceService())->gasBalances($locationId);
        $customerBalances = [];
        foreach ($customers as $customer) {
            $customerBalances[(int) $customer['id']] = $gasBalances[(int) $customer['id']] ?? 0.0;
        }
        return view('receipts/index', [
            'title' => 'Customer Receipts',
            'customers' => $customers,
            'customerBalances' => $customerBalances,
            'receipts' => $service->history($locationId, 100),
        ]);
    }

    public function save()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        try {
            $r = (new CustomerReceiptService())->post($this->request->getPost(), (int) session()->get('user_id'), TenantContext::requireBranchId());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', user_error_message($e));
        }
        return redirect()->to('/receipts')->with('success', 'Receipt ' . $r['receipt_no'] . ' posted. Current OS: Rs. ' . number_format($r['outstanding'], 2)
            . '; Received: Rs. ' . number_format($r['amount'], 2) . '; Balance after receipt: Rs. ' . number_format($r['balance_after'], 2) . '.');
    }
}
