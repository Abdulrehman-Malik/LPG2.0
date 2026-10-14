<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\CustomerOrderService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;

class OrderManagement extends Controller
{
    protected function context(bool $manage = false): array
    {
        $permission = $manage ? 'ORDER_MANAGE' : 'ORDER_VIEW';
        if (!PermissionService::allows($permission)) {
            return ['error' => $this->response->setStatusCode(403)->setBody('You do not have permission to view or manage customer orders.')];
        }

        $userId = (int) (session()->get('user_id') ?? 0);
        $shopId = (int) (session()->get('location_id') ?? 0);
        if ($userId <= 0 || $shopId <= 0 || !(new ShopScopeService())->canAccessShop($shopId, $userId)) {
            return ['error' => $this->response->setStatusCode(403)->setBody('An authorized shop context is required.')];
        }

        return ['user_id' => $userId, 'shop_id' => $shopId];
    }

    public function index()
    {
        $context = $this->context();
        if (isset($context['error'])) {
            return $context['error'];
        }

        $status = trim((string) $this->request->getGet('status'));
        try {
            $orders = (new CustomerOrderService())->listForShop(
                (int) $context['user_id'],
                (int) $context['shop_id'],
                $status !== '' ? $status : null
            );
        } catch (\Throwable $e) {
            log_message('error', 'Web order list failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('dashboard'))->with('error', 'Customer orders could not be loaded. Check the database migration status and application logs.');
        }

        return view('customer_orders/index', [
            'title' => 'Customer Orders',
            'orders' => $orders,
            'status' => $status,
            'canManage' => PermissionService::allows('ORDER_MANAGE'),
        ]);
    }

    public function show(int $orderId)
    {
        $context = $this->context();
        if (isset($context['error'])) {
            return $context['error'];
        }

        $service = new CustomerOrderService();
        $order = $service->getForShop((int) $context['user_id'], (int) $context['shop_id'], $orderId);
        if (!$order) {
            return $this->response->setStatusCode(404)->setBody('Order not found in the selected shop.');
        }

        $fulfillment = null;
        $canManage = PermissionService::allows('ORDER_MANAGE');
        if ($canManage && in_array((string) $order['status'], ['accepted', 'preparing', 'ready', 'out_for_delivery'], true)
            && (int) ($order['price_reconfirmation_required'] ?? 0) !== 1 && empty($order['pos_sale_id'])) {
            try {
                $fulfillment = $service->fulfillmentOptions((int) $context['user_id'], (int) $context['shop_id'], $orderId);
            } catch (\Throwable $e) {
                // Details remain available even when stock is not currently fulfillable.
                log_message('notice', 'Order fulfillment options unavailable for order {orderId}: {message}', [
                    'orderId' => $orderId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return view('customer_orders/detail', [
            'title' => 'Order ' . (string) $order['order_no'],
            'order' => $order,
            'fulfillment' => $fulfillment,
            'canManage' => $canManage,
        ]);
    }

    public function updateStatus(int $orderId)
    {
        $context = $this->context(true);
        if (isset($context['error'])) {
            return $context['error'];
        }

        $status = trim((string) $this->request->getPost('status'));
        $reason = trim((string) $this->request->getPost('reason'));

        try {
            $result = (new CustomerOrderService())->updateStatus(
                (int) $context['user_id'],
                (int) $context['shop_id'],
                $orderId,
                $status,
                $reason
            );

            AuditService::log(
                'CUSTOMER_ORDER_STATUS_UPDATED',
                'customer_order',
                $orderId,
                null,
                ['requested_status' => $status, 'result_status' => (string) ($result['status'] ?? '')],
                (int) $context['user_id'],
                (int) $context['shop_id']
            );

            $message = $result['message'] ?? ('Order status updated to ' . str_replace('_', ' ', $status) . '.');
            return redirect()->to(site_url('customer-orders/' . $orderId))
                ->with(isset($result['message']) ? 'warning' : 'success', $message);
        } catch (\Throwable $e) {
            return redirect()->to(site_url('customer-orders/' . $orderId))
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function fulfill(int $orderId)
    {
        $context = $this->context(true);
        if (isset($context['error'])) {
            return $context['error'];
        }

        $inputLines = $this->request->getPost('fulfillment_lines');
        $inputPayments = $this->request->getPost('payments');
        $lines = [];
        foreach (is_array($inputLines) ? $inputLines : [] as $key => $line) {
            if (!is_array($line)) {
                continue;
            }
            $line['line_no'] = (int) ($line['line_no'] ?? $key);
            if (isset($line['selected_cylinder_unit_ids']) && !is_array($line['selected_cylinder_unit_ids'])) {
                $line['selected_cylinder_unit_ids'] = [$line['selected_cylinder_unit_ids']];
            }
            $lines[] = $line;
        }

        $payments = [];
        foreach (is_array($inputPayments) ? $inputPayments : [] as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $amountRaw = trim((string) ($payment['amount'] ?? ''));
            if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0) {
                continue;
            }
            $payments[] = [
                'payment_mode' => trim((string) ($payment['payment_mode'] ?? '')),
                'amount' => round((float) $amountRaw, 2),
                'reference_no' => trim((string) ($payment['reference_no'] ?? '')),
            ];
        }

        try {
            $result = (new CustomerOrderService())->fulfill(
                (int) $context['user_id'],
                (int) $context['shop_id'],
                $orderId,
                ['fulfillment_lines' => $lines, 'payments' => $payments]
            );

            AuditService::log(
                'CUSTOMER_ORDER_FULFILLMENT_REQUESTED',
                'customer_order',
                $orderId,
                null,
                ['result_status' => (string) ($result['status'] ?? '')],
                (int) $context['user_id'],
                (int) $context['shop_id']
            );

            $status = (string) ($result['status'] ?? '');
            $flash = $status === 'completed' ? 'success' : ($status === 'reconfirmation_required' ? 'warning' : 'info');
            return redirect()->to(site_url('customer-orders/' . $orderId))
                ->with($flash, (string) ($result['message'] ?? 'Fulfillment request processed.'));
        } catch (\Throwable $e) {
            return redirect()->to(site_url('customer-orders/' . $orderId))
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
