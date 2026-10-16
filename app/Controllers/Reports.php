<?php
namespace App\Controllers;
use CodeIgniter\Controller;
use Config\Database;
class Reports extends Controller{
 private function guard(){return \App\Services\PermissionService::allows('REPORT_VIEW')?null:$this->response->setStatusCode(403)->setBody('Forbidden');}
 public function index(){if($r=$this->guard())return $r;$db=Database::connect();$loc=(int)session()->get('location_id');$today=date('Y-m-d');$from=trim((string)$this->request->getGet('from'))?:$today;$to=trim((string)$this->request->getGet('to'))?:$today;if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$from))$from=$today;if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$to))$to=$today;if($from>$to)[$from,$to]=[$to,$from];$report=(new \App\Services\ReportService($db))->periodReport($loc,$from,$to);return view('reports/index',['title'=>'Reports','today'=>$today,'from'=>$from,'to'=>$to]+$report);}
 public function custody(){if($r=$this->guard())return $r;$db=Database::connect();$loc=(int)session()->get('location_id');$rows=$db->table('cylinder_custody cc')->select('cc.*,c.code customer_code,c.name customer_name,c.phone customer_phone,cu.unit_code,cu.gas_weight_kg,ct.code cylinder_code,ct.name cylinder_name')->join('customers c','c.id=cc.customer_id')->join('cylinder_units cu','cu.id=cc.cylinder_unit_id')->join('cylinder_types ct','ct.id=cu.cylinder_type_id')->where(['cc.location_id'=>$loc,'cc.status'=>'issued'])->orderBy('c.name')->orderBy('cu.unit_code')->get()->getResultArray();$totals=$db->table('customer_security_deposits')->select("SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END) balance")->where(['location_id'=>$loc])->get()->getRowArray();return view('reports/custody',['title'=>'Cylinder Custody & Security Deposits','rows'=>$rows,'depositBalance'=>(float)($totals['balance']??0)]);}
 public function inventoryDetail(){if($r=$this->guard())return $r;$db=Database::connect();$loc=(int)session()->get('location_id');$types=$db->table('cylinder_types')->where('business_id',\App\Services\BusinessContext::businessIdForLocation($loc))->where('is_active',1)->orderBy('sort_order')->get()->getResultArray();$summary=[];$partials=[];$companyTotalGas=0.0;$companyFilled=0;$companyEmpty=0;foreach($types as $t){$typeId=(int)$t['id'];$filled=$db->table('cylinder_units')->where(['location_id'=>$loc,'cylinder_type_id'=>$typeId,'status'=>'filled'])->get()->getResultArray();$empty=(int)$db->table('cylinder_units')->where(['location_id'=>$loc,'cylinder_type_id'=>$typeId,'status'=>'empty'])->countAllResults();$gas=array_sum(array_map(static fn($u)=>(float)$u['gas_weight_kg'],$filled));$summary[]=['id'=>$typeId,'code'=>$t['code'],'name'=>$t['name'],'capacity_kg'=>(float)$t['capacity_kg'],'filled_cylinders'=>count($filled),'empty_cylinders'=>$empty,'filled_gas_kg'=>$gas,'partial_cylinders'=>0];$companyFilled+=count($filled);$companyEmpty+=$empty;$companyTotalGas+=$gas;foreach($filled as $u){$remaining=(float)$u['gas_weight_kg'];$capacity=(float)$t['capacity_kg'];if($remaining>0.00001&&$remaining<$capacity-0.00001){$partials[]=['unit_code'=>$u['unit_code'],'type_code'=>$t['code'],'type_name'=>$t['name'],'capacity_kg'=>$capacity,'gas_weight_kg'=>$remaining,'used_kg'=>max(0,$capacity-$remaining),'used_percent'=>$capacity>0?max(0,min(100,(($capacity-$remaining)/$capacity)*100)):0,'updated_at'=>$u['updated_at']??$u['created_at']];$summary[array_key_last($summary)]['partial_cylinders']++;}}}usort($partials,static fn($a,$b)=>strcmp($a['type_code'].'|'.$a['unit_code'],$b['type_code'].'|'.$b['unit_code']));return view('reports/inventory_detail',['title'=>'Inventory Detail Report','summary'=>$summary,'partials'=>$partials,'companyTotalGas'=>$companyTotalGas,'companyFilled'=>$companyFilled,'companyEmpty'=>$companyEmpty]);}
 public function stock(){if($r=$this->guard())return $r;return redirect()->to('/inventory');}
 public function ledger(int $customerId){if($r=$this->guard())return $r;$db=Database::connect();$loc=(int)session()->get('location_id');$c=$db->table('customers')->where('id',$customerId)->where('location_id',$loc)->get()->getRowArray();if(!$c)return $this->response->setStatusCode(404)->setBody('Customer not found');$sales=$db->table('sales')->where(['customer_id'=>$customerId,'status'=>'posted'])->where('location_id',$loc)->orderBy('transaction_at')->get()->getResultArray();$receipts=$db->table('customer_receipts')->where(['customer_id'=>$customerId,'status'=>'posted'])->where('location_id',$loc)->orderBy('receipt_at')->get()->getResultArray();$events=[];foreach($sales as $s){$events[]=['date'=>$s['transaction_at'],'type'=>'Sale OS','reference'=>$s['sale_no'],'debit'=>(float)$s['credit_amount'],'credit'=>0.0];}foreach($receipts as $rr){$events[]=['date'=>$rr['receipt_at'],'type'=>'Receipt / OS Settlement','reference'=>$rr['receipt_no'],'debit'=>0.0,'credit'=>(float)$rr['amount']];}usort($events,static fn($a,$b)=>strcmp($a['date'].'|'.$a['reference'],$b['date'].'|'.$b['reference']));$running=(float)$c['opening_balance'];foreach($events as &$e){$running+=(float)$e['debit']-(float)$e['credit'];$e['running_balance']=$running;}unset($e);$settings=(new \App\Models\ShopSettingsModel())->forLocation($loc);$mode=$settings['credit_limit_validation_mode']??'none';$limit=$mode==='shop'?(float)($settings['shop_credit_limit']??0):(float)$c['credit_limit'];return view('reports/ledger',['title'=>'Customer Ledger','customer'=>$c,'events'=>$events,'openingBalance'=>(float)$c['opening_balance'],'creditLimitMode'=>$mode,'effectiveCreditLimit'=>$limit,'currentBalance'=>$running]);}

 /**
  * Head Office branch overview (Phase 9.2): one business at a time, only the branches the
  * user may access, with an optional branch filter and CSV export.
  */
 public function branches()
 {
  if($r=$this->guard()) return $r;
  $scope=new \App\Services\ShopScopeService();
  $db=Database::connect();
  $accessibleShopIds=$scope->accessibleShopIds();
  if(!$accessibleShopIds) return $this->response->setStatusCode(403)->setBody('Forbidden');

  $shops=$db->table('locations l')->select('l.id,l.code,l.name,l.business_id,b.name business_name')
   ->join('businesses b','b.id=l.business_id')->whereIn('l.id',$accessibleShopIds)->where('l.is_active',1)
   ->orderBy('b.name')->orderBy('l.name')->get()->getResultArray();
  $businesses=[];
  foreach($shops as $shop) $businesses[(int)$shop['business_id']]=$shop['business_name'];

  $currentBusinessId=\App\Services\BusinessContext::currentBusinessId();
  $businessId=(int)$this->request->getGet('business_id');
  if(!isset($businesses[$businessId])) $businessId=isset($businesses[$currentBusinessId])?$currentBusinessId:(int)array_key_first($businesses);
  $businessShops=array_values(array_filter($shops,static fn($s)=>(int)$s['business_id']===$businessId));
  $businessShopIds=array_map(static fn($s)=>(int)$s['id'],$businessShops);

  $requested=array_map('intval',(array)$this->request->getGet('branch_ids'));
  $selected=array_values(array_intersect($businessShopIds,$requested)) ?: $businessShopIds;

  $today=date('Y-m-d');
  $from=trim((string)$this->request->getGet('from')) ?: date('Y-m-01');
  $to=trim((string)$this->request->getGet('to')) ?: $today;
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from=date('Y-m-01');
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) $to=$today;
  if($from>$to) [$from,$to]=[$to,$from];

  $report=(new \App\Services\ReportService())->branchSummary($selected,$from,$to);
  if($this->request->getGet('export')==='csv') return $this->branchesCsv($report,$businesses[$businessId],$from,$to);

  return view('reports/branches',[
   'title'=>'Branch Overview','report'=>$report,'businesses'=>$businesses,'businessId'=>$businessId,
   'businessShops'=>$businessShops,'selected'=>$selected,'from'=>$from,'to'=>$to,
  ]);
 }

 private function branchesCsv(array $report,string $businessName,string $from,string $to)
 {
  $columns=['code'=>'Branch code','name'=>'Branch','sale_count'=>'Sales','sales_total'=>'Sales total','gas_kg_sold'=>'Gas KG sold','credit_created'=>'Credit created','discounts'=>'Discounts','voided_count'=>'Voided sales'];
  foreach($report['payment_modes'] as $mode) $columns['pay_'.$mode]='Paid '.$mode;
  $columns+=['customer_receipts'=>'Customer receipts','purchases_total'=>'Purchases','supplier_payments'=>'Supplier payments','expenses'=>'Expenses','deposits_received'=>'Deposits received','deposits_refunded'=>'Deposits refunded','gas_stock_kg'=>'Gas stock KG','filled_cylinders'=>'Filled cylinders','empty_cylinders'=>'Empty cylinders','custody_cylinders'=>'On customer custody','outgoing_in_transit'=>'Sent (in transit)','incoming_in_transit'=>'Incoming (in transit)','customer_os'=>'Customer OS','customers_with_os'=>'Customers with OS','deposit_balance'=>'Deposit balance','cash_expected'=>'Open cash expected'];
  $out=fopen('php://temp','r+');
  fputcsv($out,['Branch overview',$businessName,$from,$to]);
  fputcsv($out,array_values($columns));
  $rows=$report['branches'];$rows[]=['code'=>'','name'=>'TOTAL']+$report['totals'];
  foreach($rows as $row){
   $line=[];
   foreach(array_keys($columns) as $key){
    $line[]=str_starts_with($key,'pay_')?round((float)($row['payments'][substr($key,4)]??0),2):(is_float($row[$key]??null)?round($row[$key],2):($row[$key]??''));
   }
   fputcsv($out,$line);
  }
  rewind($out);$csv=stream_get_contents($out);fclose($out);
  return $this->response->setHeader('Content-Type','text/csv; charset=utf-8')
   ->setHeader('Content-Disposition','attachment; filename="branch-overview-'.$from.'-to-'.$to.'.csv"')->setBody($csv);
 }
}
