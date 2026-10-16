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
        $today = date('Y-m-d');
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $from;

        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false) { $from = $today; $fromTs = strtotime($from); }
        if ($toTs === false || $toTs < $fromTs) $to = $from;

        return [
            'from' => $from,
            'to' => $to,
            'toExclusive' => date('Y-m-d', strtotime($to . ' +1 day')),
            'label' => $from === $to
                ? date('d M Y', strtotime($from))
                : date('d M Y', strtotime($from)) . ' — ' . date('d M Y', strtotime($to)),
            'isToday' => $from === $today && $to === $today,
        ];
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
