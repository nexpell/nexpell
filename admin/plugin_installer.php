<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\PluginUninstaller;
use nexpell\PluginMigrationHelper;

/* ======================================================
   BOOTSTRAP
====================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$installerDebug = [];





$_SESSION['language'] ??= 'de';

global $_database;


$action = null;

foreach (['install','update','reinstall','uninstall'] as $a) {
    if (isset($_GET[$a])) {
        $action = $a;
        break;
    }
}

/* ======================================================
   FLASH MESSAGES
====================================================== */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

/* ======================================================
   LANGUAGE / ACCESS
====================================================== */
$languageService = new LanguageService($_database);
$lang = $languageService->detectLanguage();
$languageService->readModule('plugin_installer', true);

AccessControl::checkAdminAccess('ac_plugin_installer');

/* ======================================================
   CONFIG
====================================================== */
$coreVersion   = include __DIR__ . '/../system/version.php';
$pluginDir     = '../includes/plugins/';
$pluginJsonUrl = 'https://www.update.nexpell.de/plugins/plugins_v2.json';

/* ======================================================
   ADMIN EMAIL
====================================================== */
$adminEmail = '';
if (!empty($_SESSION['userID'])) {
    $r = safe_query("SELECT email FROM users WHERE userID=".(int)$_SESSION['userID']." LIMIT 1");
    if ($u = mysqli_fetch_assoc($r)) {
        $adminEmail = strtolower(trim($u['email']));
    }
}

/* ======================================================
   HELPER: CORE / VISIBILITY
====================================================== */
function pluginMatchesCore(array $plugin, string $coreVersion): bool
{
    $min = $plugin['core']['min'] ?? null;
    $max = $plugin['core']['max'] ?? null;

    if ($min && version_compare($coreVersion, $min, '<')) return false;
    if ($max && version_compare($coreVersion, $max, '>')) return false;

    return true;
}

function pluginIsInstallable(array $p, string $adminEmail, string $coreVersion, array &$dbg = []): bool
{
    $adminEmail = strtolower(trim($adminEmail));
    $version    = $p['version'] ?? 'unknown';

    /* ===============================
       CORE-KOMPATIBILITÄT
    =============================== */
    if (!empty($p['core']['min']) &&
        version_compare($coreVersion, $p['core']['min'], '<')) {

        $dbg[] = "❌ {$p['modulname']} {$version}: core {$coreVersion} < min {$p['core']['min']}";
        return false;
    }

    if (!empty($p['core']['max']) &&
        version_compare($coreVersion, $p['core']['max'], '>')) {

        $dbg[] = "❌ {$p['modulname']} {$version}: core {$coreVersion} > max {$p['core']['max']}";
        return false;
    }

    /* ===============================
       SICHTBARKEIT / RECHTE
    =============================== */

    $visibleFor = strtoupper((string)($p['visible_for'] ?? 'ALL'));
    $emails     = array_map('strtolower', $p['visible_emails'] ?? []);

    // 🚫 Komplett deaktiviert
    if ($visibleFor === 'DISABLED') {
        $dbg[] = "❌ {$p['modulname']} {$version}: DISABLED";
        return false;
    }

    // 👻 Sichtbar, aber NICHT installierbar (optional, falls genutzt)
    if ($visibleFor === 'HIDDEN') {
        $dbg[] = "⚠️ {$p['modulname']} {$version}: HIDDEN";
        return false;
    }

    // 📧 Einschränkung per E-Mail (hat Priorität!)
    if (!empty($emails)) {
        if ($adminEmail !== '' && in_array($adminEmail, $emails, true)) {
            $dbg[] = "✅ {$p['modulname']} {$version}: email match ({$adminEmail})";
            return true;
        }

        $dbg[] = "❌ {$p['modulname']} {$version}: email no match ({$adminEmail})";
        return false;
    }

    // 🌍 Öffentlich (ALL / leer / nicht gesetzt)
    $dbg[] = "✅ {$p['modulname']} {$version}: public";
    return true;
}




