<?php
// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\NavigationUpdater;
use nexpell\AccessControl;

AccessControl::checkAdminAccess('ac_static');

$CAPCLASS = new \nexpell\Captcha;
global $_database;

// 1. Sprachen laden
$languages = [];
$res = mysqli_query($_database, "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $languages[$row['iso_639_1']] = $row['name_de'];
}

// 2. Aktive Sprache bestimmen
$currentLang = null;

// 2️⃣ Aktive Sprache bestimmen

if (!empty($_SESSION['static_active_lang'])) {
    $currentLang = $_SESSION['static_active_lang'];
    unset($_SESSION['static_active_lang']);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['active_lang'])) {
    $currentLang = $_POST['active_lang'];
}
elseif (!empty($_SESSION['language'])) {
    $currentLang = $_SESSION['language'];
}
else {
    $currentLang = $languageService->detectLanguage();
}

// 4️⃣ Sicherheit: nur erlaubte Sprachen
if (!isset($languages[$currentLang])) {
    $currentLang = array_key_first($languages); // meist 'de'
}

/* =========================================================
   SPEICHERN
========================================================= */
if (isset($_POST['save'])) {

    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_redirect('admincenter.php?site=settings_static', 'danger', 'alert_transaction_invalid', false);
    }

    $nameArray  = $_POST['message'] ?? [];
    $activeLang = $_POST['active_lang'] ?? 'de';
    $editor     = isset($_POST['editor']) ? '1' : '0';
    $date       = time();
    $categoryID = (int)($_POST['categoryID'] ?? 0);

    $staticID = isset($_POST['staticID']) ? (int)$_POST['staticID'] : null;
    $isUpdate = !empty($staticID);

    $access_roles      = isset($_POST['access_roles']) ? (array)$_POST['access_roles'] : [];
    $access_roles_json = mysqli_real_escape_string(
        $_database,
        json_encode($access_roles, JSON_UNESCAPED_UNICODE)
    );

    /* =====================================================
       settings_static (META)
    ===================================================== */
    if ($isUpdate) {

        safe_query("
            UPDATE settings_static
            SET access_roles = '$access_roles_json',
                date = '$date',
                categoryID = '$categoryID'
            WHERE staticID = '$staticID'
        ");

    } else {

        safe_query("
            INSERT INTO settings_static (access_roles, date, categoryID)
            VALUES ('$access_roles_json', '$date', '$categoryID')
        ");

        $staticID = (int)mysqli_insert_id($_database);
    }

    /* =====================================================
       CONTENT – nur aktive Sprache
    ===================================================== */
    if (isset($nameArray[$activeLang])) {

        $html = mysqli_real_escape_string(
            $_database,
            $nameArray[$activeLang]
        );

        safe_query("
            INSERT INTO settings_content_lang
                (content_key, language, content, updated_at)
            VALUES
                ('static_$staticID', '$activeLang', '$html', NOW())
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                updated_at = NOW()
        ");
    }

    /* =====================================================
       TITLE – nur aktive Sprache
    ===================================================== */
    if (isset($_POST['title_lang'][$activeLang])) {

        $title = mysqli_real_escape_string(
            $_database,
            $_POST['title_lang'][$activeLang]
        );

        safe_query("
            INSERT INTO settings_content_lang
                (content_key, language, content, updated_at)
            VALUES
                ('static_title_$staticID', '$activeLang', '$title', NOW())
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                updated_at = NOW()
        ");
    }

    /* =====================================================
       NAVIGATION
    ===================================================== */

    $snavID = (int)($_POST['snavID'] ?? 0);

    if (!$snavID) {
        $row = mysqli_fetch_assoc(safe_query("
            SELECT snavID
            FROM navigation_website_sub
            WHERE url = 'index.php?site=static&staticID=$staticID'
            LIMIT 1
        "));
        $snavID = (int)($row['snavID'] ?? 0);
    }

    if ($snavID > 0) {

        safe_query("
            UPDATE navigation_website_sub
            SET mnavID = '$categoryID',
                last_modified = NOW()
            WHERE snavID = '$snavID'
        ");

    } else {

        $modulname = 'static_' . (int)$staticID;

        $check = safe_query("
            SELECT snavID
            FROM navigation_website_sub
            WHERE modulname = '$modulname'
        ");

        if (mysqli_num_rows($check) == 0) {

            safe_query("
                INSERT INTO navigation_website_sub
                    (mnavID, modulname, url, sort, indropdown, last_modified)
                VALUES
                    (
                        '$categoryID',
                        '$modulname',
                        'index.php?site=static&staticID=$staticID',
                        1,
                        1,
                        NOW()
                    )
            ");

            $snavID = (int)mysqli_insert_id($_database);

        } else {

            $row = mysqli_fetch_assoc($check);
            $snavID = (int)$row['snavID'];
        }
    }

    /* =====================================================
       NAVIGATION TITLE – nur aktive Sprache
    ===================================================== */
    if ($snavID && isset($_POST['title_lang'][$activeLang])) {

        $navTitle = mysqli_real_escape_string(
            $_database,
            $_POST['title_lang'][$activeLang]
        );

        safe_query("
            INSERT INTO navigation_website_lang
                (content_key, language, content, updated_at)
            VALUES
                ('nav_sub_$snavID', '$activeLang', '$navTitle', NOW())
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                updated_at = NOW()
        ");
    }

    $_SESSION['static_active_lang'] = $activeLang;

    nx_redirect(
        'admincenter.php?site=settings_static&action=edit&staticID=' . $staticID,
        'success',
        'alert_saved',
        false
    );
}


/* =========================================================
   LÖSCHEN
========================================================= */
elseif (isset($_GET['delete'])) {
    if ($CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'] ?? '')) {

        $staticID = (int)($_GET['staticID'] ?? 0);

        // PATCH: Navigationseintrag entfernen (fehlte)
        safe_query("
            DELETE FROM navigation_website_sub
            WHERE url = 'index.php?site=static&staticID=$staticID'
        ");

        // Static-Meta löschen
        safe_query("
            DELETE FROM settings_static
            WHERE staticID = '$staticID'
        ");

        // Inhalte & Titel löschen
        safe_query("
            DELETE FROM settings_content_lang
            WHERE content_key IN ('static_$staticID','static_title_$staticID')
        ");

        nx_redirect(
            'admincenter.php?site=settings_static',
            'success',
            'alert_deleted',
            false
        );
    }
}


/* =========================================================
   FORMULAR (ADD / EDIT)
========================================================= */
if (isset($_GET['action']) && ($_GET['action'] == "add" || $_GET['action'] == "edit")) {

    $currentLang = strtolower($languageService->detectLanguage());

    $staticID = isset($_GET['staticID']) ? (int)$_GET['staticID'] : 0;
    $ds = ($staticID)
        ? mysqli_fetch_array(safe_query("SELECT * FROM settings_static WHERE staticID='$staticID'"))
        : [];

    // CONTENT laden
    $content = [];
    $lastUpdate = [];
    if ($staticID) {
        $res_lang = safe_query("
            SELECT language, content, updated_at
            FROM settings_content_lang
            WHERE content_key = 'static_$staticID'
        ");
        while ($rl = mysqli_fetch_assoc($res_lang)) {
            $content[strtolower($rl['language'])] = $rl['content'];
            $lastUpdate[strtolower($rl['language'])] = $rl['updated_at'];
        }
    }

    // TITLE laden
    $titles = [];
    if ($staticID) {
        $res_t = safe_query("
            SELECT language, content
            FROM settings_content_lang
            WHERE content_key = 'static_title_$staticID'
        ");
        while ($rt = mysqli_fetch_assoc($res_t)) {
            $titles[strtolower($rt['language'])] = $rt['content'];
        }
    }

    // Rollen aus DB laden
    $role_result = safe_query("SELECT role_name, modulname FROM user_roles WHERE is_active=1 ORDER BY role_name ASC");
    $allRoles = [];
    while ($r = mysqli_fetch_assoc($role_result)) {
        $allRoles[] = $r;
    }

    // Vorhandene Rollen auslesen
    $selectedRoles = [];
    if (!empty($ds['access_roles'])) {
        $selectedRoles = json_decode($ds['access_roles'], true);
    }
    if (!is_array($selectedRoles)) {
        $selectedRoles = [];
    }

    /* PATCH: Bei neuer Seite → Gast standardmäßig aktiv */
    if (empty($selectedRoles)) {
        $selectedRoles = ['ac_guest'];
    }


    $roleNameToKey = [];
    foreach ($allRoles as $r) {
        $roleNameToKey[(string)$r['role_name']] = (string)$r['modulname'];
    }

    $selectedRoleKeys = [];
    foreach ($selectedRoles as $sr) {
        $sr = (string)$sr;

        if (isset($allRoles) && str_starts_with($sr, 'ac_')) {
            $selectedRoleKeys[] = $sr;
            continue;
        }

        if (isset($roleNameToKey[$sr])) {
            $selectedRoleKeys[] = $roleNameToKey[$sr];
            continue;
        }

        $selectedRoleKeys[] = $sr;
    }

    // Checkboxen generieren
    $leftColumn = '';
    $rightColumn = '';
    $half = ceil(count($allRoles) / 2);
    $i = 0;

    foreach ($allRoles as $role) {
        $value = (string)$role['modulname'];
        $label = (string)$role['role_name'];

        $checked = in_array($value, $selectedRoleKeys, true) ? 'checked="checked"' : '';
        $checkboxId = 'access_role_' . $i;

        $checkbox = '<div class="form-check d-flex align-items-start gap-2 mb-2">'
            . '<input class="form-check-input mt-1" type="checkbox"'
            . ' id="' . htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') . '"'
            . ' name="access_roles[]"'
            . ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
            . ' ' . $checked . '>'
            . '<label class="form-check-label flex-grow-1"'
            . ' for="' . htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</label>'
            . '</div>';

        if ($i < $half) {
            $leftColumn .= $checkbox;
        } else {
            $rightColumn .= $checkbox;
        }
        $i++;
    }

    

    $role_result = safe_query("SELECT role_name, modulname FROM user_roles WHERE is_active=1 ORDER BY role_name ASC");
    $selectedRoles = !empty($ds['access_roles']) ? json_decode($ds['access_roles'], true) : [];

$roleCheckboxes = ' <div class="row g-2"> <div class="col-12 col-md-6">' . $leftColumn . '</div> <div class="col-12 col-md-6">' . $rightColumn . '</div> </div>';

$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();
?>

<script>
    const lastUpdateByLang = <?= json_encode($lastUpdate ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>

<form method="post" id="staticForm" action="admincenter.php?site=settings_static">

<div class="nx-lang-editor">

    <input type="hidden" name="captcha_hash" value="<?= htmlspecialchars($hash) ?>">
    <input type="hidden" name="staticID" value="<?= (int)$staticID ?>">
    <input type="hidden" name="active_lang" id="active_lang" value="<?= htmlspecialchars($currentLang) ?>">
    <input type="hidden" name="snavID" value="<?= (int)($snavID ?? 0) ?>">

    <div class="row g-4">

        <!-- =========================
             LEFT SIDE (EDITOR)
        ========================== -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4 mt-3">

                <div class="card-header d-flex align-items-center">
                    <div class="card-title mb-0">
                        <i class="bi bi-file-earmark-richtext"></i>
                        <?= $languageService->get('static_pages') ?>
                        <small class="text-muted">
                            <?= $staticID ? $languageService->get('edit') : $languageService->get('add') ?>
                        </small>
                    </div>

                    <div class="d-flex align-items-center gap-3 ms-auto">

                        <!-- LANGUAGE SWITCH -->
                        <div class="btn-group" id="lang-switch">
                            <?php foreach ($languages as $iso => $label): ?>
                                <button type="button"
                                        class="btn <?= $iso === $currentLang ? 'btn-primary' : 'btn-secondary' ?>"
                                        data-lang="<?= $iso ?>">
                                    <?= strtoupper($iso) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <!-- LAST UPDATE -->
                        <div class="text-end ps-3">
                            <div class="text-muted small" id="last-update-box">
                                <?php if (!empty($lastUpdate[$currentLang])): ?>
                                    <i class="bi bi-clock-history me-1"></i>
                                    <span id="last-update-text">
                                        <?= date('d.m.Y H:i', strtotime($lastUpdate[$iso])) ?>
                                    </span>
                                <?php else: ?>
                                    <span id="last-update-text">–</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">

                    <!-- =========================
                         CONTENT EDITOR
                    ========================== -->
                    <textarea class="form-control"
                              id="nx-editor-main"
                              data-editor="nx_editor"
                              rows="20"><?= htmlspecialchars($content[$currentLang] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

                    <?php foreach ($languages as $iso => $label): ?>
                        <input type="hidden"
                               name="message[<?= $iso ?>]"
                               id="content_<?= $iso ?>"
                               value="<?= htmlspecialchars($content[$iso] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>

                    <div class="mt-4 d-flex gap-2">

                        <button class="btn btn-primary btn-lg" type="submit" name="save">
                            <i class="bi bi-save"></i>
                            <?= $languageService->get('save') ?>
                        </button>

                        <a href="admincenter.php?site=settings_static"
                           class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-left"></i>
                            <?= $languageService->get('back') ?>
                        </a>

                    </div>

                </div>
            </div>
        </div>

        <!-- =========================
             RIGHT SIDE (SETTINGS)
        ========================== -->
        <div class="col-lg-4">

            <!-- SETTINGS CARD -->
            <div class="card shadow-sm border-0 mb-4 mt-3">
                <div class="card-header">
                    <div class="card-title mb-0">
                        <i class="bi bi-sliders"></i>
                        <?= $languageService->get('settings') ?>
                    </div>
                </div>

                <div class="card-body">

                    <!-- CATEGORY -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <?= $languageService->get('category') ?>
                        </label>

                        <select name="categoryID" class="form-select">
                            <option value="0">
                                <?= $languageService->get('no_category') ?>
                            </option>

                            <?php
                            $cat_res = safe_query("
                                SELECT 
                                    m.mnavID,
                                    COALESCE(l_cur.content, l_de.content, '---') AS name
                                FROM navigation_website_main m
                                LEFT JOIN navigation_website_lang l_cur 
                                    ON l_cur.content_key = CONCAT('nav_main_', m.mnavID)
                                   AND l_cur.language = '$currentLang'
                                LEFT JOIN navigation_website_lang l_de
                                    ON l_de.content_key = CONCAT('nav_main_', m.mnavID)
                                   AND l_de.language = 'de'
                                ORDER BY m.sort ASC
                            ");

                            while ($cat = mysqli_fetch_assoc($cat_res)) {
                                $sel = ((int)($ds['categoryID'] ?? 0) === (int)$cat['mnavID']) ? 'selected' : '';
                                echo '<option value="' . (int)$cat['mnavID'] . '" ' . $sel . '>'
                                    . htmlspecialchars($cat['name'])
                                    . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- TITLE -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <?= $languageService->get('navigation_title') ?>
                        </label>

                        <input type="text"
                               id="nx-title-input"
                               class="form-control"
                               value="<?= htmlspecialchars($titles[$currentLang] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <?php foreach ($languages as $iso => $label): ?>
                            <input type="hidden"
                                   name="title_lang[<?= $iso ?>]"
                                   id="title_<?= $iso ?>"
                                   value="<?= htmlspecialchars($titles[$iso] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>

            <!-- ACCESS LEVEL -->
            <div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-header">
                    <div class="card-title mb-0">
                        <i class="bi bi-shield-lock"></i>
                        <?= $languageService->get('accesslevel') ?>
                    </div>
                </div>

                <div class="card-body">

                    <div class="alert alert-info">
                        <?= $languageService->get('alert_rolecheckboxes') ?>
                    </div>

                    <?= $roleCheckboxes ?>

                </div>
            </div>

        </div>
    </div>

</div>
</form>

<?php
}
 else {

echo '<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-file-earmark-plus"></i>
            <span>' . $languageService->get('static_pages') . '</span>
            <small class="text-muted">' . $languageService->get('overview') . '</small>
        </div>
    </div>
    <div class="card-body">

        <div class="col-md-8 mb-4">
            <a href="admincenter.php?site=settings_static&amp;action=add"
               class="btn btn-secondary" type="button">
                ' . $languageService->get('add') . '
            </a>
        </div>';

$ergebnis = safe_query("SELECT * FROM settings_static ORDER BY staticID");

echo '<table class="table">
    <thead>
        <tr>
            <th>' . $languageService->get('id') . '</th>
            <th>' . $languageService->get('title') . '</th>
            <th>' . $languageService->get('accesslevel') . '</th>
            <th>' . $languageService->get('actions') . '</th>
        </tr>
    </thead>';

$i = 1;

$CAPCLASS = new \nexpell\Captcha;
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();

$currentLang = $languageService->detectLanguage();

while ($ds = mysqli_fetch_array($ergebnis)) {

    $staticIdInt = (int)$ds['staticID'];

    /* =====================================================
       ACCESS ROLES
    ===================================================== */
    $roles = [];

    if (!empty($ds['access_roles'])) {
        $roles = json_decode($ds['access_roles'], true);
        if (!is_array($roles)) {
            $roles = [];
        }
    }

    $roleMap = [];
    $roleRes = safe_query("
        SELECT modulname, role_name
        FROM user_roles
        WHERE is_active = 1
    ");

    while ($r = mysqli_fetch_assoc($roleRes)) {
        $roleMap[(string)$r['modulname']] = (string)$r['role_name'];
    }

    $accesslevel = empty($roles)
        ? $languageService->get('public')
        : implode(', ', array_map(function ($roleKey) use ($roleMap) {

            $key = trim((string)$roleKey);

            if (isset($roleMap[$key])) {
                return htmlspecialchars($roleMap[$key], ENT_QUOTES, 'UTF-8');
            }

            return htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

        }, $roles));

    /* =====================================================
       TITLE (navigation_title → title → fallback)
    ===================================================== */
    $title = '';

    // 1️⃣ Navigation Title
    $resNav = safe_query("
        SELECT content
        FROM settings_content_lang
        WHERE content_key = 'static_navtitle_$staticIdInt'
          AND language = '" . escape($currentLang) . "'
        LIMIT 1
    ");

    if ($row = mysqli_fetch_assoc($resNav)) {
        $title = trim((string)$row['content']);
    }

    // 2️⃣ Fallback: normaler Titel
    if ($title === '') {
        $resTitle = safe_query("
            SELECT content
            FROM settings_content_lang
            WHERE content_key = 'static_title_$staticIdInt'
              AND language = '" . escape($currentLang) . "'
            LIMIT 1
        ");

        if ($row = mysqli_fetch_assoc($resTitle)) {
            $title = trim((string)$row['content']);
        }
    }

    // 3️⃣ Letzter Fallback
    if ($title === '') {
        $title = 'Static #' . $staticIdInt;
    }

    $deleteUrl = 'admincenter.php?site=settings_static&delete=true'
        . '&staticID=' . $staticIdInt
        . '&captcha_hash=' . rawurlencode($hash);

    echo '<tr>
        <td>' . $staticIdInt . '</td>

        <td>
            <a href="../index.php?site=static&amp;staticID=' . $staticIdInt . '"
               target="_blank">
                ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '
            </a>
        </td>

        <td>' . $accesslevel . '</td>

        <td>
            <a href="admincenter.php?site=settings_static&amp;action=edit&amp;staticID=' . $staticIdInt . '"
               class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto">
                <i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '
            </a>

            <button type="button"
                class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto"
                data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal"
                data-delete-url="' . htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') . '">
                <i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '
            </button>
        </td>
    </tr>';

    $i++;
}

echo '</table>
</div>
</div>';

}
?>