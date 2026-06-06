<?php
declare(strict_types=1);

use nexpell\CMSDatabaseMigration;

return function (CMSDatabaseMigration $m): void {

    /* ============================================================
       1) DUPLIKATE BEREINIGEN (settings_plugins_installed)
       → behält den NEUESTEN Eintrag pro modulname
    ============================================================ */

    $m->run("
        DELETE t1
        FROM settings_plugins_installed t1
        INNER JOIN settings_plugins_installed t2
            ON t1.modulname = t2.modulname
           AND t1.id < t2.id
    ");

    $m->log("🧹 Doppelte Plugin-Einträge bereinigt (ältere entfernt)");

    /* ============================================================
       2) UNIQUE INDEX PRÜFEN & SETZEN (modulname)
    ============================================================ */

    $res = $m->query("
        SELECT COUNT(*) AS cnt
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'settings_plugins_installed'
          AND INDEX_NAME   = 'uniq_modulname'
    ");

    $row = $res->fetch_assoc();

    if ((int)$row['cnt'] === 0) {
        $m->run("
            ALTER TABLE settings_plugins_installed
            ADD UNIQUE KEY uniq_modulname (modulname)
        ");
        $m->log("🔐 UNIQUE-Key uniq_modulname gesetzt");
    } else {
        $m->log("ℹ️ UNIQUE-Key uniq_modulname existiert bereits");
    }

    /* ============================================================
       3) password_reset_attempts
       (Rate-Limit pro IP)
    ============================================================ */

    $m->run("
        CREATE TABLE IF NOT EXISTS password_reset_attempts (
            id INT(11) NOT NULL AUTO_INCREMENT,
            ip VARCHAR(45) NOT NULL,
            attempts INT(11) NOT NULL DEFAULT 1,
            last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ip (ip)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_general_ci
    ");

    $m->log("🔒 Tabelle password_reset_attempts erstellt");

    /* ============================================================
       4) password_resets
       (Token-basierter Passwort-Reset)
    ============================================================ */

    $m->run("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT(11) NOT NULL AUTO_INCREMENT,
            userID INT(11) NOT NULL,
            token CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_token (token),
            KEY idx_userID (userID)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_general_ci
    ");

    $m->log("🔐 Tabelle password_resets erstellt");

    /* ============================================================
       DONE
    ============================================================ */

    $m->log("🎉 Migration 1.0.3.1 erfolgreich abgeschlossen");
};
