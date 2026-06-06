<?php

namespace nexpell;

if (class_exists(__NAMESPACE__ . '\\LanguageService', false)) {
    return;
}

class LanguageService
{
    private \mysqli $_database;

    public string $currentLanguage = 'en';

    public array $module = [];
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

        $this->currentLanguage = $this->resolveInitialLanguage();
        $_SESSION['language'] = $this->currentLanguage;
    }

    public function autoLoadActiveModule(bool $isAdmin = false): void
    {
        if (empty($GLOBALS['nx_active_module'])) {
            return;
        }

        $rawModule = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$GLOBALS['nx_active_module']);
        $module = $rawModule;

        if ($isAdmin) {
            $resolved = $this->resolveAdminModuleName($rawModule);
            if ($resolved !== '') {
                $module = $resolved;
            } elseif (str_starts_with($rawModule, 'admin_')) {
                $module = substr($rawModule, 6);
            }
        }

        $this->readModule($module, $isAdmin);
    }

    public function readModule(string $module, bool $isAdmin = false): void
    {
        $language = $this->currentLanguage;
        $basePath = rtrim(BASE_PATH, '/') . '/';

        $coreBasePath = $isAdmin
            ? $basePath . 'admin/languages'
            : $basePath . 'languages';

        if (($isAdmin && !$this->baseLoadedAdmin) || (!$isAdmin && !$this->baseLoadedFrontend)) {
            $fallbackBase = "{$coreBasePath}/{$this->fallbackLanguage}/base.php";
            if (file_exists($fallbackBase)) {
                $language_array = [];
                include $fallbackBase;
                if (is_array($language_array)) {
                    $this->fallback = array_replace($this->fallback, $language_array);
                }
            }

            $baseFile = "{$coreBasePath}/{$language}/base.php";
            if (file_exists($baseFile)) {
                $language_array = [];
                include $baseFile;
                if (is_array($language_array)) {
                    $this->module = array_replace($this->module, $language_array);
                }
            }

            $isAdmin ? $this->baseLoadedAdmin = true : $this->baseLoadedFrontend = true;
        }

        $coreCandidates = [$module];
        if ($isAdmin && !str_starts_with($module, 'admin_')) {
            $coreCandidates[] = 'admin_' . $module;
        }

        foreach ($coreCandidates as $candidate) {
            $coreFile = "{$coreBasePath}/{$language}/{$candidate}.php";
            if (!is_file($coreFile)) {
                continue;
            }

            $language_array = [];
            include $coreFile;

            if (!empty($language_array) && is_array($language_array)) {
                $this->module = array_replace($this->module, $language_array);
            }
        }

        $pluginLangPaths = $this->getPluginLanguagePaths($module);
        $pluginFileCandidates = [$module];
        if ($isAdmin && !str_starts_with($module, 'admin_')) {
            $pluginFileCandidates[] = 'admin_' . $module;
        }

        foreach ($pluginLangPaths as $pluginLangPath) {
            foreach ($pluginFileCandidates as $candidate) {
                $pluginFallback = "{$pluginLangPath}{$this->fallbackLanguage}/{$candidate}.php";
                if (file_exists($pluginFallback)) {
                    $language_array = [];
                    include $pluginFallback;
                    if (is_array($language_array)) {
                        $this->fallback = array_replace($this->fallback, $language_array);
                    }
                }

                $pluginFile = "{$pluginLangPath}{$language}/{$candidate}.php";
                if (file_exists($pluginFile)) {
                    $language_array = [];
                    include $pluginFile;
                    if (is_array($language_array)) {
                        $this->module = array_replace($this->module, $language_array);
                    }
                }
            }
        }
    }

    protected function resolveAdminModuleName(string $adminRoute): string
    {
        if ($adminRoute === '' || !$this->_database) {
            return '';
        }

        $stmt = $this->_database->prepare("SELECT modulname, admin_file FROM settings_plugins WHERE activate = 1");
        if (!$stmt) {
            return '';
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $resolved = '';

        while ($row = $res->fetch_assoc()) {
            $files = array_map('trim', explode(',', (string)($row['admin_file'] ?? '')));
            if (in_array($adminRoute, $files, true)) {
                $resolved = (string)($row['modulname'] ?? '');
                break;
            }
        }

        $stmt->close();
        return $resolved;
    }

    protected function getPluginLanguagePaths(string $module): array
    {
        $basePath = rtrim(BASE_PATH, '/') . '/';
        $paths = [];

        $candidates = [
            $basePath . "includes/plugins/{$module}/languages/",
            $basePath . "__plugins/{$module}/languages/",
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $paths[] = $candidate;
            }
        }

        $stmt = $this->_database->prepare(
            "SELECT path FROM settings_plugins WHERE activate = 1 AND modulname = ? LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("s", $module);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            $pluginPath = trim((string)($row['path'] ?? ''));
            if ($pluginPath !== '') {
                if (!preg_match('~^([a-zA-Z]:[\\\\/]|/)~', $pluginPath)) {
                    $pluginPath = $basePath . ltrim($pluginPath, "/\\");
                }
                $pluginPath = rtrim($pluginPath, "/\\") . '/languages/';
                if (is_dir($pluginPath)) {
                    $paths[] = $pluginPath;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    protected function loadFallback(string $module, bool $isAdmin = false): void
    {
        $basePath = $isAdmin
            ? $_SERVER['DOCUMENT_ROOT'] . '/admin/languages'
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

    public function readPluginModule(string $pluginName): void
    {
        $this->readModule($pluginName, false);
    }

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

    public function setLanguage(string $lang): void
    {
        $this->currentLanguage = $this->normalizeLanguageCode($lang);
    }

    public function detectLanguage(): string
    {
        return $this->currentLanguage;
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

    protected function resolveInitialLanguage(): string
    {
        $lang = (string)($_SESSION['language'] ?? '');

        if ($lang === '') {
            $lang = (string)($GLOBALS['default_language'] ?? '');
        }

        if ($lang === '' && $this->_database) {
            $res = $this->_database->query("SELECT default_language FROM settings LIMIT 1");
            if ($res && ($row = $res->fetch_assoc())) {
                $lang = (string)($row['default_language'] ?? '');
            }
        }

        return $this->normalizeLanguageCode($lang !== '' ? $lang : 'en');
    }

    protected function normalizeLanguageCode(string $lang): string
    {
        $lang = strtolower(trim($lang));
        if ($lang === '') {
            return 'en';
        }

        $lang = str_replace('-', '_', $lang);
        if (str_contains($lang, '_')) {
            $lang = explode('_', $lang, 2)[0];
        }

        return preg_replace('/[^a-z]/', '', $lang) ?: 'en';
    }
}
