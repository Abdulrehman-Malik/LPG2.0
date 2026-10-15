<?php

namespace App\Controllers\Api\V1\Customer;

use App\Services\ConfigurationService;
use App\Services\CustomerOrderService;
use App\Services\ApiSessionService;
use CodeIgniter\Controller;

class Products extends Controller
{
    protected function accountId(): ?int
    {
        $header = trim($this->request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match)) {
            return null;
        }

        $principal = (new ApiSessionService())->authenticateAccessToken($match[1], 'customer');
        return $principal ? (int) $principal['customer_app_account_id'] : null;
    }

    public function index()
    {
        $accountId = $this->accountId();
        if (!$accountId) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => ['code' => 'INVALID_OR_EXPIRED_TOKEN', 'message' => 'A valid customer access token is required.'],
            ]);
        }

        try {
            $orders = new CustomerOrderService();
            $context = $orders->customerContext($accountId);
            if (!$context) {
                return $this->response->setStatusCode(403)->setJSON([
                    'error' => ['code' => 'CUSTOMER_NOT_AVAILABLE', 'message' => 'The linked customer is not active.'],
                ]);
            }

            $configuration = new ConfigurationService();
            $businessId = (int) $context['business_id'];
            $shopId = (int) $context['shop_id'];
            $products = $orders->catalogForCustomer($accountId);

            return $this->response->setJSON(['data' => [
                'shop' => ['id' => $shopId, 'name' => $context['shop_name']],
                'business' => ['id' => $businessId, 'name' => $context['business_name']],
                'fulfilment' => [
                    'delivery_enabled' => (bool) $configuration->resolve('delivery_enabled', $businessId, $shopId)['value'],
                    'collection_enabled' => (bool) $configuration->resolve('collection_enabled', $businessId, $shopId)['value'],
                    'delivery_fee' => round((float) $configuration->resolve('delivery_fee', $businessId, $shopId)['value'], 2),
                ],
                'products' => $products,
            ]]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'currently unavailable')) {
                return $this->response->setStatusCode(403)->setJSON([
                    'error' => ['code' => 'ORDERING_UNAVAILABLE', 'message' => $e->getMessage()],
                ]);
            }
            log_message('error', 'Customer catalog failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(503)->setJSON([
                'error' => ['code' => 'CATALOG_UNAVAILABLE', 'message' => 'The shop catalog is temporarily unavailable.'],
            ]);
        }
    }
}
