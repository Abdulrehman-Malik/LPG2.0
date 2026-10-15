<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * Inter-branch stock transfer of physical cylinders within one business (owner decision D9-2).
 *
 * Flow is a business setting (stock_transfer_mode):
 *   one_step — cylinders leave the sending branch and arrive at the receiving branch at once.
 *   two_step — the sending branch dispatches (cylinders become 'in_transit' and leave its
 *              stock); the receiving branch confirms receipt (cylinders join its stock).
 *              A dispatched transfer can be cancelled by the sending branch before receipt.
 *
 * Each cylinder keeps its identity and actual gas weight. Every stock change is written to
 * inventory_movements (source_type stock_transfer / stock_transfer_cancel) at the branch
 * where it happens, so both branches' histories reconcile.
 */
class StockTransferService
{
    private BaseConnection $db;
    private ConfigurationService $configuration;
    /** @var list<string> */
    private array $locks = [];

    public function __construct(?BaseConnection $db = null, ?ConfigurationService $configuration = null)
    {
        $this->db = $db ?? Database::connect();
        $this->configuration = $configuration ?? new ConfigurationService($this->db);
    }

    public function isEnabled(int $locationId): bool
    {
        return (bool) $this->configuration->valueForShop('stock_transfer_enabled', $locationId);
    }

    public function flow(int $locationId): string
    {
        return (string) $this->configuration->valueForShop('stock_transfer_mode', $locationId);
    }

    /** Other active branches of the same business. */
    public function destinations(int $fromLocationId): array
    {
        return $this->db->table('locations')
            ->select('id,code,name,city')
            ->where('business_id', BusinessContext::businessIdForLocation($fromLocationId))
            ->where('is_active', 1)
            ->where('id !=', $fromLocationId)
            ->orderBy('name')
            ->get()->getResultArray();
    }

    /** Filled and empty cylinders in the branch's stock that can be transferred. */
    public function availableUnits(int $locationId): array
    {
        return $this->db->table('cylinder_units cu')
            ->select('cu.id,cu.unit_code,cu.status,cu.gas_weight_kg,cu.cylinder_type_id,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg')
            ->join('cylinder_types ct', 'ct.id=cu.cylinder_type_id')
            ->where('cu.location_id', $locationId)
            ->whereIn('cu.status', ['filled', 'empty'])
            ->orderBy('ct.sort_order')->orderBy('cu.status')->orderBy('cu.id')
            ->get()->getResultArray();
    }

