<?php

namespace App\Controllers;

use App\Services\PermissionService;
use App\Services\StockTransferService;
use App\Services\TransactionDatePolicy;
use CodeIgniter\Controller;

/**
 * Branch Stock Transfers (D9-2). All posting rules live in StockTransferService; the
 * controller only passes the current branch and user. The current branch is always the
 * sender for new transfers and cancellations, and the receiver for receipts.
 */
class StockTransfers extends Controller
{
    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        return PermissionService::allows('STOCK_TRANSFER_MANAGE') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    private function locationId(): int { return (int) session()->get('location_id'); }
    private function userId(): int { return (int) session()->get('user_id'); }

    public function index()
    {
        if ($r = $this->guard()) return $r;
        $service = new StockTransferService();
        $locationId = $this->locationId();
        $status = (string) $this->request->getGet('status');
        $direction = (string) $this->request->getGet('direction');
        $enabled = $service->isEnabled($locationId);

        return view('inventory/transfers', [
            'title' => 'Branch Stock Transfers',
            'enabled' => $enabled,
            'flow' => $service->flow($locationId),
            'destinations' => $enabled ? $service->destinations($locationId) : [],
            'units' => $enabled ? $service->availableUnits($locationId) : [],
            'transfers' => $service->listForLocation($locationId, $status ?: null, $direction ?: null),
            'filters' => ['status' => $status, 'direction' => $direction],
            'locationId' => $locationId,
            'dateRules' => (new TransactionDatePolicy())->rules($locationId),
            'canConfigure' => PermissionService::allows('BUSINESS_SETTINGS_MANAGE'),
        ]);
    }

    public function create()
    {
        if ($r = $this->guard()) return $r;
        try {
            $transfer = (new StockTransferService())->create(
                $this->locationId(),
                (int) $this->request->getPost('to_location_id'),
                (array) $this->request->getPost('unit_ids'),
                $this->userId(),
                (string) $this->request->getPost('notes'),
                (string) $this->request->getPost('transaction_at')
            );
            $message = $transfer['status'] === 'received'
                ? 'Transfer ' . $transfer['transfer_no'] . ' posted: ' . count($transfer['items']) . ' cylinder(s) moved to ' . $transfer['to_name'] . '.'
                : 'Transfer ' . $transfer['transfer_no'] . ' dispatched: ' . count($transfer['items']) . ' cylinder(s) in transit to ' . $transfer['to_name'] . '. The receiving branch must confirm receipt.';
            return redirect()->to(site_url('inventory/transfers/' . $transfer['id']))->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id)
    {
        if ($r = $this->guard()) return $r;
        $transfer = (new StockTransferService())->find($id, $this->locationId());
        if (!$transfer) {
            return $this->response->setStatusCode(404)->setBody('Transfer not found for this branch.');
        }
        return view('inventory/transfer_detail', [
            'title' => 'Transfer ' . $transfer['transfer_no'],
            'transfer' => $transfer,
            'isSender' => (int) $transfer['from_location_id'] === $this->locationId(),
            'isReceiver' => (int) $transfer['to_location_id'] === $this->locationId(),
        ]);
    }

    public function receive(int $id)
    {
        if ($r = $this->guard()) return $r;
        try {
            $transfer = (new StockTransferService())->receive($id, $this->locationId(), $this->userId());
            return redirect()->to(site_url('inventory/transfers/' . $id))->with('success', 'Transfer ' . $transfer['transfer_no'] . ' received. The cylinders are now in this branch\'s stock.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id)
    {
        if ($r = $this->guard()) return $r;
        try {
            $transfer = (new StockTransferService())->cancel($id, $this->locationId(), $this->userId(), (string) $this->request->getPost('cancel_reason'));
            return redirect()->to(site_url('inventory/transfers/' . $id))->with('success', 'Transfer ' . $transfer['transfer_no'] . ' cancelled. The cylinders are back in this branch\'s stock.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
