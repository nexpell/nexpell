<?php

namespace nexpell;

class LanguageService
{
    private \mysqli $_database;
    public string $currentLanguage;
    public array $module = [];

    public function __construct(\mysqli $database)
    {
        $this->_database = $database;

        if (session_status() === PHP_SESSION_NONE) {
        session_start();
        }

        if (isset($_SESSION['language'])) {
            $this->currentLanguage = $_SESSION['language'];
        } else {
            $result = $this->_database->query("SELECT default_language FROM settings LIMIT 1");

            if ($result && $row = $result->fetch_assoc()) {
                $this->currentLanguage = $row['default_language'] ?: 'de';
            } else {
                $this->currentLanguage = 'de';
            }

            $_SESSION['language'] = $this->currentLanguage;
        }
    }

    public function readModule(string $module, bool $isAdmin = false): void
    {
        $lang = $this->currentLanguage;

        $basePath = $isAdmin
            ? $_SERVER['DOCUMENT_ROOT'] . '/admin/language'
            : $_SERVER['DOCUMENT_ROOT'] . '/languages';

        $file = "{$basePath}/{$lang}/{$module}.php";

        if (!file_exists($file)) {
            $this->module = [];
            return;
        }

        include $file;

        if (isset($language_array) && is_array($language_array)) {
            $this->module = $language_array;
        } else {
            $this->module = [];
        }
    }


    public function readPluginModule(string $pluginName): void
    {
        $lang = $this->currentLanguage;

        $file = $_SERVER['DOCUMENT_ROOT'] . "/includes/plugins/{$pluginName}/languages/{$lang}/{$pluginName}.php";

        if (!file_exists($file)) {
            $this->module = [];
            return;
        }

        include $file;

        if (isset($language_array) && is_array($language_array)) {
            $this->module = $language_array;
        } else {
            $this->module = [];
        }
    }

    public function get(string $key): string
    {
        return $this->module[$key] ?? "[{$key}]";
    }

    public function setLanguage(string $lang): void
    {
        $this->currentLanguage = $lang;
        $_SESSION['language'] = $lang;
    }

    /**
     * Liefert die aktuell gesetzte Sprache, falls nicht gesetzt, die Standard-Sprache
     */
    public function detectLanguage(): string
    {
        // Prüfe Session
        if (isset($_SESSION['language']) && $this->isLanguageActive($_SESSION['language'])) {
            return $_SESSION['language'];
        }

        // Fallback auf DB erste aktive Sprache
        $res = $this->_database->query("SELECT iso_639_1 FROM settings_languages WHERE active = 1 ORDER BY id LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            return $row['iso_639_1'];
        }

        // Fallback hartkodiert (z.B. deutsch)
        return 'de';
    }

    private function isLanguageActive(string $lang): bool
    {
        $stmt = $this->_database->prepare("SELECT 1 FROM settings_languages WHERE iso_639_1 = ? AND active = 1");
        $stmt->bind_param("s", $lang);
        $stmt->execute();
        $stmt->store_result();
        $active = $stmt->num_rows === 1;
        $stmt->close();

        return $active;
    }

    /**
     * Gibt alle aktiven Sprachen als Array zurück
     * Beispiel: [ ['iso_639_1'=>'en', 'name_en'=>'English', ...], ... ]
     */
    public function getActiveLanguages(): array
    {
        $result = $this->_database->query("SELECT * FROM settings_languages WHERE active = 1 ORDER BY name_en");
        $languages = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $languages[] = $row;
            }
        }

        return $languages;
    }

    /**
     * Gibt die Sprachdaten zu einem ISO-Code zurück
     */
    public function getLanguageByIso(string $iso): ?array
    {
        $stmt = $this->_database->prepare("SELECT * FROM settings_languages WHERE iso_639_1 = ? AND active = 1");
        $stmt->bind_param("s", $iso);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $stmt->close();

        return $data ?: null;
    }

    public function parseMultilang(string $text): string
    {
        // Pattern für aktuellen Sprachblock, z.B. [[lang:de]]... bis [[lang:xx]] oder Ende
        $pattern = '/\[\[lang:' . preg_quote($this->currentLanguage, '/') . '\]\](.*?)(?=(\[\[lang:|\z))/s';

        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }

        // Falls kein Sprachblock gefunden wird, als Fallback den kompletten Text zurückgeben
        return $text;
    }


}

