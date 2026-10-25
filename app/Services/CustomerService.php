<?php

namespace App\Services;

use App\Models\CustomerModel;
use RuntimeException;

/**
 * Customer master data of one branch (BL-021; moved from the Customers controller so the web
 * screen and the API share it). Every create/update is audit-logged with before/after values
 * (BL-015); deactivating a customer revokes their mobile app sessions.
 */
class CustomerService
{
    /** Fields a caller may set; anything else in the input is ignored. */
    public const FIELDS = ['code', 'name', 'phone', 'city', 'address', 'vehicle_no', 'credit_limit', 'allow_credit_sale', 'opening_balance', 'is_active'];

    private CustomerModel $model;

    public function __construct(?CustomerModel $model = null)
    {
        $this->model = $model ?? new CustomerModel();
    }

    /** Normalises raw input (form or JSON) into a customers row for this branch. */
    public function normalise(array $input, int $locationId): array
    {
        $data = ['location_id' => $locationId];
        foreach (['code', 'phone', 'city', 'address', 'vehicle_no'] as $f) {
            $data[$f] = trim((string) ($input[$f] ?? '')) ?: null;
        }
        $data['name'] = trim((string) ($input['name'] ?? ''));
        $data['credit_limit'] = (float) ($input['credit_limit'] ?? 0);
        $data['opening_balance'] = (float) ($input['opening_balance'] ?? 0);
        $data['allow_credit_sale'] = self::flag($input['allow_credit_sale'] ?? 0);
        $data['is_active'] = self::flag($input['is_active'] ?? 0);
        foreach (CustomerFields::EXTRA as $f => $kind) {
            if (array_key_exists($f, $input)) {
                $data[$f] = $kind === 'flag' ? self::flag($input[$f]) : (trim((string) $input[$f]) ?: null);
            }
        }
        return $data;
    }

    private static function flag($value): int
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    /**
     * Creates (id 0) or updates a customer of the branch.
     *
     * @return array{id:int,created:bool}
     */
    /**
     * Soft delete (BL-030): the row stays for history, ledgers and sync, but is deactivated and
     * hidden from lists and finds; app sessions are revoked as on deactivation.
     */
    public function delete(int $id, int $userId, int $locationId): void
    {
        $existing = $this->model->findForLocation($id, $locationId);
        if (!$existing) {
            throw new RuntimeException('Customer not found.');
        }
        $this->model->update($id, ['is_active' => 0]);
        $this->model->delete($id);
        $apiSessions = new ApiSessionService();
        foreach ($this->model->db->table('customer_app_accounts')->select('id')->where('customer_id', $id)->get()->getResultArray() as $accountRow) {
            $apiSessions->revokeAllForCustomer((int) $accountRow['id']);
        }
        AuditService::log('DELETE', 'customer', $id, self::auditable($existing), null, $userId, $locationId);
    }

    public function save(int $id, array $input, int $userId, int $locationId): array
    {
        $existing = null;
        if ($id > 0) {
            $existing = $this->model->findForLocation($id, $locationId);
            if (!$existing) {
                throw new RuntimeException('Customer not found.');
            }
        }
        $data = $this->normalise($input, $locationId);
        if ($data['name'] === '') {
            throw new RuntimeException('Customer name is required.');
        }
        if ($data['credit_limit'] < 0 || $data['opening_balance'] < 0) {
            throw new RuntimeException('Credit limit and opening balance cannot be negative.');
        }
        CustomerFields::validate($data);

        try {
            if ($existing) {
                $this->model->update($id, $data);
                if ((int) $data['is_active'] !== 1) {
                    $apiSessions = new ApiSessionService();
                    foreach ($this->model->db->table('customer_app_accounts')->select('id')->where('customer_id', $id)->get()->getResultArray() as $accountRow) {
                        $apiSessions->revokeAllForCustomer((int) $accountRow['id']);
                    }
                }
            } else {
                $this->model->insert($data);
                $id = (int) $this->model->getInsertID();
            }
        } catch (\Throwable $e) {
            log_message('error', 'Customer save failed: {message}', ['message' => $e->getMessage()]);
            throw new RuntimeException('Customer could not be saved. Check the code for duplicates.');
        }

        $after = $this->model->find($id);
        AuditService::log($existing ? 'UPDATE' : 'CREATE', 'customer', $id, $existing ? self::auditable($existing) : null, self::auditable((array) $after), $userId, $locationId);

        return ['id' => $id, 'created' => $existing === null];
    }

    private static function auditable(array $row): array
    {
        return array_intersect_key($row, array_flip(array_merge(self::FIELDS, array_keys(CustomerFields::EXTRA))));
    }
}
