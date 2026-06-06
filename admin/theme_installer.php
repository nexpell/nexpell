<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\themeUninstaller;
use nexpell\Plugininstaller;

// Admin-Rechte prüfen
AccessControl::checkAdminAccess('ac_plugin_installer');

$action = $_GET['action'] ?? '';

/**
 * Resolve localized text without depending on the legacy multiLanguage class.
 * Supports:
 * 1) Legacy markers: [[lang:de]]...[[lang:en]]...
 * 2) JSON object string or array: {"de":"...", "en":"..."}
 */
function resolveThemeLocalizedText($value, string $lang): string
{
    $lang = strtolower(trim($lang));
    if ($lang === '') {
        $lang = 'de';
    }

    if (is_array($value)) {
        foreach ([$lang, 'en', 'gb', 'de', 'it'] as $k) {
            if (isset($value[$k]) && trim((string)$value[$k]) !== '') {
                return (string)$value[$k];
            }
        }
        foreach ($value as $v) {
            if (trim((string)$v) !== '') {
                return (string)$v;
            }
        }
        return '';
    }

    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $jsonDecoded = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
        return resolveThemeLocalizedText($jsonDecoded, $lang);
    }

    if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/si', $text, $m)) {
        return trim((string)$m[1]);
    }
    foreach (['en', 'gb', 'de', 'it'] as $fb) {
        if (preg_match('/\[\[lang:' . preg_quote($fb, '/') . '\]\](.*?)(?=\[\[lang:|$)/si', $text, $m)) {
            return trim((string)$m[1]);
        }
    }

    return $text;
}

switch ($action) {
    case 'upload':
        define('THEME_INSTALLER_CONTEXT', true);
        include __DIR__ . '/theme_installer_upload.php';
        break;

    default:
// Konfiguration
$theme_dir = '../includes/themes/default/css/dist/';
$theme_path = 'https://www.update.nexpell.de/themes';
$theme_json_url = $theme_path . '/theme.json';

echo '<style>
        .theme-card .card-title { line-height: 1.2; padding: 0px; }
    </style>';
// Theme-Aktion: Installieren, Updaten, Deinstallieren
if (isset($_GET['install']) || isset($_GET['update']) || isset($_GET['uninstall'])) {

    $theme_action = isset($_GET['install']) ? 'install' : (isset($_GET['update']) ? 'update' : 'uninstall');
    $theme_folder = basename($_GET[$theme_action]);

    // Ordner-Validierung (für alle Aktionen)
    if (empty($theme_folder) || !preg_match('/^[a-z0-9_\-]+$/i', $theme_folder)) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_invalid_theme_folder', false);
    }

    $theme_info_array = @json_decode(@file_get_contents($theme_json_url), true);
    $theme_info = null;

    if (is_array($theme_info_array)) {
        foreach ($theme_info_array as $theme) {
            if (!empty($theme['modulname']) && $theme['modulname'] === $theme_folder) {
                $theme_info = $theme;
                break;
            }
        }
    }

    if ($theme_action === 'uninstall') {

        $CAPCLASS = new \nexpell\Captcha;
        $captchaHash = $_POST['captcha_hash'] ?? ($_GET['captcha_hash'] ?? '');

        if (!$CAPCLASS->checkCaptcha(0, $captchaHash)) {
            nx_redirect('admincenter.php?site=theme_installer', 'danger', 'transaction_invalid', false);
        }

        $uninstaller = new ThemeUninstaller();
        $uninstaller->uninstall($theme_folder);

        nx_audit_action('theme_installer', 'audit_action_theme_uninstalled', $theme_folder, null, 'admincenter.php?site=theme_installer', ['theme' => $theme_folder]);

        $themesRoot = realpath(__DIR__ . '/../includes/themes');
        $expectedThemePath = $themesRoot ? ($themesRoot . '/' . $theme_folder) : null;

        foreach ($uninstaller->getLog() as $entry) {
            $msg = (string)($entry['message'] ?? '');

            if ($expectedThemePath
                && stripos($msg, 'Ordner') === 0
                && stripos($msg, $theme_folder) !== false
                && stripos($msg, 'nicht gefunden') !== false
                && !is_dir($expectedThemePath)
            ) {
                continue;
            }

            $type = in_array(($entry['type'] ?? ''), ['success', 'danger', 'warning', 'info'], true) ? $entry['type'] : 'info';
            nx_alert($type, $msg, true, true, true);
        }

        nx_redirect('admincenter.php?site=theme_installer');
    }

    if (!$theme_info) {
        $msg = $languageService->get('alert_theme_not_found') . ' ' . htmlspecialchars($theme_folder, ENT_QUOTES, 'UTF-8');
        nx_redirect('admincenter.php?site=theme_installer', 'danger', $msg, true, true);
    }

    $local_theme_folder = $theme_dir . $theme_folder;

    if (!download_theme_files($theme_folder, $local_theme_folder, $theme_path)) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_download_failed', false);
    }

    $script_file = $theme_action === 'install' ? 'install.php' : 'update.php';
    if (file_exists($local_theme_folder . '/' . $script_file)) {
        include $local_theme_folder . '/' . $script_file;
    }

    $name = htmlspecialchars($theme_info['name']);
    $modulname = htmlspecialchars($theme_info['modulname']);
    $description = htmlspecialchars($theme_info['description']);
    $version = htmlspecialchars($theme_info['version']);
    $author = htmlspecialchars($theme_info['author']);
    $url = htmlspecialchars($theme_info['url']);
    $folder = htmlspecialchars($theme_folder);

    if ($theme_action === 'install') {
        safe_query("INSERT INTO settings_themes_installed (name, modulname, description, version, author, url, folder, installed_date)
                    VALUES ('$name','$modulname','$description','$version','$author','$url','$folder',NOW())");
        nx_audit_action('theme_installer', 'audit_action_theme_installed', $folder, null, 'admincenter.php?site=theme_installer', ['theme' => $folder, 'modulname' => $modulname, 'version' => $version]);
        nx_redirect('admincenter.php?site=theme_installer','success',sprintf($languageService->get('alert_theme_installed'), $folder),false,true);

    } else {
        safe_query("UPDATE settings_themes_installed SET version = '$version', installed_date = NOW() WHERE modulname = '$modulname'");
        nx_audit_action('theme_installer', 'audit_action_theme_updated', $folder, null, 'admincenter.php?site=theme_installer', ['theme' => $folder, 'modulname' => $modulname, 'version' => $version]);
        nx_redirect('admincenter.php?site=theme_installer','success',sprintf($languageService->get('alert_theme_updated'), $folder),false,true);
    }
}