/* ======================================================
   LOAD PLUGIN REGISTRY (JSON v2)
====================================================== */
function loadPluginsRegistry(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Nexpell Plugin Installer'
    ]);

    $json = curl_exec($ch);
    if ($json === false) {
        throw new RuntimeException(curl_error($ch));
    }

    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new RuntimeException('Registry HTTP error');
    }
    $data = json_decode($json, true);
    if (!isset($data['plugins']) || !is_array($data['plugins'])) {
        throw new RuntimeException('Invalid plugins_v2.json');
    }

    return $data['plugins'];
}

try {
    $rawPlugins = loadPluginsRegistry($pluginJsonUrl);
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
    $rawPlugins = [];
}

/* ======================================================
   RESOLVE LATEST VERSION PER PLUGIN
====================================================== */
$grouped = [];
foreach ($rawPlugins as $plugin) {
    $grouped[$plugin['modulname']][] = $plugin;
}

$plugins = [];

foreach ($grouped as $modulname => $versions) {

    usort($versions, fn($a, $b) =>
        version_compare($b['version'], $a['version'])
    );

    $installerDebug[] = "🔹 Modul: {$modulname}";
    foreach ($versions as $p) {
        $installerDebug[] = "   ├─ gefunden: Version {$p['version']}";
    }

    foreach ($versions as $plugin) {
        if (pluginIsInstallable($plugin, $adminEmail, $coreVersion, $installerDebug)) {
            $plugins[] = $plugin;
            $installerDebug[] = "   ⭐ GEWÄHLT: {$plugin['modulname']} {$plugin['version']}";
            break;
        }
    }
}

if (isset($_GET['debug_installer']) && $_GET['debug_installer'] === '1') {
    echo "<pre style='background:#111;color:#0f0;padding:15px;font-size:13px'>";
    echo implode("\n", $installerDebug);
    echo "</pre>";
    exit;
}

/* ======================================================
   INSTALLED PLUGINS
====================================================== */
$installed = [];
$r = safe_query("SELECT * FROM settings_plugins_installed");
while ($row = mysqli_fetch_assoc($r)) {
    $installed[$row['modulname']] = $row;
}

/* ======================================================
   ACTION
====================================================== */
/* ======================================================
   ACTION (FINAL – STABIL)
====================================================== */


