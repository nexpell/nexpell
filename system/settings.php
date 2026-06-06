<?php


// -- SYSTEM ERROR DISPLAY -- //
include('error.php'); // Fehlerbehandlungsdatei einbinden
ini_set('display_errors', 1); // Alle Fehler im Entwicklung-Modus anzeigen

// -- PHP FUNCTION CHECK -- //
if (!function_exists('mb_substr')) {
    // ÃœberprÃ¼fen, ob die mbstring-Erweiterung aktiviert ist
    system_error('PHP Multibyte String Support is not enabled.', 0); // Fehler ausgeben, wenn die Funktion nicht existiert
}

// -- ERROR REPORTING -- //
define('DEBUG', "ON"); // Debugging-Modus (ON fÃ¼r Entwicklungsmodus, OFF fÃ¼r Produktionsmodus)
if (DEBUG === 'ON') {
    error_reporting(E_ALL); // Alle Fehler im Entwicklungsmodus anzeigen
} else {
    error_reporting(0); // Fehler im Produktionsmodus unterdrÃ¼cken
}

// -- SET ENCODING FOR MB-FUNCTIONS -- //
mb_internal_encoding("UTF-8"); // Die interne Zeichencodierung auf UTF-8 setzen

// -- SET INCLUDE-PATH FOR vendors --//
$path = __DIR__.DIRECTORY_SEPARATOR.'components'; // Pfad zum Verzeichnis mit den Komponenten setzen
set_include_path(get_include_path() . PATH_SEPARATOR .$path); // Include-Pfad fÃ¼r externe Bibliotheken erweitern

// -- SET HTTP ENCODING -- //
header('content-type: text/html; charset=utf-8'); // Den HTTP-Header fÃ¼r die richtige Zeichencodierung setzen

// -- INSTALL CHECK -- //
if (DEBUG == "OFF" && file_exists('install/index.php')) {
    // ÃœberprÃ¼fen, ob das Installationsverzeichnis noch vorhanden ist, falls der Debug-Modus ausgeschaltet ist
    system_error(
        'The install-folder exists. Did you run the <a href="install/">Installer</a>?<br>
        If yes, please remove the install-folder.',
        0
    );
}

// -- CONNECTION TO MYSQL -- //
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.inc.php';
}

if (!isset($GLOBALS['_database'])) {
    $_database = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($_database->connect_error) {
        die("âŒ Fehler bei der Verbindung zur Datenbank: " . $_database->connect_error);
    }

    $_database->query("SET NAMES 'utf8mb4'");
    $_database->query("SET sql_mode = ''");

    $GLOBALS['_database'] = $_database; // in $GLOBALS registrieren, falls in anderen Bereichen benÃ¶tigt
}

// -- GENERAL PROTECTIONS -- //
if (function_exists("globalskiller") === false) {
    // Sicherstellen, dass die Funktion zur ZerstÃ¶rung nicht-systemrelevanter Variablen vorhanden ist
    function globalskiller() {
        // LÃ¶scht alle nicht-systemrelevanten globalen Variablen
        $global = array(
            'GLOBALS', '_POST', '_GET', '_COOKIE', '_FILES', '_SERVER', '_ENV', '_REQUEST', '_SESSION', '_database'
        );

        // Durchlaufe alle globalen Variablen
        foreach ($GLOBALS as $key => $val) {
            // ÃœberprÃ¼fe, ob der SchlÃ¼ssel nicht zu den systemrelevanten Variablen gehÃ¶rt
            if (!in_array($key, $global)) {
                // LÃ¶sche die Variable, falls sie kein Array ist
                if (is_array($val)) {
                    unset($GLOBALS[$key]); // LÃ¶sche Arrays
                } else {
                    unset($GLOBALS[$key]); // LÃ¶sche nicht-Array Variablen
                }
            }
        }
    }
}

if (function_exists("unset_array") === false) {
    // Sicherstellen, dass die Funktion zum LÃ¶schen von Arrays existiert
    function unset_array($array) {
        foreach ($array as $key) {
            if (is_array($key)) {
                unset_array($key); // Rekursiv Arrays lÃ¶schen
            } else {
                unset($key); // LÃ¶sche einzelne Elemente
            }
        }
    }
}

