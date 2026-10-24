<?php

namespace App\Database\MySQLi;

use CodeIgniter\Database\MySQLi\Connection as BaseMySQLiConnection;

/**
 * The application's MySQLi connection (BL-009). It behaves exactly like CodeIgniter's driver;
 * the only difference is its Query Builder, which stamps the tenant on new rows. Forge and
 * Utils still resolve to CodeIgniter's MySQLi classes.
 */
class Connection extends BaseMySQLiConnection
{
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->DBDriver = 'MySQLi';
    }
}
