<?php

namespace App\Controllers\Api\V1\Staff;

use App\Controllers\Api\V1\ApiController;
use App\Services\BranchReportService;
use App\Services\DashboardService;
use App\Services\ReportService;
use App\Services\SaleQueryService;
use App\Services\ShopScopeService;
use Config\Database;

/** Staff API: dashboard and reports of the selected shop or business (BL-027), read-only. */
class Reports extends ApiController
{
    public function dashboard()
    {
        $scope = $this->staffScope('DASHBOARD_VIEW');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $range = DashboardService::range($this->request->getGet('from'), $this->request->getGet('to'));
        return $this->ok(['from' => $range['from'], 'to' => $range['to']] + (new DashboardService())->forBranch($shopId, $range));
    }

    public function period()
    {
        $scope = $this->staffScope('REPORT_VIEW');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        [$from, $to] = SaleQueryService::range($this->request->getGet('from'), $this->request->getGet('to'));
        return $this->ok(['from' => $from, 'to' => $to] + (new ReportService())->periodReport($shopId, $from, $to));
    }

    public function custody()
    {
        $scope = $this->staffScope('REPORT_VIEW');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        return $this->ok((new BranchReportService())->custody($shopId));
    }

    /** Branch overview across the business's shops the user may access. */
    public function branches()
    {
        $scope = $this->staffScope('REPORT_VIEW');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, , $businessId] = $scope;
        $shopScope = new ShopScopeService();
        $ids = array_values(array_filter(
            $shopScope->accessibleShopIds($userId),
            static fn (int $id): bool => $shopScope->businessIdForShop($id, $userId) === $businessId
        ));
        $ids = $ids ? array_map('intval', array_column(Database::connect()->table('locations')->select('id')->whereIn('id', $ids)->where('is_active', 1)->get()->getResultArray(), 'id')) : [];
        $requested = array_map('intval', (array) ($this->request->getGet('branch_ids') ?? []));
        $selected = array_values(array_intersect($ids, $requested)) ?: $ids;
        $from = (string) ($this->request->getGet('from') ?: date('Y-m-01'));
        [$from, $to] = SaleQueryService::range($from, $this->request->getGet('to') ?: date('Y-m-d'));
        return $this->ok(['from' => $from, 'to' => $to] + (new ReportService())->branchSummary($selected, $from, $to));
    }
}
