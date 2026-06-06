<?php
/**
 * 🧩 NEXPELL UPDATE CORE – Wizard / Tab-Version
 * ----------------------------------------------
 * ✓ 3 Schritte (Vorbereitung → Migration → Abschluss)
 * ✓ Kein doppeltes Logging
 * ✓ Bootstrap 5.3 Tabs + Fortschrittsbalken
 * ✓ action=start / progress / finish
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) session_start();

use nexpell\CMSUpdater;
use nexpell\AccessControl;

require_once __DIR__ . '/../system/classes/CMSUpdater.php';
require_once __DIR__ . '/../system/classes/DatabaseMigrationHelper.php';

global $_language, $_database;

$tpl = new Template();
$data_array = [];

$version_file = __DIR__ . '/../system/version.php';
$core_version = file_exists($version_file) ? include $version_file : '1.0.0';
define('CURRENT_VERSION', $core_version);

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

$action = $_GET['action'] ?? 'start';

// ============================================================
// 🧩 Update-Info-Datei abrufen und prüfen
// ============================================================
$update_info_url = "https://update.nexpell.de/updates/update_info.json";
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
            <b>Ressource:</b> <code>/updates/update_info.json</code><br>
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
            <b>Datei:</b> <code>update_info.json</code><br>
            <b>JSON-Fehler:</b> " . htmlspecialchars(json_last_error_msg()) . "<br>
            <b>Hinweis:</b> Möglicherweise ist die Datei beschädigt oder leer.
        </div>
    </div>";
    exit;
}

// --- Erfolgreich: Updates einlesen ---
$updates = array_values(array_filter(
    $update_info['updates'],
    fn($entry) => version_compare($entry['version'], CURRENT_VERSION, '>')
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
// Updates filtern
// -------------------------------------------------------
$updates = array_filter($update_info['updates'], function ($entry) use ($client_ip, $user_email) {

    // Normale Updates → immer sichtbar
    if (empty($entry['beta']) || !$entry['beta']) {
        return true;
    }

    // Beta + visible_for = ["all"] → alle dürfen sehen
    if (!empty($entry['visible_for']) && in_array('all', $entry['visible_for'], true)) {
        return true;
    }

    // Beta → nur bestimmte Nutzer/IPs
    if (!empty($entry['visible_for'])) {
        foreach ($entry['visible_for'] as $allowed) {
            if ($client_ip === $allowed || $user_email === $allowed) {
                return true;
            }
        }
    }

    // Standard: Nicht sichtbar
    return false;
});


// -------------------------------------------------------
// Nur neuere Versionen als aktuelle anzeigen
// -------------------------------------------------------
$updates = array_values(array_filter(
    $updates,
    fn($entry) => version_compare($entry['version'], CURRENT_VERSION, '>')
));



/* ========================================================================
   🧩 ACTION: START
   ======================================================================== */
/* ========================================================================
   🧩 ACTION: START
   ======================================================================== */
