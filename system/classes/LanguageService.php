<?php

namespace nexpell;

class LanguageService
{
    private \mysqli $_database;

    public string $currentLanguage;

    /** Aktive Sprache (merged) */
    #protected array $module = [];
    public array $module = [];

    /** Fallback-Sprache (z. B. EN) */
    protected array $fallback = [];

    protected string $fallbackLanguage = 'en';

    protected bool $baseLoadedFrontend = false;
    protected bool $baseLoadedAdmin = false;

    public function __construct(\mysqli $database)
    {
        $this->_database = $database;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['language'])) {
            $this->currentLanguage = $_SESSION['language'];
        } else {
            $res = $this->_database->query("SELECT default_language FROM settings LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                $this->currentLanguage = $row['default_language'] ?: 'de';
            } else {
                $this->currentLanguage = 'de';
            }
            $_SESSION['language'] = $this->currentLanguage;
        }
    }

    /* ==========================================================
       CORE MODULE LOADER (base + fallback + merge)
    ========================================================== */

public function readModule(string $module, bool $isAdmin = false): void
{
    // 🔥 AKTIVE SPRACHE AUS DEM SERVICE
    $language = $this->currentLanguage;

    $basePath = $isAdmin
        ? $_SERVER['DOCUMENT_ROOT'] . '/admin/language'
        : $_SERVER['DOCUMENT_ROOT'] . '/languages';

    /* ---------- BASE + FALLBACK (1x pro Kontext) ---------- */
    if (($isAdmin && !$this->baseLoadedAdmin) || (!$isAdmin && !$this->baseLoadedFrontend)) {

        // 🔹 Fallback base (EN)
        $fallbackBase = "{$basePath}/{$this->fallbackLanguage}/base.php";
        if (file_exists($fallbackBase)) {
            $language_array = [];
            include $fallbackBase;
            if (is_array($language_array)) {
                $this->fallback = array_replace($this->fallback, $language_array);
            }
        }

        // 🔹 Aktive Sprache base
        $baseFile = "{$basePath}/{$language}/base.php";
        if (file_exists($baseFile)) {
            $language_array = [];
            include $baseFile;
            if (is_array($language_array)) {
                $this->module = array_replace($this->module, $language_array);
            }
        }

        $isAdmin
            ? $this->baseLoadedAdmin = true
            : $this->baseLoadedFrontend = true;
    }

    /* ---------- Fallback-Modul ---------- */
    $this->loadFallback($module, $isAdmin);

    /* ---------- Aktives Sprachmodul ---------- */
    $file = "{$basePath}/{$language}/{$module}.php";
    if (!file_exists($file)) {
        return;
    }

    $language_array = [];
    include $file;

    if (is_array($language_array)) {
        $this->module = array_replace($this->module, $language_array);
    }
}



    public function autoLoadActiveModule(bool $isAdmin = false): void
    {
        if (empty($GLOBALS['nx_active_module'])) {
            return;
        }

        $module = preg_replace('/[^a-zA-Z0-9_-]/', '', $GLOBALS['nx_active_module']);
        $this->readModule($module, $isAdmin);
    }


    /* ==========================================================
       FALLBACK LOADER (intern)
    ========================================================== */

    protected function loadFallback(string $module, bool $isAdmin = false): void
    {
        $basePath = $isAdmin
            ? $_SERVER['DOCUMENT_ROOT'] . '/admin/language'
            : $_SERVER['DOCUMENT_ROOT'] . '/languages';

        $file = "{$basePath}/{$this->fallbackLanguage}/{$module}.php";

        if (!file_exists($file)) {
            return;
        }

        $language_array = [];
        include $file;

        if (is_array($language_array)) {
            $this->fallback = array_replace($this->fallback, $language_array);
        }
    }


    /* ==========================================================
       PLUGIN MODULE LOADER (mit Fallback)
    ========================================================== */

    public function readPluginModule(string $pluginName): void
    {
        $lang = $this->currentLanguage;

        // 🔹 Fallback (EN)
        $fallbackFile = $_SERVER['DOCUMENT_ROOT']
            . "/includes/plugins/{$pluginName}/languages/{$this->fallbackLanguage}/{$pluginName}.php";

        if (file_exists($fallbackFile)) {
            $language_array = [];
            include $fallbackFile;
            if (is_array($language_array)) {
                $this->fallback = array_replace($this->fallback, $language_array);
            }
        }

        // 🔹 Aktive Sprache
        $file = $_SERVER['DOCUMENT_ROOT']
            . "/includes/plugins/{$pluginName}/languages/{$lang}/{$pluginName}.php";

        if (!file_exists($file)) {
            return;
        }

        $language_array = [];
        include $file;

        if (is_array($language_array)) {
            $this->module = array_replace($this->module, $language_array);
        }
    }

    /* ==========================================================
       GET TEXT (mit Fallback)
    ========================================================== */

    public function get(string $key): string
    {
        if (isset($this->module[$key])) {
            return $this->module[$key];
        }

        if (isset($this->fallback[$key])) {
            return $this->fallback[$key];
        }

        return "[{$key}]";
    }

    /* ==========================================================
       LANGUAGE HANDLING
    ========================================================== */

    public function setLanguage(string $lang): void
    {
        $this->currentLanguage = $lang;
        $_SESSION['language'] = $lang;
    }

    public function detectLanguage(): string
    {
        if (isset($_SESSION['language']) && $this->isLanguageActive($_SESSION['language'])) {
            return $_SESSION['language'];
        }

        $res = $this->_database->query(
            "SELECT iso_639_1 FROM settings_languages WHERE active = 1 ORDER BY id LIMIT 1"
        );

        if ($res && $row = $res->fetch_assoc()) {
            $_SESSION['language'] = $row['iso_639_1'];
            return $row['iso_639_1'];
        }

        $_SESSION['language'] = 'de';
        return 'de';
    }

    private function isLanguageActive(string $lang): bool
    {
        $stmt = $this->_database->prepare(
            "SELECT 1 FROM settings_languages WHERE iso_639_1 = ? AND active = 1"
        );
        $stmt->bind_param("s", $lang);
        $stmt->execute();
        $stmt->store_result();
        $active = $stmt->num_rows === 1;
        $stmt->close();

        return $active;
    }

    public function getActiveLanguages(): array
    {
        $res = $this->_database->query(
            "SELECT * FROM settings_languages WHERE active = 1 ORDER BY name_en"
        );

        $languages = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $languages[] = $row;
            }
        }

        return $languages;
    }

    public function getLanguageByIso(string $iso): ?array
    {
        $stmt = $this->_database->prepare(
            "SELECT * FROM settings_languages WHERE iso_639_1 = ? AND active = 1"
        );
        $stmt->bind_param("s", $iso);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $stmt->close();

        return $data ?: null;
    }

    /* ==========================================================
       MULTILANG CONTENT [[lang:xx]]
    ========================================================== */

    public function parseMultilang(string $text): string
    {
        $pattern = '/\[\[lang:' . preg_quote($this->currentLanguage, '/') . '\]\](.*?)(?=(\[\[lang:|\z))/s';
        if (preg_match($pattern, $text, $m)) {
            return trim($m[1]);
        }

        $patternFallback = '/\[\[lang:' . preg_quote($this->fallbackLanguage, '/') . '\]\](.*?)(?=(\[\[lang:|\z))/s';
        if (preg_match($patternFallback, $text, $m)) {
            return trim($m[1]);
        }

        return $text;
    }
}
