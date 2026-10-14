<?php

namespace App\Controllers;

use App\Models\CylinderTypeModel;
use App\Models\InventoryOpeningBalanceModel;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\CylinderUnitService;
use App\Services\ExcelInventoryService;
use Config\Database;
use CodeIgniter\Controller;

class InventoryOpening extends Controller
{
    protected InventoryOpeningBalanceModel $model;
    protected CylinderTypeModel $types;
    protected CylinderUnitService $cylinders;
    protected ExcelInventoryService $excel;

    public function __construct()
    {
        $this->model = new InventoryOpeningBalanceModel();
        $this->types = new CylinderTypeModel();
        $this->cylinders = new CylinderUnitService();
        $this->excel = new ExcelInventoryService();
    }

    private function guard(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if (!PermissionService::allows('INVENTORY_MANAGE')) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }
        return null;
    }

    public function index()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $locationId = (int) session()->get('location_id');
        $rows = $this->model
            ->select('inventory_opening_balances.*, cylinder_types.code AS cylinder_code, cylinder_types.name AS cylinder_name, cylinder_types.capacity_kg')
            ->join('cylinder_types', 'cylinder_types.id=inventory_opening_balances.cylinder_type_id', 'left')
            ->where('inventory_opening_balances.location_id', $locationId)
            ->orderBy('inventory_date', 'DESC')
            ->orderBy('inventory_type')
            ->findAll();

        $gasByOpening = $this->model->gasStockByOpeningIds(
            $locationId,
            array_map(static fn(array $row): int => (int) $row['id'], $rows)
        );

        foreach ($rows as &$row) {
            $row['gas_stock'] = $gasByOpening[(int) $row['id']] ?? 0;
        }
        unset($row);

        return view('inventory/opening', [
            'title' => 'Opening Inventory',
            'types' => $this->types->where('is_active', 1)->orderBy('sort_order')->findAll(),
            'rows' => $rows,
        ]);
    }

    public function save()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $locationId = (int) session()->get('location_id');
        $userId = (int) session()->get('user_id');
        $openingIdRaw = $this->request->getPost('opening_id');
        $openingId = $openingIdRaw === null || $openingIdRaw === '' ? null : (int) $openingIdRaw;

        $date = (string) $this->request->getPost('inventory_date');
        $typeId = $this->request->getPost('cylinder_type_id') === '' ? null : (int) $this->request->getPost('cylinder_type_id');
        $qtyRaw = $this->request->getPost('quantity');
        $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : 0;
        $actualRaw = $this->request->getPost('actual_gas_weight_kg');
        $comments = trim((string) ($this->request->getPost('comments') ?? ''));

        if (!$date || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) || $qty < 1) {
            return redirect()->back()->withInput()->with('error', 'Valid date and a cylinder quantity of at least 1 are required.');
        }
        if (!$typeId) {
            return redirect()->back()->withInput()->with('error', 'Select a cylinder type.');
        }
        if (floor($qty) !== $qty) {
            return redirect()->back()->withInput()->with('error', 'Cylinder quantity must be a whole number.');
        }
        if (mb_strlen($comments) > 500) {
            return redirect()->back()->withInput()->with('error', 'Comments cannot exceed 500 characters.');
        }

        $ct = $this->types->find($typeId);
        if (!$ct) {
            return redirect()->back()->withInput()->with('error', 'Cylinder type not found.');
        }

        $actualProvided = $actualRaw !== null && $actualRaw !== '';
        if (!$actualProvided || !is_numeric($actualRaw)) {
            return redirect()->back()->withInput()->with('error', 'Enter the actual gas quantity per cylinder. Enter 0 for an empty cylinder.');
        }

        $actual = (float) $actualRaw;
        $capacity = (float) $ct['capacity_kg'];

        if ($capacity <= 0) {
            return redirect()->back()->withInput()->with('error', 'Selected cylinder type has an invalid capacity.');
        }
        if ($actual < 0) {
            return redirect()->back()->withInput()->with('error', 'Gas quantity cannot be negative.');
        }
        if ($actual > $capacity + 0.00001) {
            return redirect()->back()->withInput()->with('error', 'Gas quantity cannot exceed the selected cylinder capacity of ' . number_format($capacity, 2) . ' KG.');
        }

        // Full / partially filled / empty is a calculated cylinder condition,
        // not a separate cylinder type.
        $kind = $actual <= 0.00001 ? 'empty_cylinder' : 'filled_cylinder';

        $existing = null;
        if ($openingId !== null) {
            $existing = $this->model
                ->where(['id' => $openingId, 'location_id' => $locationId])
                ->first();
            if (!$existing) {
                return redirect()->back()->withInput()->with('error', 'Opening inventory record not found.');
            }
        }

        $db = Database::connect();

        // Cylinder type is part of the physical identity. It may not be changed
        // while editing an opening entry.
        if ($existing) {
            $typeId = (int) $existing['cylinder_type_id'];
            $ct = $this->types->find($typeId);
            if (!$ct) {
                return redirect()->back()->withInput()->with('error', 'The cylinder type used by this opening record no longer exists.');
            }
            $capacity = (float) $ct['capacity_kg'];
            if ($actual > $capacity + 0.00001) {
                return redirect()->back()->withInput()->with('error', 'Gas quantity cannot exceed the selected cylinder capacity of ' . number_format($capacity, 2) . ' KG.');
            }

            $existingUnits = $db->table('cylinder_units')
                ->select('id')
                ->where([
                    'location_id' => $locationId,
                    'source_type' => 'opening',
                    'source_id' => (int) $existing['id'],
                ])
                ->get()->getResultArray();

            $existingUnitIds = array_values(array_map(
                static fn(array $unit): int => (int) $unit['id'],
                $existingUnits
            ));

            if ($existingUnitIds) {
                $downstreamCount = (int) $db->table('inventory_movements')
                    ->whereIn('cylinder_unit_id', $existingUnitIds)
                    ->where('source_type !=', 'opening_cylinder')
                    ->countAllResults();

                if ($downstreamCount > 0) {
                    return redirect()->back()->withInput()->with(
                        'error',
                        'This opening entry cannot be edited because one or more of its physical cylinders already have downstream inventory history. Use stock adjustment or reversal instead.'
                    );
                }
            }
        }

        $db->transBegin();

        try {
            // The opening table has a unique key on location + date + type + cylinder type.
            // Check it explicitly so users get a useful validation message instead of
            // the generic "could not be saved" transaction error.
            $openingBatchKey = $existing ? (string) ($existing['opening_batch_key'] ?? '') : '';
            $duplicateQuery = $db->table('inventory_opening_balances')
                ->where('location_id', $locationId)
                ->where('inventory_date', $date)
                ->where('inventory_type', $kind)
                ->where('cylinder_type_id', $typeId)
                ->where('opening_batch_key', $openingBatchKey);

            if ($existing) {
                $duplicateQuery->where('id !=', (int) $existing['id']);
            }

            if ($duplicateQuery->countAllResults() > 0) {
                throw new \RuntimeException('An opening inventory entry already exists for this date, cylinder type, and cylinder status. Edit the existing entry instead.');
            }

            $oldValues = $existing ? [
                'id' => (int) $existing['id'],
                'inventory_date' => $existing['inventory_date'],
                'inventory_type' => $existing['inventory_type'],
                'cylinder_type_id' => (int) $existing['cylinder_type_id'],
                'quantity' => (float) $existing['quantity'],
                'comments' => $existing['comments'] ?? null,
            ] : null;

            $data = [
                'location_id' => $locationId,
                'inventory_date' => $date,
                'inventory_type' => $kind,
                'cylinder_type_id' => $typeId,
                'quantity' => $qty,
                'comments' => $comments !== '' ? $comments : null,
                'created_by' => $existing['created_by'] ?? $userId,
            ];

            if ($existing) {
                $ok = $db->table('inventory_opening_balances')
                    ->where(['id' => (int) $existing['id'], 'location_id' => $locationId])
                    ->update($data);

                if ($ok === false) {
                    $error = $db->error();
                    throw new \RuntimeException('Opening inventory update failed: ' . ($error['message'] ?? 'Database error.'));
                }

                $openingId = (int) $existing['id'];
            } else {
                $ok = $db->table('inventory_opening_balances')->insert($data);

                if ($ok === false) {
                    $error = $db->error();
                    throw new \RuntimeException('Opening inventory insert failed: ' . ($error['message'] ?? 'Database error.'));
                }

                $openingId = (int) $db->insertID();
                if ($openingId <= 0) {
                    throw new \RuntimeException('Opening inventory insert failed: no record ID was generated.');
                }
            }

            // Every new opening save creates the requested number of new
            // physical cylinder units. On edit, the existing opening is rebuilt
            // only after the downstream-history guard above confirms it is safe.
            if ($existing) {
                $db->table('inventory_movements')
                    ->where(['source_type' => 'opening_cylinder', 'source_id' => $openingId])
                    ->delete();

                $db->table('cylinder_units')
                    ->where([
                        'location_id' => $locationId,
                        'source_type' => 'opening',
                        'source_id' => $openingId,
                    ])
                    ->delete();
            }

            $unitStatus = $actual <= 0.00001 ? 'empty' : 'filled';

            $this->cylinders->createUnits(
                $locationId,
                $typeId,
                (int) $qty,
                $unitStatus,
                $actual,
                $userId,
                'opening',
                $openingId
            );

            if ($unitStatus === 'filled') {
                $current = $db->table('cylinder_units')
                    ->where([
                        'location_id' => $locationId,
                        'source_type' => 'opening',
                        'source_id' => $openingId,
                        'status' => 'filled',
                    ])
                    ->get()->getResultArray();

                foreach ($current as $unit) {
                    $db->table('inventory_movements')->insert([
                        'location_id' => $locationId,
                        'inventory_type' => 'gas_kg',
                        'cylinder_type_id' => null,
                        'quantity' => $actual,
                        'direction' => 'in',
                        'movement_at' => $date . ' 00:00:00',
                        'source_type' => 'opening_cylinder',
                        'source_id' => $openingId,
                        'cylinder_unit_id' => $unit['id'],
                        'created_by' => $userId,
                        'notes' => 'Gas contained in opening filled cylinder',
                    ]);
                }
            }


            $newValues = [
                'id' => $openingId,
                'inventory_date' => $date,
                'inventory_type' => $kind,
                'cylinder_type_id' => $typeId,
                'quantity' => $qty,
                'comments' => $comments !== '' ? $comments : null,
                'cylinder_status' => $actual <= 0.00001
                    ? 'Empty'
                    : ($actual < $capacity - 0.00001 ? 'Partially Filled' : 'Full'),
                'gas_per_cylinder_kg' => $actual,
                'gas_stock_kg' => $kind === 'filled_cylinder'
                    ? (float) $db->table('cylinder_units')
                        ->selectSum('gas_weight_kg')
                        ->where([
                            'location_id' => $locationId,
                            'source_type' => 'opening',
                            'source_id' => $openingId,
                            'status' => 'filled',
                        ])
                        ->get()->getRow('gas_weight_kg')
                    : 0,
            ];

            AuditService::log(
                $oldValues === null ? 'CREATE' : 'UPDATE',
                'inventory_opening',
                $openingId,
                $oldValues,
                $newValues,
                $userId,
                $locationId
            );

            if (!$db->transStatus()) {
                $error = $db->error();
                throw new \RuntimeException('Opening inventory could not be saved: ' . ($error['message'] ?? 'database transaction failed.'));
            }

            $db->transCommit();

            return redirect()->to(site_url('inventory/opening'))
                ->with('success', $oldValues === null ? 'Opening inventory created successfully.' : 'Opening inventory updated successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $target = WRITEPATH . 'uploads/opening_inventory_template_' . bin2hex(random_bytes(6)) . '.xlsx';
        try {
            $this->excel->createTemplate($target);
            $contents = file_get_contents($target);
            if ($contents === false) {
                throw new \RuntimeException('Could not read the generated Excel template.');
            }

            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="opening_inventory_template.xlsx"')
                ->setHeader('Content-Length', (string) strlen($contents))
                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->setBody($contents);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setBody('Could not generate the Excel template: ' . $e->getMessage());
        } finally {
            if (is_file($target)) {
                @unlink($target);
            }
        }
    }

    public function importExcel()
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $locationId = (int) session()->get('location_id');
        $userId = (int) session()->get('user_id');
        $confirmed = (string) $this->request->getPost('capacity_mismatch_confirmed') === '1';
        $file = $this->request->getFile('opening_inventory_file');

        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'Please select a valid .xlsx Excel file.');
        }
        if (strtolower($file->getExtension()) !== 'xlsx') {
            return redirect()->back()->with('error', 'Only .xlsx Excel files are supported. Download the template and use that format.');
        }

        $tempPath = WRITEPATH . 'uploads/opening_import_' . bin2hex(random_bytes(8)) . '.xlsx';
        try {
            $file->move(dirname($tempPath), basename($tempPath));
            $rows = $this->excel->readOpeningInventory($tempPath);
            $today = date('Y-m-d');
            $db = Database::connect();

            $validated = [];
            $mismatches = [];
            $errors = [];
            $typeCache = [];

            foreach ($rows as $row) {
                $excelRow = (int) $row['excel_row'];
                $code = strtoupper(trim((string) $row['cylinder_type']));
                $quantityRaw = $row['quantity'];
                $capacityRaw = $row['max_gas_capacity'];
                $gasRaw = $row['available_gas'];

                if ($code === '' || !preg_match('/^[A-Z0-9_-]{1,30}$/', $code)) {
                    $errors[] = 'Row ' . $excelRow . ': Cylinder Type must be 1-30 characters using letters, numbers, hyphen or underscore.';
                    continue;
                }
                if (!is_numeric($quantityRaw) || (float) $quantityRaw < 1 || floor((float) $quantityRaw) !== (float) $quantityRaw) {
                    $errors[] = 'Row ' . $excelRow . ': Quantity must be a positive whole number.';
                    continue;
                }
                if (!is_numeric($capacityRaw) || (float) $capacityRaw <= 0) {
                    $errors[] = 'Row ' . $excelRow . ': Max GAS Capacity must be greater than zero.';
                    continue;
                }
                if (!is_numeric($gasRaw) || (float) $gasRaw < 0) {
                    $errors[] = 'Row ' . $excelRow . ': Available Gas must be zero or greater.';
                    continue;
                }

                $quantity = (int) $quantityRaw;
                $excelCapacity = (float) $capacityRaw;
                $gas = (float) $gasRaw;
                if ($gas > $excelCapacity + 0.00001) {
                    $errors[] = 'Row ' . $excelRow . ': Available Gas cannot exceed Max GAS Capacity.';
                    continue;
                }

                if (!array_key_exists($code, $typeCache)) {
                    $typeCache[$code] = $db->table('cylinder_types')->where('code', $code)->get()->getRowArray();
                }
                $type = $typeCache[$code];

                if ($type) {
                    $dbCapacity = (float) $type['capacity_kg'];
                    if (abs($dbCapacity - $excelCapacity) > 0.00001) {
                        $mismatches[] = [
                            'row' => $excelRow,
                            'code' => $code,
                            'excel_capacity' => $excelCapacity,
                            'database_capacity' => $dbCapacity,
                        ];
                        if (!$confirmed) {
                            continue;
                        }
                    }
                    $effectiveCapacity = $dbCapacity;
                    if ($gas > $effectiveCapacity + 0.00001) {
                        $errors[] = 'Row ' . $excelRow . ': Available Gas (' . $gas . ' KG) exceeds the existing database capacity (' . $dbCapacity . ' KG) for ' . $code . '.';
                        continue;
                    }
                    $typeId = (int) $type['id'];
                } else {
                    $effectiveCapacity = $excelCapacity;
                    $typeId = null;
                }

                $validated[] = [
                    'excel_row' => $excelRow,
                    'code' => $code,
                    'quantity' => $quantity,
                    'excel_capacity' => $excelCapacity,
                    'effective_capacity' => $effectiveCapacity,
                    'gas' => $gas,
                    'type_id' => $typeId,
                ];
            }

            if ($mismatches && !$confirmed) {
                $details = array_map(static function (array $m): string {
                    return 'Row ' . $m['row'] . ' (' . $m['code'] . '): Excel ' . number_format($m['excel_capacity'], 2) . ' KG vs database ' . number_format($m['database_capacity'], 2) . ' KG';
                }, $mismatches);
                return redirect()->back()->with('error', 'Capacity mismatch found. Review and upload again with "Confirm capacity mismatches" enabled. Existing database capacity will be retained. ' . implode(' | ', $details));
            }
            if ($errors) {
                return redirect()->back()->with('error', implode(' | ', array_slice($errors, 0, 20)) . (count($errors) > 20 ? ' | More validation errors exist.' : ''));
            }
            if (!$validated) {
                return redirect()->back()->with('error', 'No valid inventory rows were found in the Excel file.');
            }

            $fileHash = hash_file('sha256', $tempPath);
            $batchKeyBase = substr($fileHash, 0, 48);
            $duplicateImport = $db->table('audit_logs')
                ->where('location_id', $locationId)
                ->where('entity_type', 'inventory_opening_excel_import')
                ->like('new_values', '"file_hash":"' . $fileHash . '"')
                ->countAllResults();
            if ($duplicateImport > 0) {
                throw new \RuntimeException('This Excel file has already been imported for this shop. Do not upload the same opening inventory file twice.');
            }

            $db->transBegin();
            try {
                $createdTypes = [];
                $createdOpeningIds = [];
                $totalUnits = 0;
                $totalGas = 0.0;

                foreach ($validated as $index => $row) {
                    $typeId = (int) ($row['type_id'] ?? 0);
                    if ($typeId <= 0) {
                        $maxSort = (int) ($db->table('cylinder_types')->selectMax('sort_order')->get()->getRow('sort_order') ?? 0);
                        $ok = $db->table('cylinder_types')->insert([
                            'code' => $row['code'],
                            'name' => $row['code'],
                            'capacity_kg' => $row['effective_capacity'],
                            'sort_order' => $maxSort + 1,
                            'is_active' => 1,
                        ]);
                        if ($ok === false) {
                            $error = $db->error();
                            throw new \RuntimeException('Could not create cylinder type ' . $row['code'] . ': ' . ($error['message'] ?? 'database error.'));
                        }
                        $typeId = (int) $db->insertID();
                        $typeCache[$row['code']] = $db->table('cylinder_types')->where('id', $typeId)->get()->getRowArray();
                        $createdTypes[] = $row['code'];
                    }

                    $kind = $row['gas'] <= 0.00001 ? 'empty_cylinder' : 'filled_cylinder';
                    $batchKey = $batchKeyBase . '-' . str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT);

                    $ok = $db->table('inventory_opening_balances')->insert([
                        'location_id' => $locationId,
                        'inventory_date' => $today,
                        'inventory_type' => $kind,
                        'cylinder_type_id' => $typeId,
                        'quantity' => $row['quantity'],
                        'comments' => 'Excel opening inventory import; Excel row ' . $row['excel_row'],
                        'opening_batch_key' => $batchKey,
                        'created_by' => $userId,
                    ]);
                    if ($ok === false) {
                        $error = $db->error();
                        throw new \RuntimeException('Opening inventory row ' . $row['excel_row'] . ' could not be saved: ' . ($error['message'] ?? 'database error.'));
                    }
                    $openingId = (int) $db->insertID();
                    if ($openingId <= 0) {
                        throw new \RuntimeException('Opening inventory row ' . $row['excel_row'] . ' did not receive an ID.');
                    }

                    $unitStatus = $row['gas'] <= 0.00001 ? 'empty' : 'filled';
                    $unitIds = $this->cylinders->createUnits(
                        $locationId,
                        $typeId,
                        $row['quantity'],
                        $unitStatus,
                        $row['gas'],
                        $userId,
                        'opening',
                        $openingId
                    );

                    foreach ($unitIds as $unitId) {
                        $movementAt = $today . ' 00:00:00';
                        $db->table('inventory_movements')->insert([
                            'location_id' => $locationId,
                            'inventory_type' => $kind,
                            'cylinder_type_id' => $typeId,
                            'quantity' => 1,
                            'direction' => 'in',
                            'movement_at' => $movementAt,
                            'source_type' => 'opening_excel',
                            'source_id' => $openingId,
                            'cylinder_unit_id' => $unitId,
                            'created_by' => $userId,
                            'notes' => 'Physical cylinder created from Excel opening inventory row ' . $row['excel_row'],
                        ]);

                        if ($unitStatus === 'filled') {
                            $db->table('inventory_movements')->insert([
                                'location_id' => $locationId,
                                'inventory_type' => 'gas_kg',
                                'cylinder_type_id' => null,
                                'quantity' => $row['gas'],
                                'direction' => 'in',
                                'movement_at' => $movementAt,
                                'source_type' => 'opening_excel',
                                'source_id' => $openingId,
                                'cylinder_unit_id' => $unitId,
                                'created_by' => $userId,
                                'notes' => 'Gas contained in Excel opening filled cylinder row ' . $row['excel_row'],
                            ]);
                        }
                    }

                    $createdOpeningIds[] = $openingId;
                    $totalUnits += $row['quantity'];
                    $totalGas += $row['quantity'] * $row['gas'];
                }

                if (!$db->transStatus()) {
                    $error = $db->error();
                    throw new \RuntimeException('Opening inventory Excel import failed: ' . ($error['message'] ?? 'database transaction failed.'));
                }

                AuditService::log(
                    'CREATE',
                    'inventory_opening_excel_import',
                    $createdOpeningIds[0] ?? 0,
                    null,
                    [
                        'file_hash' => $fileHash,
                        'file_name' => $file->getClientName(),
                        'inventory_date' => $today,
                        'row_count' => count($validated),
                        'physical_cylinder_count' => $totalUnits,
                        'gas_kg' => $totalGas,
                        'created_cylinder_types' => $createdTypes,
                        'capacity_mismatch_confirmed' => $confirmed && (bool) $mismatches,
                    ],
                    $userId,
                    $locationId
                );

                $db->transCommit();
            } catch (\Throwable $e) {
                $db->transRollback();
                throw $e;
            }

            $message = 'Excel opening inventory imported successfully: ' . count($validated) . ' rows, ' . $totalUnits . ' physical cylinders, ' . number_format($totalGas, 2) . ' KG gas.';
            if ($createdTypes) {
                $message .= ' Created cylinder types: ' . implode(', ', $createdTypes) . '.';
            }
            if ($mismatches) {
                $message .= ' Capacity mismatch confirmed; existing database capacities were retained.';
            }
            return redirect()->to(site_url('inventory/opening'))->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function delete(int $id)
    {
        if ($r = $this->guard()) {
            return $r;
        }

        $locationId = (int) session()->get('location_id');
        $userId = (int) session()->get('user_id');

        $existing = $this->model
            ->where(['id' => $id, 'location_id' => $locationId])
            ->first();

        if (!$existing) {
            return redirect()->back()->with('error', 'Opening inventory record not found.');
        }

        $db = Database::connect();
        $units = $db->table('cylinder_units')
            ->where([
                'location_id' => $locationId,
                'source_type' => 'opening',
                'source_id' => $id,
            ])
            ->get()->getResultArray();

        $unitIds = array_values(array_map(
            static fn(array $unit): int => (int) $unit['id'],
            $units
        ));

        if ($unitIds) {
            $downstreamCount = (int) $db->table('inventory_movements')
                ->whereIn('cylinder_unit_id', $unitIds)
                ->where('source_type !=', 'opening_cylinder')
                ->countAllResults();

            if ($downstreamCount > 0) {
                return redirect()->back()->with(
                    'error',
                    'This opening entry cannot be deleted because one or more of its physical cylinders already have downstream inventory history. Use stock adjustment or reversal instead.'
                );
            }
        }

        $oldValues = [
            'id' => (int) $existing['id'],
            'inventory_date' => $existing['inventory_date'],
            'inventory_type' => $existing['inventory_type'],
            'cylinder_type_id' => $existing['cylinder_type_id'] !== null ? (int) $existing['cylinder_type_id'] : null,
            'quantity' => (float) $existing['quantity'],
            'comments' => $existing['comments'] ?? null,
            'gas_stock_kg' => $existing['inventory_type'] === 'filled_cylinder'
                ? array_sum(array_map(static fn(array $unit): float => (float) $unit['gas_weight_kg'], $units))
                : 0,
        ];

        $db->transBegin();

        try {
            $db->table('inventory_movements')
                ->where(['source_type' => 'opening_cylinder', 'source_id' => $id])
                ->delete();
            $db->table('cylinder_units')
                ->where(['location_id' => $locationId, 'source_type' => 'opening', 'source_id' => $id])
                ->delete();
            $this->model->delete($id);

            AuditService::log(
                'DELETE',
                'inventory_opening',
                $id,
                $oldValues,
                null,
                $userId,
                $locationId
            );

            if (!$db->transStatus()) {
                throw new \RuntimeException('Opening inventory could not be deleted.');
            }

            $db->transCommit();
            return redirect()->to(site_url('inventory/opening'))
                ->with('success', 'Opening inventory deleted successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
