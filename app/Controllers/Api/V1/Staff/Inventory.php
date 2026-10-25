<?php

namespace App\Controllers\Api\V1\Staff;

use App\Controllers\Api\V1\ApiController;
use App\Services\BranchReportService;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use Config\Database;

/** Staff API: stock, physical cylinders, movement history, adjustments and transfers (BL-024). */
class Inventory extends ApiController
{
    public function stock()
    {
        $scope = $this->staffScope('INVENTORY_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        return $this->ok((new BranchReportService())->inventoryDetail($shopId));
    }

    public function units()
    {
        $scope = $this->staffScope('INVENTORY_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $b = Database::connect()->table('cylinder_units cu')
            ->select('cu.id,cu.unit_code,cu.status,cu.gas_weight_kg,cu.cylinder_type_id,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg,cu.custody_customer_id,cu.updated_at')
            ->join('cylinder_types ct', 'ct.id=cu.cylinder_type_id')
            ->where('cu.location_id', $shopId)->orderBy('ct.sort_order')->orderBy('cu.unit_code');
        if ($status = trim((string) $this->request->getGet('status'))) {
            $b->where('cu.status', $status);
        }
        if ($typeId = (int) $this->request->getGet('cylinder_type_id')) {
            $b->where('cu.cylinder_type_id', $typeId);
        }
        [$rows, $meta] = $this->paginate($b);
        return $this->ok(['units' => $rows], $meta);
    }

    public function movements()
    {
        $scope = $this->staffScope('INVENTORY_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $b = Database::connect()->table('inventory_movements im')
            ->select('im.*,ct.code cylinder_code,cu.unit_code')
            ->join('cylinder_types ct', 'ct.id=im.cylinder_type_id', 'left')
            ->join('cylinder_units cu', 'cu.id=im.cylinder_unit_id', 'left')
            ->where('im.location_id', $shopId)->orderBy('im.id', 'DESC');
        foreach (['inventory_type', 'source_type', 'direction'] as $f) {
            if (($v = trim((string) $this->request->getGet($f))) !== '') {
                $b->where('im.' . $f, $v);
            }
        }
        if ($typeId = (int) $this->request->getGet('cylinder_type_id')) {
            $b->where('im.cylinder_type_id', $typeId);
        }
        if ($from = $this->request->getGet('from')) {
            $b->where('im.movement_at >=', $from . ' 00:00:00');
        }
        if ($to = $this->request->getGet('to')) {
            $b->where('im.movement_at <=', $to . ' 23:59:59');
        }
        [$rows, $meta] = $this->paginate($b);
        return $this->ok(['movements' => $rows], $meta);
    }

    public function adjust()
    {
        $scope = $this->staffScope('INVENTORY_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $p = $this->payload();
        try {
            $no = (new InventoryService())->adjust(
                $shopId,
                (string) ($p['inventory_type'] ?? ''),
                !empty($p['cylinder_type_id']) ? (int) $p['cylinder_type_id'] : null,
                (float) ($p['quantity'] ?? 0),
                (string) ($p['direction'] ?? ''),
                $userId,
                (string) ($p['notes'] ?? ''),
                (float) ($p['actual_gas_weight_kg'] ?? 0),
                !empty($p['source_cylinder_unit_id']) ? (int) $p['source_cylinder_unit_id'] : null,
                (string) ($p['adjustment_scope'] ?? 'bulk'),
                (string) ($p['reason'] ?? '')
            );
        } catch (\Throwable $e) {
            return $this->fail(422, 'ADJUSTMENT_REJECTED', user_error_message($e));
        }
        return $this->ok(['adjustment_no' => $no], null, 201);
    }

    public function transfers()
    {
        $scope = $this->staffScope('STOCK_TRANSFER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $rows = (new StockTransferService())->listForLocation($shopId, $this->request->getGet('status') ?: null, $this->request->getGet('direction') ?: null);
        [$page, $meta] = $this->paginateArray($rows);
        return $this->ok(['transfers' => $page], $meta);
    }

    public function transfer(int $id)
    {
        $scope = $this->staffScope('STOCK_TRANSFER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $transfer = (new StockTransferService())->find($id, $shopId);
        return $transfer ? $this->ok($transfer) : $this->fail(404, 'TRANSFER_NOT_FOUND', 'Transfer not found for the selected shop.');
    }

    public function createTransfer()
    {
        $scope = $this->staffScope('STOCK_TRANSFER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $p = $this->payload();
        try {
            $result = (new StockTransferService())->create($shopId, (int) ($p['to_location_id'] ?? $p['to_shop_id'] ?? 0), array_map('intval', (array) ($p['unit_ids'] ?? [])), $userId, $p['notes'] ?? null);
        } catch (\Throwable $e) {
            return $this->fail(422, 'TRANSFER_REJECTED', user_error_message($e));
        }
        return $this->ok($result, null, 201);
    }

    public function receiveTransfer(int $id)
    {
        return $this->transferAction($id, 'receive');
    }

    public function cancelTransfer(int $id)
    {
        return $this->transferAction($id, 'cancel');
    }

    private function transferAction(int $id, string $action)
    {
        $scope = $this->staffScope('STOCK_TRANSFER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        try {
            $service = new StockTransferService();
            $result = $action === 'receive'
                ? $service->receive($id, $shopId, $userId)
                : $service->cancel($id, $shopId, $userId, (string) ($this->payload()['reason'] ?? ''));
        } catch (\Throwable $e) {
            return str_contains(strtolower($e->getMessage()), 'not found')
                ? $this->fail(404, 'TRANSFER_NOT_FOUND', 'Transfer not found for the selected shop.')
                : $this->fail(422, 'TRANSFER_REJECTED', user_error_message($e));
        }
        return $this->ok($result);
    }
}
