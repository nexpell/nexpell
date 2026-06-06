<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_dashboard_navigation');

$CAPCLASS = new \nexpell\Captcha;

if (!function_exists('dashboardNavigationCaptchaValid')) {
    function dashboardNavigationCaptchaValid($captcha, string $hash): bool
    {
        if (is_object($captcha) && method_exists($captcha, 'checkCaptcha')) {
            return (bool) $captcha->checkCaptcha(0, $hash);
        }
        if (is_object($captcha) && method_exists($captcha, 'checkTransaction')) {
            return (bool) $captcha->checkTransaction($hash);
        }
        return false;
    }
}

if (isset($_GET['ajax_sort'])) {
    $data = json_decode($_POST['new_order_data'] ?? '', true);
    
    if ($data) {
        // 1. Kategorien sortieren
        if (!empty($data['categories'])) {
            foreach ($data['categories'] as $cat) {
                $id = (int)$cat['id'];
                $sort = (int)$cat['sort'];
                safe_query("UPDATE navigation_dashboard_categories SET sort='$sort' WHERE catID='$id'");
            }
        }
        
        // 2. Links sortieren und ggf. Kategorie (Parent) wechseln
        if (!empty($data['links'])) {
            foreach ($data['links'] as $link) {
                $id = (int)$link['id'];
                $sort = (int)$link['sort'];
                $parent = (int)$link['parent'];
                safe_query("UPDATE navigation_dashboard_links SET sort='$sort', catID='$parent' WHERE linkID='$id'");
            }
        }
        exit('success');
    }
    exit('error');
}

// Include Bootstrap Icon Datei
function dn_findBootstrapIconsCssPath(): ?string
{
    $p = __DIR__ . '/css/bootstrap-icons.min.css';
    if (is_file($p) && is_readable($p)) {
        return $p;
    }
    return null;
}

// Liest alle verfügbaren Bootstrap-Icon-Klassen (bi-*) aus der CSS-Datei.
function dn_getBootstrapIconClasses(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cssPath = dn_findBootstrapIconsCssPath();
    $icons = [];

    if ($cssPath !== null) {
        $css = @file_get_contents($cssPath);
        if ($css !== false && $css !== '') {
            if (preg_match_all('/\.bi-([a-z0-9-]+)::before/i', $css, $m)) {
                foreach ($m[1] as $name) {
                    $icons[] = 'bi-' . strtolower($name);
                }
            }
        }
    }

    // Fallback, falls Datei nicht gefunden
    if (!$icons) {
        $icons = [
            'bi-question-circle','bi-house','bi-gear','bi-person','bi-link-45deg','bi-folder','bi-chat','bi-globe',
            'bi-shield','bi-star','bi-bell','bi-calendar','bi-search','bi-pencil','bi-trash','bi-plus-circle'
        ];
    }

    $icons = array_values(array_unique($icons));
    sort($icons, SORT_STRING);

    $cache = $icons;
    return $cache;
}

