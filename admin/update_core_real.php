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

global $_language, $_database, $languageService;

$tpl = new Template();
$data_array = [];
$data_array['update_history'] = '';

if (isset($languageService) && method_exists($languageService, 'readModule')) {
    // Prefer route/module name used by admincenter.php, keep fallback for legacy file naming.
    $languageService->readModule('update_core', true);
    $languageService->readModule('update_core_real', true);
}

if (!function_exists('nx_t')) {
    function nx_t(string $key, string $fallback = ''): string
    {
        global $languageService;

        if (isset($languageService) && method_exists($languageService, 'get')) {
            $val = (string)$languageService->get($key);
            if ($val !== '' && $val !== '[' . $key . ']') {
                return $val;
            }
        }

        return $fallback;
    }
}

if (!function_exists('nx_normalize_update_channel')) {
    function nx_normalize_update_channel(?string $channel): string
    {
        $channel = strtolower(trim((string)$channel));
        return in_array($channel, ['stable', 'beta', 'dev'], true) ? $channel : 'stable';
    }
}

if (!function_exists('nx_get_update_channel')) {
    function nx_get_update_channel(): string
    {
        global $_database;

        if (!$_database instanceof mysqli) {
            return 'stable';
        }

        $res = $_database->query("SELECT update_channel FROM settings LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return nx_normalize_update_channel($row['update_channel'] ?? 'stable');
        }

        return 'stable';
    }
}

if (!function_exists('nx_set_update_channel')) {
    function nx_set_update_channel(string $channel): string
    {
        global $_database;

        $channel = nx_normalize_update_channel($channel);
        if (!$_database instanceof mysqli) {
            return $channel;
        }

        $_database->query("
            UPDATE settings
            SET update_channel = '" . $_database->real_escape_string($channel) . "'
        ");

        return $channel;
    }
}

if (!function_exists('nx_download_site_host')) {
    function nx_download_site_host(): string
    {
        $candidates = [
            $_SERVER['HTTP_HOST'] ?? '',
            $_SERVER['SERVER_NAME'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $host = strtolower(trim((string)$candidate));
            $host = preg_replace('/:\d+$/', '', $host);
            $host = preg_replace('/[^a-z0-9.\-]/i', '', $host);

            if ($host !== '') {
                return $host;
            }
        }

        return 'unknown';
    }
}

if (!function_exists('nx_append_download_site')) {
    function nx_append_download_site(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!str_contains($url, 'system/download.php')) {
            return $url;
        }

        $site = nx_download_site_host();
        if ($site === 'unknown') {
            return $url;
        }

        if (preg_match('/([?&])site=/', $url)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'site=' . rawurlencode($site);
    }
}

if (!function_exists('nx_fetch_url')) {
    function nx_fetch_url(string $url): string|false
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'User-Agent: Nexpell Core Updater',
                    'Accept: application/vnd.github+json, application/json',
                ]),
                'timeout' => 20,
            ],
        ]);

        return @file_get_contents($url, false, $context);
    }
}

if (!function_exists('nx_normalize_github_tag_version')) {
    function nx_normalize_github_tag_version(string $tag): string
    {
        $tag = trim($tag);
        $tag = preg_replace('/^nexpell[-_]/i', '', $tag) ?? $tag;
        $tag = preg_replace('/^core[-_]/i', '', $tag) ?? $tag;
        $tag = preg_replace('/^v/i', '', $tag) ?? $tag;

        return trim($tag);
    }
}

if (!function_exists('nx_build_github_update_info')) {
    function nx_build_github_update_info(array $tags): array
    {
        $updates = [];

        foreach ($tags as $tag) {
            if (!is_array($tag)) {
                continue;
            }

            $tagName = (string)($tag['name'] ?? '');
            $version = nx_normalize_github_tag_version($tagName);
            if ($tagName === '' || !preg_match('/^\d+\.\d+\.\d+(?:[.\-+][A-Za-z0-9._-]+)?$/', $version)) {
                continue;
            }

            $zipUrl = 'https://github.com/nexpell/nexpell/archive/refs/tags/' . rawurlencode($tagName) . '.zip';
            $channel = preg_match('/[a-z]/i', $version) ? 'beta' : 'stable';

            $updates[] = [
                'version' => $version,
                'channel' => $channel,
                'build' => 1,
                'zip_url' => $zipUrl,
                'source' => 'github',
                'source_type' => 'github',
                'tag' => $tagName,
                'visible_for' => ['all'],
                'notes' => 'GitHub tag ' . $tagName,
                'changelog' => 'Core-Update aus GitHub-Tag ' . $tagName,
            ];
        }

        usort($updates, static function (array $a, array $b): int {
            return version_compare((string)$a['version'], (string)$b['version']);
        });

        return [
            'source' => 'github',
            'repository' => 'nexpell/nexpell',
            'updates' => $updates,
        ];
    }
}

