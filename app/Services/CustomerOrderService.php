<?php

namespace App\Services;

use App\Models\GasRateModel;
use Config\Database;
use RuntimeException;

class CustomerOrderService
{
    protected $db;
    protected ConfigurationService $configuration;
    protected GasRateModel $rates;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->configuration = new ConfigurationService();
        $this->rates = new GasRateModel();
    }

    public function customerContext(int $accountId): ?array
    {
        if ($accountId <= 0) {
            return null;
        }

        return $this->db->table('customer_app_accounts ca')
            ->select('ca.id AS account_id,ca.username,ca.status AS account_status,c.id AS customer_id,c.name AS customer_name,c.phone AS customer_phone,c.city AS customer_city,c.address AS customer_address,c.vehicle_no,c.is_active AS customer_active,l.id AS shop_id,l.name AS shop_name,l.business_id,b.name AS business_name,b.is_active AS business_active,l.is_active AS shop_active')
            ->join('customers c', 'c.id=ca.customer_id', 'inner')
            ->join('locations l', 'l.id=c.location_id', 'inner')
            ->join('businesses b', 'b.id=l.business_id', 'inner')
            ->where('ca.id', $accountId)
            ->get()->getRowArray() ?: null;
    }

    protected function ensureCustomerOrderingEnabled(array $context): void
    {
        if (($context['account_status'] ?? '') !== 'approved'
            || (int) ($context['customer_active'] ?? 0) !== 1
            || (int) ($context['shop_active'] ?? 0) !== 1
            || (int) ($context['business_active'] ?? 0) !== 1) {
            throw new RuntimeException('Customer, shop or business is no longer active.');
        }

        $businessId = (int) $context['business_id'];
        $shopId = (int) $context['shop_id'];
        foreach (['customer_ordering_feature_enabled', 'orders_enabled'] as $key) {
            if (!(bool) $this->configuration->resolve($key, $businessId, $shopId)['value']) {
                throw new RuntimeException('Customer ordering is currently unavailable for this shop.');
            }
        }
    }

    public function catalogForCustomer(int $accountId): array
    {
        $context = $this->customerContext($accountId);
        if (!$context) {
            throw new RuntimeException('The customer app account is not linked to an active customer.');
        }
        $this->ensureCustomerOrderingEnabled($context);

        $rows = $this->db->table('business_catalog_products p')
            ->select('p.id AS product_id,p.business_id,p.cylinder_type_id,p.sale_mode,p.display_name,p.description,p.quantity_unit,p.min_quantity,p.quantity_step,p.sort_order,ct.code AS cylinder_code,ct.name AS cylinder_name,ct.capacity_kg,ct.empty_cylinder_price,a.is_active AS shop_product_active')
            ->join('shop_product_availability a', 'a.business_catalog_product_id=p.id AND a.business_id=p.business_id', 'inner')
            ->join('cylinder_types ct', 'ct.id=p.cylinder_type_id', 'inner')
            ->where('p.business_id', (int) $context['business_id'])
            ->where('a.location_id', (int) $context['shop_id'])
            ->where(['p.is_active' => 1, 'a.is_active' => 1, 'ct.is_active' => 1])
            ->orderBy('p.sort_order')
            ->orderBy('p.display_name')
            ->get()->getResultArray();

        $gasRate = $this->rates->currentKgRate(null, (int) $context['shop_id']);
        $products = [];
        foreach ($rows as $row) {
            $cylinderRate = $this->effectiveCylinderRate($row, (int) $context['shop_id']);
            $mode = (string) $row['sale_mode'];
            $priced = match ($mode) {
                'sell_gas_only' => $gasRate !== null && $gasRate > 0,
                'sell_filled' => $gasRate !== null && $gasRate > 0 && $cylinderRate !== null && $cylinderRate > 0,
                'sell_empty' => $cylinderRate !== null && $cylinderRate > 0,
                default => false,
            };

            $products[] = [
                'id' => (int) $row['product_id'],
                'name' => $row['display_name'],
                'description' => $row['description'],
                'sale_mode' => $mode,
                'quantity_unit' => $row['quantity_unit'],
                'minimum_quantity' => (float) $row['min_quantity'],
                'quantity_step' => (float) $row['quantity_step'],
                'cylinder_type' => [
                    'id' => (int) $row['cylinder_type_id'],
                    'code' => $row['cylinder_code'],
                    'name' => $row['cylinder_name'],
                    'capacity_kg' => (float) $row['capacity_kg'],
                ],
                'price' => [
                    'gas_rate_per_kg' => $mode === 'sell_empty' ? 0.0 : (float) ($gasRate ?? 0),
                    'cylinder_rate' => (float) ($cylinderRate ?? 0),
                    'price_available' => $priced,
                ],
                'shop_id' => (int) $context['shop_id'],
                'shop_name' => $context['shop_name'],
            ];
        }

        return $products;
    }

    protected function effectiveCylinderRate(array $product, int $shopId): ?float
    {
        $configured = $this->rates->currentCylinderRate((int) $product['cylinder_type_id'], null, $shopId);
        if ($configured !== null && $configured > 0) {
            return $configured;
        }

        $master = (float) ($product['empty_cylinder_price'] ?? 0);
        return $master > 0 ? $master : null;
    }

    protected function normalizeItems(array $items): array
    {
        if (!$items || count($items) > 40) {
            throw new RuntimeException('An order must contain between 1 and 40 line items.');
        }

        $normalized = [];
        $seen = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new RuntimeException('Invalid order line ' . ($index + 1) . '.');
            }
            $productId = (int) ($item['product_id'] ?? $item['business_catalog_product_id'] ?? 0);
            $quantityRaw = $item['quantity'] ?? null;
            if ($productId <= 0 || !is_numeric($quantityRaw)) {
                throw new RuntimeException('Each order line needs a product ID and numeric quantity.');
            }
            if (isset($seen[$productId])) {
                throw new RuntimeException('A product may appear only once in an order. Combine its quantity into one line.');
            }
            $seen[$productId] = true;
            $quantity = round((float) $quantityRaw, 3);
            if (!is_finite($quantity) || $quantity <= 0) {
                throw new RuntimeException('Order quantities must be positive.');
            }
            $notes = trim((string) ($item['notes'] ?? ''));
            if (mb_strlen($notes) > 500) {
                throw new RuntimeException('Order line notes cannot exceed 500 characters.');
            }

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'notes' => $notes !== '' ? $notes : null,
            ];
        }

        return $normalized;
    }

    protected function canonicalRequest(string $method, array $items, string $address, string $city, string $notes): string
    {
        $canonical = [
            'fulfilment_method' => $method,
            'delivery_address' => trim($address),
            'delivery_city' => trim($city),
            'customer_notes' => trim($notes),
            'items' => $items,
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    protected function quoteItems(int $businessId, int $shopId, array $items): array
    {
        $quoted = [];
        $subtotal = 0.0;
        $gasRate = $this->rates->currentKgRate(null, $shopId);

        foreach ($items as $index => $item) {
            $row = $this->db->query(
                'SELECT p.id AS product_id,p.business_id,p.cylinder_type_id,p.sale_mode,p.display_name,
                        p.quantity_unit,p.min_quantity,p.quantity_step,ct.name AS cylinder_name,
                        ct.capacity_kg,ct.empty_cylinder_price,a.is_active AS shop_product_active
                 FROM business_catalog_products p
                 JOIN shop_product_availability a
                   ON a.business_catalog_product_id=p.id AND a.business_id=p.business_id
                 JOIN cylinder_types ct ON ct.id=p.cylinder_type_id
                 WHERE p.id=? AND p.business_id=? AND a.location_id=?
                   AND p.is_active=1 AND a.is_active=1 AND ct.is_active=1
                 LIMIT 1 FOR UPDATE',
                [(int) $item['product_id'], $businessId, $shopId]
            )->getRowArray();

            if (!$row) {
                throw new RuntimeException('Product #' . (int) $item['product_id'] . ' is no longer available at this shop.');
            }

            $quantity = (float) $item['quantity'];
            $minimum = (float) $row['min_quantity'];
            $step = (float) $row['quantity_step'];
            if ($quantity + 0.000001 < $minimum || $step <= 0
                || abs((($quantity - $minimum) / $step) - round(($quantity - $minimum) / $step)) > 0.0001) {
                throw new RuntimeException($row['display_name'] . ' quantity does not meet the shop minimum or quantity step.');
            }

            $mode = (string) $row['sale_mode'];
            if (in_array($mode, ['sell_filled', 'sell_empty'], true) && floor($quantity) !== $quantity) {
                throw new RuntimeException($row['display_name'] . ' must be ordered in whole cylinders.');
            }

            $cylinderRate = $this->effectiveCylinderRate($row, $shopId) ?? 0.0;
            $gasKg = 0.0;
            $gasRateSnapshot = 0.0;
            $cylinderRateSnapshot = 0.0;
            $lineTotal = 0.0;
            $selectedUnitIds = [];

            if ($mode === 'sell_gas_only') {
                if ($gasRate === null || $gasRate <= 0) {
                    throw new RuntimeException('No effective gas/kg rate is available for ' . $row['display_name'] . '.');
                }
                $gasKg = $quantity;
                $gasRateSnapshot = $gasRate;
                $lineTotal = $gasKg * $gasRateSnapshot;
            } elseif ($mode === 'sell_filled') {
                if ($gasRate === null || $gasRate <= 0 || $cylinderRate <= 0) {
                    throw new RuntimeException('An effective gas and cylinder rate is required for ' . $row['display_name'] . '.');
                }
                $selectedUnitIds = array_values(array_unique(array_map('intval', is_array($item['selected_cylinder_unit_ids'] ?? null) ? $item['selected_cylinder_unit_ids'] : [])));
                if ($selectedUnitIds) {
                    if (count($selectedUnitIds) !== (int) $quantity || min($selectedUnitIds) <= 0) {
                        throw new RuntimeException('Select exactly ' . (int) $quantity . ' physical cylinder(s) for ' . $row['display_name'] . '.');
                    }
                    $units = $this->db->table('cylinder_units')->select('id,location_id,cylinder_type_id,status,gas_weight_kg')
                        ->where('location_id', $shopId)->where('cylinder_type_id', (int) $row['cylinder_type_id'])
                        ->where('status', 'filled')->whereIn('id', $selectedUnitIds)->get()->getResultArray();
                    if (count($units) !== (int) $quantity) {
                        throw new RuntimeException('One or more selected filled cylinders are no longer available for ' . $row['display_name'] . '.');
                    }
                    $byId = [];
                    foreach ($units as $unit) $byId[(int) $unit['id']] = $unit;
                    $gasKg = 0.0;
                    foreach ($selectedUnitIds as $unitId) $gasKg += (float) $byId[$unitId]['gas_weight_kg'];
                } else {
                    // Before physical cylinders are selected, the customer sees a nominal-capacity estimate.
                    $gasKg = $quantity * (float) $row['capacity_kg'];
                }
                $gasRateSnapshot = $gasRate;
                $cylinderRateSnapshot = $cylinderRate;
                $lineTotal = ($gasKg * $gasRateSnapshot) + ($quantity * $cylinderRateSnapshot);
            } elseif ($mode === 'sell_empty') {
                if ($cylinderRate <= 0) {
                    throw new RuntimeException('An effective empty-cylinder price is required for ' . $row['display_name'] . '.');
                }
                $selectedUnitIds = array_values(array_unique(array_map('intval', is_array($item['selected_cylinder_unit_ids'] ?? null) ? $item['selected_cylinder_unit_ids'] : [])));
                if ($selectedUnitIds) {
                    if (count($selectedUnitIds) !== (int) $quantity || min($selectedUnitIds) <= 0) {
                        throw new RuntimeException('Select exactly ' . (int) $quantity . ' physical empty cylinder(s) for ' . $row['display_name'] . '.');
                    }
                    $units = $this->db->table('cylinder_units')->select('id')
                        ->where('location_id', $shopId)->where('cylinder_type_id', (int) $row['cylinder_type_id'])
                        ->where('status', 'empty')->whereIn('id', $selectedUnitIds)->get()->getResultArray();
                    if (count($units) !== (int) $quantity) {
                        throw new RuntimeException('One or more selected empty cylinders are no longer available for ' . $row['display_name'] . '.');
                    }
                }
                $cylinderRateSnapshot = $cylinderRate;
                $lineTotal = $quantity * $cylinderRateSnapshot;
            } else {
                throw new RuntimeException('The product has an unsupported sale mode.');
            }

            $lineTotal = round($lineTotal, 2);
            $subtotal += $lineTotal;
            $quoted[] = [
                'product_id' => (int) $row['product_id'],
                'business_id' => $businessId,
                'cylinder_type_id' => (int) $row['cylinder_type_id'],
                'sale_mode' => $mode,
                'product_name_snapshot' => (string) $row['display_name'],
                'quantity_unit' => (string) $row['quantity_unit'],
                'quantity' => $quantity,
                'estimated_gas_kg' => round($gasKg, 3),
                'gas_rate_snapshot' => round($gasRateSnapshot, 4),
                'cylinder_rate_snapshot' => round($cylinderRateSnapshot, 4),
                'line_total' => $lineTotal,
                'selected_cylinder_unit_ids' => $selectedUnitIds ?? [],
                'notes' => $item['notes'],
            ];
        }

        return ['items' => $quoted, 'subtotal' => round($subtotal, 2)];
    }

    protected function deliveryFee(int $businessId, int $shopId, string $method): float
    {
        if ($method === 'collection') {
            return 0.0;
        }
        return round((float) $this->configuration->resolve('delivery_fee', $businessId, $shopId)['value'], 2);
    }

    protected function orderById(int $orderId, ?int $accountId = null): ?array
    {
        $builder = $this->db->table('customer_orders o')
            ->select('o.*,l.name AS shop_name,b.name AS business_name')
            ->join('locations l', 'l.id=o.location_id')
            ->join('businesses b', 'b.id=o.business_id')
            ->where('o.id', $orderId);

        if ($accountId !== null) {
            $builder->where('o.customer_app_account_id', $accountId);
        }
        $order = $builder->get()->getRowArray();
        if (!$order) {
            return null;
        }

        $order['items'] = $this->db->table('customer_order_items')
            ->where('order_id', $orderId)
            ->orderBy('line_no')
            ->get()->getResultArray();
        $order['status_history'] = $this->db->table('customer_order_status_history')
            ->select('from_status,to_status,actor_type,reason,created_at')
            ->where(['order_id' => $orderId, 'business_id' => (int) $order['business_id']])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()->getResultArray();
        return $order;
    }

    protected function statusHistory(int $orderId, int $businessId, ?string $fromStatus, string $toStatus, string $actorType, ?int $accountId, ?int $userId, ?string $reason, ?string $requestId): void
    {
        $this->db->table('customer_order_status_history')->insert([
            'order_id' => $orderId,
            'business_id' => $businessId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'customer_app_account_id' => $accountId,
            'user_id' => $userId,
            'reason' => $reason,
            'request_id' => $requestId,
        ]);
    }

    public function create(int $accountId, array $payload, string $idempotencyKey): array
    {
        $context = $this->customerContext($accountId);
        if (!$context) {
            throw new RuntimeException('The customer app account is not linked to an active customer.');
        }
        $this->ensureCustomerOrderingEnabled($context);

        $method = strtolower(trim((string) ($payload['fulfilment_method'] ?? '')));
        if (!in_array($method, ['delivery', 'collection'], true)) {
            throw new RuntimeException('Choose delivery or collection.');
        }
        $businessId = (int) $context['business_id'];
        $shopId = (int) $context['shop_id'];
        foreach ([
            'delivery' => 'delivery_enabled',
            'collection' => 'collection_enabled',
        ] as $fulfilment => $key) {
            if ($method === $fulfilment && !(bool) $this->configuration->resolve($key, $businessId, $shopId)['value']) {
                throw new RuntimeException('This shop does not currently offer ' . $fulfilment . '.');
            }
        }

        if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $idempotencyKey)) {
            throw new RuntimeException('Send an Idempotency-Key of 8–100 letters, numbers, dots, underscores, colons or dashes.');
        }

        $items = $this->normalizeItems(is_array($payload['items'] ?? null) ? $payload['items'] : []);
        $deliveryAddress = trim((string) ($payload['delivery_address'] ?? $context['customer_address'] ?? ''));
        $deliveryCity = trim((string) ($payload['delivery_city'] ?? $context['customer_city'] ?? ''));
        $customerNotes = trim((string) ($payload['customer_notes'] ?? ''));
        if (mb_strlen($customerNotes) > 1000) {
            throw new RuntimeException('Order notes cannot exceed 1000 characters.');
        }
        if ($method === 'delivery' && $deliveryAddress === '') {
            throw new RuntimeException('A delivery address is required.');
        }
        if (mb_strlen($deliveryAddress) > 500 || mb_strlen($deliveryCity) > 80) {
            throw new RuntimeException('Delivery address or city is too long.');
        }

        $fingerprint = $this->canonicalRequest($method, $items, $deliveryAddress, $deliveryCity, $customerNotes);
        $this->db->transBegin();
        try {
            $existing = $this->db->table('customer_orders')
                ->where(['customer_app_account_id' => $accountId, 'idempotency_key' => $idempotencyKey])
                ->get()->getRowArray();
            if ($existing) {
                if (!hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
                    $this->db->transRollback();
                    throw new RuntimeException('IDEMPOTENCY_KEY_REUSED: this key was already used for a different order request.');
                }
                $this->db->transCommit();
                return $this->orderById((int) $existing['id'], $accountId) ?? $existing;
            }

            $quote = $this->quoteItems($businessId, $shopId, $items);
            $deliveryFee = $this->deliveryFee($businessId, $shopId, $method);
            $total = round($quote['subtotal'] + $deliveryFee, 2);
            $orderNo = 'O' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

            $this->db->table('customer_orders')->insert([
                'business_id' => $businessId,
                'location_id' => $shopId,
                'customer_id' => (int) $context['customer_id'],
                'customer_app_account_id' => $accountId,
                'order_no' => $orderNo,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'price_revision' => 1,
                'price_reconfirmation_required' => 0,
                'price_reconfirmed_at' => null,
                'status' => 'pending',
                'fulfilment_method' => $method,
                'customer_name_snapshot' => (string) $context['customer_name'],
                'customer_phone_snapshot' => $context['customer_phone'],
                'delivery_address_snapshot' => $method === 'delivery' ? $deliveryAddress : null,
                'delivery_city_snapshot' => $deliveryCity !== '' ? $deliveryCity : null,
                'delivery_fee' => $deliveryFee,
                'subtotal' => $quote['subtotal'],
                'discount_amount' => 0,
                'total_amount' => $total,
                'customer_notes' => $customerNotes !== '' ? $customerNotes : null,
            ]);
            $orderId = (int) $this->db->insertID();

            foreach ($quote['items'] as $index => $item) {
                $this->db->table('customer_order_items')->insert([
                    'order_id' => $orderId,
                    'business_id' => $businessId,
                    'line_no' => $index + 1,
                    'business_catalog_product_id' => $item['product_id'],
                    'cylinder_type_id' => $item['cylinder_type_id'],
                    'sale_mode' => $item['sale_mode'],
                    'product_name_snapshot' => $item['product_name_snapshot'],
                    'quantity_unit' => $item['quantity_unit'],
                    'quantity' => $item['quantity'],
                    'estimated_gas_kg' => $item['estimated_gas_kg'],
                    'gas_rate_snapshot' => $item['gas_rate_snapshot'],
                    'cylinder_rate_snapshot' => $item['cylinder_rate_snapshot'],
                    'line_total' => $item['line_total'],
                    'selected_cylinder_unit_ids' => !empty($item['selected_cylinder_unit_ids']) ? json_encode(array_values(array_map('intval', $item['selected_cylinder_unit_ids'])), JSON_THROW_ON_ERROR) : null,
                    'notes' => $item['notes'],
                ]);
            }

            $this->statusHistory($orderId, $businessId, null, 'pending', 'customer', $accountId, null, 'Order submitted by customer.', $idempotencyKey);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Order could not be saved.');
            }
            $this->db->transCommit();
            return $this->orderById($orderId, $accountId) ?? ['id' => $orderId, 'order_no' => $orderNo, 'status' => 'pending'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            if (str_contains($e->getMessage(), 'IDEMPOTENCY_KEY_REUSED:')) {
                throw $e;
            }
            // A concurrent retry may win the unique idempotency-key constraint.
            $existing = $this->db->table('customer_orders')
                ->where(['customer_app_account_id' => $accountId, 'idempotency_key' => $idempotencyKey])
                ->get()->getRowArray();
            if ($existing) {
                if (!hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
                    throw new RuntimeException('IDEMPOTENCY_KEY_REUSED: this key was already used for a different order request.');
                }
                return $this->orderById((int) $existing['id'], $accountId) ?? $existing;
            }
            throw $e;
        }
    }

    public function listForCustomer(int $accountId, ?string $status = null): array
    {
        $context = $this->customerContext($accountId);
        if (!$context) {
            throw new RuntimeException('The customer app account is not active.');
        }

        $builder = $this->db->table('customer_orders o')
            ->select('o.id,o.order_no,o.status,o.fulfilment_method,o.delivery_fee,o.subtotal,o.total_amount,o.price_revision,o.price_reconfirmation_required,o.created_at,o.updated_at,l.name AS shop_name')
            ->join('locations l', 'l.id=o.location_id')
            ->where('o.customer_app_account_id', $accountId)
            ->orderBy('o.created_at', 'DESC')
            ->orderBy('o.id', 'DESC');
        if ($status !== null && $status !== '') {
            if (!in_array($status, ['pending','accepted','rejected','preparing','ready','out_for_delivery','completed','cancelled','unable_to_fulfil'], true)) {
                throw new RuntimeException('Unsupported order status filter.');
            }
            $builder->where('o.status', $status);
        }
        return $builder->get()->getResultArray();
    }

    public function getForCustomer(int $accountId, int $orderId): ?array
    {
        return $this->orderById($orderId, $accountId);
    }

    public function cancelByCustomer(int $accountId, int $orderId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) > 500) {
            throw new RuntimeException('Cancellation reason cannot exceed 500 characters.');
        }

        $this->db->transBegin();
        try {
            $order = $this->db->query(
                'SELECT * FROM customer_orders WHERE id=? AND customer_app_account_id=? LIMIT 1 FOR UPDATE',
                [$orderId, $accountId]
            )->getRowArray();
            if (!$order) {
                $this->db->transRollback();
                throw new RuntimeException('Order not found.');
            }
            if ($order['status'] !== 'pending') {
                $this->db->transRollback();
                throw new RuntimeException('Only pending orders can be cancelled by the customer.');
            }

            $this->db->table('customer_orders')->where('id', $orderId)->update([
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason !== '' ? $reason : 'Cancelled by customer.',
            ]);
            $this->statusHistory($orderId, (int) $order['business_id'], 'pending', 'cancelled', 'customer', $accountId, null, $reason !== '' ? $reason : 'Cancelled by customer.', null);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Order cancellation could not be saved.');
            }
            $this->db->transCommit();
            return $this->orderById($orderId, $accountId) ?? [];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    protected function requoteOrder(array $order): array
    {
        $items = $this->db->table('customer_order_items')
            ->where('order_id', (int) $order['id'])
            ->orderBy('line_no')
            ->get()->getResultArray();
        $input = [];
        foreach ($items as $item) {
            $selectedUnitIds = [];
            if (!empty($item['selected_cylinder_unit_ids'])) {
                $decoded = json_decode((string) $item['selected_cylinder_unit_ids'], true);
                if (is_array($decoded)) $selectedUnitIds = array_values(array_unique(array_map('intval', $decoded)));
            }
            $input[] = [
                'product_id' => (int) $item['business_catalog_product_id'],
                'quantity' => (float) $item['quantity'],
                'selected_cylinder_unit_ids' => $selectedUnitIds,
                'notes' => $item['notes'],
            ];
        }

        $quote = $this->quoteItems((int) $order['business_id'], (int) $order['location_id'], $input);
        $fee = $this->deliveryFee((int) $order['business_id'], (int) $order['location_id'], (string) $order['fulfilment_method']);
        return [
            'items' => $quote['items'],
            'subtotal' => $quote['subtotal'],
            'delivery_fee' => $fee,
            'total' => round($quote['subtotal'] + $fee, 2),
        ];
    }

    protected function decodeSelectedUnitIds($value): array
    {
        if (is_string($value)) {
            if (trim($value) === '') return [];
            $value = json_decode($value, true);
        }
        if (!is_array($value)) return [];
        $ids = array_values(array_unique(array_map('intval', $value)));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    protected function quoteDiffers(array $order, array $quote, array $oldItems): bool
    {
        if (abs((float) $order['subtotal'] - $quote['subtotal']) > 0.009
            || abs((float) $order['delivery_fee'] - $quote['delivery_fee']) > 0.009
            || abs((float) $order['total_amount'] - $quote['total']) > 0.009
            || count($oldItems) !== count($quote['items'])) {
            return true;
        }

        foreach ($oldItems as $index => $old) {
            $new = $quote['items'][$index] ?? null;
            if (!$new || (int) $old['business_catalog_product_id'] !== (int) $new['product_id']
                || (string) $old['sale_mode'] !== (string) $new['sale_mode']
                || (string) $old['quantity_unit'] !== (string) $new['quantity_unit']
                || abs((float) $old['quantity'] - (float) $new['quantity']) > 0.0001
                || abs((float) $old['estimated_gas_kg'] - (float) $new['estimated_gas_kg']) > 0.0001
                || abs((float) $old['gas_rate_snapshot'] - (float) $new['gas_rate_snapshot']) > 0.0001
                || abs((float) $old['cylinder_rate_snapshot'] - (float) $new['cylinder_rate_snapshot']) > 0.0001
                || abs((float) $old['line_total'] - (float) $new['line_total']) > 0.009
                || $this->decodeSelectedUnitIds($old['selected_cylinder_unit_ids'] ?? null) !== $this->decodeSelectedUnitIds($new['selected_cylinder_unit_ids'] ?? null)) {
                return true;
            }
        }
        return false;
    }

    protected function saveNewQuote(array $order, array $quote, bool $requiresConfirmation): void
    {
        foreach ($quote['items'] as $index => $item) {
            $this->db->table('customer_order_items')
                ->where(['order_id' => (int) $order['id'], 'line_no' => $index + 1])
                ->update([
                    'business_catalog_product_id' => $item['product_id'],
                    'cylinder_type_id' => $item['cylinder_type_id'],
                    'sale_mode' => $item['sale_mode'],
                    'product_name_snapshot' => $item['product_name_snapshot'],
                    'quantity_unit' => $item['quantity_unit'],
                    'quantity' => $item['quantity'],
                    'estimated_gas_kg' => $item['estimated_gas_kg'],
                    'gas_rate_snapshot' => $item['gas_rate_snapshot'],
                    'cylinder_rate_snapshot' => $item['cylinder_rate_snapshot'],
                    'line_total' => $item['line_total'],
                    'notes' => $item['notes'],
                ]);
        }

        $this->db->table('customer_orders')->where('id', (int) $order['id'])->update([
            'subtotal' => $quote['subtotal'],
            'delivery_fee' => $quote['delivery_fee'],
            'total_amount' => $quote['total'],
            'price_revision' => (int) $order['price_revision'] + 1,
            'price_reconfirmation_required' => $requiresConfirmation ? 1 : 0,
            'price_reconfirmed_at' => null,
        ]);
    }

    public function reconfirmPrice(int $accountId, int $orderId): array
    {
        $this->db->transBegin();
        try {
            $order = $this->db->query(
                'SELECT * FROM customer_orders WHERE id=? AND customer_app_account_id=? LIMIT 1 FOR UPDATE',
                [$orderId, $accountId]
            )->getRowArray();
            if (!$order) {
                $this->db->transRollback();
                throw new RuntimeException('Order not found.');
            }
            if ($order['status'] !== 'pending' || (int) $order['price_reconfirmation_required'] !== 1) {
                $this->db->transRollback();
                throw new RuntimeException('This order does not need price reconfirmation.');
            }

            $oldItems = $this->db->table('customer_order_items')
                ->where('order_id', $orderId)->orderBy('line_no')->get()->getResultArray();
            $quote = $this->requoteOrder($order);
            if ($this->quoteDiffers($order, $quote, $oldItems)) {
                $this->saveNewQuote($order, $quote, true);
                $newRevision = (int) $order['price_revision'] + 1;
                $this->statusHistory($orderId, (int) $order['business_id'], 'pending', 'pending', 'customer', $accountId, null, 'A newer quote was generated; customer review is required again.', 'price-reconfirm');
                $this->db->transCommit();
                return [
                    'status' => 'reconfirmation_required',
                    'message' => 'The quote changed again. Review the updated total and confirm again.',
                    'order' => $this->orderById($orderId, $accountId),
                    'price_revision' => $newRevision,
                ];
            }

            $this->db->table('customer_orders')->where('id', $orderId)->update([
                'price_reconfirmation_required' => 0,
                'price_reconfirmed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->statusHistory($orderId, (int) $order['business_id'], 'pending', 'pending', 'customer', $accountId, null, 'Customer reconfirmed quoted price revision ' . (int) $order['price_revision'] . '.', 'price-reconfirm');
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Price reconfirmation could not be saved.');
            }
            $this->db->transCommit();
            return [
                'status' => 'price_reconfirmed',
                'message' => 'Price confirmed. The order is still waiting for shop acceptance.',
                'order' => $this->orderById($orderId, $accountId),
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function listForShop(int $userId, int $shopId, ?string $status = null): array
    {
        if (!(new ShopScopeService())->canAccessShop($shopId, $userId)) {
            throw new RuntimeException('You do not have access to this shop.');
        }

        $businessId = (int) ($this->db->table('locations')->select('business_id')->where('id', $shopId)->get()->getRowArray()['business_id'] ?? 0);
        if ($businessId <= 0) {
            return [];
        }

        $builder = $this->db->table('customer_orders o')
            ->select('o.id,o.order_no,o.status,o.fulfilment_method,o.customer_name_snapshot,o.customer_phone_snapshot,o.delivery_address_snapshot,o.delivery_city_snapshot,o.delivery_fee,o.subtotal,o.total_amount,o.price_revision,o.price_reconfirmation_required,o.created_at,o.updated_at')
            ->where(['o.location_id' => $shopId, 'o.business_id' => $businessId])
            ->orderBy('o.created_at', 'ASC')
            ->orderBy('o.id', 'ASC');
        if ($status !== null && $status !== '') {
            if (!in_array($status, ['pending','accepted','rejected','preparing','ready','out_for_delivery','completed','cancelled','unable_to_fulfil'], true)) {
                throw new RuntimeException('Unsupported order status filter.');
            }
            $builder->where('o.status', $status);
        }
        return $builder->get()->getResultArray();
    }

    public function getForShop(int $userId, int $shopId, int $orderId): ?array
    {
        if (!(new ShopScopeService())->canAccessShop($shopId, $userId)) {
            return null;
        }
        $order = $this->db->table('customer_orders')
            ->where(['id' => $orderId, 'location_id' => $shopId])
            ->get()->getRowArray();
        return $order ? $this->orderById($orderId, null) : null;
    }

    public function fulfill(int $userId, int $shopId, int $orderId, array $payload): array
    {
        if (!(new ShopScopeService())->canAccessShop($shopId, $userId)) {
            throw new RuntimeException('You do not have access to this shop.');
        }

        $requestedLines = is_array($payload['fulfillment_lines'] ?? null) ? $payload['fulfillment_lines'] : [];
        $payments = is_array($payload['payments'] ?? null) ? $payload['payments'] : [];
        if (!$requestedLines) {
            throw new RuntimeException('Physical cylinder/source selection is required for fulfillment.');
        }
        if (!$payments) {
            throw new RuntimeException('At least one payment is required to fulfill an order.');
        }

        $lineSelections = [];
        foreach ($requestedLines as $selection) {
            if (!is_array($selection)) throw new RuntimeException('Invalid fulfillment line selection.');
            $lineNo = (int) ($selection['line_no'] ?? 0);
            if ($lineNo <= 0 || isset($lineSelections[$lineNo])) {
                throw new RuntimeException('Fulfillment line numbers must be unique positive values.');
            }
            $lineSelections[$lineNo] = $selection;
        }

        $this->db->transBegin();
        try {
            $order = $this->db->query(
                'SELECT * FROM customer_orders WHERE id=? AND location_id=? LIMIT 1 FOR UPDATE',
                [$orderId, $shopId]
            )->getRowArray();
            if (!$order) throw new RuntimeException('Order not found in this shop.');
            if ((string) $order['status'] === 'completed' && !empty($order['pos_sale_id'])) {
                $this->db->transCommit();
                return ['status' => 'completed', 'message' => 'This order has already been fulfilled.', 'order' => $this->orderById($orderId, null)];
            }
            if (!in_array((string) $order['status'], ['accepted', 'preparing', 'ready', 'out_for_delivery'], true)) {
                throw new RuntimeException('Only an accepted order can be fulfilled.');
            }
            if ((int) $order['price_reconfirmation_required'] === 1) {
                throw new RuntimeException('PRICE_RECONFIRMATION_REQUIRED: customer must confirm the current quote before fulfillment.');
            }

            $oldItems = $this->db->table('customer_order_items')->where('order_id', $orderId)->orderBy('line_no')->get()->getResultArray();
            if (count($lineSelections) !== count($oldItems)) {
                throw new RuntimeException('Provide a fulfillment selection for every order line.');
            }

            $quoteInput = [];
            $saleLines = [];
            foreach ($oldItems as $item) {
                $lineNo = (int) $item['line_no'];
                if (!isset($lineSelections[$lineNo])) throw new RuntimeException('Missing fulfillment selection for order line ' . $lineNo . '.');
                $selection = $lineSelections[$lineNo];
                $mode = (string) $item['sale_mode'];
                $quantity = (float) $item['quantity'];
                $selectedIds = array_values(array_unique(array_map('intval', is_array($selection['selected_cylinder_unit_ids'] ?? null) ? $selection['selected_cylinder_unit_ids'] : [])));
                $sourceId = (int) ($selection['source_cylinder_unit_id'] ?? 0);

                if (in_array($mode, ['sell_filled', 'sell_empty'], true)) {
                    if (count($selectedIds) !== (int) $quantity || min($selectedIds) <= 0) {
                        throw new RuntimeException('Select exactly ' . (int) $quantity . ' physical cylinder(s) for order line ' . $lineNo . '.');
                    }
                } elseif ($mode === 'sell_gas_only' && $sourceId <= 0) {
                    throw new RuntimeException('Select a filled source cylinder for gas line ' . $lineNo . '.');
                }

                $quoteInput[] = [
                    'product_id' => (int) $item['business_catalog_product_id'],
                    'quantity' => $quantity,
                    'selected_cylinder_unit_ids' => $selectedIds,
                    'notes' => $item['notes'],
                ];
                $saleLines[] = [
                    'sale_mode' => $mode,
                    'cylinder_type_id' => (int) $item['cylinder_type_id'],
                    'quantity' => $quantity,
                    'gas_weight_kg' => $quantity,
                    'gas_rate' => (float) $item['gas_rate_snapshot'],
                    'cylinder_rate' => (float) $item['cylinder_rate_snapshot'],
                    'source_cylinder_unit_id' => $sourceId > 0 ? $sourceId : null,
                    'selected_cylinder_unit_ids' => $selectedIds,
                    'notes' => $item['notes'],
                ];
            }
            foreach (array_keys($lineSelections) as $lineNo) {
                if (!in_array((int) $lineNo, array_map(static fn(array $item): int => (int) $item['line_no'], $oldItems), true)) {
                    throw new RuntimeException('Unknown order line number ' . $lineNo . '.');
                }
            }

            $quote = $this->quoteItems((int) $order['business_id'], $shopId, $quoteInput);
            if ($this->quoteDiffers($order, $quote, $oldItems)) {
                $this->saveNewQuote($order, $quote, true);
                $this->db->table('customer_orders')->where('id', $orderId)->update([
                    'status' => 'pending',
                    'accepted_by' => null,
                    'accepted_at' => null,
                ]);
                $this->statusHistory($orderId, (int) $order['business_id'], (string) $order['status'], 'pending', 'staff', null, $userId, 'Physical cylinder selection changed the final quote; customer reconfirmation is required.', null);
                if (!$this->db->transStatus()) throw new RuntimeException('Updated physical-cylinder quote could not be saved.');
                $this->db->transCommit();
                return [
                    'status' => 'reconfirmation_required',
                    'message' => 'The selected cylinders change the final price. The revised quote has been saved; customer reconfirmation and shop acceptance are required before posting.',
                    'order' => $this->orderById($orderId, null),
                ];
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $salePayload = [
            'customer_id' => (int) $order['customer_id'],
            'transaction_at' => date('Y-m-d H:i:s'),
            'notes' => 'Customer order ' . (string) $order['order_no'] . ($order['customer_notes'] ? ' | ' . (string) $order['customer_notes'] : ''),
            'discount_amount' => 0,
            'lines' => $saleLines,
            'payments' => $payments,
        ];
        (new SalesService())->postForCustomerOrder(
            $salePayload,
            $userId,
            $shopId,
            $orderId,
            (float) $order['delivery_fee']
        );

        return [
            'status' => 'completed',
            'message' => 'Order fulfilled and posted to POS.',
            'order' => $this->orderById($orderId, null),
        ];
    }

    public function updateStatus(int $userId, int $shopId, int $orderId, string $newStatus, string $reason = ''): array
    {
        $validStaffStatuses = ['accepted', 'rejected', 'preparing', 'ready', 'out_for_delivery', 'cancelled', 'unable_to_fulfil'];
        if (!in_array($newStatus, $validStaffStatuses, true)) {
            throw new RuntimeException('Unsupported status. An order cannot be marked completed until validated POS fulfilment is implemented.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) > 500) {
            throw new RuntimeException('Status reason cannot exceed 500 characters.');
        }
        if (in_array($newStatus, ['rejected', 'cancelled', 'unable_to_fulfil'], true) && $reason === '') {
            throw new RuntimeException('A reason is required for rejection, cancellation or inability to fulfil.');
        }

        if (!(new ShopScopeService())->canAccessShop($shopId, $userId)) {
            throw new RuntimeException('You do not have access to this shop.');
        }

        $this->db->transBegin();
        try {
            $order = $this->db->query(
                'SELECT * FROM customer_orders WHERE id=? AND location_id=? LIMIT 1 FOR UPDATE',
                [$orderId, $shopId]
            )->getRowArray();
            if (!$order) {
                $this->db->transRollback();
                throw new RuntimeException('Order not found in this shop.');
            }

            $from = (string) $order['status'];
            $allowed = [
                'pending' => ['accepted', 'rejected', 'cancelled'],
                'accepted' => ['preparing', 'ready', 'out_for_delivery', 'cancelled', 'unable_to_fulfil'],
                'preparing' => ['ready', 'out_for_delivery', 'cancelled', 'unable_to_fulfil'],
                'ready' => ['out_for_delivery', 'cancelled', 'unable_to_fulfil'],
                'out_for_delivery' => ['unable_to_fulfil'],
            ];
            if (!in_array($newStatus, $allowed[$from] ?? [], true)) {
                $this->db->transRollback();
                throw new RuntimeException('Transition from ' . $from . ' to ' . $newStatus . ' is not allowed.');
            }

            if ($newStatus === 'accepted') {
                if ((int) $order['price_reconfirmation_required'] === 1) {
                    $this->db->transRollback();
                    throw new RuntimeException('PRICE_RECONFIRMATION_REQUIRED: customer must confirm the revised quote before the shop can accept.');
                }

                $oldItems = $this->db->table('customer_order_items')
                    ->where('order_id', $orderId)->orderBy('line_no')->get()->getResultArray();
                try {
                    $quote = $this->requoteOrder($order);
                } catch (\Throwable $e) {
                    $this->db->transRollback();
                    throw new RuntimeException('ORDER_PRODUCT_UNAVAILABLE: ' . $e->getMessage());
                }

                if ($this->quoteDiffers($order, $quote, $oldItems)) {
                    $this->saveNewQuote($order, $quote, true);
                    $this->statusHistory($orderId, (int) $order['business_id'], 'pending', 'pending', 'staff', null, $userId, 'Quote changed at acceptance check; customer reconfirmation is required.', null);
                    if (!$this->db->transStatus()) {
                        throw new RuntimeException('Revised quote could not be saved.');
                    }
                    $this->db->transCommit();
                    return [
                        'status' => 'reconfirmation_required',
                        'message' => 'The quote changed. The order remains pending until the customer reviews and confirms the new price.',
                        'order' => $this->orderById($orderId, null),
                    ];
                }
            }

            $changes = ['status' => $newStatus];
            if ($newStatus === 'accepted') {
                $changes['accepted_by'] = $userId;
                $changes['accepted_at'] = date('Y-m-d H:i:s');
            } elseif ($newStatus === 'rejected') {
                $changes['rejected_by'] = $userId;
                $changes['rejected_at'] = date('Y-m-d H:i:s');
                $changes['rejection_reason'] = $reason;
            } elseif ($newStatus === 'cancelled') {
                $changes['cancelled_by'] = $userId;
                $changes['cancelled_at'] = date('Y-m-d H:i:s');
                $changes['cancellation_reason'] = $reason;
            }

            $this->db->table('customer_orders')->where('id', $orderId)->update($changes);
            $this->statusHistory($orderId, (int) $order['business_id'], $from, $newStatus, 'staff', null, $userId, $reason !== '' ? $reason : null, null);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Order status could not be saved.');
            }
            $this->db->transCommit();
            return $this->orderById($orderId, null) ?? [];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
