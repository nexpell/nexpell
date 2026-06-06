<?php
declare(strict_types=1);

/* =====================================================
   🔓 UPDATER-LOCK AUFHEBEN / FAILSAFE
   (MUSS GANZ OBEN STEHEN!)
===================================================== */


// 🔓 Normaler Fall: Lock nach Reload entfernen
$lockFile = __DIR__ . '/.updater_lock';

if (file_exists($lockFile)) {

    $data = json_decode(file_get_contents($lockFile), true);
    unlink($lockFile); // ONE-SHOT

    echo "
    <div class='alert alert-warning text-center mt-4'>
        <i class='bi bi-exclamation-triangle-fill me-2'></i>
        <strong>Updater wurde aktualisiert</strong><br><br>
        Bitte lade die Seite jetzt neu (F5), um mit dem neuen Updater fortzufahren.
    </div>";
    exit;
}

if (!function_exists('nx_core_log')) {
    function nx_core_log(string $msg): void
    {
        $file = __DIR__ . '/update_core_debug.log';
        @file_put_contents(
            $file,
            date('[Y-m-d H:i:s] ') . $msg . PHP_EOL,
            FILE_APPEND
        );
    }
}



/* =====================================================
   🚀 AB HIER BEGINNT DER NEUE UPDATER
===================================================== */

// ab hier dein bestehender Code:
// session_start();
// require config
// update logic

/**
 * 🧩 NEXPELL UPDATE CORE – Wizard / Tab-Version
 * ----------------------------------------------
 * ✓ 3 Schritte (Vorbereitung → Migration → Abschluss)
 * ✓ Kein doppeltes Logging
 * ✓ Bootstrap 5.3 Tabs + Fortschrittsbalken
 * ✓ action=start / progress / finish
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) session_start();


require_once __DIR__ . '/../system/classes/CMSUpdater.php';
require_once __DIR__ . '/../system/classes/CMSDatabaseMigration.php';

use nexpell\CMSUpdater;
use nexpell\CMSDatabaseMigration;
use nexpell\AccessControl;

global $_language, $_database;

$tpl = new Template();
$data_array = [];

$version_file = __DIR__ . '/../system/version.php';
$core_version = file_exists($version_file) ? include $version_file : '1.0.0';
define('CURRENT_VERSION', $core_version);


/*$installedBuilds = [];

$res = safe_query("
    SELECT version, MAX(build) AS build
    FROM system_update_history
    GROUP BY version
");

while ($row = mysqli_fetch_assoc($res)) {
    $installedBuilds[$row['version']] = (int)$row['build'];
}*/

// 🔄 Installed Builds neu einlesen (SEHR WICHTIG)
$installedBuilds = [];

$res = safe_query("
    SELECT version, MAX(build) AS build
    FROM system_update_history
    GROUP BY version
");

while ($row = mysqli_fetch_assoc($res)) {
    $installedBuilds[$row['version']] = (int)$row['build'];
}


?>
<style>
/* 🌿 Einheitlicher Look für die Update-Wizard-Navigation */
.nx-wizard-nav.nav-link {
    border-radius: 0.375rem;
    padding: 0.5rem 1.25rem;
    border: 1px solid #fe821d;
    color: #212529;
    background-color: #fff;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
}

/* Hover-Zustand */
.nx-wizard-nav.nav-link:hover {
    background-color: #fe821d;
    color: #ffffff;
    border-color: #fe821d;
}

/* Aktiver Tab */
.nx-wizard-nav.nav-link.active {
    background-color: #fe821d;
    border-color: #fe821d;
    color: #fff !important;
    box-shadow: 0 0 6px rgba(25, 135, 84, 0.35);
}
</style>
<?php

// 🛡️ Settings laden
// ============================================================
// 🔧 Update-Kanal IMMER zuerst festlegen
// ============================================================

// Settings sicher laden
$settings = [];

$res = safe_query("SELECT update_channel FROM settings LIMIT 1");
if ($row = mysqli_fetch_assoc($res)) {
    $settings['update_channel'] = $row['update_channel'] ?? 'stable';
} else {
    $settings['update_channel'] = 'stable';
}







$action = $_GET['action'] ?? 'start';

// ============================================================
// 🧩 Update-Info-Datei abrufen und prüfen
// ============================================================
$update_info_url = "https://update.nexpell.de/updates/update_info_v2.json";
$http_status = "unbekannt";
$error_reason = "";

// --- Funktion zur Diagnose ---
function nx_checkUpdateSource(string $url, &$http_status): string {
    // PHP-Versionstoleranter Aufruf von get_headers
    if (version_compare(PHP_VERSION, '8.3.0', '>=')) {
        $headers = @get_headers($url, false); // PHP 8.3+: bool statt int
    } else {
        $headers = @get_headers($url, 1);     // Ältere PHP-Versionen
    }

    if (!$headers) {
        $http_status = "Keine Antwort";
        return "Keine Verbindung zum Server – möglicherweise offline oder blockiert.";
    }

    if (preg_match('/\s(\d{3})\s/', $headers[0], $m)) {
        $http_status = $m[1];
    }

    switch ((int)$http_status) {
        case 200:
            return "Datei ist erreichbar, aber enthält möglicherweise fehlerhafte Daten.";
        case 403:
            return "Zugriff verweigert – der Server blockiert die Anfrage.";
        case 404:
            return "Update-Datei nicht gefunden – möglicherweise verschoben oder gelöscht.";
        case 500:
        case 502:
        case 503:
        case 504:
            return "Der Update-Server meldet einen internen Fehler oder ist überlastet.";
        default:
            return "Unerwartete Server-Antwort: HTTP {$http_status}.";
    }
}


// --- Datei abrufen ---
$update_info_json = @file_get_contents($update_info_url);

// --- Fehler: Datei nicht erreichbar ---
if (!$update_info_json) {
    $error_reason = nx_checkUpdateSource($update_info_url, $http_status);

    echo "
    <div class='alert alert-danger m-3'>
        <h5 class='fw-bold mb-2'>
            <i class='bi bi-exclamation-triangle-fill me-2'></i>
            Update-Informationen konnten nicht geladen werden
        </h5>

        <div class='small'>
            <b>Server:</b> <code>update.nexpell.de</code><br>
            <b>Ressource:</b> <code>/updates/update_info_v2.json</code><br>
            <b>HTTP-Status:</b> {$http_status}<br>
            <b>Ursache:</b> {$error_reason}
        </div>

        <hr class='my-2'>

        <div class='small text-muted' id='nx-server-check'>
            <i class='bi bi-info-circle me-1'></i>
            <b>Hilfe & Diagnose:</b><br>
            • Prüfe, ob dein Server ausgehende HTTPS-Verbindungen erlaubt.<br>
            • Wenn du Shared Hosting nutzt (z.&nbsp;B. Lima-City, All-Inkl), aktiviere <code>allow_url_fopen</code> oder <code>cURL</code>.<br>
            • Teste die Erreichbarkeit direkt:
              <a href='{$update_info_url}' target='_blank'>{$update_info_url}</a><br>
            • Offizieller Server-Status: 
              <!--<a href='https://status.nexpell.de' target='_blank'>status.nexpell.de</a>.<br><br>-->
            <span class='text-secondary small'>
                <i class='bi bi-clock me-1'></i> Prüfe Verbindung zu <code>update.nexpell.de</code> ...
            </span>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        fetch('https://update.nexpell.de/', { method: 'HEAD', mode: 'no-cors' })
            .then(() => {
                document.getElementById('nx-server-check').insertAdjacentHTML(
                    'beforeend',
                    \"<div class='text-success small mt-1'><i class='bi bi-check-circle me-1'></i>Server ist erreichbar.</div>\"
                );
            })
            .catch(() => {
                document.getElementById('nx-server-check').insertAdjacentHTML(
                    'beforeend',
                    \"<div class='text-danger small mt-1'><i class='bi bi-x-circle me-1'></i>Server ist weiterhin nicht erreichbar.</div>\"
                );
            });
    });
    </script>
    ";
    exit;
}


