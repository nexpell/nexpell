<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('settings', true);

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_settings');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}


if(isset($_POST['submit'])) {
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST[ 'captcha_hash' ])) {
        safe_query(
            "UPDATE
                settings
            SET
                hptitle='" . $_POST[ 'hptitle' ] . "',
                hpurl='" . $_POST[ 'url' ] . "',
                clanname='" . $_POST[ 'clanname' ] . "',
                clantag='" . $_POST[ 'clantag' ] . "',
                adminname='" . $_POST[ 'admname' ] . "',
                adminemail='" . $_POST[ 'admmail' ] . "',
                since ='" . $_POST[ 'since' ] . "',
                webkey ='" . $_POST['webkey'] . "',
                seckey ='" . $_POST['seckey'] . "',
                keywords='" . $_POST[ 'keywords' ] . "',
                webkey ='" . $_POST['webkey'] . "',
                seckey ='" . $_POST['seckey'] . "',
                startpage='"  . $_POST[ 'startpage' ] . "'"
        );
        echo '<div class="alert alert-success" role="alert">' . $languageService->get('updated_successfully') . '</div>';
        redirect("admincenter.php?site=settings", '', 2);
    } else {
        echo '<div class="alert alert-danger" role="alert">' . $languageService->get('transaction_invalid') . '</div>';
        redirect("admincenter.php?site=settings", '', 3);  
    }
}

if (isset($_POST["saveedit"])) {
        $CAPCLASS = new \nexpell\Captcha;
        if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {

            safe_query(
                "UPDATE settings_social_media SET
                    twitch = '" . $_POST[ 'twitch' ] . "',
                    facebook = '" . $_POST[ 'facebook' ] . "',
                    twitter = '" . $_POST[ 'twitter' ] . "',
                    youtube = '" . $_POST[ 'youtube' ] . "',
                    rss = '" . $_POST[ 'rss' ] . "',
                    vine = '" . $_POST[ 'vine' ] . "',
                    flickr = '" . $_POST[ 'flickr' ] . "',
                    linkedin = '" . $_POST[ 'linkedin' ] . "',
                    instagram = '" . $_POST[ 'instagram' ] . "',
                    gametracker = '" . $_POST[ 'gametracker' ] . "',
                    steam = '" . $_POST[ 'steam' ] . "',
                    discord = '" . $_POST[ 'discord' ] . "'"
            );
            echo '<div class="alert alert-success" role="alert">' . $languageService->get('updated_successfully') . '</div>';
            redirect("admincenter.php?site=settings&action=social_setting", '', 2);
        } else {
            echo '<div class="alert alert-danger" role="alert">' . $languageService->get('transaction_invalid') . '</div>';
            redirect("admincenter.php?site=settings&action=social_setting", '', 3);
        }

}


if (isset($_POST["use_seo_urls_edit"])) {
    $result = $_database->query("SELECT use_seo_urls FROM settings LIMIT 1");
    if ($result) {
        $row = $result->fetch_assoc();
        $currentValue = (int)$row['use_seo_urls'];
        $newValue = $currentValue === 1 ? 0 : 1;

        $stmt = $_database->prepare("UPDATE settings SET use_seo_urls = ? LIMIT 1");
        $stmt->bind_param('i', $newValue);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            if ($newValue === 1) {
                echo '<div class="alert alert-success" role="alert">' . $languageService->get('seo_urls_activated') . '</div>';
                redirect("admincenter.php?site=settings", '', 3);
            } else {
                echo '<div class="alert alert-danger" role="alert">' . $languageService->get('seo_urls_deactivated') . '</div>';
                redirect("admincenter.php?site=settings", '', 3);
            }
        } else {
            echo '<div class="alert alert-danger" role="alert">' . $languageService->get('transaction_invalid') . '</div>';
            redirect("admincenter.php?site=settings", '', 3);
        }
    } else {
        echo '<div class="alert alert-danger" role="alert">' . $languageService->get('transaction_invalid') . '</div>';
        redirect("admincenter.php?site=settings", '', 3);
    }

    #$_database->close();
}






