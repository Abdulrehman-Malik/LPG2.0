<?php

namespace App\Commands;

use App\Services\SalesService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * QA-only command (tests/qa). verify_phase8.sh copies it into app/Commands for one check and
 * removes it again; it is never deployed. Posts credit sales with no browser session to prove that
 * balances and credit limits use the posting branch passed by the caller (Phase 8.3.1).
 */
class QaPhase8Credit extends BaseCommand
{
    protected $group = 'QA';
    protected $name = 'qa:phase8-credit';
    protected $usage = 'qa:phase8-credit <location_id> <customer_id> <cylinder_type_id> <unit_id>';

    public function run(array $params)
    {
        [$locationId, $customerId, $typeId, $unitId] = array_map('intval', $params + [0, 0, 0, 0]);
        $db = Database::connect();
        $db->table('shop_settings')->where('location_id', $locationId)->update(['credit_limit_validation_mode' => 'customer']);
        $db->table('customers')->where('id', $customerId)->update(['allow_credit_sale' => 1, 'credit_limit' => 150]);
        $payload = static fn () => [
            'transaction_type' => 'gas_sale',
            'customer_id' => $customerId,
            'lines' => [['cylinder_type_id' => $typeId, 'source_cylinder_unit_id' => $unitId, 'quantity' => 1, 'gas_entry_mode' => 'quantity', 'gas_rate' => 100]],
            'payments' => [['payment_type' => 'sale', 'payment_mode' => 'credit', 'amount' => 0]],
        ];
        foreach (['FIRST', 'SECOND'] as $step) {
            try {
                $sale = (new SalesService())->post($payload(), 1, $locationId);
                CLI::write($step . '_OK ' . $sale['sale_no']);
            } catch (\Throwable $e) {
                CLI::write($step . '_REJECTED: ' . $e->getMessage());
            }
        }
    }
}
