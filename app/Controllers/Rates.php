<?php

namespace App\Controllers;

use App\Models\CylinderTypeModel;
use App\Models\RateCardModel;
use App\Models\RateChangeLogModel;
use App\Services\PermissionService;
use App\Services\RateService;
use CodeIgniter\Controller;
use Config\Database;

class Rates extends Controller
{
    protected RateCardModel $rates;
    protected CylinderTypeModel $types;
    public function __construct(){ $this->rates=new RateCardModel(); $this->types=new CylinderTypeModel(); }

    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if(!PermissionService::allows('RATE_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
        return null;
    }

    public function index()
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)(session()->get('location_id') ?? 0);
        $allTypes=$this->types->forBusiness();
        $activeTypes=array_values(array_filter($allTypes,static fn($t)=>(int)$t['is_active']===1));
        $rateCylinderTypes=[]; foreach($allTypes as $t) $rateCylinderTypes[(string)$t['id']]=$t;
        $service=new RateService();
        $cards=new \App\Models\GasRateModel();
        // What the POS charges a walk-in customer now, per cylinder type (customer rates override).
        $current=[];
        foreach($activeTypes as $t){
            $filled=$service->filledCylinderRate($locationId,null,$t);
            $configured=$cards->currentFilledCylinderRate((int)$t['id'],null,$locationId);
            $current[]=['type'=>$t,'filled'=>$filled['rate'],'filled_from_kg'=>!($configured!==null && $configured>0) || $service->pricingMode($locationId)==='kg_rate','empty'=>$service->emptyCylinderPrice($locationId,null,$t)];
        }
        $customers=Database::connect()->table('customers')->select('id,code,name')->where(['location_id'=>$locationId,'is_active'=>1])->orderBy('name')->get()->getResultArray();
        return view('rates/index',[
            'title'=>'LPG Rates','types'=>$activeTypes,'rates'=>$this->rates->history($locationId),'rateCylinderTypes'=>$rateCylinderTypes,
            'currentGasRate'=>$cards->currentKgRate(null,$locationId),'current'=>$current,'pricingMode'=>$service->pricingMode($locationId),
            'customers'=>$customers,'customerRates'=>$service->branchCustomerRates($locationId),
            'tab'=>$this->request->getGet('tab')==='customers'?'customers':'branch',
        ]);
    }

    public function customerSave()
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)(session()->get('location_id') ?? 0);
        $type=(string)$this->request->getPost('rate_type');
        $typeId=$this->request->getPost('cylinder_type_id')===''?null:(int)$this->request->getPost('cylinder_type_id');
        $effective=str_replace('T',' ',trim((string)$this->request->getPost('effective_from'))) ?: date('Y-m-d H:i:s');
        if(strlen($effective)===16) $effective.=':00';
        try{
            (new RateService())->setCustomerRate($locationId,(int)$this->request->getPost('customer_id'),$type,$typeId,(float)$this->request->getPost('rate_value'),$effective,trim((string)$this->request->getPost('notes')),(int)session()->get('user_id'));
        }catch(\Throwable $e){
            return redirect()->to('/rates?tab=customers')->withInput()->with('error',user_error_message($e));
        }
        return redirect()->to('/rates?tab=customers')->with('success','Customer rate saved.');
    }

    public function customerEnd(int $id)
    {
        if($r=$this->guard()) return $r;
        try{
            (new RateService())->endCustomerRate((int)(session()->get('location_id') ?? 0),$id,(int)session()->get('user_id'));
        }catch(\Throwable $e){
            return redirect()->to('/rates?tab=customers')->with('error',user_error_message($e));
        }
        return redirect()->to('/rates?tab=customers')->with('success','Customer rate ended. The customer now gets the standard rate.');
    }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $locationId=(int)(session()->get('location_id') ?? 0);
        $type=$this->request->getPost('rate_type');
        $typeId=$this->request->getPost('cylinder_type_id')===''?null:(int)$this->request->getPost('cylinder_type_id');
        $value=(float)$this->request->getPost('rate_value');
        $effective=str_replace('T',' ',trim((string)$this->request->getPost('effective_from')));
        $reason=trim((string)$this->request->getPost('reason')) ?: null;
        if(!in_array($type,['gas_per_kg','cylinder_package','filled_cylinder'],true)||$value<0||$effective==='') return redirect()->back()->withInput()->with('error','Valid rate type, value and effective date/time are required.');
        if($type==='gas_per_kg') $typeId=null;
        if($type!=='gas_per_kg' && !$typeId) return redirect()->back()->withInput()->with('error','Select a cylinder type for this rate.');
        if($typeId && !\App\Services\BusinessContext::cylinderType($typeId,$locationId)) return redirect()->back()->withInput()->with('error','Cylinder type not found for this business.');

        $db=Database::connect();
        $db->transStart();
        $current=$this->rates->where('location_id',$locationId)->where('rate_type',$type)->where('cylinder_type_id',$typeId)->where('effective_from <=',date('Y-m-d H:i:s'))->orderBy('effective_from','DESC')->first();
        $row=[
            'location_id'=>$locationId,'rate_type'=>$type,'cylinder_type_id'=>$typeId,'rate_value'=>$value,
            'effective_from'=>$effective,'created_by'=>(int)session()->get('user_id'),'created_at'=>date('Y-m-d H:i:s')
        ];
        $rateId=$this->rates->insert($row,true);
        (new RateChangeLogModel())->insert([
            'rate_card_id'=>$rateId,'location_id'=>$locationId,'rate_type'=>$type,'cylinder_type_id'=>$typeId,
            'old_rate'=>$current['rate_value'] ?? null,'new_rate'=>$value,'changed_by'=>(int)session()->get('user_id'),
            'reason'=>$reason
        ]);
        $db->transComplete();
        \App\Services\AuditService::log('RATE_CHANGED','rate_card',(int)$rateId,
            $current?['rate_value'=>(float)$current['rate_value'],'effective_from'=>$current['effective_from']]:null,
            ['rate_type'=>$type,'cylinder_type_id'=>$typeId,'rate_value'=>$value,'effective_from'=>$effective,'reason'=>$reason],
            (int)session()->get('user_id'),$locationId);
        return redirect()->to('/rates')->with('success','Rate saved and history recorded.');
    }
}