<?php

namespace App\Controllers;

use App\Models\CylinderTypeModel;
use App\Models\RateCardModel;
use App\Models\RateChangeLogModel;
use App\Services\PermissionService;
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
        $allTypes=$this->types->orderBy('sort_order')->findAll(); $activeTypes=array_values(array_filter($allTypes,static fn($t)=>(int)$t['is_active']===1)); $rateCylinderTypes=[]; foreach($allTypes as $t) $rateCylinderTypes[(string)$t['id']]=$t; return view('rates/index',['title'=>'LPG Rates','types'=>$activeTypes,'rates'=>$this->rates->history($locationId),'rateCylinderTypes'=>$rateCylinderTypes]);
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
        if(!in_array($type,['gas_per_kg','cylinder_package'],true)||$value<0||$effective==='') return redirect()->back()->withInput()->with('error','Valid rate type, value and effective date/time are required.');
        if($type==='gas_per_kg') $typeId=null;
        if($type==='cylinder_package' && !$typeId) return redirect()->back()->withInput()->with('error','Select a cylinder type for package rates.');

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
        return redirect()->to('/rates')->with('success','Rate saved and history recorded.');
    }
}