if ($action !== null) {

    $modul = basename($_GET[$action]);

    /* ===============================
       UNINSTALL
    =============================== */
    if ($action === 'uninstall') {

        $uninstaller = new PluginUninstaller();
        $uninstaller->uninstall($modul);

        foreach ($uninstaller->getLog() as $entry) {
            flash(
                in_array($entry['type'], ['success','danger','warning','info'], true)
                    ? $entry['type']
                    : 'info',
                $entry['message']
            );
        }
$_SESSION['redirect_after'] = 3;
        header('Location: admincenter.php?site=plugin_installer');
        exit;
    }

    /* ===============================
       INSTALL / UPDATE / REINSTALL
    =============================== */
    $plugin = null;
    foreach ($plugins as $p) {
        if ($p['modulname'] === $modul) {
            $plugin = $p;
            break;
        }
    }

    if (!$plugin || !pluginIsInstallable($plugin, $adminEmail, $coreVersion)) {
        flash('danger', 'Plugin nicht installierbar.');
        $_SESSION['redirect_after'] = 3;
        header('Location: admincenter.php?site=plugin_installer');
        exit;
    }

    $pluginPath = $pluginDir . $modul;

    /* 🔁 REINSTALL: alte Dateien entfernen */
    if ($action === 'reinstall' && is_dir($pluginPath)) {
        deleteFolder($pluginPath);
        flash('info', 'Plugin-Dateien wurden für Reinstall entfernt.');
        $_SESSION['redirect_after'] = 3;
    }

    if (!download_plugin_files($plugin, $pluginPath)) {
        flash('danger', 'Plugin-Download fehlgeschlagen.');
        $_SESSION['redirect_after'] = 3;
        header('Location: admincenter.php?site=plugin_installer');
        exit;
    }

    /* install.php bei install + reinstall */
    $script = ($action === 'update') ? 'update.php' : 'install.php';
    if (file_exists($pluginPath.'/'.$script)) {
        include $pluginPath.'/'.$script;
    }

    /* ===============================
       DB SPEICHERN
    =============================== */
    safe_query("
        INSERT INTO settings_plugins_installed
            (name, modulname, description, version, author, url, folder, installed_date)
        VALUES (
            '".escape($plugin['name'])."',
            '".escape($plugin['modulname'])."',
            '".escape($plugin['description'] ?? '')."',
            '".escape($plugin['version'])."',
            '".escape($plugin['author'] ?? '')."',
            '".escape($plugin['url'] ?? '')."',
            '".escape($modul)."',
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            version = VALUES(version),
            installed_date = NOW()
    ");

    flash(
        'success',
        'Plugin „'.$plugin['name'].'“ wurde erfolgreich '
        .(
            $action === 'install'   ? 'installiert' :
            ($action === 'update'   ? 'aktualisiert' :
                                      'neu installiert')
        ).'.'
    );
    $_SESSION['redirect_after'] = 3;
    header('Location: admincenter.php?site=plugin_installer');
    exit;
}



/* ======================================================
   HTML
====================================================== */
if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $msg): ?>
        <div class="alert alert-<?= htmlspecialchars($msg['type']) ?>">
            <?= htmlspecialchars($msg['message']) ?>
        </div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['redirect_after'])): ?>
<script>
    setTimeout(function () {
        window.location.href = "admincenter.php?site=plugin_installer";
    }, <?= (int)$_SESSION['redirect_after'] * 1000 ?>);
</script>
<?php unset($_SESSION['redirect_after']); ?>
<?php endif;









/* =========================================
   1) GLOBAL SORTIEREN (VOR PAGINATION)
========================================= */
usort($plugins, fn($a, $b) =>
    strcasecmp(
        (string)($a['modulname'] ?? ''),
        (string)($b['modulname'] ?? '')
    )
);



/* ======================================================
   HTML / PAGINATION
====================================================== */
$cardsPerPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

/* 1) GLOBAL SORTIEREN */
usort($plugins, fn($a, $b) =>
    strcasecmp(
        (string)($a['modulname'] ?? ''),
        (string)($b['modulname'] ?? '')
    )
);

/* 2) PAGINATION */
$totalPlugins = count($plugins);
$totalPages   = (int)ceil($totalPlugins / $cardsPerPage);

$offset = ($page - 1) * $cardsPerPage;
$pluginsPage = array_slice($plugins, $offset, $cardsPerPage);