// --- Fehler: JSON fehlerhaft ---
$update_info = json_decode($update_info_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($update_info['updates']) || !is_array($update_info['updates'])) {
    echo "
    <div class='alert alert-danger m-3'>
        <h5 class='fw-bold mb-2'>
            <i class='bi bi-file-earmark-excel-fill me-2'></i>
            Update-Informationen konnten nicht korrekt verarbeitet werden
        </h5>
        <div class='small'>
            <b>Datei:</b> <code>update_info_v2.json</code><br>
            <b>JSON-Fehler:</b> " . htmlspecialchars(json_last_error_msg()) . "<br>
            <b>Hinweis:</b> Möglicherweise ist die Datei beschädigt oder leer.
        </div>
    </div>";
    exit;
}

// --- Erfolgreich: Updates einlesen ---
#$updates = array_values(array_filter(
#    $update_info['updates'],
#    fn($entry) => version_compare($entry['version'], CURRENT_VERSION, '>')
#));

// -------------------------------------------------------
// 🔧 Update-Kanal
// -------------------------------------------------------
$res = safe_query("SELECT update_channel FROM settings LIMIT 1");
$channel = 'stable';

if ($row = mysqli_fetch_assoc($res)) {
    $channel = $row['update_channel'] ?? 'stable';
}

// -------------------------------------------------------
// 👤 Benutzer-Mail
// -------------------------------------------------------
$user_email = strtolower(trim($_SESSION['user_email'] ?? ''));

if ($user_email === '' && isset($_SESSION['userID'])) {
    $uid = (int)$_SESSION['userID'];
    $res = safe_query("SELECT email FROM users WHERE userID = {$uid} LIMIT 1");
    if ($r = mysqli_fetch_assoc($res)) {
        $user_email = strtolower(trim($r['email'] ?? ''));
    }
}

// -------------------------------------------------------
// 🌍 Client-IP
// -------------------------------------------------------
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';


$updates = $update_info['updates'] ?? [];

if (!is_array($updates)) {
    $updates = [];
}

$updates = array_values(array_filter(
    $update_info['updates'] ?? [],
    function ($entry) use (
        $channel,
        $user_email,
        $client_ip,
        $installedBuilds
    ) {

        // ------------------------------
        // Grundvalidierung
        // ------------------------------
        if (
            !is_array($entry) ||
            empty($entry['version']) ||
            empty($entry['channel'])
        ) {
            return false;
        }

        $version = (string)$entry['version'];
        $build   = (int)($entry['build'] ?? 1);

        nx_core_log(
            "[UPDATER] {$version} | build {$build} | channel {$entry['channel']} | installed " .
            ($installedBuilds[$version] ?? 0)
        );

        // ------------------------------
        // 1️⃣ KANAL-REGELN
        // ------------------------------
        $allowedByChannel = match ($channel) {
            'stable' => $entry['channel'] === 'stable',
            'beta'   => in_array($entry['channel'], ['stable', 'beta'], true),
            'dev'    => in_array($entry['channel'], ['stable', 'beta', 'dev'], true),
            default  => false,
        };

        if (!$allowedByChannel) {
            return false;
        }

        // ------------------------------
        // 2️⃣ visible_for
        // ------------------------------
        if (!empty($entry['visible_for'])) {

            if (in_array('all', $entry['visible_for'], true)) {
                // ok
            } else {
                $allowed = false;

                foreach ($entry['visible_for'] as $v) {
                    $v = strtolower(trim($v));
                    if ($v === $user_email || $v === $client_ip) {
                        $allowed = true;
                        break;
                    }
                }

                if (!$allowed) {
                    return false;
                }
            }
        }

        // ------------------------------
        // 3️⃣ VERSION + BUILD
        // ------------------------------
        // Neue Version → immer anzeigen
        if (version_compare($version, CURRENT_VERSION, '>')) {
            return true;
        }

        // Gleiche Version → nur höherer Build
        if (
            version_compare($version, CURRENT_VERSION, '==') &&
            $build > ($installedBuilds[$version] ?? 0)
        ) {
            return true;
        }

        return false;
    }
));




/* ========================================================================
   🧭 WIZARD NAVIGATION – Fortschrittsanzeige
   ======================================================================== */
$steps_nav = [
    'start'    => ['title' => 'Vorbereitung', 'icon' => 'bi-cloud-download'],
    'progress' => ['title' => 'Migration', 'icon' => 'bi-database'],
    'finish'   => ['title' => 'Abschluss', 'icon' => 'bi-check2-circle'],
];

$current_index = array_search($action, array_keys($steps_nav));
$total_steps = count($steps_nav);
$progress_percent = (($current_index + 1) / $total_steps) * 100;

$progress_html = "
<div class='progress my-3' style='height: 6px;'>
  <div class='progress-bar bg-success' role='progressbar' 
       style='width: {$progress_percent}%;' 
       aria-valuenow='{$progress_percent}' aria-valuemin='0' aria-valuemax='100'></div>
</div>";

$wizard_nav_html = "<ul class='nav nav-pills justify-content-center mb-4'>";
$i = 0;

foreach ($steps_nav as $key => $step) {
    $i++;
    $active = ($key === $action) ? 'active' : '';
    $disabled = 'disabled'; // alle sind deaktiviert (nicht klickbar)
    
    $wizard_nav_html .= "
    <li class='nav-item mx-1'>
        <a class='nx-wizard-nav nav-link $active $disabled' tabindex='-1' aria-disabled='true'>
            <i class='bi {$step['icon']} me-1'></i>{$step['title']}
        </a>
    </li>";
}

$wizard_nav_html .= "</ul>";


$data_array['wizard_nav'] = $wizard_nav_html;
$data_array['progress_bar'] = $progress_html;
$data_array['current_version'] = CURRENT_VERSION;


// ============================================================
// 🧩 Jetzt: Beta- und Zugriffsschutz-Filter anwenden
// ============================================================
// -------------------------------------------------------
// Benutzer-IP holen
// -------------------------------------------------------
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';


// -------------------------------------------------------
// Benutzer-E-Mail holen
// -------------------------------------------------------
$user_email = $_SESSION['user_email'] ?? '';

if (empty($user_email) && isset($_SESSION['userID'])) {
    $uid = (int)$_SESSION['userID'];

    $res = safe_query("SELECT email FROM users WHERE userID = {$uid} LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $user_email = $row['email'] ?? '';
    }
}

