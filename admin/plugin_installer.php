<?php
declare(strict_types=1);

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\PluginUninstaller;
use nexpell\PluginMigrationHelper;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$installerDebug = [];

global $_database;
$action = null;

foreach (['install','update','reinstall','uninstall'] as $a) {
    if (isset($_GET[$a])) {
        $action = $a;
        break;
    }
}

// ACCESS
AccessControl::checkAdminAccess('ac_plugin_installer');

// CONFIG
$coreVersion   = include __DIR__ . '/../system/version.php';
$pluginDir     = '../includes/plugins/';
$bundledPluginDir = __DIR__ . '/../__plugins/';
$pluginJsonUrl = 'https://www.update.nexpell.de/plugins/plugins_v2.json';

// ADMIN EMAIL
$adminEmail = '';
if (!empty($_SESSION['userID'])) {
    $r = safe_query("SELECT email FROM users WHERE userID=".(int)$_SESSION['userID']." LIMIT 1");
    if ($u = mysqli_fetch_assoc($r)) {
        $adminEmail = strtolower(trim($u['email']));
    }
}

// HELPER: CORE / VISIBILITY
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

    // Core min
    if (!empty($p['core']['min']) &&
        version_compare($coreVersion, $p['core']['min'], '<')) {

        $dbg[] = "{$p['modulname']} {$version}: core {$coreVersion} < min {$p['core']['min']}";
        return false;
    }

    // Core max (NULL erlaubt!)
    if (!empty($p['core']['max']) &&
        version_compare($coreVersion, $p['core']['max'], '>')) {

        $dbg[] = "{$p['modulname']} {$version}: core {$coreVersion} > max {$p['core']['max']}";
        return false;
    }

    $visibleFor = strtoupper($p['visible_for'] ?? 'ALL');

    if ($visibleFor === 'ALL') {
        $dbg[] = "{$p['modulname']} {$version}: visible_for ALL";
        return true;
    }

    if ($visibleFor === 'CUSTOM') {
        $emails = array_map('strtolower', $p['visible_emails'] ?? []);
        if (in_array($adminEmail, $emails, true)) {
            $dbg[] = "{$p['modulname']} {$version}: CUSTOM match {$adminEmail}";
            return true;
        }

        $dbg[] = "{$p['modulname']} {$version}: CUSTOM no match ({$adminEmail})";
        return false;
    }

    $dbg[] = "{$p['modulname']} {$version}: unknown visible_for";
    return false;
}

/**
 * Resolve localized plugin text without depending on the multiLanguage class.
 * Supports:
 * 1) Legacy format: [[lang:de]]...[[lang:gb]]...
 * 2) Object format: {"de":"...", "gb":"...", "it":"..."}
 */
function resolvePluginLocalizedText($value, string $lang): string
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

    $text = (string)$value;
    if ($text === '') {
        return '';
    }

    if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/si', $text, $m)) {
        return trim((string)$m[1]);
    }

    foreach (['en', 'gb', 'de', 'it'] as $fb) {
        if (preg_match('/\[\[lang:' . preg_quote($fb, '/') . '\]\](.*?)(?=\[\[lang:|$)/si', $text, $m)) {
            return trim((string)$m[1]);
        }
    }

    return trim($text);
}

