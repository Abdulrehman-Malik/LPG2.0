<?php

namespace App\Controllers\Api\V1\Customer;

use App\Services\ApiSessionService;
use App\Services\CustomerOrderService;
use CodeIgniter\Controller;

class Orders extends Controller
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

    protected function payload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }
        $post = $this->request->getPost();
        return is_array($post) ? $post : [];
    }

    protected function apiError(int $status, string $code, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }

    protected function serviceError(\Throwable $e)
    {
        $message = $e->getMessage();
        if (str_contains($message, 'IDEMPOTENCY_KEY_REUSED')) {
            return $this->apiError(409, 'IDEMPOTENCY_KEY_REUSED', 'This idempotency key was already used for a different order request.');
        }
        if (str_contains($message, 'not found')) {
            return $this->apiError(404, 'ORDER_NOT_FOUND', $message);
        }
        if (str_contains($message, 'currently unavailable') || str_contains($message, 'no longer active')) {
            return $this->apiError(403, 'ORDERING_UNAVAILABLE', 'Customer ordering is unavailable because the customer, shop, or business is inactive or disabled.');
        }
        if (str_contains($message, 'reconfirmation') || str_contains($message, 'quantity') || str_contains($message, 'required') || str_contains($message, 'Choose') || str_contains($message, 'between')) {
            return $this->apiError(422, 'VALIDATION_FAILED', $message);
        }
        log_message('error', 'Customer order API failed: {message}', ['message' => $message]);
        return $this->apiError(503, 'ORDER_SERVICE_UNAVAILABLE', 'The order service is temporarily unavailable.');
    }

    public function index()
    {
        $accountId = $this->accountId();
        if (!$accountId) {
            return $this->apiError(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid customer access token is required.');
        }

        try {
            $status = trim((string) $this->request->getGet('status'));
            $rows = (new CustomerOrderService())->listForCustomer($accountId, $status !== '' ? $status : null);
            return $this->response->setJSON(['data' => ['orders' => $rows]]);
        } catch (\Throwable $e) {
            return $this->serviceError($e);
        }
    }

    public function show(int $orderId)
    {
        $accountId = $this->accountId();
        if (!$accountId) {
            return $this->apiError(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid customer access token is required.');
        }

        $order = (new CustomerOrderService())->getForCustomer($accountId, $orderId);
        if (!$order) {
            return $this->apiError(404, 'ORDER_NOT_FOUND', 'Order not found.');
        }

        return $this->response->setJSON(['data' => ['order' => $order]]);
    }

    public function create()
    {
        $accountId = $this->accountId();
        if (!$accountId) {
            return $this->apiError(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid customer access token is required.');
        }

        $payload = $this->payload();
        $key = trim($this->request->getHeaderLine('Idempotency-Key'));
        if ($key === '') {
            $key = trim((string) ($payload['idempotency_key'] ?? ''));
        }

        try {
            $order = (new CustomerOrderService())->create($accountId, $payload, $key);
            $status = !empty($order['id']) ? 201 : 200;
            return $this->response->setStatusCode($status)->setJSON([
                'data' => [
                    'order' => $order,
                    'message' => 'Order request saved. The shop must review and accept it before fulfilment.',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->serviceError($e);
        }
    }

    public function cancel(int $orderId)
    {
        $accountId = $this->accountId();
        if (!$accountId) {
            return $this->apiError(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid customer access token is required.');
        }

        $payload = $this->payload();
        try {
            $order = (new CustomerOrderService())->cancelByCustomer($accountId, $orderId, (string) ($payload['reason'] ?? ''));
            return $this->response->setJSON(['data' => ['order' => $order, 'message' => 'Pending order cancelled.']]);
        } catch (\Throwable $e) {
            return $this->serviceError($e);
        }
    }

    public function reconfirmPrice(int $orderId)
    {
        $accountId = $this->accountId();
        if (!$accountId) {
            return $this->apiError(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid customer access token is required.');
        }

        try {
            $result = (new CustomerOrderService())->reconfirmPrice($accountId, $orderId);
            return $this->response->setJSON(['data' => $result]);
        } catch (\Throwable $e) {
            return $this->serviceError($e);
        }
    }
}
