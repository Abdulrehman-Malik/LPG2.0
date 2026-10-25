<?php

namespace App\Controllers;

use App\Libraries\TenantContext;
use App\Services\CashService;
use App\Services\ExpenseService;
use App\Services\PermissionService;
use CodeIgniter\Controller;

/** Expenses screen; posting lives in ExpenseService (BL-017), shared with the staff API. */
class Expenses extends Controller
{
    private function guard()
    {
        return PermissionService::allows('EXPENSE_MANAGE') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $locationId = TenantContext::requireBranchId();
        $service = new ExpenseService();
        return view('expenses/index', [
            'title' => 'Expenses',
            'categories' => $service->activeCategories($locationId),
            'expenses' => $service->recent($locationId, 50),
            'cash' => (new CashService())->openSessionForLocation($locationId),
        ]);
    }

    public function save()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        try {
            $result = (new ExpenseService())->post($this->request->getPost(), (int) session()->get('user_id'), TenantContext::requireBranchId());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', user_error_message($e));
        }
        return redirect()->to('/expenses')->with('success', 'Expense ' . $result['expense_no'] . ' posted.');
    }
}