// Rendert einen Icon-Picker als Galerie (Grid) inkl. Suche
function dn_renderBootstrapIconSelect(LanguageService $languageService, string $fieldName, string $selected, string $label, string $helpText = ''): void {
    $icons = dn_getBootstrapIconClasses();

    // Normalisiere DB-Wert (z.B. "bi bi-gear") auf Picker-Value (z.B. "bi-gear")
    $selectedRaw = trim((string)$selected);
    if ($selectedRaw !== '' && preg_match('/\bbi-([a-z0-9-]+)\b/i', $selectedRaw, $m)) {
        $selected = 'bi-' . strtolower($m[1]);
    } else {
        $selected = trim($selectedRaw);
    }

    $base = preg_replace('/[^a-z0-9\-_]/i', '_', $fieldName);
    $uid  = substr(md5($fieldName . '|' . microtime(true) . '|' . random_int(0, PHP_INT_MAX)), 0, 10);
    $pickerId = 'bi_picker_' . $base . '_' . $uid;

    static $assetsPrinted = false;
    if (!$assetsPrinted) {
        $assetsPrinted = true;

        echo '<style>
.bi-picker-wrap{border:1px solid rgba(0,0,0,.1); border-radius:.4rem; padding:1rem;}
.bi-picker-toolbar{display:flex; gap:.75rem; align-items:center; justify-content:space-between; flex-wrap:wrap; margin-bottom:.75rem;}
.bi-picker-preview{display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem;}
.bi-picker-preview-box{width:52px; height:52px; border:1px solid rgba(0,0,0,.12); border-radius:.75rem; display:flex; align-items:center; justify-content:center; background:#fff;}
.bi-picker-preview-box i{font-size:1.75rem; line-height:1;}

.bi-picker-search{min-width:220px; max-width:420px;}
.bi-picker-grid{display:grid; grid-template-columns:repeat(12, minmax(0, 1fr)); gap:.5rem; max-height:250px; overflow:auto; padding:.25rem;}
@media (max-width: 1200px){.bi-picker-grid{grid-template-columns:repeat(8, minmax(0, 1fr));}}
@media (max-width: 992px){.bi-picker-grid{grid-template-columns:repeat(6, minmax(0, 1fr));}}
@media (max-width: 768px){.bi-picker-grid{grid-template-columns:repeat(4, minmax(0, 1fr));}}
.bi-picker-tile{display:flex; flex-direction:column; gap:.35rem; align-items:center; justify-content:center; border:1px solid rgba(0,0,0,.12); border-radius:.4rem; padding:.5rem .15rem; cursor:pointer; user-select:none; background:#fff;}
.bi-picker-tile i{font-size:1.35rem; line-height:1;}
.bi-picker-radio{position:absolute; opacity:0; pointer-events:none;}
.bi-picker-radio:checked + label{outline:2px solid #fe821d; border-color: #fe821d; background: rgba(254,130,29, .15);}
.bi-picker-tile:hover{border-color:rgba(0,0,0,.25);}
.bi-picker-tile.is-hidden{display:none !important;}
</style>';

        echo '<script>
(function(){
  function norm(s){ return (s||"").toString().toLowerCase().replace(/\\s+/g," ").trim(); }

  function initPicker(root){
    if (!root || root._biInit) return;
    root._biInit = true;

    var search = root.querySelector(".js-bi-grid-search");
    if (!search) return;

    var preview = root.querySelector(".js-bi-preview i");
    function setPreviewFromValue(val){
      if (!preview) return;
      var iconClass = val || "bi-slash-circle";
      preview.className = "bi " + iconClass;
    }

    // initial preview from checked radio
    var checked = root.querySelector("input[type=\"radio\"][name][checked]") || root.querySelector("input[type=\"radio\"][name]:checked");
    if (checked) setPreviewFromValue(checked.value);

    // update preview on change
    root.addEventListener("change", function(e){
      if (e.target && e.target.matches("input[type=\"radio\"]")) {
        setPreviewFromValue(e.target.value);
      }
    });

    var tiles = root.querySelectorAll(".js-bi-tile");
    var map = [];
    for (var i=0;i<tiles.length;i++){
      var t = tiles[i];
      map.push({ el: t, key: norm(t.getAttribute("data-icon") || t.textContent) });
    }

    function apply(){
      var q = norm(search.value);
      if (!q){
        for (var i=0;i<map.length;i++) map[i].el.classList.remove("is-hidden");
        return;
      }
      for (var i=0;i<map.length;i++){
        var show = map[i].key.indexOf(q) !== -1;
        if (show) map[i].el.classList.remove("is-hidden");
        else map[i].el.classList.add("is-hidden");
      }
    }

    search.addEventListener("input", apply);
    apply();
  }

  document.addEventListener("DOMContentLoaded", function(){
    var pickers = document.querySelectorAll(".js-bi-grid-picker");
    for (var i=0;i<pickers.length;i++) initPicker(pickers[i]);
  });
})();
</script>';
    }

    echo '<div class="mb-3 js-bi-grid-picker" id="' . htmlspecialchars($pickerId) . '">';
    if ($label !== '') {
        echo '<label class="form-label">' . htmlspecialchars($label) . '</label>';
    }

    // Große Vorschau (aktuell ausgewähltes Icon)
    $previewIcon = ($selected !== '') ? $selected : 'bi-slash-circle';
    echo '<div class="bi-picker-preview js-bi-preview">';
    echo '<div class="bi-picker-preview-box"><i class="bi ' . htmlspecialchars($previewIcon) . '"></i></div>';
    echo '<div class="small text-muted">' . $languageService->get('icon_current') . '</div>';
    echo '</div>';

    echo '<div class="bi-picker-wrap">';
    echo '<div class="bi-picker-toolbar">';
    echo '<input type="search" class="form-control bi-picker-search js-bi-grid-search" placeholder="' . $languageService->get('icon_search') . '" autocomplete="off">';
    if ($helpText !== '') {
        echo '<small class="text-muted">' . htmlspecialchars($helpText) . '</small>';
    } else {
        echo '<small class="text-muted">' . $languageService->get('icon_search_desc') . '</small>';
    }
    echo '</div>';

    echo '<div class="bi-picker-grid">';

    // "kein Icon"
    $noneId = $pickerId . '_none';
    $checkedNone = ($selected === '') ? ' checked' : '';

    echo '<input class="bi-picker-radio" type="radio"'
    . ' name="' . htmlspecialchars($fieldName) . '"'
    . ' id="' . htmlspecialchars($noneId) . '"'
    . ' value=""' . $checkedNone . '>';

    echo '<label class="bi-picker-tile js-bi-tile" data-icon="none" for="' . htmlspecialchars($noneId) . '">';
    echo '<small class="text-center">' . $languageService->get('icon_none') . '</small>';
    echo '</label>';

    foreach ($icons as $ic) {
        $id = $pickerId . '_' . preg_replace('/[^a-z0-9\-_]/i', '_', $ic);
        $checked = ($ic === $selected) ? ' checked' : '';

        echo '<input class="bi-picker-radio" type="radio"'
        . ' name="' . htmlspecialchars($fieldName) . '"'
        . ' id="' . htmlspecialchars($id) . '"'
        . ' value="' . htmlspecialchars($ic) . '"' . $checked . '>';

        echo '<label class="bi-picker-tile js-bi-tile" data-icon="' . htmlspecialchars($ic) . '" for="' . htmlspecialchars($id) . '">';
        echo '<i class="bi ' . htmlspecialchars($ic) . '"></i>';
        echo '</label>';
    }

    echo '    </div>'; // grid
    echo '  </div>'; // wrap
    echo '</div>'; // mb-3
}

/* ============================================================
   LÖSCHEN EINES LINKS
============================================================ */
/* ============================================================
   LÖSCHEN EINES LINKS
============================================================ */
// Wir prüfen auf GET, da der Lösch-Link in der Regel per URL aufgerufen wird
if (isset($_GET['delete'])) {
    
    // Captcha Check - WICHTIG: Nutze $_GET, wenn der Hash in der URL steht
    if (!dashboardNavigationCaptchaValid($CAPCLASS, (string)($_GET['captcha_hash'] ?? ''))) {
        nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'alert_captcha_invalid', false);
    }

    $linkID = (int)($_GET['linkID'] ?? 0);
    if ($linkID <= 0) nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'alert_invalid_id', false);

    // 1. Modulnamen holen (für Rechte & Audit)
    $modulname = '';
    $stmt = $_database->prepare("SELECT modulname FROM navigation_dashboard_links WHERE linkID = ?");
    $stmt->bind_param("i", $linkID);
    $stmt->execute();
    $stmt->bind_result($modulname);
    $stmt->fetch();
    $stmt->close();

    // 2. Link löschen
    $stmt = $_database->prepare("DELETE FROM navigation_dashboard_links WHERE linkID = ?");
    $stmt->bind_param("i", $linkID);
    $stmt->execute();
    $stmt->close();

    // 3. NEU: Sprach-Einträge löschen
    $stmt = $_database->prepare("DELETE FROM navigation_dashboard_lang WHERE content_key = ?");
    $contentKey = "nav_link_" . $linkID;
    $stmt->bind_param("s", $contentKey);
    $stmt->execute();
    $stmt->close();

    // 4. Rechte-Tabelle bereinigen
    if (!empty($modulname)) {
        $stmt = $_database->prepare("DELETE FROM user_role_admin_navi_rights WHERE modulname = ?");
        $stmt->bind_param("s", $modulname);
        $stmt->execute();
        $stmt->close();
    }

    nx_audit_delete('dashboard_navigation', (string)$linkID, ($modulname !== '' ? $modulname : 'Link #'.$linkID), 'admincenter.php?site=dashboard_navigation');
    nx_redirect('admincenter.php?site=dashboard_navigation', 'success', 'alert_deleted', false);
}

/* ============================================================
    LÖSCHEN EINER KATEGORIE (inkl. aller Links)
============================================================ */
elseif (isset($_GET['delcat'])) {
    
    if (!dashboardNavigationCaptchaValid($CAPCLASS, (string)($_GET['captcha_hash'] ?? ''))) {
        nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'alert_captcha_invalid', false);
    }

    $catID = (int)($_GET['catID'] ?? 0);
    if ($catID <= 0) nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'alert_invalid_id', false);

    // 1. Kategorie-Modulname & untergeordnete Links holen
    $catModulname = '';
    $stmt = $_database->prepare("SELECT modulname FROM navigation_dashboard_categories WHERE catID = ?");
    $stmt->bind_param("i", $catID);
    $stmt->execute();
    $stmt->bind_result($catModulname);
    $stmt->fetch();
    $stmt->close();

    $links = [];
    $stmt = $_database->prepare("SELECT linkID, modulname FROM navigation_dashboard_links WHERE catID = ?");
    $stmt->bind_param("i", $catID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $links[] = $row; }
    $stmt->close();

    // 2. Sprach-Einträge der Links löschen
    foreach ($links as $link) {
        $lID = (int)$link['linkID'];
        safe_query("DELETE FROM navigation_dashboard_lang WHERE content_key = 'nav_link_$lID'");
    }

    // 3. Sprach-Eintrag der Kategorie löschen
    safe_query("DELETE FROM navigation_dashboard_lang WHERE content_key = 'nav_cat_$catID'");

    // 4. Hauptdaten löschen
    safe_query("DELETE FROM navigation_dashboard_links WHERE catID = '$catID'");
    safe_query("DELETE FROM navigation_dashboard_categories WHERE catID = '$catID'");

    // 5. Rechte-Einträge bereinigen
    $modulnamesToDelete = [];
    if (!empty($catModulname)) $modulnamesToDelete[] = $catModulname;
    foreach ($links as $link) { 
        if (!empty($link['modulname'])) $modulnamesToDelete[] = $link['modulname']; 
    }

    if (!empty($modulnamesToDelete)) {
        $in = str_repeat('?,', count($modulnamesToDelete) - 1) . '?';
        $stmt = $_database->prepare("DELETE FROM user_role_admin_navi_rights WHERE modulname IN ($in)");
        $stmt->bind_param(str_repeat('s', count($modulnamesToDelete)), ...$modulnamesToDelete);
        $stmt->execute();
        $stmt->close();
    }

    nx_audit_delete('dashboard_navigation', (string)$catID, ($catModulname !== '' ? $catModulname : 'Cat #'.$catID), 'admincenter.php?site=dashboard_navigation');
    nx_redirect('admincenter.php?site=dashboard_navigation', 'success', 'alert_deleted', false);
}


