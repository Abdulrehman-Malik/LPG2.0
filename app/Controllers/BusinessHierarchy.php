<?php

namespace App\Controllers;

use App\Services\BusinessHierarchyService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;

/** Business Hierarchy screen (Phase 9.7): tenants, Head Office users, shops and shop staff in one tree. */
class BusinessHierarchy extends Controller
{
    public function index()
    {
        if (!PermissionService::allows('APPLICATION_SETTINGS_VIEW')
            && !PermissionService::allows('APPLICATION_SETTINGS_MANAGE')
            && !PermissionService::allows('SHOP_ACCESS_MANAGE')) {
            return $this->response->setStatusCode(403)->setBody('You do not have permission to view the business hierarchy.');
        }
        $userId = (int) (session()->get('user_id') ?? 0);

        return view('business_hierarchy/index', [
            'title' => 'Business Hierarchy',
            'tree' => (new BusinessHierarchyService())->tree($userId),
            'canCreateShop' => PermissionService::allows('SHOP_ACCESS_MANAGE'),
            'canManageUsers' => PermissionService::allows('USER_MANAGE'),
            'isSystemAdmin' => (new ShopScopeService())->isSystemAdministrator($userId),
            'currentShopId' => (int) session()->get('location_id'),
            'selectedNode' => preg_match('/^(business|shop|user)-[0-9a-z-]+$/', (string) $this->request->getGet('node')) ? (string) $this->request->getGet('node') : '',
        ]);
    }
}
