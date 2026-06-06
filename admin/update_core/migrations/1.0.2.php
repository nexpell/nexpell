<?php
// Migration 1.1.0_core (Offline SQL-Diff)
declare(strict_types=1);

require_once __DIR__ . '/../../../system/classes/DatabaseMigrationHelper.php';
use nexpell\DatabaseMigrationHelper;

global $_database;
if (!isset($migrator) || !$migrator instanceof DatabaseMigrationHelper) {
    $migrator = new DatabaseMigrationHelper($_database);
}


if (!function_exists('nx_cleanup_duplicate_modulname_core')) {
    /**
     * 🧹 Löscht doppelte oder leere modulname-Einträge in allen Tabellen, die dieses Feld besitzen.
     *    Beibehaltung immer des ältesten (kleinsten ID-Wertes).
     *    Erkennt Duplikate zuverlässig auch bei utf8mb4_general_ci Collation.
     */
    function nx_cleanup_duplicate_modulname_core(DatabaseMigrationHelper $migrator): void {
        global $_database, $steps_log;

        // Alle Tabellen, die modulname besitzen sollen
        $targets = [
            'navigation_dashboard_links',
            'navigation_dashboard_categories',
            'navigation_website_main',
            'settings_plugins',
            'settings_widgets_positions',
            'user_roles'
        ];

        foreach ($targets as $table) {

            // Prüfen ob Tabelle existiert
            $exists = $_database->query("SHOW TABLES LIKE '$table'");
            if (!$exists || !$exists->num_rows) continue;

            // Prüfen ob modulname-Spalte existiert
            $colCheck = $_database->query("SHOW COLUMNS FROM `$table` LIKE 'modulname'");
            if (!$colCheck || !$colCheck->num_rows) continue;

            // ID-Spalte erkennen
            $idRes = $_database->query("
                SHOW COLUMNS FROM `$table`
                WHERE Extra LIKE '%auto_increment%'
                OR Field IN ('id','ID','catID','linkID','mnavID','pluginID','roleID','posID')
            ");
            if (!$idRes || $idRes->num_rows === 0) continue;
            $idCol = $idRes->fetch_assoc()['Field'];

            $deletedTotal = 0;

            // 1️⃣ Leere modulname löschen
            $_database->query("DELETE FROM `$table` WHERE modulname IS NULL OR TRIM(modulname) = ''");
            $deletedEmpty = $_database->affected_rows;
            if ($deletedEmpty > 0) {
                $deletedTotal += $deletedEmpty;
                $steps_log[] = "<div class='alert alert-danger small py-1 my-1'>🧹 <b>$table</b>: $deletedEmpty leere modulname-Einträge gelöscht.</div>";
            }

            // 2️⃣ Alle Duplikate abrufen – original Werte verwenden
            $res = $_database->query("
                SELECT modulname
                FROM `$table`
                WHERE TRIM(modulname) != ''
                GROUP BY TRIM(BINARY modulname)
                HAVING COUNT(*) > 1
            ");

            if (!$res || $res->num_rows === 0) {
                $steps_log[] = "<div class='text-muted small'>ℹ️ Keine Duplikate in <b>$table</b>.</div>";
                continue;
            }

            // 3️⃣ Doppelte pro Name löschen
            while ($row = $res->fetch_assoc()) {
                $mod = $_database->real_escape_string(trim($row['modulname']));

                // Älteste ID holen
                $keepRes = $_database->query("
                    SELECT $idCol
                    FROM `$table`
                    WHERE TRIM(BINARY modulname) = '$mod'
                    ORDER BY $idCol ASC
                    LIMIT 1
                ");
                if (!$keepRes || !$keepRes->num_rows) continue;
                $keepID = (int)$keepRes->fetch_assoc()[$idCol];

                // 🔥 Jetzt löschen (alle außer älteste ID)
                $_database->query("
                    DELETE FROM `$table`
                    WHERE TRIM(BINARY modulname) = '$mod'
                    AND $idCol != $keepID
                ");
                $deleted = $_database->affected_rows;
                if ($deleted > 0) {
                    $deletedTotal += $deleted;
                    $steps_log[] = "<div class='alert alert-warning small py-1 my-1'>
                        🧹 <b>$table</b>: $deleted doppelte Einträge für modulname='$mod' gelöscht (ältester ID=$keepID behalten).
                    </div>";
                }
            }

            if ($deletedTotal > 0) {
                $steps_log[] = "<div class='alert alert-success small py-1 my-1'>✅ <b>$table</b>: $deletedTotal modulname-Duplikate entfernt.</div>";
            } else {
                $steps_log[] = "<div class='text-muted small'>ℹ️ <b>$table</b> war bereits sauber.</div>";
            }
        }

        $steps_log[] = "<div class='alert alert-success small py-1 my-1'>✅ Modulname-Bereinigung abgeschlossen – alle Tabellen überprüft.</div>";
    }
}


nx_cleanup_duplicate_modulname_core($migrator);

// --- SQL Änderungen ---
$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `filename` text NOT NULL,
  `description` text,
  `createdby` int(11) NOT NULL DEFAULT '0',
  `createdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `banned_ips` (
  `banID` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `deltime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `userID` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`banID`),
  KEY `userID` (`userID`),
  CONSTRAINT `fk_userID` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `captcha` (
  `hash` VARCHAR(255) NOT NULL,
  `captcha` INT(11) NOT NULL DEFAULT '0',
  `deltime` INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS comments (
  commentID INT(11) NOT NULL AUTO_INCREMENT,
  plugin VARCHAR(50) NOT NULL,
  itemID INT(11) NOT NULL,
  userID INT(11) NOT NULL,
  comment TEXT NOT NULL,
  date DATETIME NOT NULL DEFAULT current_timestamp,
  parentID INT(11) DEFAULT 0,
  modulname varchar(100) NOT NULL,
  PRIMARY KEY (commentID),
  KEY plugin_item (plugin, itemID),
  KEY userID (userID),
  CONSTRAINT fk_global_comments_user FOREIGN KEY (userID) REFERENCES users (userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `contact` (
  `contactID` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `email` (
  `emailID` int(1) NOT NULL,
  `user` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `host` varchar(255) NOT NULL,
  `port` int(5) NOT NULL,
  `debug` int(1) NOT NULL,
  `auth` int(1) NOT NULL,
  `html` int(1) NOT NULL,
  `smtp` int(1) NOT NULL,
  `secure` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `email` (`emailID`, `user`, `password`, `host`, `port`, `debug`, `auth`, `html`, `smtp`, `secure`)
VALUES (1, '', '', '', 25, 0, 0, 1, 0, 0)
ON DUPLICATE KEY UPDATE
  `user` = VALUES(`user`),
  `password` = VALUES(`password`),
  `host` = VALUES(`host`),
  `port` = VALUES(`port`),
  `debug` = VALUES(`debug`),
  `auth` = VALUES(`auth`),
  `html` = VALUES(`html`),
  `smtp` = VALUES(`smtp`),
  `secure` = VALUES(`secure`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `failed_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL DEFAULT current_timestamp,
  `status` enum('failed','blocked') DEFAULT 'failed',
  `reason` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userID` (`userID`),
  CONSTRAINT `fk_failed_login_user` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS link_clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plugin VARCHAR(50),
    itemID INT,
    url TEXT,
    clicked_at DATETIME,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `navigation_dashboard_categories` (
  `catID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `modulname` varchar(255) NOT NULL,
  `fa_name` varchar(255) NOT NULL DEFAULT '',
  `sort_art` int(11) DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`catID`),
  UNIQUE KEY `modulname` (`modulname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `navigation_dashboard_categories` (`catID`, `name`, `modulname`, `fa_name`, `sort_art`, `sort`)
VALUES (1, '[[lang:de]]System & Einstellungen[[lang:en]]System & Settings[[lang:it]]Sistema e Impostazioni', 'cat_system', 'bi bi-gear', 0, 1),
(2, '[[lang:de]]Statistiken[[lang:en]]Statistics[[lang:it]]Statistiche', 'cat_statistics', 'bi bi-bar-chart-line', 0, 2),
(3, '[[lang:de]]Benutzer & Rollen[[lang:en]]Users & Roles[[lang:it]]Utenti e Ruoli', 'cat_users', 'bi bi-person', 0, 3),
(4, '[[lang:de]]Sicherheit[[lang:en]]Security[[lang:it]]Sicurezza', 'cat_security', 'bi bi-shield-lock', 0, 4),
(5, '[[lang:de]]Teamverwaltung[[lang:en]]Team Management[[lang:it]]Gestione Team', 'cat_team', 'bi bi-people', 0, 5),
(6, '[[lang:de]]Design & Layout[[lang:en]]Design & Layout[[lang:it]]Design e Layout', 'cat_design', 'bi bi-layout-text-window-reverse', 0, 6),
(7, '[[lang:de]]Plugins & Erweiterungen[[lang:en]]Plugins & Extensions[[lang:it]]Plugin ed Estensioni', 'cat_plugins', 'bi bi-puzzle', 0, 7),
(8, '[[lang:de]]Webinhalte[[lang:en]]Website Content[[lang:it]]Contenuti Web', 'cat_content', 'bi bi-card-checklist', 0, 8),
(9, '[[lang:de]]Medien & Projekte[[lang:en]]Media & Projects[[lang:it]]Media e Progetti', 'cat_media', 'bi bi-image', 0, 9),
(10, '[[lang:de]]Header & Slider[[lang:en]]Header & Slider[[lang:it]]Header e Slider', 'cat_slider_header', 'bi bi-fast-forward-btn', 0, 10),
(11, '[[lang:de]]Game & Voice Tools[[lang:en]]Game & Voice Tools[[lang:it]]Game e Voice Tools', 'cat_tools_game', 'bi bi-controller', 0, 11),
(12, '[[lang:de]]Social Media[[lang:en]]Social Media[[lang:it]]Social Media', 'cat_social', 'bi bi-steam', 0, 12),
(13, '[[lang:de]]Downloads & Partner[[lang:en]]Downloads & Partners[[lang:it]]Download e Sponsor', 'cat_partners', 'bi bi-link', 0, 13)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `modulname` = VALUES(`modulname`),
  `fa_name` = VALUES(`fa_name`),
  `sort_art` = VALUES(`sort_art`),
  `sort` = VALUES(`sort`);
SQL
);









$steps_log[] = "<div class='fw-bold text-primary mb-2'>🧹 Starte vollständige Navigation-Bereinigung...</div>";

try {

    /* -------------------------------------------------------------
     * 0. UNSICHTBARE ZEICHEN BEREINIGEN
     * ------------------------------------------------------------- */
    $steps_log[] = "<div class='small text-info'>🔧 Entferne Zero-Width, NBSP und Sonderzeichen aus modulname…</div>";

    // NBSP, Zero-Width, Encoding-Reste entfernen
    $migrator->run("UPDATE navigation_dashboard_links SET modulname = REPLACE(modulname, CHAR(194 USING utf8mb4), '')");
    $migrator->run("UPDATE navigation_dashboard_links SET modulname = REPLACE(modulname, CHAR(160 USING utf8mb4), '')");
    $migrator->run("UPDATE navigation_dashboard_links SET modulname = REPLACE(modulname, CHAR(0xE2808B USING utf8mb4), '')");

    // Trim
    $migrator->run("UPDATE navigation_dashboard_links SET modulname = TRIM(BOTH ' ' FROM modulname)");
    $migrator->run("UPDATE navigation_dashboard_links SET modulname = TRIM(modulname)");

    $steps_log[] = "<div class='small text-success'>✔ Unsichtbare Zeichen erfolgreich entfernt.</div>";


    /* -------------------------------------------------------------
     * 1. LEERE modulname entfernen
     * ------------------------------------------------------------- */
    $migrator->run("DELETE FROM navigation_dashboard_links WHERE modulname IS NULL OR TRIM(modulname) = ''");
    $delEmpty = $migrator->affected_rows ?? 0;

    if ($delEmpty > 0) {
        $steps_log[] = "<div class='small text-warning'>🧹 $delEmpty Einträge ohne modulname gelöscht.</div>";
    }


    /* -------------------------------------------------------------
     * 2. ECHTE DUPLIKATE LOKAL BEREINIGEN
     * ------------------------------------------------------------- */
    $steps_log[] = "<div class='small text-info'>🔍 Suche nach echten modulname-Duplikaten…</div>";

    $res = $migrator->query("
        SELECT TRIM(BINARY modulname) AS m, COUNT(*) AS c
        FROM navigation_dashboard_links
        WHERE TRIM(modulname) <> ''
        GROUP BY TRIM(BINARY modulname)
        HAVING c > 1
    ");

    if ($res && $res->num_rows > 0) {

        while ($row = $res->fetch_assoc()) {

            $mod = $row['m'];
            $modEsc = $migrator->escape($mod);

            // Ältesten Datensatz ermitteln
            $keepRes = $migrator->query("
                SELECT linkID
                FROM navigation_dashboard_links
                WHERE TRIM(BINARY modulname) = '{$modEsc}'
                ORDER BY linkID ASC
                LIMIT 1
            ");

            $keepID = (int)$keepRes->fetch_assoc()['linkID'];

            // Alle neueren löschen
            $migrator->run("
                DELETE FROM navigation_dashboard_links
                WHERE TRIM(BINARY modulname) = '{$modEsc}'
                AND linkID > {$keepID}
            ");

            $deleted = $migrator->affected_rows ?? 0;

            $steps_log[] =
                "<div class='small text-warning'>🧹 modulname='<b>{$mod}</b>': $deleted Duplikate gelöscht (behalte ID $keepID)</div>";
        }

        $steps_log[] = "<div class='small text-success'>✔ Alle modulname-Duplikate erfolgreich bereinigt.</div>";

    } else {
        $steps_log[] = "<div class='small text-muted'>ℹ️ Keine Duplikate gefunden.</div>";
    }


    /* -------------------------------------------------------------
     * 3. FINALER CHECK VOR DEM INSERT
     * ------------------------------------------------------------- */
    $final = $migrator->query("
        SELECT TRIM(BINARY modulname) AS m, COUNT(*) AS c
        FROM navigation_dashboard_links
        WHERE TRIM(modulname) <> ''
        GROUP BY TRIM(BINARY modulname)
        HAVING c > 1
    ");

    if ($final && $final->num_rows > 0) {
        $steps_log[] =
            "<div class='alert alert-danger small'>❌ ACHTUNG: modulname-Konflikte vor dem Insert vorhanden!</div>";
    } else {
        $steps_log[] =
            "<div class='alert alert-success small'>✔ Navigation ist vor dem Insert duplikatfrei.</div>";
    }


    /* -------------------------------------------------------------
     * 4. ENTRIES EINSETZEN / AKTUALISIEREN
     * ------------------------------------------------------------- */
    $steps_log[] = "<div class='small text-info'>📥 Füge Navigationseinträge ein oder aktualisiere sie…</div>";

    $migrator->run(<<<'SQL'
INSERT INTO `navigation_dashboard_links` (`catID`, `modulname`, `name`, `url`, `sort`)
VALUES
(1,'ac_overview','[[lang:de]]Webserver-Info[[lang:en]]Webserver Info[[lang:it]]Informazioni Sul Sito','admincenter.php?site=overview',1),
(1,'ac_settings','[[lang:de]]Allgemeine Einstellungen[[lang:en]]General Settings[[lang:it]]Impostazioni Generali','admincenter.php?site=settings',2),
(1,'ac_dashboard_navigation','[[lang:de]]Admincenter Navigation[[lang:en]]Admincenter Navigation[[lang:it]]Menu Navigazione Admin','admincenter.php?site=dashboard_navigation',3),
(1,'ac_email','[[lang:de]]E-Mail[[lang:en]]E-Mail[[lang:it]]E-Mail','admincenter.php?site=email',4),
(1,'ac_contact','[[lang:de]]Kontakte[[lang:en]]Contacts[[lang:it]]Contatti','admincenter.php?site=contact',5),
(1,'ac_database','[[lang:de]]Datenbank[[lang:en]]Database[[lang:it]]Database','admincenter.php?site=database',6),
(1,'ac_languages','[[lang:de]]Sprachen verwalten[[lang:en]]Manage Languages[[lang:it]]Gestisci lingue','admincenter.php?site=languages',7),
(1,'ac_editlang','[[lang:de]]Spracheditor[[lang:en]]Language Editor[[lang:it]]Editor di Linguaggi','admincenter.php?site=editlang',8),
(1,'ac_seo_meta','[[lang:de]]SEO-Metadaten[[lang:en]]SEO Metadata[[lang:it]]Metadati SEO','admincenter.php?site=seo_meta',9),
(1,'ac_update_core','[[lang:de]]Core aktualisieren[[lang:en]]Update Core[[lang:it]]Aggiorna Core','admincenter.php?site=update_core',10),
(2,'ac_statistic','[[lang:de]]Seiten Statistiken[[lang:en]]Page Statistics[[lang:it]]Pagina delle Statistiche','admincenter.php?site=statistic',1),
(2,'ac_visitor_statistic','[[lang:de]]Besucher Statistiken[[lang:en]]Visitor Statistics[[lang:it]]Statistiche Visitatori','admincenter.php?site=visitor_statistic',2),
(2,'ac_db_stats','[[lang:de]]Besucher / Seitenzugriffe[[lang:en]]Visitors / Pageviews[[lang:it]]Visitatori / Visualizzazioni di pagina','admincenter.php?site=db_stats',3),
(3,'ac_user_roles','[[lang:de]]Registrierte Benutzer und Rollen[[lang:en]]Registered Users and Roles[[lang:it]]Utenti registrati e ruoli','admincenter.php?site=user_roles',1),
(4,'ac_security_overview','[[lang:de]]Admin Security[[lang:en]]Admin Security[[lang:it]]Sicurezza Admin','admincenter.php?site=security_overview',1),
(4,'ac_log_viewer','[[lang:de]]Zugriffsprotokoll[[lang:en]]Access Log Viewer[[lang:it]]Visualizzatore Log di Accesso','admincenter.php?site=log_viewer',1),
(6,'ac_webside_navigation','[[lang:de]]Webseiten Navigation[[lang:en]]Website Navigation[[lang:it]]Menu Navigazione Web','admincenter.php?site=webside_navigation',1),
(6,'ac_theme_installer','[[lang:de]]Themes Installer[[lang:en]]Themes Installer[[lang:it]]Installazione Themes','admincenter.php?site=theme_installer',2),
(6,'ac_theme','[[lang:de]]Themes[[lang:en]]Themes[[lang:it]]Temi','admincenter.php?site=theme',3),
(6,'ac_stylesheet','[[lang:de]]Stylesheet bearbeiten[[lang:en]]Edit stylesheet[[lang:it]]Modifica stylesheet','admincenter.php?site=edit_stylesheet',4),
(6,'ac_headstyle','[[lang:de]]Kopfzeilen-Stil[[lang:en]]Head Style[[lang:it]]Stile intestazione','admincenter.php?site=headstyle',5),
(6,'ac_startpage','[[lang:de]]Startseite[[lang:en]]Start Page[[lang:it]]Pagina Principale','admincenter.php?site=settings_startpage',6),
(6,'ac_static','[[lang:de]]Statische Seiten[[lang:en]]Static Pages[[lang:it]]Pagine Statiche','admincenter.php?site=settings_static',7),
(6,'ac_imprint','[[lang:de]]Impressum[[lang:en]]Imprint[[lang:it]]Impronta Editoriale','admincenter.php?site=settings_imprint',8),
(6,'ac_privacy_policy','[[lang:de]]Datenschutz-Bestimmungen[[lang:en]]Privacy Policy[[lang:it]]Informativa sulla Privacy','admincenter.php?site=settings_privacy_policy',9),
(7,'ac_plugin_manager','[[lang:de]]Plugin Manager[[lang:en]]PluginManager[[lang:it]]Gestore di Plugin','admincenter.php?site=plugin_manager',1),
(7,'ac_plugin_widgets_setting','[[lang:de]]Widgets verwalten[[lang:en]]Manage widgets[[lang:it]]Gestire i widget','admincenter.php?site=plugin_widgets_setting',2),
(7,'ac_plugin_installer','[[lang:de]]Plugin Installer[[lang:en]]Plugin Installer[[lang:it]]Installazione Plugin','admincenter.php?site=plugin_installer',3),
(8,'footer_easy','[[lang:de]]Footer Easy[[lang:en]]Footer Easy[[lang:it]]Piè di pagina Easy','admincenter.php?site=admin_footer_easy',1)
ON DUPLICATE KEY UPDATE
  catID = VALUES(catID),
  name  = VALUES(name),
  url   = VALUES(url),
  sort  = VALUES(sort);
SQL
);

    $steps_log[] = "<div class='small text-success'>✔ Navigationseinträge wurden eingefügt oder aktualisiert.</div>";


    /* -------------------------------------------------------------
     * 5. ZWEITER CLEANUP – NACH INSERT
     * ------------------------------------------------------------- */
    $steps_log[] = "<div class='small text-info'>🔁 Zweiter Cleanup-Durchlauf…</div>";

    $migrator->run("
DELETE n1 FROM navigation_dashboard_links n1
JOIN navigation_dashboard_links n2
  ON TRIM(BINARY n1.modulname) = TRIM(BINARY n2.modulname)
 AND n1.linkID > n2.linkID
");

    $steps_log[] = "<div class='small text-success'>✔ Navigation endgültig duplikatfrei.</div>";


    /* -------------------------------------------------------------
     * 6. ENDPRÜFUNG ALLER TABELLEN
     * ------------------------------------------------------------- */
    $steps_log[] = "<div class='small text-info'>🔍 Prüfe weitere Tabellen auf modulname-Duplikate…</div>";

    $tables_to_check = [
        'navigation_dashboard_categories',
        'navigation_dashboard_links',
        'user_role_admin_navi_rights'
    ];

    foreach ($tables_to_check as $table) {

        $sql_check = "
            SELECT TRIM(BINARY modulname) AS m, COUNT(*) AS c 
            FROM {$table}
            WHERE TRIM(modulname) <> ''
            GROUP BY TRIM(BINARY modulname)
            HAVING c > 1
        ";

        $res = $migrator->query($sql_check);

        if ($res && mysqli_num_rows($res) > 0) {
            $steps_log[] = "<div class='small text-danger'>⚠ Warnung: In <b>{$table}</b> existieren modulname-Duplikate!</div>";
        } else {
            $steps_log[] = "<div class='small text-success'>✔ {$table} hat keine modulname-Duplikate.</div>";
        }
    }

} catch (Throwable $e) {

    $steps_log[] = "<div class='text-danger'>❌ Ausnahme: " . htmlspecialchars($e->getMessage()) . "</div>";

}

$steps_log[] = "<div class='fw-bold text-success mt-2'>🎉 Navigation – Migration abgeschlossen.</div>";

















$steps_log[] = "<div class='fw-bold mb-2 text-primary'>🧭 Navigation – navigation_website_main</div>";

try {

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `navigation_website_main` (
  `mnavID` int(11) NOT NULL AUTO_INCREMENT,
  `modulname` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '#',
  `default` tinyint(1) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0,
  `isdropdown` tinyint(1) NOT NULL DEFAULT 0,
  `windows` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`mnavID`),
  UNIQUE KEY `unique_modulname` (`modulname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
    );



    if (!$migrator->columnExists('navigation_website_main', 'modulname')) {
        $migrator->run("
            ALTER TABLE `navigation_website_main`
            ADD COLUMN `modulname` VARCHAR(255) NOT NULL DEFAULT '' AFTER `mnavID`;
        ");
        $steps_log[] = "<div class='small text-success'>➕ Spalte <code>modulname</code> ergänzt.</div>";
    }


    $checkEmpty = $_database->query("
        SELECT mnavID
        FROM `navigation_website_main`
        WHERE modulname = ''
    ");

    if ($checkEmpty && $checkEmpty->num_rows > 1) {
        
        $migrator->run("
            DELETE t1 FROM navigation_website_main t1
            JOIN navigation_website_main t2
              ON t1.modulname = t2.modulname
             AND t1.mnavID > t2.mnavID
            WHERE t1.modulname = '';
        ");

        $steps_log[] = "<div class='small text-warning'>⚠️ Mehrere <code>modulname=''</code> gefunden – bereinigt.</div>";
    }

    
    $migrator->run("
        UPDATE navigation_website_main
        SET modulname = CONCAT('auto_', mnavID)
        WHERE modulname = '';
    ");

    $steps_log[] = "<div class='small text-info'>🔧 Leere modulname automatisch korrigiert.</div>";


    $checkIndex = $_database->query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'navigation_website_main'
          AND INDEX_NAME = 'unique_modulname'
    ");

    if ($checkIndex && $checkIndex->num_rows === 0) {

        
        $migrator->run("
            UPDATE navigation_website_main
            SET modulname = CONCAT('fix_', mnavID)
            WHERE modulname IN (
                SELECT modulname FROM (
                    SELECT modulname 
                    FROM navigation_website_main 
                    GROUP BY modulname 
                    HAVING COUNT(*) > 1
                ) AS tmp
            );
        ");

        
        $migrator->run("
            ALTER TABLE `navigation_website_main`
            ADD UNIQUE KEY `unique_modulname` (`modulname`);
        ");

        $steps_log[] = "<div class='small text-info'>🔑 UNIQUE-Index <code>unique_modulname</code> hinzugefügt.</div>";
    }



$migrator->run(<<<'SQL'
INSERT INTO `navigation_website_main` 
(`mnavID`, `modulname`, `name`, `url`, `default`, `sort`, `isdropdown`, `windows`)
VALUES 
(1, 'nav_home',      '[[lang:de]]Aktuelles[[lang:en]]News[[lang:it]]Notizie', '#', 1, 1, 1, 1),
(2, 'nav_about',     '[[lang:de]]Über uns[[lang:en]]About Us[[lang:it]]Chi siamo', '#', 1, 2, 1, 1),
(3, 'nav_community', '[[lang:de]]COMMUNITY[[lang:en]]COMMUNITY[[lang:it]]COMMUNITY', '#', 1, 3, 1, 1),
(4, 'nav_media',     '[[lang:de]]MEDIEN[[lang:en]]MEDIA[[lang:it]]MEDIA', '#', 1, 4, 1, 1),
(5, 'nav_service',   '[[lang:de]]Service[[lang:en]]Service[[lang:it]]Servizio', '#', 1, 5, 1, 1),
(6, 'nav_network',   '[[lang:de]]Netzwerk[[lang:en]]Network[[lang:it]]Rete', '#', 1, 6, 1, 1)
ON DUPLICATE KEY UPDATE
  `modulname` = VALUES(`modulname`),
  `name` = VALUES(`name`),
  `url` = VALUES(`url`),
  `default` = VALUES(`default`),
  `sort` = VALUES(`sort`),
  `isdropdown` = VALUES(`isdropdown`),
  `windows` = VALUES(`windows`);
SQL
    );

    $steps_log[] = "<div class='small text-success'>✅ Navigationseinträge hinzugefügt/aktualisiert.</div>";



} catch (\Throwable $e) {

    $steps_log[] = "<div class='text-danger small'>
        ❌ Fehler bei navigation_website_main: " . htmlspecialchars($e->getMessage()) . "
    </div>";
}



$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `navigation_website_sub` (
  `snavID` int(11) NOT NULL AUTO_INCREMENT,
  `mnavID` int(11) NOT NULL DEFAULT 0,
  `modulname` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '#',
  `sort` int(11) NOT NULL DEFAULT 0,
  `indropdown` tinyint(1) NOT NULL DEFAULT 1,
  `last_modified` datetime DEFAULT NULL,
  PRIMARY KEY (`snavID`),
  UNIQUE KEY `unique_modulname` (`modulname`),
  KEY `idx_mnavID` (`mnavID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `plugins_footer_easy` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_number` tinyint(1) NOT NULL COMMENT '1–5',
  `copyright_link_name` varchar(255) NOT NULL DEFAULT '',
  `copyright_link` varchar(255) NOT NULL DEFAULT '',
  `new_tab` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `link_number` (`link_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `plugins_footer_easy` (`link_number`, `copyright_link_name`, `copyright_link`, `new_tab`)
VALUES (1, '[[lang:de]]Impressum[[lang:en]]Imprint[[lang:it]]Impronta Editoriale', 'index.php?site=imprint', 0),
(2, '[[lang:de]]Datenschutz[[lang:en]]Privacy Policy[[lang:it]]Informativa sulla Privacy', 'index.php?site=privacy_policy', 0),
(3, '[[lang:de]]Kontakt[[lang:en]]Contact[[lang:it]]Contatti', 'index.php?site=contact', 0),
(4, '', '', 0),
(5, '', '', 0)
ON DUPLICATE KEY UPDATE
  `link_number` = VALUES(`link_number`),
  `copyright_link_name` = VALUES(`copyright_link_name`),
  `copyright_link` = VALUES(`copyright_link`),
  `new_tab` = VALUES(`new_tab`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS ratings (
  ratingID INT(11) NOT NULL AUTO_INCREMENT,
  plugin VARCHAR(50) NOT NULL,
  itemID INT(11) NOT NULL,
  userID INT(11) NOT NULL,
  rating TINYINT(4) NOT NULL CHECK (rating BETWEEN 0 AND 10),
  date DATETIME NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (ratingID),
  UNIQUE KEY unique_vote (plugin, itemID, userID),
  KEY userID (userID),
  CONSTRAINT fk_global_ratings_user FOREIGN KEY (userID) REFERENCES users (userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings` (
  `settingID` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `hptitle` VARCHAR(255) NOT NULL,
  `hpurl` VARCHAR(255) NOT NULL,
  `clanname` VARCHAR(255) NOT NULL,
  `clantag` VARCHAR(255) NOT NULL,
  `adminname` VARCHAR(255) NOT NULL,
  `adminemail` VARCHAR(255) NOT NULL CHECK (`adminemail` LIKE '%@%'),
  `since` YEAR NOT NULL DEFAULT '2025',
  `webkey` VARCHAR(255) NOT NULL DEFAULT 'PLACEHOLDER_WEBKEY',
  `seckey` VARCHAR(255) NOT NULL DEFAULT 'PLACEHOLDER_SECKEY',
  `closed` TINYINT(1) NOT NULL DEFAULT 0,
  `default_language` VARCHAR(5) NOT NULL DEFAULT 'de',
  `keywords` TEXT NOT NULL,
  `startpage` VARCHAR(255) NOT NULL,
  `use_seo_urls` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_headstyle_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `selected_style` VARCHAR(64) NOT NULL DEFAULT 'head-style-1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT IGNORE INTO `settings_headstyle_config` (`id`, `selected_style`)
VALUES (1, 'head-boxes-1');
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_imprint` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type` varchar(255) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `represented_by` varchar(255) NOT NULL,
  `tax_id` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `disclaimer` text DEFAULT NULL,
  `address` varchar(255) DEFAULT '',
  `postal_code` varchar(20) DEFAULT '',
  `city` varchar(100) DEFAULT '',
  `register_office` varchar(100) DEFAULT '',
  `register_number` varchar(100) DEFAULT '',
  `vat_id` varchar(50) DEFAULT '',
  `supervisory_authority` varchar(255) DEFAULT '',
  `editor` int(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_languages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `iso_639_1` char(2) NOT NULL COMMENT 'ISO 639-1 language code, z.B. \"en\"',
  `iso_639_2` char(3) DEFAULT NULL COMMENT 'Optional ISO 639-2 code, z.B. \"eng\"',
  `name_en` varchar(100) NOT NULL COMMENT 'Language name in English, z.B. \"English\"',
  `name_native` varchar(100) DEFAULT NULL COMMENT 'Native language name, z.B. \"Deutsch\"',
  `name_de` varchar(100) DEFAULT NULL COMMENT 'Language name in German, z.B. \"Deutsch\"',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is the language active for selection',
  `flag` varchar(255) DEFAULT NULL COMMENT 'Pfad oder CSS-Klasse für Flagge, z.B. \"/admin/images/flags/de.svg\" oder \"fi fi-de\"',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp,
  PRIMARY KEY (`id`),
  UNIQUE KEY `iso_639_1` (`iso_639_1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `settings_languages` (`id`, `iso_639_1`, `iso_639_2`, `name_en`, `name_native`, `name_de`, `active`, `flag`, `created_at`, `updated_at`)
VALUES (1, 'en', 'eng', 'English', 'English', 'Englisch', 1, '/admin/images/flags/gb.svg', NOW(), NULL),
(2, 'de', 'deu', 'German', 'Deutsch', 'Deutsch', 1, '/admin/images/flags/de.svg', NOW(), NULL),
(3, 'it', 'ita', 'Italian', 'Italiano', 'Italienisch', 1, '/admin/images/flags/it.svg', NOW(), NULL),
(4, 'fr', 'fra', 'French', 'Français', 'Französisch', 0, '/admin/images/flags/fr.svg', NOW(), NULL),
(5, 'es', 'spa', 'Spanish', 'Español', 'Spanisch', 0, '/admin/images/flags/es.svg', NOW(), NULL),
(6, 'pt', 'por', 'Portuguese', 'Português', 'Portugiesisch', 0, '/admin/images/flags/pt.svg', NOW(), NULL),
(7, 'pl', 'pol', 'Polish', 'Polski', 'Polnisch', 0, '/admin/images/flags/pl.svg', NOW(), NULL),
(8, 'tr', 'tur', 'Turkish', 'Türkçe', 'Türkisch', 0, '/admin/images/flags/tr.svg', NOW(), NULL)
ON DUPLICATE KEY UPDATE
  `iso_639_1` = VALUES(`iso_639_1`),
  `iso_639_2` = VALUES(`iso_639_2`),
  `name_en` = VALUES(`name_en`),
  `name_native` = VALUES(`name_native`),
  `name_de` = VALUES(`name_de`),
  `active` = VALUES(`active`),
  `flag` = VALUES(`flag`),
  `created_at` = VALUES(`created_at`),
  `updated_at` = VALUES(`updated_at`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_plugins` (
  `pluginID` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `modulname` VARCHAR(100) NOT NULL,
  `info` TEXT NOT NULL,
  `admin_file` VARCHAR(255) DEFAULT NULL,
  `activate` TINYINT(1) NOT NULL DEFAULT 1,
  `author` VARCHAR(200) DEFAULT NULL,
  `website` VARCHAR(200) DEFAULT NULL,
  `index_link` VARCHAR(255) DEFAULT NULL,
  `hiddenfiles` TEXT DEFAULT NULL,
  `version` VARCHAR(20) DEFAULT '1.0',
  `path` VARCHAR(255) NOT NULL,
  `status_display` TINYINT(1) NOT NULL DEFAULT 1,
  `plugin_display` TINYINT(1) NOT NULL DEFAULT 1,
  `widget_display` TINYINT(1) NOT NULL DEFAULT 0,
  `delete_display` TINYINT(1) NOT NULL DEFAULT 1,
  `sidebar` ENUM('deactivated','activated','full_activated') NOT NULL DEFAULT 'deactivated',
  UNIQUE KEY `unique_modulname` (`modulname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `settings_plugins` (`pluginID`, `name`, `modulname`, `info`, `admin_file`, `activate`, `author`, `website`, `index_link`, `hiddenfiles`, `version`, `path`, `status_display`, `plugin_display`, `widget_display`, `delete_display`, `sidebar`)
VALUES (1, 'Startpage', 'startpage', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', '', '', '', '', 0, 0, 1, 0, 'full_activated'),
(2, 'Privacy Policy', 'privacy_policy', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'privacy_policy', '', '', '', 0, 0, 1, 0, 'deactivated'),
(3, 'Imprint', 'imprint', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'imprint', '', '', '', 0, 0, 1, 0, 'deactivated'),
(4, 'Static', 'static', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'static', '', '', '', 0, 0, 1, 0, 'deactivated'),
(5, 'Error_404', 'error_404', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'error_404', '', '', '', 0, 0, 1, 0, 'deactivated'),
(6, 'Profile', 'profile', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'profile', '', '', '', 0, 0, 1, 0, 'deactivated'),
(7, 'Login', 'login', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'login', '', '', '', 0, 0, 1, 0, 'deactivated'),
(8, 'Lost Password', 'lostpassword', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'lostpassword', '', '', '', 0, 0, 1, 0, 'deactivated'),
(9, 'Contact', 'contact', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'contact', '', '', '', 0, 0, 1, 0, 'deactivated'),
(10, 'Register', 'register', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'register', '', '', '', 0, 0, 1, 0, 'deactivated'),
(11, 'Edit Profile', 'edit_profile', '[[lang:de]]Kein Plugin. Bestandteil vom System!!![[lang:en]]No plugin. Part of the system!!![[lang:it]]Nessun plug-in. Parte del sistema!!!', '', 1, '', '', 'edit_profile,edit_profile_save', '', '', '', 0, 1, 1, 0, 'deactivated'),
(12, 'Navigation', 'navigation', '[[lang:de]]Mit diesem Plugin könnt ihr euch die Navigation anzeigen lassen.[[lang:en]]With this plugin you can display navigation.[[lang:it]]Con questo plugin puoi visualizzare la Barra di navigazione predefinita.', '', 1, 'T-Seven', 'https://www.nexpell.de', '', '', '0.3', 'includes/plugins/navigation/', 1, 1, 0, 0, 'deactivated'),
(13, 'Footer Easy', 'footer_easy', '[[lang:de]]Mit diesem Plugin könnt ihr einen neuen Footer Easy anzeigen lassen.[[lang:en]]With this plugin you can have a new Footer Easy displayed.[[lang:it]]Con questo plugin puoi visualizzare un nuovo piè di pagina.', 'admin_footer_easy', 1, 'T-Seven', 'https://www.nexpell.de', '', '', '0.1', 'includes/plugins/footer_easy/', 1, 1, 0, 0, 'deactivated')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `modulname` = VALUES(`modulname`),
  `info` = VALUES(`info`),
  `admin_file` = VALUES(`admin_file`),
  `activate` = VALUES(`activate`),
  `author` = VALUES(`author`),
  `website` = VALUES(`website`),
  `index_link` = VALUES(`index_link`),
  `hiddenfiles` = VALUES(`hiddenfiles`),
  `version` = VALUES(`version`),
  `path` = VALUES(`path`),
  `status_display` = VALUES(`status_display`),
  `plugin_display` = VALUES(`plugin_display`),
  `widget_display` = VALUES(`widget_display`),
  `delete_display` = VALUES(`delete_display`),
  `sidebar` = VALUES(`sidebar`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_plugins_installed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `modulname` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `version` varchar(50) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `folder` varchar(255) DEFAULT NULL,
  `installed_date` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_privacy_policy` (
  `privacy_policyID` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `date` DATETIME NOT NULL,
  `privacy_policy_text` mediumtext NOT NULL,
  `editor` int(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
/*$migrator->run(<<<'SQL'
INSERT INTO `settings_privacy_policy` (`privacy_policyID`, `date`, `privacy_policy_text`, `editor`)
VALUES (1, NOW(), '', 1)
ON DUPLICATE KEY UPDATE
  `date` = VALUES(`date`),
  `privacy_policy_text` = VALUES(`privacy_policy_text`),
  `editor` = VALUES(`editor`);
SQL
);*/



if (!isset($migrator)) {
    die("❌ Kein Migrationsobjekt gefunden!");
}

$steps_log[] = "<div class='fw-bold mb-2 text-primary'>🔄 Migration 1.0.2 – settings_seo_meta</div>";

try {

    
    $hasSeoId = $migrator->columnExists('settings_seo_meta', 'seoID');

    if ($hasSeoId) {
        $steps_log[] = "<div class='small text-info'>🧩 Alte Struktur erkannt – migriere Tabelle...</div>";

        
        $migrator->run("RENAME TABLE `settings_seo_meta` TO `settings_seo_meta_old`;");

        
        $migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_seo_meta` (
  `site` varchar(64) NOT NULL,
  `language` varchar(8) NOT NULL DEFAULT 'de',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`site`, `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        
        $migrator->run(<<<'SQL'
INSERT IGNORE INTO `settings_seo_meta` (`site`, `language`, `title`, `description`)
SELECT `site`, `language`, `title`, `description` FROM `settings_seo_meta_old`;
SQL
        );

        
        $migrator->run("DROP TABLE IF EXISTS `settings_seo_meta_old`;");

        $steps_log[] = "<div class='small text-success'>✅ Migration abgeschlossen – alte Spalte <code>seoID</code> entfernt.</div>";

    } else {
        $steps_log[] = "<div class='small text-muted'>ℹ️ Struktur bereits aktuell – prüfe PRIMARY KEY...</div>";

        
        $check = $_database->query("
            SELECT COUNT(*) AS has_primary
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'settings_seo_meta'
              AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ");
        $row = $check ? $check->fetch_assoc() : ['has_primary' => 0];
        $check?->free();

        
        if ((int)$row['has_primary'] > 0) {
            $migrator->run("ALTER TABLE `settings_seo_meta` DROP PRIMARY KEY;");
            $steps_log[] = "<div class='small text-info'>🗑️ PRIMARY KEY entfernt.</div>";
        } else {
            $steps_log[] = "<div class='small text-muted'>ℹ️ Kein PRIMARY KEY vorhanden – übersprungen.</div>";
        }

        // Sicherstellen, dass neuer zusammengesetzter PK existiert
        $migrator->run("ALTER TABLE `settings_seo_meta` ADD PRIMARY KEY (`site`, `language`);");
        $steps_log[] = "<div class='small text-success'>✅ PRIMARY KEY (`site`,`language`) gesetzt.</div>";

        
        $migrator->run("ALTER TABLE `settings_seo_meta` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    $steps_log[] = "<div class='small text-success'>✅ settings_seo_meta erfolgreich aktualisiert.</div>";

} catch (Throwable $e) {
    $steps_log[] = "<div class='text-danger small'>❌ Fehler bei der Migration: " . htmlspecialchars($e->getMessage()) . "</div>";
}


$migrator->run(<<<'SQL'
INSERT INTO `settings_seo_meta` (`site`, `language`, `title`, `description`) VALUES
-- ABOUT
('about', 'de', 'Über uns – Das Team hinter Nexpell', 'Lerne das Team und die Geschichte von Nexpell kennen. Ein modernes Open-Source-CMS für Gamer.'),
('about', 'en', 'About Us – The Team Behind Nexpell', 'Get to know the team and story behind Nexpell. A modern open-source CMS for gamers.'),
('about', 'it', 'Chi siamo – Il team dietro Nexpell', 'Scopri il team e la storia di Nexpell. Un CMS moderno e open-source per gamer.'),

-- ARTICLES / NEWS
('articles', 'de', 'Artikel – Aktuelle Beiträge und News', 'Entdecke spannende Artikel, Neuigkeiten und Hintergrundberichte rund um Nexpell und seine Community.'),
('articles', 'en', 'Articles – Latest Posts and News', 'Discover articles, news and in-depth reports about Nexpell and its community.'),
('articles', 'it', 'Articoli – Ultimi post e notizie', 'Scopri articoli, notizie e approfondimenti sulla community di Nexpell.'),

-- CONTACT
('contact', 'de', 'Kontakt – Nimm Kontakt mit dem Nexpell-Team auf', 'Du hast Fragen oder Feedback? Nutze unser Kontaktformular – wir freuen uns auf deine Nachricht.'),
('contact', 'en', 'Contact – Get in Touch with the Nexpell Team', 'Have questions or feedback? Use our contact form to reach the Nexpell team.'),
('contact', 'it', 'Contatto – Mettiti in contatto con il team Nexpell', 'Hai domande o suggerimenti? Usa il modulo di contatto per scriverci.'),

-- DISCORD
('discord', 'de', 'Nexpell Discord – Community & Support', 'Tritt dem offiziellen Nexpell-Discord bei und erhalte direkten Support vom Team.'),
('discord', 'en', 'Nexpell Discord – Community and Support', 'Join the official Nexpell Discord to connect with the community and get support.'),
('discord', 'it', 'Nexpell Discord – Community e Supporto', 'Unisciti al Discord ufficiale di Nexpell per parlare con la community e ricevere supporto.'),

-- DOWNLOADS
('downloads', 'de', 'Downloads – Erweiterungen für dein Nexpell CMS', 'Lade Module, Themes und Erweiterungen für dein Nexpell CMS herunter.'),
('downloads', 'en', 'Downloads – Extensions for Your Nexpell CMS', 'Download modules, themes and extensions for your Nexpell CMS.'),
('downloads', 'it', 'Download – Estensioni per il tuo CMS Nexpell', 'Scarica moduli, temi ed estensioni per il tuo CMS Nexpell.'),

-- FORUM
('forum', 'de', 'Community Forum – Fragen, Hilfe & Austausch', 'Stelle Fragen und tausche dich mit anderen Nexpell-Nutzern im Forum aus.'),
('forum', 'en', 'Community Forum – Questions, Help & Exchange', 'Ask questions and connect with other Nexpell users in the forum.'),
('forum', 'it', 'Forum della community – Domande, aiuto e confronto', 'Fai domande e confrontati con altri utenti della community.'),

-- GAMETRACKER
('gametracker', 'de', 'Game Server Übersicht – Echtzeit-Serverstatus', 'Überwache deine Gameserver in Echtzeit: Spieler, Karten, Status und mehr.'),
('gametracker', 'en', 'Game Server Overview – Real-Time Server Info', 'Monitor your game servers in real time: players, maps, versions and server status.'),
('gametracker', 'it', 'Panoramica server di gioco – Stato in tempo reale', 'Monitora i tuoi server di gioco in tempo reale: giocatori, mappe e stato del server.'),

-- IMPRINT / LEGAL
('imprint', 'de', 'Impressum – Rechtliche Angaben zu Nexpell', 'Rechtliche Informationen und Verantwortliche gemäß §5 TMG.'),
('imprint', 'en', 'Legal Notice – Company and Legal Information about Nexpell', 'Legal information and responsible parties in accordance with §5 TMG.'),
('imprint', 'it', 'Note legali – Informazioni legali su Nexpell', 'Informazioni legali e responsabili secondo il §5 TMG.'),

-- PRIVACY POLICY
('privacy_policy', 'de', 'Datenschutz – Umgang mit deinen Daten', 'Erfahre, wie wir deine Daten schützen. DSGVO-konform und transparent.'),
('privacy_policy', 'en', 'Privacy Policy – How We Handle Your Data', 'Learn how we protect your data. GDPR-compliant and transparent.'),
('privacy_policy', 'it', 'Privacy – Come trattiamo i tuoi dati', 'Scopri come proteggiamo i tuoi dati in conformità al GDPR.'),

-- SHOUTBOX (RICHTIG KORRIGIERT!)
('shoutbox', 'de', 'Shoutbox – Kurznachrichten deiner Community', 'Poste schnelle Nachrichten und bleibe mit deiner Community verbunden.'),
('shoutbox', 'en', 'Shoutbox – Quick Messages for Your Community', 'Post short messages and stay connected with your community.'),
('shoutbox', 'it', 'Shoutbox – Messaggi rapidi per la tua community', 'Invia messaggi brevi e rimani in contatto con la tua community.'),

-- TODO (RICHTIG KORRIGIERT!)
('todo', 'de', 'TODO – Offene Aufgaben und wichtige To-Dos', 'Behalte einen Überblick über offene Aufgaben und Projektfortschritte.'),
('todo', 'en', 'TODO – Open Tasks and Important To-Dos', 'Keep track of open tasks and ongoing project steps.'),
('todo', 'it', 'TODO – Compiti aperti e cose da fare importanti', 'Tieni traccia dei compiti aperti e dei passaggi pianificati.'),

-- USERLIST (RICHTIG KORRIGIERT!)
('userlist', 'de', 'Mitgliederliste – Alle registrierten Nutzer im Überblick', 'Hier findest du alle Mitglieder der Nexpell-Community mit Profilinformationen.'),
('userlist', 'en', 'Member List – All Registered Users at a Glance', 'See all registered members of the Nexpell community with profile info.'),
('userlist', 'it', 'Lista membri – Tutti gli utenti registrati', 'Visualizza tutti i membri registrati della community Nexpell.'),

-- DEFAULT
('default', 'de', 'Nexpell CMS – Das modulare CMS für Communities und Clans', 'Modernes Open-Source-CMS, modular, flexibel und kostenlos.'),
('default', 'en', 'Nexpell CMS – The Modular CMS for Communities and Clans', 'A modern modular open-source CMS for communities and clans.'),
('default', 'it', 'Nexpell CMS – Il CMS modulare per community e clan', 'Un CMS open-source moderno, modulare e completamente gratuito.');
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `description` = VALUES(`description`);
SQL
);



$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_site_lock` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reason` TEXT NOT NULL,
  `time` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_social_media` (
  `socialID` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `twitch` varchar(255) NOT NULL,
  `facebook` varchar(255) NOT NULL,
  `twitter` varchar(255) NOT NULL,
  `youtube` varchar(255) NOT NULL,
  `rss` varchar(255) NOT NULL,
  `vine` varchar(255) NOT NULL,
  `flickr` varchar(255) NOT NULL,
  `linkedin` varchar(255) NOT NULL,
  `instagram` varchar(255) NOT NULL,
  `since` varchar(255) NOT NULL,
  `gametracker` varchar(255) NOT NULL,
  `discord` varchar(255) NOT NULL,
  `steam` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT IGNORE INTO `settings_social_media` (`socialID`, `twitch`, `facebook`, `twitter`, `youtube`, `rss`, `vine`, `flickr`, `linkedin`, `instagram`, `since`, `gametracker`, `discord`, `steam`) VALUES
(1, 'https://www.twitch.tv/pulsradiocom', 'https://www.facebook.com/nexpell', 'https://twitter.com/nexpell', '-', '-', '-', '-', '-', '-', '2025', '85.14.228.228:28960', 'https://www.discord.gg/kErxPxb', '-');
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_startpage` (
  `pageID` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `startpage_text` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `editor` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `settings_startpage` (`pageID`, `title`, `startpage_text`, `date`, `editor`)
VALUES (
  1,
  'Next-Generation',
  '[[lang:de]]Willkommen bei nexpell!<br><br>Herzlichen Glückwunsch — die Installation von nexpell wurde erfolgreich abgeschlossen. Sie haben damit die Basis für eine moderne, flexible und leistungsstarke Webplattform geschaffen, die Ihnen alle Freiheiten bietet, Ihre Ideen zu verwirklichen. Ganz gleich, ob Sie einen Blog, eine Galerie, ein Forum oder eine umfassende Community-Plattform aufbauen möchten — mit nexpell haben Sie das passende Werkzeug in der Hand.<br><br><strong>👉 Ihre nächsten Schritte:</strong><br>- Melden Sie sich im Admin-Panel an, um Ihre ersten Seiten, Kategorien und Inhalte zu erstellen.<br>- Konfigurieren Sie Designs, Farben und Sprachoptionen ganz nach Ihrem Geschmack.<br>- Aktivieren Sie weitere Module wie Artikel, Bewertungen oder ein Diskussionsforum, um Ihre Besucher noch besser einzubinden.<br>- Nutzen Sie die eingebauten Statistik- und Analysefunktionen, um Ihre Zielgruppe besser zu verstehen und Ihre Website weiterzuentwickeln.<br><br>nexpell wurde entwickelt, damit Sie schnell und unkompliziert starten können — und gleichzeitig alle Möglichkeiten offen bleiben, Ihre Webpräsenz individuell zu gestalten.<br><br>Wir wünschen Ihnen viel Erfolg und vor allem Freude beim Aufbau Ihrer neuen Website![[lang:en]]Welcome to nexpell!<br><br>Congratulations — the installation of nexpell has been successfully completed. You now have the foundation for a modern, flexible, and powerful web platform that gives you complete freedom to realize your ideas. Whether you want to build a blog, a gallery, a forum, or a comprehensive community platform — with nexpell, you have the right tool in hand.<br><br><strong>👉 Your next steps:</strong><br>- Log in to the admin panel to create your first pages, categories, and content.<br>- Configure designs, colors, and language options to your liking.<br>- Activate additional modules such as articles, reviews, or a discussion forum to better engage your visitors.<br>- Use the built-in statistics and analysis features to better understand your audience and further develop your website.<br><br>Nexpell was designed so you can start quickly and easily — while keeping all possibilities open to customize your web presence.<br><br>We wish you much success and, above all, joy in building your new website![[lang:it]]Benvenuto in nexpell!<br><br>Congratulazioni — l\'installazione di nexpell è stata completata con successo. Ora hai le basi per una piattaforma web moderna, flessibile e potente che ti offre piena libertà di realizzare le tue idee. Che tu voglia creare un blog, una galleria, un forum o una piattaforma comunitaria completa — con nexpell hai lo strumento giusto a portata di mano.<br><br><strong>👉 I tuoi prossimi passi:</strong><br>- Accedi al pannello di amministrazione per creare le tue prime pagine, categorie e contenuti.<br>- Configura design, colori e opzioni linguistiche secondo i tuoi gusti.<br>- Attiva moduli aggiuntivi come articoli, recensioni o un forum di discussione per coinvolgere meglio i tuoi visitatori.<br>- Utilizza le funzioni statistiche e di analisi integrate per comprendere meglio il tuo pubblico e sviluppare ulteriormente il tuo sito.<br><br>Nexpell è stato progettato per permetterti di iniziare rapidamente e facilmente — mantenendo aperte tutte le possibilità per personalizzare la tua presenza sul web.<br><br>Ti auguriamo tanto successo e, soprattutto, gioia nella costruzione del tuo nuovo sito web!',
  CURRENT_TIMESTAMP,
  '0'
)
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `startpage_text` = VALUES(`startpage_text`),
  `date` = VALUES(`date`),
  `editor` = VALUES(`editor`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_static` (
  `staticID` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `categoryID` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `date` int(14) NOT NULL,
  `editor` int(1) DEFAULT 0,
  `access_roles` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_themes` (
  `themeID` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `modulname` VARCHAR(100) NOT NULL,
  `pfad` VARCHAR(255) NOT NULL,
  `version` VARCHAR(11) NOT NULL,
  `active` INT(11) DEFAULT NULL,
  `themename` VARCHAR(255) NOT NULL,
  `navbar_class` VARCHAR(50) NOT NULL,
  `navbar_theme` VARCHAR(10) NOT NULL,
  `express_active` INT(11) NOT NULL DEFAULT 0,
  `logo_pic` VARCHAR(255) DEFAULT '0',
  `reg_pic` VARCHAR(255) NOT NULL,
  `headlines` VARCHAR(255) DEFAULT '0',
  `sort` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`themeID`),
  UNIQUE KEY `unique_modulname` (`modulname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `settings_themes`
(`themeID`, `name`, `modulname`, `pfad`, `version`, `active`, `themename`, `navbar_class`, `navbar_theme`,
 `express_active`, `logo_pic`, `reg_pic`, `headlines`, `sort`)
VALUES
(1, 'Default', 'default', 'default', '0.3', 1, 'lux', 'bg-light', 'light', 0, 'default_logo.png', 'default_login_bg.jpg', 'headlines_03.css', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `pfad` = VALUES(`pfad`),
  `version` = VALUES(`version`),
  `active` = VALUES(`active`),
  `themename` = VALUES(`themename`),
  `navbar_class` = VALUES(`navbar_class`),
  `navbar_theme` = VALUES(`navbar_theme`),
  `express_active` = VALUES(`express_active`),
  `logo_pic` = VALUES(`logo_pic`),
  `reg_pic` = VALUES(`reg_pic`),
  `headlines` = VALUES(`headlines`),
  `sort` = VALUES(`sort`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_themes_installed` (
  `themeID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `modulname` VARCHAR(255) NOT NULL,
  `version` VARCHAR(20) NOT NULL,
  `author` VARCHAR(100) DEFAULT NULL,
  `url` VARCHAR(255) NOT NULL,
  `folder` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `installed_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`themeID`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `settings_themes_installed`
(`name`, `modulname`, `version`, `author`, `url`, `folder`, `description`)
VALUES
('Lux', 'lux', '5.3.3', 'Bootswatch', 'https://bootswatch.com/lux/', 'lux',
 '[[lang:de]]Ein luxuriöses Theme mit klaren Linien.[[lang:en]]A luxurious theme with clean lines.[[lang:it]]Un tema lussuoso con linee pulite.')
ON DUPLICATE KEY UPDATE
`modulname` = VALUES(`modulname`),
`version` = VALUES(`version`),
`author` = VALUES(`author`),
`url` = VALUES(`url`),
`folder` = VALUES(`folder`),
`description` = VALUES(`description`);
SQL
);













if (!isset($migrator)) {
    die("❌ Kein Migrationsobjekt gefunden!");
}

$steps_log[] = "<div class='fw-bold mb-2 text-primary'>🧩 Migration – settings_widgets</div>";

try {

    if ($migrator->tableExists('settings_widgets_old')) {
        $steps_log[] = "<div class='small text-info'>🔄 Alte Migration erkannt – überspringe Neuanlage...</div>";
    }


    elseif ($migrator->tableExists('settings_widgets') 
         && $migrator->columnExists('settings_widgets', 'widgetdatei')) {

        $steps_log[] = "<div class='small text-info'>📦 Sehr alte Struktur erkannt – führe vollständige Migration durch...</div>";

        // Backup
        $migrator->run("RENAME TABLE `settings_widgets` TO `settings_widgets_old`;");

$migrator->run(<<<'SQL'
CREATE TABLE `settings_widgets` (
  `widget_key`    varchar(128) NOT NULL,
  `title`         varchar(255) NOT NULL DEFAULT '',
  `modulname`     varchar(100) NOT NULL DEFAULT '',
  `plugin`        varchar(64)  NOT NULL DEFAULT '',
  `description`   text DEFAULT NULL,
  `allowed_zones` varchar(255) NOT NULL DEFAULT '',
  `active`        tinyint(1) NOT NULL DEFAULT 1,
  `version`       varchar(16) NOT NULL DEFAULT '1.0.0',
  `created_at`    timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`widget_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

$migrator->run(<<<'SQL'
INSERT IGNORE INTO `settings_widgets` 
(widget_key, title, plugin, allowed_zones)
SELECT 
  widgetdatei AS widget_key,
  widgetname  AS title,
  modulname   AS plugin,
  'top,undertop,left,maintop,mainbottom,right,bottom'
FROM `settings_widgets_old`;
SQL
        );

        $migrator->run("DROP TABLE IF EXISTS `settings_widgets_old`;");

        $steps_log[] = "<div class='small text-success'>✅ Alte settings_widgets erfolgreich modernisiert.</div>";
    }



    elseif ($migrator->tableExists('settings_widgets')) {

        $steps_log[] = "<div class='small text-info'>🔍 Prüfe bestehende Tabelle...</div>";

        // Vollständige moderne Struktur prüfen
        $columnsToAdd = [
            'modulname'     => "ALTER TABLE `settings_widgets` ADD COLUMN `modulname` varchar(100) NOT NULL DEFAULT '' AFTER `title`",
            'plugin'        => "ALTER TABLE `settings_widgets` ADD COLUMN `plugin` varchar(64) NOT NULL DEFAULT '' AFTER `modulname`",
            'description'   => "ALTER TABLE `settings_widgets` ADD COLUMN `description` text DEFAULT NULL AFTER `plugin`",
            'allowed_zones' => "ALTER TABLE `settings_widgets` ADD COLUMN `allowed_zones` varchar(255) NOT NULL DEFAULT '' AFTER `description`",
            'active'        => "ALTER TABLE `settings_widgets` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `allowed_zones`",
            'version'       => "ALTER TABLE `settings_widgets` ADD COLUMN `version` varchar(16) NOT NULL DEFAULT '1.0.0' AFTER `active`",
            'created_at'    => "ALTER TABLE `settings_widgets` ADD COLUMN `created_at` timestamp NULL DEFAULT current_timestamp() AFTER `version`",
        ];

        foreach ($columnsToAdd as $col => $sql) {
            if (!$migrator->columnExists('settings_widgets', $col)) {
                $migrator->run($sql);
                $steps_log[] = "<div class='small text-warning'>➕ Spalte <code>{$col}</code> ergänzt.</div>";
            }
        }

        $migrator->run("
            ALTER TABLE `settings_widgets` 
            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        ");

        $steps_log[] = "<div class='small text-success'>✅ Tabelle settings_widgets vollständig aktualisiert.</div>";
    }



    else {

        $steps_log[] = "<div class='small text-info'>🆕 Erstelle settings_widgets neu...</div>";

        $migrator->run(<<<'SQL'
CREATE TABLE `settings_widgets` (
  `widget_key`    varchar(128) NOT NULL,
  `title`         varchar(255) NOT NULL DEFAULT '',
  `modulname`     varchar(100) NOT NULL DEFAULT '',
  `plugin`        varchar(64)  NOT NULL DEFAULT '',
  `description`   text DEFAULT NULL,
  `allowed_zones` varchar(255) NOT NULL DEFAULT '',
  `active`        tinyint(1) NOT NULL DEFAULT 1,
  `version`       varchar(16) NOT NULL DEFAULT '1.0.0',
  `created_at`    timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`widget_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    $steps_log[] = "<div class='small text-success'>🎯 settings_widgets Migration abgeschlossen.</div>";

} catch (Throwable $e) {
    $steps_log[] = "<div class='text-danger small'>
        ❌ Fehler: " . htmlspecialchars($e->getMessage()) . "
    </div>";
}

















$steps_log[] = "<div class='fw-bold mb-2 text-primary'>🧩 Migration – settings_widgets_positions</div>";

try {

    /* -------------------------------------------------------
     * 1) Tabelle erstellen (falls nicht vorhanden)
     * ----------------------------------------------------- */
    $migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `settings_widgets_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `modulname` varchar(100) NOT NULL DEFAULT '',
  `widget_key` varchar(128) NOT NULL DEFAULT '',
  `position` varchar(32) NOT NULL DEFAULT 'top',
  `page` varchar(64) NOT NULL DEFAULT 'index',
  `instance_id` varchar(64) NOT NULL DEFAULT '',
  `settings` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
    );

    /* -------------------------------------------------------
     * 2) Fehlende Spalten ergänzen
     * ----------------------------------------------------- */
    $columnsToAdd = [
        'title'       => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `id`",
        'modulname'   => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `modulname` varchar(100) NOT NULL DEFAULT '' AFTER `title`",
        'widget_key'  => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `widget_key` varchar(128) NOT NULL DEFAULT '' AFTER `modulname`",
        'position'    => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `position` varchar(32) NOT NULL DEFAULT 'top' AFTER `widget_key`",
        'page'        => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `page` varchar(64) NOT NULL DEFAULT 'index' AFTER `position`",
        'instance_id' => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `instance_id` varchar(64) NOT NULL DEFAULT '' AFTER `page`",
        'settings'    => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `settings` text DEFAULT NULL AFTER `instance_id`",
        'sort_order'  => "ALTER TABLE `settings_widgets_positions` ADD COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 AFTER `settings`",
    ];

    foreach ($columnsToAdd as $col => $sql) {
        if (!$migrator->columnExists('settings_widgets_positions', $col)) {
            $migrator->run($sql);
            $steps_log[] = "<div class='small text-warning'>➕ Spalte <code>{$col}</code> ergänzt.</div>";
        }
    }

    /* -------------------------------------------------------
     * 3) Leere instance_id reparieren
     * ----------------------------------------------------- */
    $migrator->run("
        UPDATE `settings_widgets_positions`
        SET `instance_id` = CONCAT('iid_', id)
        WHERE `instance_id` = '';
    ");
    $steps_log[] = "<div class='small text-info'>🔧 Leere instance_id automatisch korrigiert.</div>";


    /* -------------------------------------------------------
     * 4) Duplikate löschen (page + instance_id)
     *    → wichtig für sauberes Move-Verhalten
     * ----------------------------------------------------- */
    $migrator->run("
        DELETE t1 FROM `settings_widgets_positions` t1
        JOIN `settings_widgets_positions` t2
          ON t1.page = t2.page
         AND t1.instance_id = t2.instance_id
         AND t1.id > t2.id;
    ");
    $steps_log[] = "<div class='small text-warning'>🧹 Duplikate in page+instance_id bereinigt.</div>";


    /* -------------------------------------------------------
     * 5) Alle Indexe droppen (ausser PRIMARY)
     * ----------------------------------------------------- */
    $res = $_database->query("
        SELECT DISTINCT INDEX_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'settings_widgets_positions'
          AND INDEX_NAME <> 'PRIMARY'
    ");

    if ($res) {
        while ($idx = $res->fetch_assoc()) {
            $iname = $idx['INDEX_NAME'];
            $migrator->run("ALTER TABLE `settings_widgets_positions` DROP INDEX `$iname`;");
        }
    }

    /* -------------------------------------------------------
     * 6) Neue, korrekte Indexe setzen
     * ----------------------------------------------------- */
$migrator->run(<<<'SQL'
ALTER TABLE `settings_widgets_positions`
  ADD UNIQUE KEY `uniq_page_instance` (`page`,`instance_id`),
  ADD KEY `idx_widget_key` (`widget_key`),
  ADD KEY `idx_page_position_sort` (`page`,`position`,`sort_order`);
SQL
    );

    /* -------------------------------------------------------
     * 7) Charset korrigieren
     * ----------------------------------------------------- */
    $migrator->run("
        ALTER TABLE `settings_widgets_positions`
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ");

    $steps_log[] = "<div class='small text-success'>🎯 settings_widgets_positions erfolgreich migriert.</div>";

} catch (Throwable $e) {
    $steps_log[] = "<div class='text-danger small'>
        ❌ Fehler: " . htmlspecialchars($e->getMessage()) . "
    </div>";
}











$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tags` (
  `rel` varchar(255) NOT NULL,
  `ID` int(11) NOT NULL,
  `tag` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `users` (
  `userID` int(11) NOT NULL AUTO_INCREMENT,
  `registerdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastlogin` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `password_hash` varchar(255) NOT NULL,
  `password_pepper` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_hide` tinyint(1) NOT NULL DEFAULT 1,
  `email_change` varchar(255) NOT NULL,
  `email_activate` varchar(255) NOT NULL,
  `role` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `activation_code` varchar(64) DEFAULT NULL,
  `activation_expires` int(11) DEFAULT NULL,
  `visits` int(11) NOT NULL DEFAULT 0,
  `language` varchar(2) NOT NULL,
  `last_update` datetime DEFAULT NULL,
  `login_time` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `total_online_seconds` int(11) DEFAULT 0,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`userID`),
  KEY `idx_last_update` (`last_update`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_profiles (
  userID int(10) UNSIGNED NOT NULL PRIMARY KEY,
  firstname varchar(100) DEFAULT NULL,
  lastname varchar(100) DEFAULT NULL,
  location varchar(150) DEFAULT NULL,
  about_me text DEFAULT NULL,
  avatar varchar(255) DEFAULT NULL,
  birthday date DEFAULT NULL,
  gender varchar(50) DEFAULT NULL,
  signatur varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `user_profiles` (`userID`, `firstname`, `lastname`, `location`, `about_me`, `avatar`, `birthday`, `gender`, `signatur`)
VALUES (1, NULL, NULL, NULL, '', NULL, NULL, NULL, '')
ON DUPLICATE KEY UPDATE
  `firstname` = VALUES(`firstname`),
  `lastname` = VALUES(`lastname`),
  `location` = VALUES(`location`),
  `about_me` = VALUES(`about_me`),
  `avatar` = VALUES(`avatar`),
  `birthday` = VALUES(`birthday`),
  `gender` = VALUES(`gender`),
  `signatur` = VALUES(`signatur`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_register_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL DEFAULT current_timestamp,
  `status` enum('success','failed') NOT NULL DEFAULT 'failed',
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `attempt_time` (`attempt_time`),
  KEY `username` (`username`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$steps_log[] = "<div class='fw-bold mb-2 text-primary'>🧩 Migration – user_roles</div>";

try {

    // ────────────────────────────────────────────────
    // 1. Foreign Keys temporär deaktivieren
    // ────────────────────────────────────────────────
    $migrator->run("SET FOREIGN_KEY_CHECKS = 0;");
    $steps_log[] = "<div class='small text-info'>🔓 FOREIGN_KEY_CHECKS deaktiviert.</div>";


    // ────────────────────────────────────────────────
    // 2. Tabelle löschen (auch wenn FK existieren)
    // ────────────────────────────────────────────────
    $migrator->run("DROP TABLE IF EXISTS `user_roles`;");
    $steps_log[] = "<div class='small text-warning'>🗑 user_roles gelöscht.</div>";


    // ────────────────────────────────────────────────
    // 3. Neu erstellen
    // ────────────────────────────────────────────────
    $migrator->run(<<<'SQL'
CREATE TABLE `user_roles` (
  `roleID` INT(11) NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL,
  `modulname` VARCHAR(100) NOT NULL DEFAULT '',
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_default` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`roleID`),
  UNIQUE KEY `unique_role_name` (`role_name`),
  UNIQUE KEY `unique_modulname` (`modulname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
    );
    $steps_log[] = "<div class='small text-info'>🆕 user_roles neu erstellt.</div>";


    // ────────────────────────────────────────────────
    // 4. Standardrollen sauber einfügen
    // ────────────────────────────────────────────────
    $migrator->run(<<<'SQL'
INSERT INTO `user_roles` (`roleID`, `role_name`, `modulname`, `description`, `is_active`, `is_default`)
VALUES 
(1, 'Admin',                 'ac_admin',        'Vollzugriff', 1, 0),
(2, 'Co-Admin',              'ac_coadmin',      'Unterstützt Admin', 1, 0),
(3, 'Leader',                'ac_leader',       'Clan-Leiter', 1, 0),
(4, 'Co-Leader',             'ac_coleader',     'Vertretung', 1, 0),
(5, 'Squad-Leader',          'ac_squadleader',  'Squad-Leitung', 1, 0),
(6, 'War-Organisator',       'ac_warorganizer', 'Turnierorga', 1, 0),
(7, 'Moderator',             'ac_moderator',    'Moderation', 1, 0),
(8, 'Redakteur',             'ac_editor',       'News/Content', 1, 0),
(9, 'Member',                'ac_member',       'Mitglied', 1, 0),
(10,'Trial-Member',          'ac_trialmember',  'Probezeit', 1, 0),
(11,'Gast',                  'ac_guest',        'Besucher', 1, 0),
(12,'Registrierter Benutzer','ac_registered',   'Angemeldet', 1, 0),
(13,'Ehrenmitglied',         'ac_honor',        'Ehrenstatus', 1, 0),
(14,'Streamer',              'ac_streamer',     'Streams', 1, 0),
(15,'Designer',              'ac_designer',     'Grafiken', 1, 0),
(16,'Techniker',             'ac_technician',   'Technik', 1, 0);
SQL
    );
    $steps_log[] = "<div class='small text-success'>🎉 Standardrollen eingefügt.</div>";


    // ────────────────────────────────────────────────
    // 5. FOREIGN KEY Checks wieder aktivieren
    // ────────────────────────────────────────────────
    $migrator->run("SET FOREIGN_KEY_CHECKS = 1;");
    $steps_log[] = "<div class='small text-info'>🔒 FOREIGN_KEY_CHECKS wieder aktiviert.</div>";

} catch (Throwable $e) {

    $steps_log[] = "<div class='text-danger small'>
        ❌ Fehler bei user_roles: " . htmlspecialchars($e->getMessage()) . "
    </div>";

    // Sicherstellen, dass FOREIGN_KEY_CHECKS wieder aktiv sind
    $migrator->run("SET FOREIGN_KEY_CHECKS = 1;");
}





if ($migrator->columnExists('user_role_admin_navi_rights', 'accessID')) {
    $migrator->run("ALTER TABLE `user_role_admin_navi_rights` DROP COLUMN `accessID`;");
}

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_role_admin_navi_rights` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `roleID` INT(11) NOT NULL,
  `type` ENUM('link','category') NOT NULL,
  `modulname` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_access` (`roleID`, `type`, `modulname`),
  CONSTRAINT `user_role_admin_navi_rights_ibfk_1`
    FOREIGN KEY (`roleID`) REFERENCES `user_roles` (`roleID`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `user_role_admin_navi_rights` (`roleID`, `type`, `modulname`)
VALUES 
(1, 'link', 'ac_overview'),
(1, 'link', 'ac_visitor_statistic'),
(1, 'link', 'ac_settings'),
(1, 'link', 'ac_dashboard_navigation'),
(1, 'link', 'ac_email'),
(1, 'link', 'ac_contact'),
(1, 'link', 'ac_database'),
(1, 'link', 'ac_theme'),
(1, 'link', 'ac_startpage'),
(1, 'link', 'ac_static'),
(1, 'link', 'ac_imprint'),
(1, 'link', 'ac_db_stats'),
(1, 'link', 'ac_editlang'),
(1, 'link', 'ac_headstyle'),
(1, 'link', 'ac_languages'),
(1, 'link', 'ac_log_viewer'),
(1, 'link', 'ac_plugin_installer'),
(1, 'link', 'ac_plugin_manager'),
(1, 'link', 'ac_plugin_widgets_save'),
(1, 'link', 'ac_plugin_widgets_setting'),
(1, 'link', 'ac_privacy_policy'),
(1, 'link', 'ac_security_overview'),
(1, 'link', 'ac_seo_meta'),
(1, 'link', 'ac_site_lock'),
(1, 'link', 'ac_statistic'),
(1, 'link', 'ac_stylesheet'),
(1, 'link', 'ac_theme_installer'),
(1, 'link', 'ac_theme_preview'),
(1, 'link', 'ac_theme_save'),
(1, 'link', 'ac_update_core'),
(1, 'link', 'ac_user_roles'),
(1, 'link', 'ac_webside_navigation'),
(1, 'link', 'footer_easy'),
(1, 'category', 'cat_content'),
(1, 'category', 'cat_design'),
(1, 'category', 'cat_media'),
(1, 'category', 'cat_partners'),
(1, 'category', 'cat_plugins'),
(1, 'category', 'cat_security'),
(1, 'category', 'cat_slider_header'),
(1, 'category', 'cat_social'),
(1, 'category', 'cat_statistics'),
(1, 'category', 'cat_system'),
(1, 'category', 'cat_team'),
(1, 'category', 'cat_tools_game'),
(1, 'category', 'cat_users')
ON DUPLICATE KEY UPDATE
  `type` = VALUES(`type`),
  `modulname` = VALUES(`modulname`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_role_assignments` (
  `assignmentID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `roleID` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`assignmentID`),
  KEY `roleID` (`roleID`),
  KEY `user_role_assignments` (`userID`) USING BTREE,
  CONSTRAINT `user_role_assignments_admin` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  CONSTRAINT `user_role_assignments_role` FOREIGN KEY (`roleID`) REFERENCES `user_roles` (`roleID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `userID` int(11) NOT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `session_data` text DEFAULT NULL,
  `browser` text DEFAULT NULL,
  `last_activity` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  KEY `userID` (`userID`),
  CONSTRAINT `fk_sessions_userID` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_settings (
    userID INT UNSIGNED NOT NULL,
    language VARCHAR(10) DEFAULT 'de',
    dark_mode TINYINT(1) DEFAULT 0,
    email_notifications TINYINT(1) DEFAULT 1,
    private_profile TINYINT(1) DEFAULT 0,
    PRIMARY KEY (userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `user_settings` (`userID`, `language`, `dark_mode`, `email_notifications`, `private_profile`)
VALUES (1, 'de', 0, 1, 0)
ON DUPLICATE KEY UPDATE
  `language` = VALUES(`language`),
  `dark_mode` = VALUES(`dark_mode`),
  `email_notifications` = VALUES(`email_notifications`),
  `private_profile` = VALUES(`private_profile`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_socials (
    userID INT UNSIGNED NOT NULL,
    facebook VARCHAR(255) DEFAULT NULL,
    twitter VARCHAR(255) DEFAULT NULL,
    instagram VARCHAR(255) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    github VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);
$migrator->run(<<<'SQL'
INSERT INTO `user_socials` (`userID`, `facebook`, `twitter`, `instagram`, `website`, `github`)
VALUES (1, NULL, NULL, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE
  `facebook` = VALUES(`facebook`),
  `twitter` = VALUES(`twitter`),
  `instagram` = VALUES(`instagram`),
  `website` = VALUES(`website`),
  `github` = VALUES(`github`);
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_stats (
    userID INT UNSIGNED NOT NULL,
    points INT UNSIGNED DEFAULT 0,
    lastlogin DATETIME DEFAULT NULL,
    registerdate DATETIME DEFAULT CURRENT_TIMESTAMP,
    logins_count INT UNSIGNED DEFAULT 0,
    total_time_online INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_username` (
  `userID` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `visitors_live` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `time` INT(11) NOT NULL,
  `userID` INT(11) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `site` VARCHAR(255) DEFAULT NULL,
  `country_code` VARCHAR(5) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `visitors_live_history` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `time` INT(10) UNSIGNED NOT NULL,
  `userID` INT(10) UNSIGNED DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `site` VARCHAR(255) DEFAULT NULL,
  `country_code` CHAR(2) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `visitor_daily_counter` (
  `date` DATE NOT NULL,
  `hits` INT(11) NOT NULL DEFAULT 0,
  `online` INT(11) NOT NULL DEFAULT 0,
  `maxonline` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `visitor_daily_counter_hits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `ip_hash` CHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_date` (`user_id`, `date`),
  UNIQUE KEY `uq_iphash_date` (`ip_hash`, `date`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `visitor_daily_iplist` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `dates` DATE NOT NULL,
  `del` INT(11) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `country_code` VARCHAR(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip_date` (`ip`, `dates`),
  KEY `idx_date` (`dates`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `visitor_daily_stats` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `hits` INT(11) NOT NULL DEFAULT 0,
  `online` INT(11) NOT NULL DEFAULT 0,
  `maxonline` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

$migrator->run(<<<'SQL'
CREATE TABLE IF NOT EXISTS `visitor_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `pageviews` int(11) DEFAULT 1,
  `last_seen` datetime NOT NULL DEFAULT current_timestamp,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `page` varchar(255) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `ip_hash` varchar(64) NOT NULL,
  `referer` varchar(300) NOT NULL,
  `user_agent` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
);

if (!$migrator->columnExists('navigation_website_main', 'modulname')) {
    $migrator->run("ALTER TABLE `navigation_website_main` ADD COLUMN `modulname` varchar(255) NOT NULL DEFAULT '';");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>modulname</code> in <b>navigation_website_main</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>modulname</code> in <b>navigation_website_main</b> existiert bereits – übersprungen.</div>";
}
if ($migrator->columnExists('navigation_website_main', 'mnavID')) {
    $migrator->run("ALTER TABLE `navigation_website_main` MODIFY `mnavID` int(11) NOT NULL AUTO_INCREMENT;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>mnavID</code> in <b>navigation_website_main</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>mnavID</code> in <b>navigation_website_main</b> fehlt – konnte nicht geändert werden.</div>";
}
if ($migrator->columnExists('navigation_website_main', 'url')) {
    $migrator->run("ALTER TABLE `navigation_website_main` MODIFY `url` varchar(255) NOT NULL DEFAULT '#';");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>url</code> in <b>navigation_website_main</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>url</code> in <b>navigation_website_main</b> fehlt – konnte nicht geändert werden.</div>";
}
if ($migrator->columnExists('navigation_website_main', 'default')) {
    $migrator->run("ALTER TABLE `navigation_website_main` MODIFY `default` tinyint(1) NOT NULL DEFAULT 1;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>default</code> in <b>navigation_website_main</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>default</code> in <b>navigation_website_main</b> fehlt – konnte nicht geändert werden.</div>";
}
if ($migrator->columnExists('navigation_website_main', 'sort')) {
    $migrator->run("ALTER TABLE `navigation_website_main` MODIFY `sort` int(11) NOT NULL DEFAULT 0;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>sort</code> in <b>navigation_website_main</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>sort</code> in <b>navigation_website_main</b> fehlt – konnte nicht geändert werden.</div>";
}
if ($migrator->columnExists('navigation_website_main', 'isdropdown')) {
    $migrator->run("ALTER TABLE `navigation_website_main` MODIFY `isdropdown` tinyint(1) NOT NULL DEFAULT 0;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>isdropdown</code> in <b>navigation_website_main</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>isdropdown</code> in <b>navigation_website_main</b> fehlt – konnte nicht geändert werden.</div>";
}
if ($migrator->columnExists('navigation_website_main', 'windows')) {
    $migrator->run("ALTER TABLE `navigation_website_main` MODIFY `windows` tinyint(1) NOT NULL DEFAULT 1;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>windows</code> in <b>navigation_website_main</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>windows</code> in <b>navigation_website_main</b> fehlt – konnte nicht geändert werden.</div>";
}
if (!$migrator->columnExists('settings', 'use_seo_urls')) {
    $migrator->run("ALTER TABLE `settings` ADD COLUMN `use_seo_urls` tinyint(1) DEFAULT 0;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>use_seo_urls</code> in <b>settings</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>use_seo_urls</code> in <b>settings</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('settings_imprint', 'editor')) {
    $migrator->run("ALTER TABLE `settings_imprint` ADD COLUMN `editor` int(1) DEFAULT 0;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>editor</code> in <b>settings_imprint</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>editor</code> in <b>settings_imprint</b> existiert bereits – übersprungen.</div>";
}
if ($migrator->columnExists('settings_languages', 'updated_at')) {
    $migrator->run("ALTER TABLE `settings_languages` MODIFY `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>updated_at</code> in <b>settings_languages</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>updated_at</code> in <b>settings_languages</b> fehlt – konnte nicht geändert werden.</div>";
}
if (!$migrator->columnExists('users', 'login_time')) {
    $migrator->run("ALTER TABLE `users` ADD COLUMN `login_time` datetime DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>login_time</code> in <b>users</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>login_time</code> in <b>users</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('users', 'last_activity')) {
    $migrator->run("ALTER TABLE `users` ADD COLUMN `last_activity` datetime DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>last_activity</code> in <b>users</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>last_activity</code> in <b>users</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('users', 'total_online_seconds')) {
    $migrator->run("ALTER TABLE `users` ADD COLUMN `total_online_seconds` int(11) DEFAULT 0;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>total_online_seconds</code> in <b>users</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>total_online_seconds</code> in <b>users</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('users', 'is_online')) {
    $migrator->run("ALTER TABLE `users` ADD COLUMN `is_online` tinyint(1) NOT NULL DEFAULT 0;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>is_online</code> in <b>users</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>is_online</code> in <b>users</b> existiert bereits – übersprungen.</div>";
}
if ($migrator->columnExists('users', 'email_hide')) {
    $migrator->run("ALTER TABLE `users` MODIFY `email_hide` tinyint(1) NOT NULL DEFAULT 1;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>email_hide</code> in <b>users</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>email_hide</code> in <b>users</b> fehlt – konnte nicht geändert werden.</div>";
}
if (!$migrator->columnExists('user_roles', 'modulname')) {
    $migrator->run("ALTER TABLE `user_roles` ADD COLUMN `modulname` VARCHAR(100) NOT NULL DEFAULT '';");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>modulname</code> in <b>user_roles</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>modulname</code> in <b>user_roles</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('user_roles', 'is_active')) {
    $migrator->run("ALTER TABLE `user_roles` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>is_active</code> in <b>user_roles</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>is_active</code> in <b>user_roles</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('settings_widgets', 'widget_key')) {
    $migrator->run("ALTER TABLE `settings_widgets` ADD COLUMN `widget_key` VARCHAR(255) NOT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>widget_key</code> in <b>settings_widgets</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>widget_key</code> in <b>settings_widgets</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('settings_widgets', 'title')) {
    $migrator->run("ALTER TABLE `settings_widgets` ADD COLUMN `title` VARCHAR(255) DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>title</code> in <b>settings_widgets</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>title</code> in <b>settings_widgets</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('settings_widgets', 'plugin')) {
    $migrator->run("ALTER TABLE `settings_widgets` ADD COLUMN `plugin` VARCHAR(255) NOT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>plugin</code> in <b>settings_widgets</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>plugin</code> in <b>settings_widgets</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('settings_widgets', 'allowed_zones')) {
    $migrator->run("ALTER TABLE `settings_widgets` ADD COLUMN `allowed_zones` varchar(255) DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>allowed_zones</code> in <b>settings_widgets</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>allowed_zones</code> in <b>settings_widgets</b> existiert bereits – übersprungen.</div>";
}
if ($migrator->columnExists('settings_widgets', 'modulname')) {
    $migrator->run("ALTER TABLE `settings_widgets` MODIFY `modulname` VARCHAR(255) NOT NULL;");
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚙️ Spalte <code>modulname</code> in <b>settings_widgets</b> wurde geändert.</div>";
} else {
    $steps_log[] = "<div class='alert alert-warning py-1 my-1 small'>⚠️ Spalte <code>modulname</code> in <b>settings_widgets</b> fehlt – konnte nicht geändert werden.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'user_id')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `user_id` int(11) DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>user_id</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>user_id</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'last_seen')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `last_seen` datetime NOT NULL DEFAULT current_timestamp;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>last_seen</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>last_seen</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'device_type')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `device_type` varchar(20) DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>device_type</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>device_type</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'os')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `os` varchar(50) DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>os</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>os</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'browser')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `browser` varchar(100) DEFAULT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>browser</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>browser</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'ip_hash')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `ip_hash` varchar(64) NOT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>ip_hash</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>ip_hash</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'referer')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `referer` varchar(300) NOT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>referer</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>referer</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}
if (!$migrator->columnExists('visitor_statistics', 'user_agent')) {
    $migrator->run("ALTER TABLE `visitor_statistics` ADD COLUMN `user_agent` text NOT NULL;");
    $steps_log[] = "<div class='alert alert-success py-1 my-1 small'>➕ Spalte <code>user_agent</code> in <b>visitor_statistics</b> wurde hinzugefügt.</div>";
} else {
    $steps_log[] = "<div class='text-muted small'>ℹ️ Spalte <code>user_agent</code> in <b>visitor_statistics</b> existiert bereits – übersprungen.</div>";
}


if (!function_exists('nx_fix_auto_increment')) {
    /**
     * Repariert fehlerhafte AUTO_INCREMENT- und PRIMARY KEY-Spalten.
     * - Sorgt dafür, dass $idColumn existiert, AUTO_INCREMENT ist und PRIMARY KEY hat.
     * - Entfernt alle anderen AUTO_INCREMENT-Spalten.
     * - Funktioniert sicher auch bei alten Dumps / Migrationen.
     */
    function nx_fix_auto_increment($migrator, string $table, string $idColumn = 'id'): void {
        global $_database, $steps_log;

        try {
            // 🔍 Prüfen, ob Tabelle existiert
            if (!$migrator->tableExists($table)) {
                $steps_log[] = "<div class='text-warning small'>⚠️ Tabelle <code>{$table}</code> nicht gefunden – übersprungen.</div>";
                return;
            }

            // 🔎 Spalten abrufen
            $result = $_database->query("SHOW COLUMNS FROM `$table`");
            if (!$result) {
                $steps_log[] = "<div class='text-danger small'>❌ Spalten für <code>{$table}</code> konnten nicht gelesen werden.</div>";
                return;
            }

            $autoCols = [];
            while ($col = $result->fetch_assoc()) {
                if (stripos($col['Extra'], 'auto_increment') !== false) {
                    $autoCols[] = $col['Field'];
                }
            }
            $result->free();

            // 🧹 Mehrere AUTO_INCREMENT-Spalten? -> korrigieren
            if (count($autoCols) > 1) {
                foreach ($autoCols as $col) {
                    if ($col !== $idColumn) {
                        $migrator->query("ALTER TABLE `$table` MODIFY `$col` INT(11) NULL;");
                        $steps_log[] = "<div class='text-warning small'>🧹 Spalte <code>{$col}</code> in <code>{$table}</code> AutoIncrement entfernt.</div>";
                    }
                }
            }

            // ⚙️ Prüfen, ob ID-Spalte existiert
            $check = $_database->query("SHOW COLUMNS FROM `$table` LIKE '{$idColumn}'");
            if ($check && $check->num_rows > 0) {

                // 🧱 Ist sie bereits PRIMARY KEY?
                $isPrimary = false;
                $pkRes = $_database->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
                if ($pkRes && $pkRes->num_rows > 0) {
                    while ($row = $pkRes->fetch_assoc()) {
                        if ($row['Column_name'] === $idColumn) {
                            $isPrimary = true;
                            break;
                        }
                    }
                    $pkRes->free();
                }

                // 🔧 ID-Spalte korrigieren
                $migrator->query("ALTER TABLE `$table` MODIFY `$idColumn` INT(11) NOT NULL AUTO_INCREMENT;");
                if (!$isPrimary) {
                    $migrator->query("ALTER TABLE `$table` ADD PRIMARY KEY (`$idColumn`);");
                    $steps_log[] = "<div class='text-success small'>✅ <code>{$idColumn}</code> in <code>{$table}</code> als PRIMARY KEY gesetzt.</div>";
                } else {
                    $steps_log[] = "<div class='text-success small'>✅ <code>{$idColumn}</code> in <code>{$table}</code> als AUTO_INCREMENT bestätigt.</div>";
                }

            } else {
                // 🚨 Keine ID-Spalte vorhanden → anlegen
                $migrator->query("ALTER TABLE `$table` ADD COLUMN `$idColumn` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;");
                $steps_log[] = "<div class='text-success small'>🆕 Spalte <code>{$idColumn}</code> in <code>{$table}</code> hinzugefügt (AutoIncrement + PK).</div>";
            }

        } catch (Throwable $e) {
            $steps_log[] = "<div class='text-danger small'>⚠️ Fix AutoIncrement für <code>{$table}</code> fehlgeschlagen: "
                         . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}



nx_fix_auto_increment($migrator, 'visitors_live');
nx_fix_auto_increment($migrator, 'visitors_live_history');
nx_fix_auto_increment($migrator, 'visitor_daily_counter_hits');
nx_fix_auto_increment($migrator, 'visitor_daily_iplist');
nx_fix_auto_increment($migrator, 'visitor_daily_stats');









