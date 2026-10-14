<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Services\PermissionService;
use App\Services\ExcelInventoryService;
use App\Services\AuditService;
use CodeIgniter\Controller;

class Customers extends Controller
{
    protected CustomerModel $model;
    public function __construct(){ $this->model=new CustomerModel(); }

    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if(!PermissionService::allows('CUSTOMER_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
        return null;
    }

    public function downloadTemplate()
    {
        if($r=$this->guard()) return $r;
        $path=WRITEPATH.'uploads/customer_template.xlsx';
        try { (new ExcelInventoryService())->createCustomerTemplate($path); return $this->response->download($path,null)->setFileName('customer_template.xlsx'); }
        catch(\Throwable $e){ return $this->response->setStatusCode(500)->setBody('Unable to generate customer template: '.esc($e->getMessage())); }
    }

    public function importExcel()
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)(session()->get('location_id')??0);
        if($locationId<=0) return $this->response->setStatusCode(403)->setBody('A shop context is required.');
        $file=$this->request->getFile('customer_excel');
        if(!$file || !$file->isValid()) return redirect()->back()->with('error','Please select a valid .xlsx customer file.');
        if(strtolower($file->getExtension())!=='xlsx') return redirect()->back()->with('error','Only .xlsx Excel files are supported.');
        $path=$file->getTempName(); $db=$this->model->db;
        try {
            $rows=(new ExcelInventoryService())->readCustomers($path); $db->transBegin(); $created=0; $seenCodes=[]; $userId=(int)(session()->get('user_id')??0);
            foreach($rows as $row){
                $code=$row['code']; $name=$row['name'];
                if($name==='') throw new \RuntimeException('Row '.$row['excel_row'].': Customer Name is required.');
                if($code!=='' && !preg_match('/^[A-Za-z0-9_-]{1,30}$/',$code)) throw new \RuntimeException('Row '.$row['excel_row'].': Code may contain only letters, numbers, underscore and dash, maximum 30 characters.');
                if($code!=='') { $key=strtoupper($code); if(isset($seenCodes[$key])) throw new \RuntimeException('Row '.$row['excel_row'].': duplicate customer code '.$code.' in the uploaded file.'); $seenCodes[$key]=true; if($this->model->where('code',$code)->first()) throw new \RuntimeException('Row '.$row['excel_row'].': customer code '.$code.' already exists. Existing customers are not overwritten.'); }
                foreach([['credit_limit','Credit Limit'],['opening_balance','Opening Balance']] as [$field,$label]){ if($row[$field]===''||!is_numeric($row[$field])||(float)$row[$field]<0) throw new \RuntimeException('Row '.$row['excel_row'].': '.$label.' must be a number greater than or equal to 0.'); }
                foreach([['allow_credit_sale','Allow Credit Sale'],['is_active','Active']] as [$field,$label]) if(!in_array($row[$field],['0','1'],true)) throw new \RuntimeException('Row '.$row['excel_row'].': '.$label.' must be 0 or 1.');
                $data=['location_id'=>$locationId,'code'=>$code?:null,'name'=>$name,'phone'=>$row['phone']?:null,'city'=>$row['city']?:null,'address'=>$row['address']?:null,'vehicle_no'=>$row['vehicle_no']?:null,'credit_limit'=>(float)$row['credit_limit'],'allow_credit_sale'=>(int)$row['allow_credit_sale'],'opening_balance'=>(float)$row['opening_balance'],'is_active'=>(int)$row['is_active']];
                $id=$this->model->insert($data,true); if(!$id) throw new \RuntimeException('Row '.$row['excel_row'].': customer could not be created.');
                AuditService::log('excel_import','customer',(int)$id,null,$data,$userId,(int)(session()->get('location_id')??0)); $created++;
            }
            if(!$db->transStatus()) throw new \RuntimeException('Customer Excel import failed.'); $db->transCommit();
            return redirect()->to('/customers')->with('success',$created.' customer(s) imported successfully.');
        } catch(\Throwable $e) { if($db->transStatus()!==false) $db->transRollback(); return redirect()->back()->with('error','Customer Excel import failed: '.$e->getMessage()); }
    }

    public function index()
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)(session()->get('location_id')??0);
        if($locationId<=0) return $this->response->setStatusCode(403)->setBody('A shop context is required.');
        $editId=(int)$this->request->getGet('edit');
        $edit=$editId?$this->model->findForLocation($editId,$locationId):null;
        return view('customers/index',[
            'title'=>'Customers / Parties',
            'customers'=>$this->model->where('location_id',$locationId)->orderBy('name')->findAll(),
            'edit'=>$edit,
        ]);
    }

    public function ledger(int $id)
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)session()->get('location_id');
        $customer=$this->model->withLedgerTotals($id,$locationId);
        if(!$customer) return $this->response->setStatusCode(404)->setBody('Customer not found');
        $db=$this->model->db;
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
        return view('customers/ledger',['title'=>'Customer Ledger — '.$customer['name'],'customer'=>$customer,'sales'=>$sales,'receipts'=>$receipts,'deposits'=>$deposits,'custody'=>$custody]);
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        $locationId=(int)(session()->get('location_id')??0);
        if($locationId<=0) return $this->response->setStatusCode(403)->setBody('A shop context is required.');
        if($id && !$this->model->findForLocation($id,$locationId)) return $this->response->setStatusCode(404)->setBody('Customer not found.');
        $data=[
            'location_id'=>$locationId,
            'code'=>trim((string)$this->request->getPost('code')) ?: null,
            'name'=>trim((string)$this->request->getPost('name')),
            'phone'=>trim((string)$this->request->getPost('phone')) ?: null,
            'city'=>trim((string)$this->request->getPost('city')) ?: null,
            'address'=>trim((string)$this->request->getPost('address')) ?: null,
            'vehicle_no'=>trim((string)$this->request->getPost('vehicle_no')) ?: null,
            'credit_limit'=>(float)$this->request->getPost('credit_limit'),
            'allow_credit_sale'=>$this->request->getPost('allow_credit_sale') ? 1 : 0,
            'opening_balance'=>(float)$this->request->getPost('opening_balance'),
            'is_active'=>$this->request->getPost('is_active') ? 1 : 0,
        ];
        if($data['name']==='') return redirect()->back()->withInput()->with('error','Customer name is required.');
        if($data['credit_limit']<0||$data['opening_balance']<0) return redirect()->back()->withInput()->with('error','Credit limit and opening balance cannot be negative.');
        try { if($id) $this->model->update($id,$data); else $this->model->insert($data); } catch (\Throwable $e) { return redirect()->back()->withInput()->with('error','Customer could not be saved. Check the code for duplicates.'); }
        return redirect()->to('/customers')->with('success',$id?'Customer updated.':'Customer created.');
    }
}