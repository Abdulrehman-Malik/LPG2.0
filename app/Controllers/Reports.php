<?php
namespace App\Controllers;
use CodeIgniter\Controller;
use Config\Database;
class Reports extends Controller{
 private function guard(){return \App\Services\PermissionService::allows('REPORT_VIEW')?null:$this->response->setStatusCode(403)->setBody('Forbidden');}
 public function index(){if($r=$this->guard())return $r;$db=Database::connect();$loc=(int)session()->get('location_id');$today=date('Y-m-d');$from=trim((string)$this->request->getGet('from'))?:$today;$to=trim((string)$this->request->getGet('to'))?:$today;if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$from))$from=$today;if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$to))$to=$today;if($from>$to)[$from,$to]=[$to,$from];$report=(new \App\Services\ReportService($db))->periodReport($loc,$from,$to);return view('reports/index',['title'=>'Reports','today'=>$today,'from'=>$from,'to'=>$to]+$report);}
 public function custody()
 {
  if($r=$this->guard()) return $r;
  return view('reports/custody',['title'=>'Cylinder Custody & Security Deposits']+(new \App\Services\BranchReportService())->custody(\App\Libraries\TenantContext::requireBranchId()));
 }
 public function inventoryDetail()
 {
  if($r=$this->guard()) return $r;
  return view('reports/inventory_detail',['title'=>'Inventory Detail Report']+(new \App\Services\BranchReportService())->inventoryDetail(\App\Libraries\TenantContext::requireBranchId()));
 }
 public function stock(){if($r=$this->guard())return $r;return redirect()->to('/inventory');}
 public function ledger(int $customerId)
 {
  if($r=$this->guard()) return $r;
  $ledger=(new \App\Services\BranchReportService())->customerOsLedger(\App\Libraries\TenantContext::requireBranchId(),$customerId);
  if(!$ledger) return $this->response->setStatusCode(404)->setBody('Customer not found');
  return view('reports/ledger',['title'=>'Customer Ledger']+$ledger);
 }

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
