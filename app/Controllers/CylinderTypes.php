<?php
namespace App\Controllers;
use App\Models\CylinderTypeModel;
use App\Services\PermissionService;
use CodeIgniter\Controller;

class CylinderTypes extends Controller
{
    protected CylinderTypeModel $model;
    public function __construct(){ $this->model=new CylinderTypeModel(); }
    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    { if(!PermissionService::allows('INVENTORY_MANAGE')) return $this->response->setStatusCode(403)->setBody('Forbidden'); return null; }

    public function index(){ if($r=$this->guard()) return $r; return view('cylinder_types/index',['title'=>'Cylinder Types','types'=>$this->model->orderBy('sort_order')->findAll()]); }

    public function save()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        $data=['code'=>trim((string)$this->request->getPost('code')),'name'=>trim((string)$this->request->getPost('name')),'capacity_kg'=>(float)$this->request->getPost('capacity_kg'),'tare_weight_kg'=>$this->request->getPost('tare_weight_kg')===''?null:(float)$this->request->getPost('tare_weight_kg'),'empty_cylinder_price'=>max(0,(float)$this->request->getPost('empty_cylinder_price')),'sort_order'=>(int)$this->request->getPost('sort_order'),'is_active'=>$this->request->getPost('is_active')?1:0];
        if($data['code']===''||$data['name']===''||$data['capacity_kg']<=0) return redirect()->back()->withInput()->with('error','Code, name and positive capacity are required.');
        try {
            if($id){ if(!$this->model->find($id)) throw new \RuntimeException('Cylinder type not found.'); $this->model->update($id,$data); }
            else $this->model->insert($data);
            return redirect()->to('/cylinder-types')->with('success',$id?'Cylinder type updated.':'Cylinder type created.');
        } catch(\Throwable $e){ return redirect()->back()->withInput()->with('error','Cylinder type could not be saved: '.$e->getMessage()); }
    }

    public function toggle()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id'); $row=$this->model->find($id);
        if(!$row) return redirect()->back()->with('error','Cylinder type not found.');
        $this->model->update($id,['is_active'=>((int)$row['is_active'])?0:1]);
        return redirect()->to('/cylinder-types')->with('success',((int)$row['is_active'])?'Cylinder type deactivated.':'Cylinder type activated.');
    }

    public function delete()
    {
        if($r=$this->guard()) return $r;
        $id=(int)$this->request->getPost('id');
        try {
            $result=$this->model->deleteIfUnused($id);
            return redirect()->to('/cylinder-types')->with('success','Cylinder type deleted.');
        } catch(\Throwable $e){ return redirect()->back()->with('error',$e->getMessage()); }
    }
}
