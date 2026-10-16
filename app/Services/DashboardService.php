<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Branch dashboard figures (Phase 11.3; query logic moved out of the Dashboard controller).
 *
 * Period figures (sales, cash movements, expenses, trend) follow the selected date range;
 * stock, custody, receivables and the open cash session are current. Everything is read from
 * posted transactions or live physical cylinders, never from stored totals.
 */
class DashboardService
{
    private const NON_REVENUE_TYPES = ['security_deposit', 'cylinder_return'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param array{from:string,to:string,toExclusive:string} $range
     */
    public function forBranch(int $locationId, array $range): array
    {
        $stock = $this->stock($locationId);
        $sales = $this->sales($locationId, $range);
        $cash = $this->cashMovements($locationId, $range);
        $receivables = $this->receivables($locationId);
        $expenses = $this->expenses($locationId, $range);

        return $stock + $sales + $cash + $receivables + $expenses + [
            'trend' => $this->trend($locationId, $range),
            'openCash' => $this->openCash($locationId),
            'incomingTransfers' => $this->incomingTransfers($locationId),
        ];
    }

    /** Filled, empty and customer-custody cylinders at the branch, grouped for the drill-downs. */
    private function stock(int $locationId): array
    {
        $units = $this->db->table('cylinder_units cu')
            ->select('cu.id,cu.unit_code,cu.status,cu.gas_weight_kg,cu.updated_at,ct.id cylinder_type_id,ct.code type_code,ct.name type_name,ct.capacity_kg')
            ->join('cylinder_types ct', 'ct.id=cu.cylinder_type_id')
            ->where('cu.location_id', $locationId)->whereIn('cu.status', ['filled', 'empty'])
            ->orderBy('ct.sort_order')->orderBy('cu.unit_code')->get()->getResultArray();
        $filled = array_values(array_filter($units, static fn ($u) => $u['status'] === 'filled'));
        $empty = array_values(array_filter($units, static fn ($u) => $u['status'] === 'empty'));

        $issued = $this->db->table('cylinder_units cu')
            ->select('cu.id,cu.unit_code,cu.gas_weight_kg,cu.updated_at,ct.id cylinder_type_id,ct.code type_code,ct.name type_name,ct.capacity_kg,ct.sort_order,c.id customer_id,c.code customer_code,c.name customer_name,c.phone customer_phone,cc.deposit_amount,cc.issued_at,cc.issued_condition,cc.issued_gas_weight_kg')
            ->join('cylinder_types ct', 'ct.id=cu.cylinder_type_id')
            ->join('cylinder_custody cc', "cc.cylinder_unit_id=cu.id AND cc.status='issued'", 'inner')
            ->join('customers c', 'c.id=cc.customer_id', 'inner')
            ->where(['cu.location_id' => $locationId, 'cu.status' => 'custody'])
            ->orderBy('c.name')->orderBy('ct.sort_order')->orderBy('cu.unit_code')->get()->getResultArray();

        $gasTypes = [];
        foreach ($filled as $row) {
            $id = (int) $row['cylinder_type_id'];
            $gasTypes[$id] ??= ['id' => $id, 'code' => $row['type_code'], 'name' => $row['type_name'], 'capacity_kg' => (float) $row['capacity_kg'], 'cylinder_count' => 0, 'gas_kg' => 0.0, 'rows' => []];
            $gasTypes[$id]['cylinder_count']++;
            $gasTypes[$id]['gas_kg'] += (float) $row['gas_weight_kg'];
            $gasTypes[$id]['rows'][] = $row;
        }
        $emptyTypes = [];
        foreach ($empty as $row) {
            $id = (int) $row['cylinder_type_id'];
            $emptyTypes[$id] ??= ['id' => $id, 'code' => $row['type_code'], 'name' => $row['type_name'], 'capacity_kg' => (float) $row['capacity_kg'], 'count' => 0, 'rows' => []];
            $emptyTypes[$id]['count']++;
            $emptyTypes[$id]['rows'][] = $row;
        }
        $issuedCustomers = [];
        foreach ($issued as $row) {
            $id = (int) $row['customer_id'];
            $issuedCustomers[$id] ??= ['id' => $id, 'code' => $row['customer_code'], 'name' => $row['customer_name'], 'phone' => $row['customer_phone'], 'count' => 0, 'deposit' => 0.0, 'rows' => []];
            $issuedCustomers[$id]['count']++;
            $issuedCustomers[$id]['deposit'] += (float) $row['deposit_amount'];
            $issuedCustomers[$id]['rows'][] = $row;
        }

        // Position per cylinder type: filled gas vs capacity, empties and custody.
        $byType = [];
        $touch = static function (array $row) use (&$byType): int {
            $id = (int) $row['cylinder_type_id'];
            $byType[$id] ??= ['code' => $row['type_code'], 'name' => $row['type_name'], 'capacity_kg' => (float) $row['capacity_kg'], 'filled' => 0, 'partial' => 0, 'gas_kg' => 0.0, 'empty' => 0, 'custody' => 0];
            return $id;
        };
        foreach ($filled as $row) {
            $id = $touch($row);
            $byType[$id]['filled']++;
            $byType[$id]['gas_kg'] += (float) $row['gas_weight_kg'];
            if ((float) $row['gas_weight_kg'] < (float) $row['capacity_kg'] - 0.00001) {
                $byType[$id]['partial']++;
            }
        }
        foreach ($empty as $row) {
            $byType[$touch($row)]['empty']++;
        }
        foreach ($issued as $row) {
            $byType[$touch($row)]['custody']++;
        }

        $issuedDeposit = array_sum(array_map(static fn ($x) => (float) $x['deposit_amount'], $issued));
        $filledGas = array_sum(array_map(static fn ($x) => (float) $x['gas_weight_kg'], $filled));

        return [
            'gasRows' => $filled,
            'gasTypes' => array_values($gasTypes),
            'availableGasKg' => $filledGas,
            'emptyTypes' => array_values($emptyTypes),
            'emptyCylinderCount' => count($empty),
            'issuedCustomers' => array_values($issuedCustomers),
            'issuedCylinderCount' => count($issued),
            'issuedDepositTotal' => $issuedDeposit,
            'stockByType' => array_values($byType),
            'stock' => [
                'filled' => ['title' => 'Filled Cylinders', 'count' => count($filled), 'gas_kg' => $filledGas, 'rows' => $filled],
                'empty' => ['title' => 'Empty Cylinders', 'count' => count($empty), 'gas_kg' => 0.0, 'rows' => $empty],
                'issued' => ['title' => 'Issued Temporarily Against Deposit', 'count' => count($issued), 'deposit' => $issuedDeposit, 'rows' => $issued],
            ],
        ];
    }

    /** Posted sales (excluding deposit/custody transactions) in the range, with payment-mode split. */
    private function sales(int $locationId, array $range): array
    {
        $q = $this->db->table('sales s')
            ->select("s.id,s.sale_no,s.transaction_at,s.customer_id,s.total_amount,s.credit_amount,s.status,s.transaction_type,c.name customer_name,
                COALESCE(SUM(CASE WHEN sp.payment_mode='cash' AND sp.payment_type='sale' THEN sp.amount ELSE 0 END),0) cash_amount,
                COALESCE(SUM(CASE WHEN sp.payment_mode='online' AND sp.payment_type='sale' THEN sp.amount ELSE 0 END),0) online_amount,
                COALESCE(SUM(CASE WHEN sp.payment_mode='cheque' AND sp.payment_type='sale' THEN sp.amount ELSE 0 END),0) cheque_amount")
            ->join('customers c', 'c.id=s.customer_id', 'left')
            ->join('sale_payments sp', "sp.sale_id=s.id AND sp.payment_type='sale'", 'left')
            ->where(['s.location_id' => $locationId, 's.status' => 'posted'])
            ->whereNotIn('s.transaction_type', self::NON_REVENUE_TYPES)
            ->groupBy('s.id')->orderBy('s.transaction_at', 'DESC');
        $this->period($q, 's.transaction_at', $range);
        $rows = $q->get()->getResultArray();

        $summary = ['total' => 0.0, 'count' => count($rows), 'cash' => 0.0, 'online' => 0.0, 'cheque' => 0.0, 'credit' => 0.0];
        foreach ($rows as $row) {
            $summary['total'] += (float) $row['total_amount'];
            $summary['cash'] += (float) $row['cash_amount'];
            $summary['online'] += (float) $row['online_amount'];
            $summary['cheque'] += (float) $row['cheque_amount'];
            $summary['credit'] += (float) $row['credit_amount'];
        }
        return ['salesRows' => $rows, 'saleSummary' => $summary];
    }

    /** Register movements in the range, grouped by register. */
    private function cashMovements(int $locationId, array $range): array
    {
        $q = $this->db->table('cash_transactions ct')
            ->select('ct.id,ct.transaction_at,ct.transaction_type,ct.direction,ct.amount,ct.reference_type,ct.reference_id,ct.notes,cr.code register_code,cr.name register_name,cs.id session_id')
            ->join('cash_sessions cs', 'cs.id=ct.cash_session_id')
            ->join('cash_registers cr', 'cr.id=cs.register_id')
            ->where('cr.location_id', $locationId)->orderBy('cr.code')->orderBy('ct.transaction_at', 'DESC')->orderBy('ct.id', 'DESC');
        $this->period($q, 'ct.transaction_at', $range);

        $registers = [];
        $in = $out = 0.0;
        foreach ($q->get()->getResultArray() as $row) {
            $key = (string) $row['register_code'];
            $registers[$key] ??= ['code' => $row['register_code'], 'name' => $row['register_name'], 'in' => 0.0, 'out' => 0.0, 'rows' => []];
            if ($row['direction'] === 'in') {
                $registers[$key]['in'] += (float) $row['amount'];
                $in += (float) $row['amount'];
            } else {
                $registers[$key]['out'] += (float) $row['amount'];
                $out += (float) $row['amount'];
            }
            $registers[$key]['rows'][] = $row;
        }
        return ['cashRegisters' => array_values($registers), 'cashIn' => $in, 'cashOut' => $out];
    }

    /** Current customer OS (SalesService::customerBalance is the single balance rule) and the ledger drill-down. */
    private function receivables(int $locationId): array
    {
        $customers = $this->db->table('customers')->where('location_id', $locationId)->where('is_active', 1)->orderBy('name')->get()->getResultArray();
        $creditBy = $paidBy = [];
        foreach ($this->db->table('sales')->select('customer_id,SUM(credit_amount) credit')
            ->where(['location_id' => $locationId, 'status' => 'posted'])->where('customer_id IS NOT NULL', null, false)
            ->groupBy('customer_id')->get()->getResultArray() as $row) {
            $creditBy[(int) $row['customer_id']] = (float) $row['credit'];
        }
        foreach ($this->db->table('customer_receipts')->select('customer_id,SUM(amount) paid')
            ->where(['location_id' => $locationId, 'status' => 'posted'])->groupBy('customer_id')->get()->getResultArray() as $row) {
            $paidBy[(int) $row['customer_id']] = (float) $row['paid'];
        }

        $salesService = new SalesService();
        $receivables = [];
        foreach ($customers as $c) {
            $id = (int) $c['id'];
            $balance = max(0, (float) $salesService->customerBalance($id));
            if ($balance <= 0.005) {
                continue;
            }
            $receivables[] = ['id' => $id, 'code' => $c['code'], 'name' => $c['name'], 'phone' => $c['phone'], 'opening_balance' => (float) $c['opening_balance'], 'credit_sales' => (float) ($creditBy[$id] ?? 0), 'receipts' => (float) ($paidBy[$id] ?? 0), 'balance' => $balance];
        }
        usort($receivables, static fn ($a, $b) => $b['balance'] <=> $a['balance']);

        $ledger = [];
        $ledgerSales = $this->db->table('sales s')->select('s.customer_id,s.sale_no,s.transaction_at,s.transaction_type,s.credit_amount,0 AS receipt_amount,c.name customer_name')
            ->join('customers c', 'c.id=s.customer_id', 'inner')->where(['s.location_id' => $locationId, 's.status' => 'posted'])
            ->where('s.customer_id IS NOT NULL', null, false)->where('s.credit_amount >', 0)->orderBy('s.transaction_at', 'DESC')->get()->getResultArray();
        $ledgerReceipts = $this->db->table('customer_receipts r')->select('r.customer_id,r.receipt_no,r.receipt_at transaction_at,r.amount receipt_amount,0 AS credit_amount,c.name customer_name')
            ->join('customers c', 'c.id=r.customer_id', 'inner')->where(['r.location_id' => $locationId, 'r.status' => 'posted'])
            ->orderBy('r.receipt_at', 'DESC')->get()->getResultArray();
        foreach (array_merge($ledgerSales, $ledgerReceipts) as $row) {
            $ledger[(int) $row['customer_id']][] = $row;
        }

        return [
            'receivables' => $receivables,
            'receivableTotal' => array_sum(array_column($receivables, 'balance')),
            'receivableLedger' => $ledger,
        ];
    }

    private function expenses(int $locationId, array $range): array
    {
        $q = $this->db->table('expenses e')->select('e.expense_no,e.expense_at,e.amount,e.payment_mode,e.reference_no,e.description,ec.name category_name')
            ->join('expense_categories ec', 'ec.id=e.category_id', 'left')->where('e.location_id', $locationId)->orderBy('e.expense_at', 'DESC');
        $this->period($q, 'e.expense_at', $range);
        $rows = $q->get()->getResultArray();

        $categories = [];
        foreach ($rows as $row) {
            $key = (string) ($row['category_name'] ?? 'Other');
            $categories[$key] ??= ['name' => $key, 'amount' => 0.0, 'count' => 0];
            $categories[$key]['amount'] += (float) $row['amount'];
            $categories[$key]['count']++;
        }
        $categories = array_values($categories);
        usort($categories, static fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return ['expenseRows' => $rows, 'expenseCategories' => $categories, 'expenseTotal' => array_sum(array_map(static fn ($x) => (float) $x['amount'], $rows))];
    }

    /**
     * Daily sales totals for the trend chart. Short ranges (under a week) show the 14 days
     * ending on the range end so a single day still has context; long ranges are capped at 92 days.
     *
     * @return array{from:string,to:string,days:list<array{day:string,total:float}>}
     */
    private function trend(int $locationId, array $range): array
    {
        $to = $range['to'];
        $from = $range['from'];
        $span = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($span < 7) {
            $from = date('Y-m-d', strtotime($to . ' -13 days'));
        } elseif ($span > 92) {
            $from = date('Y-m-d', strtotime($to . ' -91 days'));
        }
        $totals = [];
        foreach ((new ReportService($this->db))->dailySales([$locationId], $from, $to) as $row) {
            $totals[$row['day']] = (float) $row['total'];
        }
        $days = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
            $key = date('Y-m-d', $d);
            $days[] = ['day' => $key, 'total' => $totals[$key] ?? 0.0];
        }
        return ['from' => $from, 'to' => $to, 'days' => $days];
    }

    private function openCash(int $locationId): ?array
    {
        $cash = new CashService();
        $session = $cash->openSessionForLocation($locationId);
        if (!$session) {
            return null;
        }
        return $session + ['expected' => $cash->summary((int) $session['id'])['expected']];
    }

    private function incomingTransfers(int $locationId): int
    {
        return $this->db->table('stock_transfers')->where(['to_location_id' => $locationId, 'status' => 'in_transit'])->countAllResults();
    }

    /**
     * Branch comparison for Head Office users: the other branches of the same business the user
     * may access, for the selected range. Empty unless at least two branches are accessible.
     */
    public function branchComparison(int $locationId, array $accessibleShopIds, array $range): array
    {
        $businessId = BusinessContext::businessIdForLocation($locationId);
        if (!$businessId || count($accessibleShopIds) < 2) {
            return [];
        }
        $ids = array_map('intval', array_column(
            $this->db->table('locations')->select('id')->where(['business_id' => $businessId, 'is_active' => 1])
                ->whereIn('id', array_map('intval', $accessibleShopIds))->get()->getResultArray(),
            'id'
        ));
        if (count($ids) < 2) {
            return [];
        }
        $report = (new ReportService($this->db))->branchSummary($ids, $range['from'], $range['to']);
        return array_values($report['branches']);
    }

    private function period($builder, string $column, array $range): void
    {
        $builder->where($column . ' >=', $range['from'] . ' 00:00:00')
            ->where($column . ' <', $range['toExclusive'] . ' 00:00:00');
    }
}
