<?php
// /system/core/init_widget.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require_once BASE_PATH . '/system/config.inc.php';

// === DB ===============================================================
global $_database;
if (!isset($_database) || !($_database instanceof mysqli)) {
    $_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($_database->connect_errno) {
        throw new RuntimeException('DB connect error: ' . $_database->connect_error);
    }
    $_database->set_charset('utf8mb4');
}

// === Helper / Klassen laden ===========================================
foreach ([BASE_PATH.'/system/functions', BASE_PATH.'/system/classes'] as $dir) {
    if (is_dir($dir)) foreach (glob($dir.'/*.php') as $f) require_once $f;
}

// === Sicherheits-Fallbacks ============================================

if (!function_exists('safe_query')) {
    function safe_query(string $query) {
        global $_database;
        if (!($_database instanceof mysqli)) {
            $_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $_database->set_charset('utf8mb4');
        }
        $res = $_database->query($query);
        if (!$res) throw new RuntimeException('SQL-Fehler: '.$_database->error.' — '.$query);
        return $res;
    }
}

if (!function_exists('getusername')) {
    function getusername(int $userID): string {
        $res = safe_query("SELECT username FROM users WHERE userID=".(int)$userID." LIMIT 1");
        if ($row = $res->fetch_assoc()) return $row['username'];
        return 'Unknown';
    }
}

// === Avatar-Erkennung mit Pfad-Fallbacks ==============================
if (!function_exists('getavatar')) {
    function getavatar(int $userID): string {
        $candidates = ['avatar','userpic','picture','userimage'];
        $column = null;
        foreach ($candidates as $col) {
            $chk = safe_query("SHOW COLUMNS FROM users LIKE '".$col."'");
            if ($chk && $chk->num_rows > 0) { $column = $col; break; }
        }
        if (!$column) return '/images/avatars/noavatar.png';

        $res = safe_query("SELECT `$column` FROM users WHERE userID=".(int)$userID." LIMIT 1");
        if ($row = $res->fetch_assoc()) {
            $file = trim($row[$column]);
            if ($file !== '') {
                $paths = [
                    '/images/avatars/'.$file,
                    '/includes/images/avatars/'.$file,
                    '/uploads/avatars/'.$file,
                    '/images/userpics/'.$file
                ];
                foreach ($paths as $p) {
                    if (file_exists(BASE_PATH.$p)) {
                        return $p;
                    }
                }
            }
        }
        return '/images/avatars/noavatar.png';
    }
}


// === Sprachsystem =====================================================
if (!class_exists('nexpell\\LanguageService') && file_exists(BASE_PATH.'/system/classes/LanguageService.php'))
    require_once BASE_PATH.'/system/classes/LanguageService.php';

// Kompatibilität: multiLanguage
if (!class_exists('multiLanguage') && class_exists('nexpell\\LanguageService')) {
    class multiLanguage extends \nexpell\LanguageService {
        public function __construct($db = null) {
            if (!($db instanceof \mysqli)) $db = $GLOBALS['_database'] ?? null;
            parent::__construct($db);
        }

        // alter Aufruf detectLanguages()
        public function detectLanguages() { return $this->detectLanguage(); }

        // alter Aufruf getTextByLanguage($de, $en)
        // ➜ erlaubt 1 oder 2 Parameter
        public function getTextByLanguage(string $text_de, ?string $text_en = null): string {
            $lang = $_SESSION['language'] ?? 'de';
            if ($text_en === null) return $text_de;
            return ($lang === 'de' ? $text_de : $text_en);
        }
    }
}

// === Template-System ==================================================
if (!class_exists('Template') && file_exists(BASE_PATH.'/system/classes/Template.php'))
    require_once BASE_PATH.'/system/classes/Template.php';

global $tpl;
if (!isset($tpl) || !$tpl instanceof Template) $tpl = new Template();
$GLOBALS['tpl'] = $tpl;

// === SEO-Handler ======================================================
if (!class_exists('nexpell\\SeoUrlHandler') && file_exists(BASE_PATH.'/system/classes/SeoUrlHandler.php'))
    require_once BASE_PATH.'/system/classes/SeoUrlHandler.php';

// === LanguageService-Instanz =========================================
global $languageService;
if (!isset($languageService) || !$languageService instanceof \nexpell\LanguageService) {
    $languageService = new \nexpell\LanguageService($_database);
    $langCode = $_SESSION['language'] ?? 'de';
    $languageService->setLanguage($langCode);
    $_SESSION['language'] = $langCode;
}
$GLOBALS['languageService'] = $languageService;

// multiLanguage-Fallback-Instanz
if (!isset($GLOBALS['multiLanguage']) || !$GLOBALS['multiLanguage'] instanceof multiLanguage)
    $GLOBALS['multiLanguage'] = new multiLanguage($_database);

// === Debug ============================================================
if (defined('NXB_DEBUG') && NXB_DEBUG) {
    error_log('[NXB init_widget] OK — Sprache: '.($_SESSION['language'] ?? 'de'));
}
