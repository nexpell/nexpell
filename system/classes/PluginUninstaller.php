<?php

namespace nexpell;

class PluginUninstaller
{
    private $log = [];

    public function uninstall($plugin_folder)
    {
        $this->log = [];

        // Plugin-Verzeichnis korrekt berechnen (2 Ebenen hoch von /system/classes/)
        $plugin_dir = dirname(__DIR__, 2) . '/includes/plugins/' . $plugin_folder;

        $this->addLog('info', 'Korrigierter Pfad: ' . $plugin_dir);

        if (!is_dir($plugin_dir)) {
            $this->addLog('error', 'Plugin-Ordner nicht gefunden: ' . $plugin_folder);
            return false;
        }

        $this->removePluginFiles($plugin_dir);
        $this->removeDatabaseEntries($plugin_folder);

        return true;
    }



    private function removePluginFiles($plugin_dir)
    {
        // Alle Dateien und Ordner des Plugins löschen
        if (deleteFolder($plugin_dir)) {
            $this->addLog('success', 'Plugin-Dateien erfolgreich gelöscht.');
        } else {
            $this->addLog('error', 'Fehler beim Löschen der Plugin-Dateien.');
        }
    }

private function removeDatabaseEntries(string $plugin_folder): void
{
    global $_database;

    $plugin = $_database->real_escape_string($plugin_folder);

    /* =====================================================
       1️⃣ Plugin-Registrierungen entfernen
    ===================================================== */
    $_database->query("DELETE FROM settings_plugins_installed WHERE modulname = '$plugin'");
    $_database->query("DELETE FROM settings_widgets WHERE modulname = '$plugin'");
    $_database->query("DELETE FROM settings_widgets_positions WHERE modulname = '$plugin'");

    /* =====================================================
       2️⃣ Plugin-Tabellen ermitteln
    ===================================================== */
    $tables = [];
    $res = $_database->query("SHOW TABLES LIKE 'plugins_{$plugin}%'");
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }

    if (!$tables) {
        $this->addLog('info', 'Keine Plugin-Tabellen gefunden.');
        return;
    }

    /* =====================================================
       3️⃣ EXTERNE FOREIGN KEYS ENTFERNEN
       (Core / andere Plugins → Plugin-Tabellen)
    ===================================================== */
    $inList = "'" . implode("','", array_map('escape', $tables)) . "'";

    $sql = "
        SELECT CONSTRAINT_NAME, TABLE_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME IN ($inList)
    ";

    $res = $_database->query($sql);
    while ($row = $res->fetch_assoc()) {
        $fkTable = $row['TABLE_NAME'];
        $fkName  = $row['CONSTRAINT_NAME'];

        if ($_database->query("ALTER TABLE `$fkTable` DROP FOREIGN KEY `$fkName`")) {
            $this->addLog('info', "Foreign Key entfernt: {$fkTable}.{$fkName}");
        } else {
            $this->addLog('error', "FK konnte nicht entfernt werden: {$fkTable}.{$fkName}");
        }
    }

    /* =====================================================
       4️⃣ Plugin-Tabellen löschen
       (Reihenfolge jetzt egal)
    ===================================================== */
    foreach ($tables as $table) {
        if ($_database->query("DROP TABLE `$table`")) {
            $this->addLog('success', "Tabelle gelöscht: {$table}");
        } else {
            $this->addLog('error', "Tabelle konnte nicht gelöscht werden: {$table}");
        }
    }

    /* =====================================================
       5️⃣ modulname-Aufräumen (global)
    ===================================================== */
    $this->removeEntriesByModuleColumn($plugin_folder);
}





    private function addLog($type, $message)
    {
        $this->log[] = ['type' => $type, 'message' => $message];
    }

    public function getLog()
    {
        return $this->log;
    }

    private function removeEntriesByModuleColumn($plugin_folder)
    {
        global $_database;

        $folder_escaped = $_database->real_escape_string($plugin_folder);

        // Hole alle Tabellen der Datenbank
        $result = $_database->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $table = $row[0];

            // Prüfen, ob die Tabelle eine Spalte "modulname" hat
            $col_result = $_database->query("SHOW COLUMNS FROM `" . $table . "` LIKE 'modulname'");
            if ($col_result && $col_result->num_rows > 0) {
                // Einträge mit modulname = 'pluginname' löschen
                $delete_sql = "DELETE FROM `" . $table . "` WHERE `modulname` = '" . $folder_escaped . "'";
                $_database->query($delete_sql);

                if ($_database->affected_rows > 0) {
                    $this->addLog('success', "Einträge aus {$table} gelöscht (modulname = '{$plugin_folder}', {$_database->affected_rows} Zeilen).");
                }
            }
        }
    }

/*    private function removeAllPluginTables($plugin_folder)
    {
        global $_database;

        $folder_escaped = $_database->real_escape_string($plugin_folder);

        $_database->query("SET FOREIGN_KEY_CHECKS = 0"); // <- HINZUGEFÜGT

        $sql = "SHOW TABLES LIKE 'plugins_" . $folder_escaped . "%'";
        $result = $_database->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_row()) {
                $table_name = $row[0];
                $drop_sql = "DROP TABLE IF EXISTS `" . $table_name . "`";
                if ($_database->query($drop_sql)) {
                    $this->addLog('success', "Tabelle gelöscht: " . $table_name);
                } else {
                    $this->addLog('error', "Fehler beim Löschen der Tabelle: " . $table_name);
                }
            }
        } else {
            $this->addLog('info', "Keine passenden Tabellen für 'plugins_{$plugin_folder}' gefunden.");
        }

        $_database->query("SET FOREIGN_KEY_CHECKS = 1"); // <- HINZUGEFÜGT
    }
*/
}
