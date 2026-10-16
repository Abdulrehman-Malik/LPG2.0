<?php

namespace App\Services;

use App\Models\CustomerModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Customer and supplier ledger data for one branch (Phase 10.1; moved from the Customers and
 * Suppliers controllers unchanged so the future mobile/API ledger can reuse it).
 */
class LedgerService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** @return array{customer:array,sales:array,receipts:array,deposits:array,custody:array}|null */
    public function customerLedger(int $id, int $locationId): ?array
    {
        $customer=(new CustomerModel())->withLedgerTotals($id,$locationId);
        if(!$customer) return null;
        $db=$this->db;
        $sales=$db->table('sales')->select('transaction_at,sale_no,transaction_type,total_amount,credit_amount,return_gas_ledger_amount,security_deposit_amount,security_deposit_refund_amount,status')->where('customer_id',$id)->where('location_id',$locationId)->orderBy('transaction_at','DESC')->get()->getResultArray();
        $receipts=$db->query(
            "SELECT receipt_at,receipt_no,amount,payment_mode,status,receipt_type,source,reference_no,details
             FROM (
                 SELECT cr.receipt_at,cr.receipt_no,cr.amount,cr.payment_mode,cr.status,
                        CASE WHEN cr.notes LIKE 'OS settlement collected with sale %' THEN 'POS OS Settlement' ELSE 'Customer Receipt' END AS receipt_type,
                        CASE WHEN cr.notes LIKE 'OS settlement collected with sale %' THEN 'POS — OS Settlement' ELSE 'Customer Receipts screen' END AS source,
                        cr.reference_no,cr.notes AS details
                 FROM customer_receipts cr
                 WHERE cr.customer_id=? AND cr.location_id=?
                 UNION ALL
                 SELECT sp.payment_at AS receipt_at,
                        CONCAT(s.sale_no, CASE sp.payment_type
                            WHEN 'security_deposit' THEN ' / Deposit'
                            WHEN 'security_deposit_refund' THEN ' / Deposit Refund'
                            ELSE ' / POS'
                        END) AS receipt_no,
                        sp.amount,sp.payment_mode,s.status,
                        CASE sp.payment_type
                            WHEN 'security_deposit' THEN 'POS Security Deposit'
                            WHEN 'security_deposit_refund' THEN 'POS Security Deposit Refund'
                            ELSE 'POS Sale Receipt'
                        END AS receipt_type,
                        CASE sp.payment_type
                            WHEN 'security_deposit' THEN 'POS — Security Deposit'
                            WHEN 'security_deposit_refund' THEN 'POS — Security Deposit Refund'
                            ELSE CONCAT('POS — ',REPLACE(s.transaction_type,'_',' '),' / Sale Payment')
                        END AS source,
                        sp.reference_no,
                        CASE sp.payment_type
                            WHEN 'security_deposit' THEN CONCAT('POS security deposit payment for ',s.sale_no)
                            WHEN 'security_deposit_refund' THEN CONCAT('POS security deposit refund for ',s.sale_no)
                            ELSE CONCAT('POS sale payment for ',s.sale_no)
                        END AS details
                 FROM sale_payments sp
                 JOIN sales s ON s.id=sp.sale_id
                 WHERE s.customer_id=? AND s.location_id=? AND sp.payment_mode <> 'credit'
             ) AS all_receipts
             ORDER BY receipt_at DESC,receipt_no DESC",
            [$id,$locationId,$id,$locationId]
        )->getResultArray();
        $deposits=$db->query(
            "SELECT transaction_at,entry_type,amount,sale_id,custody_id,notes
             FROM customer_security_deposits
             WHERE customer_id=? AND location_id=?
             UNION ALL
             SELECT s.transaction_at,
                    CASE sp.payment_type WHEN 'security_deposit' THEN 'hold' ELSE 'refund' END AS entry_type,
                    SUM(sp.amount) AS amount,
                    s.id AS sale_id,
                    NULL AS custody_id,
                    CASE sp.payment_type
                        WHEN 'security_deposit' THEN CONCAT('Recovered from POS deposit payment history — ',s.sale_no)
                        ELSE CONCAT('Recovered from POS deposit refund payment history — ',s.sale_no)
                    END AS notes
             FROM sale_payments sp
             JOIN sales s ON s.id=sp.sale_id
             WHERE s.customer_id=? AND s.location_id=? AND s.status='posted'
               AND sp.payment_type IN ('security_deposit','security_deposit_refund')
               AND NOT EXISTS (
                   SELECT 1 FROM customer_security_deposits csd
                   WHERE csd.sale_id=s.id
                     AND csd.entry_type=CASE sp.payment_type WHEN 'security_deposit' THEN 'hold' ELSE 'refund' END
               )
             GROUP BY s.id,s.sale_no,s.transaction_at,sp.payment_type",
            [$id,$locationId,$id,$locationId]
        )->getResultArray();
        usort($deposits,static fn($a,$b)=>strcmp((string)$b['transaction_at'],(string)$a['transaction_at']));
        $depositHeld=0.0;
        foreach($deposits as $depositRow){
            $depositHeld+=(($depositRow['entry_type']??'')==='hold'?1:-1)*(float)($depositRow['amount']??0);
        }
        $customer['security_deposit_held']=max(0,$depositHeld);
        $custody=$db->table('cylinder_custody cc')->select('cc.*,cu.unit_code,cu.gas_weight_kg,ct.code cylinder_code,ct.name cylinder_name')->join('cylinder_units cu','cu.id=cc.cylinder_unit_id')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where(['cc.location_id'=>$locationId,'cc.customer_id'=>$id,'cc.status'=>'issued'])->orderBy('cc.id')->get()->getResultArray();
        return ['customer'=>$customer,'sales'=>$sales,'receipts'=>$receipts,'deposits'=>$deposits,'custody'=>$custody];
    }

    /** @return array{supplier:array,purchases:array,payments:array}|null */
    public function supplierLedger(int $id, int $locationId): ?array
    {
        $supplier=BusinessContext::supplierVisibleTo($id,$locationId,false);
        if(!$supplier) return null;
        $db=$this->db;
        $purchases=$db->table('purchases')
            ->select('transaction_at,purchase_no,total_amount,credit_amount,status,(total_amount-credit_amount) AS paid_amount')
            ->where('supplier_id',$id)->where('location_id',$locationId)
            ->orderBy('transaction_at','DESC')->get()->getResultArray();

        $payments=$db->query(
            "SELECT payment_at,payment_no,amount,payment_mode,status,purchase_no,source
             FROM (
                SELECT pp.payment_at, CONCAT('PUR-', p.purchase_no) AS payment_no,
                       pp.amount, pp.payment_mode, p.status, p.purchase_no,
                       'Purchase Payment' AS source
                FROM purchase_payments pp
                JOIN purchases p ON p.id=pp.purchase_id
                WHERE p.supplier_id=? AND p.location_id=?
                UNION ALL
                SELECT sp.payment_at, sp.payment_no, sp.amount, sp.payment_mode,
                       sp.status, NULL AS purchase_no,
                       'Supplier Account Payment' AS source
                FROM supplier_payments sp
                WHERE sp.supplier_id=? AND sp.location_id=?
             ) x
             ORDER BY payment_at DESC",
            [$id,$locationId,$id,$locationId]
        )->getResultArray();

        $credit=(float)$supplier['opening_balance'];
        foreach($purchases as $row) if($row['status']==='posted') $credit+=(float)$row['credit_amount'];
        foreach($payments as $row) if($row['status']==='posted' && $row['source']==='Supplier Account Payment') $credit-=(float)$row['amount'];
        $supplier['credit_due']=$credit;
        return ['supplier'=>$supplier,'purchases'=>$purchases,'payments'=>$payments];
    }
}
