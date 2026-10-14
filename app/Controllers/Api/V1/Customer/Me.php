<?php

namespace App\Controllers\Api\V1\Customer;

use App\Services\ApiSessionService;
use CodeIgniter\Controller;
use Config\Database;

class Me extends Controller
{
    public function show()
    {
        $header = trim($this->request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match)) {
            return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'A valid bearer access token is required.']]);
        }

        $principal = (new ApiSessionService())->authenticateAccessToken($match[1], 'customer');
        if (!$principal) {
            return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'INVALID_OR_EXPIRED_TOKEN', 'message' => 'The customer session is invalid or expired.']]);
        }

        $row = Database::connect()->table('customer_app_accounts ca')
            ->select('ca.id AS app_account_id,ca.username,c.id AS customer_id,c.name,c.phone,c.city,c.address,c.vehicle_no,l.id AS shop_id,l.name AS shop_name,b.id AS business_id,b.name AS business_name')
            ->join('customers c', 'c.id=ca.customer_id', 'inner')
            ->join('locations l', 'l.id=c.location_id', 'inner')
            ->join('businesses b', 'b.id=l.business_id', 'inner')
            ->where('ca.id', (int) $principal['customer_app_account_id'])
            ->where(['ca.status' => 'approved', 'c.is_active' => 1, 'l.is_active' => 1, 'b.is_active' => 1])
            ->get()->getRowArray();

        if (!$row) {
            return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'CUSTOMER_NOT_AVAILABLE', 'message' => 'The linked customer or shop is no longer active.']]);
        }

        return $this->response->setJSON(['data' => ['customer' => [
            'id' => (int) $row['customer_id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'city' => $row['city'],
            'address' => $row['address'],
            'vehicle_no' => $row['vehicle_no'],
            'shop' => ['id' => (int) $row['shop_id'], 'name' => $row['shop_name']],
            'business' => ['id' => (int) $row['business_id'], 'name' => $row['business_name']],
        ]]]);
    }
}
