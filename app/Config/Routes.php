<?php
use CodeIgniter\Router\RouteCollection;
/** @var RouteCollection $routes */
$routes->get('/','Auth::showLogin'); $routes->get('login','Auth::showLogin'); $routes->post('login','Auth::attemptLogin'); $routes->get('installation','Installation::index'); $routes->post('installation/run','Installation::run'); $routes->post('migrations/run','Migrations::run'); $routes->get('logout','Auth::logout'); $routes->get('database-backup','DatabaseBackup::index'); $routes->get('database-backup/download','DatabaseBackup::download'); $routes->post('database-backup/create','DatabaseBackup::create'); $routes->post('database-backup/restore','DatabaseBackup::restore'); $routes->post('database-backup/email-settings/save','DatabaseBackup::saveEmailSettings'); $routes->post('database-backup/email-settings/test','DatabaseBackup::testEmail'); $routes->post('database-backup/delete-selected','DatabaseBackup::deleteSelected'); $routes->post('database-backup/email-selected','DatabaseBackup::emailSelected');
$routes->group('api/v1', ['filter'=>'api-ready'], static function(RouteCollection $routes){
    $routes->get('health', static fn() => service('response')->setJSON(['status'=>'ok','api_version'=>'v1']));
    $routes->post('customer/auth/signup','Api\\V1\\Auth::customerSignup');
    $routes->post('customer/auth/login','Api\\V1\\Auth::customerLogin');
    $routes->post('customer/auth/refresh','Api\\V1\\Auth::customerRefresh');
    $routes->post('staff/auth/login','Api\\V1\\Auth::staffLogin');
    $routes->post('staff/auth/refresh','Api\\V1\\Auth::staffRefresh');

    $routes->group('customer', ['filter'=>'api-auth:customer'], static function(RouteCollection $routes){
        $routes->post('auth/logout','Api\\V1\\Auth::customerLogout');
        $routes->post('auth/change-password','Api\\V1\\Auth::customerChangePassword');
        $routes->get('me','Api\\V1\\Customer\\Me::show');
        $routes->get('products','Api\\V1\\Customer\\Products::index');
        $routes->get('orders','Api\\V1\\Customer\\Orders::index');
        $routes->post('orders','Api\\V1\\Customer\\Orders::create');
        $routes->get('orders/(:num)','Api\\V1\\Customer\\Orders::show/$1');
        $routes->post('orders/(:num)/cancel','Api\\V1\\Customer\\Orders::cancel/$1');
        $routes->post('orders/(:num)/reconfirm-price','Api\\V1\\Customer\\Orders::reconfirmPrice/$1');
    });
    $routes->group('staff', ['filter'=>'api-auth:staff'], static function(RouteCollection $routes){
        $routes->post('auth/logout','Api\\V1\\Auth::staffLogout');
        $routes->post('auth/change-password','Api\\V1\\Auth::staffChangePassword');
        $routes->get('me','Api\\V1\\Staff\\Me::show');
        $routes->get('shops','Api\\V1\\Staff\\Me::shops');
        $routes->get('businesses','Api\\V1\\Staff\\Businesses::index');
        $routes->post('businesses','Api\\V1\\Staff\\Businesses::createBusiness');
        $routes->get('businesses/(:num)/shops','Api\\V1\\Staff\\Businesses::shops/$1');
        $routes->post('businesses/(:num)/shops','Api\\V1\\Staff\\Businesses::createShop/$1');
        $routes->get('businesses/(:num)/staff-access','Api\\V1\\Staff\\Businesses::staffAccess/$1');
        $routes->post('businesses/(:num)/staff-access','Api\\V1\\Staff\\Businesses::setStaffAccess/$1');
        $routes->get('orders','Api\\V1\\Staff\\Orders::index');
        $routes->get('orders/(:num)','Api\\V1\\Staff\\Orders::show/$1');
        $routes->post('orders/(:num)/status','Api\\V1\\Staff\\Orders::updateStatus/$1');
        $routes->get('businesses/(:num)/catalog','Api\\V1\\Staff\\Catalog::businessCatalog/$1');
        $routes->post('businesses/(:num)/catalog','Api\\V1\\Staff\\Catalog::saveBusinessProduct/$1');
        $routes->get('shops/(:num)/products','Api\\V1\\Staff\\Catalog::shopProducts/$1');
        $routes->post('shops/(:num)/products/(:num)/availability','Api\\V1\\Staff\\Catalog::setShopProductAvailability/$1/$2');
        $routes->get('customer-signups','Api\\V1\\Staff\\CustomerAccounts::signupRequests');
        $routes->post('customer-signups/(:num)/approve','Api\\V1\\Staff\\CustomerAccounts::approveSignup/$1');
        $routes->post('customer-signups/(:num)/reject','Api\\V1\\Staff\\CustomerAccounts::rejectSignup/$1');
        $routes->post('customers/(:num)/app-account','Api\\V1\\Staff\\CustomerAccounts::createAccount/$1');
        $routes->post('customer-app-accounts/(:num)/disable','Api\\V1\\Staff\\CustomerAccounts::disableAccount/$1');
    });
});

