<?php

namespace App\Controllers;

use App\Models\ShopSettingsModel;
use App\Services\InventoryControlService;
use App\Services\PermissionService;
use CodeIgniter\Controller;
use Config\Database;

class ShopSettings extends Controller
{
    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        $allowed = PermissionService::allows('USER_MANAGE') || PermissionService::allows('INVENTORY_MANAGE');
        return $allowed ? null : $this->response->setStatusCode(403)->setBody('Forbidden');
    }

    public function index()
    {
        if ($r = $this->guard()) return $r;

        $db = Database::connect();
        $locationId = (int) session()->get('location_id');
        $settings = (new ShopSettingsModel())->forLocation($locationId);
        $location = $db->table('locations')->where('id', $locationId)->get()->getRowArray() ?: [];
        $assignableUsers = $db->table('users')
            ->select('id, full_name, username')
            ->where('is_active', 1)
            ->groupStart()
                ->where('location_id', $locationId)
                ->orWhere('location_id IS NULL', null, false)
            ->groupEnd()
            ->orderBy('full_name', 'ASC')
            ->get()->getResultArray();
        $assignedPurchaseVoidUsers = array_map('intval', array_column(
            $db->table('shop_purchase_void_users')->select('user_id')->where('location_id', $locationId)->get()->getResultArray(),
            'user_id'
        ));

        return view('shop-settings/index', [
            'title' => 'Shop Settings',
            'settings' => $settings,
            'location' => $location,
            'assignableUsers' => $assignableUsers,
            'assignedPurchaseVoidUsers' => $assignedPurchaseVoidUsers,
        ]);
    }

    public function save()
    {
        if ($r = $this->guard()) return $r;

        $locationId = (int) session()->get('location_id');
        $saleMode = trim((string) $this->request->getPost('default_sale_mode'));
        $transactionType = trim((string) $this->request->getPost('default_transaction_type'));
        $gasEntryMode = trim((string) $this->request->getPost('default_gas_entry_mode'));
        $paymentMode = trim((string) $this->request->getPost('default_payment_mode'));
        $visibleTransactionTypes = array_values(array_unique(array_intersect(
            array_map('strval', (array) $this->request->getPost('pos_visible_transaction_types')),
            ShopSettingsModel::TRANSACTION_TYPES
        )));
        if (!$visibleTransactionTypes) {
            return redirect()->back()->withInput()->with('error', 'Select at least one POS transaction type.');
        }
        $individualCylinderTracking = $this->request->getPost('individual_cylinder_tracking') ? 1 : 0;
        $allowPosSourceCylinderSelection = $this->request->getPost('allow_pos_source_cylinder_selection') ? 1 : 0;
        if (!$individualCylinderTracking && $allowPosSourceCylinderSelection) {
            return redirect()->back()->withInput()->with('error', 'Source Filled Cylinder Selection on POS can only be enabled when Individual Cylinder Tracking is enabled.');
        }
        $posFontSize = (float) $this->request->getPost('pos_font_size_px');
        if ($posFontSize < 10 || $posFontSize > 24) {
            return redirect()->back()->withInput()->with('error', 'Application font size must be between 10 and 24 pixels.');
        }
        $themeMode = trim((string) $this->request->getPost('theme_mode'));
        $fontFamily = trim((string) $this->request->getPost('font_family'));
        $primaryColor = trim((string) $this->request->getPost('primary_color'));
        $accentColor = trim((string) $this->request->getPost('accent_color'));
        if (!in_array($themeMode, ['light','dark'], true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid theme mode.');
        }
        if (!in_array($fontFamily, ['system','arial','verdana','tahoma','trebuchet','georgia','times'], true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid font style.');
        }
        foreach (['primaryColor'=>$primaryColor,'accentColor'=>$accentColor] as $label=>$color) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                return redirect()->back()->withInput()->with('error', 'Invalid '.$label.' color.');
            }
        }

        if (!in_array($saleMode, ShopSettingsModel::SALE_MODES, true)) {
            $saleMode = 'sell_gas_only';
        }
        if (!in_array($gasEntryMode, ['quantity','amount','cylinders'], true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid default gas entry mode.');
        }
        if (!in_array($transactionType, ShopSettingsModel::TRANSACTION_TYPES, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid default POS transaction type.');
        }
        if (!in_array($transactionType, $visibleTransactionTypes, true)) {
            $transactionType = $visibleTransactionTypes[0];
        }
        if (!in_array($paymentMode, ShopSettingsModel::PAYMENT_MODES, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid default payment mode.');
        }
        $creditMode = trim((string) $this->request->getPost('credit_limit_validation_mode'));
        $shopCreditLimit = (float) $this->request->getPost('shop_credit_limit');
        $purchaseVoidEnabled = $this->request->getPost('purchase_void_enabled') ? 1 : 0;
        $purchaseVoidUserIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->request->getPost('purchase_void_user_ids')),
            static fn(int $id): bool => $id > 0
        )));
        if (!in_array($creditMode, ['none','customer','shop'], true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid credit limit validation mode.');
        }
        if ($shopCreditLimit < 0) {
            return redirect()->back()->withInput()->with('error', 'Shop credit limit cannot be negative.');
        }
        if ($purchaseVoidEnabled && !$purchaseVoidUserIds) {
            return redirect()->back()->withInput()->with('error', 'Select at least one user allowed to void purchases, or disable Purchase Void.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $db->table('locations')->where('id', $locationId)->update([
                'name' => trim((string) $this->request->getPost('shop_name')),
                'address' => trim((string) $this->request->getPost('shop_address')) ?: null,
                'city' => trim((string) $this->request->getPost('shop_city')) ?: null,
                'phone' => trim((string) $this->request->getPost('shop_phone')) ?: null,
            ]);

            $model = new ShopSettingsModel();
            $settings = $model->forLocation($locationId);
            $data = [
                'location_id' => $locationId,
                'default_sale_mode' => $saleMode,
                'default_transaction_type' => $transactionType,
                'default_gas_entry_mode' => $gasEntryMode,
                'pos_visible_transaction_types' => json_encode($visibleTransactionTypes, JSON_UNESCAPED_UNICODE),
                'individual_cylinder_tracking' => $individualCylinderTracking,
                'allow_pos_source_cylinder_selection' => $allowPosSourceCylinderSelection,
                'include_security_deposit_in_os' => $this->request->getPost('include_security_deposit_in_os') ? 1 : 0,
                'deposit_payment_allocation_rule' => in_array((string)$this->request->getPost('deposit_payment_allocation_rule'), ['gas_first','deposit_first','manual'], true) ? (string)$this->request->getPost('deposit_payment_allocation_rule') : 'deposit_first',
                'deposit_payment_allocation_rule' => in_array((string)$this->request->getPost('deposit_payment_allocation_rule'), ['gas_first','deposit_first','manual'], true) ? (string)$this->request->getPost('deposit_payment_allocation_rule') : 'gas_first',
                'allow_return_gas_qty' => $this->request->getPost('allow_return_gas_qty') ? 1 : 0,
                'return_gas_affects_os' => $this->request->getPost('return_gas_affects_os') ? 1 : 0,
                'allow_empty_issued_return_gas' => $this->request->getPost('allow_empty_issued_return_gas') ? 1 : 0,
                'allow_return_gas_over_issued' => $this->request->getPost('allow_return_gas_over_issued') ? 1 : 0,
                'default_payment_mode' => $paymentMode,
                'pos_font_size_px' => $posFontSize,
                'theme_mode' => $themeMode,
                'font_family' => $fontFamily,
                'primary_color' => $primaryColor,
                'accent_color' => $accentColor,
                'stock_validation_enabled' => $this->request->getPost('stock_validation_enabled') ? 1 : 0,
                'allow_stock_override' => $this->request->getPost('allow_stock_override') ? 1 : 0,
                'credit_limit_validation_mode' => $creditMode,
                'shop_credit_limit' => $shopCreditLimit,
                'purchase_void_enabled' => $purchaseVoidEnabled,
                'backup_enabled' => $this->request->getPost('backup_enabled') ? 1 : 0,
                'db_backup_url' => trim((string) $this->request->getPost('db_backup_url')) ?: null,
                'backup_notes' => trim((string) $this->request->getPost('backup_notes')) ?: null,
                'receipt_title' => trim((string) $this->request->getPost('receipt_title')) ?: 'SALE RECEIPT',
                'receipt_footer' => trim((string) $this->request->getPost('receipt_footer')) ?: 'Thank you',
                'show_address_on_receipt' => $this->request->getPost('show_address_on_receipt') ? 1 : 0,
                'settings_note' => trim((string) $this->request->getPost('settings_note')) ?: null,
            ];

            if ((int) ($settings['id'] ?? 0) > 0) {
                $model->update((int) $settings['id'], $data);
            } else {
                $model->insert($data);
            }

            $assignableIds = array_map('intval', array_column(
                $db->table('users')
                    ->select('id')
                    ->where('is_active', 1)
                    ->groupStart()
                        ->where('location_id', $locationId)
                        ->orWhere('location_id IS NULL', null, false)
                    ->groupEnd()
                    ->get()->getResultArray(),
                'id'
            ));
            $purchaseVoidUserIds = array_values(array_intersect($purchaseVoidUserIds, $assignableIds));

            $db->table('shop_purchase_void_users')->where('location_id', $locationId)->delete();
            foreach ($purchaseVoidUserIds as $voidUserId) {
                $db->table('shop_purchase_void_users')->insert([
                    'location_id' => $locationId,
                    'user_id' => $voidUserId,
                ]);
            }

            $inventory = new InventoryControlService();
            $defaultPolicy = $inventory->policy($locationId);
            $inventory->savePolicy(
                $locationId,
                null,
                (bool) $data['stock_validation_enabled'],
                (string) $defaultPolicy['wastage_mode'],
                (float) $defaultPolicy['wastage_percent'],
                (float) $defaultPolicy['wastage_fixed_kg'],
                (int) session()->get('user_id')
            );

            $db->table('audit_logs')->insert([
                'user_id'=>(int)session()->get('user_id'),
                'location_id'=>$locationId,
                'action'=>'settings_update',
                'entity_type'=>'shop_settings',
                'entity_id'=>(int)($settings['id'] ?? 0),
                'old_values'=>json_encode($settings, JSON_UNESCAPED_UNICODE),
                'new_values'=>json_encode($data, JSON_UNESCAPED_UNICODE),
                'ip_address'=>$this->request->getIPAddress(),
                'user_agent'=>substr((string)$this->request->getUserAgent(),0,500)
            ]);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Shop settings could not be saved.');
            }

            $db->transCommit();
            return redirect()->to('/shop-settings')->with('success', 'Shop settings saved successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}