<?php
namespace App\Services;
use Config\Database;
class AuditService{
 public static function log(string $action,string $entityType,?int $entityId,?array $old,?array $new,int $userId,?int $locationId):void{
  try{
   Database::connect()->table('audit_logs')->insert([
    'user_id'=>$userId,'location_id'=>$locationId,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,
    'old_values'=>$old===null?null:json_encode($old),'new_values'=>$new===null?null:json_encode($new),
    'ip_address'=>service('request')->getIPAddress(),'user_agent'=>substr((string)service('request')->getUserAgent(),0,500)
   ]);
  }catch(\Throwable $e){
   log_message('error','Audit log write failed: '.$e->getMessage());
  }
 }
}