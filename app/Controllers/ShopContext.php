<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;

class ShopContext extends Controller
{
    public function select()
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        $locationId = (int) $this->request->getPost('location_id');

        if ($userId <= 0 || !(new ShopScopeService())->canAccessShop($locationId, $userId)) {
            return redirect()->back()->with('error', 'You do not have access to the selected shop.');
        }

        $previousLocationId = (int) (session()->get('location_id') ?? 0);
        if ($previousLocationId === $locationId) {
            return redirect()->back();
        }

        session()->set('location_id', $locationId);
        \App\Libraries\TenantContext::setBranch($locationId);
        AuditService::log(
            'SHOP_CONTEXT_SWITCH',
            'location',
            $locationId,
            ['location_id' => $previousLocationId],
            ['location_id' => $locationId],
            $userId,
            $locationId
        );

        $location = \Config\Database::connect()->table('locations')
            ->select('name')
            ->where('id', $locationId)
            ->get()
            ->getRowArray();

        return redirect()->back()->with(
            'success',
            'Active shop changed to ' . (string) ($location['name'] ?? 'selected shop') . '.'
        );
    }
}