// Lokale Themes scannen
$local_themes = [];
foreach (scandir($theme_dir) as $folder) {
    if ($folder === '.' || $folder === '..') continue;
    $path = $theme_dir . $folder;
    if (is_dir($path) && file_exists("$path/theme.json")) {
        $json = json_decode(file_get_contents("$path/theme.json"), true);
        if ($json) {
            $json['dir'] = $folder;
            $local_themes[$json['name']] = $json;
        }
    }
}

// Externe Themes abrufen
$external_themes = [];
$remote_data = @file_get_contents($theme_json_url);
if ($remote_data) {
    $decoded = json_decode($remote_data, true);
    if (is_array($decoded)) {
        foreach ($decoded as $theme) {
            $external_themes[$theme['name']] = $theme;
        }
    }
}

// Installierte Themes
$installed_themes = [];
$res = safe_query("SELECT * FROM settings_themes_installed");
while ($row = mysqli_fetch_assoc($res)) {
    $installed_themes[$row['name']] = $row;
}

// Zusammenführen
$all_theme_names = array_unique(array_merge(array_keys($local_themes), array_keys($external_themes), array_keys($installed_themes)));
$themes_for_template = [];

foreach ($all_theme_names as $name) {
    $local = $local_themes[$name] ?? null;
    $external = $external_themes[$name] ?? null;
    $installed_entry = $installed_themes[$name] ?? null;
    $theme = $local ?? $external;
    if (!$theme) continue;

    $theme_folder = $theme['dir'] ?? $theme['name'];
    $installed = $installed_entry !== null;
    $installed_version = $installed_entry['version'] ?? '—';
    $update = $installed && isset($theme['version']) && version_compare($installed_version, $theme['version'], '<');

    $themes_for_template[] = [
        'name' => $name,
        'modulname' => $theme['modulname'] ?? '',
        'description' => $theme['description'] ?? '',
        'version' => $theme['version'] ?? '',
        'author' => $theme['author'] ?? '',
        'url' => $theme['url'] ?? '',
        'download' => $theme['download'] ?? '',
        'folder' => $theme_folder,
        'installed_version' => $installed_version,
        'installed' => $installed,
        'update' => $update
    ];
}

