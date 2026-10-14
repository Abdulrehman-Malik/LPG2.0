<?php

namespace App\Controllers\Api\V1\Staff;

use App\Services\ApiSessionService;
use App\Services\CustomerOrderService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;

class Orders extends Controller
{
    protected function principal(): ?array
    {
        $header = trim($this->request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match)) {
            return null;
        }
        return (new ApiSessionService())->authenticateAccessToken($match[1], 'staff');
    }

    protected function shopId(int $userId): ?int
    {
        $header = trim($this->request->getHeaderLine('X-Shop-Id'));
        $shopId = $header !== '' && ctype_digit($header)
            ? (int) $header
            : (new ShopScopeService())->defaultShopIdForUser($userId);

        if (!$shopId || !(new ShopScopeService())->canAccessShop((int) $shopId, $userId)) {
            return null;
        }
        return (int) $shopId;
    }

    protected function guard(string $permission): array
    {
        $principal = $this->principal();
        if (!$principal) {
            return ['response' => $this->response->setStatusCode(401)->setJSON([
                'error' => ['code' => 'INVALID_OR_EXPIRED_TOKEN', 'message' => 'A valid staff access token is required.'],
            ])];
        }

        $userId = (int) $principal['user_id'];
        if (!PermissionService::allowsForUser($permission, $userId)) {
            return ['response' => $this->response->setStatusCode(403)->setJSON([
                'error' => ['code' => 'PERMISSION_DENIED', 'message' => 'The staff account does not have the required order permission.'],
            ])];
        }

        $shopId = $this->shopId($userId);
        if (!$shopId) {
            return ['response' => $this->response->setStatusCode(403)->setJSON([
                'error' => ['code' => 'SHOP_ACCESS_DENIED', 'message' => 'Select a shop assigned to your staff account using X-Shop-Id.'],
            ])];
        }

        return ['principal' => $principal, 'user_id' => $userId, 'shop_id' => $shopId];
    }

    protected function apiError(int $status, string $code, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    public function index()
    {
        $guard = $this->guard('ORDER_VIEW');
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        try {
            $status = trim((string) $this->request->getGet('status'));
            return $this->response->setJSON(['data' => [
                'shop_id' => $guard['shop_id'],
                'orders' => (new CustomerOrderService())->listForShop($guard['user_id'], $guard['shop_id'], $status !== '' ? $status : null),
            ]]);
        } catch (\Throwable $e) {
            log_message('error', 'Staff order list failed: {message}', ['message' => $e->getMessage()]);
            return $this->apiError(503, 'ORDER_SERVICE_UNAVAILABLE', 'The order list is temporarily unavailable.');
        }
    }

    public function show(int $orderId)
    {
        $guard = $this->guard('ORDER_VIEW');
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $order = (new CustomerOrderService())->getForShop($guard['user_id'], $guard['shop_id'], $orderId);
        if (!$order) {
            return $this->apiError(404, 'ORDER_NOT_FOUND', 'Order not found in the selected shop.');
        }
        return $this->response->setJSON(['data' => ['order' => $order]]);
    }

    public function fulfill(int $orderId)
    {
        $guard = $this->guard('ORDER_MANAGE');
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $input = $this->request->getJSON(true);
        if (!is_array($input)) {
            $input = $this->request->getPost();
        }

        try {
            $result = (new CustomerOrderService())->fulfill(
                $guard['user_id'],
                $guard['shop_id'],
                $orderId,
                is_array($input) ? $input : []
            );
            return $this->response->setJSON(['data' => [
                'order' => $result['order'] ?? [],
                'status' => $result['status'] ?? null,
                'message' => $result['message'] ?? 'Order fulfillment processed.',
            ]]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'ORDER_PRICE_RECONFIRMATION_REQUIRED') || str_contains($message, 'PRICE_RECONFIRMATION_REQUIRED')) {
                return $this->apiError(409, 'PRICE_RECONFIRMATION_REQUIRED', 'The customer must review and reconfirm the current order total before fulfillment.');
            }
            if (str_contains($message, 'not found')) {
                return $this->apiError(404, 'ORDER_NOT_FOUND', 'Order not found in the selected shop.');
            }
            if (str_contains($message, 'You do not have access')) {
                return $this->apiError(403, 'SHOP_ACCESS_DENIED', 'You do not have access to this shop.');
            }
            if (str_contains($message, 'Only an accepted order') || str_contains($message, 'not eligible for physical-cylinder') || str_contains($message, 'changed before') || str_contains($message, 'unavailable') || str_contains($message, 'Insufficient') || str_contains($message, 'stock changed') || str_contains($message, 'counter cash session')) {
                return $this->apiError(409, 'POS_FULFILLMENT_CONFLICT', 'The order cannot be fulfilled with the current order state, stock, or cash-counter state. Refresh the order and try again.');
            }
            if (str_contains($message, 'Select ') || str_contains($message, 'Provide a fulfillment') || str_contains($message, 'fulfillment selection') || str_contains($message, 'line numbers') || str_contains($message, 'Unknown order line') || str_contains($message, 'At least one payment') || str_contains($message, 'payment')) {
                return $this->apiError(422, 'INVALID_FULFILLMENT_REQUEST', $message);
            }
            log_message('error', 'Staff order fulfillment failed: {message}', ['message' => $message]);
            return $this->apiError(503, 'POS_FULFILLMENT_UNAVAILABLE', 'The order could not be posted to POS right now.');
        }
    }

    public function updateStatus(int $orderId)
    {
        $guard = $this->guard('ORDER_MANAGE');
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $input = $this->request->getJSON(true);
        if (!is_array($input)) {
            $input = $this->request->getPost();
        }
        $status = trim((string) ($input['status'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? ''));

        try {
            $result = (new CustomerOrderService())->updateStatus(
                $guard['user_id'],
                $guard['shop_id'],
                $orderId,
                $status,
                $reason
            );
            return $this->response->setJSON(['data' => [
                'order' => $result['order'] ?? $result,
                'status' => $result['status'] ?? ($result['status'] ?? null),
                'message' => $result['message'] ?? 'Order status updated.',
            ]]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'PRICE_RECONFIRMATION_REQUIRED')) {
                return $this->apiError(409, 'PRICE_RECONFIRMATION_REQUIRED', 'Customer reconfirmation is pending.');
            }
            if (str_contains($message, 'ORDER_PRODUCT_UNAVAILABLE')) {
                return $this->apiError(409, 'ORDER_PRODUCT_UNAVAILABLE', 'One or more products are no longer orderable at this shop.');
            }
            if (str_contains($message, 'not found')) {
                return $this->apiError(404, 'ORDER_NOT_FOUND', 'Order not found in the selected shop.');
            }
            if (str_contains($message, 'not allowed') || str_contains($message, 'reason is required') || str_contains($message, 'Unsupported status')) {
                return $this->apiError(422, 'INVALID_ORDER_TRANSITION', $message);
            }
            log_message('error', 'Staff order status update failed: {message}', ['message' => $message]);
            return $this->apiError(503, 'ORDER_UPDATE_UNAVAILABLE', 'The order could not be updated right now.');
        }
    }
}