#==== Allgemeine Einstellungen=============#
if ($action == "") { 

$settings = safe_query("SELECT * FROM settings");
$ds = mysqli_fetch_array($settings);

// Ausgabe starten
echo '
<div class="card">
    <div class="card-header"><i class="bi bi-house-gear"></i> ' . $languageService->get('settings') . '</div>
    <div class="card-body">
        <a href="admincenter.php?site=settings" class="btn btn-primary disabled" type="button">
            <i class="bi bi-gear"></i> ' . $languageService->get('settings') . '
        </a>
        <a href="admincenter.php?site=settings&action=social_setting" class="btn btn-primary" type="button">
            <i class="bi bi-gear-wide-connected"></i> ' . $languageService->get('social_settings') . '
        </a>';

$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

echo '
        
            <form method="post" action="">

               
                        <div class="row align-items-stretch">
                            <div class="col-md-6">
                                <div class="card border-primary mb-4 shadow-sm h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary mb-3">
                                            🌐 ' . $languageService->get('site_settings') .'
                                        </h5>
                                        <p class="card-text text-muted">
                                            ' . $languageService->get('website_info_description') . '
                                        </p>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">' . $languageService->get('page_url') . ':</label>
                                            <div class="col-md-8">
                                                <input class="form-control" type="url" name="url" value="' . htmlspecialchars($ds['hpurl']) . '">
                                            </div>
                                        </div>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">SEO & ' . $languageService->get('page_title') . ':</label>
                                            <div class="col-md-8">
                                                <input class="form-control" type="text" name="hptitle" value="' . htmlspecialchars($ds['hptitle']) . '">
                                            </div>
                                        </div>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">' . $languageService->get('meta_keywords') . ':</label>
                                            <div class="col-md-8">
                                                <textarea class="form-control" name="keywords" rows="5">' . htmlspecialchars($ds['keywords']) . '</textarea>
                                            </div>
                                        </div>

                                    </div>
                                </div>';



                           echo' </div> <!-- col-md-6 -->

                            <div class="col-md-6">
                                <div class="card border-success mb-4 shadow-sm h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-success mb-3">
                                            ⚙️ ' . $languageService->get('general_settings') . '
                                        </h5>
                                        <p class="card-text text-muted">
                                            ' . $languageService->get('project_info_description') . '
                                        </p>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">' . $languageService->get('clan_name') . ':</label>
                                            <div class="col-md-8">
                                                <input class="form-control" type="text" name="clanname" value="' . htmlspecialchars($ds['clanname']) . '">
                                            </div>
                                        </div>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">' . $languageService->get('since') . ':</label>
                                            <div class="col-md-8">
                                                <input class="form-control" type="text" name="since" value="' . htmlspecialchars($ds['since']) . '">
                                            </div>
                                        </div>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">' . $languageService->get('clan_tag') . ':</label>
                                            <div class="col-md-8">
                                                <input class="form-control" type="text" name="clantag" value="' . htmlspecialchars($ds['clantag']) . '">
                                            </div>
                                        </div>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">' . $languageService->get('admin_name') . ':</label>
                                            <div class="col-md-8">
                                                <input class="form-control" type="text" name="admname" value="' . htmlspecialchars($ds['adminname']) . '">
                                            </div>
                                        </div>

                                        <div class="mb-3 row">
                                            <label class="col-md-4 col-form-label fw-semibold">' . $languageService->get('admin_email') . ':</label>
                                            <div class="col-md-8">
                                                <input class="form-control" type="email" name="admmail" value="' . htmlspecialchars($ds['adminemail']) . '">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> <!-- col-md-6 -->
                        </div> <br> <!-- row -->';


                    // Site lock info holen und Button bestimmen
                    $db = mysqli_fetch_array(safe_query("SELECT * FROM settings"));
                    $lock = ($db['closed'] == '1') ? 'success' : 'danger';
                    $text_lock = ($db['closed'] == '1') ? $languageService->get('off_pagelock') : $languageService->get('on_pagelock');        


                    // Plugins einlesen
                    $modules = ['articles', 'about', 'history', 'calendar', 'blog', 'forum'];
                    $widget_alle = "<option value='blank'>" . $languageService->get('no_startpage') . "</option>\n";
                    $widget_alle .= "<option value='startpage'>Startpage</option>\n";

                    foreach ($modules as $modul) {
                        $dx = mysqli_fetch_array(safe_query("SELECT * FROM settings_plugins WHERE modulname='" . $modul . "'"));
                        if (@$dx['modulname'] == $modul) {
                            $widget_alle .= "<option value='{$modul}'>" . ucfirst(str_replace("_", " ", $modul)) . "</option>\n";
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

                    // Datum auslesen

                    // === Einheitliche Pfade (oben im Settings-Controller definieren) ===
                    $rootDir    = dirname(__DIR__);               // von /admin/ eine Ebene hoch -> Webroot
                    $stateDir   = $rootDir . '/var';
                    $updateFile = $stateDir . '/sitemap_last_update.txt';

                    if (!is_dir($stateDir)) { @mkdir($stateDir, 0775, true); }

                    // === ANZEIGE ===
                    $lastUpdate = 'Noch keine Sitemap generiert';
                    if (is_readable($updateFile)) {
                        $lastUpdate = trim((string)file_get_contents($updateFile));
                    }



                    // Fehlerhandling

// --- stabiler Kopf ---
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');

// Gemeinsame Pfade
$rootDir     = dirname(__DIR__);                 // /pfad/zu/webroot (von /admin/ eine Ebene hoch)
$sitemapFile = $rootDir . '/sitemap.xml';
$stateDir    = $rootDir . '/var';
$updateFile  = $stateDir . '/sitemap_last_update.txt';

// Anzeige letzter Stand (vor dem Formular)
if (!is_dir($stateDir)) { @mkdir($stateDir, 0775, true); }
$lastUpdate = 'Noch keine Sitemap generiert';
if (is_readable($updateFile)) {
    $lastUpdate = trim((string)file_get_contents($updateFile));
}

// ======= POST: Generieren, aber Request NICHT blockieren =======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {

    // 1) Sofortiger PRG-Redirect vorbereiten
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $target = $scheme . '://' . $host . '/admin/admincenter.php?site=settings';

    // 2) Antwort an den Browser abschließen (Redirect) — danach weiterarbeiten
    //    (a) Header setzen
    header('Cache-Control: no-store');
    header('Location: ' . $target, true, 303);
    header('Connection: close');

    //    (b) Kleine Body-Antwort, damit einige Server den Socket sauber schließen
    $out = "OK";
    $len = strlen($out);
    header('Content-Length: ' . $len);

    //    (c) Senden + Verbindung schließen
    echo $out;

    //    (d) Request wirklich beenden (PHP-FPM) oder bestmöglich flushen
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // ab hier ist der Browser schon weg
    } else {
        @ob_flush(); @flush();
    }

    // 3) Ab hier „Hintergrund“ im selben PHP-Prozess (Browser ist schon weg)
    ignore_user_abort(true);
    @set_time_limit(300);               // 5 Minuten
    @ini_set('memory_limit', '512M');   // großzügig für größere Sitemaps

    try {
        // (a) Verzeichnisse prüfen
        $sitemapDir = dirname($sitemapFile);
        if (!is_dir($sitemapDir)) { throw new RuntimeException("Sitemap-Verzeichnis fehlt: $sitemapDir"); }
        if (!is_writable($sitemapDir)) { throw new RuntimeException("Sitemap-Verzeichnis nicht schreibbar: $sitemapDir"); }
        if (!is_dir($stateDir)) { @mkdir($stateDir, 0775, true); }
        if (!is_writable($stateDir)) { throw new RuntimeException("Update-Verzeichnis nicht schreibbar: $stateDir"); }

        // (b) Sitemap generieren (Library-Modus!)
        if (!defined('SITEMAP_EMIT')) { define('SITEMAP_EMIT', false); }
        // Falls noch Output-Buffer offen ist, unkritisch – Sitemap liefert String:
        $xml = include $rootDir . '/sitemap.php';
        if (!is_string($xml) || $xml === '') {
            throw new RuntimeException('sitemap.php hat keinen XML-String zurückgegeben.');
        }
        if (strpos($xml, '<urlset') === false || strpos($xml, '</urlset>') === false) {
            throw new RuntimeException('Sitemap-XML unvollständig (kein <urlset> gefunden).');
        }

        // (c) Atomar schreiben + Rechte 0644
        $tmp = tempnam($sitemapDir, 'smap_');
        if ($tmp === false) { throw new RuntimeException('Tempfile fehlgeschlagen.'); }

        $oldUmask = umask(0022); // neue Dateien -> 644 möglich
        $bytes    = file_put_contents($tmp, $xml);
        if ($bytes === false) {
            if (isset($oldUmask)) umask($oldUmask);
            throw new RuntimeException('Sitemap konnte nicht geschrieben werden.');
        }
        if (!@rename($tmp, $sitemapFile)) {
            @unlink($tmp);
            if (isset($oldUmask)) umask($oldUmask);
            throw new RuntimeException('rename() auf sitemap.xml fehlgeschlagen.');
        }
        @chmod($sitemapFile, 0644);
        if (isset($oldUmask)) umask($oldUmask);

        // (d) Timestamp aktualisieren
        $stamp = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('d.m.Y H:i:s');
        @file_put_contents($updateFile, $stamp, LOCK_EX);

        // Optional: Erfolg ins Log
        error_log('[sitemap] erfolgreich generiert am ' . $stamp);

    } catch (Throwable $e) {
        // Fehler ins Serverlog
        error_log('Sitemap-Generate-Error: ' . $e->getMessage());
        // Hier KEINE Header/Antwort mehr senden (Browser ist weg)
    }

    // 4) Prozess sauber beenden
    exit;
}

// … danach dein normales Admin-HTML: Anzeige von $lastUpdate + Button name="generate"

                    // … hier folgt dein normales Admin-HTML (Formular etc.),
                    // z. B. Anzeige $lastUpdate und Button name="generate".


                    echo '
                        <div class="row d-flex align-items-stretch">
                          <!-- Linke Spalte -->
                          <div class="col-md-6 d-flex">
                            <div class="card border-danger mb-3 shadow-sm flex-fill">
                              <div class="card-body p-3">
                                <h5 class="text-danger mb-3">🌐 ' . htmlspecialchars($languageService->get('seo_urls_title')) . '</h5>
                                <p class="card-text text-muted">
                                  ' . htmlspecialchars($languageService->get('seo_urls_description')) . '
                                </p>

                                <div class="row align-items-center mt-3">
                                  <div class="col-md-5 fw-semibold">' . htmlspecialchars($languageService->get('seo_url_setting')) . ':</div>
                                  <div class="col-md-7">
                                    <!--<form method="post" action="">-->
                                      <button type="submit" name="use_seo_urls_edit" class="btn btn-' . $btnClass . '">
                                        ' . htmlspecialchars($btnText) . '
                                      </button>
                                    <!--</form>-->
                                  </div>
                                </div>

                                <hr>

                                <h5 class="text-danger mt-4">📄 ' . htmlspecialchars($languageService->get('sitemap_title')) . '</h5>
                                <p class="card-text text-muted mb-2">
                                  ' . htmlspecialchars($languageService->get('sitemap_description')) . '
                                </p>
                                <div class="row align-items-center mt-3">
                                  <div class="col-md-5 fw-semibold">' . htmlspecialchars($languageService->get('sitemap_last_update')) . ': <strong>' . htmlspecialchars($lastUpdate) . '</strong></div>
                                  <div class="col-md-7">
                                    <!--<form method="post">-->
                                      <button type="submit" name="generate" class="btn btn-info">' . htmlspecialchars($languageService->get('sitemap_regenerate')) . '</button>
                                    <!--</form>-->
                                  </div>
                                </div>

                                <hr>


                                <h5 class="text-danger mt-4">' . $languageService->get('meta_description') . ':</h5>
                                <p class="card-text text-muted mb-2">
                                    Die Meta-Beschreibung kann hier nicht direkt bearbeitet werden. Um Titel und Beschreibungen für diese und alle anderen Seiten zu verwalten, nutze bitte den untenstehenden Button. Auf der SEO-Meta-Verwaltungsseite kannst du alle SEO-relevanten Texte zentral anpassen, um die Auffindbarkeit deiner Website in Suchmaschinen zu verbessern und die Darstellung in Suchergebnissen zu optimieren.
                                </p>
                                <div class="row align-items-center mt-3">
                                    <div class="col-md-5 fw-semibold">' . htmlspecialchars($languageService->get('seo_url_setting')) . ':</div>
                                        <div class="col-md-7">
                                            <a href="admincenter.php?site=seo_meta" class="btn btn-primary mt-2" role="button" title="SEO Meta Einstellungen bearbeiten">
                                                SEO Meta Einstellungen bearbeiten
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rechte Spalte -->
                        <div class="col-md-6 d-flex">
                            <div class="d-flex flex-column w-100">
                              <div class="card border-warning shadow-sm mb-3">
                                <div class="card-body p-3">
                                  <h5 class="card-title text-warning mb-3">🔒 ' . htmlspecialchars($languageService->get('website_disable')) . '</h5>
                                  <p class="card-text text-muted" style="font-size: 0.9rem;">
                                    ' . htmlspecialchars($languageService->get('disable_website_text')) . '
                                  </p>
                                  <div class="row align-items-center mt-3">
                                    <div class="col-md-4 fw-semibold">' . htmlspecialchars($languageService->get('additional_options')) . ':</div>
                                    <div class="col-md-8">
                                      <a class="btn btn-' . $lock . '" href="admincenter.php?site=site_lock">' . htmlspecialchars($text_lock) . '</a>
                                    </div>
                                  </div>
                                </div>
                              </div>

                              <div class="card border-info shadow-sm mb-3">
                                <div class="card-body p-3">
                                  <h5 class="card-title text-info mb-3">🏠 ' . htmlspecialchars($languageService->get('startpage')) . '</h5>
                                  <p class="card-text text-muted" style="font-size: 0.9rem;">
                                    ' . htmlspecialchars($languageService->get('startpage_description')) . '
                                  </p>
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


                    echo' <div class="card border-secondary mb-4 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-secondary mb-3">🖼️ ' . $languageService->get('reCaptcha') . '</h5>
                                <div class="row align-items-center">
                                    <!-- Beschreibung -->
                                    <div class="col-md-4 mb-3">
                                        <p class="text-muted">' . $languageService->get('recaptcha_description') . '</p>
                                    </div>

                                    <!-- Bild -->
                                    <div class="col-md-4 mb-3 text-center">
                                        <img src="/admin/images/recapcha.png" class="img-fluid rounded shadow-sm" style="max-height:150px;" alt="Google reCAPTCHA">
                                    </div>

                                    <!-- Eingabefelder -->
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="webkey" class="form-label fw-semibold">' . $languageService->get('web-key') . ':</label>
                                            <input id="webkey" class="form-control" type="text" name="webkey" value="' . htmlspecialchars($ds['webkey']) . '">
                                        </div>
                                        <div class="mb-3">
                                            <label for="seckey" class="form-label fw-semibold">' . $languageService->get('secret-key') . ':</label>
                                            <input id="seckey" class="form-control" type="text" name="seckey" value="' . htmlspecialchars($ds['seckey']) . '">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                

            <div class="mb-3 row">
                <div class="col-md-12"><br>
                    
                    
              
        <input type="hidden" name="captcha_hash" value="' . $hash . '">
    <button class="btn btn-warning" type="submit" name="submit">
        <i class="bi bi-box-arrow-down"></i> '.$languageService->get('update').'
    </button>
</form>

                </div>
            </div>
           

            
       
    </div>
</div>
';

}


