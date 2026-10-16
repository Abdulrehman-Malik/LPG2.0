<?php

namespace App\Libraries;

/**
 * Back-office sidebar menu. Items are shown only when the signed-in user holds the item's
 * permission (or any of them, when a list is given). Only the most specific item matching the
 * current path is marked active, so "expenses/categories" does not also highlight "expenses".
 */
final class Navigation
{
    public const DASHBOARD = ['permission' => 'DASHBOARD_VIEW', 'url' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'];

    public const GROUPS = [
        'sales' => ['label' => 'Sales & Receipts', 'icon' => 'bi-cart-check', 'items' => [
            ['permission' => 'POS_SALE', 'url' => 'sales', 'label' => 'POS Sales', 'icon' => 'bi-cart-plus'],
            ['permission' => 'POS_HISTORY', 'url' => 'sales/history', 'label' => 'Sale History', 'icon' => 'bi-clock-history'],
            ['permission' => 'POS_SALE', 'url' => 'receipts', 'label' => 'Customer Receipts', 'icon' => 'bi-wallet2'],
        ]],
        'purchases' => ['label' => 'Purchasing', 'icon' => 'bi-bag-check', 'items' => [
            ['permission' => 'PURCHASE_MANAGE', 'url' => 'purchases', 'label' => 'Purchases', 'icon' => 'bi-bag-plus'],
            ['permission' => 'PURCHASE_MANAGE', 'url' => 'supplier-payments', 'label' => 'Supplier Payments', 'icon' => 'bi-credit-card'],
        ]],
        'inventory' => ['label' => 'Inventory', 'icon' => 'bi-boxes', 'items' => [
            ['permission' => 'INVENTORY_MANAGE', 'url' => 'inventory', 'label' => 'Inventory', 'icon' => 'bi-boxes'],
            ['permission' => 'INVENTORY_MANAGE', 'url' => 'inventory/opening', 'label' => 'Opening Inventory', 'icon' => 'bi-archive'],
            ['permission' => 'INVENTORY_MANAGE', 'url' => 'cylinder-types', 'label' => 'Cylinder Types', 'icon' => 'bi-box'],
            ['permission' => 'INVENTORY_MANAGE', 'url' => 'inventory/adjustments', 'label' => 'Stock Adjustment & History', 'icon' => 'bi-sliders2-vertical'],
            ['permission' => 'INVENTORY_CONTROLS_MANAGE', 'url' => 'inventory/controls', 'label' => 'Inventory Controls', 'icon' => 'bi-sliders'],
            ['permission' => 'GAS_WASTAGE_MANAGE', 'url' => 'inventory/wastage', 'label' => 'Gas Wastage', 'icon' => 'bi-droplet-half'],
            ['permission' => 'STOCK_TRANSFER_MANAGE', 'url' => 'inventory/transfers', 'label' => 'Branch Stock Transfers', 'icon' => 'bi-arrow-left-right'],
        ]],
        'parties' => ['label' => 'Parties & Ledgers', 'icon' => 'bi-people', 'items' => [
            ['permission' => 'CUSTOMER_MANAGE', 'url' => 'customers', 'label' => 'Customers / Parties', 'icon' => 'bi-people'],
            ['permission' => 'SUPPLIER_MANAGE', 'url' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'bi-truck'],
        ]],
        'mobile_operations' => ['label' => 'Mobile Ordering Setup', 'icon' => 'bi-phone', 'items' => [
            ['permission' => 'CUSTOMER_ACCOUNT_MANAGE', 'url' => 'customer-app-accounts', 'label' => 'Customer App Accounts', 'icon' => 'bi-person-check'],
            ['permission' => ['BUSINESS_CATALOG_VIEW', 'BUSINESS_CATALOG_MANAGE', 'SHOP_SETTINGS_VIEW', 'SHOP_SETTINGS_MANAGE'], 'url' => 'mobile-catalog', 'label' => 'Customer Ordering Catalog', 'icon' => 'bi-basket2'],
        ]],
        'ordering' => ['label' => 'Customer Ordering', 'icon' => 'bi-basket2', 'items' => [
            ['permission' => ['ORDER_VIEW', 'ORDER_MANAGE'], 'url' => 'customer-orders', 'label' => 'Customer Orders', 'icon' => 'bi-basket2'],
        ]],
        'cash' => ['label' => 'Cash & Expenses', 'icon' => 'bi-cash-coin', 'items' => [
            ['permission' => 'CASH_MANAGE', 'url' => 'cash', 'label' => 'Counter Cash', 'icon' => 'bi-cash-stack'],
            ['permission' => 'EXPENSE_MANAGE', 'url' => 'expenses', 'label' => 'Expenses', 'icon' => 'bi-receipt'],
            ['permission' => 'EXPENSE_MANAGE', 'url' => 'expenses/categories', 'label' => 'Expense Categories', 'icon' => 'bi-tags'],
        ]],
        'reporting' => ['label' => 'Reporting & Audit', 'icon' => 'bi-bar-chart', 'items' => [
            ['permission' => 'REPORT_VIEW', 'url' => 'reports/branches', 'label' => 'Branch Overview', 'icon' => 'bi-diagram-3'],
            ['permission' => 'REPORT_VIEW', 'url' => 'reports', 'label' => 'Reports', 'icon' => 'bi-bar-chart'],
            ['permission' => 'REPORT_VIEW', 'url' => 'reports/inventory-detail', 'label' => 'Inventory Detail Report', 'icon' => 'bi-clipboard-data'],
            ['permission' => 'AUDIT_VIEW', 'url' => 'audit', 'label' => 'Audit Log', 'icon' => 'bi-shield-check'],
        ]],
        'configuration' => ['label' => 'Configuration', 'icon' => 'bi-gear', 'items' => [
            ['permission' => 'RATE_MANAGE', 'url' => 'rates', 'label' => 'LPG Rates', 'icon' => 'bi-tags'],
            ['permission' => 'USER_MANAGE', 'url' => 'users', 'label' => 'Users / Roles', 'icon' => 'bi-person-gear'],
            ['permission' => ['APPLICATION_SETTINGS_VIEW', 'BUSINESS_SETTINGS_VIEW', 'SHOP_SETTINGS_VIEW'], 'url' => 'configuration', 'label' => 'Central Configuration', 'icon' => 'bi-sliders2'],
            ['permission' => ['APPLICATION_SETTINGS_VIEW', 'APPLICATION_SETTINGS_MANAGE', 'SHOP_ACCESS_MANAGE'], 'url' => 'business-hierarchy', 'label' => 'Business Hierarchy', 'icon' => 'bi-diagram-2'],
            ['permission' => ['APPLICATION_SETTINGS_VIEW', 'APPLICATION_SETTINGS_MANAGE', 'SHOP_ACCESS_MANAGE'], 'url' => 'business-management', 'label' => 'Businesses & Shops', 'icon' => 'bi-building-gear'],
            ['permission' => ['USER_MANAGE', 'INVENTORY_MANAGE'], 'url' => 'shop-settings', 'label' => 'Shop Settings', 'icon' => 'bi-gear-wide-connected'],
            ['permission' => 'BACKUP_MANAGE', 'url' => 'database-backup', 'label' => 'Database Backup & Restore', 'icon' => 'bi-database-gear'],
        ]],
    ];

    /**
     * @param array<string,mixed> $permissions permission code => anything (PermissionService::current())
     * @return array{dashboard: array|null, groups: array, active: string, trail: list<string>}
     */
    public static function build(array $permissions, string $currentPath): array
    {
        $can = static fn (string $code): bool => isset($permissions[$code]);
        $allowed = static fn (array $item): bool => is_array($item['permission'])
            ? (bool) array_filter($item['permission'], $can)
            : $can($item['permission']);

        $groups = [];
        foreach (self::GROUPS as $key => $group) {
            $group['items'] = array_values(array_filter($group['items'], $allowed));
            if ($group['items']) {
                $groups[$key] = $group;
            }
        }

        $active = $currentPath === '' || str_starts_with($currentPath, 'dashboard') ? 'dashboard' : '';
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $url = $item['url'];
                if (($currentPath === $url || str_starts_with($currentPath, $url . '/')) && strlen($url) > strlen($active)) {
                    $active = $url;
                }
            }
        }

        $trail = [];
        foreach ($groups as $key => $group) {
            $urls = array_column($group['items'], 'url');
            $groups[$key]['open'] = in_array($active, $urls, true);
            if ($groups[$key]['open']) {
                $item = $group['items'][array_search($active, $urls, true)];
                $trail = [$group['label'], $item['label']];
            }
        }

        return [
            'dashboard' => $allowed(self::DASHBOARD) ? self::DASHBOARD : null,
            'groups' => $groups,
            'active' => $active,
            'trail' => $trail,
        ];
    }
}