globalskiller(); // Funktion aufrufen, um nicht benÃ¶tigte globale Variablen zu lÃ¶schen

// -- LEGACY LANGUAGE COMPATIBILITY -- //
// Older plugins may still instantiate multiLanguage directly.
if (!class_exists('nexpell\\LanguageService')) {
    $languageServiceClass = __DIR__ . '/classes/LanguageService.php';
    if (file_exists($languageServiceClass)) {
        require_once $languageServiceClass;
    }
}

if (!class_exists('multiLanguage') && class_exists('nexpell\\LanguageService')) {
    class multiLanguage extends \nexpell\LanguageService
    {
        public function __construct($db = null)
        {
            if (!($db instanceof \mysqli)) {
                $db = $GLOBALS['_database'] ?? null;
            }
            parent::__construct($db);
        }

        public function detectLanguages()
        {
            return $this->detectLanguage();
        }

        public function getTextByLanguage(string $text_de, ?string $text_en = null): string
        {
            $lang = $_SESSION['language'] ?? 'de';
            if ($text_en === null) {
                return $text_de;
            }
            return ($lang === 'de' ? $text_de : $text_en);
        }
    }
}

if (isset($_GET[ 'site' ])) {
    // Wenn der Parameter 'site' in der URL vorhanden ist, diesen setzen
    $site = $_GET[ 'site' ];
} else {
    $site = null; // Andernfalls auf null setzen
}

// -- VALIDATE QUERY STRING -- //
if ($site != "search") {
    // ÃœberprÃ¼fen, ob die Seite nicht 'search' ist, um SQL-Injektionen zu verhindern
    $request = strtolower(urldecode($_SERVER[ 'QUERY_STRING' ])); // Anfrage-String in Kleinbuchstaben dekodieren
    $protarray = array(
        "union", "select", "into", "where", "update ", "from", "/*", "set ", "users", // Tabelle geÃ¤ndert von 'user' auf 'users'
        "users(", "users`", "user_groups", "phpinfo", "escapeshellarg", "exec", "fopen", "fwrite",
        "escapeshellcmd", "passthru", "proc_close", "proc_get_status", "proc_nice", "proc_open",
        "proc_terminate", "shell_exec", "system", "telnet", "ssh", "cmd", "mv", "chmod", "chdir",
        "locate", "killall", "passwd", "kill", "script", "bash", "perl", "mysql", "~root", ".history",
        "~nobody", "getenv"
    );
    // Ersetze alle potenziell gefÃ¤hrlichen Teile der Anfrage durch '*'
    $check = str_replace($protarray, '*', $request);
    if ($request != $check) {
        // Wenn sich die Anfrage nach der Ersetzung unterscheidet, wurde eine potenziell gefÃ¤hrliche Anfrage entdeckt
        system_error("Invalid request detected.");
    }
}


// -- SECURITY SLASHES FUNCTION -- //
// Diese Funktion stellt sicher, dass alle Eingabewerte aus $_POST, $_GET, $_COOKIE und $_REQUEST
// gegen SQL-Injektionen geschÃ¼tzt werden, indem sie Escaping durchfÃ¼hren.
/*function security_slashes(&$array)
{
    global $_database;

    // Durchlaufe jedes Element im Array
    foreach ($array as $key => $value) {
        if (is_array($array[ $key ])) {
            // Rekursiv auf verschachtelte Arrays anwenden
            security_slashes($array[ $key ]);
        } else {
            $tmp = $value;
            if (function_exists("mysqli_real_escape_string")) {
                // Sicherstellen, dass wir eine sichere Methode fÃ¼r das Escaping verwenden
                $array[ $key ] = $_database->escape_string($tmp);
            } else {
                // Fallback auf addslashes, falls mysqli_real_escape_string nicht verfÃ¼gbar ist
                $array[ $key ] = addslashes($tmp);
            }
            unset($tmp);
        }
    }
}

// Aufruf der Funktion fÃ¼r alle globalen Eingabewerte
security_slashes($_POST);
security_slashes($_COOKIE);
security_slashes($_GET);
security_slashes($_REQUEST);*/

