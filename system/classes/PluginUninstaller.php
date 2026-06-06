<?php

namespace nexpell;

class PluginUninstaller
{
    private array $log = [];

    public function uninstall($plugin_folder)
    {
        $this->log = [];

        $plugin_dir = dirname(__DIR__, 2) . '/includes/plugins/' . $plugin_folder;
        $this->addLog('info', 'Korrigierter Pfad: ' . $plugin_dir);

        if (!is_dir($plugin_dir)) {
            $this->addLog('error', 'Plugin-Ordner nicht gefunden: ' . $plugin_folder);
            return false;
        }

        $this->removePluginFiles($plugin_dir);
        $this->removeDatabaseEntries((string) $plugin_folder);

        return true;
    }

    public function getLog()
    {
        return $this->log;
    }

    private function removePluginFiles(string $plugin_dir): void
    {
        if ($this->deleteFolder($plugin_dir)) {
            $this->addLog('success', 'Plugin-Dateien erfolgreich geloescht.');
            return;
        }

        $this->addLog('error', 'Fehler beim Loeschen der Plugin-Dateien.');
    }

    private function removeDatabaseEntries(string $plugin_folder): void
    {
        global $_database;

        $plugin = $_database->real_escape_string($plugin_folder);
        $moduleAliases = $this->getModuleAliases($plugin_folder);
        $escapedAliases = array_map([$this, 'escapeDbValue'], $moduleAliases);
        $moduleInList = "'" . implode("','", $escapedAliases) . "'";

        $_database->query("DELETE FROM settings_plugins_installed WHERE modulname IN ($moduleInList)");
        $_database->query("DELETE FROM settings_widgets WHERE modulname IN ($moduleInList)");
        $_database->query("DELETE FROM settings_widgets_positions WHERE modulname IN ($moduleInList)");

        $tables = [];
        $res = $_database->query("SHOW TABLES LIKE 'plugins_{$plugin}%'");
        while ($res && ($row = $res->fetch_row())) {
            $tables[] = $row[0];
        }

        if (!$tables) {
            $this->addLog('info', 'Keine Plugin-Tabellen gefunden.');
            $this->removeEntriesByModuleColumn($moduleAliases);
            return;
        }

        $inList = "'" . implode("','", array_map('escape', $tables)) . "'";
        $sql = "
            SELECT CONSTRAINT_NAME, TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IN ($inList)
        ";

        $res = $_database->query($sql);
        while ($res && ($row = $res->fetch_assoc())) {
            $fkTable = $row['TABLE_NAME'];
            $fkName = $row['CONSTRAINT_NAME'];

            if ($_database->query("ALTER TABLE `$fkTable` DROP FOREIGN KEY `$fkName`")) {
                $this->addLog('info', "Foreign Key entfernt: {$fkTable}.{$fkName}");
            } else {
                $this->addLog('error', "FK konnte nicht entfernt werden: {$fkTable}.{$fkName}");
            }
        }

        foreach ($tables as $table) {
            if ($_database->query("DROP TABLE `$table`")) {
                $this->addLog('success', "Tabelle geloescht: {$table}");
            } else {
                $this->addLog('error', "Tabelle konnte nicht geloescht werden: {$table}");
            }
        }

        $this->removeEntriesByModuleColumn($moduleAliases);
    }

    private function removeEntriesByModuleColumn(array $moduleAliases): void
    {
        global $_database;

        $escapedAliases = array_map([$this, 'escapeDbValue'], $moduleAliases);
        $moduleInList = "'" . implode("','", $escapedAliases) . "'";
        $aliasLabel = implode(', ', $moduleAliases);

        $result = $_database->query("SHOW TABLES");
        while ($result && ($row = $result->fetch_row())) {
            $table = $row[0];
            $col_result = $_database->query("SHOW COLUMNS FROM `$table` LIKE 'modulname'");

            if (!$col_result || $col_result->num_rows <= 0) {
                continue;
            }

            $_database->query("DELETE FROM `$table` WHERE `modulname` IN ($moduleInList)");
            if ($_database->affected_rows > 0) {
                $this->addLog('success', "Eintraege aus {$table} geloescht (modulname = '{$aliasLabel}', {$_database->affected_rows} Zeilen).");
            }
        }
    }

    private function getModuleAliases(string $pluginFolder): array
    {
        $aliases = [$pluginFolder];

        if ($pluginFolder === 'about') {
            $aliases[] = 'leistung';
            $aliases[] = 'info';
        }

        return array_values(array_unique(array_filter($aliases, static function ($alias): bool {
            return (string) $alias !== '';
        })));
    }

    private function escapeDbValue(string $value): string
    {
        global $_database;

        return $_database->real_escape_string($value);
    }

    private function deleteFolder(string $dir): bool
    {
        if (!is_dir($dir)) {
            return !file_exists($dir);
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                if (!$this->deleteFolder($path)) {
                    return false;
                }
                continue;
            }

            if (!@unlink($path)) {
                return false;
            }
        }

        return @rmdir($dir);
    }

    private function addLog($type, $message): void
    {
        $this->log[] = ['type' => $type, 'message' => $message];
    }
}