// -------------------------------------------------------
// 🔧 Update-Kanal & Benutzer-Kontext
// -------------------------------------------------------

$settings = [];
$res = safe_query("SELECT update_channel FROM settings LIMIT 1");
if ($row = mysqli_fetch_assoc($res)) {
    $settings['update_channel'] = $row['update_channel'] ?? 'stable';
} else {
    $settings['update_channel'] = 'stable';
}

$channel = $settings['update_channel'] ?? 'stable';

// Benutzer-Mail
$user_email = strtolower(trim($_SESSION['user_email'] ?? ''));

// Fallback: Mail aus DB holen
if ($user_email === '' && isset($_SESSION['userID'])) {
    $uid = (int)$_SESSION['userID'];
    $res = safe_query("SELECT email FROM users WHERE userID = {$uid} LIMIT 1");
    if ($r = mysqli_fetch_assoc($res)) {
        $user_email = strtolower(trim($r['email'] ?? ''));
    }
}

// Client-IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';


// -------------------------------------------------------
// 🔥 UPDATES FILTERN (ALT = STABIL)
// -------------------------------------------------------

$updates = array_filter(
    $update_info['updates'] ?? [],
    function ($entry) use ($channel, $user_email, $client_ip) {

        if (
            !is_array($entry) ||
            empty($entry['version']) ||
            empty($entry['channel'])
        ) {
            return false;
        }

        // 1) Kanal-Regeln
        $allowedByChannel = match ($channel) {
            'stable' => $entry['channel'] === 'stable',
            'beta'   => in_array($entry['channel'], ['stable', 'beta'], true),
            'dev'    => in_array($entry['channel'], ['stable', 'beta', 'dev'], true),
            default  => false,
        };

        if (!$allowedByChannel) {
            return false;
        }

        // 2) visible_for
        if (empty($entry['visible_for'])) {
            return true;
        }

        if (in_array('all', $entry['visible_for'], true)) {
            return true;
        }

        foreach ($entry['visible_for'] as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === $user_email || $allowed === $client_ip) {
                return true;
            }
        }

        return false;
    }
);

// 🔒 Array neu indizieren (SEHR WICHTIG!)
$updates = array_values($updates);


// -------------------------------------------------------
// 🔁 Update-Kanal ändern (PRG)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_channel'])) {

    $allowed = ['stable', 'beta', 'dev'];
    $newChannel = in_array($_POST['update_channel'], $allowed, true)
        ? $_POST['update_channel']
        : 'stable';

    safe_query("
        UPDATE settings
        SET update_channel = '" . escape($newChannel) . "'
    ");

    header("Location: admincenter.php?site=update_core");
    exit;
}

$sel_stable = ($channel === 'stable') ? 'selected' : '';
$sel_beta   = ($channel === 'beta')   ? 'selected' : '';
$sel_dev    = ($channel === 'dev')    ? 'selected' : '';

$data_array['channel_form'] = '
<form method="post">
    <label class="form-label fw-bold">Update-Kanal</label>
    <select name="update_channel" class="form-select" onchange="this.form.submit()">
        <option value="stable" ' . $sel_stable . '>Stable (empfohlen)</option>
        <option value="beta" '   . $sel_beta   . '>Beta (Vorschau)</option>
        <option value="dev" '    . $sel_dev    . '>Dev (intern)</option>
    </select>
</form>';

$data_array['beta_badge'] = match ($channel) {
    'beta' => '<div class="alert alert-warning small mt-2">
                <i class="bi bi-flask me-1"></i> Beta-Kanal aktiv
               </div>',
    'dev'  => '<div class="alert alert-danger small mt-2">
                <i class="bi bi-bug me-1"></i> Dev-Kanal aktiv
               </div>',
    default => '<div class="alert alert-success small mt-2">
                <i class="bi bi-shield-check me-1"></i> Stable-Kanal aktiv
               </div>',
};

// -------------------------------------------------------
// Updates filtern
// -------------------------------------------------------

// Kanal setzen (JETZT existiert er!)
$channel = $settings['update_channel'] ?? 'stable';
$user_email = strtolower(trim($user_email ?? ''));

$updates = array_filter(
    $update_info['updates'] ?? [],
    function ($entry) use ($channel, $user_email, $client_ip) {

        if (!is_array($entry) || empty($entry['version']) || empty($entry['channel'])) {
            return false;
        }

        // 1) Kanal-Regeln (WER KANN)
        $allowedByChannel = match ($channel) {
            'stable' => $entry['channel'] === 'stable',
            'beta'   => in_array($entry['channel'], ['stable', 'beta'], true),
            'dev'    => in_array($entry['channel'], ['stable', 'beta', 'dev'], true),
            default  => false,
        };

        if (!$allowedByChannel) {
            return false;
        }

        // 2) visible_for-Regeln (WER DARF)
        if (empty($entry['visible_for'])) {
            return true; // kein Filter → alle im Kanal
        }

        if (in_array('all', $entry['visible_for'], true)) {
            return true;
        }

        // exakte Freigaben
        foreach ($entry['visible_for'] as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === $user_email || $allowed === $client_ip) {
                return true;
            }
        }

        return false;
    }
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_channel'])) {

    $allowed = ['stable', 'beta', 'dev'];
    $newChannel = in_array($_POST['update_channel'], $allowed, true)
        ? $_POST['update_channel']
        : 'stable';

    safe_query("
        UPDATE settings
        SET update_channel = '" . escape($newChannel) . "'
    ");

    // 🔁 PRG: Redirect, damit der neue Zustand sauber geladen wird
    header("Location: admincenter.php?site=update_core");
    exit;
}

$res = safe_query("SELECT update_channel FROM settings LIMIT 1");
$row = mysqli_fetch_assoc($res);

$settings['update_channel'] = $row['update_channel'] ?? 'stable';
$channel = $settings['update_channel'];

$channel = $settings['update_channel'] ?? 'stable';

$sel_stable = ($channel === 'stable') ? 'selected' : '';
$sel_beta   = ($channel === 'beta')   ? 'selected' : '';
$sel_dev    = ($channel === 'dev')    ? 'selected' : '';

$channel_form = '
<form method="post" action="">
    <label class="form-label fw-bold">
        Update-Kanal
    </label>

    <select name="update_channel" class="form-select" onchange="this.form.submit()">
        <option value="stable" ' . $sel_stable . '>Stable (empfohlen)</option>
        <option value="beta" '   . $sel_beta   . '>Beta (Vorschau)</option>
        <option value="dev" '    . $sel_dev    . '>Dev (intern)</option>
    </select>
</form>';

$beta_badge = '';

switch ($channel) {

    case 'stable':
        $beta_badge = '
        <div class="alert alert-success small mt-2">
            <i class="bi bi-shield-check me-1"></i>
            <b>Stable-Kanal aktiv:</b>
            Es werden ausschließlich geprüfte und freigegebene Updates installiert.
        </div>';
        break;

    case 'beta':
        $beta_badge = '
        <div class="alert alert-warning small mt-2">
            <i class="bi bi-flask me-1"></i>
            <b>Beta-Kanal aktiv:</b>
            Du erhältst Vorab-Updates, die noch nicht final getestet sind.
        </div>';
        break;

    case 'dev':
        $beta_badge = '
        <div class="alert alert-danger small mt-2">
            <i class="bi bi-bug me-1"></i>
            <b>Dev-Kanal aktiv:</b>
            Interne Entwickler-Builds – nicht für Produktivsysteme geeignet!
        </div>';
        break;
}




