<?php

namespace App\Database;

/**
 * Which tables carry the tenant (`business_id`) and where it comes from (BL-009 / BL-010).
 * The application's Query Builder (App\Database\MySQLi\Builder) fills business_id on insert
 * from these rules, and composite foreign keys keep it consistent with the branch / parent.
 */
final class TenantTables
{
    /** Branch-owned tables: business_id follows location_id. table => location_id nullable */
    public const BRANCH_OWNED = [
        'customers' => false, 'sales' => false, 'purchases' => false, 'customer_receipts' => false,
        'supplier_payments' => false, 'expenses' => false, 'cash_registers' => false, 'cylinder_units' => false,
        'inventory_movements' => false, 'inventory_opening_balances' => false, 'cylinder_custody' => false,
        'customer_security_deposits' => false, 'document_sequences' => false, 'shop_settings' => false,
        'inventory_policies' => false, 'inventory_wastage_logs' => false, 'shop_purchase_void_users' => false,
        'supplier_opening_balances' => false, 'shop_setting_overrides' => false,
        'rate_cards' => true, 'rate_change_log' => true, 'audit_logs' => true,
    ];

    /** Child tables: business_id follows the parent row. child => [parent column, parent table] */
    public const CHILDREN = [
        'cash_sessions' => ['register_id', 'cash_registers'],
        'sale_items' => ['sale_id', 'sales'],
        'sale_payments' => ['sale_id', 'sales'],
        'purchase_items' => ['purchase_id', 'purchases'],
        'purchase_payments' => ['purchase_id', 'purchases'],
        'stock_transfer_items' => ['transfer_id', 'stock_transfers'],
        'customer_rates' => ['customer_id', 'customers'],
        'customer_app_accounts' => ['customer_id', 'customers'],
        'cash_transactions' => ['cash_session_id', 'cash_sessions'],
    ];

    /** Columns that fix the tenant of a new row, and the table that resolves it. */
    public static function sourceFor(string $table): ?array
    {
        if (isset(self::BRANCH_OWNED[$table])) {
            return ['location_id', 'locations'];
        }
        return self::CHILDREN[$table] ?? null;
    }
}
