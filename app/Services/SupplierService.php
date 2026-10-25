<?php

namespace App\Services;

use App\Models\SupplierModel;
use RuntimeException;

/**
 * Supplier create/update (BL-025; moved unchanged from the Suppliers controller so the web screen
 * and the API share it). Suppliers belong to a business and are business-wide or limited to one
 * branch according to the business setting supplier_scope_mode (D9-3); the opening payable is
 * recorded per branch (D9-4).
 */
class SupplierService
{
    private SupplierModel $model;

    public function __construct(?SupplierModel $model = null)
    {
        $this->model = $model ?? new SupplierModel();
    }

    public function scopeMode(int $locationId): string
    {
        return (string) (new ConfigurationService())->valueForShop('supplier_scope_mode', $locationId);
    }

    /**
     * @param array<string,mixed> $input code, name, phone, city, address, credit_limit, is_active,
     *                                   opening_balance, supplier_scope (branch|business when the mode is "both")
     * @return array{id:int,created:bool}
     */
    public function save(int $id, array $input, int $userId, int $locationId): array
    {
        $businessId = BusinessContext::businessIdForLocation($locationId);
        $data = [
            'code' => trim((string) ($input['code'] ?? '')) ?: null,
            'name' => trim((string) ($input['name'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')) ?: null,
            'city' => trim((string) ($input['city'] ?? '')) ?: null,
            'address' => trim((string) ($input['address'] ?? '')) ?: null,
            'credit_limit' => (float) ($input['credit_limit'] ?? 0),
            'is_active' => !empty($input['is_active']) && $input['is_active'] !== 'false' ? 1 : 0,
        ];
        if ($data['name'] === '') {
            throw new RuntimeException('Supplier name is required.');
        }
        $opening = (float) ($input['opening_balance'] ?? 0);
        if ($data['credit_limit'] < 0 || $opening < 0) {
            throw new RuntimeException('Credit limit and opening balance cannot be negative.');
        }

        $mode = $this->scopeMode($locationId);
        $requested = (string) ($input['supplier_scope'] ?? '');
        $branchOnly = match ($mode) { 'branch' => true, 'both' => $requested === 'branch', default => false };
        $data['location_id'] = $branchOnly ? $locationId : null;

        $existing = null;
        if ($data['code'] !== null) {
            $duplicate = $this->model->where(['business_id' => $businessId, 'code' => $data['code']])->where('id !=', $id)->first();
            if ($duplicate) {
                throw new RuntimeException('Another supplier of this business already uses this code.');
            }
        }
        if ($id) {
            $existing = BusinessContext::supplierVisibleTo($id, $locationId, false);
            if (!$existing) {
                throw new RuntimeException('Supplier not found.');
            }
            if ($branchOnly && $existing['location_id'] === null && $this->usedByOtherBranches($id, $locationId)) {
                throw new RuntimeException('This supplier already has transactions in other branches, so it must stay business-wide.');
            }
            if ($mode === 'shared' && $existing['location_id'] !== null) {
                // Business switched to shared suppliers: existing branch-only suppliers become business-wide when edited.
                $data['location_id'] = null;
            }
            $this->model->update($id, $data);
        } else {
            $id = (int) $this->model->insert($data + ['business_id' => $businessId, 'opening_balance' => 0]);
        }
        // D9-4: the opening payable is recorded at this branch.
        (new SupplierBalanceService())->setBranchOpening($id, $locationId, $opening, $userId);
        AuditService::log($existing ? 'UPDATE' : 'CREATE', 'supplier', $id, $existing ?: null, $data + ['branch_opening' => $opening], $userId, $locationId);

        return ['id' => $id, 'created' => $existing === null];
    }

    private function usedByOtherBranches(int $supplierId, int $locationId): bool
    {
        $db = $this->model->db;
        return $db->table('purchases')->where('supplier_id', $supplierId)->where('location_id !=', $locationId)->countAllResults() > 0
            || $db->table('supplier_payments')->where('supplier_id', $supplierId)->where('location_id !=', $locationId)->countAllResults() > 0;
    }
}
