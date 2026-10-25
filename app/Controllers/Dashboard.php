<?php

namespace App\Controllers;

use App\Services\PermissionService;
use App\Services\DashboardService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;

class Dashboard extends Controller
{
    private function guard()
    {
        return PermissionService::allows('DASHBOARD_VIEW')
            ? null
            : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    private function dateRange(): array
    {
        return DashboardService::range((string) $this->request->getGet('from'), (string) $this->request->getGet('to'));
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;

        // Dashboard totals are transaction-sensitive; prevent browsers/proxies from
        // reusing a stale rendered summary after a POS transaction is posted.
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', '0');

        $locationId = (int) (session()->get('location_id') ?? 0);
        $range = $this->dateRange();
        $service = new DashboardService();
        $data = $service->forBranch($locationId, $range);
        $branches = PermissionService::allows('REPORT_VIEW')
            ? $service->branchComparison($locationId, (new ShopScopeService())->accessibleShopIds(), $range)
            : [];

        return view('dashboard/index', $data + [
            'title' => 'Dashboard',
            'range' => $range,
            'branches' => $branches,
            'canCash' => PermissionService::allows('CASH_MANAGE'),
            'canReports' => PermissionService::allows('REPORT_VIEW'),
            'canTransfers' => PermissionService::allows('STOCK_TRANSFER_MANAGE'),
        ]);
    }
}
