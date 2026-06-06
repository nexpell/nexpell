<?php

namespace nexpell;

class PluginInstaller
{
    public static function install(string $modulname, string $plugin_folder_path): void
    {
        echo "<strong>PluginInstaller::install() wurde aufgerufen!</strong><br>";
        global $_database;

        // Plugin-Datenbanktabellen erstellen
        self::createPluginTables($modulname);

        // Einstellungen für das Plugin hinzufügen
        self::insertPluginSettings($modulname);

        // Plugin-Verzeichnis und Dateien kopieren
        self::copyPluginFiles($plugin_folder_path);

        // Plugin in der Datenbank registrieren
        self::registerPluginInDatabase($modulname);
    }

    private static function createPluginTables(string $modulname): void
    {
        global $_database;
        $tables = self::getPluginTables($modulname);
        foreach ($tables as $table) {
            // Beispiel für Tabellen-Erstellung, passe sie nach Bedarf an
            $query = "CREATE TABLE `$table` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            safe_query($query);
            echo "Tabelle <code>$table</code> wurde erstellt.<br>";
        }
    }

    private static function getPluginTables(string $modulname): array
    {
        // Beispielhafte Tabellenbezeichner, passe sie nach deinen Bedürfnissen an
        return [
            "plugins_{$modulname}_data",
            "plugins_{$modulname}_settings"
        ];
    }

    private static function insertPluginSettings(string $modulname): void
    {
        global $_database;
        // Beispielhafte Plugin-Einstellungen einfügen
        $settings = [
            'plugin_name' => $modulname,
            'enabled' => 1
        ];
        foreach ($settings as $key => $value) {
            safe_query("INSERT INTO settings (name, value) VALUES ('" . mysqli_real_escape_string($_database, $key) . "', '" . mysqli_real_escape_string($_database, $value) . "')");
        }
        echo "Plugin-Einstellungen für <code>$modulname</code> wurden hinzugefügt.<br>";
    }

    private static function copyPluginFiles(string $plugin_folder): void
    {
        $source = $plugin_folder;  // z.B. "/www/htdocs/w00f9e9f/214/includes/plugins/news"
        $destination = __DIR__ . '/../includes/plugins/' . basename($plugin_folder);

        // Recursives Kopieren der Dateien (ohne Fehlerbehandlung)
        self::recurseCopy($source, $destination);

        echo "Plugin-Dateien aus <code>$source</code> wurden kopiert.<br>";
    }

    private static function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::recurseCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }


    private static function registerPluginInDatabase(string $modulname): void
    {
        global $_database;
        safe_query("INSERT INTO settings_plugins_installed (modulname, installed_date) VALUES ('" . mysqli_real_escape_string($_database, $modulname) . "', NOW())");
        echo "Plugin <code>$modulname</code> wurde in der Datenbank registriert.<br>";
    }
}
