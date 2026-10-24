<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-003: accounts still using a shipped default password (schema.sql `admin`, migration 030
 * `pak`) must set a new password at their next sign-in.
 */
class UserMustChangePassword extends Migration
{
    /** Passwords that were ever shipped with the product or its seed data. */
    private const KNOWN_DEFAULTS = ['admin123', '1', 'pak', 'pak123', 'admin', 'password'];

    public function up(): void
    {
        $this->forge->addColumn('users', [
            'must_change_password' => ['type' => 'BOOLEAN', 'null' => false, 'default' => 0, 'after' => 'password_hash'],
        ]);

        foreach ($this->db->table('users')->select('id,password_hash')->get()->getResultArray() as $user) {
            foreach (self::KNOWN_DEFAULTS as $candidate) {
                if (password_verify($candidate, (string) $user['password_hash'])) {
                    $this->db->table('users')->where('id', (int) $user['id'])->update(['must_change_password' => 1]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'must_change_password');
    }
}
