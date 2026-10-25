<?php

namespace App\Libraries;

use App\Models\ShopSettingsModel;
use App\Services\MigrationRunnerService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use Config\Database;

/**
 * Data the application shell (layouts/app.php) needs on every page: current branch and
 * business, the branches the user may switch to, branch theme settings, the permission-aware
 * menu and any pending-migration notice (BL-018: moved out of the view, unchanged).
 */
final class LayoutData
{
    /** @param array<string,mixed> $location a page may pass its own branch row */
    public static function build(array $location, ?string $title): array
    {
        $branchId = TenantContext::branchId() ?? (int) (session()->get('location_id') ?? 0);
        $availableShops = [];
        try {
            $db = Database::connect();
            if (!$location && $branchId > 0) {
                $location = $db->table('locations l')
                    ->select('l.*, b.name AS business_name')
                    ->join('businesses b', 'b.id=l.business_id', 'left')
                    ->where('l.id', $branchId)
                    ->get()->getRowArray() ?: [];
            }
            $allowedShopIds = (new ShopScopeService())->accessibleShopIds();
            if ($allowedShopIds) {
                $availableShops = $db->table('locations l')
                    ->select('l.id, l.name, l.business_id, b.name AS business_name')
                    ->join('businesses b', 'b.id=l.business_id', 'left')
                    ->whereIn('l.id', $allowedShopIds)
                    ->orderBy('b.name')->orderBy('l.name')
                    ->get()->getResultArray();
            }
        } catch (\Throwable $e) {
            $availableShops = [];
        }

        $branchName = trim((string) ($location['name'] ?? ''));
        $branchName = $branchName !== '' ? $branchName : (string) env('app.productName', 'Perfect LPG');
        $businessName = trim((string) ($location['business_name'] ?? ''));
        if ($businessName === '' && !empty($location['business_id'])) {
            try {
                $businessName = (string) (Database::connect()->table('businesses')->select('name')->where('id', (int) $location['business_id'])->get()->getRow('name') ?? '');
            } catch (\Throwable $e) {
                $businessName = '';
            }
        }

        $pendingMigrationNotice = null;
        try {
            $migrationStatus = (new MigrationRunnerService())->status();
            if (!$migrationStatus['ready']) {
                $pendingFiles = array_map(static fn (array $row): string => (string) ($row['file'] ?? 'migration'), $migrationStatus['migrations'] ?? []);
                $pendingMigrationNotice = 'Database migrations are pending. Please log off and execute the pending migrations from the login page.';
                if ($pendingFiles) {
                    $pendingMigrationNotice .= ' Pending: ' . implode(', ', $pendingFiles) . '.';
                }
            }
        } catch (\Throwable $e) {
            $pendingMigrationNotice = 'Database migration status could not be verified. Please log off and check the migration status from the login page.';
        }

        $userName = (string) (session()->get('full_name') ?? 'User');
        $initials = '';
        foreach (array_slice(preg_split('/\s+/', trim($userName), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return [
            'location' => $location,
            'availableShops' => $availableShops,
            'branchName' => $branchName,
            'businessName' => $businessName,
            'appSettings' => (new ShopSettingsModel())->forLocation($branchId),
            'nav' => Navigation::build(PermissionService::current(), uri_string()),
            'pageTitle' => $title ?? 'Dashboard',
            'userName' => $userName,
            'initials' => $initials !== '' ? $initials : 'U',
            'pendingMigrationNotice' => $pendingMigrationNotice,
        ];
    }
}
