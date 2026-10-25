<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-019: stored responses for API creates sent with an Idempotency-Key header, so a mobile
 * client retrying after a timeout gets the original result instead of a duplicate.
 */
class ApiIdempotencyKeys extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'principal_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'principal_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'route' => ['type' => 'VARCHAR', 'constraint' => 190],
            'request_fingerprint' => ['type' => 'CHAR', 'constraint' => 64],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'response_status' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'response_body' => ['type' => 'MEDIUMTEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['principal_type', 'principal_id', 'idempotency_key'], 'uq_api_idempotency_key');
        $this->forge->addKey('created_at', false, false, 'idx_api_idempotency_created');
        $this->forge->createTable('api_idempotency_keys');
    }

    public function down(): void
    {
        $this->forge->dropTable('api_idempotency_keys');
    }
}
