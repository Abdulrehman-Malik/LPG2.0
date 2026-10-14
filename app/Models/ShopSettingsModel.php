<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopSettingsModel extends Model
{
    protected $table = 'shop_settings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'location_id','default_sale_mode','default_transaction_type','default_gas_entry_mode','pos_visible_transaction_types','individual_cylinder_tracking','allow_pos_source_cylinder_selection','include_security_deposit_in_os','deposit_payment_allocation_rule','allow_return_gas_qty','return_gas_affects_os','allow_empty_issued_return_gas','allow_return_gas_over_issued','default_payment_mode','pos_font_size_px','theme_mode','font_family','primary_color','accent_color',
        'stock_validation_enabled','allow_stock_override','credit_limit_validation_mode','shop_credit_limit','purchase_void_enabled',
        'backup_enabled','db_backup_url','backup_notes',
        'smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','smtp_from_email','smtp_from_name','smtp_enabled',
        'receipt_title','receipt_footer','show_address_on_receipt',
        'settings_note'
    ];

    public const SALE_MODES = [
        'sell_gas_only',
        'replace_same',
        'sell_filled',
        'replace_different',
        'sell_empty',
    ];

    public const TRANSACTION_TYPES = ['gas_sale','cylinder_sale','security_deposit','cylinder_return'];

    public const TRANSACTION_TYPE_LABELS = [
        'gas_sale' => 'Gas Sale / Refill',
        'cylinder_sale' => 'Cylinder Sale',
        'security_deposit' => 'Security Deposit / Issue Cylinder',
        'cylinder_return' => 'Cylinder Return / Refund Deposit',
    ];

    public const PAYMENT_MODES = ['cash','cheque','online','credit'];

    public function visibleTransactionTypes(array $settings): array
    {
        $raw = $settings['pos_visible_transaction_types'] ?? null;
        $types = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($types)) {
            $types = self::TRANSACTION_TYPES;
        }
        $types = array_values(array_unique(array_intersect(array_map('strval', $types), self::TRANSACTION_TYPES)));
        return $types ?: self::TRANSACTION_TYPES;
    }

    public function isTransactionTypeVisible(array $settings, string $transactionType): bool
    {
        return in_array($transactionType, $this->visibleTransactionTypes($settings), true);
    }

    public function forLocation(int $locationId): array
    {
        $defaults = [
            'id' => 0,
            'location_id' => $locationId,
            'default_sale_mode' => 'sell_gas_only',
            'default_transaction_type' => 'gas_sale',
            'default_gas_entry_mode' => 'quantity',
            'pos_visible_transaction_types' => json_encode(self::TRANSACTION_TYPES),
            'individual_cylinder_tracking' => 0,
            'allow_pos_source_cylinder_selection' => 0,
            'include_security_deposit_in_os' => 0,
            'deposit_payment_allocation_rule' => 'deposit_first',
            'allow_return_gas_qty' => 0,
            'return_gas_affects_os' => 0,
            'allow_empty_issued_return_gas' => 0,
            'allow_return_gas_over_issued' => 0,
            'default_payment_mode' => 'cash',
            'pos_font_size_px' => 14,
            'theme_mode' => 'light',
            'font_family' => 'system',
            'primary_color' => '#1b2a3a',
            'accent_color' => '#ff7a1a',
            'stock_validation_enabled' => 1,
            'allow_stock_override' => 1,
            'credit_limit_validation_mode' => 'none',
            'shop_credit_limit' => 0,
            'purchase_void_enabled' => 0,
            'backup_enabled' => 0,
            'db_backup_url' => '',
            'backup_notes' => '',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_from_email' => '',
            'smtp_from_name' => 'Perfect LPG',
            'smtp_enabled' => 0,
            'receipt_title' => 'SALE RECEIPT',
            'receipt_footer' => 'Thank you',
            'show_address_on_receipt' => 1,
            'settings_note' => '',
        ];

        try {
            $row = $this->where('location_id', $locationId)->first();
            if (!$row) {
                $this->insert($defaults);
                $row = $this->where('location_id', $locationId)->first();
            }
            return array_merge($defaults, (array) $row);
        } catch (\Throwable $e) {
            return $defaults;
        }
    }
}