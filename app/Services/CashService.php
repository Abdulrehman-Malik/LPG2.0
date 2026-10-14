<?php
namespace App\Services;
use Config\Database;
use RuntimeException;
class CashService {
 protected $db;
 public function __construct(){ $this->db=Database::connect(); }
 public function openSession(int $userId,int $locationId,float $openingCash,string $notes=''): int {
  if($openingCash<0) throw new RuntimeException('Opening cash cannot be negative.');
  $this->db->transBegin();
  try {
   $register=$this->db->query("SELECT * FROM cash_registers WHERE location_id=? AND is_active=1 ORDER BY id LIMIT 1 FOR UPDATE",[$locationId])->getRowArray();
   if(!$register) throw new RuntimeException('No active cash register exists for this location.');
   if($this->db->table('cash_sessions')->where('register_id',$register['id'])->where('status','open')->get()->getRowArray()) throw new RuntimeException('An open cash session already exists for this register.');
   $this->db->table('cash_sessions')->insert(['register_id'=>$register['id'],'opened_by'=>$userId,'opening_cash'=>$openingCash,'notes'=>trim($notes)?:null]);
   $id=(int)$this->db->insertID();
   if($openingCash>0) $this->db->table('cash_transactions')->insert(['cash_session_id'=>$id,'transaction_type'=>'opening_float','direction'=>'in','amount'=>$openingCash,'created_by'=>$userId,'notes'=>'Opening float']);
   if(!$this->db->transStatus()) throw new RuntimeException('Cash session could not be opened.');
   $this->db->transCommit(); return $id;
  } catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }
 public function openSessionForLocation(int $locationId): ?array {
  return $this->db->table('cash_sessions cs')->select('cs.*,cr.code register_code,cr.name register_name')->join('cash_registers cr','cr.id=cs.register_id')->where('cr.location_id',$locationId)->where('cs.status','open')->orderBy('cs.id','DESC')->get()->getRowArray() ?: null;
 }
 public function postSaleCash(int $sessionId,int $saleId,float $amount,int $userId,string $at): void {
  if($amount<=0) return;
  $session=$this->db->query("SELECT * FROM cash_sessions WHERE id=? FOR UPDATE",[$sessionId])->getRowArray();
  if(!$session || $session['status']!=='open') throw new RuntimeException('No open cash session is available for this cash sale.');
  $this->db->table('cash_transactions')->insert(['cash_session_id'=>$sessionId,'transaction_type'=>'sale_cash','direction'=>'in','amount'=>$amount,'transaction_at'=>$at,'reference_type'=>'sale','reference_id'=>$saleId,'created_by'=>$userId]);
  if(!$this->db->transStatus()) throw new RuntimeException('Cash transaction could not be posted.');
 }
 public function postGeneric(int $sessionId,string $type,string $direction,float $amount,string $referenceType,int $referenceId,int $userId,string $notes='',?string $at=null): void {
  if($amount<=0) throw new RuntimeException('Cash amount must be positive.');
  if(!in_array($direction,['in','out'],true)) throw new RuntimeException('Invalid cash direction.');
  $session=$this->db->query("SELECT * FROM cash_sessions WHERE id=? FOR UPDATE",[$sessionId])->getRowArray();
  if(!$session || $session['status']!=='open') throw new RuntimeException('Cash session is closed or unavailable.');
  $this->db->table('cash_transactions')->insert(['cash_session_id'=>$sessionId,'transaction_type'=>$type,'direction'=>$direction,'amount'=>$amount,'transaction_at'=>$at?:date('Y-m-d H:i:s'),'reference_type'=>$referenceType,'reference_id'=>$referenceId,'created_by'=>$userId,'notes'=>$notes?:null]);
  if(!$this->db->transStatus()) throw new RuntimeException('Cash transaction could not be posted.');
 }
 public function summary(int $sessionId): array {
  $session=$this->db->table('cash_sessions')->where('id',$sessionId)->get()->getRowArray();
  if(!$session) throw new RuntimeException('Cash session not found.');
  $row=$this->db->table('cash_transactions')->select("SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) cash_in,SUM(CASE WHEN direction='out' THEN amount ELSE 0 END) cash_out")->where('cash_session_id',$sessionId)->get()->getRowArray();
  return ['session'=>$session,'cash_in'=>(float)($row['cash_in']??0),'cash_out'=>(float)($row['cash_out']??0),'expected'=>(float)($row['cash_in']??0)-(float)($row['cash_out']??0)];
 }
 public function history(int $locationId, ?string $from=null, ?string $to=null, ?int $registerId=null): array {
  $from=$from?:date('Y-m-d',strtotime('-30 days')); $to=$to?:date('Y-m-d');
  $q=$this->db->table('cash_transactions ct')->select('ct.*,cs.opened_by,cs.opened_at,cs.closed_at,cs.counted_cash,cr.code register_code,cr.name register_name')->join('cash_sessions cs','cs.id=ct.cash_session_id')->join('cash_registers cr','cr.id=cs.register_id')->where('cr.location_id',$locationId)->where('ct.transaction_at >=',$from.' 00:00:00')->where('ct.transaction_at <=',$to.' 23:59:59');
  if($registerId) $q->where('cr.id',$registerId);
  return $q->orderBy('ct.transaction_at','DESC')->orderBy('ct.id','DESC')->get()->getResultArray();
 }
 public function closeSession(int $sessionId,int $userId,float $countedCash,string $notes=''): array {
  if($countedCash<0) throw new RuntimeException('Counted cash cannot be negative.');
  $this->db->transBegin();
  try {
   $session=$this->db->query("SELECT * FROM cash_sessions WHERE id=? FOR UPDATE",[$sessionId])->getRowArray();
   if(!$session) throw new RuntimeException('Cash session not found.');
   if($session['status']!=='open') throw new RuntimeException('Cash session is already closed.');
   $summary=$this->summary($sessionId);
   $this->db->table('cash_sessions')->where('id',$sessionId)->update(['closed_by'=>$userId,'closed_at'=>date('Y-m-d H:i:s'),'counted_cash'=>$countedCash,'status'=>'closed','notes'=>trim($notes)?:$summary['session']['notes']]);
   if(!$this->db->transStatus()) throw new RuntimeException('Cash session could not be closed.');
   $this->db->transCommit(); return ['expected'=>$summary['expected'],'counted'=>$countedCash,'difference'=>$countedCash-$summary['expected']];
  } catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }
}