function cleanupDuplicateWebsiteNavigationEntries(string $modulname): void
{
    global $_database;

    $modulnameEscaped = escape($modulname);
    $navResult = safe_query("
        SELECT snavID, mnavID, url, sort, indropdown
        FROM navigation_website_sub
        WHERE modulname = '" . $modulnameEscaped . "'
        ORDER BY sort ASC, snavID ASC
    ");

    $rows = [];
    while ($row = mysqli_fetch_assoc($navResult)) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        return;
    }

    $keepId = (int)$rows[0]['snavID'];
    $keepContentKey = 'nav_sub_' . $keepId;

    safe_query("
        UPDATE navigation_website_sub
        SET last_modified = NOW()
        WHERE snavID = " . $keepId
    );

    if (count($rows) < 2) {
        return;
    }

    for ($i = 1, $count = count($rows); $i < $count; $i++) {
        $deleteId = (int)$rows[$i]['snavID'];
        $deleteContentKey = 'nav_sub_' . $deleteId;

        safe_query("
            INSERT INTO navigation_website_lang
                (content_key, language, content, modulname, updated_at, lang, translation, name)
            SELECT
                '" . $keepContentKey . "',
                language,
                content,
                modulname,
                NOW(),
                lang,
                translation,
                name
            FROM navigation_website_lang
            WHERE content_key = '" . $deleteContentKey . "'
            ON DUPLICATE KEY UPDATE
                updated_at = NOW(),
                modulname = VALUES(modulname)
        ");

        safe_query("
            DELETE FROM navigation_website_lang
            WHERE content_key = '" . $deleteContentKey . "'
        ");

        safe_query("
            DELETE FROM navigation_website_sub
            WHERE snavID = " . $deleteId
        );
    }

    safe_query("
        UPDATE navigation_website_sub
        SET last_modified = NOW()
        WHERE modulname = '" . $modulnameEscaped . "'
          AND (last_modified IS NULL OR snavID = " . $keepId . ")
    ");
}

// LOAD PLUGIN REGISTRY (JSON v2)
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
    nx_alert('danger', $e->getMessage(), false, true, true);
    $rawPlugins = [];
}

// RESOLVE LATEST VERSION PER PLUGIN
$grouped = [];
foreach ($rawPlugins as $plugin) {
    $grouped[$plugin['modulname']][] = $plugin;
}

$plugins = [];

foreach ($grouped as $modulname => $versions) {

    usort($versions, fn($a, $b) =>
        version_compare($b['version'], $a['version'])
    );

    $installerDebug[] = sprintf(
        $languageService->get('installer_module'),
        $modulname
    );

    foreach ($versions as $p) {
        $installerDebug[] = sprintf(
            $languageService->get('installer_found_version'),
            $p['version']
        );
    }

    foreach ($versions as $plugin) {
        if (pluginIsInstallable($plugin, $adminEmail, $coreVersion, $installerDebug)) {
            $plugins[] = $plugin;

            $installerDebug[] = sprintf(
                $languageService->get('installer_selected_plugin'),
                $plugin['modulname'],
                $plugin['version']
            );

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

// INSTALLED PLUGINS
$installed = [];
$r = safe_query("SELECT * FROM settings_plugins_installed");
while ($row = mysqli_fetch_assoc($r)) {
    $installed[$row['modulname']] = $row;
}

// ACTION
if ($action !== null) {

    $modul = basename((string)($_GET[$action] ?? ''));

    // UNINSTALL
    if ($action === 'uninstall') {
        $uninstaller = new PluginUninstaller();
        $uninstaller->uninstall($modul);

        foreach ($uninstaller->getLog() as $entry) {
            nx_alert(in_array($entry['type'], ['success','danger','warning','info'], true) ? $entry['type'] : 'info',(string)($entry['message'] ?? ''),true, true, true);
        }

        nx_audit('DELETE','plugin',(string)$modul,'audit_delete_plugin',['object'   => (string)$modul,'name'     => (string)$modul,'page_url' => 'admincenter.php?site=plugin_installer']);
        nx_redirect('admincenter.php?site=plugin_installer', 'success', 'alert_saved', false);
    }

    // INSTALL / UPDATE / REINSTALL
    $plugin = null;
    foreach ($plugins as $p) {
        if (($p['modulname'] ?? '') === $modul) { $plugin = $p; break; }
    }

    if (!$plugin || !pluginIsInstallable($plugin, $adminEmail, $coreVersion)) nx_redirect('admincenter.php?site=plugin_installer', 'danger', 'alert_plugin_not_installable', false);

    $pluginPath = $pluginDir . $modul;
    $bundledPluginPath = $bundledPluginDir . $modul;

    $script = ($action === 'update') ? 'update.php' : 'install.php';
    $scriptPath = $pluginPath . '/' . $script;
    $hasLocalBundledScript = file_exists($scriptPath);

    $filesRefreshed = false;
    if (in_array($action, ['install', 'update', 'reinstall'], true)) {
        $filesRefreshed = download_plugin_files($plugin, $pluginPath, $languageService);

        if (!$filesRefreshed) {
            $localZipPath = findLocalPluginPackagePath($plugin, $pluginPath);
            if ($localZipPath !== null) {
                $filesRefreshed = install_plugin_files_from_zip($localZipPath, $pluginPath, $modul, $languageService);
                if ($filesRefreshed) {
                    nx_alert(
                        'warning',
                        'Remote plugin download failed. Using local plugin package instead.',
                        false,
                        true,
                        true
                    );
                }
            }
        }

        if (!$filesRefreshed && is_dir($bundledPluginPath)) {
            syncPluginDirectoryFromSource($bundledPluginPath, $pluginPath, $modul);
            $filesRefreshed = true;
            nx_alert(
                'warning',
                'Remote plugin download failed. Using local bundled plugin files instead.',
                false,
                true,
                true
            );
        }

        if (!$filesRefreshed) {
            nx_redirect('admincenter.php?site=plugin_installer', 'danger', 'alert_download_failed', false);
        }

        clearstatcache(true, $scriptPath);
        $hasLocalBundledScript = file_exists($scriptPath);
        if (!$hasLocalBundledScript) {
            nx_redirect('admincenter.php?site=plugin_installer', 'danger', 'alert_download_failed', false);
        }
    }
    if ($action === 'reinstall') { nx_alert('info', 'alert_reinstall_cleaned', true); }

    // Compatibility shim: older plugin packages may still write into
    // *_lang(name, lang, translation) while newer cores use
    // *_lang(content_key, language, content, updated_at).
    // Ensure legacy columns exist in common language tables so both
    // installer generations can run on fresh systems.
    if ($action !== 'uninstall') {
        $compatTables = [
            'settings_plugins_lang',
            'navigation_dashboard_lang',
            'navigation_website_lang'
        ];

        $legacyCols = [
            'name'        => "VARCHAR(255) NOT NULL DEFAULT ''",
            'lang'        => "VARCHAR(10) NOT NULL DEFAULT 'de'",
            'translation' => "TEXT NULL"
        ];

        foreach ($compatTables as $tableName) {
            $tableRes = safe_query("SHOW TABLES LIKE '" . escape($tableName) . "'");
            $hasTable = $tableRes && mysqli_num_rows($tableRes) > 0;
            if (!$hasTable) {
                continue;
            }

            foreach ($legacyCols as $colName => $colType) {
                $colRes = safe_query("SHOW COLUMNS FROM `$tableName` LIKE '" . escape($colName) . "'");
                if (!$colRes || mysqli_num_rows($colRes) === 0) {
                    safe_query("ALTER TABLE `$tableName` ADD COLUMN `$colName` $colType");
                }
            }
        }

    }

    if (file_exists($scriptPath)) {
        // Normalize known legacy column triplets inside downloaded plugin scripts
        // before execution (old packages may still use name/lang/translation).
        $scriptContent = @file_get_contents($scriptPath);
        if (is_string($scriptContent) && $scriptContent !== '') {
            $normalizedScript = strtr($scriptContent, [
                '(`name`, `lang`, `translation`)' => '(`content_key`, `language`, `content`)',
                '(name, lang, translation)' => '(content_key, language, content)',
                '(`name`, `language`, `content`)' => '(`content_key`, `language`, `content`)',
                '(name, language, content)' => '(content_key, language, content)',
                '(`content_key`, `lang`, `content`)' => '(`content_key`, `language`, `content`)',
                '(content_key, lang, content)' => '(content_key, language, content)'
            ]);
            if ($normalizedScript !== $scriptContent) {
                @file_put_contents($scriptPath, $normalizedScript);
            }
        }
        include $scriptPath;
    }

    cleanupDuplicateWebsiteNavigationEntries($plugin['modulname']);

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
    nx_audit_action('plugin_installer', ($action === 'update' ? 'audit_action_plugin_updated' : ($action === 'reinstall' ? 'audit_action_plugin_reinstalled' : 'audit_action_plugin_installed')), $modul, null, 'admincenter.php?site=plugin_installer');
    nx_redirect('admincenter.php?site=plugin_installer', 'success', 'alert_saved', false);
}

// 1) GLOBAL SORTIEREN
usort($plugins, fn($a, $b) =>
    strcasecmp(
        (string)($a['modulname'] ?? ''),
        (string)($b['modulname'] ?? '')
    )
);

// 2) VIEW DATA
$currentLang = strtolower((string)(isset($lang) ? $lang : ($_SESSION['language'] ?? 'de')));
$searchQuery = '';






?>
<div class="d-flex justify-content-end mb-3">
    <div class="input-group input-group-sm" style="min-width: 260px; max-width: 360px;">
        <span class="input-group-text">
            <i class="bi bi-search"></i>
        </span>
        <input
            id="pluginSearch"
            type="search"
            class="form-control"
            placeholder="<?= $languageService->get('search') ?>"
            value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>
</div>
    <div class="card-body p-0">
            <div class="row g-4">
                <?php
                if (empty($plugins)): ?>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            Keine Plugins fuer die aktuelle Suche gefunden.
                        </div>
                    </div>
                <?php
                endif;

                foreach ($plugins as $p):

                    $inst = $installed[$p['modulname']] ?? null;

                    // 1) Update Abfrage
                    $installedVersion = $inst['version'] ?? null;
                    $latestVersion    = $p['version'];

                    $isInstalled = (bool)$inst;
                    $hasUpdate   = $isInstalled && version_compare($latestVersion, $installedVersion, '>');

                    // Beschreibung übersetzen
                    $desc = resolvePluginLocalizedText($p['description'] ?? '', $currentLang);

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
                                          class="rounded me-1"
                                          style="height:22px;">';
                            }
                        }
                    }
                    $searchText = mb_strtolower(trim(implode(' ', [
                        (string)($p['modulname'] ?? ''),
                        (string)($p['name'] ?? ''),
                        (string)($p['author'] ?? ''),
                        trim(strip_tags((string) $desc)),
                    ])));
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 plugin-card-item mb-4 mt-4" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card h-100 shadow-sm">

                            <img class="card-img-top"
                                 src="https://www.update.nexpell.de/plugins/images/<?= htmlspecialchars($p['image'] ?? 'default.png') ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 onerror="this.onerror=null;this.src='https://www.update.nexpell.de/plugins/images/default.png';">

                            <div class="card-body d-flex flex-column">

                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h5 class="mb-0">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </h5>
                                    <div class="small">
                                        <?= $flags_html ?>
                                    </div>
                                </div>

                                <div class="small text-muted mb-2">
                                    <?=$languageService->get('version') ?> <?= htmlspecialchars($p['version']) ?>
                                </div>
<?php
$previewLength = 160;

$desc = (string)($desc ?? '');
$plainText = trim(strip_tags($desc));

$isLong  = mb_strlen($plainText) > $previewLength;
$preview = mb_substr($plainText, 0, $previewLength);

// ✅ Eindeutige ID pro Plugin
$uid = 'desc_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['modulname']);
?>
<div class="text-muted mb-2 mt-2" style="line-height:1.4">

    <div class="desc-box"
         data-preview="<?= htmlspecialchars($preview, ENT_QUOTES) ?>"
         data-full="<?= htmlspecialchars($plainText, ENT_QUOTES) ?>"
         data-open="0">
        <?= nl2br(htmlspecialchars($preview)) ?><?php if ($isLong): ?>…<?php endif; ?>
    </div>

    <?php if ($isLong): ?>
        <a href="#"
           class="d-inline-block mt-1 small text-primary desc-toggle">
            mehr anzeigen
        </a>
    <?php endif; ?>

</div>








                                

                                <div class="mt-auto">

                                    <div class="mt-auto">

                                        <?php if ($isInstalled && !$hasUpdate): ?>

                                        <!-- Installiert & aktuell -->
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-secondary" disabled>
                                                <?=$languageService->get('installed') ?> (<?= htmlspecialchars($installedVersion) ?>)
                                            </button>

                                            <a class="btn btn-warning"
                                            href="?site=plugin_installer&reinstall=<?= urlencode($p['modulname']) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteModal"
                                            data-confirm-url="?site=plugin_installer&reinstall=<?= urlencode($p['modulname']) ?>"
                                            data-header-class="bg-warning text-light"
                                            data-confirm-class="btn-warning"
                                            data-modal-title="<?= htmlspecialchars($languageService->get('reinstall'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-modal-body="<?= htmlspecialchars($languageService->get('reinstall_info'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-confirm-text="<?= htmlspecialchars($languageService->get('reinstall'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-cancel-text="<?= htmlspecialchars($languageService->get('cancel'), ENT_QUOTES, 'UTF-8') ?>">
                                            <?=$languageService->get('reinstall') ?>
                                            </a>

                                            <?php
                                                $deleteUrl = '?site=plugin_installer&uninstall=' . urlencode($p['modulname']);
                                                $deleteUrlAttr = htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button type="button"
                                                    class="btn btn-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#confirmDeleteModal"
                                                    data-delete-url="<?= $deleteUrlAttr ?>">
                                                <?= $languageService->get('deinstall') ?>
                                            </button>
                                        </div>

                                    <?php elseif ($hasUpdate): ?>

                                        <!-- Update verfügbar -->
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-info" disabled>
                                                <?=$languageService->get('installed') ?>: <?= htmlspecialchars($installedVersion) ?>
                                            </button>

                                            <a class="btn btn-primary"
                                               href="?site=plugin_installer&update=<?= urlencode($p['modulname']) ?>">
                                                <?=$languageService->get('update_to') ?> <?= htmlspecialchars($latestVersion) ?>
                                            </a>

                                            <?php
                                                $deleteUrl = '?site=plugin_installer&uninstall=' . urlencode($p['modulname']);
                                                $deleteUrlAttr = htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button type="button"
                                                    class="btn btn-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#confirmDeleteModal"
                                                    data-delete-url="<?= $deleteUrlAttr ?>">
                                                <?= $languageService->get('deinstall') ?>
                                            </button>
                                        </div>

                                    <?php elseif (pluginIsInstallable($p, $adminEmail, $coreVersion)): ?>

                                        <!-- Noch nicht installiert -->
                                        <a class="btn btn-success w-100"
                                           href="?site=plugin_installer&install=<?= urlencode($p['modulname']) ?>">
                                            <?=$languageService->get('install') ?>
                                        </a>

                                    <?php else: ?>

                                        <!-- Nicht verfügbar -->
                                        <button class="btn btn-secondary w-100" disabled>
                                            <?=$languageService->get('not_available') ?>
                                        </button>

                                    <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div id="pluginSearchEmpty" class="col-12 d-none">
                    <div class="alert alert-info mb-0">
                        Keine Plugins fuer die aktuelle Suche gefunden.
                    </div>
                </div>

        <!-- Zurück -->

<style>
.desc-box {
    overflow: hidden;
    transition: height .35s ease;
}

</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var input = document.getElementById("pluginSearch");
    if (!input) return;
    var cards = Array.prototype.slice.call(document.querySelectorAll(".plugin-card-item"));
    var emptyState = document.getElementById("pluginSearchEmpty");

    function applyFilter() {
        var query = (input.value || "").toLowerCase().trim();
        var visible = 0;

        cards.forEach(function (card) {
            var haystack = (card.getAttribute("data-search") || "").toLowerCase();
            var show = !query || haystack.indexOf(query) !== -1;
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
</script>
</div>
</div>

<?php

// DOWNLOAD
function download_plugin_files(array $plugin, string $target, $languageService): bool
{
    // Basisdaten
    $modul = $plugin['modulname'] ?? null;

    if (!$modul) {
        error_log(
            $languageService->get('installer_error_missing_modulname')
        );
        return false;
    }

    // ZIP-Dateiname
    // Priorität: JSON -> download
    // Fallback: modulname.zip (alte Plugins)
    $downloadUrls = buildPluginPackageDownloadUrls(buildPluginPackageCandidateNames($plugin));

    // Temp-Datei
    $tmp = sys_get_temp_dir() . '/' . uniqid($modul . '_', true) . '.zip';

    // Download
    $data = false;
    foreach ($downloadUrls as $url) {
        $candidateData = @file_get_contents($url);
        if ($candidateData === false || strlen($candidateData) < 100) {
            continue;
        }

        $data = $candidateData;
        break;
    }

    if ($data === false) {
        error_log(
            sprintf(
                $languageService->get('installer_error_download_failed_url'),
                implode(' | ', $downloadUrls)
            )
        );
        return false;
    }

    file_put_contents($tmp, $data);

    // ZIP prüfen
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        error_log(
            sprintf(
                $languageService->get('installer_error_zip_open_failed'),
                $tmp
            )
        );
        @unlink($tmp);
        return false;
    }

    $zip->close();
    $ok = install_plugin_files_from_zip($tmp, $target, $modul, $languageService);
    @unlink($tmp);
    return $ok;
}

function buildPluginPackageCandidateNames(array $plugin): array
{
    $modul = (string)($plugin['modulname'] ?? '');
    $version = (string)($plugin['version'] ?? '');
    $download = basename((string)($plugin['download'] ?? ''));

    $candidateNames = [];
    if ($download !== '' && strtoupper($download) !== 'DISABLED') {
        $candidateNames[] = $download;
    }
    if ($modul !== '' && $version !== '') {
        $candidateNames[] = $modul . '_' . $version . '.zip';
        $candidateNames[] = $modul . '-' . $version . '.zip';
    }
    if ($modul !== '') {
        $candidateNames[] = $modul . '.zip';
    }

    return array_values(array_unique($candidateNames));
}

function buildPluginPackageDownloadUrls(array $candidateNames): array
{
    $urls = [];
    $serverName = rawurlencode((string)($_SERVER['SERVER_NAME'] ?? ''));

    foreach ($candidateNames as $candidateName) {
        $encodedName = rawurlencode($candidateName);
        $urls[] = "https://www.update.nexpell.de/system/download.php?type=plugin&file={$encodedName}&site={$serverName}";
        $urls[] = "https://www.update.nexpell.de/plugins/{$encodedName}";
    }

    return array_values(array_unique($urls));
}

function install_plugin_files_from_zip(string $zipPath, string $target, ?string $modul, $languageService): bool
{
    if (!file_exists($zipPath)) {
        return false;
    }

    $extractDir = sys_get_temp_dir() . '/' . uniqid((string)$modul . '_extract_', true);
    ensureDirectory($extractDir);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        error_log(
            sprintf(
                $languageService->get('installer_error_zip_open_failed'),
                $zipPath
            )
        );
        deleteFolder($extractDir);
        return false;
    }

    $zip->extractTo($extractDir);
    $zip->close();

    $sourceDir = resolveExtractedPluginRoot($extractDir, $modul);
    if ($sourceDir === null) {
        deleteFolder($extractDir);
        return false;
    }

    syncPluginDirectoryFromSource($sourceDir, $target, $modul);

    foreach (['install.php', 'update.php'] as $scriptFile) {
        $scriptPath = $target . '/' . $scriptFile;
        if (file_exists($scriptPath)) {
            normalize_legacy_lang_columns_in_script($scriptPath);
        }
    }

    $hasInstallerScript = file_exists($target . '/install.php') || file_exists($target . '/update.php');
    if (!$hasInstallerScript) {
        error_log(
            sprintf(
                $languageService->get('installer_error_plugin_missing_files'),
                (string)$modul
            )
        );
        deleteFolder($extractDir);
        return false;
    }

    deleteFolder($extractDir);
    return true;
}

function findLocalPluginPackagePath(array $plugin, string $pluginPath): ?string
{
    $modul = (string)($plugin['modulname'] ?? '');

    foreach (buildPluginPackageCandidateNames($plugin) as $candidateName) {
        $candidatePath = rtrim($pluginPath, '/\\') . '/' . $candidateName;
        if (file_exists($candidatePath)) {
            return $candidatePath;
        }
    }

    if ($modul === '') {
        return null;
    }

    $fallbackMatches = glob(rtrim($pluginPath, '/\\') . '/' . $modul . '*.zip');
    if (!is_array($fallbackMatches) || $fallbackMatches === []) {
        return null;
    }

    usort($fallbackMatches, static function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    return $fallbackMatches[0] ?? null;
}

function normalize_legacy_lang_columns_in_script(string $scriptPath): void
{
    $content = @file_get_contents($scriptPath);
    if ($content === false || $content === '') {
        return;
    }

    // 1) Make known legacy seed inserts idempotent.
    $patched = str_replace(
        [
            "INSERT INTO `plugins_achievements` (`id`,",
            "INSERT INTO plugins_achievements (id,",
            "INSERT INTO `plugins_achievements_categories` (`id`,",
            "INSERT INTO plugins_achievements_categories (id,",
        ],
        [
            "INSERT IGNORE INTO `plugins_achievements` (`id`,",
            "INSERT IGNORE INTO plugins_achievements (id,",
            "INSERT IGNORE INTO `plugins_achievements_categories` (`id`,",
            "INSERT IGNORE INTO plugins_achievements_categories (id,",
        ],
        $content
    );

    // 2) Normalize legacy *_lang column names.
    $patched = preg_replace_callback(
        '/(INSERT\s+(?:IGNORE\s+)?INTO\s+`?[a-z0-9_]+_lang`?\s*)\((.*?)\)(\s*VALUES)/is',
        static function (array $m): string {
            $rawCols = $m[2];
            $cols = array_map(
                static function (string $c): string {
                    return strtolower(trim(str_replace('`', '', $c)));
                },
                explode(',', $rawCols)
            );

            $map = [
                'name' => 'content_key',
                'lang' => 'language',
                'translation' => 'content',
            ];

            $changed = false;
            foreach ($cols as $i => $col) {
                if (isset($map[$col])) {
                    $cols[$i] = $map[$col];
                    $changed = true;
                }
            }

            if (!$changed) {
                return $m[0];
            }

            $newCols = '`' . implode('`, `', $cols) . '`';
            return $m[1] . '(' . $newCols . ')' . $m[3];
        },
        $patched
    );

    if (!is_string($patched) || $patched === '') {
        return;
    }

    if ($patched !== $content) {
        @file_put_contents($scriptPath, $patched);
    }
}

function pluginPersistentRelativePaths(?string $modul): array
{
    $common = ['uploads', 'uploads/forum_images', 'images', 'files', 'img'];
    $map = [
        'forum' => ['uploads', 'uploads/forum_images'],
        'gallery' => ['images/upload'],
    ];

    $modul = strtolower((string)$modul);
    $paths = $common;
    if ($modul !== '' && isset($map[$modul])) {
        $paths = array_merge($paths, $map[$modul]);
    }

    return array_values(array_unique($paths));
}

function detectPluginPersistentRelativePaths(string $pluginDir): array
{
    $detected = [];
    $directoryNames = ['images', 'uploads', 'files', 'img'];

    if (!is_dir($pluginDir)) {
        return $detected;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isDir()) {
            continue;
        }

        $basename = strtolower($item->getBasename());
        if (!in_array($basename, $directoryNames, true)) {
            continue;
        }

        $fullPath = str_replace('\\', '/', $item->getPathname());
        $pluginBase = rtrim(str_replace('\\', '/', $pluginDir), '/');
        if (strpos($fullPath, $pluginBase . '/') !== 0) {
            continue;
        }

        $relative = ltrim(substr($fullPath, strlen($pluginBase)), '/');
        if ($relative !== '') {
            $detected[] = $relative;
        }
    }

    usort($detected, static function (string $a, string $b): int {
        return substr_count($a, '/') <=> substr_count($b, '/');
    });

    $normalized = [];
    foreach ($detected as $path) {
        $skip = false;
        foreach ($normalized as $kept) {
            if ($path === $kept || strpos($path, $kept . '/') === 0) {
                $skip = true;
                break;
            }
        }

        if (!$skip) {
            $normalized[] = $path;
        }
    }

    return $normalized;
}

function backupPluginPersistentPaths(string $pluginDir, ?string $modul): ?string
{
    $backupDir = sys_get_temp_dir() . '/' . uniqid('plugin_preserve_', true);
    $hasBackup = false;

    $persistentPaths = array_values(array_unique(array_merge(
        pluginPersistentRelativePaths($modul),
        detectPluginPersistentRelativePaths($pluginDir)
    )));

    foreach ($persistentPaths as $relativePath) {
        $sourcePath = $pluginDir . '/' . str_replace('\\', '/', $relativePath);
        if (!file_exists($sourcePath)) {
            continue;
        }

        $targetPath = $backupDir . '/' . str_replace('\\', '/', $relativePath);
        ensureDirectory(dirname($targetPath));

        if (is_dir($sourcePath)) {
            copyDirectoryRecursive($sourcePath, $targetPath);
        } else {
            @copy($sourcePath, $targetPath);
        }

        $hasBackup = true;
    }

    if (!$hasBackup) {
        if (is_dir($backupDir)) {
            deleteFolder($backupDir);
        }
        return null;
    }

    return $backupDir;
}

function resolveExtractedPluginRoot(string $extractDir, ?string $modul): ?string
{
    $candidates = [$extractDir];
    $modul = strtolower((string)$modul);

    if (is_dir($extractDir . '/' . $modul)) {
        array_unshift($candidates, $extractDir . '/' . $modul);
    }

    foreach ($candidates as $candidate) {
        if (file_exists($candidate . '/install.php') || file_exists($candidate . '/update.php')) {
            return $candidate;
        }
    }

    $entries = @scandir($extractDir);
    if (!is_array($entries)) {
        return null;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $candidate = $extractDir . '/' . $entry;
        if (!is_dir($candidate)) {
            continue;
        }

        if (file_exists($candidate . '/install.php') || file_exists($candidate . '/update.php')) {
            return $candidate;
        }
    }

    return null;
}

function syncPluginDirectoryFromSource(string $sourceDir, string $targetDir, ?string $modul): void
{
    $preserved = array_values(array_unique(array_merge(
        pluginPersistentRelativePaths($modul),
        detectPluginPersistentRelativePaths($targetDir)
    )));

    ensureDirectory($targetDir);
    deleteNonPreservedPluginEntries($targetDir, $preserved);
    copyDirectoryRecursive($sourceDir, $targetDir);
}

function deleteNonPreservedPluginEntries(string $targetDir, array $preservedRelativePaths): void
{
    if (!is_dir($targetDir)) {
        return;
    }

    $entries = @scandir($targetDir);
    if (!is_array($entries)) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $targetDir . '/' . $entry;
        if (shouldPreservePluginPath($entry, $preservedRelativePaths)) {
            continue;
        }

        if (is_dir($fullPath)) {
            deleteFolder($fullPath);
        } elseif (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}

function shouldPreservePluginPath(string $relativePath, array $preservedRelativePaths): bool
{
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
    if ($relativePath === '') {
        return false;
    }

    foreach ($preservedRelativePaths as $preservedPath) {
        $preservedPath = trim(str_replace('\\', '/', $preservedPath), '/');
        if ($preservedPath === '') {
            continue;
        }

        if (
            $relativePath === $preservedPath ||
            strpos($relativePath, $preservedPath . '/') === 0 ||
            strpos($preservedPath, $relativePath . '/') === 0
        ) {
            return true;
        }
    }

    return false;
}

function restorePluginPersistentPaths(string $backupDir, string $pluginDir): void
{
    if (!is_dir($backupDir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($backupDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', $iterator->getSubPathName());
        $targetPath = $pluginDir . '/' . $relative;

        if ($item->isDir()) {
            ensureDirectory($targetPath);
            continue;
        }

        ensureDirectory(dirname($targetPath));
        @copy($item->getPathname(), $targetPath);
    }

    deleteFolder($backupDir);
}

function copyDirectoryRecursive(string $sourceDir, string $targetDir): void
{
    ensureDirectory($targetDir);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', $iterator->getSubPathName());
        $targetPath = $targetDir . '/' . $relative;

        if ($item->isDir()) {
            ensureDirectory($targetPath);
            continue;
        }

        ensureDirectory(dirname($targetPath));
        @copy($item->getPathname(), $targetPath);
    }
}

function ensureDirectory(string $dir): void
{
    if ($dir === '' || $dir === '.' || is_dir($dir)) {
        return;
    }

    @mkdir($dir, 0755, true);
}

function deleteFolder(string $d): void
{
    foreach (array_diff(scandir($d),['.','..']) as $f) {
        $p="$d/$f";
        is_dir($p)?deleteFolder($p):unlink($p);
    }
    rmdir($d);
}

?>
<script>
document.addEventListener('click', function (e) {

    const btn = e.target.closest('.desc-toggle');
    if (!btn) return;

    e.preventDefault();

    const box = btn.previousElementSibling;
    const isOpen = box.dataset.open === '1';

    const fromHeight = box.offsetHeight;

    // Inhalt tauschen
    box.innerHTML = isOpen
        ? box.dataset.preview + '…'
        : box.dataset.full;

    // Zielhöhe messen
    const toHeight = box.scrollHeight;

    // Animation vorbereiten
    box.style.height = fromHeight + 'px';

    requestAnimationFrame(() => {
        box.style.height = toHeight + 'px';
    });

    // Nach Animation auf auto zurücksetzen
    setTimeout(() => {
        box.style.height = 'auto';
    }, 350);

    box.dataset.open = isOpen ? '0' : '1';
    btn.textContent = isOpen ? 'mehr anzeigen' : 'weniger anzeigen';

});
</script>