$data_array['channel_form'] = $channel_form;
$data_array['beta_badge'] = $beta_badge;


// -------------------------------------------------------
// Nur neuere Versionen als aktuelle anzeigen
// -------------------------------------------------------
#$updates = array_values(array_filter(
#    $updates,
#    fn($entry) => version_compare($entry['version'], CURRENT_VERSION, '>')
#));

// -------------------------------------------------------
// 🔧 Update-Kanal
// -------------------------------------------------------
$res = safe_query("SELECT update_channel FROM settings LIMIT 1");
$channel = 'stable';

if ($row = mysqli_fetch_assoc($res)) {
    $channel = $row['update_channel'] ?? 'stable';
}

// -------------------------------------------------------
// 👤 Benutzer-Mail
// -------------------------------------------------------
$user_email = strtolower(trim($_SESSION['user_email'] ?? ''));

if ($user_email === '' && isset($_SESSION['userID'])) {
    $uid = (int)$_SESSION['userID'];
    $res = safe_query("SELECT email FROM users WHERE userID = {$uid} LIMIT 1");
    if ($r = mysqli_fetch_assoc($res)) {
        $user_email = strtolower(trim($r['email'] ?? ''));
    }
}

// -------------------------------------------------------
// 🌍 Client-IP
// -------------------------------------------------------
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';


$updates = $update_info['updates'] ?? [];

if (!is_array($updates)) {
    $updates = [];
}
$updates = array_values(array_filter(
    $update_info['updates'] ?? [],
    function ($entry) use (
        $channel,
        $user_email,
        $client_ip,
        $installedBuilds
    ) {

        // ------------------------------
        // Grundvalidierung
        // ------------------------------
        if (
            !is_array($entry) ||
            empty($entry['version']) ||
            empty($entry['channel'])
        ) {
            return false;
        }

        $version = (string)$entry['version'];
        $build   = (int)($entry['build'] ?? 1);

        nx_core_log(
            "[UPDATER] {$version} | build {$build} | channel {$entry['channel']} | installed " .
            ($installedBuilds[$version] ?? 0)
        );

        // ------------------------------
        // 1️⃣ KANAL-REGELN
        // ------------------------------
        $allowedByChannel = match ($channel) {
            'stable' => $entry['channel'] === 'stable',
            'beta'   => in_array($entry['channel'], ['stable', 'beta'], true),
            'dev'    => in_array($entry['channel'], ['stable', 'beta', 'dev'], true),
            default  => false,
        };

        if (!$allowedByChannel) {
            return false;
        }

        // ------------------------------
        // 2️⃣ visible_for
        // ------------------------------
        if (!empty($entry['visible_for'])) {

            if (in_array('all', $entry['visible_for'], true)) {
                // ok
            } else {
                $allowed = false;

                foreach ($entry['visible_for'] as $v) {
                    $v = strtolower(trim($v));
                    if ($v === $user_email || $v === $client_ip) {
                        $allowed = true;
                        break;
                    }
                }

                if (!$allowed) {
                    return false;
                }
            }
        }

        // ------------------------------
        // 3️⃣ VERSION + BUILD
        // ------------------------------
        // Neue Version → immer anzeigen
        if (version_compare($version, CURRENT_VERSION, '>')) {
            return true;
        }

        // Gleiche Version → nur höherer Build
        if (
            version_compare($version, CURRENT_VERSION, '==') &&
            $build > ($installedBuilds[$version] ?? 0)
        ) {
            return true;
        }

        return false;
    }
));



/* ========================================================================
   🧩 ACTION: START
   ======================================================================== */
