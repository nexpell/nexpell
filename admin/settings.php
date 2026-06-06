<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_settings');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

if (isset($_POST['submit'])) {
    $CAPCLASS = new \nexpell\Captcha;

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {

        $stmt = $_database->prepare("
            UPDATE settings SET
                hptitle    = ?,
                hpurl      = ?,
                clanname   = ?,
                clantag    = ?,
                adminname  = ?,
                adminemail = ?,
                since      = ?,
                webkey     = ?,
                seckey     = ?,
                keywords   = ?,
                startpage  = ?
        ");

        $hptitle   = (string)($_POST['hptitle'] ?? '');
        $url       = (string)($_POST['url'] ?? '');
        $clanname  = (string)($_POST['clanname'] ?? '');
        $clantag   = (string)($_POST['clantag'] ?? '');
        $admname   = (string)($_POST['admname'] ?? '');
        $admmail   = (string)($_POST['admmail'] ?? '');
        $since     = (string)($_POST['since'] ?? '');
        $webkey    = (string)($_POST['webkey'] ?? '');
        $seckey    = (string)($_POST['seckey'] ?? '');
        $keywords  = (string)($_POST['keywords'] ?? '');
        $startpage = (string)($_POST['startpage'] ?? '');

        $stmt->bind_param(
            "sssssssssss",
            $hptitle,
            $url,
            $clanname,
            $clantag,
            $admname,
            $admmail,
            $since,
            $webkey,
            $seckey,
            $keywords,
            $startpage
        );

        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok) {
            nx_audit_update('settings', 'singleton', $affected > 0);
            nx_redirect('admincenter.php?site=settings', 'success', 'alert_saved', false);
        }

        nx_redirect('admincenter.php?site=settings', 'danger', 'alert_transaction_invalid', false);
    }

    nx_redirect('admincenter.php?site=settings', 'danger', 'alert_transaction_invalid', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {

    $CAPCLASS = new \nexpell\Captcha;
    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        // Keine Audit-Zeile: keine gültige Aktion ausgeführt
        nx_redirect('admincenter.php?site=settings', 'danger', 'alert_transaction_invalid', false);
    }

    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
    ini_set('log_errors', '1');

    $rootDir     = dirname(__DIR__);
    $sitemapFile = $rootDir . '/sitemap.xml';
    $stateDir    = $rootDir . '/var';
    $updateFile  = $stateDir . '/sitemap_last_update.txt';

    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    try {
        $sitemapDir = dirname($sitemapFile);
        if (!is_dir($sitemapDir)) {
            throw new RuntimeException(sprintf($languageService->get('sitemap_missing_dir'), $sitemapDir));
        }
        if (!is_writable($sitemapDir)) {
            throw new RuntimeException(sprintf($languageService->get('sitemap_writeable'), $sitemapDir));
        }
        if (!is_dir($stateDir)) {
            @mkdir($stateDir, 0775, true);
        }
        if (!is_writable($stateDir)) {
            throw new RuntimeException(sprintf($languageService->get('sitemap_updatedir'), $stateDir));
        }

        if (!defined('SITEMAP_EMIT')) {
            define('SITEMAP_EMIT', false);
        }
        if (!defined('SITEMAP_EMIT')) {
            define('SITEMAP_EMIT', false);
        }

        ob_start();
        $return = include $rootDir . '/sitemap.php';
        $buffer = ob_get_clean();

        $xml = '';
        if (is_string($return) && $return !== '') {
            $xml = $return;
        } elseif (is_string($buffer) && trim($buffer) !== '') {
            $xml = $buffer;
        }

        if (!is_string($xml) || trim($xml) === '') {
            throw new RuntimeException(sprintf($languageService->get('sitemap_no_xml'), 'sitemap.php'));
        }
        if (strpos($xml, '<urlset') === false || strpos($xml, '</urlset>') === false) {
            throw new RuntimeException(sprintf($languageService->get('sitemap_invalid_xml'), '<urlset>'));
        }

        $tmp = tempnam($sitemapDir, 'smap_');
        if ($tmp === false) {
            throw new RuntimeException(sprintf($languageService->get('sitemap_tmp_failed'), $sitemapDir));
        }

        $oldUmask = umask(0022);
        $bytes    = file_put_contents($tmp, $xml);
        if ($bytes === false) {
            umask($oldUmask);
            throw new RuntimeException(sprintf($languageService->get('sitemap_write_failed'), basename($tmp)));
        }

        if (!@rename($tmp, $sitemapFile)) {
            @unlink($tmp);
            umask($oldUmask);
            throw new RuntimeException(
                sprintf(
                    $languageService->get('sitemap_rename_failed'),
                    basename($tmp),
                    basename($sitemapFile)
                )
            );
        }

        @chmod($sitemapFile, 0644);
        umask($oldUmask);

        $stamp = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('d.m.Y H:i:s');
        @file_put_contents($updateFile, $stamp, LOCK_EX);

        error_log(sprintf($languageService->get('sitemap_log_success'), $stamp));

        nx_audit_action('settings','audit_action_sitemap_generated',null,null,'admincenter.php?site=settings',['stamp' => $stamp]);
        nx_redirect('admincenter.php?site=settings', 'success', 'sitemap_regenerate', false);

    } catch (Throwable $e) {
        error_log(sprintf($languageService->get('sitemap_log_error'), $e->getMessage()));
        nx_redirect('admincenter.php?site=settings', 'danger', $e->getMessage(), false);
    }
}

if (isset($_POST['saveedit'])) {
    $CAPCLASS = new \nexpell\Captcha;

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {

        $stmt = $_database->prepare("
            UPDATE settings_social_media SET
                twitch = ?, facebook = ?, twitter = ?, youtube = ?, rss = ?, vine = ?,
                flickr = ?, linkedin = ?, instagram = ?, gametracker = ?, steam = ?, discord = ?
        ");

        $twitch      = (string)($_POST['twitch'] ?? '');
        $facebook    = (string)($_POST['facebook'] ?? '');
        $twitter     = (string)($_POST['twitter'] ?? '');
        $youtube     = (string)($_POST['youtube'] ?? '');
        $rss         = (string)($_POST['rss'] ?? '');
        $vine        = (string)($_POST['vine'] ?? '');
        $flickr      = (string)($_POST['flickr'] ?? '');
        $linkedin    = (string)($_POST['linkedin'] ?? '');
        $instagram   = (string)($_POST['instagram'] ?? '');
        $gametracker = (string)($_POST['gametracker'] ?? '');
        $steam       = (string)($_POST['steam'] ?? '');
        $discord     = (string)($_POST['discord'] ?? '');

        $stmt->bind_param(
            "ssssssssssss",
            $twitch,
            $facebook,
            $twitter,
            $youtube,
            $rss,
            $vine,
            $flickr,
            $linkedin,
            $instagram,
            $gametracker,
            $steam,
            $discord
        );

        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok) {
            nx_audit_update('settings_social_media',null,$affected > 0,null,'admincenter.php?site=settings&action=social_setting');
            nx_redirect('admincenter.php?site=settings&action=social_setting', 'success', 'alert_saved', false);
        }

        nx_redirect('admincenter.php?site=settings&action=social_setting', 'danger', 'alert_transaction_invalid', false);
    }

    nx_redirect('admincenter.php?site=settings&action=social_setting', 'danger', 'alert_transaction_invalid', false);
}

if (isset($_POST['use_seo_urls_edit'])) {

    $result = $_database->query("SELECT use_seo_urls FROM settings LIMIT 1");
    if (!$result) {
        nx_redirect('admincenter.php?site=settings', 'danger', 'alert_transaction_invalid', false);
    }

    $row          = $result->fetch_assoc();
    $currentValue = (int)($row['use_seo_urls'] ?? 0);
    $newValue     = $currentValue === 1 ? 0 : 1;

    $stmt = $_database->prepare("UPDATE settings SET use_seo_urls = ? LIMIT 1");
    if (!$stmt) {
        nx_redirect('admincenter.php?site=settings', 'danger', 'alert_transaction_invalid', false);
    }

    $stmt->bind_param('i', $newValue);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {

        nx_audit_update('settings',null,true,null,'admincenter.php?site=settings',['new_value' => $newValue]);
        nx_redirect('admincenter.php?site=settings','success',$newValue === 1 ? 'alert_activated' : 'alert_deactivated',false);
    }

    nx_redirect('admincenter.php?site=settings', 'danger', 'alert_transaction_invalid', false);
}

// Allgemeine Einstellungen
if ($action == "") { 

$settings = safe_query("SELECT * FROM settings");
$ds = mysqli_fetch_array($settings);

// Ausgabe starten
echo '<div class="d-flex flex-wrap align-items-start gap-3 mb-4">
            <a href="admincenter.php?site=settings" class="btn btn-secondary disabled" aria-current="page">
                ' . $languageService->get('settings') . '
            </a>
            <a href="admincenter.php?site=settings&action=social_setting" class="btn btn-secondary">
                ' . $languageService->get('social_settings') . '
            </a>
    </div>';

$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

echo '<form method="post" action="">
        <div class="row align-items-stretch">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 mb-4 shadow-sm h-100">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="bi bi-globe2"></i>
                                <span>' . $languageService->get('site_settings') .'</span>
                                <small class="small-muted">' . $languageService->get('website_info_description') . '</small>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label class="form-label">' . $languageService->get('page_url') . ':</label>
                                <div>
                                    <input class="form-control" type="url" name="url" value="' . htmlspecialchars($ds['hpurl']) . '" placeholder="' . $languageService->get('page_url') . '">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SEO & ' . $languageService->get('page_title') . ':</label>
                                <div>
                                    <input class="form-control" type="text" name="hptitle" value="' . htmlspecialchars($ds['hptitle']) . '" placeholder="' . $languageService->get('page_title') . '">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">' . $languageService->get('meta_keywords') . ':</label>
                                    <div>
                                        <textarea class="form-control" name="keywords" rows="8">' . htmlspecialchars($ds['keywords']) . '</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>';
                echo' </div> <!-- col-md-4 -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 mb-4 shadow-sm h-100">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="bi bi-sliders"></i>
                                    <span>️ ' . $languageService->get('general_settings') . '</span>
                                    <small class="small-muted">' . $languageService->get('project_info_description') . '</small>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <label class="form-label">' . $languageService->get('clan_name') . ':</label>
                                <div>
                                    <input class="form-control" type="text" name="clanname" value="' . htmlspecialchars($ds['clanname']) . '" placeholder="' . $languageService->get('clan_name') . '">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">' . $languageService->get('since') . ':</label>
                                <div>
                                    <input class="form-control" type="text" name="since" value="' . htmlspecialchars($ds['since']) . '" placeholder="' . $languageService->get('since') . '">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">' . $languageService->get('clan_tag') . ':</label>
                                <div>
                                    <input class="form-control" type="text" name="clantag" value="' . htmlspecialchars($ds['clantag']) . '" placeholder="' . $languageService->get('clan_tag') . '">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">' . $languageService->get('admin_name') . ':</label>
                                <div>
                                    <input class="form-control" type="text" name="admname" value="' . htmlspecialchars($ds['adminname']) . '" placeholder="' . $languageService->get('admin_name') . '">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">' . $languageService->get('admin_email') . ':</label>
                                <div>
                                    <input class="form-control" type="email" name="admmail" value="' . htmlspecialchars($ds['adminemail']) . '" placeholder="' . $languageService->get('admin_email') . '">
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- col-md-4 -->';
                    echo '<div class="col-md-4">
                            <div class="card shadow-sm border-0 mb-4 h-100 position-relative">

                                <!-- Header -->
                                <div class="card-header position-relative pe-5">
                                    <div class="card-title mb-0">
                                        <i class="bi bi-shield-lock me-1"></i>
                                        <span>' . $languageService->get('reCaptcha') . '</span><br>
                                        <small class="text-muted">' . $languageService->get('recaptcha_description') . '</small>
                                    </div>
                                    <img 
                                        src="/admin/images/recapcha.png"
                                        alt="Google reCAPTCHA"
                                        class="position-absolute top-0 end-0 m-2"
                                        style="max-height:80px; opacity:0.9;"
                                    >
                                </div>

                                <!-- Body -->
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <label for="webkey" class="form-label fw-semibold">
                                            ' . $languageService->get('web_key') . ':
                                        </label>
                                        <input 
                                            id="webkey"
                                            class="form-control"
                                            type="text"
                                            name="webkey"
                                            value="' . htmlspecialchars($ds['webkey']) . '"
                                            placeholder="' . $languageService->get('web_key') . '"
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <label for="seckey" class="form-label fw-semibold">
                                            ' . $languageService->get('secret_key') . ':
                                        </label>
                                        <input 
                                            id="seckey"
                                            class="form-control"
                                            type="text"
                                            name="seckey"
                                            value="' . htmlspecialchars($ds['seckey']) . '"
                                            placeholder="' . $languageService->get('secret_key') . '"
                                        >
                                    </div>
                                </div>

                            </div>
                        </div></div>
                        <br>';
                    // Site lock info holen und Button bestimmen
                    $db = mysqli_fetch_array(safe_query("SELECT * FROM settings"));
                    $lock = ($db['closed'] == '1') ? 'success' : 'danger';
                    $text_lock = ($db['closed'] == '1') ? $languageService->get('off_pagelock') : $languageService->get('on_pagelock');        

                    // Plugins einlesen
                    $modules = ['articles', 'about', 'history', 'calendar', 'blog', 'forum'];
                    $widget_alle = "<option value='blank'>" . $languageService->get('no_startpage') . "</option>\n";
                    $widget_alle .= "<option value='startpage'>Startpage</option>\n";

                    foreach ($modules as $modul) {

    $stmt = $_database->prepare(
        "SELECT modulname FROM settings_plugins WHERE modulname = ? LIMIT 1"
    );
    $stmt->bind_param('s', $modul);
    $stmt->execute();

    $res = $stmt->get_result();
    $dx  = $res ? $res->fetch_assoc() : null;

    $stmt->close();

    if ($dx && $dx['modulname'] === $modul) {
        $widget_alle .= "<option value='{$modul}'>" .
            ucfirst(str_replace("_", " ", $modul)) .
            "</option>\n";
    }
}


                    $widget_startpage = str_replace(
                        "value='" . $ds['startpage'] . "'",
                        "value='" . $ds['startpage'] . "' selected='selected'",
                        $widget_alle
                    );

                    // SEO-URLs Einstellung aus DB laden
                    $db = mysqli_fetch_array(safe_query("SELECT use_seo_urls FROM settings"));

                    // SEO-URLs aktiviert?
                    $seoEnabled = ($db['use_seo_urls'] == '1');

                    // Button-Klasse und Text je nach Status setzen
                    $btnClass = $seoEnabled ? 'success' : 'danger';
                    $btnText = $seoEnabled ? $languageService->get('seo_urls_enabled') : $languageService->get('seo_urls_disabled');

                    // Einheitliche Pfade
                    $rootDir    = dirname(__DIR__); // von /admin/ eine Ebene hoch -> Webroot
                    $stateDir   = $rootDir . '/var';
                    $updateFile = $stateDir . '/sitemap_last_update.txt';

                    if (!is_dir($stateDir)) { @mkdir($stateDir, 0775, true); }

                    // Anzeige
                    $lastUpdate = $languageService->get('no_sitemap');
                    if (is_readable($updateFile)) {
                        $lastUpdate = trim((string)file_get_contents($updateFile));
                    }
                    // Fehlerhandling

                    // stabiler Kopf
                    error_reporting(E_ALL);
                    ini_set('display_errors', '0');
                    ini_set('html_errors', '0');
                    ini_set('log_errors', '1');

                    // Gemeinsame Pfade
                    $rootDir     = dirname(__DIR__); // /pfad/zu/webroot (von /admin/ eine Ebene hoch)
                    $sitemapFile = $rootDir . '/sitemap.xml';
                    $stateDir    = $rootDir . '/var';
                    $updateFile  = $stateDir . '/sitemap_last_update.txt';

                    // Anzeige letzter Stand (vor dem Formular)
                    if (!is_dir($stateDir)) { @mkdir($stateDir, 0775, true); }
                    $lastUpdate = $languageService->get('no_sitemap');
                    if (is_readable($updateFile)) {
                        $lastUpdate = trim((string)file_get_contents($updateFile));
                    }
                    echo '<div class="row align-items-stretch">
                    <div class="col-md-6 d-flex">
                        <div class="card shadow-sm border-0 flex-fill h-100">

                        <!-- Card Header -->
                        <div class="card-header">
                            <div class="card-title">
                            <i class="bi bi-link-45deg"></i>
                            <span>' . htmlspecialchars($languageService->get('seo_urls_title')) . '</span>
                            <small class="small-muted">' . htmlspecialchars($languageService->get('seo_urls_description')) . '</small>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-4 d-flex flex-column gap-4">

                            <!-- SEO URLs -->
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                            <div>
                                <div class="fw-semibold">' . htmlspecialchars($languageService->get('seo_url_setting')) . '</div>
                                <div class="text-muted small">
                                    ' . htmlspecialchars($languageService->get('seo_urls_desc_short')) . '
                                </div>
                            </div>
                            <button type="submit" name="use_seo_urls_edit" class="btn btn-' . $btnClass . '">
                                ' . htmlspecialchars($btnText) . '
                            </button>
                            </div>

                            <hr class="my-0">

                            <!-- Sitemap -->
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                            <div>
                                <div class="fw-semibold">' . htmlspecialchars($languageService->get('sitemap_title')) . '</div>
                                <div class="text-muted small">
                                ' . htmlspecialchars($languageService->get('sitemap_description')) . '<br>
                                <span class="fw-semibold">' . htmlspecialchars($languageService->get('sitemap_last_update')) . ':</span>
                                ' . htmlspecialchars($lastUpdate) . '
                                </div>
                            </div>
                                <button type="submit" name="generate" class="btn btn-secondary">
                                    ' . htmlspecialchars($languageService->get('sitemap_regenerate')) . '
                                </button>
                            </div>

                            <hr class="my-0">

                            <!-- Meta SEO -->
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                            <div>
                                <div class="fw-semibold">' . htmlspecialchars($languageService->get('meta_description')) . '</div>
                                <div class="text-muted small">
                                    ' . htmlspecialchars($languageService->get('meta_managed_notice')) . '
                                </div>
                            </div>
                            <a href="admincenter.php?site=seo_meta"
                                class="btn btn-secondary"
                                title="' .  $languageService->get('meta_manage_btn_desc') . '">
                                ' .  $languageService->get('meta_manage_btn') . '
                            </a>
                            </div>

                        </div>
                        </div>
                    </div>

                        <!-- Rechte Spalte -->
                        <div class="col-md-6 d-flex">
                            <div class="d-flex flex-column w-100 h-100">
                              <div class="card shadow-sm border-0 shadow-sm mb-3 flex-shrink-0">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="bi bi-lock"></i>
                                        <span>' . htmlspecialchars($languageService->get('website_disable')) . '</span>
                                        <small class="small-muted">' . htmlspecialchars($languageService->get('disable_website_text')) . '</small>
                                    </div>
                                </div>

                                <div class="card-body p-4">
                                  <div class="row align-items-center mt-3">
                                    <div class="col-md-4 fw-semibold">' . htmlspecialchars($languageService->get('additional_options')) . ':</div>
                                    <div class="col-md-8">
                                      <a class="btn btn-' . $lock . '" href="admincenter.php?site=site_lock">' . htmlspecialchars($text_lock) . '</a>
                                    </div>
                                  </div>
                                </div>
                              </div>

                              <div class="card shadow-sm border-0 shadow-sm mb-0 flex-grow-1">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="bi bi-house"></i>
                                        <span>' . htmlspecialchars($languageService->get('startpage')) . '</span>
                                        <small class="small-muted">' . htmlspecialchars($languageService->get('startpage_description')) . '</small>
                                    </div>
                                </div>

                                <div class="card-body p-4">
                                  <div class="row align-items-center mt-3">
                                    <div class="col-md-4 fw-semibold">' . htmlspecialchars($languageService->get('startpage')) . ':</div>
                                    <div class="col-md-8">
                                      <select class="form-select form-select-sm" name="startpage">' . $widget_startpage . '</select>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>';
                    echo'<div class="mb-3">
                <div class="col-md-12"><br>
        <input type="hidden" name="captcha_hash" value="' . $hash . '">
            <button class="btn btn-primary" type="submit" name="submit">
                '.$languageService->get('save').'
            </button>
                </div>
            </div>
        </form>
</div>';
}
// Social Einstellungen
 elseif ($action == "social_setting") {

echo '<div class="d-flex flex-wrap align-items-start gap-3 mb-4">
        <a href="admincenter.php?site=settings" class="btn btn-secondary">
            ' . $languageService->get('settings') . '
        </a>
        <a href="admincenter.php?site=settings&action=social_setting" class="btn btn-secondary disabled" aria-current="page">
            ' . $languageService->get('social_settings') . '
        </a>
    </div>';

// Social-Media-Einstellungen aus der DB laden
$ds = mysqli_fetch_array(safe_query("SELECT * FROM settings_social_media"));

// Captcha-Objekt erzeugen und Transaktion starten
$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

// Formular-Karte starten
echo '<div class="card shadow-sm border-0 mb-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-share"></i>
                <span>' . $languageService->get("title_social_media") . '</span>
                <small class="small-muted">' . $languageService->get("social_media_desc") . '</small>
            </div>
        </div>

        <div class="card-body p-4">
                <p class="card-text mb-4">
                    ' . $languageService->get("social_media_intro") . '
                </p>
            <form action="admincenter.php?site=settings&action=social_setting" method="post">
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">';

$socialFields = [
    'discord'     => ['label' => 'Discord',     'icon' => 'bi-discord',    'placeholder' => 'https://discord.gg/...',        'type' => 'url',  'ribbon' => 'sm-discord'],
    'twitch'      => ['label' => 'Twitch',      'icon' => 'bi-twitch',     'placeholder' => 'https://twitch.tv/...',          'type' => 'url',  'ribbon' => 'sm-twitch'],
    'steam'       => ['label' => 'Steam',       'icon' => 'bi-steam',      'placeholder' => 'https://steamcommunity.com/...', 'type' => 'url',  'ribbon' => 'sm-steam'],
    'facebook'    => ['label' => 'Facebook',    'icon' => 'bi-facebook',   'placeholder' => 'https://facebook.com/...',       'type' => 'url',  'ribbon' => 'sm-facebook'],
    'twitter'     => ['label' => 'X / Twitter', 'icon' => 'bi-twitter-x',  'placeholder' => 'https://x.com/...',              'type' => 'url',  'ribbon' => 'sm-twitter'],
    'youtube'     => ['label' => 'YouTube',     'icon' => 'bi-youtube',    'placeholder' => 'https://youtube.com/...',        'type' => 'url',  'ribbon' => 'sm-youtube'],
    'instagram'   => ['label' => 'Instagram',   'icon' => 'bi-instagram',  'placeholder' => 'https://instagram.com/...',      'type' => 'url',  'ribbon' => 'sm-instagram'],
    'linkedin'    => ['label' => 'LinkedIn',    'icon' => 'bi-linkedin',   'placeholder' => 'https://linkedin.com/in/...',    'type' => 'url',  'ribbon' => 'sm-linkedin'],
    'rss'         => ['label' => 'RSS',         'icon' => 'bi-rss',        'placeholder' => 'https://example.com/feed.xml',   'type' => 'url',  'ribbon' => 'sm-rss'],
    'gametracker' => ['label' => 'Gametracker', 'icon' => 'bi-controller', 'placeholder' => 'IP:Port oder URL',               'type' => 'text', 'ribbon' => 'sm-gametracker'],
    'tiktok'      => ['label' => 'TikTok',      'icon' => 'bi-tiktok',     'placeholder' => 'https://www.tiktok.com/@username','type' => 'text', 'ribbon' => 'sm-tiktok'],
];

foreach ($socialFields as $field => $cfg) {

    // "-" wird bewusst wie leer behandelt
    $rawValue  = isset($ds[$field]) ? trim((string)$ds[$field]) : '';
    $value     = ($rawValue === '-') ? '' : $rawValue;

    $safeValue       = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $safePlaceholder = htmlspecialchars($cfg['placeholder'], ENT_QUOTES, 'UTF-8');

    $isActive = ($value !== '');

    // Farben
    $bgClass = $isActive ? 'bg-success-subtle border border-success-subtle' : 'bg-light border';
    $badge   = $isActive
        ? '<span class="badge text-bg-success">' . $languageService->get("social_media_active") . '</span>'
        : '<span class="badge bg-secondary">' . $languageService->get("social_media_empty") . '</span>';
    $href = $isActive ? $safeValue : '#';

    echo '
        <div class="col">
            <div class="card shadow-sm h-100 ' . $bgClass . '">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="sm-ribbon ' . $cfg['ribbon'] . '">
                                <i class="bi ' . $cfg['icon'] . '"></i>
                            </span>
                            <span class="fw-semibold small">' . $cfg['label'] . '</span>
                        </div>
                        ' . $badge . '
                    </div>

                    <div class="input-group">
                        <input
                            type="' . $cfg['type'] . '"
                            name="' . $field . '"
                            class="form-control"
                            value="' . $safeValue . '"
                            placeholder="' . $safePlaceholder . '"
                        >
                        ' . ($cfg['type'] === 'url' ? '
                        <a class="btn btn-secondary' . ($isActive ? '' : ' disabled') . '"
                           href="' . $href . '"
                           target="_blank"
                           title="' . $languageService->get('visit') . '"
                           rel="noopener">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>' : '') . '
                    </div>
                </div>
            </div>
        </div>';
}

// SVG Spezialfälle
$svgFields = [
    'flickr' => [
        'viewBox' => '0 0 24 24',
        'ribbon'  => 'sm-flickr',
        'path'    => '<circle cx="7.5" cy="12" r="3.5"></circle><circle cx="16.5" cy="12" r="3.5"></circle>',
        'placeholder' => 'https://flickr.com'
    ],
];

foreach ($svgFields as $field => $meta) {

    $rawValue = isset($ds[$field]) ? trim((string)$ds[$field]) : '';
    $value    = ($rawValue === '-') ? '' : $rawValue;

    $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $isActive  = ($value !== '');

    $bgClass = $isActive ? 'bg-success-subtle border border-success-subtle' : 'bg-light border';
    $badge   = $isActive
        ? '<span class="badge text-bg-success">' . $languageService->get("social_media_active") . '</span>'
        : '<span class="badge bg-secondary">' . $languageService->get("social_media_empty") . '</span>';

    echo '
        <div class="col">
            <div class="card shadow-sm h-100 ' . $bgClass . '">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="sm-ribbon ' . $meta['ribbon'] . '">
                                <svg xmlns="http://www.w3.org/2000/svg" height="16" viewBox="' . $meta['viewBox'] . '" aria-hidden="true">
                                    ' . $meta['path'] . '
                                </svg>
                            </span>
                            <span class="fw-semibold small">' . ucfirst($field) . '</span>
                        </div>
                        ' . $badge . '
                    </div>

                    <div class="input-group">
                        <input type="url" name="' . $field . '" class="form-control"
                               value="' . $safeValue . '" placeholder="' . htmlspecialchars($meta['placeholder'], ENT_QUOTES, 'UTF-8') . '">
                        <a class="btn btn-secondary' . ($isActive ? '' : ' disabled') . '"
                           href="' . ($isActive ? $safeValue : '#') . '" target="_blank" rel="noopener" title="' . $languageService->get('visit') . '">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>';
}

echo '</div>
        <div class="d-flex justify-content-start mt-4">
            <input type="hidden" name="captcha_hash" value="' . $hash . '">
            <input type="hidden" name="socialID" value="' . (int)$ds["socialID"] . '">
            <button class="btn btn-primary" type="submit" name="saveedit">
                ' . $languageService->get("save") . '
            </button>
        </div>
        </form>
    </div>
</div>';
}  
?>