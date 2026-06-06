<?php
// Schutz gegen Direktaufruf: Dieses Modul ist als Include für admincenter.php gedacht
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'webside_navigation.php') {
    header('Location: admincenter.php?site=webside_navigation', true, 302);
    exit;
}

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_webside_navigation');


// AJAX Sortierung verarbeiten
if (isset($_GET['ajax_sort']) && isset($_POST['new_order_data'])) {

    $orderData = json_decode($_POST['new_order_data'], true);

    if (is_array($orderData)) {

        // Kategorien
        if (!empty($orderData['categories'])) {
            foreach ($orderData['categories'] as $cat) {
                safe_query("
                    UPDATE navigation_website_main
                    SET sort = " . (int)$cat['sort'] . "
                    WHERE mnavID = " . (int)$cat['id'] . "
                ");
            }
        }

        // Links (SORT + CATEGORY!)
        if (!empty($orderData['links'])) {
            foreach ($orderData['links'] as $link) {
                safe_query("
                    UPDATE navigation_website_sub
                    SET
                        sort  = " . (int)$link['sort'] . ",
                        mnavID = " . (int)$link['catID'] . "
                    WHERE snavID = " . (int)$link['id'] . "
                ");
            }
        }
    }

    exit;
}












// =========================================
// 🔴 EINZELNEN SUB-LINK LÖSCHEN (Website)
// =========================================
if (isset($_GET['delete'])) {
    $snavID = (int)($_GET['snavID'] ?? 0);
    $CAPCLASS = new \nexpell\Captcha;

    if (!$CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'] ?? '')) {
        nx_redirect('admincenter.php?site=webside_navigation', 'warning', 'alert_transaction_invalid', false);
    }

    // 1. Sub-Link aus der Haupttabelle löschen
    safe_query("DELETE FROM navigation_website_sub WHERE snavID = '$snavID'");

    // 2. Alle Sprach-Einträge für diesen Sub-Link löschen
    // Hinweis: Prüfe, ob dein Key 'nav_sub_ID' oder 'nav_sub_link_ID' heißt
    safe_query("DELETE FROM navigation_website_lang WHERE content_key = 'nav_sub_$snavID'");

    nx_audit_delete('webside_navigation', (string)$snavID, 'Sub-Link #'.$snavID, 'admincenter.php?site=webside_navigation');

    nx_redirect('admincenter.php?site=webside_navigation', 'success', 'alert_deleted', false);
} 

// =========================================
// 🔴 HAUPT-KATEGORIE (UND ALLE SUBS) LÖSCHEN
// =========================================
elseif (isset($_GET['delcat'])) {
    $mnavID = (int)($_GET['mnavID'] ?? 0);
    $CAPCLASS = new \nexpell\Captcha;

    if (!$CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'] ?? '')) {
        nx_redirect('admincenter.php?site=webside_navigation', 'warning', 'alert_transaction_invalid', false);
    }

    // 1. Alle Sub-Links finden, die zu dieser Hauptkategorie gehören
    $resSubs = safe_query("SELECT snavID FROM navigation_website_sub WHERE mnavID = '$mnavID'");

    while ($row = mysqli_fetch_assoc($resSubs)) {
        $sID = (int)$row['snavID'];
        // Sprachdaten der untergeordneten Sub-Links löschen
        safe_query("DELETE FROM navigation_website_lang WHERE content_key = 'nav_sub_$sID'");
    }

    // 2. Alle Sub-Links dieser Kategorie löschen
    safe_query("DELETE FROM navigation_website_sub WHERE mnavID = '$mnavID'");

    // 3. Sprachdaten der Haupt-Kategorie löschen
    safe_query("DELETE FROM navigation_website_lang WHERE content_key = 'nav_main_$mnavID'");

    // 4. Haupt-Eintrag aus der Tabelle löschen
    safe_query("DELETE FROM navigation_website_main WHERE mnavID = '$mnavID'");

    nx_audit_remove('webside_navigation', (string)$mnavID, 'Main-Nav #'.$mnavID, 'admincenter.php?site=webside_navigation');

    nx_redirect('admincenter.php?site=webside_navigation', 'success', 'alert_deleted', false);
}












elseif (isset($_POST['save']) || isset($_POST['saveedit'])) {

    $CAPCLASS = new \nexpell\Captcha;
    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_redirect('admincenter.php?site=webside_navigation', 'warning', 'alert_transaction_invalid', false);
    }

    $snavID   = isset($_POST['snavID']) ? (int)$_POST['snavID'] : 0;
    $mnavID   = (int)($_POST['mnavID'] ?? 0);
    $url      = trim((string)($_POST['link'] ?? ''));
    $modul    = trim((string)($_POST['modulname'] ?? ''));
    $isUpdate = ($snavID > 0);
    $nameArray = $_POST['name'] ?? ($_POST['title_lang'] ?? []);
    
    // Fallback-Name für die Haupttabelle (z.B. Deutsch)
    $defaultName = trim((string)($nameArray['de'] ?? reset($nameArray) ?? ''));

    if ($isUpdate) {
        // UPDATE bestehender Link
        $stmt = $_database->prepare("
            UPDATE navigation_website_sub 
            SET mnavID = ?, url = ?, modulname = ?, name = ?
            WHERE snavID = ?
        ");
        $stmt->bind_param('isssi', $mnavID, $url, $modul, $defaultName, $snavID);
        $stmt->execute();
        $stmt->close();
    } else {
        // INSERT neuer Link
        $res  = safe_query("SELECT COUNT(*) AS cnt FROM navigation_website_sub WHERE mnavID = $mnavID");
        $row  = mysqli_fetch_assoc($res);
        $sort = ((int)$row['cnt']) + 1;

        $stmt = $_database->prepare("
            INSERT INTO navigation_website_sub (mnavID, name, url, modulname, sort, indropdown)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $indropdown = 1;
        $stmt->bind_param('isssii', $mnavID, $defaultName, $url, $modul, $sort, $indropdown);

        $stmt->execute();
        $snavID = (int)$stmt->insert_id;
        $stmt->close();
    }

    // SPRACHEN SPEICHERN
    foreach (['de','en','it'] as $lang) {
        if (array_key_exists($lang, $nameArray)) {
            #$text = escape($GLOBALS['sql_db'], $_POST['name'][$lang]);
            $text = trim((string)($nameArray[$lang] ?? ''));
            $content_key = "nav_sub_" . $snavID;

            safe_query("
                INSERT INTO navigation_website_lang (content_key, language, content)
                VALUES ('$content_key', '$lang', '$text')
                ON DUPLICATE KEY UPDATE content = '$text'
            ");
        }
    }

    $auditAction = $isUpdate ? 'update' : 'create';
    nx_audit_create('webside_navigation', (string)$snavID, "Action: $auditAction | URL: $url", 'admincenter.php?site=webside_navigation');
    
    nx_redirect('admincenter.php?site=webside_navigation', 'success', 'alert_saved', false);
}

elseif (isset($_POST['savecat']) || isset($_POST['saveeditcat'])) {

    $CAPCLASS = new \nexpell\Captcha;
    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_redirect(
            'admincenter.php?site=webside_navigation',
            'danger',
            'alert_transaction_invalid',
            false
        );
    }

    /* ===============================
       BASISDATEN
    =============================== */
    $mnavID     = (int)($_POST['mnavID'] ?? 0);
    $isUpdate   = ($mnavID > 0);

    $url        = trim((string)($_POST['link'] ?? ''));
    $windows    = (int)($_POST['windows'] ?? 0);
    $isdropdown = isset($_POST['isdropdown']) ? 1 : 0;
    $nameArray  = $_POST['name'] ?? [];

    // ✅ MODULNAME
    $modulname = strtolower(trim((string)($_POST['modulname'] ?? '')));
    $modulname = preg_replace('/[^a-z0-9_]/', '', $modulname);

    if (empty($nameArray['de']) || $modulname === '') {
        nx_alert('warning', 'alert_missing_required', false);
        return;
    }

    /* ===============================
       MAIN-NAV SPEICHERN
    =============================== */
    if ($isUpdate) {

        $stmt = $_database->prepare("
            UPDATE navigation_website_main
            SET url = ?, windows = ?, isdropdown = ?, modulname = ?
            WHERE mnavID = ?
        ");
        $stmt->bind_param(
            'siisi',
            $url,
            $windows,
            $isdropdown,
            $modulname,
            $mnavID
        );
        $stmt->execute();
        $stmt->close();

    } else {

        $stmt = $_database->prepare("
            INSERT INTO navigation_website_main
                (url, windows, isdropdown, sort, modulname)
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmt->bind_param(
            'siis',
            $url,
            $windows,
            $isdropdown,
            $modulname
        );
        $stmt->execute();
        $mnavID = (int)$stmt->insert_id;
        $stmt->close();
    }

    /* ===============================
       MULTILANG NAME SPEICHERN
    =============================== */
    $stmtLang = $_database->prepare("
    INSERT INTO navigation_website_lang
            (content_key, language, content, updated_at)
        VALUES
            (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            updated_at = NOW()
    ");

    $content_key = "nav_main_" . $mnavID;

    foreach (['de','en','it'] as $lang) {
        $text = trim((string)($nameArray[$lang] ?? ''));

        $stmtLang->bind_param("sss", $content_key, $lang, $text);
        $stmtLang->execute();
    }

    $stmtLang->close();


    /* ===============================
       AUDIT
    =============================== */
    $auditName = trim(
        $nameArray['de']
        ?? $nameArray['en']
        ?? $nameArray['it']
        ?? ('ID ' . $mnavID)
    );

    if ($isUpdate) {
        nx_audit_update(
            'webside_navigation',
            (string)$mnavID,
            true,
            $auditName,
            'admincenter.php?site=webside_navigation'
        );
    } else {
        nx_audit_create(
            'webside_navigation',
            (string)$mnavID,
            $auditName,
            'admincenter.php?site=webside_navigation'
        );
    }

    nx_redirect(
        'admincenter.php?site=webside_navigation',
        'success',
        'alert_saved',
        false
    );
}

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

if ($action == "add") {
    $currentLang = strtolower($languageService->detectLanguage());

    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get('dashnavi') . '</span>
                <small class="text-muted ms-2">' . $languageService->get('add_link') . '</small>
            </div>
            <div class="ms-auto btn-group" id="lang-switch">';

    /* ===============================
       SPRACHEN LADEN
    =============================== */
    $languages = [];
    $resLang = safe_query("SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $iso = strtolower($row['iso_639_1']);
        $languages[$iso] = $row['name_de'];
        
        // Buttons (Farben: btn-primary / btn-secondary)
        $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
        echo '<button type="button" class="btn ' . $activeClass . '" data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
    }

    echo '    </div>
        </div>

        <div class="card-body p-4">';

    /* ===============================
       KATEGORIEN FÜR JS VORBEREITEN
    =============================== */
    $catData = [];
    $resCat = safe_query("
        SELECT m.mnavID, l.language, l.content 
        FROM navigation_website_main m
        JOIN navigation_website_lang l ON l.content_key = CONCAT('nav_main_', m.mnavID)
    ");
    while ($c = mysqli_fetch_assoc($resCat)) {
        $catData[$c['mnavID']][strtolower($c['language'])] = $c['content'];
    }
    $catJson = json_encode($catData);

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();

    echo '<form method="post" action="admincenter.php?site=webside_navigation" id="navForm">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">' . $languageService->get('category') . '</label>
                    <select class="form-select" name="mnavID" id="category_select">';
                    foreach ($catData as $mID => $langs) {
                        $display = $langs[$currentLang] ?? ($langs['de'] ?? '---');
                        echo '<option value="' . $mID . '">' . htmlspecialchars($display) . '</option>';
                    }
    echo '          </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">' . $languageService->get('url') . '</label>
                    <input class="form-control" type="text" name="link" placeholder="z.B. index.php?site=news">
                </div>

                <div class="col-md-4">
                    <label class="form-label">' . $languageService->get('modulname') . '</label>
                    <input class="form-control" type="text" name="module" placeholder="z.B. news">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold" id="title-label">
                    ' . $languageService->get('navigation_name') . '
                </label>
                <input type="text" class="form-control form-control-lg" id="title_main" placeholder="Link-Namen eingeben...">';
                
                // Versteckte Felder für alle Sprachen (Initial leer bei "add")
                foreach ($languages as $iso => $label) {
                    echo '<input type="hidden" name="name[' . $iso . ']" id="title_hidden_' . $iso . '" value="">';
                }
    echo '  </div>

            <div class="pt-3">
                <input type="hidden" name="captcha_hash" value="' . $CAPCLASS->getHash() . '">
                <button class="btn btn-primary px-4" type="submit" name="save">
                    ' . $languageService->get('save') . '
                </button>
                <a href="admincenter.php?site=webside_navigation" class="btn btn-link text-muted">Abbrechen</a>
            </div>
        </form>
    </div></div>';
}

elseif ($action === "edit") {
    $currentLang = strtolower($languageService->detectLanguage());
    $snavID = (int)($_GET['snavID'] ?? 0);

    // Basis-Daten laden
    $ds = mysqli_fetch_assoc(safe_query("SELECT * FROM navigation_website_sub WHERE snavID = $snavID"));
    if (!$ds) nx_redirect('admincenter.php?site=webside_navigation', 'danger', 'error_link_id', false);

    // Sprachen laden
    $languages = [];
    $resLang = safe_query("SELECT iso_639_1 FROM settings_languages WHERE active = 1 ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[] = strtolower($row['iso_639_1']);
    }

    // Titel laden
    $titles = [];
    $resTitle = safe_query("SELECT language, content FROM navigation_website_lang WHERE content_key = 'nav_sub_$snavID'");
    while ($row = mysqli_fetch_assoc($resTitle)) {
        $titles[strtolower($row['language'])] = $row['content'];
    }

    // Kategorien für JS (Dropdown-Übersetzung)
    $catData = [];
    $resCat = safe_query("SELECT m.mnavID, l.language, l.content 
                          FROM navigation_website_main m
                          JOIN navigation_website_lang l ON l.content_key = CONCAT('nav_main_', m.mnavID)");
    while ($c = mysqli_fetch_assoc($resCat)) {
        $catData[$c['mnavID']][strtolower($c['language'])] = $c['content'];
    }
    $catJson = json_encode($catData);

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();

    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get('dashnavi') . '</span>
                <small class="text-muted ms-2">' . $languageService->get('edit_link') . '</small>
            </div>
            <div class="ms-auto btn-group" id="lang-switch">';
            
            // BUTTONS mit btn-primary / btn-secondary
            foreach ($languages as $iso) {
                $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                echo '<button type="button" class="btn ' . $activeClass . '" data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
            }
            
    echo '</div>
        </div>

        <div class="card-body">
            <form method="post" action="admincenter.php?site=webside_navigation" id="navForm">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('category') . '</label>
                        <select class="form-select" name="mnavID" id="category_select">';
                        foreach ($catData as $mID => $langs) {
                            $sel = ($mID == $ds['mnavID']) ? ' selected' : '';
                            $display = $langs[$currentLang] ?? ($langs['de'] ?? '---');
                            echo '<option value="' . $mID . '"' . $sel . '>' . htmlspecialchars($display) . '</option>';
                        }
            echo '      </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('url') . '</label>
                        <input class="form-control" type="text" name="link" value="' . htmlspecialchars($ds['url']) . '">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('modulname') . '</label>
                        <input class="form-control" type="text" name="modulname" value="' . htmlspecialchars($ds['modulname']) . '">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('navigation_name') . '</label>
                    <input type="text" class="form-control form-control-lg" id="title_input" value="' . htmlspecialchars($titles[$currentLang] ?? '') . '">';
                    
                    foreach ($languages as $iso) {
                        echo '<input type="hidden" name="title_lang[' . $iso . ']" id="title_hidden_' . $iso . '" value="' . htmlspecialchars($titles[$iso] ?? '') . '">';
                    }
            echo '</div>

                <input type="hidden" name="captcha_hash" value="' . $CAPCLASS->getHash() . '">
                <input type="hidden" name="snavID" value="' . $snavID . '">
                
                <div class="pt-3">
                    <button class="btn btn-warning px-4" type="submit" name="saveedit">
                        ' . $languageService->get('save') . '
                    </button>
                    <a href="admincenter.php?site=webside_navigation" class="btn btn-link text-muted">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>';
}

elseif ($action == "addcat") {
    $currentLang = strtolower($languageService->detectLanguage());

    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get('dashnavi') . '</span>
                <small class="text-muted ms-2">' . $languageService->get('add_category') . '</small>
            </div>
            <div class="ms-auto btn-group" id="lang-switch">';

    // Sprachen laden
    $languages = [];
    $resLang = safe_query("SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $iso = strtolower($row['iso_639_1']);
        $languages[$iso] = $row['name_de'];
        
        // Buttons generieren (Farben wie im Editor-Beispiel)
        $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
        echo '<button type="button" class="btn ' . $activeClass . '" data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
    }

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();

    echo '    </div>
        </div>

        <div class="card-body p-4">
            <form method="post" action="admincenter.php?site=webside_navigation" id="catForm">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="text-uppercase text-muted fw-semibold mb-3 small">Einstellungen</div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">
                                ' . $languageService->get('modulename') . '
                            </label>
                            <input class="form-control" type="text" name="modulname" placeholder="z. B. forum, articles, downloads" required>
                            <div class="form-text text-muted">
                                Interner Bezeichner der Kategorie (keine Leerzeichen)
                            </div>
                        </div>

                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('url') . '</label>
                            <input class="form-control" type="text" name="link" placeholder="index.php?site=...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Target</label>
                            <select name="windows" class="form-select">
                                <option value="1">' . $languageService->get('_self') . '</option>
                                <option value="0">' . $languageService->get('_blank') . '</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="isdropdown" id="isdropdown" value="1" checked />
                                <label class="form-check-label" for="isdropdown">' . $languageService->get('dropdown') . '</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        

                        <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            ' . $languageService->get('category_name') . '
                        </label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="name_main" 
                                   placeholder="Name der Kategorie eingeben...">
                        </div>';

                        // Versteckte Felder für die verschiedenen Sprachen
                        foreach ($languages as $iso => $label) {
                            echo '<input type="hidden" name="name[' . $iso . ']" id="name_hidden_' . $iso . '" value="">';
                        }

    

    echo '          </div>
                </div>

                <div class="d-flex justify-content-start gap-2 mt-4 pt-3">
                    <input type="hidden" name="captcha_hash" value="' . $CAPCLASS->getHash() . '" />
                    <button class="btn btn-primary px-4" type="submit" name="savecat">
                        ' . $languageService->get('save') . '
                    </button>
                    <a href="admincenter.php?site=webside_navigation" class="btn btn-link text-muted">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>';
}

elseif ($action == "editcat") {
    $currentLang = strtolower($languageService->detectLanguage());

    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get('dashnavi') . '</span>
                <small class="text-muted ms-2">' . $languageService->get('edit_category') . '</small>
            </div>';

    /* ===============================
       BASIS DATEN
    =============================== */
    $mnavID = (int)$_GET['mnavID'];
    $ds = mysqli_fetch_array(safe_query("SELECT * FROM navigation_website_main WHERE mnavID='$mnavID'"));

    /* ===============================
       SPRACHEN LADEN
    =============================== */
    $languages = [];
    $resLang = safe_query("SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[strtolower($row['iso_639_1'])] = $row['name_de'];
    }

    /* ===============================
       TITEL AUS Multilang-Tabelle
    =============================== */
    $titles = [];
    $resTitle = safe_query("SELECT language, content FROM navigation_website_lang WHERE content_key = 'nav_main_$mnavID'");
    while ($row = mysqli_fetch_assoc($resTitle)) {
        $titles[strtolower($row['language'])] = $row['content'];
    }

    // Sprach-Umschalter in der Header-Leiste (Farben: btn-primary / btn-secondary)
    echo '<div class="ms-auto btn-group" id="lang-switch">';
    foreach ($languages as $iso => $label) {
        $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
        echo '<button type="button" class="btn ' . $activeClass . '" data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
    }
    echo '</div></div>';

    /* ===============================
       FORMULAR START
    =============================== */
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    $isdropdown = ($ds['isdropdown'] == 1) ? 'checked' : '';
    $sel_self = ($ds['windows'] == "1") ? 'selected' : '';
    $sel_blank = ($ds['windows'] == "0") ? 'selected' : '';

    echo '<div class="card-body p-4">
        <form method="post" action="admincenter.php?site=webside_navigation" id="catForm">
            <input type="hidden" name="mnavID" value="' . $mnavID . '" />
            <input type="hidden" name="captcha_hash" value="' . $hash . '" />

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="text-uppercase text-muted small fw-bold mb-3">URL & Target</div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            ' . $languageService->get('modulename') . '
                        </label>
                        <input class="form-control" type="text" name="modulname" value="' . htmlspecialchars($ds['modulname']) . '" required>
                        <div class="form-text text-muted">
                            Interner Bezeichner der Kategorie (keine Leerzeichen)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('url') . '</label>
                        <input class="form-control" type="text" name="link" value="' . htmlspecialchars((string)$ds['url']) . '">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Target</label>
                        <select name="windows" class="form-select">
                            <option value="1" ' . $sel_self . '>' . $languageService->get('_self') . '</option>
                            <option value="0" ' . $sel_blank . '>' . $languageService->get('_blank') . '</option>
                        </select>
                    </div>

                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="isdropdown" value="1" id="isdrop" ' . $isdropdown . '>
                        <label class="form-check-label" for="isdrop">' . $languageService->get('dropdown') . '</label>
                    </div>
                </div>

                <div class="col-lg-7">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            ' . $languageService->get('category_name') . '
                        </label>
                        <input type="text" class="form-control form-control-lg" id="title_main" 
                               value="' . htmlspecialchars($titles[$currentLang] ?? '', ENT_QUOTES) . '" placeholder="Kategoriename...">
                    </div>';

                    // Versteckte Felder für alle Sprachen
                    foreach ($languages as $iso => $label) {
                        echo '<input type="hidden" name="name[' . $iso . ']" id="title_hidden_' . $iso . '" 
                                     value="' . htmlspecialchars($titles[$iso] ?? '', ENT_QUOTES) . '">';
                    }

            echo '</div>
            </div>

            <div class="mt-4 pt-3">
                <button class="btn btn-warning px-4" type="submit" name="saveeditcat">
                    ' . $languageService->get('save') . '
                </button>
                <a href="admincenter.php?site=webside_navigation" class="btn btn-link text-muted">Abbrechen</a>
            </div>
        </form>
    </div>
</div>';
}

else {
        /* ===============================
           ÜBERSICHT MIT DIV-STRUKTUR (CLEAN DRAG)
        =============================== */
        echo '<div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title mb-0">
                    <i class="bi bi-menu-app me-2"></i>
                    <span>' . $languageService->get('dashnavi') . '</span>
                    <small id="save-status" class="ms-2 fw-normal text-muted transition-all" style="opacity:0;">
                        <i class="bi bi-check-circle-fill text-success"></i> Gespeichert
                    </small>
                </div>
                <div>
                    <a class="btn btn-secondary" href="admincenter.php?site=webside_navigation&amp;action=addcat">
                        ' . $languageService->get('add_category') . '
                    </a>
                    <a class="btn btn-secondary ms-2" href="admincenter.php?site=webside_navigation&amp;action=add">
                        ' . $languageService->get('add_link') . '
                    </a>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="d-none d-md-flex bg-light border-bottom fw-bold p-2 small text-muted">
                    <div style="width:50px"></div>
                    <div style="width:35%">' . $languageService->get('name') . '</div>
                    <div style="width:40%">Link / URL</div>
                    <div style="width:20%">' . $languageService->get('actions') . '</div>
                </div>

                <div id="main-sort-container">';

            $currentLang = strtolower($languageService->detectLanguage());
            $ergebnis = safe_query("SELECT * FROM navigation_website_main ORDER BY sort");
            $CAPCLASS = new \nexpell\Captcha;

            while ($ds = mysqli_fetch_array($ergebnis)) {
                $mnavID = $ds['mnavID'];
                $resTitle = mysqli_fetch_assoc(safe_query("SELECT content FROM navigation_website_lang WHERE content_key = 'nav_main_$mnavID' AND language = '$currentLang'"));
                $name = !empty($resTitle['content']) ? htmlspecialchars($resTitle['content']) : htmlspecialchars($ds['name']);

                $CAPCLASS->createTransaction();
                $hashCat = $CAPCLASS->getHash();
                
                $catactions = '';
                if ($ds['default'] != 0) {
                    $catactions = '
                        <a class="btn btn-warning" href="admincenter.php?site=webside_navigation&action=editcat&mnavID=' . $mnavID . '"><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '</a>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" 
                                data-delete-url="admincenter.php?site=webside_navigation&delcat=true&mnavID=' . $mnavID . '&captcha_hash=' . $hashCat . '">
                            <i class="bi bi-trash3"></i>  ' . $languageService->get('delete') . '
                        </button>';
                }

                echo '<div class="category-group border-bottom" data-id="' . $mnavID . '">
                        <div class="main-row d-flex align-items-center p-2 bg-secondary bg-opacity-10">
                            <div style="width:50px" class="sortable-handle-cat text-center text-muted cursor-grab"><i class="bi bi-grip-vertical fs-5"></i></div>
                            <div style="width:35%" class="fw-bold text-uppercase small"><i class="bi bi-folder2-open me-2"></i>' . $name . '</div>
                            <div style="width:40%" class="text-muted small">' . $ds['url'] . '</div>
                            <div style="width:20%">' . $catactions . '</div>
                        </div>

                        <div class="sub-sort-container ps-5 bg-white" data-parent-id="' . $mnavID . '" style="min-height: 10px;">';

                $links = safe_query("SELECT * FROM navigation_website_sub WHERE mnavID='$mnavID' ORDER BY sort");
                if (mysqli_num_rows($links)) {
                    while ($db = mysqli_fetch_array($links)) {
                        $snavID = $db['snavID'];
                        $resLinkTitle = mysqli_fetch_assoc(safe_query("SELECT content FROM navigation_website_lang WHERE content_key = 'nav_sub_$snavID' AND language = '$currentLang'"));
                        $linkName = !empty($resLinkTitle['content']) ? htmlspecialchars($resLinkTitle['content']) : "Link $snavID";

                        $CAPCLASS->createTransaction();
                        $hashLink = $CAPCLASS->getHash();

                        echo '<div class="sub-row d-flex align-items-center py-2 border-bottom border-light" data-id="' . $snavID . '">
                                <div style="width:40px" class="sortable-handle-link text-center text-muted cursor-grab"><i class="bi bi-grip-vertical"></i></div>
                                <div style="width:35%"><i class="bi bi-arrow-return-right text-muted me-2"></i>' . $linkName . '</div>
                                <div style="width:40%" class="small text-muted">' . $db['url'] . '</div>
                                <div style="width:20%">
                                    <a href="admincenter.php?site=webside_navigation&action=edit&snavID=' . $snavID . '" class="btn btn-link text-warning"><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '</a>
                                    <button type="button" class="btn btn-link text-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" 
                                            data-delete-url="admincenter.php?site=webside_navigation&delete=true&snavID=' . $snavID . '&captcha_hash=' . $hashLink . '">
                                        <i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '
                                    </button>
                                </div>
                            </div>';
                    }
                }
                echo '  </div>
                    </div>'; 
            }

            echo '</div>
            </div>
        </div>';

        ?>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const statusIndicator = document.getElementById('save-status');

            const autoSave = () => {
                let sortData = { categories: [], links: [] };
                
                document.querySelectorAll('.category-group').forEach((group, index) => {
                    const catId = group.dataset.id;
                    sortData.categories.push({ id: catId, sort: index + 1 });

                    group.querySelectorAll('.sub-row').forEach((row, lIndex) => {
                        sortData.links.push({ 
                            id: row.dataset.id, 
                            sort: lIndex + 1, 
                            catID: catId 
                        });
                    });
                });

                fetch('admincenter.php?site=webside_navigation&ajax_sort=true', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'new_order_data=' + encodeURIComponent(JSON.stringify(sortData))
                })
                .then(() => {
                    statusIndicator.style.opacity = "1";
                    setTimeout(() => { statusIndicator.style.opacity = "0"; }, 2000);
                });
            };

            // Kategorien sortieren
            new Sortable(document.getElementById('main-sort-container'), {
                handle: '.sortable-handle-cat',
                animation: 200,
                ghostClass: 'sort-ghost',
                chosenClass: 'sort-chosen',
                onEnd: autoSave
            });

            // Links sortieren & verschieben
            document.querySelectorAll('.sub-sort-container').forEach(container => {
                new Sortable(container, {
                    handle: '.sortable-handle-link',
                    animation: 200,
                    group: 'shared-links',
                    ghostClass: 'sort-ghost',
                    chosenClass: 'sort-chosen',
                    onEnd: autoSave
                });
            });
        });
        </script>

        <style>
            .cursor-grab { cursor: grab; }
            .cursor-grab:active { cursor: grabbing; }
            
            /* Verhindert das Blauwerden/Markieren */
            .category-group, .sub-row { 
                user-select: none; 
                -webkit-user-select: none; 
            }

            .category-group { transition: background 0.2s; }
            .sub-row:hover { background-color: #f8f9fa; }

            /* Ghost: Der Platzhalter an der alten Stelle */
            .sort-ghost { 
                opacity: 0.3 !important; 
                background-color: #f1f1f1 !important; 
                border: 1px dashed #adb5bd !important;
            }

            /* Chosen: Das Element, das am Mauszeiger hängt */
            .sort-chosen { 
                background-color: #fff !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
            }
        </style>
        <?php
    }

?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Grundlegende Elemente finden (wir prüfen beide Varianten ID-Namen)
    const form = document.getElementById("navForm") || document.getElementById("catForm");
    if (!form) return; // Wenn kein Formular da ist, brauchen wir das Skript nicht

    // Wir suchen das sichtbare Input-Feld (kann title_main, title_input oder name_main sein)
    const mainInput = document.getElementById("title_main") || 
                      document.getElementById("title_input") || 
                      document.getElementById("name_main");
    
    const label = document.getElementById("title-label");
    const catSel = document.getElementById("category_select");
    const buttons = document.querySelectorAll("#lang-switch button");
    
    // Übersetzungen für das Dropdown (falls vorhanden)
    const catTranslations = <?php echo !empty($catJson) ? $catJson : '{}'; ?>;

    if (!mainInput || buttons.length === 0) return;

    buttons.forEach(btn => {
        btn.addEventListener("click", function() {
            const newLang = this.getAttribute("data-lang") || this.dataset.lang;
            const activeBtn = document.querySelector("#lang-switch .btn-primary");
            if (!activeBtn) return;
            const oldLang = activeBtn.getAttribute("data-lang") || activeBtn.dataset.lang;

            if (newLang === oldLang) return;

            // --- 1. TITEL SYNC (Sichern & Laden) ---
            // Wir prüfen, welches Präfix die Hidden-Felder haben (title_hidden_ oder name_hidden_)
            let oldHidden = document.getElementById("title_hidden_" + oldLang) || 
                            document.getElementById("name_hidden_" + oldLang);
            let newHidden = document.getElementById("title_hidden_" + newLang) || 
                            document.getElementById("name_hidden_" + newLang);

            if (oldHidden && newHidden) {
                oldHidden.value = mainInput.value;
                mainInput.value = newHidden.value;
            }

            // --- 2. DROPDOWN ÜBERSETZEN ---
            if (catSel) {
                Array.from(catSel.options).forEach(opt => {
                    const mID = opt.value;
                    if (catTranslations[mID] && catTranslations[mID][newLang]) {
                        opt.text = catTranslations[mID][newLang];
                    }
                });
            }

            // --- 3. UI UPDATE ---
            activeBtn.classList.replace("btn-primary", "btn-secondary");
            this.classList.replace("btn-secondary", "btn-primary");
            
            if (label) {
                // Text dynamisch anpassen (Name oder Titel)
                const baseText = label.innerText.split('(')[0].trim();
                label.innerText = baseText + " (" + newLang.toUpperCase() + ")";
            }
        });
    });

    // --- 4. FINAL SYNC BEIM ABSENDEN ---
    form.addEventListener("submit", function() {
        const activeBtn = document.querySelector("#lang-switch .btn-primary");
        if (activeBtn) {
            const activeLang = activeBtn.getAttribute("data-lang") || activeBtn.dataset.lang;
            let finalHidden = document.getElementById("title_hidden_" + activeLang) || 
                              document.getElementById("name_hidden_" + activeLang);
            if (finalHidden) {
                finalHidden.value = mainInput.value;
            }
        }
    });
});
</script>
