<?php


// -- SYSTEM ERROR DISPLAY -- //
include('error.php'); // Fehlerbehandlungsdatei einbinden
ini_set('display_errors', 1); // Alle Fehler im Entwicklung-Modus anzeigen

// -- PHP FUNCTION CHECK -- //
if (!function_exists('mb_substr')) {
    // Überprüfen, ob die mbstring-Erweiterung aktiviert ist
    system_error('PHP Multibyte String Support is not enabled.', 0); // Fehler ausgeben, wenn die Funktion nicht existiert
}

// -- ERROR REPORTING -- //
define('DEBUG', "ON"); // Debugging-Modus (ON für Entwicklungsmodus, OFF für Produktionsmodus)
if (DEBUG === 'ON') {
    error_reporting(E_ALL); // Alle Fehler im Entwicklungsmodus anzeigen
} else {
    error_reporting(0); // Fehler im Produktionsmodus unterdrücken
}

// -- SET ENCODING FOR MB-FUNCTIONS -- //
mb_internal_encoding("UTF-8"); // Die interne Zeichencodierung auf UTF-8 setzen

// -- SET INCLUDE-PATH FOR vendors --//
$path = __DIR__.DIRECTORY_SEPARATOR.'components'; // Pfad zum Verzeichnis mit den Komponenten setzen
set_include_path(get_include_path() . PATH_SEPARATOR .$path); // Include-Pfad für externe Bibliotheken erweitern

// -- SET HTTP ENCODING -- //
header('content-type: text/html; charset=utf-8'); // Den HTTP-Header für die richtige Zeichencodierung setzen

// -- INSTALL CHECK -- //
if (DEBUG == "OFF" && file_exists('install/index.php')) {
    // Überprüfen, ob das Installationsverzeichnis noch vorhanden ist, falls der Debug-Modus ausgeschaltet ist
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
        die("❌ Fehler bei der Verbindung zur Datenbank: " . $_database->connect_error);
    }

    $_database->query("SET NAMES 'utf8mb4'");
    $_database->query("SET sql_mode = ''");

    $GLOBALS['_database'] = $_database; // in $GLOBALS registrieren, falls in anderen Bereichen benötigt
}

// -- GENERAL PROTECTIONS -- //
if (function_exists("globalskiller") === false) {
    // Sicherstellen, dass die Funktion zur Zerstörung nicht-systemrelevanter Variablen vorhanden ist
    function globalskiller() {
        // Löscht alle nicht-systemrelevanten globalen Variablen
        $global = array(
            'GLOBALS', '_POST', '_GET', '_COOKIE', '_FILES', '_SERVER', '_ENV', '_REQUEST', '_SESSION', '_database'
        );

        // Durchlaufe alle globalen Variablen
        foreach ($GLOBALS as $key => $val) {
            // Überprüfe, ob der Schlüssel nicht zu den systemrelevanten Variablen gehört
            if (!in_array($key, $global)) {
                // Lösche die Variable, falls sie kein Array ist
                if (is_array($val)) {
                    unset($GLOBALS[$key]); // Lösche Arrays
                } else {
                    unset($GLOBALS[$key]); // Lösche nicht-Array Variablen
                }
            }
        }
    }
}

if (function_exists("unset_array") === false) {
    // Sicherstellen, dass die Funktion zum Löschen von Arrays existiert
    function unset_array($array) {
        foreach ($array as $key) {
            if (is_array($key)) {
                unset_array($key); // Rekursiv Arrays löschen
            } else {
                unset($key); // Lösche einzelne Elemente
            }
        }
    }
}

globalskiller(); // Funktion aufrufen, um nicht benötigte globale Variablen zu löschen

if (isset($_GET[ 'site' ])) {
    // Wenn der Parameter 'site' in der URL vorhanden ist, diesen setzen
    $site = $_GET[ 'site' ];
} else {
    $site = null; // Andernfalls auf null setzen
}