if ($action === 'start') {
    $last_update_file = __DIR__ . '/../system/last_update.txt';
    $last_update_date = file_exists($last_update_file)
        ? file_get_contents($last_update_file)
        : 'unbekannt';

    $updateCount = count($updates);

    /* ============================================================
       🧩 FALL 1: Keine Updates verfügbar
       ============================================================ */
    if ($updateCount === 0) {
        $data_array['content'] = "
        <div class='alert alert-success'>
            <i class='bi bi-check-circle me-2'></i>
            Du verwendest bereits die neueste Version 
            <b>" . htmlspecialchars(CURRENT_VERSION) . "</b>.<br>
            <span class='small text-muted'>
                <i class='bi bi-clock-history me-1'></i>
                Installiert am {$last_update_date}
            </span>
        </div>

        <div class='alert alert-info mt-3 small'>
            <i class='bi bi-info-circle me-2'></i>
            Es sind derzeit keine weiteren Updates verfügbar. 
            Dein System ist auf dem neuesten Stand.
        </div>";
    }

    /* ============================================================
       🧩 FALL 2: Updates sind verfügbar
       ============================================================ */
    else {
        $versions = array_column($updates, 'version');
        $log = "";

        foreach ($updates as $update) {
            $ver = htmlspecialchars($update['version']);
            $desc = nl2br(htmlspecialchars($update['changelog'] ?? 'Keine Beschreibung.'));
            $log .= "🟢 <b>{$ver}</b>: {$desc}<br>";
        }

        // 🔧 Serverprüfung
        function nx_isUpdateServerReachable(string $url): bool {
            $headers = @get_headers($url);
            if (!$headers) return false;
            return (strpos($headers[0], '200') !== false);
        }

        $update_info_url = "https://update.nexpell.de/updates/update_info.json";
        $server_ok = nx_isUpdateServerReachable($update_info_url);

        $server_status = $server_ok
            ? "<span class='text-success'><i class='bi bi-check-circle-fill me-1'></i> Verbindung erfolgreich</span>"
            : "<span class='text-danger'><i class='bi bi-x-circle-fill me-1'></i> Keine Verbindung möglich</span>";

        $update_count_text = ($updateCount === 1)
            ? "Ein neues Update steht bereit!"
            : "$updateCount Updates stehen zur Installation bereit!";

        /* ============================================================
           📋 UI-Struktur
           ============================================================ */
        $data_array['content'] = "
        <!-- 🔹 Aktuelle Version -->
        <div class='alert alert-secondary mb-4'>
            <i class='bi bi-info-circle me-2'></i>
            Aktuell installierte Version: 
            <b>" . htmlspecialchars(CURRENT_VERSION) . "</b><br>
            <span class='small text-muted'>
                <i class='bi bi-clock-history me-1'></i>
                Installiert am {$last_update_date}
            </span>
        </div>

        <!-- 🔹 Verbindung zum Update-Server -->
        <div class='alert alert-light border mb-4 small'>
            <i class='bi bi-hdd-network me-2 text-primary'></i>
            Update-Server: <code>update.nexpell.de</code><br>
            Status: {$server_status}
        </div>

        <!-- 🔹 Update-Informationen -->
        <div class='alert alert-primary'>
            <h5 class='mb-1'>
                <i class='bi bi-rocket-takeoff-fill me-2 text-primary'></i>
                {$update_count_text}
            </h5>
            <p class='mb-2 small'>
                Es wurden neue Versionen des Nexpell-Cores gefunden, die 
                <b>wichtige Verbesserungen, Sicherheits-Patches</b> und 
                <b>neue Funktionen</b> enthalten.  
                <br>Starte jetzt das Update, um dein System auf dem 
                neuesten Stand zu halten und volle Stabilität 
                sowie Performance zu gewährleisten.
            </p>
            <hr class='my-2'>
            <div class='small'>
                <i class='bi bi-info-circle me-1 text-info'></i>
                <b>Verfügbare Versionen:</b> " . implode(', ', $versions) . "
            </div>
        </div>

        <!-- 🔹 Änderungsprotokoll -->
        <div class='border rounded p-2 small bg-light mb-3'>
            {$log}
        </div>

        <!-- 🔹 Wichtige Hinweise -->
        <div class='alert alert-warning mt-4 small' style=\"background:#fff8e1;border:1px solid #ffeeba;\">
            <h6 class='fw-bold mb-2'>
                <i class='bi bi-shield-exclamation me-2 text-warning'></i>
                Wichtige Hinweise vor dem Update
            </h6>
            <ul class='mb-0 ps-3'>
                <li><b>Sicherung:</b> Bitte vor dem Update ein vollständiges Backup der Datenbank und Systemdateien anlegen.</li>
                <li><b>Benutzeraktivität:</b> Update außerhalb Stoßzeiten.</li>
                <li><b>Anpassungen:</b> Eigene Systemdateien prüfen.</li>
                <li><b>Internet:</b> Verbindung zu <code>update.nexpell.de</code> erforderlich.</li>
                <li><b>PHP-Version:</b> Empfohlen ≥ 8.2.</li>
            </ul>
        </div>

        <!-- 🔹 Update starten -->
        <form method='post' action='admincenter.php?site=update_core&action=progress'>
            <button class='btn btn-success shadow-sm mt-3'>
                <i class='bi bi-arrow-clockwise me-1'></i> Update jetzt starten
            </button>
        </form>";
    }

    echo $tpl->loadTemplate("update_core", "wizard", $data_array, "admin");
    #exit;
}




/* ========================================================================
   🧩 ACTION: PROGRESS
   ======================================================================== */
