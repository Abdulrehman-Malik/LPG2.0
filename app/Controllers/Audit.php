<?php
namespace App\Controllers;
use CodeIgniter\Controller;
use Config\Database;
class Audit extends Controller{
 public function index(){if(!\App\Services\PermissionService::allows('AUDIT_VIEW'))return $this->response->setStatusCode(403)->setBody('Forbidden');$rows=Database::connect()->table('audit_logs a')->select('a.*,u.full_name')->join('users u','u.id=a.user_id','left')->where('a.location_id',(int)session()->get('location_id'))->orderBy('a.id','DESC')->limit(200)->get()->getResultArray();return view('audit/index',['title'=>'Audit Log','rows'=>$rows]);}
}