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

    private function removeDatabaseEntries($plugin_folder)
    {
        global $_database; // Deine DB-Verbindung

        $folder_escaped = $_database->real_escape_string($plugin_folder);

        // Tabelle plugins_installed löschen (deine bisherige Löschung)
        $sql1 = "DELETE FROM settings_plugins_installed WHERE modulname = '" . $folder_escaped . "'";
        $result1 = $_database->query($sql1);

        $sql2 = "DELETE FROM settings_widgets WHERE modulname = '" . $folder_escaped . "'";
        $result1 = $_database->query($sql2);

        $sql3 = "DELETE FROM settings_widgets_positions WHERE modulname = '" . $folder_escaped . "'";
        $result1 = $_database->query($sql3);

        // Tabelle plugins_news (oder entsprechend) löschen
        // Achtung: Das löscht die gesamte Tabelle! Sicherstellen, dass das erwünscht ist.
        $table_name = 'plugins_' . $folder_escaped;
        $sql2 = "DROP TABLE IF EXISTS `" . $table_name . "`";
        $result2 = $_database->query($sql2);

        if ($result1) {
            $this->addLog('success', 'Datenbankeinträge für "' . $plugin_folder . '" wurden gelöscht.');
        } else {
            $this->addLog('error', 'Fehler beim Löschen der Einträge in plugins_installed.');
        }

        if ($result2) {
            $this->addLog('success', 'Datenbanktabelle "' . $table_name . '" wurde gelöscht.');
            echo '<script type="text/javascript">
                setTimeout(function() {
                    window.location.href = "admincenter.php?site=plugin_installer";
                }, 3000); // 3 Sekunden warten
            </script>';
        } else {
            $this->addLog('error', 'Fehler beim Löschen der Tabelle "' . $table_name . '".');
        }
        $this->removeEntriesByModuleColumn($plugin_folder);
        $this->removeAllPluginTables($plugin_folder);
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

    private function removeAllPluginTables($plugin_folder)
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

}