// -- VALIDATE QUERY STRING -- //
if ($site != "search") {
    // Überprüfen, ob die Seite nicht 'search' ist, um SQL-Injektionen zu verhindern
    $request = strtolower(urldecode($_SERVER[ 'QUERY_STRING' ])); // Anfrage-String in Kleinbuchstaben dekodieren
    $protarray = array(
        "union", "select", "into", "where", "update ", "from", "/*", "set ", "users", // Tabelle geändert von 'user' auf 'users'
        "users(", "users`", "user_groups", "phpinfo", "escapeshellarg", "exec", "fopen", "fwrite",
        "escapeshellcmd", "passthru", "proc_close", "proc_get_status", "proc_nice", "proc_open",
        "proc_terminate", "shell_exec", "system", "telnet", "ssh", "cmd", "mv", "chmod", "chdir",
        "locate", "killall", "passwd", "kill", "script", "bash", "perl", "mysql", "~root", ".history",
        "~nobody", "getenv"
    );
    // Ersetze alle potenziell gefährlichen Teile der Anfrage durch '*'
    $check = str_replace($protarray, '*', $request);
    if ($request != $check) {
        // Wenn sich die Anfrage nach der Ersetzung unterscheidet, wurde eine potenziell gefährliche Anfrage entdeckt
        system_error("Invalid request detected.");
    }
}


// -- SECURITY SLASHES FUNCTION -- //
// Diese Funktion stellt sicher, dass alle Eingabewerte aus $_POST, $_GET, $_COOKIE und $_REQUEST
// gegen SQL-Injektionen geschützt werden, indem sie Escaping durchführen.
function security_slashes(&$array)
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
                // Sicherstellen, dass wir eine sichere Methode für das Escaping verwenden
                $array[ $key ] = $_database->escape_string($tmp);
            } else {
                // Fallback auf addslashes, falls mysqli_real_escape_string nicht verfügbar ist
                $array[ $key ] = addslashes($tmp);
            }
            unset($tmp);
        }
    }
}

// Aufruf der Funktion für alle globalen Eingabewerte
security_slashes($_POST);
security_slashes($_COOKIE);
security_slashes($_GET);
security_slashes($_REQUEST);