#==== Social Einstellungen=============#

#==== Social Einstellungen=============#

 elseif ($action == "social_setting") {

echo '<div class="card">
        <div class="card-header">
            <i class="bi bi-house-gear"></i> ' . $languageService->get('social_settings') . '
        </div>
        <div class="card-body">
            <a href="admincenter.php?site=settings" class="btn btn-primary">
                <i class="bi bi-gear"></i> ' . $languageService->get('settings') . '
            </a>
            <a href="admincenter.php?site=settings&action=social_setting" class="btn btn-primary disabled">
               <i class="bi bi-gear-wide-connected"></i> ' . $languageService->get('social_settings') . '
            </a>';

// Social-Media-Einstellungen aus der DB laden
$ds = mysqli_fetch_array(safe_query("SELECT * FROM settings_social_media"));

// Captcha-Objekt erzeugen und Transaktion starten
$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

// Formular-Karte starten
echo '<div class="card border-secondary mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title text-secondary mb-3">
                💡 ' . $languageService->get('title_social_media') . '
            </h5>
            <div class="row align-items-center">

                <!-- Beschreibung -->
                <div class="col-md-4 mb-3">
                    <p class="text-muted">
                        Dieses Formular ermöglicht die zentrale Verwaltung aller Social-Media-Links der Website. Admins können Plattformen wie Facebook, Twitter, Discord & Co. über passende Icons schnell erkennen und die URLs bequem aktualisieren. Ideal für eine einheitliche Darstellung im Frontend.
                    </p>
                </div>

                <form action="admincenter.php?site=settings&action=social_setting" method="post" class="row g-3">';

$social_fields = [
    'gametracker' => 'bi-controller',
    'discord'     => 'bi-discord',
    'twitch'      => 'bi-twitch',
    'steam'       => 'bi-steam',
    'facebook'    => 'bi-facebook',
    'twitter'     => 'bi-twitter-x',
    'youtube'     => 'bi-youtube',
    'rss'         => 'bi-rss',
    'linkedin'    => 'bi-linkedin',
    'instagram'   => 'bi-instagram',
];

// Social-Media-Felder mit Icons und Eingabefeldern
foreach ($social_fields as $field => $icon) {
    $label = ucfirst($field);
    echo '<div class="col-md-6">
            <label class="form-label fw-semibold">
                <i class="bi ' . $icon . '"></i> ' . $label . ':
            </label>
            <input type="text" name="' . $field . '" class="form-control" value="' . htmlspecialchars($ds[$field]) . '">
          </div>';
}

// Spezialfälle: Vine & Flickr mit eigenen SVG-Icons
echo '<div class="col-md-6">
        <label class="form-label fw-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" height="16" viewBox="0 0 384 512"><path fill="currentColor" d="..."/></svg> Vine:
        </label>
        <input type="text" name="vine" class="form-control" value="' . htmlspecialchars($ds['vine']) . '">
      </div>';

echo '<div class="col-md-6">
        <label class="form-label fw-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" height="16" viewBox="0 0 448 512"><path fill="currentColor" d="..."/></svg> Flickr:
        </label>
        <input type="text" name="flickr" class="form-control" value="' . htmlspecialchars($ds['flickr']) . '">
      </div>';

echo '<div class="col-12 text-end mt-3">
        <input type="hidden" name="captcha_hash" value="' . $hash . '" />
        <input type="hidden" name="socialID" value="' . (int)$ds['socialID'] . '" />
        <button class="btn btn-warning" type="submit" name="saveedit">
            <i class="bi bi-save"></i> ' . $languageService->get('update') . '
        </button>
      </div>
    </form>

    </div> <!-- .row -->
  </div> <!-- .card-body -->
</div> <!-- .card -->
</div> <!-- .card-body main -->
</div> <!-- .main card -->';

}  
?>