if ($action === 'progress') {
    $steps_log = [];
    $migration_logs_combined = '';
    $tmp_dir = __DIR__ . '/tmp';
    $extract_path = __DIR__ . '/..';
    $new_version = CURRENT_VERSION;
    $all_updates_succeeded = true;

    // ============================================================
    // 🧩 Schritt 1: tmp prüfen
    // ============================================================
    $steps_log[] = "1️⃣ Prüfe tmp-Verzeichnis...";
    if (!is_dir($tmp_dir) && !mkdir($tmp_dir, 0755, true)) {
        $steps_log[] = "❌ tmp-Verzeichnis konnte nicht erstellt werden.";
        $all_updates_succeeded = false;
    }

    // ============================================================
    // 🧩 Schritt 2: Updates herunterladen
    // ============================================================
    if ($all_updates_succeeded) {
        $steps_log[] = "2️⃣ Lade Updates herunter...";
        foreach ($updates as $update) {
            $version = $update['version'];
            $zip_url = $update['zip_url'];
            $zip_file = "$tmp_dir/update_$version.zip";
            $sql_file = "$tmp_dir/migrations/$version.php";

            if (!is_dir("$tmp_dir/migrations")) mkdir("$tmp_dir/migrations", 0755, true);

            $zip_content = @file_get_contents($zip_url);
            if (!$zip_content || !file_put_contents($zip_file, $zip_content)) {
                $steps_log[] = "❌ Fehler: Update $version konnte nicht geladen werden.";
                $all_updates_succeeded = false;
                break;
            }

            $zip = new ZipArchive;
            if ($zip->open($zip_file) === TRUE) {
                $zip->extractTo($tmp_dir, "admin/update_core/migrations/{$version}.php");
                $zip->close();
                $src = "$tmp_dir/admin/update_core/migrations/$version.php";
                if (file_exists($src)) rename($src, $sql_file);
            }
        }
    }

    // ============================================================
    // 🧩 Schritt 3: Migrationen ausführen
    // ============================================================
    if ($all_updates_succeeded) {
        $steps_log[] = "3️⃣ Führe Datenbank-Migrationen aus...";
        foreach ($updates as $update) {
            $version = $update['version'];
            $sql_file = "$tmp_dir/migrations/$version.php";
            if (!file_exists($sql_file)) continue;

            try {
                global $migrator;
                $migrator = new \nexpell\DatabaseMigrationHelper($_database);
                ob_start();
                include $sql_file;
                $migration_output = trim(ob_get_clean());

                if (method_exists($migrator, 'getLog')) {
                    $migration_logs_combined .= $migrator->getLog();
                }
                if ($migration_output !== '') {
                    $migration_logs_combined .= "<pre class='small bg-light p-2 border rounded mb-1'>" .
                        htmlspecialchars($migration_output) . "</pre>";
                }

                $steps_log[] = "✅ Migration $version abgeschlossen.";
                $new_version = $version;

            } catch (Throwable $e) {
                $steps_log[] = "❌ Fehler in Migration $version:<br>" . htmlspecialchars($e->getMessage());
                $all_updates_succeeded = false;
                break;
            }
        }
    }

    // ============================================================
    // 🧩 Schritt 4: Dateien entpacken & Änderungen auflisten
    // ============================================================
    if ($all_updates_succeeded) {
        $steps_log[] = "4️⃣ Entpacke Update-Dateien und prüfe Dateiänderungen...";

        $files_created = [];
        $files_overwritten = [];
        $files_deleted = [];

        foreach ($updates as $update) {
            $version = $update['version'];
            $zip_file = "{$tmp_dir}/update_{$version}.zip";
            if (!file_exists($zip_file)) continue;

            $zip = new ZipArchive;
            if ($zip->open($zip_file) === TRUE) {
                // 🔍 Alle Dateien im ZIP durchgehen
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $file = $zip->getNameIndex($i);
                    if (substr($file, -1) === '/') continue; // Skip Ordner

                    $target_path = $extract_path . '/' . $file;

                    if (file_exists($target_path)) {
                        $files_overwritten[] = $file;
                    } else {
                        $files_created[] = $file;
                    }
                }

                // 🔧 Dateien extrahieren
                $zip->extractTo($extract_path);
                $zip->close();

                $steps_log[] = "<div class='text-success small'>✅ Dateien für Version {$version} erfolgreich entpackt.</div>";

                // 🔥 Gelöschte Dateien aus Manifest
                if (!empty($update['delete_files']) && is_array($update['delete_files'])) {
                    foreach ($update['delete_files'] as $rel_path) {
                        $full = $extract_path . '/' . $rel_path;
                        if (file_exists($full)) {
                            unlink($full);
                            $files_deleted[] = $rel_path;
                        }
                    }
                }
            }
        }

        // === Übersicht der Dateiänderungen ===
        $total_new = count($files_created);
        $total_over = count($files_overwritten);
        $total_del = count($files_deleted);

        $steps_log[] = "<h6 class='mt-3'><i class='bi bi-folder-check me-2 text-success'></i>Dateiänderungen:</h6>
        <ul class='small list-unstyled mb-2'>
            <li><i class='bi bi-file-earmark-plus-fill text-success me-2'></i><b>Neu erstellt:</b> {$total_new}</li>
            <li><i class='bi bi-arrow-repeat text-primary me-2'></i><b>Überschrieben:</b> {$total_over}</li>
            <li><i class='bi bi-trash3-fill text-danger me-2'></i><b>Gelöscht:</b> {$total_del}</li>
        </ul>";

        if ($total_new > 0) {
            $steps_log[] = "<div class='alert alert-success small py-2 mb-2 border-0'>
                <i class='bi bi-file-earmark-plus me-2'></i><b>Neu erstellt:</b><br>" .
                implode('<br>', array_map('htmlspecialchars', $files_created)) . "
            </div>";
        }

        if ($total_over > 0) {
            $steps_log[] = "<div class='alert small py-2 mb-2 border-0' style='background:#e9f3ff;color:#0d6efd'>
                <i class='bi bi-arrow-repeat me-2'></i><b>Überschrieben:</b><br>" .
                implode('<br>', array_map('htmlspecialchars', $files_overwritten)) . "
            </div>";
        }

        if ($total_del > 0) {
            $steps_log[] = "<div class='alert alert-danger small py-2 mb-2 border-0'>
                <i class='bi bi-trash3-fill me-2'></i><b>Gelöscht:</b><br>" .
                implode('<br>', array_map('htmlspecialchars', $files_deleted)) . "
            </div>";
        }

        // ✅ Version-Datei aktualisieren
        if (!empty($new_version)) {
            $version_file = __DIR__ . '/../system/version.php';
            $version_content = "<?php\nreturn '" . addslashes($new_version) . "';\n";
            if (@file_put_contents($version_file, $version_content) !== false) {
                $steps_log[] = "<div class='text-success small'>✅ Version-Datei aktualisiert auf {$new_version}.</div>";
            } else {
                $steps_log[] = "<div class='text-danger small'>❌ Fehler: Version-Datei konnte nicht aktualisiert werden (<code>{$version_file}</code>).</div>";
            }
        }

        // 🕓 Datum speichern
        @file_put_contents(__DIR__ . '/../system/last_update.txt', date('d.m.Y H:i:s'));
    }

    // ============================================================
    // 🧩 Schritt 5: CMSUpdater
    // ============================================================
    if ($all_updates_succeeded) {
        $steps_log[] = "<h5 class='mt-4'><i class='bi bi-gear-wide-connected me-2'></i>System-Synchronisation:</h5>";
        if ($migration_logs_combined !== '') {
            $steps_log[] = "<div class='bg-white border rounded small p-2 mb-3' 
                style='max-height:70vh;overflow-y:auto;'>{$migration_logs_combined}</div>";
        }

        try {
            $cmsUpdater = new \nexpell\CMSUpdater();
            $cms_log_html = $cmsUpdater->runUpdates();
            $steps_log[] = $cms_log_html;
        } catch (Throwable $e) {
            $steps_log[] = "<div class='alert alert-warning'>⚠️ CMSUpdater-Fehler: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // ============================================================
    // 🧩 Schritt 6: Aufräumen
    // ============================================================
    $steps_log[] = "5️⃣ Bereinige temporäre Dateien...";
    foreach (glob("$tmp_dir/update_*.zip") as $f) @unlink($f);
    foreach (glob("$tmp_dir/migrations/*.php") as $f) @unlink($f);
    @rmdir("$tmp_dir/migrations");
    $steps_log[] = "<div class='text-secondary small'>🧹 Temporäre Dateien & alte Migrationen entfernt.</div>";

    // ============================================================
    // 🧩 Abschlussanzeige
    // ============================================================
    $data_array['content'] = "
        <ul class='list-unstyled small mb-0'>
            <li>" . implode("</li><li>", $steps_log) . "</li>
        </ul>
        <form method='get' action='admincenter.php'>
            <input type='hidden' name='site' value='update_core'>
            <input type='hidden' name='action' value='finish'>
            <button class='btn btn-success mt-3'>
                <i class='bi bi-check2'></i> Abschluss anzeigen
            </button>
        </form>";

    echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
    #exit;
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
    #exit;
}

