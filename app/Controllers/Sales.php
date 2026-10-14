<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\CylinderTypeModel;
use App\Models\GasRateModel;
use App\Models\ShopSettingsModel;
use App\Services\PermissionService;
use App\Services\SalesService;
use CodeIgniter\Controller;

class Sales extends Controller
{
    private function guard(string $permission='POS_SALE'): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if(!PermissionService::allows($permission)) return $this->response->setStatusCode(403)->setBody('Forbidden');
        return null;
    }

    public function index()
    {
        if($r=$this->guard()) return $r;
        $types=(new CylinderTypeModel())->activeOrdered();
        $customers=(new CustomerModel())->activeDirectory();
        $service=new SalesService();
        $balances=[];$creditLimits=[];$shopOutstanding=0.0;
        foreach($customers as $c){$balances[$c['id']]=$service->customerBalance((int)$c['id']);$creditLimits[$c['id']]=(float)$c['credit_limit'];}
        foreach($balances as $balance){if((float)$balance>0)$shopOutstanding+=(float)$balance;}
        $rateModel=new GasRateModel();
        $cylinderRates=[];
        foreach($types as $type) $cylinderRates[$type['id']]=$rateModel->currentCylinderRate((int)$type['id']);
        $shopSettings=(new ShopSettingsModel())->forLocation((int)session()->get('location_id')); $defaultTransactionType=(string)($shopSettings['default_transaction_type']??'gas_sale'); $defaultGasEntryMode=(string)($shopSettings['default_gas_entry_mode']??'quantity');
        $locationId=(int)session()->get('location_id'); $cashSession=(new \App\Services\CashService())->openSessionForLocation($locationId); $inv=new \App\Services\InventoryService(); $cylinders=new \App\Services\CylinderUnitService(); $filledStock=[]; $filledUnits=[]; $emptyStock=[]; $gasStock=0.0; foreach($types as $type){$typeId=(int)$type['id']; $filledStock[$typeId]=$inv->stock($locationId,'filled_cylinder',$typeId); $filledUnits[$typeId]=$cylinders->availableForDisplay($locationId,$typeId,'filled'); foreach($filledUnits[$typeId] as $unit) $gasStock+=(float)$unit['gas_weight_kg']; $emptyStock[$typeId]=$inv->stock($locationId,'empty_cylinder',$typeId);} 
        $db=\Config\Database::connect(); $depositRows=$db->table('customer_security_deposits')->select("customer_id,SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END) AS balance")->where('location_id',$locationId)->groupBy('customer_id')->get()->getResultArray(); $depositBalances=[]; foreach($depositRows as $dr) $depositBalances[(int)$dr['customer_id']]=max(0,(float)$dr['balance']); $custodyUnits=$db->table('cylinder_custody cc')->select('cc.id custody_id,cc.customer_id,cc.deposit_amount,cu.id unit_id,cu.unit_code,cu.cylinder_type_id,cu.gas_weight_kg,cu.status cylinder_status,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg')->join('cylinder_units cu','cu.id=cc.cylinder_unit_id')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where(['cc.location_id'=>$locationId,'cc.status'=>'issued'])->orderBy('cu.id')->get()->getResultArray(); 
        $availableCustodyUnits=$cylinders->availableCompanyUnits($locationId);
        return view('sales/index',['title'=>'POS Sales','types'=>$types,'customers'=>$customers,'balances'=>$balances,'creditLimits'=>$creditLimits,'kgRate'=>$rateModel->currentKgRate(),'cylinderRates'=>$cylinderRates,'cashSession'=>$cashSession,'filledStock'=>$filledStock,'filledUnits'=>$filledUnits,'emptyStock'=>$emptyStock,'gasStock'=>$gasStock,'defaultTransactionType'=>$defaultTransactionType,'defaultGasEntryMode'=>$defaultGasEntryMode,'shopSettings'=>$shopSettings,'inventoryPolicy'=>(new \App\Services\InventoryControlService())->policy($locationId),'creditLimitMode'=>$shopSettings['credit_limit_validation_mode']??'none','shopCreditLimit'=>(float)($shopSettings['shop_credit_limit']??0),'shopOutstanding'=>$shopOutstanding,'individualCylinderTracking'=>(int)($shopSettings['individual_cylinder_tracking']??0),'allowPosSourceCylinderSelection'=>(int)($shopSettings['allow_pos_source_cylinder_selection']??0),'custodyUnits'=>$custodyUnits,'depositBalances'=>$depositBalances,'availableCustodyUnits'=>$availableCustodyUnits,'canVoidSales'=>PermissionService::allows('POS_VOID')]);
    }

    public function custody(int $customerId)
    {
        if($r=$this->guard()) return $r;
        if($customerId<=0) return $this->response->setJSON([]);
        $units=(new \App\Services\CylinderUnitService())->customerCustody((int)session()->get('location_id'),$customerId);
        return $this->response->setJSON($units);
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        try{
            $transactionType=trim((string)$this->request->getPost('transaction_type'));
            $shopSettings=(new ShopSettingsModel())->forLocation((int)session()->get('location_id'));
            if (!(new ShopSettingsModel())->isTransactionTypeVisible($shopSettings, $transactionType)) {
                throw new \RuntimeException('This POS transaction type is not enabled for the current shop.');
            }
            $lines=json_decode((string)$this->request->getPost('lines_json'),true);
            $payments=json_decode((string)$this->request->getPost('payments_json'),true);
            if(!is_array($lines)||!is_array($payments)) throw new \RuntimeException('Invalid POS line or payment data.');
            $result=(new SalesService())->post(['transaction_type'=>$this->request->getPost('transaction_type'),'customer_id'=>$this->request->getPost('customer_id'),'transaction_at'=>$this->request->getPost('transaction_at'),'discount_amount'=>$this->request->getPost('discount_amount'),'security_deposit_amount'=>$this->request->getPost('security_deposit_amount'),'security_deposit_refund_amount'=>$this->request->getPost('security_deposit_refund_amount'),'custody_unit_ids'=>$this->request->getPost('custody_unit_ids')?:[],'return_unit_ids'=>$this->request->getPost('return_unit_ids')?:[],'lines'=>$lines,'payments'=>$payments,'notes'=>$this->request->getPost('notes'),'stock_override_confirmed'=>$this->request->getPost('stock_override_confirmed')],(int)session()->get('user_id'),(int)session()->get('location_id'));
            if ($this->request->isAJAX() || str_contains(strtolower((string)$this->request->getHeaderLine('Accept')), 'application/json')) {
                session()->setFlashdata('success','Transaction '.$result['sale_no'].' posted successfully.');
                session()->setFlashdata('receipt_url',site_url('sales/receipt/'.$result['id']));
                return $this->response->setJSON(['success'=>true,'sale_no'=>$result['sale_no'],'id'=>$result['id'],'receipt_url'=>site_url('sales/receipt/'.$result['id']),'redirect'=>site_url('sales')]);
            }
            return redirect()->to('/sales')
                ->with('success','Transaction '.$result['sale_no'].' posted successfully.')
                ->with('receipt_url',site_url('sales/receipt/'.$result['id']));
        }catch(\Throwable $e){
            log_message('error', 'POS sale posting failed: {message} at {file}:{line}', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if ($this->request->isAJAX() || str_contains(strtolower((string)$this->request->getHeaderLine('Accept')), 'application/json')) {
                return $this->response->setStatusCode(422)->setJSON(['success'=>false,'error'=>$e->getMessage()]);
            }
            return redirect()->back()->withInput()->with('error',$e->getMessage());
        }
    }

    public function customerBalance(int $id)
    {
        if($r=$this->guard()) return $r;
        $customer=(new CustomerModel())->find($id);
        if(!$customer || (int)($customer['is_active']??0)!==1) {
            return $this->response->setStatusCode(404)->setJSON(['error'=>'Customer not found.']);
        }
        try {
            $balance=(new SalesService())->customerBalance($id);
            return $this->response->setJSON(['customer_id'=>$id,'balance'=>$balance]);
        } catch (\Throwable $e) {
            log_message('error','POS customer balance refresh failed: '.$e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error'=>'Unable to refresh customer balance.']);
        }
    }

    public function receipt(int $id)
    {
        if($r=$this->guard()) return $r;
        $db=\Config\Database::connect();
        $sale=$db->table('sales s')->select('s.*,c.code customer_code,c.name customer_name,c.phone customer_phone')->join('customers c','c.id=s.customer_id','left')->where('s.id',$id)->where('s.location_id',(int)session()->get('location_id'))->get()->getRowArray();
        if(!$sale) return $this->response->setStatusCode(404)->setBody('Sale not found');
        $items=$db->table('sale_items')->where('sale_id',$id)->orderBy('line_no')->get()->getResultArray();
        $payments=$db->table('sale_payments')->where('sale_id',$id)->orderBy('id')->get()->getResultArray();
        $shopSettings=(new \App\Models\ShopSettingsModel())->forLocation((int)session()->get('location_id')); $location=$db->table('locations')->where('id',(int)session()->get('location_id'))->get()->getRowArray() ?: [];
        return view('sales/receipt',['sale'=>$sale,'items'=>$items,'payments'=>$payments,'shopSettings'=>$shopSettings,'location'=>$location]);
    }

    public function historyPage()
    {
        if($r=$this->guard('POS_HISTORY')) return $r;
        return view('sales/history', [
            'title'=>'Sale History',
            'canVoidSales'=>PermissionService::allows('POS_VOID'),
        ]);
    }

    public function historyData()
    {
        if($r=$this->guard('POS_HISTORY')) return $r;
        $db=\Config\Database::connect();
        $from=trim((string)$this->request->getGet('from')) ?: date('Y-m-d');
        $to=trim((string)$this->request->getGet('to')) ?: $from;
        if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$from)) $from=date('Y-m-d');
        if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$to)) $to=$from;
        if($from>$to) [$from,$to]=[$to,$from];
        $b=$db->table('sales s')->select('s.*,c.code customer_code,c.name customer_name,u.full_name created_by_name,vu.full_name voided_by_name')->join('customers c','c.id=s.customer_id','left')->join('users u','u.id=s.created_by','left')->join('users vu','vu.id=s.voided_by','left')->where('s.location_id',(int)session()->get('location_id'))->where('s.transaction_at >=',$from.' 00:00:00')->where('s.transaction_at <=',$to.' 23:59:59')->orderBy('s.transaction_at','DESC')->orderBy('s.id','DESC');
        $status=trim((string)$this->request->getGet('status')); if(in_array($status,['posted','voided'],true)) $b->where('s.status',$status);
        $customer=trim((string)$this->request->getGet('customer')); if($customer!=='') $b->groupStart()->like('c.name',$customer)->orLike('c.code',$customer)->groupEnd();
        $type=trim((string)$this->request->getGet('transaction_type')); if($type!=='') $b->where('s.transaction_type',$type);
        $saleNo=trim((string)$this->request->getGet('sale_no')); if($saleNo!=='') $b->like('s.sale_no',$saleNo);
        return $this->response->setJSON(['from'=>$from,'to'=>$to,'sales'=>$b->get()->getResultArray()]);
    }
    public function details(int $id)
    {
        if($r=$this->guard('POS_HISTORY')) return $r;
        $db=\Config\Database::connect(); $locationId=(int)session()->get('location_id');
        $sale=$db->table('sales s')->select('s.*,c.code customer_code,c.name customer_name,c.phone customer_phone,u.full_name created_by_name,vu.full_name voided_by_name')->join('customers c','c.id=s.customer_id','left')->join('users u','u.id=s.created_by','left')->join('users vu','vu.id=s.voided_by','left')->where(['s.id'=>$id,'s.location_id'=>$locationId])->get()->getRowArray();
        if(!$sale) return $this->response->setStatusCode(404)->setJSON(['error'=>'Sale not found.']);
        $items=$db->table('sale_items si')->select('si.*,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg,cu.unit_code customer_unit_code')->join('cylinder_types ct','ct.id=si.cylinder_type_id','left')->join('cylinder_units cu','cu.id=si.customer_cylinder_unit_id','left')->where('si.sale_id',$id)->orderBy('si.line_no')->get()->getResultArray();
        $movements=$db->table('inventory_movements im')->select('im.*,cu.unit_code,ct.code cylinder_code,ct.name cylinder_name')->join('cylinder_units cu','cu.id=im.cylinder_unit_id','left')->join('cylinder_types ct','ct.id=im.cylinder_type_id','left')->where('im.source_id',$id)->whereIn('im.source_type',['sale','sale_void','security_deposit','cylinder_return'])->orderBy('im.id')->get()->getResultArray();
        $payments=$db->table('sale_payments')->where('sale_id',$id)->orderBy('id')->get()->getResultArray();
        return $this->response->setJSON(['sale'=>$sale,'items'=>$items,'payments'=>$payments,'movements'=>$movements,'can_void'=>PermissionService::allows('POS_VOID')]);
    }
    public function void(int $id)
    {
        if($r=$this->guard('POS_VOID')) return $r;
        try{
            $reason=trim((string)$this->request->getPost('void_reason'));
            $no=(new SalesService())->void($id,(int)session()->get('user_id'),(int)session()->get('location_id'),$reason);
            return redirect()->to('/sales')->with('success','Sale '.$no.' voided successfully.');
        }catch(\Throwable $e){return redirect()->back()->with('error',$e->getMessage());}
    }
}