if (!function_exists('nx_load_update_info')) {
    function nx_load_update_info(string $url, string &$rawJson): array
    {
        $rawJson = nx_fetch_url($url);
        if ($rawJson === false || $rawJson === '') {
            return [];
        }

        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['updates']) && is_array($decoded['updates'])) {
            return $decoded;
        }

        return nx_build_github_update_info($decoded);
    }
}

if (!function_exists('nx_update_is_github_package')) {
    function nx_update_is_github_package(array $update): bool
    {
        $source = strtolower((string)($update['source'] ?? $update['source_type'] ?? ''));
        $zipUrl = strtolower((string)($update['zip_url'] ?? ''));

        return $source === 'github' || str_contains($zipUrl, 'api.github.com/repos/nexpell/nexpell/zipball');
    }
}

if (!function_exists('nx_update_zip_root_prefix')) {
    function nx_update_zip_root_prefix(ZipArchive $zip): string
    {
        $root = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            $name = ltrim($name, '/');
            if ($name === '') {
                continue;
            }

            $first = explode('/', $name, 2)[0] ?? '';
            if ($first === '') {
                continue;
            }

            if ($root === '') {
                $root = $first;
                continue;
            }

            if ($root !== $first) {
                return '';
            }
        }

        return $root !== '' ? $root . '/' : '';
    }
}

if (!function_exists('nx_update_zip_relative_file')) {
    function nx_update_zip_relative_file(string $file, ZipArchive $zip, array $update): string
    {
        $file = ltrim(str_replace('\\', '/', $file), '/');
        if (!nx_update_is_github_package($update)) {
            return $file;
        }

        $rootPrefix = nx_update_zip_root_prefix($zip);
        if ($rootPrefix !== '' && str_starts_with($file, $rootPrefix)) {
            return substr($file, strlen($rootPrefix));
        }

        return $file;
    }
}

if (!function_exists('nx_update_copy_directory_contents')) {
    function nx_update_copy_directory_contents(string $source, string $target): void
    {
        $source = rtrim($source, '/\\');
        $target = rtrim($target, '/\\');

        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $source . DIRECTORY_SEPARATOR . $item;
            $dst = $target . DIRECTORY_SEPARATOR . $item;

            if (is_dir($src)) {
                nx_update_copy_directory_contents($src, $dst);
                continue;
            }

            if (!is_dir(dirname($dst))) {
                mkdir(dirname($dst), 0755, true);
            }
            copy($src, $dst);
        }
    }
}

if (!function_exists('nx_update_extract_package')) {
    function nx_update_extract_package(ZipArchive $zip, string $extractPath, array $update, string $tmpDir): bool
    {
        if (!nx_update_is_github_package($update)) {
            return $zip->extractTo($extractPath);
        }

        $stageDir = rtrim($tmpDir, '/\\') . '/github_extract_' . preg_replace('/[^a-z0-9._-]/i', '_', (string)($update['version'] ?? 'update'));
        if (is_dir($stageDir) && function_exists('recursiveRemoveDirectory')) {
            recursiveRemoveDirectory($stageDir);
        }
        if (!is_dir($stageDir)) {
            mkdir($stageDir, 0755, true);
        }

        if (!$zip->extractTo($stageDir)) {
            return false;
        }

        $rootPrefix = rtrim(nx_update_zip_root_prefix($zip), '/');
        $sourceRoot = $rootPrefix !== '' && is_dir($stageDir . '/' . $rootPrefix)
            ? $stageDir . '/' . $rootPrefix
            : $stageDir;

        nx_update_copy_directory_contents($sourceRoot, $extractPath);

        if (function_exists('recursiveRemoveDirectory')) {
            recursiveRemoveDirectory($stageDir);
        }

        return true;
    }
}

$data_array['update_title'] = nx_t('update_title', 'Nexpell Core Updater');
$data_array['update_subtitle'] = nx_t('update_subtitle', 'Core-Updates prüfen, herunterladen und installieren');
$data_array['update_channel_title'] = nx_t('update_channel_title', 'Update-Kanal');
$data_array['update_channel_hint'] = nx_t('update_channel_hint', 'Wähle aus, welche Art von Updates angezeigt und installiert werden sollen.');
$data_array['update_progress_title'] = nx_t('update_progress_title', 'Update-Fortschritt');
$data_array['update_steps_title'] = nx_t('update_steps_title', 'Schritte');
$data_array['update_log_title'] = nx_t('update_log_title', 'Updates');
$data_array['update_footer_hint'] = nx_t('update_footer_hint', 'Sicherer Core-Updater · Nexpell CMS');

