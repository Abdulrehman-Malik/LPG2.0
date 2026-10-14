<?php
namespace App\Controllers;

use App\Services\PurchaseService;
use CodeIgniter\Controller;
use Config\Database;

class Purchases extends Controller
{
    private function guard()
    {
        return \App\Services\PermissionService::allows('PURCHASE_MANAGE')
            ? null
            : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $db = Database::connect();
        $today = date('Y-m-d');
        $activeTab = (string) $this->request->getGet('tab') === 'history' ? 'history' : 'new';
        $fromDate = $this->validHistoryDate($this->request->getGet('from_date'), $today);
        $toDate = $this->validHistoryDate($this->request->getGet('to_date'), $today);

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }
        $purchaseService = new PurchaseService();
        $suppliers = $db->table('suppliers')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->getResultArray();

        $supplierBalances = [];
        foreach ($suppliers as $supplier) {
            $supplierBalances[(int) $supplier['id']] = round(
                $purchaseService->supplierBalance((int) $supplier['id'], (int) session()->get('location_id')),
                2
            );
        }

        $history = [];
        $purchaseService = new PurchaseService();
        $canVoidPurchases = $purchaseService->canVoidPurchase(
            (int) session()->get('user_id'),
            (int) session()->get('location_id')
        );

        $purchaseDetails = [];
        if ($activeTab === 'history') {
            $history = $db->table('purchases p')
                ->select('p.id, p.purchase_no, p.transaction_at, p.subtotal, p.discount_amount, p.total_amount, p.credit_amount, p.status, p.voided_at, p.void_reason, p.notes, s.name AS supplier_name, vu.full_name AS voided_by_name, COALESCE(SUM(pp.amount), 0) AS amount_paid')
                ->join('suppliers s', 's.id = p.supplier_id')
                ->join('users vu', 'vu.id = p.voided_by', 'left')
                ->join('purchase_payments pp', 'pp.purchase_id = p.id', 'left')
                ->where('p.location_id', (int) session()->get('location_id'))
                ->where('p.transaction_at >=', $fromDate . ' 00:00:00')
                ->where('p.transaction_at <=', $toDate . ' 23:59:59')
                ->groupBy('p.id')
                ->orderBy('p.transaction_at', 'DESC')
                ->orderBy('p.id', 'DESC')
                ->get()
                ->getResultArray();

            if ($history) {
                $purchaseIds = array_map(static fn(array $row): int => (int) $row['id'], $history);

                $items = $db->table('purchase_items pi')
                    ->select('pi.purchase_id, pi.line_no, pi.line_type, pi.quantity, pi.actual_gas_weight_kg, pi.unit_rate, pi.line_total, ct.code AS cylinder_code, ct.name AS cylinder_name')
                    ->join('cylinder_types ct', 'ct.id = pi.cylinder_type_id', 'left')
                    ->whereIn('pi.purchase_id', $purchaseIds)
                    ->orderBy('pi.purchase_id', 'ASC')
                    ->orderBy('pi.line_no', 'ASC')
                    ->get()
                    ->getResultArray();

                $payments = $db->table('purchase_payments pp')
                    ->select('pp.purchase_id, pp.payment_mode, pp.amount, pp.reference_no, pp.payment_at')
                    ->whereIn('pp.purchase_id', $purchaseIds)
                    ->orderBy('pp.purchase_id', 'ASC')
                    ->orderBy('pp.payment_at', 'ASC')
                    ->get()
                    ->getResultArray();

                foreach ($purchaseIds as $purchaseId) {
                    $purchaseDetails[$purchaseId] = [
                        'items' => [],
                        'payments' => [],
                    ];
                }

                foreach ($items as $item) {
                    $purchaseDetails[(int) $item['purchase_id']]['items'][] = $item;
                }

                foreach ($payments as $payment) {
                    $purchaseDetails[(int) $payment['purchase_id']]['payments'][] = $payment;
                }
            }
        }

        return view('purchases/index', [
            'title'            => 'Purchases',
            'activeTab'        => $activeTab,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'purchaseHistory'  => $history,
            'purchaseDetails'  => $purchaseDetails,
            'suppliers'        => $suppliers,
            'supplierBalances' => $supplierBalances,
            'canVoidPurchases' => $canVoidPurchases,
            'types'            => $db->table('cylinder_types')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get()
                ->getResultArray(),
        ]);
    }

    private function validHistoryDate($value, string $default): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }

        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        $errors = \DateTime::getLastErrors();
        $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

        return $date && !$hasErrors && $date->format('Y-m-d') === $value ? $value : $default;
    }

    public function save()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        try {
            $lines = json_decode((string) $this->request->getPost('lines_json'), true);
            $payments = json_decode((string) $this->request->getPost('payments_json'), true);

            $x = (new PurchaseService())->post([
                'supplier_id'     => $this->request->getPost('supplier_id'),
                'discount_amount' => $this->request->getPost('discount_amount'),
                'lines'           => $lines,
                'payments'        => $payments,
                'notes'           => $this->request->getPost('notes'),
            ], (int) session()->get('user_id'), (int) session()->get('location_id'));

            return redirect()->to('/purchases')->with(
                'success',
                'Purchase ' . $x['purchase_no'] . ' posted. Balance payable: Rs. ' .
                    number_format((float) $x['balance_payable'], 2)
            );
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function void(int $id)
    {
        if ($r = $this->guard()) {
            return $r;
        }

        try {
            $purchaseService = new PurchaseService();
            if (!$purchaseService->canVoidPurchase(
                (int) session()->get('user_id'),
                (int) session()->get('location_id')
            )) {
                throw new \RuntimeException('You are not authorized to void purchases. Purchase Void must be enabled in Shop Settings and your user must be assigned to this function.');
            }

            $reason = trim((string) $this->request->getPost('void_reason'));
            if ($reason === '') {
                $reason = 'Voided from Purchase History';
            }

            $no = (new PurchaseService())->void(
                $id,
                (int) session()->get('user_id'),
                (int) session()->get('location_id'),
                $reason
            );

            return redirect()->to('/purchases?tab=history')
                ->with('success', 'Purchase ' . $no . ' voided successfully. Stock, cash and supplier ledger effects were reversed.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
