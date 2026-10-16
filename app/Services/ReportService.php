<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Head Office reporting across the branches of one business (Phase 9.2).
 *
 * Every figure is derived from posted source transactions or current physical stock,
 * never from stored totals. Each metric is one grouped query over all requested
 * branches, so the report cost does not grow with the number of branches.
 * Callers pass only branch IDs the user is authorized to see.
 */
class ReportService
{
    /** POS types that are custody/deposit movements, not sales revenue. */
    private const NON_REVENUE_TYPES = ['security_deposit', 'cylinder_return'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param list<int> $locationIds
     * @return array{branches: array<int,array>, totals: array, payment_modes: list<string>}
     */
    public function branchSummary(array $locationIds, string $from, string $to): array
    {
        $locationIds = array_values(array_unique(array_filter(array_map('intval', $locationIds))));
        if (!$locationIds) {
            return ['branches' => [], 'totals' => $this->emptyRow(), 'payment_modes' => []];
        }
        $start = $from . ' 00:00:00';
        $end = $to . ' 23:59:59';
        $in = implode(',', array_fill(0, count($locationIds), '?'));

        $branches = [];
        foreach ($this->db->query("SELECT id,code,name,city FROM locations WHERE id IN ($in) ORDER BY name", $locationIds)->getResultArray() as $row) {
            $branches[(int) $row['id']] = $row + $this->emptyRow();
        }

        $merge = function (string $sql, array $params, callable $apply) use (&$branches): void {
            foreach ($this->db->query($sql, $params)->getResultArray() as $row) {
                $id = (int) $row['location_id'];
                if (isset($branches[$id])) {
                    $apply($branches[$id], $row);
                }
            }
        };
        $nonRevenue = "'" . implode("','", self::NON_REVENUE_TYPES) . "'";

        // Sales revenue (posted, excluding deposit/custody transactions).
        $merge(
            "SELECT location_id,COUNT(*) sale_count,COALESCE(SUM(total_amount),0) sales_total,COALESCE(SUM(total_kg),0) gas_kg_sold,
                    COALESCE(SUM(credit_amount),0) credit_created,COALESCE(SUM(discount_amount),0) discounts
             FROM sales WHERE location_id IN ($in) AND status='posted' AND transaction_type NOT IN ($nonRevenue)
               AND transaction_at BETWEEN ? AND ? GROUP BY location_id",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r): void {
                $b['sale_count'] = (int) $r['sale_count'];
                $b['sales_total'] = (float) $r['sales_total'];
                $b['gas_kg_sold'] = (float) $r['gas_kg_sold'];
                $b['credit_created'] = (float) $r['credit_created'];
                $b['discounts'] = (float) $r['discounts'];
            }
        );
        $merge(
            "SELECT location_id,COUNT(*) voided FROM sales WHERE location_id IN ($in) AND status='voided' AND transaction_at BETWEEN ? AND ? GROUP BY location_id",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r): void { $b['voided_count'] = (int) $r['voided']; }
        );

