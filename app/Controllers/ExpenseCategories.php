<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BusinessContext;
use App\Services\PermissionService;
use CodeIgniter\Controller;
use Config\Database;

/**
 * Expense categories belong to a business (D9-1) and are shared by its branches.
 * Categories are never deleted, so posted expenses always keep their category;
 * an unused category is deactivated instead.
 */
class ExpenseCategories extends Controller
{
    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        return PermissionService::allows('EXPENSE_MANAGE') ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;
        $businessId = BusinessContext::currentBusinessId();
        $categories = Database::connect()->table('expense_categories c')
            ->select('c.*, COUNT(e.id) expense_count, COALESCE(SUM(e.amount),0) expense_total')
            ->join('expenses e', 'e.category_id=c.id', 'left')
            ->where('c.business_id', $businessId)
            ->groupBy('c.id')->orderBy('c.is_active', 'DESC')->orderBy('c.name')
            ->get()->getResultArray();
        $editId = (int) $this->request->getGet('edit');
        $edit = null;
        foreach ($categories as $category) {
            if ((int) $category['id'] === $editId) $edit = $category;
        }
        return view('expenses/categories', ['title' => 'Expense Categories', 'categories' => $categories, 'edit' => $edit]);
    }

    public function save()
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $businessId = BusinessContext::currentBusinessId();
        $id = (int) $this->request->getPost('id');
        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $name = trim((string) $this->request->getPost('name'));
        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $code) || $name === '' || mb_strlen($name) > 80) {
            return redirect()->back()->withInput()->with('error', 'Enter a code (2–30 letters, numbers, - or _) and a name up to 80 characters.');
        }
        $duplicate = $db->table('expense_categories')->where(['business_id' => $businessId, 'code' => $code])->where('id !=', $id)->countAllResults();
        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Another expense category of this business already uses this code.');
        }
        if ($id) {
            $existing = $db->table('expense_categories')->where(['id' => $id, 'business_id' => $businessId])->get()->getRowArray();
            if (!$existing) return redirect()->back()->with('error', 'Expense category not found.');
            $db->table('expense_categories')->where('id', $id)->update(['code' => $code, 'name' => $name]);
            AuditService::log('UPDATE', 'expense_category', $id, ['code' => $existing['code'], 'name' => $existing['name']], ['code' => $code, 'name' => $name], (int) session()->get('user_id'), (int) session()->get('location_id'));
        } else {
            $db->table('expense_categories')->insert(['business_id' => $businessId, 'code' => $code, 'name' => $name, 'is_active' => 1]);
            AuditService::log('CREATE', 'expense_category', (int) $db->insertID(), null, ['code' => $code, 'name' => $name], (int) session()->get('user_id'), (int) session()->get('location_id'));
        }
        return redirect()->to(site_url('expenses/categories'))->with('success', $id ? 'Expense category updated.' : 'Expense category created.');
    }

    public function toggle(int $id)
    {
        if ($r = $this->guard()) return $r;
        $db = Database::connect();
        $row = $db->table('expense_categories')->where(['id' => $id, 'business_id' => BusinessContext::currentBusinessId()])->get()->getRowArray();
        if (!$row) return redirect()->back()->with('error', 'Expense category not found.');
        $active = (int) $row['is_active'] === 1;
        if ($active && $db->table('expense_categories')->where(['business_id' => $row['business_id'], 'is_active' => 1])->countAllResults() <= 1) {
            return redirect()->back()->with('error', 'Keep at least one active expense category.');
        }
        $db->table('expense_categories')->where('id', $id)->update(['is_active' => $active ? 0 : 1]);
        return redirect()->to(site_url('expenses/categories'))->with('success', 'Expense category ' . ($active ? 'deactivated.' : 'activated.'));
    }
}