    /**
     * @param list<int> $unitIds
     */
    public function create(int $fromLocationId, int $toLocationId, array $unitIds, int $userId, ?string $notes = null, ?string $transferAt = null): array
    {
        if (!$this->isEnabled($fromLocationId)) {
            throw new RuntimeException('Inter-branch stock transfer is not enabled for this business.');
        }
        if ($toLocationId === $fromLocationId) {
            throw new RuntimeException('Choose a different receiving branch.');
        }
        $businessId = BusinessContext::businessIdForLocation($fromLocationId);
        $destination = $this->db->table('locations')->where(['id' => $toLocationId, 'business_id' => $businessId, 'is_active' => 1])->get()->getRowArray();
        if (!$destination) {
            throw new RuntimeException('The receiving branch must be an active branch of the same business.');
        }
        $unitIds = array_values(array_unique(array_filter(array_map('intval', $unitIds), static fn (int $id): bool => $id > 0)));
        if (!$unitIds) {
            throw new RuntimeException('Select at least one cylinder to transfer.');
        }

        $flow = $this->flow($fromLocationId);
        $at = (new TransactionDatePolicy($this->configuration))->resolve($transferAt, $fromLocationId);
        $notes = trim((string) $notes);

        $this->db->transBegin();
        try {
            $units = $this->lockUnits($unitIds, $fromLocationId, ['filled', 'empty']);
            $this->acquireLocks($fromLocationId, $units);
            if ($flow === 'one_step') {
                $this->acquireLocks($toLocationId, $units);
            }

            $this->db->table('stock_transfers')->insert([
                'business_id' => $businessId,
                'transfer_no' => 'TMP-' . bin2hex(random_bytes(8)),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'flow' => $flow,
                'status' => $flow === 'one_step' ? 'received' : 'in_transit',
                'transfer_at' => $at,
                'notes' => $notes !== '' ? mb_substr($notes, 0, 500) : null,
                'created_by' => $userId,
                'received_by' => $flow === 'one_step' ? $userId : null,
                'received_at' => $flow === 'one_step' ? $at : null,
            ]);
            $transferId = (int) $this->db->insertID();
            // Document number from the auto-increment ID: unique without a racing MAX()+1.
            $transferNo = 'TR-' . str_pad((string) $transferId, 6, '0', STR_PAD_LEFT);
            $this->db->table('stock_transfers')->where('id', $transferId)->update(['transfer_no' => $transferNo]);

            foreach ($units as $unit) {
                $this->db->table('stock_transfer_items')->insert([
                    'transfer_id' => $transferId,
                    'cylinder_unit_id' => (int) $unit['id'],
                    'cylinder_type_id' => (int) $unit['cylinder_type_id'],
                    'unit_status' => $unit['status'],
                    'gas_weight_kg' => (float) $unit['gas_weight_kg'],
                ]);
                $this->writeMovements($unit, $unit['status'], (float) $unit['gas_weight_kg'], $fromLocationId, 'out', 'stock_transfer', $transferId, $userId, $at, 'Transfer ' . $transferNo . ' to ' . $destination['name']);
                if ($flow === 'one_step') {
                    $this->db->table('cylinder_units')->where('id', (int) $unit['id'])->update(['location_id' => $toLocationId]);
                    $this->writeMovements($unit, $unit['status'], (float) $unit['gas_weight_kg'], $toLocationId, 'in', 'stock_transfer', $transferId, $userId, $at, 'Transfer ' . $transferNo . ' received');
                } else {
                    $this->db->table('cylinder_units')->where('id', (int) $unit['id'])->update(['status' => 'in_transit']);
                }
            }

            if (!$this->db->transStatus()) {
                throw new RuntimeException('Stock transfer could not be posted.');
            }
            $this->db->transCommit();
            $this->releaseLocks();
        } catch (\Throwable $e) {
            $this->releaseLocks();
            $this->db->transRollback();
            throw $e;
        }

        AuditService::log('STOCK_TRANSFER_CREATED', 'stock_transfer', $transferId, null, [
            'transfer_no' => $transferNo, 'flow' => $flow, 'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId, 'units' => count($units),
        ], $userId, $fromLocationId);

        return $this->find($transferId, $fromLocationId);
    }

    /** Receiving branch confirms a two-step transfer. */
    public function receive(int $transferId, int $locationId, int $userId): array
    {
        $this->db->transBegin();
        try {
            $transfer = $this->db->query('SELECT * FROM stock_transfers WHERE id=? FOR UPDATE', [$transferId])->getRowArray();
            if (!$transfer || (int) $transfer['to_location_id'] !== $locationId) {
                throw new RuntimeException('Transfer not found for this branch.');
            }
            if ($transfer['status'] !== 'in_transit') {
                throw new RuntimeException('Only an in-transit transfer can be received.');
            }
            $items = $this->items($transferId);
            $units = $this->lockUnits(array_map(static fn (array $i): int => (int) $i['cylinder_unit_id'], $items), (int) $transfer['from_location_id'], ['in_transit']);
            $this->acquireLocks($locationId, array_map(static fn (array $i): array => ['status' => $i['unit_status'], 'cylinder_type_id' => $i['cylinder_type_id']], $items));
            $at = date('Y-m-d H:i:s');
            foreach ($items as $item) {
                $unit = $units[(int) $item['cylinder_unit_id']];
                $this->db->table('cylinder_units')->where('id', (int) $unit['id'])->update([
                    'location_id' => $locationId,
                    'status' => $item['unit_status'],
                ]);
                $this->writeMovements($unit, $item['unit_status'], (float) $item['gas_weight_kg'], $locationId, 'in', 'stock_transfer', $transferId, $userId, $at, 'Transfer ' . $transfer['transfer_no'] . ' received');
            }
            $this->db->table('stock_transfers')->where('id', $transferId)->update([
                'status' => 'received', 'received_by' => $userId, 'received_at' => $at,
            ]);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Stock transfer could not be received.');
            }
            $this->db->transCommit();
            $this->releaseLocks();
        } catch (\Throwable $e) {
            $this->releaseLocks();
            $this->db->transRollback();
            throw $e;
        }