        // Sale payments by mode (sale portion only; deposits/refunds are reported separately).
        $modes = [];
        $merge(
            "SELECT s.location_id,sp.payment_mode,COALESCE(SUM(sp.amount),0) amount
             FROM sale_payments sp JOIN sales s ON s.id=sp.sale_id
             WHERE s.location_id IN ($in) AND s.status='posted' AND s.transaction_type NOT IN ($nonRevenue)
               AND COALESCE(sp.payment_type,'sale')='sale' AND sp.payment_at BETWEEN ? AND ?
             GROUP BY s.location_id,sp.payment_mode",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r) use (&$modes): void {
                $b['payments'][$r['payment_mode']] = (float) $r['amount'];
                $modes[$r['payment_mode']] = true;
            }
        );

        $merge(
            "SELECT location_id,COALESCE(SUM(amount),0) amount FROM customer_receipts WHERE location_id IN ($in) AND status='posted' AND receipt_at BETWEEN ? AND ? GROUP BY location_id",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r): void { $b['customer_receipts'] = (float) $r['amount']; }
        );
        $merge(
            "SELECT location_id,COUNT(*) purchase_count,COALESCE(SUM(total_amount),0) total,COALESCE(SUM(credit_amount),0) credit FROM purchases WHERE location_id IN ($in) AND status='posted' AND transaction_at BETWEEN ? AND ? GROUP BY location_id",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r): void {
                $b['purchase_count'] = (int) $r['purchase_count'];
                $b['purchases_total'] = (float) $r['total'];
                $b['purchases_credit'] = (float) $r['credit'];
            }
        );
        $merge(
            "SELECT location_id,COALESCE(SUM(amount),0) amount FROM supplier_payments WHERE location_id IN ($in) AND status='posted' AND payment_at BETWEEN ? AND ? GROUP BY location_id",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r): void { $b['supplier_payments'] = (float) $r['amount']; }
        );
        $merge(
            "SELECT location_id,COALESCE(SUM(amount),0) amount FROM expenses WHERE location_id IN ($in) AND expense_at BETWEEN ? AND ? GROUP BY location_id",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r): void { $b['expenses'] = (float) $r['amount']; }
        );
        $merge(
            "SELECT location_id,COALESCE(SUM(CASE WHEN entry_type='hold' THEN amount ELSE 0 END),0) held,COALESCE(SUM(CASE WHEN entry_type='refund' THEN amount ELSE 0 END),0) refunded
             FROM customer_security_deposits WHERE location_id IN ($in) AND transaction_at BETWEEN ? AND ? GROUP BY location_id",
            [...$locationIds, $start, $end],
            static function (array &$b, array $r): void {
                $b['deposits_received'] = (float) $r['held'];
                $b['deposits_refunded'] = (float) $r['refunded'];
            }
        );

        // Current state (not limited by the date range).
        $merge(
            "SELECT location_id,
                    COALESCE(SUM(CASE WHEN status='filled' THEN gas_weight_kg ELSE 0 END),0) gas_stock,
                    SUM(status='filled') filled,SUM(status='empty') empty_count,SUM(status='custody') custody,SUM(status='in_transit') in_transit
             FROM cylinder_units WHERE location_id IN ($in) GROUP BY location_id",
            $locationIds,
            static function (array &$b, array $r): void {
                $b['gas_stock_kg'] = (float) $r['gas_stock'];
                $b['filled_cylinders'] = (int) $r['filled'];
                $b['empty_cylinders'] = (int) $r['empty_count'];
                $b['custody_cylinders'] = (int) $r['custody'];
                $b['outgoing_in_transit'] = (int) $r['in_transit'];
            }
        );
        $merge(
            "SELECT st.to_location_id location_id,COUNT(DISTINCT st.id) transfers,COUNT(sti.id) units
             FROM stock_transfers st JOIN stock_transfer_items sti ON sti.transfer_id=st.id
             WHERE st.to_location_id IN ($in) AND st.status='in_transit' GROUP BY st.to_location_id",
            $locationIds,
            static function (array &$b, array $r): void {
                $b['incoming_transfers'] = (int) $r['transfers'];
                $b['incoming_in_transit'] = (int) $r['units'];
            }
        );
        $merge(
            "SELECT location_id,COALESCE(SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END),0) balance
             FROM customer_security_deposits WHERE location_id IN ($in) GROUP BY location_id",
            $locationIds,
            static function (array &$b, array $r): void { $b['deposit_balance'] = max(0, (float) $r['balance']); }
        );
        // Customer outstanding: same formula as POS (opening + credit + return-gas ledger - receipts), positive balances only.
        $merge(
            "SELECT c.location_id,COUNT(*) customers,COALESCE(SUM(b.balance),0) os
             FROM customers c
             JOIN (
                SELECT c2.id,c2.opening_balance+COALESCE(s.credit,0)+COALESCE(s.return_ledger,0)-COALESCE(r.paid,0) balance
                FROM customers c2
                LEFT JOIN (SELECT customer_id,location_id,SUM(credit_amount) credit,SUM(return_gas_ledger_amount) return_ledger FROM sales WHERE status='posted' AND location_id IN ($in) GROUP BY customer_id,location_id) s ON s.customer_id=c2.id AND s.location_id=c2.location_id
                LEFT JOIN (SELECT customer_id,location_id,SUM(amount) paid FROM customer_receipts WHERE status='posted' AND location_id IN ($in) GROUP BY customer_id,location_id) r ON r.customer_id=c2.id AND r.location_id=c2.location_id
                WHERE c2.location_id IN ($in)
             ) b ON b.id=c.id
             WHERE b.balance>0.009 GROUP BY c.location_id",
            [...$locationIds, ...$locationIds, ...$locationIds],
            static function (array &$b, array $r): void {
                $b['customers_with_os'] = (int) $r['customers'];
                $b['customer_os'] = (float) $r['os'];
            }
        );
        // Open counter cash session per branch: expected cash = cash in - cash out.
        $merge(
            "SELECT cr.location_id,COUNT(DISTINCT cs.id) open_sessions,
                    COALESCE(SUM(CASE WHEN ct.direction='in' THEN ct.amount WHEN ct.direction='out' THEN -ct.amount ELSE 0 END),0) expected
             FROM cash_sessions cs JOIN cash_registers cr ON cr.id=cs.register_id
             LEFT JOIN cash_transactions ct ON ct.cash_session_id=cs.id
             WHERE cr.location_id IN ($in) AND cs.status='open' GROUP BY cr.location_id",
            $locationIds,
            static function (array &$b, array $r): void {
                $b['cash_open'] = (int) $r['open_sessions'] > 0;
                $b['cash_expected'] = (float) $r['expected'];
            }
        );

        $totals = $this->emptyRow();
        foreach ($branches as $b) {
            foreach ($totals as $key => $value) {
                if ($key === 'payments') {
                    foreach ($b['payments'] as $mode => $amount) {
                        $totals['payments'][$mode] = ($totals['payments'][$mode] ?? 0) + $amount;
                    }
                } elseif ($key === 'cash_open') {
                    $totals['cash_open'] = $totals['cash_open'] || $b['cash_open'];
                } elseif (is_int($value) || is_float($value)) {
                    $totals[$key] += $b[$key];
                }
            }
        }
        $modes = array_keys($modes);
        $order = ['cash' => 0, 'cheque' => 1, 'online' => 2, 'credit' => 3];
        usort($modes, static fn ($a, $b) => ($order[$a] ?? 9) <=> ($order[$b] ?? 9));

        return ['branches' => $branches, 'totals' => $totals, 'payment_modes' => $modes];
    }

    /** Daily sales total per branch for a trend chart. */
    public function dailySales(array $locationIds, string $from, string $to): array
    {
        $locationIds = array_values(array_unique(array_filter(array_map('intval', $locationIds))));
        if (!$locationIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($locationIds), '?'));
        $nonRevenue = "'" . implode("','", self::NON_REVENUE_TYPES) . "'";
        return $this->db->query(
            "SELECT DATE(transaction_at) day,location_id,COALESCE(SUM(total_amount),0) total
             FROM sales WHERE location_id IN ($in) AND status='posted' AND transaction_type NOT IN ($nonRevenue)
               AND transaction_at BETWEEN ? AND ? GROUP BY DATE(transaction_at),location_id ORDER BY day",
            [...$locationIds, $from . ' 00:00:00', $to . ' 23:59:59']
        )->getResultArray();
    }

    /**
     * Single-branch period report for the Reports page (Phase 10.1; moved from the controller unchanged).
     * @return array{sales:array,saleRows:array,purchases:array,payments:array,deposits:array,depositBalance:float}
     */
    public function periodReport(int $loc, string $from, string $to): array
    {
        $sales=$this->db->table('sales')->select('COUNT(*) count,SUM(total_amount) total,SUM(credit_amount) credit')->where(['location_id'=>$loc,'status'=>'posted'])->where('transaction_type !=','security_deposit')->where('transaction_type !=','cylinder_return')->where('transaction_at >=',$from.' 00:00:00')->where('transaction_at <=',$to.' 23:59:59')->get()->getRowArray();
        $saleRows=$this->db->table('sales s')->select('s.id,s.sale_no,s.transaction_at,s.transaction_type,s.total_amount,s.credit_amount,c.name customer_name,u.full_name created_by_name')->join('customers c','c.id=s.customer_id','left')->join('users u','u.id=s.created_by','left')->where(['s.location_id'=>$loc,'s.status'=>'posted'])->whereNotIn('s.transaction_type',['security_deposit','cylinder_return'])->where('s.transaction_at >=',$from.' 00:00:00')->where('s.transaction_at <=',$to.' 23:59:59')->orderBy('s.transaction_at','DESC')->orderBy('s.id','DESC')->get()->getResultArray();
        $purchases=$this->db->table('purchases')->select('COUNT(*) count,SUM(total_amount) total,SUM(credit_amount) credit')->where(['location_id'=>$loc,'status'=>'posted'])->where('transaction_at >=',$from.' 00:00:00')->where('transaction_at <=',$to.' 23:59:59')->get()->getRowArray();
        $payments=$this->db->table('sale_payments sp')->select('payment_mode,SUM(amount) amount')->join('sales s','s.id=sp.sale_id')->where('s.location_id',$loc)->where('s.status','posted')->whereNotIn('s.transaction_type',['security_deposit','cylinder_return'])->where('sp.payment_at >=',$from.' 00:00:00')->where('sp.payment_at <=',$to.' 23:59:59')->groupBy('payment_mode')->get()->getResultArray();
        $deposits=$this->db->table('customer_security_deposits')->select("SUM(CASE WHEN entry_type='hold' THEN amount ELSE 0 END) held,SUM(CASE WHEN entry_type='refund' THEN amount ELSE 0 END) refunded")->where(['location_id'=>$loc])->where('transaction_at >=',$from.' 00:00:00')->where('transaction_at <=',$to.' 23:59:59')->get()->getRowArray();
        $depositBalance=$this->db->table('customer_security_deposits')->select("SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END) balance")->where(['location_id'=>$loc])->get()->getRowArray();
        
        return ['sales'=>$sales,'saleRows'=>$saleRows,'purchases'=>$purchases,'payments'=>$payments,'deposits'=>$deposits,'depositBalance'=>(float)($depositBalance['balance']??0)];
    }

    private function emptyRow(): array
    {
        return [
            'sale_count' => 0, 'sales_total' => 0.0, 'gas_kg_sold' => 0.0, 'credit_created' => 0.0, 'discounts' => 0.0,
            'voided_count' => 0, 'payments' => [], 'customer_receipts' => 0.0,
            'purchase_count' => 0, 'purchases_total' => 0.0, 'purchases_credit' => 0.0, 'supplier_payments' => 0.0,
            'expenses' => 0.0, 'deposits_received' => 0.0, 'deposits_refunded' => 0.0,
            'gas_stock_kg' => 0.0, 'filled_cylinders' => 0, 'empty_cylinders' => 0, 'custody_cylinders' => 0,
            'outgoing_in_transit' => 0, 'incoming_transfers' => 0, 'incoming_in_transit' => 0,
            'deposit_balance' => 0.0, 'customers_with_os' => 0, 'customer_os' => 0.0,
            'cash_open' => false, 'cash_expected' => 0.0,
        ];
    }
}