if ($action === 'start') {

    /* ============================================================
       📅 Letztes Update-Datum
       ============================================================ */
    $last_update_file = __DIR__ . '/../system/last_update.txt';
    $last_update_date = file_exists($last_update_file)
        ? htmlspecialchars(file_get_contents($last_update_file))
        : 'unbekannt';

    $updateCount = count($updates);

    /* ============================================================
       🕘 UPDATE-HISTORIE LADEN
       ============================================================ */


    $history = [];
    $history_html = '';

    $res = safe_query("
        SELECT version, channel, installed_at, notes
        FROM system_update_history
        ORDER BY installed_at ASC
    ");

    $history = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $history[] = [
            'version'      => $row['version'],
            'channel'      => $row['channel'],
            'installed_at' => (int)$row['installed_at'],
            'notes' => $row['notes']
        ];
    }


    if (count($history) > 0) {



$history_html = "
<div class='card mt-4'>
    <div class='card-header'>
        <i class='bi bi-clock-history me-2'></i>
        Update-Historie
    </div>

    <div class='table-responsive'>
        <table class='table table-sm table-striped align-middle mb-0'>
            <thead class='table-light'>
                <tr>
                    <th>Typ</th>
                    <th>Version</th>
                    <th>Kanal</th>
                    <th>Notizen</th>
                    <th class='text-end'>Datum</th>
                </tr>
            </thead>
            <tbody>
";


// ==========================
// Initiale Installation
// ==========================
$first = $history[0];

$history_html .= "
<tr>
    <td>
        <span class='badge bg-secondary'>Initial</span>
    </td>
    <td>
        <b>" . htmlspecialchars($first['version']) . "</b>
    </td>
    <td>
        <span class='badge bg-secondary'>STABLE</span>
    </td>
    <td class='text-muted small'>
        " . (!empty($first['notes']) ? htmlspecialchars($first['notes']) : '-') . "
    </td>
    <td class='text-end text-muted small'>
        " . date('d.m.Y H:i', $first['installed_at']) . "
    </td>
</tr>
";


// ==========================
// Weitere Updates
// ==========================
for ($i = 1; $i < count($history); $i++) {

    $from = htmlspecialchars($history[$i - 1]['version']);
    $to   = htmlspecialchars($history[$i]['version']);

    $badge = match ($history[$i]['channel']) {
        'beta' => "<span class='badge bg-warning'>BETA</span>",
        'dev'  => "<span class='badge bg-danger'>DEV</span>",
        default => "<span class='badge bg-success'>STABLE</span>"
    };

    $history_html .= "
    <tr>
        <td>
            <span class='badge bg-primary'>Update</span>
        </td>
        <td>
            {$from} → <b>{$to}</b>
        </td>
        <td>
            {$badge}
        </td>
        <td class='text-muted small'>
            " . (!empty($history[$i]['notes']) ? htmlspecialchars($history[$i]['notes']) : '-') . "
        </td>
        <td class='text-end text-muted small'>
            " . date('d.m.Y H:i', $history[$i]['installed_at']) . "
        </td>
    </tr>
    ";
}

$history_html .= "
            </tbody>
        </table>
    </div>
</div>
";

}


    /* ============================================================
       🌐 UPDATE-SERVER PRÜFEN
       ============================================================ */
    function nx_isUpdateServerReachable(string $url): bool {
        $headers = @get_headers($url);
        return $headers && str_contains($headers[0], '200');
    }

    $update_info_url = "https://update.nexpell.de/updates/update_info_v2.json";
    $server_ok = nx_isUpdateServerReachable($update_info_url);

    $server_status = $server_ok
        ? "<span class='text-success'><i class='bi bi-check-circle-fill me-1'></i> Verbindung erfolgreich</span>"
        : "<span class='text-danger'><i class='bi bi-x-circle-fill me-1'></i> Keine Verbindung möglich</span>";

    /* ============================================================
       🧩 FALL 1: KEINE UPDATES
       ============================================================ */
    if ($updateCount === 0) {

        $data_array['content'] = "
        <!-- 🔹 Aktuelle Version -->
        <div class='alert alert-success mb-4 d-flex align-items-center justify-content-between'>

            <div>
                <div class='fw-semibold mb-1'>
                    <i class='bi bi-info-circle me-2'></i>
                    Aktuell installierte Version
                </div>

                <div class='fs-5 fw-semibold'>
                    nexpell Core Version: " . htmlspecialchars(CURRENT_VERSION) . "
                </div>

                <div class='small text-muted mt-1'>
                    <i class='bi bi-clock-history me-1'></i>
                    Installiert am " . $last_update_date . "<br>
                    <i class='bi bi-shield-check me-1'></i>
                    Dein System ist auf dem neuesten Stand und einsatzbereit.
                </div>
            </div>

            <span class='badge bg-success fs-6 px-3 py-2'>
                Aktuell
            </span>

        </div>

        <div class='alert alert-info mt-3 small'>
            <i class='bi bi-info-circle me-2'></i>
            <b>Dein System ist auf dem neuesten Stand.</b><br>
            Es sind aktuell keine Updates verfügbar.
            Alle bekannten Stabilitäts- und Sicherheitsupdates
            wurden erfolgreich installiert.
        </div>


        {$history_html}
        ";
    }

    /* ============================================================
       🧩 FALL 2: UPDATES VERFÜGBAR
       ============================================================ */
    else {

        $versions = array_column($updates, 'version');
        $log = '';

        foreach ($updates as $update) {
            $ver  = htmlspecialchars($update['version']);
            $desc = nl2br(htmlspecialchars($update['changelog'] ?? 'Keine Beschreibung.'));
            $log .= "🟢 <b>{$ver}</b>: {$desc}<br>";
        }

        $update_count_text = ($updateCount === 1)
            ? "Ein neues Update steht bereit!"
            : "{$updateCount} Updates stehen zur Installation bereit!";




$isUpToDate = ($updateCount === 0);

/* =========================
   Alert-Farbe bestimmen
========================= */
$alertClass = 'alert-success';

if (!$isUpToDate) {
    $alertClass = 'alert-warning';
}

if ($channel === 'beta') {
    $alertClass = 'alert-warning';
}

if ($channel === 'dev') {
    $alertClass = 'alert-danger';
}

/* =========================
   Badge bestimmen
========================= */
if ($channel === 'dev') {
    $statusBadge = "<span class='badge bg-danger fs-6 px-3 py-2'>DEV</span>";
} elseif ($channel === 'beta') {
    $statusBadge = "<span class='badge bg-warning fs-6 px-3 py-2'>BETA</span>";
} elseif ($isUpToDate) {
    $statusBadge = "<span class='badge bg-success fs-6 px-3 py-2'>Aktuell</span>";
} else {
    $statusBadge = "<span class='badge bg-warning fs-6 px-3 py-2'>Update verfügbar</span>";
}

/* =========================
   Text bestimmen
========================= */
if ($isUpToDate) {
    $statusText = "Dein System ist auf dem neuesten Stand und einsatzbereit.";
} else {
    $statusText = "Dein System läuft stabil, es sind jedoch Updates verfügbar.";
}

if (!$isUpToDate && $channel === 'beta') {
    $statusText .= " Du nutzt den Beta-Kanal und erhältst Vorab-Versionen.";
}

if ($channel === 'dev') {
    $statusText .= " Achtung: Dev-Kanal – Versionen können instabil sein.";
}


        $data_array['content'] = "



<!-- 🔹 Aktuelle Version -->
<div class='alert  " . $alertClass ." mb-4 d-flex align-items-center justify-content-between'>

    <div>
        <div class='fw-semibold mb-1'>
            <i class='bi bi-info-circle me-2'></i>
            Aktuell installierte Version
        </div>

        <div class='fs-5 fw-semibold'>
            nexpell Core Version: " . htmlspecialchars(CURRENT_VERSION) ."
        </div>

        <div class='small text-muted mt-1'>
            <i class='bi bi-clock-history me-1'></i>
            Installiert am " . $last_update_date ."<br>

            <i class='bi bi-shield-check me-1'></i>
            " . $statusText ."
        </div>
    </div>

    " . $statusBadge ."

</div>





<!-- 🔹 Verbindung zum Update-Server -->
<div class='alert alert-light mb-4'>

    <div class='fw-semibold mb-1'>
        <i class='bi bi-hdd-network me-2 text-primary'></i>
        Update-Server
    </div>

    <div>
        <code>update.nexpell.de</code>
    </div>

    <div class='small text-muted mt-1'>
        Status: {$server_status}<br>
        <i class='bi bi-cloud-arrow-down me-1'></i>
        Dieser Server stellt Core-, Plugin- und Sicherheitsupdates für Nexpell bereit.
    </div>

</div>


    


<!-- 🔹 Update-Informationen -->
<div class='alert alert-primary mb-4'>
    <h5 class='mb-1'>
        <i class='bi bi-rocket-takeoff-fill me-2'></i>
        {$update_count_text}
    </h5>
    <p class='mb-2 small'>
        Es wurden neue Versionen des Nexpell-Cores gefunden, die
        <b>wichtige Verbesserungen, Sicherheits-Patches</b> und
        <b>neue Funktionen</b> enthalten.
    </p>
    <div class='small'>
        <b>Verfügbare Versionen:</b> " . implode(', ', $versions) . "
    </div>
</div>

<!-- 🔹 Änderungsprotokoll -->
<div class='alert alert-light mb-4'>
    <div class='fw-semibold mb-1'>
        <i class='bi bi-journal-text me-2 text-primary'></i>
        Änderungsprotokoll
    </div>
    <p class='small text-muted mb-2'>
        Die folgenden Änderungen und Verbesserungen sind in den verfügbaren Updates enthalten:
    </p>
    <div class='nx-changelog-content small text-break'>
        {$log}
    </div>
</div>

{$history_html}

<!-- 🔹 Update starten -->
<form method='post' action='admincenter.php?site=update_core&action=progress'>
    <button class='btn btn-success btn-lg shadow-sm mt-3'>
        <i class='bi bi-arrow-clockwise me-1'></i> Update jetzt starten
    </button>
</form>
";



    }

    echo $tpl->loadTemplate("update_core", "wizard", $data_array, "admin");
}





/* ========================================================================
   🧩 ACTION: PROGRESS
   ======================================================================== */
