<?php
declare(strict_types=1);

namespace nexpell;

use mysqli;

require_once __DIR__ . '/DatabaseMigrationHelper.php';

/**
 * Backward-compatible migration wrapper used by update_core_real.php.
 */
class CMSDatabaseMigration extends DatabaseMigrationHelper
{
    public function __construct(?mysqli $database = null)
    {
        if ($database instanceof mysqli) {
            $GLOBALS['_database'] = $database;
        }
    }
}