?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-journal-text"></i> Plugin Installer
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=plugin_installer">Plugin verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">Install / Deinstall</li>
        </ol>
    </nav> 
    <div class="card-body p-0">
        <div class="container py-4">
            <div class="row g-4">
                <?php
                $ml = new multiLanguage($lang);

                foreach ($pluginsPage as $p):

                    $inst = $installed[$p['modulname']] ?? null;

                    /* =========================================
                       1) Update Abfrage
                    ========================================= */

                    $installedVersion = $inst['version'] ?? null;
                    $latestVersion    = $p['version'];

                    $isInstalled = (bool)$inst;
                    $hasUpdate   = $isInstalled && version_compare($latestVersion, $installedVersion, '>');


                    // Beschreibung übersetzen
                    $desc = $ml->getTextByLanguage($p['description']);

                    // Flaggen aus lang generieren
                    $flags_html = '';
                    if (!empty($p['lang'])) {
                        foreach (explode(',', $p['lang']) as $lc) {
                            $lc = trim($lc);
                            if ($lc !== '') {
                                $flags_html .=
                                    '<img src="images/flags/'.$lc.'.svg"
                                          alt="'.strtoupper($lc).'"
                                          title="'.strtoupper($lc).'"
                                          class="me-1"
                                          style="height:14px;">';
                            }
                        }
                    }
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card h-100 shadow-sm">

                            <img class="card-img-top"
                                 src="https://www.update.nexpell.de/plugins/images/<?= htmlspecialchars($p['image'] ?? 'default.png') ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 onerror="this.onerror=null;this.src='https://www.update.nexpell.de/plugins/images/default.png';">

                            <div class="card-body d-flex flex-column">

                                <h5 class="mb-1"><?= htmlspecialchars($p['name']) ?></h5>

                                <div class="small text-muted mb-2">
                                    Version <?= htmlspecialchars($p['version']) ?>
                                </div>

                                <div class="small mb-2">
                                    <?= $flags_html ?>
                                </div>

                                <div class="small text-muted mb-3" style="line-height:1.4">
                                    <?= $desc ?>
                                </div>

                                <div class="mt-auto">

                                    <div class="mt-auto">

                                        <?php if ($isInstalled && !$hasUpdate): ?>

                                        <!-- ✅ Installiert & aktuell -->
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-secondary btn-sm" disabled>
                                                Installiert (<?= htmlspecialchars($installedVersion) ?>)
                                            </button>

                                            <a class="btn btn-warning btn-sm"
                                               onclick="return confirm('Plugin wirklich neu installieren? Alle Dateien werden ersetzt.')"
                                               href="?site=plugin_installer&reinstall=<?= urlencode($p['modulname']) ?>">
                                               Reinstall
                                            </a>

                                            <a class="btn btn-danger btn-sm"
                                               onclick="return confirm('Plugin wirklich deinstallieren?')"
                                               href="?site=plugin_installer&uninstall=<?= urlencode($p['modulname']) ?>">
                                                Deinstallieren
                                            </a>
                                        </div>

                                    <?php elseif ($hasUpdate): ?>

                                        <!-- 🔄 Update verfügbar -->
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-info btn-sm" disabled>
                                                Installiert: <?= htmlspecialchars($installedVersion) ?>
                                            </button>

                                            <a class="btn btn-primary btn-sm"
                                               href="?site=plugin_installer&update=<?= urlencode($p['modulname']) ?>">
                                                Update auf <?= htmlspecialchars($latestVersion) ?>
                                            </a>

                                            <a class="btn btn-danger btn-sm"
                                               onclick="return confirm('Plugin wirklich deinstallieren?')"
                                               href="?site=plugin_installer&uninstall=<?= urlencode($p['modulname']) ?>">
                                                Deinstallieren
                                            </a>
                                        </div>

                                    <?php elseif (pluginIsInstallable($p, $adminEmail, $coreVersion)): ?>

                                        <!-- ➕ Noch nicht installiert -->
                                        <a class="btn btn-success btn-sm w-100"
                                           href="?site=plugin_installer&install=<?= urlencode($p['modulname']) ?>">
                                            Installieren
                                        </a>

                                    <?php else: ?>

                                        <!-- ⛔ Nicht verfügbar -->
                                        <button class="btn btn-secondary btn-sm w-100" disabled>
                                            Nicht verfügbar
                                        </button>

                                    <?php endif; ?>

                                    </div>


                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
<?php if ($totalPages > 1): ?>
<nav class="mt-5 mb-6">
    <ul class="pagination justify-content-center">

        <!-- Zurück -->
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?site=plugin_installer&page=<?= $page - 1 ?>">
               &laquo;
            </a>
        </li>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                <a class="page-link"
                   href="?site=plugin_installer&page=<?= $i ?>">
                    <?= $i ?>
                </a>
            </li>
        <?php endfor; ?>

        <!-- Weiter -->
        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?site=plugin_installer&page=<?= $page + 1 ?>">
               &raquo;
            </a>
        </li>

    </ul>