// HTML-Ausgabe
echo '<div class="mt-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <a href="admincenter.php?site=theme_installer&action=upload" class="btn btn-secondary">' . $languageService->get('upload_new_theme') . '</a>
            </div>

            <div class="input-group input-group-sm me-1" style="min-width: 260px; max-width: 360px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="themeSearch" type="search" class="form-control" placeholder="' . $languageService->get('search') . '">
            </div>
        </div>
    </div>
<div class="card-body p-0">';

// Flash-Alerts ausgeben (z. B. nach Install/Update/Uninstall), dann leeren
echo '<div class="row g-3 my-0">';

$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

foreach ($themes_for_template as $theme) {
    $description = resolveThemeLocalizedText($theme['description'] ?? '', (string)($_SESSION['language'] ?? 'de'));
    $theme_folder = $theme['folder'] ?? $theme['name'];
    $searchText = mb_strtolower(trim(implode(' ', [
        (string)($theme['name'] ?? ''),
        (string)($theme['modulname'] ?? ''),
        (string)($theme['author'] ?? ''),
        trim(strip_tags($description)),
        (string)($theme['version'] ?? ''),
        (string)($theme['installed_version'] ?? ''),
    ])));

    $uninstallUrl = 'admincenter.php?site=theme_installer&uninstall=' . rawurlencode($theme_folder) . '&captcha_hash=' . rawurlencode($hash);
    $uninstallUrlAttr = htmlspecialchars($uninstallUrl, ENT_QUOTES, 'UTF-8');
    
    $img = !empty($theme['name'])
        ? 'https://update.nexpell.de/themes/screen/' . urlencode($theme['name']) . '.png'
        : 'assets/default_theme_preview.png';

    echo '<div class="col-xl-3 col-lg-4 col-md-6 theme-card-item mb-4 mt-4" data-search="' . htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') . '">
        <div class="card h-100 shadow-sm theme-card">

            <img class="card-img-top"
                 src="' . $img . '"
                 alt="' . htmlspecialchars($theme['name']) . '"
                 onerror="this.onerror=null;this.src=\'assets/default_theme_preview.png\';">

            <div class="card-body d-flex flex-column">

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h5 class="mb-0">' . htmlspecialchars($theme['name']) . '</h5>
                    <div class="small">' . ($theme['installed'] ? '<span class="badge bg-secondary">' . $languageService->get('installed') . '</span>' : '') . '</div>
                </div>

                <div class="small text-muted mb-2">
                    ' . $languageService->get('version') . ' ' . htmlspecialchars($theme['version']) . ($theme['installed'] ? ' <span class="text-muted">(' . htmlspecialchars($theme['installed_version']) . ')</span>' : '') . '
                </div>

                <div class="text-muted mb-3 mt-2" style="line-height:1.4">
                    ' . $description . '
                </div>

                <div class="mt-auto">';

if ($theme['installed']) {

    if ($theme['update']) {

        echo '<div class="d-grid gap-2">
                <a href="admincenter.php?site=theme_installer&update=' . urlencode($theme['modulname']) . '" class="btn btn-warning">
                    <i class="bi bi-arrow-repeat"></i> ' . $languageService->get('update') . '
                </a>

                <button type="button"
                        class="btn btn-danger d-inline-flex align-items-center justify-content-center gap-1"
                        data-bs-toggle="modal"
                        data-bs-target="#confirmDeleteModal"
                        data-delete-url="' . $uninstallUrlAttr . '">
                    <i class="bi bi-trash3"></i> ' . htmlspecialchars($languageService->get('uninstall')) . '
                </button>
              </div>';

    } else {

        echo '<div class="d-grid gap-2">
                <button class="btn btn-outline-secondary" disabled>
                    <i class="bi bi-check-lg"></i> ' . $languageService->get('installed') . ' (' . htmlspecialchars($theme['installed_version']) . ')
                </button>

                <button type="button"
                        class="btn btn-danger d-inline-flex align-items-center justify-content-center gap-1"
                        data-bs-toggle="modal"
                        data-bs-target="#confirmDeleteModal"
                        data-delete-url="' . $uninstallUrlAttr . '">
                    <i class="bi bi-trash3"></i> ' . htmlspecialchars($languageService->get('uninstall')) . '
                </button>
              </div>';
    }

} else {

    if (!empty($theme['download']) && $theme['download'] !== 'DISABLED') {

        echo '<div class="d-grid gap-2">
                <a href="admincenter.php?site=theme_installer&install=' . urlencode($theme['modulname']) . '" class="btn btn-success">
                    <i class="bi bi-download"></i> ' . $languageService->get('install') . '
                </a>
              </div>';

    } else {

        echo '<span class="text-muted small">' . $languageService->get('no_download_available') . '</span>';
    }
}

echo '</div></div></div></div>';
}

