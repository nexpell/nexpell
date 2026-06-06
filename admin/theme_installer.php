<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService, $_database;
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('theme_installer', true);


use nexpell\AccessControl;
use nexpell\themeUninstaller;
use nexpell\Plugininstaller;

// Admin-Rechte prüfen
AccessControl::checkAdminAccess('ac_plugin_installer');

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'upload':
        include('theme_installer_upload.php');
        break;

    default:
echo '<div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-journal-text"></i> ' . $languageService->get('theme_installer') . '</div>
            <div>
                <a href="admincenter.php?site=theme_installer&action=upload" class="btn btn-success"><i class="bi bi-plus"></i> Neues Theme hochladen / bearbeiten</a>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item"><a href="admincenter.php?site=theme_installer">Themes verwalten</a></li>
                <li class="breadcrumb-item active" aria-current="page">Install / Deinstall</li>
            </ol>
        </nav> 
        <div class="card-body p-0">
';
// Konfiguration
$theme_dir = '../includes/themes/default/css/dist/';
$theme_path = 'https://www.update.nexpell.de/themes';
$theme_json_url = $theme_path . '/theme.json';

// Theme-Aktion: Installieren, Updaten, Deinstallieren
if (isset($_GET['install']) || isset($_GET['update']) || isset($_GET['uninstall'])) {
    $theme_action = isset($_GET['install']) ? 'install' : (isset($_GET['update']) ? 'update' : 'uninstall');
    $theme_folder = basename($_GET[$theme_action]);

    $theme_info_array = @json_decode(@file_get_contents($theme_json_url), true);
    $theme_info = null;

    if (is_array($theme_info_array)) {
        foreach ($theme_info_array as $theme) {
            if (isset($theme['modulname']) && $theme['modulname'] === $theme_folder) {
                $theme_info = $theme;
                break;
            }
        }
    }

    if ($theme_action === 'uninstall') {
        if (empty($theme_folder) || !preg_match('/^[a-z0-9_\-]+$/i', $theme_folder)) {
            echo '<div class="alert alert-danger">' . $languageService->get('invalid_theme_folder') . '</div>';
            exit;
        }

        $uninstaller = new ThemeUninstaller();
        $uninstaller->uninstall($theme_folder);
        foreach ($uninstaller->getLog() as $entry) {
            $type = in_array($entry['type'], ['success', 'danger', 'warning', 'info']) ? $entry['type'] : 'info';
            echo '<div class="alert alert-' . $type . '">' . htmlspecialchars($entry['message']) . '</div>';
        }
        echo '<script>setTimeout(function() { window.location.href = "admincenter.php?site=theme_installer"; }, 3000);</script>';
        exit;
    }

    if (!$theme_info) {
        echo '<div class="alert alert-danger">' . $languageService->get('theme_not_found') . ' ' . htmlspecialchars($theme_folder) . '</div>';
        exit;
    }

    $local_theme_folder = $theme_dir . $theme_folder;

    if (!download_theme_files($theme_folder, $local_theme_folder, $theme_path)) {
        echo '<div class="alert alert-danger">' . $languageService->get('download_failed') . '</div>';
        exit;
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
        echo '<div class="alert alert-success">Theme <strong>' . $name . '</strong> ' . $languageService->get('was_installed') . '</div>';
    } else {
        safe_query("UPDATE settings_themes_installed SET version = '$version', installed_date = NOW() WHERE modulname = '$modulname'");
        echo '<div class="alert alert-success">Theme <strong>' . $name . '</strong> ' . $languageService->get('was_updated') . '</div>';
    }

    echo '<script>setTimeout(function() { window.location.href = "admincenter.php?site=theme_installer"; }, 3000);</script>';
    exit;
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
$cardsPerPage = 12; // Anzahl Karten pro Seite
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalThemes = count($themes_for_template);
$totalPages = ceil($totalThemes / $cardsPerPage);
$start = ($page - 1) * $cardsPerPage;
$themesForCurrentPage = array_slice($themes_for_template, $start, $cardsPerPage);

echo '<div class="container py-4">
            <h3>' . $languageService->get('theme_installer_headline') . '</h3>
            <div class="row gx-3 gy-2 my-0">
';

foreach ($themesForCurrentPage as $theme) {
    $translate = new multiLanguage($_SESSION['language']);
    $description = $translate->getTextByLanguage($theme['description']);
    
    $img = !empty($theme['name'])
        ? 'https://update.nexpell.de/themes/screen/' . urlencode($theme['name']) . '.png'
        : 'assets/default_theme_preview.png';

    echo '<div class="col-md-3 d-flex align-items-stretch mb-2">
        <div class="card h-auto shadow-sm w-100">
            <img src="' . $img . '" class="card-img-top" alt="' . htmlspecialchars($theme['name']) . ' Preview">
            <div class="card-body d-flex flex-column border-top py-2 px-2">
                <h5 class="card-title mb-2">' . htmlspecialchars($theme['name']) . '</h5>
                <p class="card-text flex-grow-1 mb-2">' . $description . '</p>
                <p class="text-muted small mb-2">
                    ' . $languageService->get('version') . ': ' . htmlspecialchars($theme['version']);
    if ($theme['installed']) {
        echo ' / <small>(' . htmlspecialchars($theme['installed_version']) . ')</small>';
    }
    echo '</p>
                <div class="mt-auto d-flex justify-content-between flex-wrap">';
    
    if ($theme['installed']) {
        echo '<button class="btn btn-success" disabled>' . $languageService->get('installed') . '</button>';
        if ($theme['update']) {
            echo ' <a href="admincenter.php?site=theme_installer&update=' . urlencode($theme['modulname']) . '" class="btn btn-warning">' . $languageService->get('update') . '</a>';
        }
        echo '<a href="admincenter.php?site=theme_installer&uninstall=' . urlencode($theme['modulname']) . '" class="btn btn-danger" onclick="return confirm(\'' . addslashes($languageService->get('confirm_uninstall')) . '\');">' . htmlspecialchars($languageService->get('uninstall')) . '</a>';
    } else {
        if (!empty($theme['download']) && $theme['download'] !== 'DISABLED') {
            echo '<a href="admincenter.php?site=theme_installer&install=' . urlencode($theme['modulname']) . '" class="btn btn-primary">' . $languageService->get('install') . '</a>';
        } else {
            echo '<span style="color: gray;">' . $languageService->get('no_download_available') . '</span>';
        }
    }

    echo '</div></div></div></div>';
}

echo '</div>';

if ($totalPages > 1) {
    echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mt-3">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?site=theme_installer&page=' . $i . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}

echo '</div></div></div>';

break;
}
/**
 * Lädt und entpackt das Theme-ZIP
 */
function download_theme_files(string $theme_folder, string $local_theme_folder, string $theme_path): bool
{
    // Neue Remote-URL mit Statistik-Tracking
    $remote_url = "https://update.nexpell.de/system/download.php"
                . "?type=theme"
                . "&file=" . rawurlencode($theme_folder . ".zip")
                . "&site=" . rawurlencode($_SERVER['SERVER_NAME']);

    $local_zip = tempnam(sys_get_temp_dir(), 'theme_') . '.zip';

    // ZIP laden
    $zip_content = @file_get_contents($remote_url);
    if ($zip_content === false) {
        echo '<div class="alert alert-danger">Download fehlgeschlagen: ' . htmlspecialchars($remote_url) . '</div>';
        return false;
    }

    if (file_put_contents($local_zip, $zip_content) === false) {
        echo '<div class="alert alert-danger">ZIP konnte lokal nicht gespeichert werden.</div>';
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($local_zip) !== true) {
        unlink($local_zip);
        echo '<div class="alert alert-danger">ZIP konnte nicht geöffnet werden.</div>';
        return false;
    }

    // Entpack-Pfad vorbereiten
    $temp_extract_dir = sys_get_temp_dir() . '/theme_extract_' . uniqid();
    mkdir($temp_extract_dir, 0755, true);

    if (!$zip->extractTo($temp_extract_dir)) {
        $zip->close();
        unlink($local_zip);
        echo '<div class="alert alert-danger">ZIP konnte nicht entpackt werden.</div>';
        return false;
    }
    $zip->close();
    unlink($local_zip);

    // Verschieben / ins Ziel kopieren
    $entries = array_diff(scandir($temp_extract_dir), ['.', '..']);
    $source_dir = (count($entries) == 1 && is_dir($temp_extract_dir . '/' . reset($entries)))
        ? $temp_extract_dir . '/' . reset($entries)
        : $temp_extract_dir;

    if (is_dir($local_theme_folder)) {
        deleteFolder($local_theme_folder);
    }
    mkdir($local_theme_folder, 0755, true);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $targetPath = $local_theme_folder . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            mkdir($targetPath, 0755, true);
        } else {
            copy($item, $targetPath);
        }
    }

    deleteFolder($temp_extract_dir);
    return true;
}



/**
 * Ordner rekursiv löschen
 */
function deleteFolder($folderPath) {
    if (!is_dir($folderPath)) return false;

    $files = array_diff(scandir($folderPath), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        is_dir($filePath) ? deleteFolder($filePath) : unlink($filePath);
    }

    return rmdir($folderPath);
}