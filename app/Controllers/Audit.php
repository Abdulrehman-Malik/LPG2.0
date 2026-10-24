<?php

namespace App\Controllers;

use App\Libraries\TenantContext;
use App\Services\PermissionService;
use CodeIgniter\Controller;
use Config\Database;

/** Audit log of the current branch, newest first, filterable by action, record type and date (BL-015). */
class Audit extends Controller
{
    public function index()
    {
        if (!PermissionService::allows('AUDIT_VIEW')) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }
        $db = Database::connect();
        $locationId = TenantContext::requireBranchId();
        $filters = [
            'action' => trim((string) $this->request->getGet('action')),
            'entity_type' => trim((string) $this->request->getGet('entity_type')),
            'from' => trim((string) $this->request->getGet('from')),
            'to' => trim((string) $this->request->getGet('to')),
        ];

        $builder = $db->table('audit_logs a')->select('a.*,u.full_name')
            ->join('users u', 'u.id=a.user_id', 'left')
            ->where('a.location_id', $locationId);
        if ($filters['action'] !== '') {
            $builder->where('a.action', $filters['action']);
        }
        if ($filters['entity_type'] !== '') {
            $builder->where('a.entity_type', $filters['entity_type']);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) {
            $builder->where('a.created_at >=', $filters['from'] . ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) {
            $builder->where('a.created_at <=', $filters['to'] . ' 23:59:59');
        }
        $rows = $builder->orderBy('a.id', 'DESC')->limit(200)->get()->getResultArray();

        $distinct = static fn (string $column): array => array_column(
            $db->table('audit_logs')->select($column)->distinct()->where('location_id', $locationId)->orderBy($column)->get()->getResultArray(),
            $column
        );

        return view('audit/index', [
            'title' => 'Audit Log',
            'rows' => $rows,
            'filters' => $filters,
            'actions' => $distinct('action'),
            'entityTypes' => $distinct('entity_type'),
        ]);
    }
}