echo '<div id="themeSearchEmpty" class="col-12 d-none">
        <div class="alert alert-info mb-0">Keine Themes fuer die aktuelle Suche gefunden.</div>
      </div>';


echo '<script>
document.addEventListener("DOMContentLoaded", function () {
    var input = document.getElementById("themeSearch");
    if (!input) return;
    var cards = Array.prototype.slice.call(document.querySelectorAll(".theme-card-item"));
    var emptyState = document.getElementById("themeSearchEmpty");

    function applyFilter() {
        var q = (input.value || "").toLowerCase().trim();
        var visible = 0;

        cards.forEach(function (card) {

            // "Gesamten Content" der jeweiligen Theme-Kachel berücksichtigen
            // (Titel, Beschreibung, Version, Buttons etc.).
            var txt = (card.getAttribute("data-search") || "").toLowerCase();
            var show = (!q || txt.indexOf(q) !== -1);

            card.classList.toggle("d-none", !show);
            if (show) {
                visible++;
            }
        });

        if (emptyState) {
            emptyState.classList.toggle("d-none", visible !== 0);
        }
    }

    input.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            input.value = "";
            applyFilter();
        }
    });

    input.addEventListener("input", applyFilter);
    applyFilter();
});
</script>'; 

echo '</div></div>';

break;
}
/**
 * Lädt und entpackt das Theme-ZIP
 */
function download_theme_files(string $theme_folder, string $local_theme_folder, string $theme_path): bool
{
    // Remote-URL mit Statistik-Tracking
    $remote_url = "https://update.nexpell.de/system/download.php"
                . "?type=theme"
                . "&file=" . rawurlencode($theme_folder . ".zip")
                . "&site=" . rawurlencode($_SERVER['SERVER_NAME']);

    $local_zip = tempnam(sys_get_temp_dir(), 'theme_') . '.zip';

    // ZIP laden
    $zip_content = @file_get_contents($remote_url);
    if ($zip_content === false) {
        nx_alert('danger', 'alert_download_failed', false);
        return false;
    }

    if (@file_put_contents($local_zip, $zip_content) === false) {
        nx_alert('danger', 'alert_download_failed', false);
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($local_zip) !== true) {
        @unlink($local_zip);
        nx_alert('danger', 'alert_download_failed', false);
        return false;
    }

    // Entpack-Pfad vorbereiten
    $temp_extract_dir = sys_get_temp_dir() . '/theme_extract_' . uniqid('', true);
    @mkdir($temp_extract_dir, 0755, true);

    if (!$zip->extractTo($temp_extract_dir)) {
        $zip->close();
        @unlink($local_zip);
        nx_alert('danger', 'alert_download_failed', false);
        return false;
    }

    $zip->close();
    @unlink($local_zip);

    // Verschieben / ins Ziel kopieren
    $entries = array_diff(@scandir($temp_extract_dir) ?: [], ['.', '..']);
    $source_dir = (count($entries) === 1 && is_dir($temp_extract_dir . '/' . reset($entries)))
        ? $temp_extract_dir . '/' . reset($entries)
        : $temp_extract_dir;

    if (is_dir($local_theme_folder)) {
        deleteFolder($local_theme_folder);
    }

    @mkdir($local_theme_folder, 0755, true);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $targetPath = $local_theme_folder . '/' . $iterator->getSubPathName();

        if ($item->isDir()) {
            @mkdir($targetPath, 0755, true);
        } else {
            @copy($item, $targetPath);
        }
    }

    deleteFolder($temp_extract_dir);
    return true;
}

// Ordner rekursiv löschen
function deleteFolder($folderPath) {
    if (!is_dir($folderPath)) return false;

    $files = array_diff(scandir($folderPath), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        is_dir($filePath) ? deleteFolder($filePath) : unlink($filePath);
    }

    return rmdir($folderPath);
}