</nav>
<?php endif; ?>

        </div>    
    </div>
</div>

<?php

/* ======================================================
   DOWNLOAD
====================================================== */
function download_plugin_files(array $plugin, string $target): bool
{
    // 🔐 Basisdaten
    $modul = $plugin['modulname'] ?? null;
    if (!$modul) {
        error_log('download_plugin_files: modulname fehlt');
        return false;
    }

    // 📦 ZIP-Dateiname
    // Priorität: JSON -> download
    // Fallback: modulname.zip (alte Plugins)
    $zipFile = $plugin['download'] ?? ($modul . '.zip');

    // 🌐 Download-URL (dein bestehendes API)
    $url = "https://www.update.nexpell.de/system/download.php"
         . "?type=plugin"
         . "&file=" . rawurlencode($zipFile)
         . "&site=" . rawurlencode($_SERVER['SERVER_NAME']);

    // 📁 Temp-Datei
    $tmp = sys_get_temp_dir() . '/' . uniqid($modul . '_', true) . '.zip';

    // ⬇️ Download (file_get_contents bleibt – bewusst!)
    $data = @file_get_contents($url);
    if ($data === false || strlen($data) < 100) {
        error_log("Plugin-Download fehlgeschlagen: {$url}");
        return false;
    }

    file_put_contents($tmp, $data);

    // 🧩 ZIP prüfen
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        error_log("ZIP konnte nicht geöffnet werden: {$tmp}");
        @unlink($tmp);
        return false;
    }

    // 🔁 Zielverzeichnis vorbereiten
    if (is_dir($target)) {
        deleteFolder($target);
    }

    if (!mkdir($target, 0755, true) && !is_dir($target)) {
        error_log("Plugin-Zielverzeichnis nicht erstellbar: {$target}");
        $zip->close();
        @unlink($tmp);
        return false;
    }

    // 📂 Entpacken
    $zip->extractTo($target);
    $zip->close();
    unlink($tmp);

    // ✅ Minimal-Validierung
    if (!file_exists($target . '/install.php') && !file_exists($target . '/update.php')) {
        error_log("Plugin ungültig – install.php/update.php fehlt ({$modul})");
        return false;
    }

    return true;
}


function deleteFolder(string $d): void
{
    foreach (array_diff(scandir($d),['.','..']) as $f) {
        $p="$d/$f";
        is_dir($p)?deleteFolder($p):unlink($p);
    }
    rmdir($d);
}




/*| Wert                    | Bedeutung                | UI                       | Install     |
| ----------------------- | ------------------------ | ------------------------ | ----------- |
| *(nicht gesetzt)*       | öffentlich               | sichtbar                 | erlaubt     |
| `"all"`                 | öffentlich               | sichtbar                 | erlaubt     |
| `["all"]`               | öffentlich               | sichtbar                 | erlaubt     |
| `["mail@x.de"]`         | eingeschränkt            | sichtbar (nur für diese) | erlaubt     |
| `"DISABLED"`            | **komplett deaktiviert** | ❌ unsichtbar           | ❌ blockiert |
| `"HIDDEN"` *(optional)* | sichtbar, aber gesperrt  | sichtbar (grau)          | ❌ blockiert |

| visible_for | Sichtbar | Install | Update | Reinstall | Uninstall |
| ----------- | -------- | ------- | ------ | --------- | --------- |
| all         | ✅        | ✅       | ✅      | ✅         | ✅         |
| ["mail@x"]  | ✅        | ✅       | ✅      | ✅         | ✅         |
| HIDDEN      | ✅        | ❌       | ❌      | ❌         | ✅         |
| DISABLED    | ❌        | ❌       | ❌      | ❌         | ❌         |

*/

