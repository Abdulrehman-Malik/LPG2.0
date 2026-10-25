<?php

namespace App\Controllers\Api\V1\Staff;

use App\Controllers\Api\V1\ApiController;
use App\Models\CustomerModel;
use App\Services\CustomerBalanceService;
use App\Services\CustomerService;
use App\Services\LedgerService;
use Config\Database;

/** Staff API: customers of the selected shop, their balances and ledger (BL-021). */
class Customers extends ApiController
{
    public function index()
    {
        $scope = $this->staffScope('CUSTOMER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $builder = Database::connect()->table('customers')->where('location_id', $shopId)->orderBy('name');
        $search = trim((string) $this->request->getGet('q'));
        if ($search !== '') {
            $builder->groupStart()->like('name', $search)->orLike('code', $search)->orLike('phone', $search)->groupEnd();
        }
        if ($this->request->getGet('active') !== null) {
            $builder->where('is_active', (int) $this->request->getGet('active') === 1 ? 1 : 0);
        }
        [$rows, $meta] = $this->paginate($builder);
        $balances = (new CustomerBalanceService())->osBalances($shopId, array_map(static fn ($r) => (int) $r['id'], $rows));
        foreach ($rows as &$row) {
            $row['os_balance'] = round((float) ($balances[(int) $row['id']] ?? 0), 2);
        }
        unset($row);
        return $this->ok(['customers' => $rows], $meta);
    }

    public function show(int $id)
    {
        $scope = $this->staffScope('CUSTOMER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $customer = (new CustomerModel())->findForLocation($id, $shopId);
        if (!$customer) {
            return $this->fail(404, 'CUSTOMER_NOT_FOUND', 'Customer not found in the selected shop.');
        }
        return $this->ok(['customer' => $customer]);
    }

    public function create()
    {
        return $this->saveCustomer(0);
    }

    public function update(int $id)
    {
        return $this->saveCustomer($id);
    }

    private function saveCustomer(int $id)
    {
        $scope = $this->staffScope('CUSTOMER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $input = $this->payload();
        if ($id > 0) {
            // An update sends only the fields it changes; the rest keep their stored values.
            $existing = (new CustomerModel())->findForLocation($id, $shopId);
            if (!$existing) {
                return $this->fail(404, 'CUSTOMER_NOT_FOUND', 'Customer not found in the selected shop.');
            }
            $input += $existing;
        } else {
            $input += ['is_active' => 1];
        }
        try {
            $result = (new CustomerService())->save($id, $input, $userId, $shopId);
        } catch (\RuntimeException $e) {
            return str_contains($e->getMessage(), 'not found')
                ? $this->fail(404, 'CUSTOMER_NOT_FOUND', 'Customer not found in the selected shop.')
                : $this->fail(422, 'VALIDATION_FAILED', user_error_message($e));
        }
        return $this->ok(['customer' => (new CustomerModel())->find($result['id'])], null, $result['created'] ? 201 : 200);
    }

    public function balance(int $id)
    {
        $scope = $this->staffScope('CUSTOMER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        if (!(new CustomerModel())->findForLocation($id, $shopId)) {
            return $this->fail(404, 'CUSTOMER_NOT_FOUND', 'Customer not found in the selected shop.');
        }
        $balances = new CustomerBalanceService();
        return $this->ok([
            'customer_id' => $id,
            'os_balance' => round($balances->osBalance($shopId, $id), 2),
            'gas_balance' => round((float) ($balances->gasBalances($shopId, [$id])[$id] ?? 0), 2),
            'deposit_balance' => round((float) ($balances->depositBalances($shopId, [$id])[$id] ?? 0), 2),
            'deposit_included_in_os' => $balances->includesDeposit($shopId),
        ]);
    }

    public function ledger(int $id)
    {
        $scope = $this->staffScope('CUSTOMER_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $ledger = (new LedgerService())->customerLedger($id, $shopId);
        if (!$ledger) {
            return $this->fail(404, 'CUSTOMER_NOT_FOUND', 'Customer not found in the selected shop.');
        }
        return $this->ok($ledger);
    }
}
