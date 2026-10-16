<?php

namespace App\Controllers;

use App\Models\SupplierModel;
use App\Services\BusinessContext;
use App\Services\ConfigurationService;
use App\Services\PermissionService;
use CodeIgniter\Controller;

/**
 * Suppliers belong to a business and are either business-wide or limited to one branch.
 * Which of those is allowed is the business setting supplier_scope_mode (D9-3):
 * shared (business-wide only), branch (branch-only) or both (chosen per supplier).
 * Supplier balances are always tracked per branch.
 */
class Suppliers extends Controller
{
    protected SupplierModel $model;
    public function __construct(){ $this->model=new SupplierModel(); }
    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if(!PermissionService::allows('SUPPLIER_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
        return null;
    }
    private function locationId(): int { return (int)session()->get('location_id'); }
    private function scopeMode(): string { return (string)(new ConfigurationService())->valueForShop('supplier_scope_mode',$this->locationId()); }

    public function index()
    {
        if($r=$this->guard()) return $r;
        $editId=(int)$this->request->getGet('edit');
        return view('suppliers/index',[
            'title'=>'Suppliers',
            'suppliers'=>$suppliers=$this->model->visibleTo($this->locationId()),
            'balances'=>(new \App\Services\PurchaseService())->supplierBalances($suppliers,$this->locationId()),
            'edit'=>$editId?BusinessContext::supplierVisibleTo($editId,$this->locationId(),false):null,
            'scopeMode'=>$this->scopeMode(),
        ]);
    }

    public function ledger(int $id)
    {
        if($r=$this->guard()) return $r;
        $locationId=$this->locationId();
        $ledger=(new \App\Services\LedgerService())->supplierLedger($id,$locationId);
        if(!$ledger) return $this->response->setStatusCode(404)->setBody('Supplier not found');
        ['supplier'=>$supplier,'purchases'=>$purchases,'payments'=>$payments]=$ledger;
        return view('suppliers/ledger',['title'=>'Supplier Ledger — '.$supplier['name'],'supplier'=>$supplier,'purchases'=>$purchases,'payments'=>$payments]);
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        $locationId=$this->locationId();
        $businessId=BusinessContext::businessIdForLocation($locationId);
        $data=[
            'code'=>trim((string)$this->request->getPost('code')) ?: null,
            'name'=>trim((string)$this->request->getPost('name')),
            'phone'=>trim((string)$this->request->getPost('phone')) ?: null,
            'city'=>trim((string)$this->request->getPost('city')) ?: null,
            'address'=>trim((string)$this->request->getPost('address')) ?: null,
            'credit_limit'=>(float)$this->request->getPost('credit_limit'),
            'opening_balance'=>(float)$this->request->getPost('opening_balance'),
            'is_active'=>$this->request->getPost('is_active') ? 1 : 0,
        ];
        if($data['name']==='') return redirect()->back()->withInput()->with('error','Supplier name is required.');
        if($data['credit_limit']<0||$data['opening_balance']<0) return redirect()->back()->withInput()->with('error','Credit limit and opening balance cannot be negative.');

        $mode=$this->scopeMode();
        $requested=(string)$this->request->getPost('supplier_scope');
        $branchOnly=match($mode){ 'branch'=>true, 'both'=>$requested==='branch', default=>false };
        $data['location_id']=$branchOnly?$locationId:null;

        try {
            if($data['code']!==null){
                $duplicate=$this->model->where(['business_id'=>$businessId,'code'=>$data['code']])->where('id !=',$id)->first();
                if($duplicate) throw new \RuntimeException('Another supplier of this business already uses this code.');
            }
            if($id){
                $existing=BusinessContext::supplierVisibleTo($id,$locationId,false);
                if(!$existing) throw new \RuntimeException('Supplier not found.');
                if($branchOnly && $existing['location_id']===null && $this->usedByOtherBranches($id,$locationId)){
                    throw new \RuntimeException('This supplier already has transactions in other branches, so it must stay business-wide.');
                }
                if($mode==='shared' && $existing['location_id']!==null){
                    // Business switched to shared suppliers: existing branch-only suppliers become business-wide when edited.
                    $data['location_id']=null;
                }
                $this->model->update($id,$data);
            } else {
                $this->model->insert($data+['business_id'=>$businessId]);
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error','Supplier could not be saved: '.$e->getMessage());
        }
        return redirect()->to('/suppliers')->with('success',$id?'Supplier updated.':'Supplier created.');
    }

    private function usedByOtherBranches(int $supplierId,int $locationId): bool
    {
        $db=$this->model->db;
        return $db->table('purchases')->where('supplier_id',$supplierId)->where('location_id !=',$locationId)->countAllResults()>0
            || $db->table('supplier_payments')->where('supplier_id',$supplierId)->where('location_id !=',$locationId)->countAllResults()>0;
    }
}