elseif (isset($_POST['save']) || isset($_POST['saveedit'])) {

    $CAPCLASS = new \nexpell\Captcha;

    /* ===============================
       CAPTCHA
    =============================== */
    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_alert('danger', 'alert_captcha_invalid', false);
        return;
    }

    /* ===============================
       BASISDATEN
    =============================== */
    $linkID   = (int)($_POST['linkID'] ?? 0);
    $isUpdate = ($linkID > 0);

    $catID     = (int)($_POST['catID'] ?? 0);
    $nameArray = $_POST['name'] ?? [];

    $url       = trim($_POST['url'] ?? '');
    $modulname = strtolower(trim($_POST['modulname'] ?? ''));
    $modulname = preg_replace('/[^a-z0-9_]/', '', $modulname);

    /* ===============================
       PFLICHTFELDER
    =============================== */
    if (empty($nameArray['de']) || $url === '') {
        nx_alert('warning', 'alert_missing_required', false);
        return;
    }

    $redirectUrl = 'admincenter.php?site=dashboard_navigation';

    /* ===============================
       AUDIT-NAME
    =============================== */
    $auditName = trim(
        $nameArray['de']
        ?? $nameArray['en']
        ?? $nameArray['it']
        ?? '—'
    );

    /* =====================================================
       MODULNAME EINDEUTIGKEIT (NUR BEI NEU)
    ===================================================== */
    if (!$isUpdate && $modulname !== '') {

        $stmt = $_database->prepare("
            SELECT COUNT(*)
            FROM navigation_dashboard_links
            WHERE modulname = ?
        ");
        $stmt->bind_param("s", $modulname);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            nx_alert('warning', 'alert_modulname_exists', false);
            return;
        }
    }

    /* =====================================================
       1️⃣ LINK SPEICHERN (INSERT / UPDATE)
    ===================================================== */
    if ($isUpdate) {

        // UPDATE
        $stmt = $_database->prepare("
            UPDATE navigation_dashboard_links
            SET catID = ?, url = ?, modulname = ?
            WHERE linkID = ?
        ");
        $stmt->bind_param("issi", $catID, $url, $modulname, $linkID);
        $stmt->execute();
        $stmt->close();

    } else {

        // SORT
        $resSort = safe_query("
            SELECT MAX(sort) AS maxsort
            FROM navigation_dashboard_links
            WHERE catID = $catID
        ");
        $rowSort = mysqli_fetch_assoc($resSort);
        $sort = ((int)($rowSort['maxsort'] ?? 0)) + 1;

        // INSERT
        $stmt = $_database->prepare("
            INSERT INTO navigation_dashboard_links
                (catID, url, modulname, sort)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("issi", $catID, $url, $modulname, $sort);
        $stmt->execute();

        $linkID = (int)$stmt->insert_id;
        $stmt->close();
    }

    /* =====================================================
       2️⃣ MULTILANG-NAMEN
    ===================================================== */
    $resLang = safe_query("
        SELECT iso_639_1
        FROM settings_languages
        WHERE active = 1
    ");

    while ($lRow = mysqli_fetch_assoc($resLang)) {

        $iso  = strtolower($lRow['iso_639_1']);
        $text = trim($nameArray[$iso] ?? '');

        $contentKey = 'nav_link_' . $linkID;

        safe_query("
            INSERT INTO navigation_dashboard_lang
                (content_key, language, content, updated_at)
            VALUES
                ('$contentKey', '$iso', '" . escape($text) . "', NOW())
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                updated_at = NOW()
        ");
    }

    /* =====================================================
       3️⃣ AUDIT LOG
    ===================================================== */
    if ($isUpdate) {
        nx_audit_update(
            'dashboard_navigation',
            (string)$linkID,
            true,
            $auditName,
            $redirectUrl,
            [
                'modulname' => ($modulname !== '' ? $modulname : null),
                'catID'     => $catID
            ]
        );
    } else {
        nx_audit_create(
            'dashboard_navigation',
            (string)$linkID,
            $auditName,
            $redirectUrl,
            [
                'modulname' => ($modulname !== '' ? $modulname : null),
                'catID'     => $catID
            ]
        );
    }

    /* =====================================================
       4️⃣ ADMIN-RECHTE (AUTO)
    ===================================================== */
    if ($modulname !== '') {
        foreach ([1, 2] as $roleID) {

            $type = 'link';

            $stmt_access = $_database->prepare("
                INSERT INTO user_role_admin_navi_rights
                    (roleID, type, modulname)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    modulname = VALUES(modulname)
            ");

            $stmt_access->bind_param("iss", $roleID, $type, $modulname);
            $stmt_access->execute();
            $stmt_access->close();
        }
    }

    /* =====================================================
       5️⃣ REDIRECT
    ===================================================== */
    nx_redirect($redirectUrl, 'success', 'alert_saved', false);
}



elseif (isset($_POST['saveaddcat']) || isset($_POST['savecat'])) {

    $CAPCLASS = new \nexpell\Captcha;

    /* ===============================
       CAPTCHA
    =============================== */
    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_alert('danger', 'alert_captcha_invalid', false);
        return;
    }

    /* ===============================
       BASISDATEN
    =============================== */
    $catID    = (int)($_POST['catID'] ?? 0);
    $isUpdate = ($catID > 0);

    $icon      = trim($_POST['icon'] ?? '');
    $nameArray = $_POST['name'] ?? [];
    $redirectUrl = 'admincenter.php?site=dashboard_navigation';

    $modulname = $isUpdate
        ? null
        : strtolower(trim($_POST['modulname'] ?? ''));

    if (!$isUpdate) {
        $modulname = preg_replace('/[^a-z0-9_]/', '', $modulname);
    }

    /* ===============================
       PFLICHTFELDER
    =============================== */
    if ($icon === '' || empty($nameArray['de'])) {
        nx_alert('warning', 'alert_missing_required_cat', false);
        return;
    }

    /* ===============================
       ICON NORMALISIEREN
    =============================== */
    if (!str_starts_with($icon, 'bi ')) {
        if (str_starts_with($icon, 'bi-')) {
            $icon = 'bi ' . $icon;
        }
    }

    /* ===============================
       AUDIT-NAME
    =============================== */
    $auditName = trim(
        $nameArray['de']
        ?? $nameArray['en']
        ?? $nameArray['it']
        ?? ($isUpdate ? (string)$catID : '—')
    );

    /* =====================================================
       MODULNAME EINDEUTIGKEIT (NUR BEI NEU)
    ===================================================== */
    if (!$isUpdate && $modulname !== '') {

        $stmt = $_database->prepare("
            SELECT COUNT(*)
            FROM navigation_dashboard_categories
            WHERE modulname = ?
        ");
        $stmt->bind_param("s", $modulname);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            nx_alert('warning', 'alert_modulname_exists', false);
            return;
        }
    }

    /* =====================================================
       1️⃣ KATEGORIE SPEICHERN (INSERT / UPDATE)
    ===================================================== */
    if ($isUpdate) {

        // UPDATE
        $stmt = $_database->prepare("
            UPDATE navigation_dashboard_categories
            SET fa_name = ?
            WHERE catID = ?
        ");
        $stmt->bind_param("si", $icon, $catID);
        $stmt->execute();
        $stmt->close();

        // Modulname für Audit & ACL holen
        $stmt_mod = $_database->prepare("
            SELECT modulname
            FROM navigation_dashboard_categories
            WHERE catID = ?
        ");
        $stmt_mod->bind_param("i", $catID);
        $stmt_mod->execute();
        $stmt_mod->bind_result($modulname);
        $stmt_mod->fetch();
        $stmt_mod->close();

    } else {

        // SORTIERUNG
        $resSort = safe_query("
            SELECT MAX(sort) AS maxsort
            FROM navigation_dashboard_categories
        ");
        $rowSort = mysqli_fetch_assoc($resSort);
        $sort = ((int)($rowSort['maxsort'] ?? 0)) + 1;

        $sort_art = 0;

        // INSERT
        $stmt = $_database->prepare("
            INSERT INTO navigation_dashboard_categories
                (fa_name, modulname, sort_art, sort)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssii", $icon, $modulname, $sort_art, $sort);
        $stmt->execute();

        $catID = (int)$stmt->insert_id;
        $stmt->close();
    }

    /* =====================================================
       2️⃣ MULTILANG-TEXTE
    ===================================================== */
    $resLang = safe_query("
        SELECT iso_639_1
        FROM settings_languages
        WHERE active = 1
    ");

    while ($lRow = mysqli_fetch_assoc($resLang)) {

        $iso  = strtolower($lRow['iso_639_1']);
        $text = trim($nameArray[$iso] ?? '');

        $contentKey = 'nav_cat_' . $catID;

        safe_query("
            INSERT INTO navigation_dashboard_lang
                (content_key, language, content, updated_at)
            VALUES
                ('$contentKey', '$iso', '" . escape($text) . "', NOW())
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                updated_at = NOW()
        ");
    }

    /* =====================================================
       3️⃣ AUDIT LOG
    ===================================================== */
    if ($isUpdate) {
        nx_audit_update(
            'dashboard_navigation',
            (string)$catID,
            true,
            $auditName,
            $redirectUrl,
            ['modulname' => $modulname]
        );
    } else {
        nx_audit_create(
            'dashboard_navigation',
            (string)$catID,
            $auditName,
            $redirectUrl,
            ['modulname' => $modulname]
        );
    }

    /* =====================================================
       4️⃣ ADMIN-RECHTE (AUTO)
    ===================================================== */
    if (!empty($modulname)) {
        foreach ([1, 2] as $roleID) {

            $type = 'category';

            $stmt_access = $_database->prepare("
                INSERT INTO user_role_admin_navi_rights
                    (roleID, type, modulname)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    modulname = VALUES(modulname)
            ");

            $stmt_access->bind_param("iss", $roleID, $type, $modulname);
            $stmt_access->execute();
            $stmt_access->close();
        }
    }

    /* =====================================================
       5️⃣ REDIRECT
    ===================================================== */
    nx_redirect($redirectUrl, 'success', 'alert_saved', false);
}


if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

if ($action == "add") {

    $currentLang = strtolower($languageService->detectLanguage());

    /* ===============================
       SPRACHEN LADEN
    =============================== */
    $languages = [];
    $resLang = safe_query("
        SELECT iso_639_1, name_de 
        FROM settings_languages 
        WHERE active = 1 
        ORDER BY id ASC
    ");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[strtolower($row['iso_639_1'])] = $row['name_de'];
    }

    /* ===============================
       KATEGORIEN (mehrsprachig)
    =============================== */
    $catData = [];
    $resCat = safe_query("
        SELECT c.catID, l.language, l.content
        FROM navigation_dashboard_categories c
        JOIN navigation_dashboard_lang l 
          ON l.content_key = CONCAT('nav_cat_', c.catID)
    ");
    while ($c = mysqli_fetch_assoc($resCat)) {
        $catData[$c['catID']][strtolower($c['language'])] = $c['content'];
    }
    $catJson = json_encode($catData);

    /* ===============================
       CAPTCHA
    =============================== */
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    /* ===============================
       FORMULAR
    =============================== */
    echo '<div class="card shadow-sm border-0 mb-4 mt-4">

        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get("dashnavi") . '</span>
                <small class="text-muted ms-2">' . $languageService->get("add_link") . '</small>
            </div>

            <div class="ms-auto btn-group" id="lang-switch">';
                foreach ($languages as $iso => $label) {
                    $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                    echo '<button type="button"
                                 class="btn ' . $activeClass . '"
                                 data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
                }
    echo '  </div>
        </div>

        <div class="card-body p-4">

            <form method="post"
                  action="admincenter.php?site=dashboard_navigation"
                  id="navForm">

                <div class="row g-3 mb-4">

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get("category") . '</label>
                        <select class="form-select" name="catID" id="category_select">';
                            foreach ($catData as $catID => $langs) {
                                $display = $langs[$currentLang] ?? ($langs["de"] ?? "---");
                                echo '<option value="' . (int)$catID . '">' . htmlspecialchars($display) . '</option>';
                            }
                echo '</select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get("url") . '</label>
                        <input class="form-control"
                               type="text"
                               name="url"
                               placeholder="z.B. admincenter.php?site=news"
                               required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get("modulname") . '</label>
                        <input class="form-control"
                               type="text"
                               name="modulname"
                               placeholder="z.B. news_manager">
                    </div>

                </div>

                <!-- NAME -->
            
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">
                    <i class="bi bi-file-earmark-code me-1"></i> ' . $languageService->get("navigation_name") . '
                    </label>

                    <input type="text"
                           class="form-control form-control-lg"
                           id="title_input"
                           placeholder="Link-Namen eingeben...">';
                    
                    // Hidden Felder für alle Sprachen
                    foreach ($languages as $iso => $label) {
                        echo '<input type="hidden"
                                     name="name[' . $iso . ']"
                                     id="title_hidden_' . $iso . '"
                                     value="">';
                    }

            echo '</div>

                <div class="pt-3">
                    <input type="hidden" name="captcha_hash" value="' . $hash . '">

                    <button class="btn btn-primary px-4"
                            type="submit"
                            name="save">
                        ' . $languageService->get("save") . '
                    </button>

                    <a href="admincenter.php?site=dashboard_navigation"
                       class="btn btn-link text-muted">
                        Abbrechen
                    </a>
                </div>

            </form>
        </div>
    </div>';
}

elseif ($action === "edit") {

    $currentLang = strtolower($languageService->detectLanguage());
    $linkID = (int)($_GET['linkID'] ?? 0);

    /* ===============================
       BASISDATEN
    =============================== */
    if ($linkID <= 0) {
        nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'alert_invalid_id', false);
    }

    $ds = mysqli_fetch_assoc(
        safe_query("SELECT * FROM navigation_dashboard_links WHERE linkID = $linkID")
    );
    if (!$ds) {
        nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'alert_not_found', false);
    }

    /* ===============================
       SPRACHEN
    =============================== */
    $languages = [];
    $resLang = safe_query("
        SELECT iso_639_1 
        FROM settings_languages 
        WHERE active = 1 
        ORDER BY id ASC
    ");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[] = strtolower($row['iso_639_1']);
    }

    /* ===============================
       TITEL (navigation_dashboard_lang)
    =============================== */
    $titles = [];
    $resTitle = safe_query("
        SELECT language, content 
        FROM navigation_dashboard_lang 
        WHERE content_key = 'nav_link_$linkID'
    ");
    while ($row = mysqli_fetch_assoc($resTitle)) {
        $titles[strtolower($row['language'])] = $row['content'];
    }

    /* ===============================
       KATEGORIEN FÜR DROPDOWN + JS
    =============================== */
    $catData = [];
    $resCat = safe_query("
        SELECT c.catID, l.language, l.content
        FROM navigation_dashboard_categories c
        JOIN navigation_dashboard_lang l 
          ON l.content_key = CONCAT('nav_cat_', c.catID)
    ");
    while ($c = mysqli_fetch_assoc($resCat)) {
        $catData[$c['catID']][strtolower($c['language'])] = $c['content'];
    }
    $catJson = json_encode($catData);

    /* ===============================
       CAPTCHA
    =============================== */
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();

    /* ===============================
       FORMULAR (DESIGN IDENTISCH ZU webside_navigation)
    =============================== */
    echo '<div class="card shadow-sm border-0 mb-4 mt-4">

        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get('dashnavi') . '</span>
                <small class="text-muted ms-2">' . $languageService->get('edit_link') . '</small>
            </div>

            <div class="ms-auto btn-group" id="lang-switch">';

                foreach ($languages as $iso) {
                    $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                    echo '<button type="button"
                                 class="btn ' . $activeClass . '"
                                 data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
                }

    echo '  </div>
        </div>

        <div class="card-body">
            <form method="post"
                  action="admincenter.php?site=dashboard_navigation"
                  id="navForm">

                <div class="row g-3 mb-4">

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('category') . '</label>
                        <select class="form-select" name="catID" id="category_select">';

                            foreach ($catData as $catID => $langs) {
                                $selected = ($catID == $ds['catID']) ? ' selected' : '';
                                $display  = $langs[$currentLang] ?? ($langs['de'] ?? '---');
                                echo '<option value="' . (int)$catID . '"' . $selected . '>'
                                   . htmlspecialchars($display) .
                                   '</option>';
                            }

                    echo '</select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('url') . '</label>
                        <input class="form-control"
                               type="text"
                               name="url"
                               value="' . htmlspecialchars($ds['url']) . '">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">' . $languageService->get('modulname') . '</label>
                        <input class="form-control"
                               type="text"
                               name="modulname"
                               value="' . htmlspecialchars($ds['modulname']) . '">
                    </div>

                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">
                        ' . $languageService->get('navigation_name') . '
                    </label>

                    <input type="text"
                           class="form-control form-control-lg"
                           id="title_input"
                           value="' . htmlspecialchars($titles[$currentLang] ?? '') . '">';

                    foreach ($languages as $iso) {
                        echo '<input type="hidden"
                                     name="name[' . $iso . ']"
                                     id="title_hidden_' . $iso . '"
                                     value="' . htmlspecialchars($titles[$iso] ?? '') . '">';
                    }

            echo '</div>

                <input type="hidden" name="captcha_hash" value="' . $CAPCLASS->getHash() . '">
                <input type="hidden" name="linkID" value="' . (int)$linkID . '">

                <div class="pt-3">
                    <button class="btn btn-warning px-4" type="submit" name="saveedit">
                        ' . $languageService->get('save') . '
                    </button>
                    <a href="admincenter.php?site=dashboard_navigation"
                       class="btn btn-link text-muted">Abbrechen</a>
                </div>

            </form>
        </div>
    </div>';
}

elseif ($action === "addcat") {

    $currentLang = strtolower($languageService->detectLanguage());

    /* ===============================
       SPRACHEN LADEN
    =============================== */
    $languages = [];
    $resLang = safe_query("
        SELECT iso_639_1, name_de
        FROM settings_languages
        WHERE active = 1
        ORDER BY id ASC
    ");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $iso = strtolower($row['iso_639_1']);
        $languages[$iso] = $row['name_de'];
    }

    /* ===============================
       CAPTCHA
    =============================== */
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    /* ===============================
       FORMULAR (DESIGN = webside_navigation)
    =============================== */
    echo '<div class="card shadow-sm border-0 mb-4 mt-4">

        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get('dashnavi') . '</span>
                <small class="text-muted ms-2">' . $languageService->get('add_category') . '</small>
            </div>

            <div class="ms-auto btn-group" id="lang-switch">';

                foreach ($languages as $iso => $label) {
                    $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                    echo '<button type="button"
                                 class="btn ' . $activeClass . '"
                                 data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
                }

    echo '  </div>
        </div>

        <div class="card-body p-4">
            <form method="post"
      action="admincenter.php?site=dashboard_navigation"
      id="catForm">

    <div class="row g-4">

        <!-- LINKER BEREICH: NUR ICON -->
        <div class="col-lg-5 border-end">

            <div class="text-uppercase text-muted fw-semibold mb-3 small">
                ' . $languageService->get('icon') . '
            </div>

            <label class="form-label fw-bold small text-uppercase text-muted">
                <i class="bi bi-emoji-smile me-1"></i> ' . $languageService->get('icon') . '
            </label>';

            dn_renderBootstrapIconSelect(
                $languageService,
                'icon',
                '',
                '',
                ''
            );

        echo '</div>


        <!-- RECHTER BEREICH: SETTINGS + NAME -->
        <div class="col-lg-7">

            <!-- SETTINGS -->
            <div class="text-uppercase text-muted fw-semibold mb-3 small">
                ' . $languageService->get('settings') . '
            </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">
                        <i class="bi bi-code-slash me-1"></i> ' . $languageService->get('modulname') . '
                    </label>
                    <input class="form-control"
                        type="text"
                        placeholder="Modulname">
                    </div>

            <!-- NAME -->
            <div class="mb-3">
                <label class="form-label fw-bold small text-uppercase text-muted">
                    <i class="bi bi-file-earmark-code me-1"></i> ' . $languageService->get('category_name') . '
                </label>
                <input type="text"
                       class="form-control form-control-lg"
                       id="name_main"
                       placeholder="Name der Kategorie eingeben...">
            </div>';

            // Hidden Language Fields
            foreach ($languages as $iso => $label) {
                echo '<input type="hidden"
                             name="name[' . $iso . ']"
                             id="name_hidden_' . $iso . '"
                             value="">';
            }

    echo '</div>
    </div>

    <div class="d-flex justify-content-start gap-2 mt-4 pt-3">
        <input type="hidden" name="captcha_hash" value="' . $CAPCLASS->getHash() . '">

        <button class="btn btn-primary px-4"
                type="submit"
                name="saveaddcat">
            ' . $languageService->get('save') . '
        </button>

        <a href="admincenter.php?site=dashboard_navigation"
           class="btn btn-link text-muted">
            Abbrechen
        </a>
    </div>

</form>

        </div>
    </div>';
}

elseif ($action === "editcat") {

    $currentLang = strtolower($languageService->detectLanguage());
    $catID = (int)($_GET['catID'] ?? 0);

    if ($catID <= 0) {
        nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'alert_invalid_id', false);
    }

    /* ===============================
       BASISDATEN
    =============================== */
    $ds = mysqli_fetch_assoc(
        safe_query("SELECT * FROM navigation_dashboard_categories WHERE catID = {$catID}")
    );
    if (!$ds) {
        nx_redirect('admincenter.php?site=dashboard_navigation', 'danger', 'error_cat_not_found', false);
    }

    /* ===============================
       SPRACHEN
    =============================== */
    $languages = [];
    $resLang = safe_query("
        SELECT iso_639_1, name_de
        FROM settings_languages
        WHERE active = 1
        ORDER BY id ASC
    ");
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[strtolower($row['iso_639_1'])] = $row['name_de'];
    }

    /* ===============================
       NAMEN LADEN (nav_cat_ID)
    =============================== */
    $names = [];
    $resNames = safe_query("
        SELECT language, content
        FROM navigation_dashboard_lang
        WHERE content_key = 'nav_cat_{$catID}'
    ");
    while ($row = mysqli_fetch_assoc($resNames)) {
        $names[strtolower($row['language'])] = $row['content'];
    }

    /* ===============================
       CAPTCHA
    =============================== */
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    /* ===============================
       FORMULAR (DESIGN = webside_navigation)
    =============================== */
    echo '<div class="card shadow-sm border-0 mb-4 mt-4">

        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-menu-app me-2"></i>
                <span>' . $languageService->get('dashnavi') . '</span>
                <small class="text-muted ms-2">' . $languageService->get('edit_category') . '</small>
            </div>

            <div class="ms-auto btn-group" id="lang-switch">';

                foreach ($languages as $iso => $label) {
                    $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                    echo '<button type="button"
                                 class="btn ' . $activeClass . '"
                                 data-lang="' . $iso . '">' . strtoupper($iso) . '</button>';
                }

    echo '  </div>
        </div>

        <div class="card-body p-4">
            <form method="post"
                  action="admincenter.php?site=dashboard_navigation"
                  id="catForm">

                <div class="row g-4">

                    <!-- LINKER BEREICH: ICON + MODULNAME -->
                    <div class="col-lg-5 border-end">
                        <div class="text-uppercase text-muted fw-semibold mb-3 small">
                            ' . $languageService->get('settings') . '
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-uppercase text-muted">
                                <i class="bi bi-emoji-smile me-1"></i> ' . $languageService->get('icon') . '
                            </label>';

                            // ✅ ICON BLEIBT
                            dn_renderBootstrapIconSelect(
                                $languageService,
                                'icon',
                                (string)($ds['fa_name'] ?? ''),
                                '',
                                ''
                            );

                    echo '</div>                        
                    </div>

                    <!-- RECHTER BEREICH: SETTINGS + NAME -->
                    <div class="col-lg-7">

                        <!-- SETTINGS -->
                        <div class="text-uppercase text-muted fw-semibold mb-3 small">
                            ' . $languageService->get('settings') . '
                        </div>                        

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">
                                <i class="bi bi-code-slash me-1"></i> ' . $languageService->get('modulname') . '
                            </label>
                            <input class="form-control"
                                   type="text"
                                   value="' . htmlspecialchars($ds['modulname']) . '"
                                   disabled>
                        </div>

                        

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">
                                <i class="bi bi-file-earmark-code me-1"></i> ' . $languageService->get('category_name') . '
                            </label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="name_main"
                                   value="' . htmlspecialchars($names[$currentLang] ?? '', ENT_QUOTES) . '"
                                   placeholder="Name der Kategorie eingeben...">
                        </div>';

                        foreach ($languages as $iso => $label) {
                            echo '<input type="hidden"
                                         name="name[' . $iso . ']"
                                         id="name_hidden_' . $iso . '"
                                         value="' . htmlspecialchars($names[$iso] ?? '', ENT_QUOTES) . '">';
                        }

        echo '</div>
                </div>

                <div class="d-flex justify-content-start gap-2 mt-4 pt-3">
                    <input type="hidden" name="captcha_hash" value="' . htmlspecialchars($hash) . '">
                    <input type="hidden" name="catID" value="' . (int)$catID . '">

                    <button class="btn btn-warning px-4" type="submit" name="savecat">
                        ' . $languageService->get('save') . '
                    </button>

                    <a href="admincenter.php?site=dashboard_navigation"
                       class="btn btn-link text-muted">
                        Abbrechen
                    </a>
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
                <a class="btn btn-secondary" href="admincenter.php?site=dashboard_navigation&amp;action=addcat">
                    ' . $languageService->get('add_category') . '
                </a>
                <a class="btn btn-secondary ms-2" href="admincenter.php?site=dashboard_navigation&amp;action=add">
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
            $resCats = safe_query("SELECT * FROM navigation_dashboard_categories ORDER BY sort");

            // 1. CAPTCHA
            $CAPCLASS = new \nexpell\Captcha;
            $CAPCLASS->createTransaction();
            $globalHash = $CAPCLASS->getHash();

            while ($cat = mysqli_fetch_array($resCats)) {
                $catID = (int)$cat['catID'];

                $resTitle = mysqli_fetch_assoc(
                    safe_query("SELECT content FROM navigation_dashboard_lang WHERE content_key = 'nav_cat_$catID' AND language = '$currentLang'")
                );
                $catName = !empty($resTitle['content'])
                    ? htmlspecialchars($resTitle['content'])
                    : 'Kategorie ' . $catID;

        echo '<div class="category-group border-top" data-id="' . $catID . '">
            <div class="main-row d-flex align-items-center p-2 bg-secondary bg-opacity-10">
                <div style="width:50px" class="sortable-handle-cat text-center text-muted cursor-grab"><i class="bi bi-grip-vertical fs-5"></i></div>
                <div style="width:35%" class="fw-bold text-uppercase small"><i class="' . ($cat['fa_name'] ?: 'bi bi-folder2-open') . ' me-2"></i>' . $catName . '               </div>
                <div style="width:40%" class="text-muted small">' . htmlspecialchars($cat['modulname']) . '</div>
                <div style="width:20%"><a class="btn btn-warning" href="admincenter.php?site=dashboard_navigation&action=editcat&catID=' . $catID . '">
                        <i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                        data-delete-url="admincenter.php?site=dashboard_navigation&delcat=true&catID=' . $catID . '&captcha_hash=' . $globalHash . '">
                        <i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '
                    </button>
                </div>
            </div>

            <div class="sub-sort-container ps-5 bg-white" data-parent-id="' . $catID . '" style="min-height:10px;">';

            $currentLang = substr(strtolower($languageService->detectLanguage()), 0, 2);

            $resLinks = safe_query("
                SELECT *
                FROM navigation_dashboard_links
                WHERE catID = '$catID'
                ORDER BY sort
            ");

            while ($ds = mysqli_fetch_array($resLinks)) {
                $linkID = (int)$ds['linkID'];
                $contentKey = 'nav_link_' . $linkID;

                $resTitle = mysqli_fetch_assoc(
                    safe_query("
                        SELECT content
                        FROM navigation_dashboard_lang
                        WHERE content_key = '$contentKey'
                          AND language = '$currentLang'
                        LIMIT 1
                    ")
                );

                $linkName = !empty($resTitle['content'])
                    ? htmlspecialchars($resTitle['content'])
                    : htmlspecialchars($ds['name'] ?? ('Link ' . $linkID));

            echo '<div class="sub-row d-flex align-items-center py-2 border-bottom border-light" data-id="' . $linkID . '">
                <div style="width:40px" class="sortable-handle-link text-center text-muted cursor-grab">
                    <i class="bi bi-grip-vertical"></i>
                </div>
                <div style="width:35%">
                    <i class="bi bi-arrow-return-right text-muted me-2"></i>' . $linkName . '
                </div>
                <div style="width:40%" class="small text-muted font-monospace">' . htmlspecialchars($ds['url'] ?? '') . '</div>
                <div style="width:20%">
                    <a href="admincenter.php?site=dashboard_navigation&action=edit&linkID=' . $linkID . '"
                       class="btn btn-link text-warning">
                        <i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '
                    </a>
                    <button type="button"
                        class="btn btn-link text-danger"
                        data-bs-toggle="modal"
                        data-bs-target="#confirmDeleteModal"
                        data-delete-url="admincenter.php?site=dashboard_navigation&delete=true&linkID=' . $linkID . '&captcha_hash=' . $globalHash . '">
                        <i class="bi bi-trash"></i> ' . $languageService->get('delete') . '
                    </button>
                </div>
            </div>';
        }

        echo '</div></div>';
    }

    echo '</div></div></div>';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var deleteModal = document.getElementById('confirmDeleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            // Button der das Modal aufgerufen hat
            var button = event.relatedTarget;
            // Extrahiere URL aus data-delete-url Attribut
            var deleteUrl = button.getAttribute('data-delete-url');
            // Finde den "Bestätigen"-Button im Modal und setze den Link
            var confirmBtn = deleteModal.querySelector('#confirm-delete-btn');
            if (confirmBtn) {
                confirmBtn.setAttribute('href', deleteUrl);
            }
        });
    }
});
</script>

    
   
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
                        parent: catId 
                    });
                });
            });

            fetch('admincenter.php?site=dashboard_navigation&ajax_sort=true', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'new_order_data=' + encodeURIComponent(JSON.stringify(sortData))
            })
            .then(response => {
                statusIndicator.style.opacity = "1";
                setTimeout(() => { statusIndicator.style.opacity = "0"; }, 2000);
            });
        };

        new Sortable(document.getElementById('main-sort-container'), {
            handle: '.sortable-handle-cat',
            animation: 200,
            ghostClass: 'sort-ghost',
            onEnd: autoSave
        });

        document.querySelectorAll('.sub-sort-container').forEach(container => {
            new Sortable(container, {
                handle: '.sortable-handle-link',
                animation: 200,
                group: 'shared-links',
                ghostClass: 'sort-ghost',
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
