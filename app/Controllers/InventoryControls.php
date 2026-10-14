<?php
namespace App\Controllers;
use App\Services\InventoryControlService;
use CodeIgniter\Controller;
use Config\Database;

class InventoryControls extends Controller
{
 private function guard(){return \App\Services\PermissionService::allows('INVENTORY_MANAGE')?null:$this->response->setStatusCode(403)->setBody('Forbidden');}
 public function index(){
  if(!\App\Services\PermissionService::allows('INVENTORY_CONTROLS_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
  $db=Database::connect(); $loc=(int)session()->get('location_id');
  $types=$db->table('cylinder_types')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray();
  $svc=new InventoryControlService(); $policies=[];
  foreach($types as $t) $policies[(int)$t['id']]=$svc->policy($loc,(int)$t['id']);
  $policies['default']=$svc->policy($loc);
  $filterFrom=$this->request->getGet('from') ?: '';
  $filterTo=$this->request->getGet('to') ?: '';
  $filterTypeRaw=$this->request->getGet('cylinder_type_id');
  $filterType=$filterTypeRaw!==null && $filterTypeRaw!=='' ? (int)$filterTypeRaw : null;
  $units=$db->table('cylinder_units cu')->select('cu.id,cu.unit_code,cu.gas_weight_kg,ct.code,ct.name')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where(['cu.location_id'=>$loc,'cu.status'=>'filled'])->where('cu.gas_weight_kg >',0)->orderBy('ct.sort_order')->orderBy('cu.unit_code')->get()->getResultArray();
  $rows=$svc->wastage($loc,$filterFrom ?: null,$filterTo ?: null,$filterType);
  return view('inventory/controls',['title'=>'Inventory Controls & Wastage','types'=>$types,'policies'=>$policies,'units'=>$units,'rows'=>$rows,'filterFrom'=>$filterFrom,'filterTo'=>$filterTo,'filterType'=>$filterTypeRaw ?? '']);
 }
 public function save(){
  if(!\App\Services\PermissionService::allows('INVENTORY_CONTROLS_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
  try{
   $svc=new InventoryControlService(); $loc=(int)session()->get('location_id'); $type=$this->request->getPost('cylinder_type_id'); $typeId=$type!==''? (int)$type:null;
   $svc->savePolicy($loc,$typeId,(bool)$this->request->getPost('stock_validation_enabled'),(string)$this->request->getPost('wastage_mode'),(float)$this->request->getPost('wastage_percent'),(float)$this->request->getPost('wastage_fixed_kg'),(int)session()->get('user_id'));
   return redirect()->back()->with('success','Inventory control settings saved.');
  }catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
 }
 public function wastage(){
  if(!\App\Services\PermissionService::allows('GAS_WASTAGE_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
  $db=Database::connect(); $loc=(int)session()->get('location_id');
  $types=$db->table('cylinder_types')->where('is_active',1)->orderBy('sort_order')->get()->getResultArray();
  $units=$db->table('cylinder_units cu')->select('cu.id,cu.unit_code,cu.gas_weight_kg,ct.code,ct.name')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where(['cu.location_id'=>$loc,'cu.status'=>'filled'])->where('cu.gas_weight_kg >',0)->orderBy('ct.sort_order')->orderBy('cu.unit_code')->get()->getResultArray();
  $filterFrom=(string)($this->request->getGet('from') ?? ''); $filterTo=(string)($this->request->getGet('to') ?? ''); $filterType=(string)($this->request->getGet('cylinder_type_id') ?? '');
  $typeId=$filterType!==''?(int)$filterType:null;
  $svc=new InventoryControlService(); $rows=$svc->wastage($loc,$filterFrom ?: null,$filterTo ?: null,$typeId);
  return view('inventory/wastage',['title'=>'Gas Wastage Report & Adjustment','types'=>$types,'units'=>$units,'rows'=>$rows,'filterFrom'=>$filterFrom,'filterTo'=>$filterTo,'filterType'=>$filterType]);
 }
 public function recordWastage(){
  if(!\App\Services\PermissionService::allows('GAS_WASTAGE_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden');
  try{(new InventoryControlService())->recordWastage((int)session()->get('location_id'),(int)$this->request->getPost('cylinder_unit_id'),(float)$this->request->getPost('gas_weight_kg'),trim((string)$this->request->getPost('reason')),(int)session()->get('user_id'));return redirect()->to('/inventory/wastage')->with('success','Wastage recorded and cylinder converted to empty stock.');}
  catch(\Throwable $e){return redirect()->back()->withInput()->with('error',$e->getMessage());}
 }
}