// -- ESCAPE QUERY FUNCTION FOR TABLE -- //
// Diese Funktion sorgt dafür, dass SQL-Abfragen vor der Ausführung sicher sind
function escapestring($mquery) {
    global $_database;
    
    // Überprüfe, ob mysqli_real_escape_string verfügbar ist und verwende es
    if (function_exists("mysqli_real_escape_string")) {
        $mquery = $_database->escape_string($mquery);
    } else {
        // Fallback auf addslashes
        $mquery = addslashes($mquery);
    }
    return $mquery;
}

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
// Diese Funktion führt eine SQL-Abfrage aus und überprüft auf potenziell unsichere Abfragen
$_mysql_querys = array();
function safe_query($query = "")
{
    global $_database;
    global $_mysql_querys;

    // Setze den SQL-Modus für die Verbindung
    $_database->query("SET sql_mode = ''");

    // Überprüfe, ob die Abfrage keine potenziell gefährlichen UNION-Select-Abfragen enthält
    if (stristr(str_replace(' ', '', $query), "unionselect") === false and
        stristr(str_replace(' ', '', $query), "union(select") === false
    ) {
        $_mysql_querys[] = $query;

        // Überprüfe, ob die Abfrage leer ist
        if (empty($query)) {
            return false;
        }

        // Führe die Abfrage aus und gebe Fehler aus, wenn DEBUG aktiviert ist
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

// Führe eine Abfrage aus, um die aktiven Einstellungen zu holen
$result = safe_query("SELECT * FROM `settings_themes` WHERE `active` = '1'");

// Fehlerbehandlung für das Abfrageergebnis
if ($result && mysqli_num_rows($result) > 0) {
    // Hole die erste Zeile des Ergebnisses als assoziatives Array
    $dx = mysqli_fetch_assoc($result);

    // Überprüfe, ob die Felder existieren und setze Standardwerte, falls nicht
    $font_family = isset($dx['body1']) ? $dx['body1'] : 'default-font'; // Fallback für Schriftart
    $headlines = isset($dx['headlines']) ? $dx['headlines'] : 'default-headline'; // Fallback für Headlines
} else {
    // Fehlerbehandlung, wenn keine Daten gefunden wurden
    $font_family = 'default-font';
    $headlines = 'default-headline';
}

// CSS- und JS-Dateien
$components = array(
    'css' => array(
        '/components/bootstrap/css/bootstrap-icons.min.css',
        '/components/ckeditor/plugins/codesnippet/lib/highlight/styles/school_book_output.css',
        //'/components/css/animate.css',
        '/components/css/page.css',
        '/components/css/headstyles.css',
        '/includes/plugins/navigation/css/navigation.css',
        '/includes/plugins/footer_easy/css/footer_easy.css'
    ),
    'js' => array(
        '/components/jquery/jquery.min.js',
        '/components/bootstrap/js/bootstrap.bundle.min.js',
        //'/components/js/slick.min.js',
        //'/components/ckeditor/ckeditor.js',
        //'/components/ckeditor/config.js',
        '/components/cookie/cookie-consent.js',
        '/includes/themes/default/js/page.js',
        '/includes/plugins/navigation/js/navigation.js',
        '/includes/plugins/footer_easy/js/footer_easy.js'
    )
);

// Funktion zum Prüfen, ob die Dateien existieren (CSS und JS)
function check_file_exists($file)
{
    // Basisverzeichnis auf dein Projekt setzen (hier system/settings.php liegt in /public_html/system)
    $baseDir = dirname(__DIR__); // geht 1 Ebene hoch von /system -> /public_html

    // Absoluten Pfad bauen
    $path = $baseDir . '/' . ltrim($file, '/');

    return file_exists($path) ? $file : '';
}


// Dateien nur hinzufügen, wenn sie existieren
$valid_css = array_filter($components['css'], 'check_file_exists');
$valid_js = array_filter($components['js'], 'check_file_exists');

// -- Konfiguration und Einstellungen -- //

// Hole alle Einstellungen aus der Tabelle 'settings'
$ds = mysqli_fetch_array(
    safe_query("SELECT * FROM settings")
);

// Zusätzliche Einstellungen
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

// Fehlerbehandlung für die Logo-Abfrage
if ($dx && mysqli_num_rows($dx) > 0) {
    $ds = mysqli_fetch_assoc($dx);
    $logo = isset($ds['logo']) ? $ds['logo'] : 'default_logo.png'; // Fallback-Wert
} else {
    // Fehlerbehandlung, wenn keine Daten für Logo gefunden wurden
    $logo = 'default_logo.png'; // Setze Standardlogo, wenn nichts gefunden wurde
}

$row = safe_query("SELECT * FROM settings_themes WHERE active = '1'");
$tmp = mysqli_fetch_assoc(safe_query("SELECT count(themeID) as cnt FROM settings_themes"));
$anzpartners = $tmp[ 'cnt' ];
while ($ds = mysqli_fetch_array($row)) {
       $theme_name = $ds['pfad'];
       #print_r($theme_name);
}

// Abfrage für Partneranzahl
$tmp = safe_query("SELECT count(themeID) as cnt FROM settings_themes");

// Fehlerbehandlung für Partneranzahl
if ($tmp && mysqli_num_rows($tmp) > 0) {
    $tmp_data = mysqli_fetch_assoc($tmp);
    $anzpartners = isset($tmp_data['cnt']) ? $tmp_data['cnt'] : 0; // Fallback auf 0, wenn keine Partner gefunden
} else {
    // Fehlerbehandlung, wenn keine Partneranzahl gefunden wurde
    $anzpartners = 0; // Setze Standardwert auf 0
}
