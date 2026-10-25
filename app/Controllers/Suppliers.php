<?php

namespace App\Controllers;

use App\Models\SupplierModel;
use App\Services\BusinessContext;
use App\Services\PermissionService;
use App\Services\SupplierBalanceService;
use App\Services\SupplierService;
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
    private function scopeMode(): string { return (new SupplierService($this->model))->scopeMode($this->locationId()); }

    public function index()
    {
        if($r=$this->guard()) return $r;
        $editId=(int)$this->request->getGet('edit');
        $balances=new SupplierBalanceService();
        $suppliers=$this->model->visibleTo($this->locationId());
        $edit=$editId?BusinessContext::supplierVisibleTo($editId,$this->locationId(),false):null;
        $openings=[];
        foreach($suppliers as $s) $openings[(int)$s['id']]=$balances->opening($s,$this->locationId());
        if($edit) $edit['branch_opening']=$balances->branchOpening((int)$edit['id'],$this->locationId());
        return view('suppliers/index',[
            'title'=>'Suppliers',
            'suppliers'=>$suppliers,
            'balances'=>$balances->balances($suppliers,$this->locationId()),
            'openings'=>$openings,
            'balanceMode'=>$balances->mode($this->locationId()),
            'branchName'=>(string)($this->model->db->table('locations')->select('name')->where('id',$this->locationId())->get()->getRowArray()['name']??''),
            'edit'=>$edit,
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
        return view('suppliers/ledger',['title'=>'Supplier Ledger — '.$supplier['name'],'supplier'=>$supplier,'purchases'=>$purchases,'payments'=>$payments,'pooled'=>$ledger['pooled']]);
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        try {
            $result=(new SupplierService($this->model))->save((int)$this->request->getPost('id'),$this->request->getPost(),(int)session()->get('user_id'),$this->locationId());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error','Supplier could not be saved: '.user_error_message($e));
        }
        return redirect()->to('/suppliers')->with('success',$result['created']?'Supplier created.':'Supplier updated.');
    }
}
