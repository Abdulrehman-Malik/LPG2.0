<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

class TransactionalDataRefreshService
{
    /**
     * Clear transactional/test data plus party, cylinder-type and rate master data,
     * while preserving application configuration and user/role setup. Destructive.
     */
    public function refresh(): array
    {
        $db = Database::connect();

        $tables = [
            'audit_logs',
            'customer_security_deposits',
            'cylinder_custody',
            'sale_payments',
            'sale_items',
            'sales',
            'purchase_payments',
            'purchase_items',
            'purchases',
            'customer_receipts',
            'supplier_payments',
            'inventory_wastage_logs',
            'inventory_adjustments',
            'inventory_movements',
            'inventory_opening_balances',
            'cylinder_units',
            'cash_transactions',
            'cash_sessions',
            'expenses',
            'daily_filled_stock',
            'daily_empty_stock',
            'daily_summaries',
            // Parties and operational master data are deliberately refreshed too.
            'rate_change_log',
            'rate_cards',
            'cylinder_types',
            'customers',
            'suppliers',
        ];

        // Preflight all core tables before making any destructive changes.
        $required = [
            'sales', 'sale_items', 'sale_payments',
            'purchases', 'purchase_items', 'purchase_payments',
            'customer_receipts', 'supplier_payments',
            'inventory_movements', 'inventory_opening_balances',
            'cylinder_units', 'cash_transactions', 'cash_sessions',
            'expenses', 'customers', 'suppliers',
        ];
        foreach ($required as $table) {
            if (!$db->tableExists($table)) {
                throw new RuntimeException('Refresh cancelled: required database table is missing: ' . $table);
            }
        }

        $cleared = [];
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                // Optional tables vary between older and newer installations.
                if (!$db->tableExists($table)) {
                    continue;
                }
                $db->query('TRUNCATE TABLE ' . $db->protectIdentifiers($table));
                $cleared[] = $table;
            }

            // Customers and suppliers are cleared as requested; no opening balances remain.
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        log_message('critical', 'Transactional data refresh completed by user ID {user_id}. Tables cleared: {tables}', [
            'user_id' => (int) session()->get('user_id'),
            'tables' => implode(', ', $cleared),
        ]);

        return ['tables_cleared' => $cleared];
    }
}
