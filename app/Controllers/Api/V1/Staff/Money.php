<?php

namespace App\Controllers\Api\V1\Staff;

use App\Controllers\Api\V1\ApiController;
use App\Services\CustomerReceiptService;
use App\Services\ExpenseService;
use Config\Database;

/** Staff API: customer receipts and expenses of the selected shop (BL-026). */
class Money extends ApiController
{
    public function receipts()
    {
        $scope = $this->staffScope('POS_SALE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $b = Database::connect()->table('customer_receipts cr')->select('cr.*,c.name customer_name,c.code customer_code')
            ->join('customers c', 'c.id=cr.customer_id', 'left')
            ->where('cr.location_id', $shopId)->orderBy('cr.receipt_at', 'DESC')->orderBy('cr.id', 'DESC');
        if ($customerId = (int) $this->request->getGet('customer_id')) {
            $b->where('cr.customer_id', $customerId);
        }
        [$rows, $meta] = $this->paginate($b);
        return $this->ok(['receipts' => $rows], $meta);
    }

    public function createReceipt()
    {
        $scope = $this->staffScope('POS_SALE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        try {
            $result = (new CustomerReceiptService())->post($this->payload(), $userId, $shopId);
        } catch (\Throwable $e) {
            return $this->fail(422, 'RECEIPT_REJECTED', user_error_message($e));
        }
        return $this->ok($result, null, 201);
    }

    public function expenseCategories()
    {
        $scope = $this->staffScope('EXPENSE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        return $this->ok(['categories' => (new ExpenseService())->activeCategories($shopId)]);
    }

    public function expenses()
    {
        $scope = $this->staffScope('EXPENSE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $b = Database::connect()->table('expenses e')->select('e.*,c.name category_name')
            ->join('expense_categories c', 'c.id=e.category_id')
            ->where('e.location_id', $shopId)->orderBy('e.expense_at', 'DESC')->orderBy('e.id', 'DESC');
        [$rows, $meta] = $this->paginate($b);
        return $this->ok(['expenses' => $rows], $meta);
    }

    public function createExpense()
    {
        $scope = $this->staffScope('EXPENSE_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        try {
            $result = (new ExpenseService())->post($this->payload(), $userId, $shopId);
        } catch (\Throwable $e) {
            return $this->fail(422, 'EXPENSE_REJECTED', user_error_message($e));
        }
        return $this->ok($result, null, 201);
    }
}