$version_file = __DIR__ . '/../system/version.php';
$core_version = file_exists($version_file) ? include $version_file : '1.0.0';
define('CURRENT_VERSION', $core_version);

function nx_update_normalize_initial_history(mysqli $_database, string $currentVersion, int $userID): void
{
    if ($currentVersion !== '1.0.3.3') {
        return;
    }

    $tableCheck = safe_query("SHOW TABLES LIKE 'system_update_history'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        return;
    }

    $res = safe_query("
        SELECT version, installed_at, installed_by
        FROM system_update_history
        ORDER BY installed_at ASC, id ASC
    ");

    $history = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $history[] = [
            'version' => (string)($row['version'] ?? ''),
            'installed_at' => (int)($row['installed_at'] ?? 0),
            'installed_by' => (int)($row['installed_by'] ?? 0),
        ];
    }

    $hasCurrent = false;
    $maxVersion = '';
    $installedAt = 0;
    $installedBy = $userID > 0 ? $userID : 1;

    foreach ($history as $entry) {
        if ($entry['version'] === $currentVersion) {
            $hasCurrent = true;
        }
        if ($entry['version'] !== '' && ($maxVersion === '' || version_compare($entry['version'], $maxVersion, '>'))) {
            $maxVersion = $entry['version'];
        }
        if ($entry['installed_at'] > 0 && $installedAt === 0) {
            $installedAt = $entry['installed_at'];
        }
        if ($entry['installed_by'] > 0) {
            $installedBy = $entry['installed_by'];
        }
    }

    if ($hasCurrent) {
        return;
    }

    if (!empty($history) && $maxVersion !== '' && version_compare($maxVersion, $currentVersion, '>=')) {
        return;
    }

    if ($installedAt === 0) {
        $installedAt = time();
    }

    safe_query("DELETE FROM system_update_history");
    safe_query("
        INSERT INTO system_update_history
            (version, channel, build, installed_at, installed_by, success, notes)
        VALUES
            ('" . escape($currentVersion) . "', 'stable', 1, {$installedAt}, {$installedBy}, 1, 'Erstinstallation von Nexpell {$currentVersion}')
    ");
}

nx_update_normalize_initial_history($_database, CURRENT_VERSION, (int)($userID ?? 1));


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

// Update-Kanal vor der ersten Ausgabe speichern, damit der Redirect zuverlässig funktioniert.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_channel'])) {
    nx_set_update_channel((string)$_POST['update_channel']);
    header("Location: admincenter.php?site=update_core");
    exit;
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
$settings['update_channel'] = nx_get_update_channel();







$action = $_GET['action'] ?? 'start';

// ============================================================
// 🧩 Update-Info-Datei abrufen und prüfen
// ============================================================
$update_info_url = "https://api.github.com/repos/nexpell/nexpell/tags?per_page=100";
$update_source_host = 'github.com';
$update_source_label = 'GitHub Tags';
$update_source_resource = 'nexpell/nexpell';
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
$update_info_json = '';
$update_info = nx_load_update_info($update_info_url, $update_info_json);

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
            <b>Server:</b> <code>{$update_source_host}</code><br>
            <b>Ressource:</b> <code>{$update_source_resource}</code><br>
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
                <i class='bi bi-clock me-1'></i> Pruefe Verbindung zu <code>{$update_source_host}</code> ...
            </span>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        fetch('https://github.com/', { method: 'HEAD', mode: 'no-cors' })
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
if (json_last_error() !== JSON_ERROR_NONE || !isset($update_info['updates']) || !is_array($update_info['updates'])) {
    echo "
    <div class='alert alert-danger m-3'>
        <h5 class='fw-bold mb-2'>
            <i class='bi bi-file-earmark-excel-fill me-2'></i>
            Update-Informationen konnten nicht korrekt verarbeitet werden
        </h5>
        <div class='small'>
            <b>Datei:</b> <code>{$update_source_label}</code><br>
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
$channel = nx_get_update_channel();

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
$current_index = is_int($current_index) ? $current_index : 0;
$total_steps = count($steps_nav);
$progress_percent = (int)round((($current_index + 1) / max(1, $total_steps)) * 100);
$current_step = $steps_nav[$action] ?? reset($steps_nav);
$current_title = htmlspecialchars((string)($current_step['title'] ?? 'Update'), ENT_QUOTES, 'UTF-8');

$wizard_nav_html = "
<div class='p-3' aria-label='Update-Fortschritt'>
    <div class='d-flex align-items-center justify-content-between gap-3 mb-2'>
        <div>
            <div class='small text-uppercase fw-bold text-muted'>Update-Fortschritt</div>
            <div class='fw-semibold'>{$current_title}</div>
        </div>
        <span class='badge bg-warning text-dark'>{$progress_percent}%</span>
    </div>
    <div class='progress mb-3' role='progressbar' aria-valuenow='{$progress_percent}' aria-valuemin='0' aria-valuemax='100' style='height: 8px;'>
        <div class='progress-bar bg-warning' style='width: {$progress_percent}%'></div>
    </div>
    <div class='row g-2'>";

$i = 0;
foreach ($steps_nav as $key => $step) {
    $step_index = $i;
    $number = $i + 1;
    $state = 'pending';
    if ($step_index < $current_index) {
        $state = 'done';
    } elseif ($step_index === $current_index) {
        $state = 'active';
    }

    $title = htmlspecialchars((string)$step['title'], ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars((string)$step['icon'], ENT_QUOTES, 'UTF-8');

    $stepClass = $state === 'active' ? 'border-warning bg-warning bg-opacity-10' : ($state === 'done' ? 'border-success bg-success bg-opacity-10' : 'border-light bg-light');
    $iconClass = $state === 'active' ? 'text-warning' : ($state === 'done' ? 'text-success' : 'text-muted');

    $wizard_nav_html .= "
        <div class='col-12 col-md-4'>
            <div class='border rounded p-2 h-100 {$stepClass}'>
                <div class='small text-muted'>Schritt {$number}</div>
                <div class='fw-semibold'>
                    <i class='bi {$icon} {$iconClass} me-1'></i>{$title}
                </div>
            </div>
        </div>";
    $i++;
}

$wizard_nav_html .= "
    </div>
</div>";


$data_array['wizard_nav'] = $wizard_nav_html;
$data_array['progress_bar'] = $wizard_nav_html;
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
$settings['update_channel'] = nx_get_update_channel();

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

        if (version_compare((string)$entry['version'], CURRENT_VERSION, '<=')) {
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

    nx_set_update_channel($newChannel);

    header("Location: admincenter.php?site=update_core");
    exit;
}

$sel_stable = ($channel === 'stable') ? 'selected' : '';
$sel_beta   = ($channel === 'beta')   ? 'selected' : '';
$sel_dev    = ($channel === 'dev')    ? 'selected' : '';

$data_array['channel_form'] = '
<form method="post" action="admincenter.php?site=update_core">
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

// Kanal immer frisch aus der Datenbank lesen, damit kein alter Fallback greift.
$channel = nx_get_update_channel();
$settings['update_channel'] = $channel;
$user_email = strtolower(trim($user_email ?? ''));

$updates = array_filter(
    $update_info['updates'] ?? [],
    function ($entry) use ($channel, $user_email, $client_ip) {

        if (!is_array($entry) || empty($entry['version']) || empty($entry['channel'])) {
            return false;
        }

        if (version_compare((string)$entry['version'], CURRENT_VERSION, '<=')) {
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

    nx_set_update_channel($newChannel);

    // 🔁 PRG: Redirect, damit der neue Zustand sauber geladen wird
    header("Location: admincenter.php?site=update_core");
    exit;
}

$settings['update_channel'] = nx_get_update_channel();
$channel = $settings['update_channel'];

$channel = $settings['update_channel'] ?? 'stable';

$sel_stable = ($channel === 'stable') ? 'selected' : '';
$sel_beta   = ($channel === 'beta')   ? 'selected' : '';
$sel_dev    = ($channel === 'dev')    ? 'selected' : '';

$channel_form = '
<form method="post" action="admincenter.php?site=update_core">
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




$channelBadgeClass = match ($channel) {
    'dev' => 'nx-update-badge--danger',
    'beta' => 'nx-update-badge--warning',
    default => 'nx-update-badge--success',
};
$channelBadgeText = match ($channel) {
    'dev' => 'Dev',
    'beta' => 'Beta',
    default => 'Stable',
};
$channelBadgeHint = match ($channel) {
    'dev' => 'Entwickler-Builds',
    'beta' => 'Vorab-Updates',
    default => 'Gepruefte Updates',
};
$beta_badge = '<div class="nx-channel-status"><span class="nx-update-badge ' . $channelBadgeClass . '">' . $channelBadgeText . '</span><span>' . $channelBadgeHint . '</span></div>';

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
$channel = nx_get_update_channel();

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

    $data_array['update_history'] = $history_html;


    /* ============================================================
       🌐 UPDATE-SERVER PRÜFEN
       ============================================================ */
    function nx_isUpdateServerReachable(string $url): bool {
        $headers = @get_headers($url);
        return $headers && str_contains($headers[0], '200');
    }

    $server_ok = is_array($update_info['updates'] ?? null);

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

        ";

        $data_array['content'] = "
<div class='nx-update-overview'>
    <div class='nx-update-hero nx-update-hero--stable'>
        <div class='nx-update-hero__main'>
            <div class='nx-update-hero__eyebrow'>
                <i class='bi bi-shield-check me-1'></i>
                Core Status
            </div>
            <h3>Dein System ist aktuell.</h3>
            <p>Alle bekannten Stabilitaets- und Sicherheitsupdates wurden erfolgreich installiert.</p>
        </div>
        <div class='nx-update-hero__badge'>
            <span class='nx-update-badge nx-update-badge--success'>Aktuell</span>
        </div>
    </div>

    <div class='nx-update-summary-grid'>
        <div class='nx-update-summary-card'>
            <span class='nx-update-summary-icon'><i class='bi bi-cpu'></i></span>
            <div>
                <div class='nx-update-summary-label'>Installierte Version</div>
                <div class='nx-update-summary-value'>nexpell Core " . htmlspecialchars(CURRENT_VERSION, ENT_QUOTES, 'UTF-8') . "</div>
                <div class='nx-update-summary-note'>Installiert am " . htmlspecialchars($last_update_date, ENT_QUOTES, 'UTF-8') . "</div>
            </div>
        </div>

        <div class='nx-update-summary-card'>
            <span class='nx-update-summary-icon'><i class='bi bi-hdd-network'></i></span>
            <div>
                <div class='nx-update-summary-label'>Update-Quelle</div>
                <div class='nx-update-summary-value'><code>" . htmlspecialchars($update_source_label, ENT_QUOTES, 'UTF-8') . "</code></div>
                <div class='nx-update-summary-note'>Status: {$server_status}</div>
            </div>
        </div>

        <div class='nx-update-summary-card'>
            <span class='nx-update-summary-icon'><i class='bi bi-check2-circle'></i></span>
            <div>
                <div class='nx-update-summary-label'>Update-Lage</div>
                <div class='nx-update-summary-value'>Keine Updates verfuegbar</div>
                <div class='nx-update-summary-note'>Dein System ist auf dem neuesten Stand.</div>
            </div>
        </div>
    </div>
</div>
";
    }

    /* ============================================================
       🧩 FALL 2: UPDATES VERFÜGBAR
       ============================================================ */
    else {

        $versions = array_map('strval', array_column($updates, 'version'));
        $versionsHtml = '';
        $log = '';

        foreach ($updates as $update) {
            $ver  = htmlspecialchars($update['version']);
            $desc = nl2br(htmlspecialchars($update['changelog'] ?? 'Keine Beschreibung.'));
            $log .= "🟢 <b>{$ver}</b>: {$desc}<br>";
        }

        $log = '';
        $versionsHtml = '';
        foreach ($updates as $update) {
            $ver = htmlspecialchars((string)($update['version'] ?? ''), ENT_QUOTES, 'UTF-8');
            $desc = nl2br(htmlspecialchars((string)($update['changelog'] ?? 'Keine Beschreibung.'), ENT_QUOTES, 'UTF-8'));
            $versionsHtml .= "<span class='nx-update-pill'>{$ver}</span>";
            $log .= "
            <div class='nx-update-changelog-item'>
                <div class='nx-update-changelog-version'>Version {$ver}</div>
                <div class='nx-update-changelog-text'>{$desc}</div>
            </div>";
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
    $statusBadge = "<span class='nx-update-badge nx-update-badge--danger'>DEV</span>";
} elseif ($channel === 'beta') {
    $statusBadge = "<span class='nx-update-badge nx-update-badge--warning'>BETA</span>";
} elseif ($isUpToDate) {
    $statusBadge = "<span class='nx-update-badge nx-update-badge--success'>Aktuell</span>";
} else {
    $statusBadge = "<span class='nx-update-badge nx-update-badge--warning'>Update verfuegbar</span>";
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
        Update-Quelle
    </div>

    <div>
        <code>" . htmlspecialchars($update_source_label, ENT_QUOTES, 'UTF-8') . "</code>
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
<!-- 🔹 Update starten -->
<form method='post' action='admincenter.php?site=update_core&action=progress'>
    <button class='btn btn-success btn-lg shadow-sm mt-3'>
        <i class='bi bi-arrow-clockwise me-1'></i> Update jetzt starten
    </button>
</form>
";

        $data_array['content'] = "
<div class='nx-update-overview'>
    <div class='nx-update-hero nx-update-hero--" . htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') . "'>
        <div class='nx-update-hero__main'>
            <div class='nx-update-hero__eyebrow'>
                <i class='bi bi-rocket-takeoff me-1'></i>
                Core Update
            </div>
            <h3>{$update_count_text}</h3>
            <p>{$statusText}</p>
        </div>
        <div class='nx-update-hero__badge'>
            {$statusBadge}
        </div>
    </div>

    <div class='nx-update-summary-grid'>
        <div class='nx-update-summary-card'>
            <span class='nx-update-summary-icon'><i class='bi bi-cpu'></i></span>
            <div>
                <div class='nx-update-summary-label'>Installierte Version</div>
                <div class='nx-update-summary-value'>nexpell Core " . htmlspecialchars(CURRENT_VERSION, ENT_QUOTES, 'UTF-8') . "</div>
                <div class='nx-update-summary-note'>Installiert am " . htmlspecialchars($last_update_date, ENT_QUOTES, 'UTF-8') . "</div>
            </div>
        </div>

        <div class='nx-update-summary-card'>
            <span class='nx-update-summary-icon'><i class='bi bi-hdd-network'></i></span>
            <div>
                <div class='nx-update-summary-label'>Update-Quelle</div>
                <div class='nx-update-summary-value'><code>" . htmlspecialchars($update_source_label, ENT_QUOTES, 'UTF-8') . "</code></div>
                <div class='nx-update-summary-note'>Status: {$server_status}</div>
            </div>
        </div>

        <div class='nx-update-summary-card'>
            <span class='nx-update-summary-icon'><i class='bi bi-box-arrow-down'></i></span>
            <div>
                <div class='nx-update-summary-label'>Verfuegbare Versionen</div>
                <div class='nx-update-version-list'>{$versionsHtml}</div>
                <div class='nx-update-summary-note'>Core-, Sicherheits- und Funktionsupdates</div>
            </div>
        </div>
    </div>

    <div class='nx-update-changelog'>
        <div class='nx-update-section-title'>
            <span><i class='bi bi-journal-text'></i></span>
            <div>
                <div class='nx-update-summary-label'>Aenderungsprotokoll</div>
                <strong>Was ist neu?</strong>
            </div>
        </div>
        <div class='nx-update-changelog-list'>
            {$log}
        </div>
    </div>

    <form method='post' action='admincenter.php?site=update_core&action=progress' class='nx-update-actions'>
        <button class='btn btn-success btn-lg shadow-sm'>
            <i class='bi bi-arrow-clockwise me-1'></i> Update jetzt starten
        </button>
    </form>
</div>
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
        $zip_url  = nx_append_download_site((string)$update['zip_url']);
        $zip_file = "$tmp_dir/update_{$version}.zip";
        $sql_file = "$tmp_dir/migrations/{$version}.php";

        // Prevent stale migration files from previous failed runs.
        if (file_exists($sql_file)) {
            @unlink($sql_file);
            $migrator->log("🧹 Alte tmp-Migration {$version}.php entfernt");
        }

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
                    // Fallback: use local migration file if present after file update step.
                    $localMigration = __DIR__ . "/update_core/migrations/{$version}.php";
                    if (file_exists($localMigration)) {
                        if (!is_dir(dirname($sql_file))) {
                            mkdir(dirname($sql_file), 0755, true);
                        }
                        if (@copy($localMigration, $sql_file)) {
                            $migrator->log("Migration {$version}.php loaded from local path (fallback).");
                            $migrationFound = true;
                        }
                    }
                }

                if (!$migrationFound) {
                    if (file_exists($sql_file)) {
                        unlink($sql_file); // ensure no stale file can be executed
                    }
                    $migrator->log("Version {$version} has no database migration file in update package.");
                }

                $zip->close();
            }


            $src = "$tmp_dir/admin/update_core/migrations/$version.php";
            if (file_exists($src)) {
                rename($src, $sql_file);
                $migrator->log("Migration {$version}.php extracted from ZIP.");
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
$migrationExecuted  = [];

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
    $steps_log[] = "
    <div class='alert alert-danger mb-2'>
        <i class='bi bi-x-circle-fill me-2'></i>
        Update wurde abgebrochen. Bitte pruefe die Meldungen oben und starte den Schritt danach erneut.
    </div>";

    $data_array['content'] = "
    <div class='nx-update-overview nx-update-progress-log'>
        <div class='nx-steps'>
            " . implode("\n", $steps_log) . "
        </div>
        <div class='nx-update-actions'>
            <a href='admincenter.php?site=update_core&action=start' class='btn btn-secondary'>
                <i class='bi bi-arrow-left-circle'></i> Zurueck zur Uebersicht
            </a>
        </div>
    </div>";

    echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
    exit;
}

$steps_log[] = "
<div class='alert alert-info mb-2'>
    <i class='bi bi-database me-2'></i>
    <b>Step 3: Run database migrations</b>
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
        No migrations found to execute.
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
    $migrationExecuted[$version] = false;

    $sql_file = $tmp_dir . "/migrations/{$version}.php";
    $localMigration = __DIR__ . "/update_core/migrations/{$version}.php";
    $zip_file = "{$tmp_dir}/update_{$version}.zip";
    $migrationFile = null;

    if (file_exists($sql_file)) {
        $migrationFile = $sql_file;
    } elseif (file_exists($localMigration)) {
        $migrationFile = $localMigration;
        $steps_log[] = "
        <div class='alert alert-info py-1 my-1 small'>
            <i class='bi bi-info-circle-fill me-2'></i>
            Using local migration file for <b>{$version}</b>.
        </div>";
    } else {
        // Last fallback: locate migration directly inside update ZIP with tolerant path matching.
        if (file_exists($zip_file)) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file) === true) {
                $needleA = "update_core/migrations/{$version}.php";
                $needleB = "/{$version}.php";
                $foundName = null;

                for ($zi = 0; $zi < $zip->numFiles; $zi++) {
                    $name = (string)$zip->getNameIndex($zi);
                    $norm = str_replace('\\', '/', $name);

                    if (
                        str_ends_with($norm, $needleA) ||
                        str_ends_with($norm, $needleB)
                    ) {
                        $foundName = $name;
                        break;
                    }
                }

                if ($foundName !== null) {
                    if (!is_dir(dirname($sql_file))) {
                        mkdir(dirname($sql_file), 0755, true);
                    }

                    $payload = $zip->getFromName($foundName);
                    if ($payload !== false && file_put_contents($sql_file, $payload) !== false) {
                        $migrationFile = $sql_file;
                        $steps_log[] = "
                        <div class='alert alert-info py-1 my-1 small'>
                            <i class='bi bi-info-circle-fill me-2'></i>
                            Migration <b>{$version}</b> loaded from ZIP entry <code>" . htmlspecialchars($foundName, ENT_QUOTES, 'UTF-8') . "</code>.
                        </div>";
                    }
                }

                $zip->close();
            }
        }

        if ($migrationFile === null) {
            $steps_log[] = "
            <div class='alert alert-warning py-1 my-1 small'>
                <i class='bi bi-exclamation-triangle-fill me-2'></i>
                No DB migration file for <b>{$version}</b>.
            </div>";
            continue;
        }
    }

    $migrator    = new \nexpell\CMSDatabaseMigration($_database);
    $bufferLevel = ob_get_level();

    try {

        ob_start();

        // 🔥 MIGRATION LADEN
        $migration = include $migrationFile;

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
        $migrationExecuted[$version] = true;

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
    $steps_log[] = "
    <div class='alert alert-danger mb-2'>
        <i class='bi bi-x-circle-fill me-2'></i>
        Update wurde abgebrochen. Die Dateien wurden nicht entpackt, damit der Datenbankstand konsistent bleibt.
    </div>";

    $data_array['content'] = "
    <div class='nx-update-overview nx-update-progress-log'>
        <div class='nx-steps'>
            " . implode("\n", $steps_log) . "
        </div>
        <div class='nx-update-actions'>
            <a href='admincenter.php?site=update_core&action=start' class='btn btn-secondary'>
                <i class='bi bi-arrow-left-circle'></i> Zurueck zur Uebersicht
            </a>
        </div>
    </div>";

    echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
    exit;
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
    $localMigration = __DIR__ . "/update_core/migrations/{$version}.php";

    if (!file_exists($zip_file)) {
        continue;
    }

    if (file_exists($localMigration) && empty($migrationExecuted[$version])) {
        $steps_log[] = "
        <div class='alert alert-danger py-1 my-1 small'>
            <i class='bi bi-x-circle-fill me-2'></i>
            Version <b>{$version}</b> hat eine Migration, diese wurde aber nicht ausgeführt.
            Update wird gestoppt, um inkonsistente Datenbankstände zu vermeiden.
        </div>";
        $all_updates_succeeded = false;
        break;
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

        $relativeFile = nx_update_zip_relative_file((string)$file, $zip, $update);
        if ($relativeFile === '') {
            continue;
        }

        $target = $extract_path . '/' . $relativeFile;

        if (file_exists($target)) {
            $files_overwritten[] = $relativeFile;
        } else {
            $files_created[] = $relativeFile;
        }
    }

    /* ===============================
       📂 Dateien entpacken
    ================================ */
    if (!nx_update_extract_package($zip, $extract_path, $update, $tmp_dir)) {
        $zip->close();
        $all_updates_succeeded = false;
        break;
    }
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

    /* ===============================
       📁 delete_dirs
    ================================ */
    if (!empty($update['delete_dirs']) && is_array($update['delete_dirs'])) {

        foreach ($update['delete_dirs'] as $rel) {

            $full = $extract_path . '/' . ltrim((string)$rel, '/');
            if (is_dir($full)) {
                recursiveRemoveDirectory($full);

                if (!file_exists($full)) {
                    $files_deleted[] = rtrim((string)$rel, '/');
                }
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

        $lockDir = __DIR__;
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
        <div class='nx-update-overview nx-update-progress-log'>
            <div class='nx-update-hero nx-update-hero--progress'>
                <div class='nx-update-hero__main'>
                    <div class='nx-update-hero__eyebrow'>
                        <i class='bi bi-activity me-1'></i>
                        Update-Protokoll
                    </div>
                    <h3>Update wird verarbeitet</h3>
                    <p>Download, Migration, Dateiabgleich und System-Synchronisation werden Schritt fuer Schritt protokolliert.</p>
                </div>
                <div class='nx-update-hero__badge'>
                    <span class='nx-update-badge nx-update-badge--warning'>Live</span>
                </div>
            </div>

            <div class='nx-steps'>
                " . implode("\n", $steps_log) . "
            </div>

            <form method='get' action='admincenter.php' class='nx-update-actions'>
                <input type='hidden' name='site' value='update_core'>
                <input type='hidden' name='action' value='finish'>
                <button class='btn btn-success btn-lg shadow-sm'>
                    <i class='bi bi-check2'></i> Abschluss anzeigen
                </button>
            </form>
        </div>";
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
    
    $data_array['content'] = "
    <div class='nx-update-overview'>
        <div class='nx-update-hero nx-update-hero--stable'>
            <div class='nx-update-hero__main'>
                <div class='nx-update-hero__eyebrow'>
                    <i class='bi bi-check2-circle me-1'></i>
                    Update abgeschlossen
                </div>
                <h3>System erfolgreich aktualisiert</h3>
                <p>nexpell Core laeuft jetzt auf Version <strong>" . htmlspecialchars($core_version, ENT_QUOTES, 'UTF-8') . "</strong>.</p>
            </div>
            <div class='nx-update-hero__badge'>
                <span class='nx-update-badge nx-update-badge--success'>Fertig</span>
            </div>
        </div>

        <div class='nx-update-summary-grid'>
            <div class='nx-update-summary-card'>
                <span class='nx-update-summary-icon'><i class='bi bi-cpu'></i></span>
                <div>
                    <div class='nx-update-summary-label'>Aktuelle Version</div>
                    <div class='nx-update-summary-value'>" . htmlspecialchars($core_version, ENT_QUOTES, 'UTF-8') . "</div>
                    <div class='nx-update-summary-note'>Core wurde erfolgreich aktualisiert.</div>
                </div>
            </div>

            <div class='nx-update-summary-card'>
                <span class='nx-update-summary-icon'><i class='bi bi-clock-history'></i></span>
                <div>
                    <div class='nx-update-summary-label'>Aktualisiert am</div>
                    <div class='nx-update-summary-value'>" . htmlspecialchars($last_update_date, ENT_QUOTES, 'UTF-8') . "</div>
                    <div class='nx-update-summary-note'>Zeitpunkt des letzten Core-Updates.</div>
                </div>
            </div>
        </div>

        <div class='nx-update-actions'>
            <a href='admincenter.php?site=update_core&action=start' class='btn btn-primary btn-lg shadow-sm'>
                <i class='bi bi-arrow-left-circle'></i> Zurueck zur Uebersicht
            </a>
        </div>
    </div>";

    echo $tpl->loadTemplate('update_core', 'wizard', $data_array, 'admin');
    
}