if ($action === 'progress') {

    $steps_log = [];
    $tmp_dir = __DIR__ . '/tmp';
    $extract_path = __DIR__ . '/..';

    $new_version = CURRENT_VERSION;
    $all_updates_succeeded = true;

    $migrator = new \nexpell\CMSDatabaseMigration($_database);

    // 🔒 Updater-Wechsel-Flags
    $requiresNewUpdater = false;
    $requiresVersion    = null;

    // 🕒 Zeitpunkt für Update-History
    $installedAt = time();

    // 🔒 Feste Ausgangsversion für DIESEN Update-Lauf
    $baseVersion = null;

    $resBase = safe_query("
        SELECT version
        FROM system_update_history
        WHERE success = 1
        ORDER BY installed_at DESC, id DESC
        LIMIT 1
    ");

    if ($resBase && ($rowBase = mysqli_fetch_assoc($resBase))) {
        $baseVersion = $rowBase['version'];
    }



















if (
    file_exists(__DIR__ . '/.updater_lock')
    && empty($_GET['confirm_continue'])
) {
    $data_array['content'] = "
    <div class='alert alert-warning'>
        <i class='bi bi-shield-lock-fill me-2'></i>
        Der neue Updater ist aktiv.<br>
        Bitte entscheide explizit, ob du fortfahren möchtest.
    </div>
    ";
    echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
    exit;
}

    // ============================================================
    // 🧩 Schritt 1: tmp prüfen
    // ============================================================
// Schritt 1 UI
$steps_log[] = "
<div class='alert alert-info mb-2'>
    <i class='bi bi-info-circle-fill me-2'></i>
    <b>1️⃣ Prüfe tmp-Verzeichnis</b>
</div>";

// Log + Logik
if (!is_dir($tmp_dir) && !mkdir($tmp_dir, 0755, true)) {

    $migrator->log("❌ tmp-Verzeichnis konnte nicht erstellt werden: {$tmp_dir}");

    $steps_log[] = "
    <div class='alert alert-danger mb-2'>
        <i class='bi bi-x-circle-fill me-2'></i>
        tmp-Verzeichnis konnte nicht erstellt werden
    </div>";

    $all_updates_succeeded = false;

} else {

    $migrator->log("✅ tmp-Verzeichnis vorhanden / erstellt");
}

// 🔥 Log sichtbar machen (DAS fehlte!)
$logHtml = $migrator->getLog();
if ($logHtml !== '') {
    $steps_log[] = "
    <div class='alert alert-secondary mb-2'>
            <i class='bi bi-journal-code me-2'></i>
        <b>tmp-Verzeichnis-Prüf-Log</b><br><br>
        <div class='p-3 bg-light border rounded'>
        {$logHtml}
        </div>
    </div>";
}



    // ============================================================
    // 🧩 Schritt 2: Updates herunterladen
    // ============================================================
if ($all_updates_succeeded) {

    // UI-Header
    $steps_log[] = "
    <div class='alert alert-info mb-2'>
        <i class='bi bi-cloud-arrow-down-fill me-2'></i>
        <b>2️⃣ Lade Updates herunter</b>
    </div>";

    /*foreach ($updates as $update) {

        $version   = $update['version'];
        $zip_url   = $update['zip_url'];
        $zip_file  = "$tmp_dir/update_$version.zip";
        $sql_file  = "$tmp_dir/migrations/$version.php";

        if (!is_dir("$tmp_dir/migrations")) {
            mkdir("$tmp_dir/migrations", 0755, true);
            $migrator->log("📁 Migrations-Verzeichnis erstellt");
        }

        $migrator->log("⬇️ Lade Update {$version} von {$zip_url}");

        $zip_content = @file_get_contents($zip_url);*/
    foreach ($updates as $update) {

        $version = (string)$update['version'];
        $build   = (int)($update['build'] ?? 1);

        // 🔒 BUILD-SCHUTZ (HIER GEHÖRT ER HIN!)
        $installedBuild = $installedBuilds[$version] ?? 0;

        if ($build <= $installedBuild) {
            $migrator->log(
                "⏭️ Überspringe {$version} (build {$build} ≤ installiert {$installedBuild})"
            );
            continue;
        }

        // -----------------------------------
        // AB HIER ERST DOWNLOAD & ZIP
        // -----------------------------------
        $zip_url  = $update['zip_url'];
        $zip_file = "$tmp_dir/update_{$version}.zip";
        $sql_file = "$tmp_dir/migrations/{$version}.php";

        $migrator->log("⬇️ Lade Update {$version} (build {$build})");

        $zip_content = @file_get_contents($zip_url);
        if (!$zip_content || !file_put_contents($zip_file, $zip_content)) {
            $migrator->log("❌ Update {$version} konnte nicht geladen werden");
            $all_updates_succeeded = false;
            break;
        } 
        

        if (!$zip_content || !file_put_contents($zip_file, $zip_content)) {

            $migrator->log("❌ Update {$version} konnte nicht geladen werden");

            $steps_log[] = "
            <div class='alert alert-danger mb-2'>
                <i class='bi bi-x-circle-fill me-2'></i>
                <b>Fehler:</b> Update <b>{$version}</b> konnte nicht geladen werden.
            </div>";

            // 🔥 LOG ANZEIGEN (das fehlte!)
            $steps_log[] = "
            <div class='alert alert-secondary mb-2'>
                <i class='bi bi-journal-code me-2'></i>
                {$migrator->getLog()}
            </div>";

            $all_updates_succeeded = false;
            #break;
        }

        $migrator->log("✅ ZIP {$version} erfolgreich gespeichert");

        $zip = new ZipArchive;
        if ($zip->open($zip_file) === TRUE) {

            #$zip->extractTo($tmp_dir, "admin/update_core/migrations/{$version}.php");
            #$zip->close();

            $zip = new ZipArchive;

            if ($zip->open($zip_file) === true) {

                $migrationFound = false;

                for ($i = 0; $i < $zip->numFiles; $i++) {

                    $name = $zip->getNameIndex($i);

                    // 🔍 Migration erkennen (egal in welchem Unterordner)
                    if (preg_match(
                        '#(^|/)' . preg_quote($version, '#') . '\.php$#',
                        $name
                    )) {

                        // Ziel sicherstellen
                        if (!is_dir("$tmp_dir/migrations")) {
                            mkdir("$tmp_dir/migrations", 0755, true);
                        }

                        // Extrahieren
                        $zip->extractTo($tmp_dir, [$name]);

                        // Quelle & Ziel
                        $src = $tmp_dir . '/' . $name;
                        $dst = "$tmp_dir/migrations/{$version}.php";

                        // Zielordner sicherstellen
                        if (!is_dir(dirname($dst))) {
                            mkdir(dirname($dst), 0755, true);
                        }

                        rename($src, $dst);

                        $migrator->log("📦 Migration {$version}.php extrahiert ({$name})");

                        $migrationFound = true;
                        break;
                    }
                }

                if (!$migrationFound) {
                    $migrator->log("⚠️ Version {$version} enthält keine Datenbank-Migration (Datei-Update).");
                }

                $zip->close();
            }


            $src = "$tmp_dir/admin/update_core/migrations/$version.php";
            if (file_exists($src)) {
                rename($src, $sql_file);
                $migrator->log("📦 Migration {$version}.php extrahiert");
            }
        }
    }

    // ✅ LOG AM ENDE DES SCHRITTES ANZEIGEN
    $logHtml = $migrator->getLog();
    if ($logHtml !== '') {
        $steps_log[] = "
        <div class='alert alert-secondary mb-2'>
            <i class='bi bi-journal-code me-2'></i>
            <b>Download-Log</b><br><br>
            <div class='p-3 bg-light border rounded'>
            {$logHtml}
            </div>
        </div>";
    }
}


// ============================================================
// 🧩 Schritt 3: Migrationen ausführen
// ============================================================
// 🔒 IMMER initialisieren
// ============================================================
// 🧩 Schritt 3: Migrationen ausführen (ROBUST & BUFFER-SICHER)
// ============================================================

/* ============================================================
   🧩 SCHRITT 3: Datenbank-Migrationen ausführen
   ============================================================ */

// 🔒 Flags IMMER initialisieren
$requiresNewUpdater = false;
$requiresVersion    = null;
$updatesToRun       = [];

/* ---------------------------------------
   Updates begrenzen (bis Hard-Stop)
---------------------------------------- */
foreach ($updates as $update) {

    $updatesToRun[] = $update;

    if (!empty($update['requires_new_updater'])) {
        $requiresNewUpdater = true;
        $requiresVersion    = $update['version'];
        break; // ⛔ NUR HIER stoppen
    }
}

if (!$all_updates_succeeded) {
    return;
}

$steps_log[] = "
<div class='alert alert-info mb-2'>
    <i class='bi bi-database me-2'></i>
    <b>3️⃣ Führe Datenbank-Migrationen aus</b>
</div>";

$steps_log[] = "
<div class='alert alert-secondary mb-2'>
    <i class='bi bi-journal-code me-2'></i>
    <b>Datenbank-Migrationen-Log</b><br><br>
    <div class='p-3 bg-light border rounded'>";

if (empty($updatesToRun)) {
    $steps_log[] = "
    <div class='alert alert-info py-1 my-1 small'>
        <i class='bi bi-info-circle-fill me-2'></i>
        Keine auszuführenden Migrationen gefunden.
    </div>";
}

/* ---------------------------------------
   Migrationen ausführen
---------------------------------------- */
foreach ($updatesToRun as $update) {

    $version = (string)($update['version'] ?? '');
    if ($version === '') {
        continue;
    }

    $sql_file = $tmp_dir . "/migrations/{$version}.php";
    if (!file_exists($sql_file)) {
        continue; // 📄 Kein DB-Update → sauber überspringen
    }

    $migrator    = new \nexpell\CMSDatabaseMigration($_database);
    $bufferLevel = ob_get_level();

    try {

        ob_start();

        // 🔥 MIGRATION LADEN
        $migration = include $sql_file;

        if (!is_callable($migration)) {
            throw new RuntimeException("Migration {$version} ist nicht callable.");
        }

        // 🔥 MIGRATION AUSFÜHREN
        $migration($migrator);

        $output = trim(ob_get_clean());

        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }

        if ($output !== '') {
            $steps_log[] = "
            <div class='alert alert-warning py-1 my-1 small'>
                <i class='bi bi-bug-fill me-2'></i>
                Migration {$version} erzeugte unerwartete Ausgabe
            </div>";
        }

        if ($migrator->getLog() !== '') {
            $steps_log[] = "
            <div class='alert alert-secondary py-1 my-1 small'>
                <i class='bi bi-journal-code me-2'></i>
                <b>Migrations-Details</b><br><br>
                {$migrator->getLog()}
            </div>";
        }

        $steps_log[] = "
        <div class='alert alert-success py-1 my-1 small'>
            <i class='bi bi-database-check me-2'></i>
            Migration <b>{$version}</b> erfolgreich abgeschlossen.
        </div>";

        /* ====================================================
           ✅ HISTORY NUR BEI ERFOLG
        ===================================================== */

        $channel = $update['channel'] ?? 'stable';
        $build   = (int)($update['build'] ?? 1);
        $notes   = $update['notes'] ?? 'Datenbank-Migration';

        safe_query("
            INSERT INTO system_update_history
                (version, channel, build, installed_at, installed_by, success, notes)
            VALUES (
                '" . escape($version) . "',
                '" . escape($channel) . "',
                {$build},
                {$installedAt},
                " . (int)($_SESSION['userID'] ?? 0) . ",
                1,
                '" . escape($notes) . "'
            )
            ON DUPLICATE KEY UPDATE
                installed_at = VALUES(installed_at),
                installed_by = VALUES(installed_by),
                success      = VALUES(success),
                notes        = VALUES(notes)
        ");

        /* ====================================================
           ⛔ HARD STOP NUR NACH MIGRATION + HISTORY
        ===================================================== */
        if (!empty($update['requires_new_updater'])) {
            break;
        }

    } catch (Throwable $e) {

        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }

        $steps_log[] = "
        <div class='alert alert-danger py-1 my-1 small'>
            <i class='bi bi-x-circle-fill me-2'></i>
            <b>Fehler in Migration {$version}:</b><br>
            " . htmlspecialchars($e->getMessage()) . "
        </div>";

        $all_updates_succeeded = false;
        break;
    }
}


$steps_log[] = "</div></div>";







    // ============================================================
    // 🧩 Schritt 4: Dateien entpacken & Änderungen auflisten
    // ============================================================
/* ============================================================
   🧩 SCHRITT 4: Dateien entpacken & Dateiänderungen erfassen
   ============================================================ */
/* ============================================================
   🧩 SCHRITT 4: Dateien entpacken & Dateiänderungen erfassen
   ============================================================ */

if (!$all_updates_succeeded) {
    return;
}

$steps_log[] = "
<div class='alert alert-info mb-2'>
    <i class='bi bi-box-seam me-2'></i>
    <b>4️⃣ Entpacke Update-Dateien und prüfe Dateiänderungen</b>
</div>";

$steps_log[] = "
<div class='alert alert-secondary mb-2'>
    <i class='bi bi-journal-code me-2'></i>
    <b>Datei-Installations-Log</b><br><br>
    <div class='p-3 bg-light border rounded'>";

/* ---------------------------------------
   Tracking
---------------------------------------- */
$files_created     = [];
$files_overwritten = [];
$files_deleted     = [];
$finalVersion      = null;

/* ---------------------------------------
   Updates ausführen
---------------------------------------- */
foreach ($updatesToRun as $update) {

    if (empty($update['version'])) {
        continue;
    }

    $version   = (string)$update['version'];
    $build     = (int)($update['build'] ?? 1);
    $channel   = $update['channel'] ?? 'stable';
    $notes     = $update['notes'] ?? '';
    $zip_file  = "{$tmp_dir}/update_{$version}.zip";

    if (!file_exists($zip_file)) {
        continue;
    }

    /* ===============================
       📦 ZIP öffnen & analysieren
    ================================ */
    $zip = new ZipArchive;
    if ($zip->open($zip_file) !== true) {
        $all_updates_succeeded = false;
        break;
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {

        $file = $zip->getNameIndex($i);
        if (str_ends_with($file, '/')) continue;

        $target = $extract_path . '/' . $file;

        if (file_exists($target)) {
            $files_overwritten[] = $file;
        } else {
            $files_created[] = $file;
        }
    }

    /* ===============================
       📂 Dateien entpacken
    ================================ */
    $zip->extractTo($extract_path);
    $zip->close();

    $steps_log[] = "
    <div class='alert alert-success py-1 my-1 small'>
        <i class='bi bi-box-seam-fill me-2'></i>
        Dateien für Version <b>{$version}</b> erfolgreich entpackt.
    </div>";

    /* ===============================
       🗑️ delete_files
    ================================ */
    if (!empty($update['delete_files']) && is_array($update['delete_files'])) {

        foreach ($update['delete_files'] as $rel) {

            $full = $extract_path . '/' . ltrim($rel, '/');
            if (is_file($full)) {
                unlink($full);
                $files_deleted[] = $rel;
            }
        }
    }

    /* ====================================================
       ✅ UPDATE-HISTORY (IMMER VOR HARD STOP)
    ===================================================== */
    safe_query("
        INSERT INTO system_update_history
            (version, channel, build, installed_at, installed_by, success, notes)
        VALUES (
            '" . escape($version) . "',
            '" . escape($channel) . "',
            {$build},
            {$installedAt},
            " . (int)($_SESSION['userID'] ?? 0) . ",
            1,
            '" . escape($notes) . "'
        )
        ON DUPLICATE KEY UPDATE
            installed_at = VALUES(installed_at),
            installed_by = VALUES(installed_by),
            success      = VALUES(success),
            notes        = VALUES(notes)
    ");

    $finalVersion = $version;

    /* ====================================================
       ⛔ HARD STOP – NUR NACH HISTORY
    ===================================================== */
    if (!empty($update['requires_new_updater'])) {

        $lockDir = __DIR__ . '/../admin/update_core';
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        file_put_contents(
            $lockDir . '/.updater_lock',
            json_encode([
                'version' => $version,
                'time'    => time()
            ])
        );

        $data_array['content'] = "
        <div class='alert alert-warning'>
            <i class='bi bi-exclamation-triangle-fill me-2'></i>
            <b>Updater {$version} wurde installiert.</b><br><br>
            Der Update-Prozess wurde bewusst angehalten,
            damit der neue Updater neu geladen wird.
        </div>

        <a href='admincenter.php?site=update_core&action=start'
           class='btn btn-secondary mt-3'>
            Neuer Updater laden
        </a>";

        echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
        exit;
    }
}

/* ---------------------------------------
   📊 Änderungsübersicht (NACH LOOP!)
---------------------------------------- */
$changes = [];

if ($files_created) {
    $changes[] = "<b>Neu erstellt (" . count($files_created) . ")</b><br>" .
                 implode('<br>', array_map('htmlspecialchars', $files_created));
}

if ($files_overwritten) {
    $changes[] = "<b>Überschrieben (" . count($files_overwritten) . ")</b><br>" .
                 implode('<br>', array_map('htmlspecialchars', $files_overwritten));
}

if ($files_deleted) {
    $changes[] = "<b>Gelöscht (" . count($files_deleted) . ")</b><br>" .
                 implode('<br>', array_map('htmlspecialchars', $files_deleted));
}

if ($changes) {
    $steps_log[] = "
    <div class='alert alert-secondary py-1 my-1 small'>
        <i class='bi bi-journal-code me-2'></i>
        <b>Dateiänderungen</b><br><br>
        " . implode('<br><br>', $changes) . "
    </div>";
}

$steps_log[] = "</div></div>";

/* ====================================================
   🏁 Version final setzen (NUR wenn kein Hard-Stop)
==================================================== */
if ($all_updates_succeeded && $finalVersion !== null) {

    file_put_contents(
        __DIR__ . '/../system/version.php',
        "<?php\nreturn '" . addslashes($finalVersion) . "';\n"
    );

    file_put_contents(
        __DIR__ . '/../system/last_update.txt',
        date('d.m.Y H:i:s')
    );
}




    // ============================================================
    // 🧩 Schritt 5: CMSUpdater
    // ============================================================
// ============================================================
// 🧩 Schritt 5: System-Synchronisation (NUR LOG / DRY-RUN)
// ============================================================

if ($all_updates_succeeded) {

    $steps_log[] = "
    <div class='alert alert-info mb-2'>
        <i class='bi bi-gear-wide-connected me-2'></i>
        <b>5️⃣ System-Synchronisation</b>
    </div>";

    try {
        // 🔒 IMMER Dry-Run!
        $cmsUpdater = new \nexpell\CMSUpdater(true);
        $cms_log_html = trim($cmsUpdater->runUpdates());

        if ($cms_log_html !== '') {
            $steps_log[] = "
            <div class='alert alert-secondary mb-2'>
                <i class='bi bi-journal-code me-2'></i>
                <b>CMS-Updater Log</b><br><br>
                {$cms_log_html}
            </div>";
        } else {
            $steps_log[] = "
            <div class='alert alert-success mb-2'>
                <i class='bi bi-check-circle-fill me-2'></i>
                System-Synchronisation ohne Meldungen abgeschlossen.
            </div>";
        }

    } catch (Throwable $e) {

        $steps_log[] = "
        <div class='alert alert-warning mb-2'>
            <i class='bi bi-exclamation-triangle-fill me-2'></i>
            <b>CMS-Updater-Warnung:</b><br>
            " . htmlspecialchars($e->getMessage()) . "
        </div>";
    }

} else {

    $steps_log[] = "
    <div class='alert alert-warning'>
        <i class='bi bi-exclamation-triangle-fill me-2'></i>
        CMS-Synchronisation übersprungen – Update wurde abgebrochen.
    </div>";
}









    // ============================================================
    // 🧩 Abschlussanzeige
    // ============================================================
    $data_array['content'] = "
        <div class='nx-steps'>
            " . implode("\n", $steps_log) . "
        </div>
        <form method='get' action='admincenter.php'>
            <input type='hidden' name='site' value='update_core'>
            <input type='hidden' name='action' value='finish'>
            <button class='btn btn-success mt-3'>
                <i class='bi bi-check2'></i> Abschluss anzeigen
            </button>
        </form>";

    echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
    exit;
}



/* ========================================================================
   🧩 ACTION: FINISH
   ======================================================================== */
if ($action === 'finish') {





    $version_file = __DIR__ . '/../system/version.php';
    $core_version = file_exists($version_file) ? include $version_file : 'unbekannt';

    $last_update_file = __DIR__ . '/../system/last_update.txt';
    $last_update_date = file_exists($last_update_file)
        ? file_get_contents($last_update_file)
        : 'Unbekannt';

    $data_array['content'] = "
    <div class='alert alert-success'>
        <i class='bi bi-check-circle-fill me-2'></i>
        System wurde erfolgreich aktualisiert auf Version 
        <b>" . htmlspecialchars($core_version) . "</b>.
    </div>
    <p class='small text-muted'>
        <i class='bi bi-clock-history me-1'></i>
        Aktualisiert am {$last_update_date}
    </p>
    <a href='admincenter.php?site=update_core&action=start' class='btn btn-primary mt-3'>
        <i class='bi bi-arrow-left-circle'></i> Zurück zur Übersicht
    </a>";
    
    echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
    
}

