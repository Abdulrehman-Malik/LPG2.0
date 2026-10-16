<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Concurrency-safe document numbers (Phase 10.5): {PREFIX}{yyyymmdd}-{00001}, one sequence per
 * branch, document type and transaction day.
 *
 * The counter row in `document_sequences` is incremented with a single atomic upsert. Inside the
 * caller's posting transaction the row stays locked until commit, so two simultaneous postings get
 * different numbers; if the posting rolls back, the increment rolls back too (no gaps).
 * A new day's sequence is seeded from the highest number already used in that format, so it never
 * collides with documents created before this service existed.
 */
class DocumentNumberService
{
    /** doc type => [prefix, table, number column] */
    public const TYPES = [
        'sale' => ['S', 'sales', 'sale_no'],
        'receipt' => ['R', 'customer_receipts', 'receipt_no'],
        'purchase' => ['P', 'purchases', 'purchase_no'],
        'supplier_payment' => ['SP', 'supplier_payments', 'payment_no'],
        'expense' => ['E', 'expenses', 'expense_no'],
    ];

    public function __construct(private BaseConnection $db)
    {
    }

    public function next(string $docType, int $locationId, ?string $transactionAt = null): string
    {
        if (!isset(self::TYPES[$docType])) {
            throw new InvalidArgumentException('Unknown document type ' . $docType);
        }
        if ($locationId <= 0) {
            throw new RuntimeException('A branch is required to number a document.');
        }
        [$prefix, $table, $column] = self::TYPES[$docType];
        $ts = $transactionAt !== null ? strtotime($transactionAt) : false;
        $period = date('Ymd', $ts !== false ? $ts : time());
        $stem = $prefix . $period . '-';

        // Highest existing number of this exact format (e.g. S20261016-00042) for the seed.
        $seed = (int) ($this->db->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING({$column}, ?) AS UNSIGNED)),0) AS seq
             FROM {$table} WHERE location_id=? AND {$column} LIKE ? AND {$column} REGEXP ?",
            [strlen($stem) + 1, $locationId, $stem . '%', '^' . $prefix . '[0-9]{8}-[0-9]+$']
        )->getRow('seq') ?? 0);

        // LAST_INSERT_ID(expr) is evaluated even when the upsert then fails (e.g. unknown branch),
        // so the statement's own result must be checked before trusting the value.
        $ok = $this->db->query(
            'INSERT INTO document_sequences(location_id,doc_type,period,last_value) VALUES(?,?,?,LAST_INSERT_ID(?))
             ON DUPLICATE KEY UPDATE last_value=LAST_INSERT_ID(last_value+1)',
            [$locationId, $docType, $period, $seed + 1]
        );
        if ($ok === false || $this->db->affectedRows() < 1) {
            throw new RuntimeException('Document number could not be generated for this branch.');
        }
        $value = (int) $this->db->query('SELECT LAST_INSERT_ID() AS v')->getRow('v');
        if ($value <= 0) {
            throw new RuntimeException('Document number could not be generated.');
        }
        return $stem . str_pad((string) $value, 5, '0', STR_PAD_LEFT);
    }
}
