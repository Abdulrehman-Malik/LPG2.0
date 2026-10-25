<?php

namespace App\Controllers\Api\V1\Staff;

use App\Controllers\Api\V1\ApiController;
use App\Models\ShopSettingsModel;
use App\Services\PermissionService;
use App\Services\SaleQueryService;
use App\Services\SalesService;

/**
 * Staff API: POS transactions of the selected shop (BL-022). Posting goes through the same
 * SalesService as the POS screen, so every business rule (stock, credit limit, walk-in cash only,
 * deposits, rates, rounding, back-dating) applies unchanged.
 */
class Sales extends ApiController
{
    public function index()
    {
        $scope = $this->staffScope('POS_HISTORY');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $filters = $this->request->getGet(['from', 'to', 'status', 'customer', 'transaction_type', 'sale_no']);
        [$from, $to] = SaleQueryService::range($filters['from'] ?? null, $filters['to'] ?? null);
        [$rows, $meta] = $this->paginate((new SaleQueryService())->historyBuilder($shopId, $filters));
        return $this->ok(['from' => $from, 'to' => $to, 'sales' => $rows], $meta);
    }

    /** @param string $id numeric id or the sale's uuid (BL-028) */
    public function show(string $id)
    {
        $scope = $this->staffScope('POS_HISTORY');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $queries = new SaleQueryService();
        $saleId = ctype_digit($id) ? (int) $id : $queries->idForUuid($id, $shopId);
        $details = $saleId ? $queries->details($saleId, $shopId) : null;
        if (!$details) {
            return $this->fail(404, 'SALE_NOT_FOUND', 'Sale not found in the selected shop.');
        }
        $details['can_void'] = PermissionService::allowsForUser('POS_VOID', $userId);
        return $this->ok($details);
    }

    public function create()
    {
        $scope = $this->staffScope('POS_SALE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        // A retried sale must never post twice: an Idempotency-Key header or a client uuid (which
        // ApiIdempotencyFilter uses as the key, BL-029) is required.
        $p = $this->payload();
        if (trim($this->request->getHeaderLine('Idempotency-Key')) === '' && empty($p['uuid'])) {
            return $this->fail(422, 'IDEMPOTENCY_KEY_REQUIRED', 'Send an Idempotency-Key header or a uuid with every sale.');
        }
        $type = trim((string) ($p['transaction_type'] ?? ''));
        $settings = (new ShopSettingsModel())->forLocation($shopId);
        if (!(new ShopSettingsModel())->isTransactionTypeVisible($settings, $type)) {
            return $this->fail(422, 'TRANSACTION_TYPE_DISABLED', 'This transaction type is not enabled for the selected shop.');
        }
        if (!is_array($p['lines'] ?? null) || !is_array($p['payments'] ?? null)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'lines and payments must be arrays.');
        }
        $fields = ['transaction_type', 'customer_id', 'transaction_at', 'discount_amount', 'security_deposit_amount', 'security_deposit_refund_amount',
            'custody_unit_ids', 'return_unit_ids', 'lines', 'payments', 'notes', 'stock_override_confirmed', 'rate_change_reason', 'uuid'];
        return $this->withClientUuid('sales', function () use ($p, $fields, $userId, $shopId) {
            try {
                $result = (new SalesService())->post(array_intersect_key($p, array_flip($fields)), $userId, $shopId);
            } catch (\Throwable $e) {
                return $this->fail(422, 'SALE_REJECTED', user_error_message($e));
            }
            $sale = (new SaleQueryService())->details((int) $result['id'], $shopId);
            return $this->ok(['id' => (int) $result['id'], 'uuid' => $sale['sale']['uuid'] ?? null, 'sale_no' => $result['sale_no'], 'sale' => $sale], null, 201);
        });
    }

    public function void(int $id)
    {
        $scope = $this->staffScope('POS_VOID');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        if (!(new SaleQueryService())->details($id, $shopId)) {
            return $this->fail(404, 'SALE_NOT_FOUND', 'Sale not found in the selected shop.');
        }
        $reason = trim((string) ($this->payload()['reason'] ?? $this->payload()['void_reason'] ?? ''));
        try {
            $saleNo = (new SalesService())->void($id, $userId, $shopId, $reason);
        } catch (\Throwable $e) {
            return $this->fail(422, 'VOID_REJECTED', user_error_message($e));
        }
        return $this->ok(['id' => $id, 'sale_no' => $saleNo, 'status' => 'voided']);
    }

    public function receipt(int $id)
    {
        $scope = $this->staffScope('POS_HISTORY');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $data = (new SaleQueryService())->receiptData($id, $shopId);
        if (!$data) {
            return $this->fail(404, 'SALE_NOT_FOUND', 'Sale not found in the selected shop.');
        }
        // Only what the receipt prints; shop settings also hold SMTP and backup credentials.
        $data['shopSettings'] = array_intersect_key($data['shopSettings'], array_flip(['receipt_title', 'receipt_footer', 'show_address_on_receipt']));
        $data['location'] = array_intersect_key($data['location'], array_flip(['id', 'code', 'name', 'address', 'city', 'phone']));
        return $this->ok($data);
    }
}
