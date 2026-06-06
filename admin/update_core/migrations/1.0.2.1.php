<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Migration 1.1.0_core (ALTER UPDATER – FINAL FIXED)
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../../system/classes/DatabaseMigrationHelper.php';

use nexpell\DatabaseMigrationHelper;

global $_database, $steps_log;

/* ============================================================
   Migrator initialisieren
============================================================ */

if (!isset($migrator) || !$migrator instanceof DatabaseMigrationHelper) {
    $migrator = new DatabaseMigrationHelper($_database);
}

/* ============================================================
   HELPER: AUTO_INCREMENT sauber reparieren
============================================================ */

if (!function_exists('nx_fix_auto_increment')) {

    function nx_fix_auto_increment(DatabaseMigrationHelper $migrator, string $table): void
    {
        global $_database, $steps_log;

        $exists = $_database->query("SHOW TABLES LIKE '{$table}'");
        if (!$exists || !$exists->num_rows) {
            $steps_log[] = "<div class='small text-muted'>ℹ️ {$table} existiert nicht – übersprungen</div>";
            return;
        }

        $res = $_database->query("
            SELECT MAX(id) AS max_id
            FROM `{$table}`
        ");

        if (!$res || !($row = $res->fetch_assoc())) {
            return;
        }

        $next = ((int)$row['max_id']) + 1;
        if ($next < 1) $next = 1;

        $_database->query("
            ALTER TABLE `{$table}`
            AUTO_INCREMENT = {$next}
        ");

        $steps_log[] = "<div class='small text-success'>🔧 AUTO_INCREMENT für <b>{$table}</b> auf {$next} gesetzt</div>";
    }
}

/* ============================================================
   1) Modulname-Bereinigung (Core)
============================================================ */

if (!function_exists('nx_cleanup_duplicate_modulname_core')) {

    function nx_cleanup_duplicate_modulname_core(DatabaseMigrationHelper $migrator): void
    {
        global $_database, $steps_log;

        $targets = [
            'system_update_history',
            'settings'
        ];

        foreach ($targets as $table) {

            $exists = $_database->query("SHOW TABLES LIKE '{$table}'");
            if (!$exists || !$exists->num_rows) continue;

            $colCheck = $_database->query("SHOW COLUMNS FROM `{$table}` LIKE 'modulname'");
            if (!$colCheck || !$colCheck->num_rows) continue;

            $idRes = $_database->query("
                SHOW COLUMNS FROM `{$table}`
                WHERE Extra LIKE '%auto_increment%'
            ");
            if (!$idRes || !$idRes->num_rows) continue;

            $idCol = $idRes->fetch_assoc()['Field'];

            $_database->query("
                DELETE FROM `{$table}`
                WHERE modulname IS NULL OR TRIM(modulname) = ''
            ");

            $res = $_database->query("
                SELECT modulname
                FROM `{$table}`
                GROUP BY BINARY TRIM(modulname)
                HAVING COUNT(*) > 1
            ");

            while ($row = $res->fetch_assoc()) {

                $mod = $_database->real_escape_string(trim($row['modulname']));

                $keepID = (int)$_database->query("
                    SELECT {$idCol}
                    FROM `{$table}`
                    WHERE TRIM(BINARY modulname) = '{$mod}'
                    ORDER BY {$idCol} ASC
                    LIMIT 1
                ")->fetch_assoc()[$idCol];

                $_database->query("
                    DELETE FROM `{$table}`
                    WHERE TRIM(BINARY modulname) = '{$mod}'
                      AND {$idCol} != {$keepID}
                ");
            }
        }

        $steps_log[] = "<div class='alert alert-success small'>✅ Modulname-Bereinigung abgeschlossen</div>";
    }
}

nx_cleanup_duplicate_modulname_core($migrator);

/* ============================================================
   2) system_update_history – Tabelle
============================================================ */

// 🔥 Alte History-Tabelle IMMER entfernen (falls vorhanden)
$migrator->run("
DROP TABLE IF EXISTS system_update_history
");

// ✅ Tabelle neu anlegen (definierte, saubere Struktur)
$migrator->run("
CREATE TABLE system_update_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    channel ENUM('stable','beta','dev') NOT NULL,
    build INT NOT NULL DEFAULT 1,
    installed_at INT NOT NULL,
    installed_by INT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,

    UNIQUE KEY uniq_update (version, channel, build),
    INDEX idx_installed_at (installed_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
");

/* ============================================================
   3) settings.update_channel
============================================================ */

$res = $_database->query("
    SHOW COLUMNS FROM system_update_history LIKE 'from_version'
");

if ($res && $res->num_rows) {
    $migrator->run("
        ALTER TABLE system_update_history
        DROP COLUMN from_version
    ");
}

$res = $_database->query("SHOW COLUMNS FROM settings LIKE 'update_channel'");
if (!$res || !$res->num_rows) {

    $migrator->run("
        ALTER TABLE settings
        ADD COLUMN update_channel
        ENUM('stable','beta','dev')
        NOT NULL DEFAULT 'stable'
    ");

    $steps_log[] = "<div class='alert alert-info small'>➕ settings.update_channel hinzugefügt</div>";
}


/* ============================================================
   4) History korrekt setzen (REIHENFOLGE!)
============================================================ */

$installedAt = time(); // Fallback: jetzt

$res = $_database->query("
    SELECT registerdate
    FROM users
    WHERE userID = 1
    LIMIT 1
");

if ($res && ($row = $res->fetch_assoc())) {

    if (
        !empty($row['registerdate']) &&
        $row['registerdate'] !== '0000-00-00 00:00:00'
    ) {
        $ts = strtotime($row['registerdate']);
        if ($ts !== false && $ts > 0) {
            $installedAt = $ts;
        }
    }
}




/* NULL → 1.0.2 */
$migrator->run("
INSERT IGNORE INTO system_update_history
(version, channel, build, installed_at, installed_by, success, notes)
VALUES
('1.0.2','stable',1,{$installedAt},1,1,'Erstinstallation von Nexpell')
");


/* 1.0.2 → 1.0.2.1 */
$migrator->run("
INSERT IGNORE INTO system_update_history
(version, channel, build, installed_at, installed_by, success, notes)
VALUES
('1.0.2.1','stable',1,UNIX_TIMESTAMP(),1,1,
 'Vorbereitung für den neuen Nexpell-Core-Updaters')
");


/* 1.0.2.1 → 1.0.2.2 (ZULETZT!) */
$migrator->run("
INSERT IGNORE INTO system_update_history
(version, channel, build, installed_at, installed_by, success, notes)
VALUES
('1.0.2.2','stable',1,UNIX_TIMESTAMP(),1,1,
 'Einführung des neuen Nexpell-Core-Updaters')
");






$steps_log[] = "<div class='alert alert-success'>🎉 Migration 1.0.2.1 erfolgreich abgeschlossen</div>";
