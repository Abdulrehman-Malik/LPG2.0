<?php

namespace App\Controllers\Api\V1\Staff;

use App\Controllers\Api\V1\ApiController;
use App\Services\CashService;

/** Staff API: counter cash of the selected shop (BL-023), same CashService as the Counter Cash screen. */
class Cash extends ApiController
{
    public function current()
    {
        $scope = $this->staffScope('CASH_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $cash = new CashService();
        $session = $cash->openSessionForLocation($shopId);
        if (!$session) {
            return $this->ok(['session' => null]);
        }
        $summary = $cash->summary((int) $session['id']);
        return $this->ok([
            'session' => $session,
            'cash_in' => $summary['cash_in'],
            'cash_out' => $summary['cash_out'],
            'expected' => $summary['expected'],
            'transactions' => $cash->sessionTransactions((int) $session['id'], 50),
        ]);
    }

    public function open()
    {
        $scope = $this->staffScope('CASH_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $p = $this->payload();
        try {
            $id = (new CashService())->openSession($userId, $shopId, (float) ($p['opening_cash'] ?? 0), (string) ($p['notes'] ?? ''));
        } catch (\Throwable $e) {
            return $this->fail(422, 'CASH_SESSION_REJECTED', user_error_message($e));
        }
        return $this->ok(['session_id' => $id], null, 201);
    }

    public function move()
    {
        $scope = $this->staffScope('CASH_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $p = $this->payload();
        $cash = new CashService();
        try {
            $sessionId = $cash->manualMove($shopId, $userId, (string) ($p['direction'] ?? ''), (float) ($p['amount'] ?? 0), (string) ($p['notes'] ?? ''));
        } catch (\Throwable $e) {
            return str_contains($e->getMessage(), 'Open a cash session')
                ? $this->fail(409, 'NO_OPEN_CASH_SESSION', 'Open a cash session first.')
                : $this->fail(422, 'CASH_MOVE_REJECTED', user_error_message($e));
        }
        $summary = $cash->summary($sessionId);
        return $this->ok(['session_id' => $sessionId, 'cash_in' => $summary['cash_in'], 'cash_out' => $summary['cash_out'], 'expected' => $summary['expected']], null, 201);
    }

    public function close()
    {
        $scope = $this->staffScope('CASH_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [$userId, $shopId] = $scope;
        $p = $this->payload();
        $cash = new CashService();
        $sessionId = (int) ($p['session_id'] ?? 0) ?: (int) ($cash->openSessionForLocation($shopId)['id'] ?? 0);
        if ($sessionId <= 0) {
            return $this->fail(409, 'NO_OPEN_CASH_SESSION', 'There is no open cash session to close.');
        }
        if (!isset($p['counted_cash']) || !is_numeric($p['counted_cash'])) {
            return $this->fail(422, 'VALIDATION_FAILED', 'counted_cash is required.');
        }
        try {
            $result = $cash->closeSession($sessionId, $userId, (float) $p['counted_cash'], (string) ($p['notes'] ?? ''), $shopId);
        } catch (\Throwable $e) {
            return $this->fail(422, 'CASH_CLOSE_REJECTED', user_error_message($e));
        }
        return $this->ok($result);
    }

    public function history()
    {
        $scope = $this->staffScope('CASH_MANAGE');
        if (!is_array($scope)) {
            return $scope;
        }
        [, $shopId] = $scope;
        $rows = (new CashService())->history($shopId, $this->request->getGet('from') ?: null, $this->request->getGet('to') ?: null, ((int) $this->request->getGet('register_id')) ?: null);
        [$page, $meta] = $this->paginateArray($rows);
        return $this->ok(['transactions' => $page], $meta);
    }
}