        AuditService::log('STOCK_TRANSFER_RECEIVED', 'stock_transfer', $transferId, ['status' => 'in_transit'], ['status' => 'received'], $userId, $locationId);
        return $this->find($transferId, $locationId);
    }

    /** Sending branch cancels a two-step transfer that has not been received yet. */
    public function cancel(int $transferId, int $locationId, int $userId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A cancellation reason is required.');
        }
        $this->db->transBegin();
        try {
            $transfer = $this->db->query('SELECT * FROM stock_transfers WHERE id=? FOR UPDATE', [$transferId])->getRowArray();
            if (!$transfer || (int) $transfer['from_location_id'] !== $locationId) {
                throw new RuntimeException('Transfer not found for this branch.');
            }
            if ($transfer['status'] !== 'in_transit') {
                throw new RuntimeException('Only an in-transit transfer can be cancelled.');
            }
            $items = $this->items($transferId);
            $units = $this->lockUnits(array_map(static fn (array $i): int => (int) $i['cylinder_unit_id'], $items), $locationId, ['in_transit']);
            $this->acquireLocks($locationId, array_map(static fn (array $i): array => ['status' => $i['unit_status'], 'cylinder_type_id' => $i['cylinder_type_id']], $items));
            $at = date('Y-m-d H:i:s');
            foreach ($items as $item) {
                $unit = $units[(int) $item['cylinder_unit_id']];
                $this->db->table('cylinder_units')->where('id', (int) $unit['id'])->update(['status' => $item['unit_status']]);
                $this->writeMovements($unit, $item['unit_status'], (float) $item['gas_weight_kg'], $locationId, 'in', 'stock_transfer_cancel', $transferId, $userId, $at, 'Transfer ' . $transfer['transfer_no'] . ' cancelled');
            }
            $this->db->table('stock_transfers')->where('id', $transferId)->update([
                'status' => 'cancelled', 'cancelled_by' => $userId, 'cancelled_at' => $at, 'cancel_reason' => mb_substr($reason, 0, 255),
            ]);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Stock transfer could not be cancelled.');
            }
            $this->db->transCommit();
            $this->releaseLocks();
        } catch (\Throwable $e) {
            $this->releaseLocks();
            $this->db->transRollback();
            throw $e;
        }

        AuditService::log('STOCK_TRANSFER_CANCELLED', 'stock_transfer', $transferId, ['status' => 'in_transit'], ['status' => 'cancelled', 'reason' => $reason], $userId, $locationId);
        return $this->find($transferId, $locationId);
    }

    /** Transfers sent from or received by the branch, newest first. */
    public function listForLocation(int $locationId, ?string $status = null, ?string $direction = null): array
    {
        $builder = $this->db->table('stock_transfers st')
            ->select('st.*,fl.name from_name,tl.name to_name,u.full_name created_by_name,COUNT(sti.id) unit_count,COALESCE(SUM(sti.gas_weight_kg),0) gas_kg')
            ->join('locations fl', 'fl.id=st.from_location_id')
            ->join('locations tl', 'tl.id=st.to_location_id')
            ->join('users u', 'u.id=st.created_by', 'left')
            ->join('stock_transfer_items sti', 'sti.transfer_id=st.id', 'left');
        if ($direction === 'outgoing') {
            $builder->where('st.from_location_id', $locationId);
        } elseif ($direction === 'incoming') {
            $builder->where('st.to_location_id', $locationId);
        } else {
            $builder->groupStart()->where('st.from_location_id', $locationId)->orWhere('st.to_location_id', $locationId)->groupEnd();
        }
        if (in_array($status, ['in_transit', 'received', 'cancelled'], true)) {
            $builder->where('st.status', $status);
        }
        return $builder->groupBy('st.id')->orderBy('st.transfer_at', 'DESC')->orderBy('st.id', 'DESC')->get()->getResultArray();
    }

    /** One transfer with its items, if the branch is its sender or receiver. */
    public function find(int $transferId, int $locationId): ?array
    {
        $transfer = $this->db->table('stock_transfers st')
            ->select('st.*,fl.name from_name,tl.name to_name,cu.full_name created_by_name,ru.full_name received_by_name,xu.full_name cancelled_by_name')
            ->join('locations fl', 'fl.id=st.from_location_id')
            ->join('locations tl', 'tl.id=st.to_location_id')
            ->join('users cu', 'cu.id=st.created_by', 'left')
            ->join('users ru', 'ru.id=st.received_by', 'left')
            ->join('users xu', 'xu.id=st.cancelled_by', 'left')
            ->where('st.id', $transferId)
            ->groupStart()->where('st.from_location_id', $locationId)->orWhere('st.to_location_id', $locationId)->groupEnd()
            ->get()->getRowArray();
        if (!$transfer) {
            return null;
        }
        $transfer['items'] = $this->db->table('stock_transfer_items sti')
            ->select('sti.*,cu.unit_code,ct.code cylinder_code,ct.name cylinder_name,ct.capacity_kg')
            ->join('cylinder_units cu', 'cu.id=sti.cylinder_unit_id')
            ->join('cylinder_types ct', 'ct.id=sti.cylinder_type_id')
            ->where('sti.transfer_id', $transferId)
            ->orderBy('ct.sort_order')->orderBy('cu.id')
            ->get()->getResultArray();
        return $transfer;
    }

    private function items(int $transferId): array
    {
        return $this->db->table('stock_transfer_items')->where('transfer_id', $transferId)->orderBy('id')->get()->getResultArray();
    }

    /**
     * Locks the units and checks each is at the branch with an allowed status.
     *
     * @param list<int> $unitIds
     * @param list<string> $statuses
     * @return array<int,array>
     */
    private function lockUnits(array $unitIds, int $locationId, array $statuses): array
    {
        sort($unitIds);
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
        $rows = $this->db->query(
            "SELECT * FROM cylinder_units WHERE id IN ($placeholders) ORDER BY id FOR UPDATE",
            $unitIds
        )->getResultArray();
        $units = [];
        foreach ($rows as $row) {
            $units[(int) $row['id']] = $row;
        }
        foreach ($unitIds as $id) {
            $unit = $units[$id] ?? null;
            if (!$unit || (int) $unit['location_id'] !== $locationId || !in_array($unit['status'], $statuses, true)) {
                throw new RuntimeException('Cylinder ' . ($unit['unit_code'] ?? ('#' . $id)) . ' is no longer available for this transfer. Refresh and retry.');
            }
        }
        return $units;
    }

    private function writeMovements(array $unit, string $unitStatus, float $gasKg, int $locationId, string $direction, string $sourceType, int $transferId, int $userId, string $at, string $notes): void
    {
        $base = [
            'location_id' => $locationId, 'movement_at' => $at, 'direction' => $direction,
            'source_type' => $sourceType, 'source_id' => $transferId, 'cylinder_unit_id' => (int) $unit['id'],
            'created_by' => $userId, 'notes' => mb_substr($notes, 0, 255),
        ];
        $this->db->table('inventory_movements')->insert($base + [
            'inventory_type' => $unitStatus === 'filled' ? 'filled_cylinder' : 'empty_cylinder',
            'cylinder_type_id' => (int) $unit['cylinder_type_id'], 'quantity' => 1,
        ]);
        if ($unitStatus === 'filled' && $gasKg > 0) {
            $this->db->table('inventory_movements')->insert($base + [
                'inventory_type' => 'gas_kg', 'cylinder_type_id' => null, 'quantity' => $gasKg,
            ]);
        }
    }

    /**
     * Same advisory-lock keys as SalesService so transfers serialize with POS postings
     * that touch the same branch stock.
     *
     * @param iterable<array{status:string,cylinder_type_id:int|string}> $units
     */
    private function acquireLocks(int $locationId, array $units): void
    {
        $keys = [];
        foreach ($units as $unit) {
            $status = $unit['status'];
            $keys[($status === 'empty' ? 'empty_cylinder' : 'filled_cylinder') . '|' . (int) $unit['cylinder_type_id']] = true;
            $keys['gas_kg|null'] = true;
        }
        $keys = array_keys($keys);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $lockKey = 'lpg_inv_' . $locationId . '_' . substr(hash('sha256', $key), 0, 48);
            $result = $this->db->query('SELECT GET_LOCK(?,5) AS locked', [$lockKey])->getRowArray();
            if ((int) ($result['locked'] ?? 0) !== 1) {
                throw new RuntimeException('Inventory is busy; please retry the transfer.');
            }
            $this->locks[] = $lockKey;
        }
    }

    private function releaseLocks(): void
    {
        foreach (array_reverse($this->locks) as $lockKey) {
            $this->db->query('SELECT RELEASE_LOCK(?) AS released', [$lockKey]);
        }
        $this->locks = [];
    }
}