$routes->group('', ['filter'=>'auth'], static function(RouteCollection $routes){
    $routes->get('dashboard','Dashboard::index');
    $routes->get('configuration','Configuration::index');
    $routes->get('configuration/application','Configuration::application');
    $routes->get('configuration/business/(:num)','Configuration::business/$1');
    $routes->get('configuration/shop/(:num)','Configuration::shop/$1');
    $routes->post('configuration/save','Configuration::save');
    $routes->post('configuration/reset','Configuration::reset');
    $routes->post('shop-context/select','ShopContext::select'); $routes->post('database-backup/transactional-refresh','DatabaseBackup::refreshTransactionalData');
    $routes->get('shop-settings','ShopSettings::index'); $routes->post('shop-settings/save','ShopSettings::save');
    $routes->get('sales','Sales::index'); $routes->get('sales/customer-balance/(:num)','Sales::customerBalance/$1'); $routes->get('sales/history','Sales::historyPage'); $routes->get('sales/history/data','Sales::historyData'); $routes->get('sales/details/(:num)','Sales::details/$1'); $routes->post('sales/save','Sales::save'); $routes->get('sales/custody/(:num)','Sales::custody/$1'); $routes->get('sales/receipt/(:num)','Sales::receipt/$1'); $routes->post('sales/void/(:num)','Sales::void/$1');
    $routes->get('cash','Cash::index'); $routes->get('cash/history','Cash::history'); $routes->post('cash/open','Cash::open'); $routes->post('cash/close','Cash::close'); $routes->post('cash/move','Cash::move');
    $routes->get('purchases','Purchases::index'); $routes->post('purchases/save','Purchases::save'); $routes->post('purchases/void/(:num)','Purchases::void/$1');
    $routes->get('inventory','Inventory::index'); $routes->get('inventory/cylinders/(:num)','Inventory::cylinders/$1'); $routes->get('inventory/cylinder/(:num)','Inventory::cylinder/$1'); $routes->get('inventory/type-history/(:num)','Inventory::typeHistory/$1'); $routes->get('inventory/adjustments','Inventory::adjustments'); $routes->get('inventory/adjustments/(:num)','Inventory::adjustmentDetails/$1'); $routes->post('inventory/adjust','Inventory::adjust'); $routes->get('inventory/controls','InventoryControls::index'); $routes->post('inventory/controls/save','InventoryControls::save'); $routes->get('inventory/wastage','InventoryControls::wastage'); $routes->post('inventory/wastage/record','InventoryControls::recordWastage');
    $routes->get('expenses','Expenses::index'); $routes->post('expenses/save','Expenses::save');
    $routes->get('receipts','Receipts::index'); $routes->post('receipts/save','Receipts::save');
    $routes->get('supplier-payments','SupplierPayments::index'); $routes->post('supplier-payments/save','SupplierPayments::save');
    $routes->get('reports','Reports::index'); $routes->get('reports/inventory-detail','Reports::inventoryDetail'); $routes->get('reports/custody','Reports::custody'); $routes->get('reports/ledger/(:num)','Reports::ledger/$1');
    $routes->get('audit','Audit::index');
    $routes->get('customers','Customers::index'); $routes->get('customers/template','Customers::downloadTemplate'); $routes->post('customers/import','Customers::importExcel'); $routes->get('customers/ledger/(:num)','Customers::ledger/$1'); $routes->post('customers/save','Customers::save');
    $routes->get('suppliers','Suppliers::index'); $routes->get('suppliers/ledger/(:num)','Suppliers::ledger/$1'); $routes->post('suppliers/save','Suppliers::save');
    $routes->get('cylinder-types','CylinderTypes::index'); $routes->post('cylinder-types/save','CylinderTypes::save'); $routes->post('cylinder-types/toggle','CylinderTypes::toggle'); $routes->post('cylinder-types/delete','CylinderTypes::delete');
    $routes->get('rates','Rates::index'); $routes->post('rates/save','Rates::save');
    $routes->get('users','Users::index'); $routes->post('users/save','Users::save'); $routes->post('users/role-permissions','Users::rolePermissions');
    $routes->get('inventory/opening','InventoryOpening::index'); $routes->get('inventory/opening/template','InventoryOpening::downloadTemplate'); $routes->post('inventory/opening/import','InventoryOpening::importExcel'); $routes->post('inventory/opening/save','InventoryOpening::save'); $routes->post('inventory/opening/delete/(:num)','InventoryOpening::delete/$1');
});