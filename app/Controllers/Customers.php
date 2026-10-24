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
        catch(\Throwable $e){ return $this->response->setStatusCode(500)->setBody('Unable to generate customer template: '.esc(user_error_message($e))); }
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
                if($code!=='') { $key=strtoupper($code); if(isset($seenCodes[$key])) throw new \RuntimeException('Row '.$row['excel_row'].': duplicate customer code '.$code.' in the uploaded file.'); $seenCodes[$key]=true; if($this->model->where(['location_id'=>$locationId,'code'=>$code])->first()) throw new \RuntimeException('Row '.$row['excel_row'].': customer code '.$code.' already exists in this shop. Existing customers are not overwritten.'); }
                foreach([['credit_limit','Credit Limit'],['opening_balance','Opening Balance']] as [$field,$label]){ if($row[$field]===''||!is_numeric($row[$field])||(float)$row[$field]<0) throw new \RuntimeException('Row '.$row['excel_row'].': '.$label.' must be a number greater than or equal to 0.'); }
                foreach([['allow_credit_sale','Allow Credit Sale'],['is_active','Active']] as [$field,$label]) if(!in_array($row[$field],['0','1'],true)) throw new \RuntimeException('Row '.$row['excel_row'].': '.$label.' must be 0 or 1.');
                $data=['location_id'=>$locationId,'code'=>$code?:null,'name'=>$name,'phone'=>$row['phone']?:null,'city'=>$row['city']?:null,'address'=>$row['address']?:null,'vehicle_no'=>$row['vehicle_no']?:null,'credit_limit'=>(float)$row['credit_limit'],'allow_credit_sale'=>(int)$row['allow_credit_sale'],'opening_balance'=>(float)$row['opening_balance'],'is_active'=>(int)$row['is_active']];
                $id=$this->model->insert($data,true); if(!$id) throw new \RuntimeException('Row '.$row['excel_row'].': customer could not be created.');
                AuditService::log('excel_import','customer',(int)$id,null,$data,$userId,(int)(session()->get('location_id')??0)); $created++;
            }
            if(!$db->transStatus()) throw new \RuntimeException('Customer Excel import failed.'); $db->transCommit();
            return redirect()->to('/customers')->with('success',$created.' customer(s) imported successfully.');
        } catch(\Throwable $e) { if($db->transStatus()!==false) $db->transRollback(); return redirect()->back()->with('error','Customer Excel import failed: '.user_error_message($e)); }
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
            'balances'=>(new \App\Services\CustomerBalanceService())->osBalances($locationId),
            'edit'=>$edit,
        ]);
    }

    public function ledger(int $id)
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)session()->get('location_id');
        $ledger=(new \App\Services\LedgerService())->customerLedger($id,$locationId);
        if(!$ledger) return $this->response->setStatusCode(404)->setBody('Customer not found');
        ['customer'=>$customer,'sales'=>$sales,'receipts'=>$receipts,'deposits'=>$deposits,'custody'=>$custody]=$ledger;
        return view('customers/ledger',['title'=>'Customer Ledger — '.$customer['name'],'customer'=>$customer,'sales'=>$sales,'receipts'=>$receipts,'deposits'=>$deposits,'custody'=>$custody,'specialRates'=>(new \App\Services\RateService())->customerRateHistory((int)$customer['id'])]);
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        $locationId=(int)(session()->get('location_id')??0);
        if($locationId<=0) return $this->response->setStatusCode(403)->setBody('A shop context is required.');
        if($id && !$this->model->findForLocation($id,$locationId)) return $this->response->setStatusCode(404)->setBody('Customer not found.');
        // Shared with the staff API; validates, saves, revokes app sessions of a deactivated
        // customer and audit-logs before/after (BL-015, BL-021).
        try {
            $result=(new \App\Services\CustomerService($this->model))->save($id,$this->request->getPost(),(int)session()->get('user_id'),$locationId);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error',user_error_message($e));
        }
        return redirect()->to('/customers')->with('success',$result['created']?'Customer created.':'Customer updated.');
    }
}