// -- ESCAPE QUERY FUNCTION FOR TABLE -- //
// Diese Funktion sorgt dafÃ¼r, dass SQL-Abfragen vor der AusfÃ¼hrung sicher sind
/*function escapestring($mquery) {
    global $_database;
    
    // ÃœberprÃ¼fe, ob mysqli_real_escape_string verfÃ¼gbar ist und verwende es
    if (function_exists("mysqli_real_escape_string")) {
        $mquery = $_database->escape_string($mquery);
    } else {
        // Fallback auf addslashes
        $mquery = addslashes($mquery);
    }
    return $mquery;
}*/

// -- MYSQL FETCH FUNCTION -- //
// Diese Funktion fetcht ein assoziatives Array aus einem MySQL-Abfrageergebnis
function mysqli_fetch_assocss($mquery) {
    if(isset($mquery)) {
        $putquery = '0';
    } else {
        // Hole das assoziative Array der Abfrageergebnisse
        $putquery = mysqli_fetch_assoc($mquery);
    }

    // Ausgabe der Ergebnisse (Debugging)
    print_r($putquery);

    return $putquery;
}

// -- MYSQL QUERY FUNCTION -- //
// Diese Funktion fÃ¼hrt eine SQL-Abfrage aus und Ã¼berprÃ¼ft auf potenziell unsichere Abfragen
$_mysql_querys = array();
function safe_query($query = "")
{
    global $_database;
    global $_mysql_querys;

    $normalizeModulname = static function (string $value): string {
        $value = trim($value);
        $value = trim($value, ',');
        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
        return strtolower((string)$value);
    };

    $splitSqlCsv = static function (string $csv): array {
        $parts = [];
        $buf = '';
        $inQuote = false;
        $quoteChar = '';
        $len = strlen($csv);
        for ($i = 0; $i < $len; $i++) {
            $ch = $csv[$i];
            if ($inQuote) {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $i++;
                    $buf .= $csv[$i];
                    continue;
                }
                if ($ch === $quoteChar) {
                    $inQuote = false;
                    $quoteChar = '';
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $inQuote = true;
                $quoteChar = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === ',') {
                $parts[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        $parts[] = trim($buf);
        return $parts;
    };

    // Compatibility for legacy plugin installers that still write into
    // navigation base tables using a removed "name" column.
    if (is_string($query) && stripos($query, 'insert') !== false) {
        $query = preg_replace_callback(
            '/(INSERT\s+(?:IGNORE\s+)?INTO\s+`?(navigation_dashboard_categories|navigation_dashboard_links|navigation_website_main|navigation_website_sub)`?\s*)\((.*?)\)(\s*VALUES\s*)(.*)$/is',
            static function (array $m): string {
                $prefix = $m[1];
                $rawCols = $m[3];
                $valuesAndSuffix = $m[5];

                $cols = array_map(
                    static function (string $c): string {
                        return strtolower(trim(str_replace('`', '', $c)));
                    },
                    explode(',', $rawCols)
                );

                $nameIdx = array_search('name', $cols, true);
                if ($nameIdx === false) {
                    return $m[0];
                }

                $newCols = $cols;
                array_splice($newCols, (int)$nameIdx, 1);

                preg_match_all(
                    '/\((?:[^()\'"]+|\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*")*\)/s',
                    $valuesAndSuffix,
                    $tupleMatches
                );
                if (empty($tupleMatches[0])) {
                    return $m[0];
                }

                $splitCsv = static function (string $csv): array {
                    $parts = [];
                    $buf = '';
                    $inQuote = false;
                    $quoteChar = '';
                    $len = strlen($csv);
                    for ($i = 0; $i < $len; $i++) {
                        $ch = $csv[$i];
                        if ($inQuote) {
                            $buf .= $ch;
                            if ($ch === '\\' && $i + 1 < $len) {
                                $i++;
                                $buf .= $csv[$i];
                                continue;
                            }
                            if ($ch === $quoteChar) {
                                $inQuote = false;
                                $quoteChar = '';
                            }
                            continue;
                        }
                        if ($ch === "'" || $ch === '"') {
                            $inQuote = true;
                            $quoteChar = $ch;
                            $buf .= $ch;
                            continue;
                        }
                        if ($ch === ',') {
                            $parts[] = trim($buf);
                            $buf = '';
                            continue;
                        }
                        $buf .= $ch;
                    }
                    $parts[] = trim($buf);
                    return $parts;
                };

                $rebuiltTuples = [];
                foreach ($tupleMatches[0] as $tuple) {
                    $inner = substr($tuple, 1, -1);
                    $vals = $splitCsv($inner);
                    if (count($vals) <= (int)$nameIdx) {
                        return $m[0];
                    }
                    array_splice($vals, (int)$nameIdx, 1);
                    $rebuiltTuples[] = '(' . implode(', ', $vals) . ')';
                }

                $tail = trim($valuesAndSuffix);
                $lastTuple = end($tupleMatches[0]);
                $lastPos = strrpos($tail, $lastTuple);
                $suffix = '';
                if ($lastPos !== false) {
                    $suffix = substr($tail, $lastPos + strlen($lastTuple));
                }

                return $prefix
                    . '(`' . implode('`, `', $newCols) . '`) VALUES '
                    . implode(', ', $rebuiltTuples)
                    . $suffix;
            },
            $query
        );
    }

    if (is_string($query) && stripos($query, 'settings_plugins') !== false) {
        $query = preg_replace_callback(
            '/(INSERT\s+(?:IGNORE\s+)?INTO\s+`?settings_plugins`?\s*)\((.*?)\)(\s*VALUES\s*)(.*)$/is',
            static function (array $m) use ($splitSqlCsv, $normalizeModulname): string {
                $cols = array_map(
                    static function (string $c): string {
                        return strtolower(trim(str_replace('`', '', $c)));
                    },
                    explode(',', $m[2])
                );

                $modIdx = array_search('modulname', $cols, true);
                if ($modIdx === false) {
                    return $m[0];
                }

                preg_match_all(
                    '/\((?:[^()\'"]+|\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*")*\)/s',
                    $m[4],
                    $tupleMatches
                );
                if (empty($tupleMatches[0])) {
                    return $m[0];
                }

                $rebuiltTuples = [];
                foreach ($tupleMatches[0] as $tuple) {
                    $inner = substr($tuple, 1, -1);
                    $vals = $splitSqlCsv($inner);
                    if (!isset($vals[$modIdx])) {
                        return $m[0];
                    }

                    $rawValue = trim((string)$vals[$modIdx]);
                    if (preg_match('/^([\'"])(.*)\\1$/s', $rawValue, $match)) {
                        $quote = $match[1];
                        $normalized = $normalizeModulname((string)$match[2]);
                        $vals[$modIdx] = $quote . $normalized . $quote;
                    } else {
                        $vals[$modIdx] = "'" . $normalizeModulname($rawValue) . "'";
                    }

                    $rebuiltTuples[] = '(' . implode(', ', $vals) . ')';
                }

                $tail = trim($m[4]);
                $lastTuple = end($tupleMatches[0]);
                $lastPos = strrpos($tail, $lastTuple);
                $suffix = '';
                if ($lastPos !== false) {
                    $suffix = substr($tail, $lastPos + strlen($lastTuple));
                }

                return $m[1] . '(' . $m[2] . ')' . $m[3] . implode(', ', $rebuiltTuples) . $suffix;
            },
            $query
        );

        $query = preg_replace_callback(
            '/(UPDATE\s+`?settings_plugins`?\s+SET\s+.*?\bmodulname\b\s*=\s*)([\'"])(.*?)(\2)/is',
            static function (array $m) use ($normalizeModulname): string {
                return $m[1] . $m[2] . $normalizeModulname((string)$m[3]) . $m[2];
            },
            $query
        );
    }

    // Legacy compatibility: normalize old inserts that still use
    // (name, lang, translation/text/value) -> (content_key, language, content).
    // This is intentionally table-agnostic to also support older plugin
    // tables like plugins_about, not only *_lang tables.
    if (is_string($query) && stripos($query, 'insert') !== false) {
        // Legacy "INSERT ... SET name=..., lang=..., translation=..." syntax.
        if (stripos($query, ' set ') !== false) {
            $query = preg_replace('/(?<![a-z0-9_])`?name`?\s*=/i', '`content_key` =', $query);
            $query = preg_replace('/(?<![a-z0-9_])`?lang`?\s*=/i', '`language` =', $query);
            $query = preg_replace('/(?<![a-z0-9_])`?translation`?\s*=/i', '`content` =', $query);
            $query = preg_replace('/(?<![a-z0-9_])`?text`?\s*=/i', '`content` =', $query);
            $query = preg_replace('/(?<![a-z0-9_])`?value`?\s*=/i', '`content` =', $query);
        }

        $query = preg_replace_callback(
            '/(INSERT\s+(?:IGNORE\s+)?INTO\s+`?[^`\s(]+`?\s*)\((.*?)\)(\s*VALUE(?:S)?)/is',
            static function (array $m): string {
                $rawCols = $m[2];
                $cols = array_map(
                    static function (string $c): string {
                        return strtolower(trim(str_replace('`', '', $c)));
                    },
                    explode(',', $rawCols)
                );

                $hasLegacyName = in_array('name', $cols, true);
                $hasLegacyLang = in_array('lang', $cols, true);
                $hasLegacyContent = in_array('translation', $cols, true)
                    || in_array('text', $cols, true)
                    || in_array('value', $cols, true);

                $hasModernLang = in_array('language', $cols, true);
                $hasModernContent = in_array('content', $cols, true);

                // Handle both fully-legacy and mixed column lists, e.g.
                // (name, language, content) or (content_key, lang, content).
                if (
                    !$hasLegacyName
                    && !$hasLegacyLang
                    && !$hasLegacyContent
                ) {
                    return $m[0];
                }
                if (!(($hasLegacyLang || $hasModernLang) && ($hasLegacyContent || $hasModernContent))) {
                    return $m[0];
                }

                $map = [
                    'name' => 'content_key',
                    'lang' => 'language',
                    'translation' => 'content',
                    'text' => 'content',
                    'value' => 'content'
                ];

                $changed = false;
                foreach ($cols as $i => $col) {
                    if (isset($map[$col])) {
                        $cols[$i] = $map[$col];
                        $changed = true;
                    }
                }

                if (!$changed || !in_array('language', $cols, true) || !in_array('content', $cols, true)) {
                    return $m[0];
                }

                return $m[1] . '(`' . implode('`, `', $cols) . '`)' . $m[3];
            },
            $query
        );
    }

    // Setze den SQL-Modus fÃ¼r die Verbindung
    $_database->query("SET sql_mode = ''");

    // ÃœberprÃ¼fe, ob die Abfrage keine potenziell gefÃ¤hrlichen UNION-Select-Abfragen enthÃ¤lt
    if (stristr(str_replace(' ', '', $query), "unionselect") === false and
        stristr(str_replace(' ', '', $query), "union(select") === false
    ) {
        $_mysql_querys[] = $query;

        // ÃœberprÃ¼fe, ob die Abfrage leer ist
        if (empty($query)) {
            return false;
        }

        // FÃ¼hre die Abfrage aus und gebe Fehler aus, wenn DEBUG aktiviert ist
        if (DEBUG == "OFF") {
            $result = $_database->query($query) or system_error('Query failed!');
        } else {
            $result = $_database->query($query) or
            system_error(
                '<strong>Query failed</strong> ' . '<ul>' .
                '<li>MySQL error no.: <mark>' . $_database->errno . '</mark></li>' .
                '<li>MySQL error: <mark>' . $_database->error . '</mark></li>' .
                '<li>SQL: <mark>' . $query . '</mark></li>' .
                '</ul>',
                1,
                1
            );
        }
        return $result;
    } else {
        // Abfrage abbrechen, wenn eine unsichere UNION-Abfrage gefunden wurde
        die();
    }
}

// -- GLOBAL SETTINGS -- //
$headlines = '';

// FÃ¼hre eine Abfrage aus, um die aktiven Einstellungen zu holen
$result = safe_query("SELECT * FROM `settings_themes` WHERE `active` = '1'");

// Fehlerbehandlung fÃ¼r das Abfrageergebnis
if ($result && mysqli_num_rows($result) > 0) {
    // Hole die erste Zeile des Ergebnisses als assoziatives Array
    $dx = mysqli_fetch_assoc($result);

    // ÃœberprÃ¼fe, ob die Felder existieren und setze Standardwerte, falls nicht
    $font_family = isset($dx['body1']) ? $dx['body1'] : 'default-font'; // Fallback fÃ¼r Schriftart
    $headlines = isset($dx['headlines']) ? $dx['headlines'] : 'default-headline'; // Fallback fÃ¼r Headlines
} else {
    // Fehlerbehandlung, wenn keine Daten gefunden wurden
    $font_family = 'default-font';
    $headlines = 'default-headline';
}

// CSS- und JS-Dateien
$components = array(
    'css' => array(
        '/components/bootstrap/css/bootstrap-icons.min.css',
        //'/components/css/animate.css',
        '/components/css/page.css',
        '/components/css/headstyles.css',
        '/includes/plugins/navigation/css/navigation.css',
        '/includes/plugins/footer/css/footer.css'
    ),
    'js' => array(
        '/components/jquery/jquery.min.js',
        '/components/bootstrap/js/bootstrap.bundle.min.js',
        //'/components/js/slick.min.js',
        '/components/cookie/cookie-consent.js',
        '/includes/themes/default/js/page.js',
        '/includes/plugins/navigation/js/navigation.js',
        '/includes/plugins/footer/js/footer.js'
    )
);

// Funktion zum PrÃ¼fen, ob die Dateien existieren (CSS und JS)
function check_file_exists($file)
{
    // Basisverzeichnis auf dein Projekt setzen (hier system/settings.php liegt in /public_html/system)
    $baseDir = dirname(__DIR__); // geht 1 Ebene hoch von /system -> /public_html

    // Absoluten Pfad bauen
    $path = $baseDir . '/' . ltrim($file, '/');

    return file_exists($path) ? $file : '';
}


// Dateien nur hinzufÃ¼gen, wenn sie existieren
$valid_css = array_filter($components['css'], 'check_file_exists');
$valid_js = array_filter($components['js'], 'check_file_exists');

// -- Konfiguration und Einstellungen -- //

// Hole alle Einstellungen aus der Tabelle 'settings'
$ds = mysqli_fetch_array(
    safe_query("SELECT * FROM settings")
);

// ZusÃ¤tzliche Einstellungen
$hp_url = $ds['hpurl'];
$hp_title = stripslashes($ds['hptitle']);
#$register_per_ip = $ds['register_per_ip'];
$admin_name = $ds['adminname'];
$admin_email = $ds['adminemail'];
$myclantag = $ds['clantag'];
$myclanname = $ds['clanname'];
$since = $ds['since'];

// SEO-Einstellungen
$keywords = $ds['keywords'];

$closed = (int)$ds['closed'];

// Sprach- und Datumseinstellungen
$default_language = $ds['default_language'];
if (empty($default_language)) {
    $default_language = 'en';
}
$rss_default_language = $ds['default_language'];
if (empty($rss_default_language)) {
    $rss_default_language = 'en';
}


$new_chmod = 0666;

// -- LOGO -- //

// Logo-Abfrage
$dx = safe_query("SELECT * FROM settings_themes WHERE active = '1'");

// Fehlerbehandlung fÃ¼r die Logo-Abfrage
if ($dx && mysqli_num_rows($dx) > 0) {
    $ds = mysqli_fetch_assoc($dx);
    $logo = isset($ds['logo']) ? $ds['logo'] : 'default_logo.png'; // Fallback-Wert
} else {
    // Fehlerbehandlung, wenn keine Daten fÃ¼r Logo gefunden wurden
    $logo = 'default_logo.png'; // Setze Standardlogo, wenn nichts gefunden wurde
}

$row = safe_query("SELECT * FROM settings_themes WHERE active = '1'");
$tmp = mysqli_fetch_assoc(safe_query("SELECT count(themeID) as cnt FROM settings_themes"));
$anzpartners = $tmp[ 'cnt' ];
while ($ds = mysqli_fetch_array($row)) {
       $theme_name = $ds['pfad'];
       #print_r($theme_name);
}

// Abfrage fÃ¼r Partneranzahl
$tmp = safe_query("SELECT count(themeID) as cnt FROM settings_themes");

// Fehlerbehandlung fÃ¼r Partneranzahl
if ($tmp && mysqli_num_rows($tmp) > 0) {
    $tmp_data = mysqli_fetch_assoc($tmp);
    $anzpartners = isset($tmp_data['cnt']) ? $tmp_data['cnt'] : 0; // Fallback auf 0, wenn keine Partner gefunden
} else {
    // Fehlerbehandlung, wenn keine Partneranzahl gefunden wurde
    $anzpartners = 0; // Setze Standardwert auf 0
}
