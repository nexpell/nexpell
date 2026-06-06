<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LoginSecurity;
use nexpell\Email;
use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_user_roles');

$action = $_GET['action'] ?? '';

require_once "../system/config.inc.php";
require_once "../system/functions.php";

if (!function_exists('nx_user_roles_nav_language')) {
    function nx_user_roles_nav_language($languageService): string
    {
        $lang = 'de';
        if (is_object($languageService) && method_exists($languageService, 'detectLanguage')) {
            $lang = (string)$languageService->detectLanguage();
        } elseif (isset($GLOBALS['lang'])) {
            $lang = (string)$GLOBALS['lang'];
        }

        $lang = substr(strtolower($lang), 0, 2);
        return preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'de';
    }
}

if ($action == "edit_role_rights") {
 
// CSRF-Token generieren
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Überprüfen, ob der Benutzer berechtigt ist
if (!$userID || !checkUserRoleAssignment($userID)) nx_redirect('/admin/admincenter.php?site=user_roles&action=roles', 'danger', 'alert_no_role_assigned', false);

$categoryRights = [];
$moduleRights = [];

if (isset($_GET['roleID'])) {
    $roleID = (int)$_GET['roleID'];
    $navLang = nx_user_roles_nav_language($languageService ?? null);
    $navLangEsc = $_database->real_escape_string($navLang);

    // Modul-Liste abrufen
    $modules = [];
    $result = safe_query("
        SELECT
            l.linkID,
            l.catID,
            l.modulname,
            COALESCE(NULLIF(t.content, ''), CONCAT('Link ', l.linkID)) AS name
        FROM navigation_dashboard_links l
        LEFT JOIN navigation_dashboard_lang t
            ON t.content_key = CONCAT('nav_link_', l.linkID)
           AND t.language = '{$navLangEsc}'
        ORDER BY l.sort ASC
    ");
    if (!$result) {
        die($languageService->get('error_fetching_modules') . ": " . $_database->error);
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $modules[] = $row;
    }

    // Module nach catID gruppieren (nachdem ALLE geladen wurden)
    $modulesByCategory = [];
    foreach ($modules as $mod) {
        $catID = (int)($mod['catID'] ?? 0);
        $modulesByCategory[$catID][] = $mod;
    }

    // Kategorie-Liste abrufen
    $categories = [];
    $result = safe_query("
        SELECT
            c.catID,
            COALESCE(NULLIF(t.content, ''), CONCAT('Kategorie ', c.catID)) AS name,
            c.modulname
        FROM navigation_dashboard_categories c
        LEFT JOIN navigation_dashboard_lang t
            ON t.content_key = CONCAT('nav_cat_', c.catID)
           AND t.language = '{$navLangEsc}'
        ORDER BY c.sort ASC
    ");
    if (!$result) {
        die($languageService->get('error_fetching_categories') . ": " . $_database->error);
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }

    // Bestehende Rechte laden (ohne accessID)
    $stmt = $_database->prepare("SELECT type, modulname FROM user_role_admin_navi_rights WHERE roleID = ?");
    $stmt->bind_param('i', $roleID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['type'] === 'link') {
            $moduleRights[] = $row['modulname'];
        } elseif ($row['type'] === 'category') {
            $categoryRights[] = $row['modulname'];
        }
    }

    // Rechte speichern (POST)
    // Rechte speichern (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roleID'], $_POST['save_rights'])) {

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            nx_redirect('/admin/admincenter.php?site=user_roles&action=roles', 'danger', 'alert_invalid_csrf', false);
        }

        $roleID = (int)$_POST['roleID'];
        if ($roleID <= 0) {
            nx_redirect('/admin/admincenter.php?site=user_roles&action=roles', 'danger', 'alert_invalid_id', false);
        }

        // Reset
        safe_query("DELETE FROM user_role_admin_navi_rights WHERE roleID = {$roleID}");

        $modules   = array_unique($_POST['modules'] ?? []);
        $categories = array_unique($_POST['category'] ?? []);

        /* ============================================================
           LINKS speichern + Kategorien automatisch sammeln
        ============================================================ */

        foreach ($modules as $mod) {
            $mod = $_database->real_escape_string($mod);

            safe_query("
                INSERT IGNORE INTO user_role_admin_navi_rights
                (roleID, type, modulname)
                VALUES ({$roleID}, 'link', '{$mod}')
            ");

            $res = safe_query("
                SELECT c.modulname
                FROM navigation_dashboard_links l
                JOIN navigation_dashboard_categories c ON c.catID = l.catID
                WHERE l.modulname = '{$mod}'
                LIMIT 1
            ");

            if ($row = mysqli_fetch_assoc($res)) {
                $categories[] = $row['modulname'];
            }
        }

        /* ============================================================
           Kategorien speichern (manuell + automatisch)
        ============================================================ */

        foreach (array_unique($categories) as $cat) {
            $cat = $_database->real_escape_string($cat);

            safe_query("
                INSERT IGNORE INTO user_role_admin_navi_rights
                (roleID, type, modulname)
                VALUES ({$roleID}, 'category', '{$cat}')
            ");
        }

        nx_audit_update(
            'user_roles',
            (string)$roleID,
            true,
            null,
            '/admin/admincenter.php?site=user_roles&action=roles',
            [
                'modules'    => count($modules),
                'categories' => count(array_unique($categories))
            ]
        );

        nx_redirect('/admin/admincenter.php?site=user_roles&action=roles', 'success', 'alert_rights_updated', false);
    }

}
?>
    <!-- Seitenkopf -->
    <form method="post" id="rightsForm">
        <input type="hidden" name="roleID" value="<?= $roleID ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

        <div class="row g-4">
            <!-- Rechte -->
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm border-0 mb-4 mt-3">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="bi bi-person-gear"></i>
                            <span><?= $languageService->get('role_rights') ?></span>
                            <small class="small-muted"><?= $languageService->get('edit_role_rights') ?></small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex gap-3 align-items-start mb-3" role="alert">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                            <div class="small">
                                <div class="fw-semibold mb-1"><?= $languageService->get('admin_rights_per_role') ?></div>
                                <?= $languageService->get('admin_rights_per_role_info') ?>
                            </div>
                        </div>

                        <div class="accordion rightsAccordion" id="rightsAccordion">
                            <?php foreach ($categories as $cat): ?>
                                <?php
                                $translate = new multiLanguage($lang);
                                $translate->detectLanguages($cat['name']);
                                $catTitle = $translate->getTextByLanguage($cat['name']);
                                $catKey   = $cat['modulname'];
                                $catID    = (int)$cat['catID'];
                                $catModules = $modulesByCategory[$catID] ?? [];

                                $totalModules = count($catModules);
                                $selectedModules = 0;
                                if ($totalModules > 0) {
                                    foreach ($catModules as $m0) {
                                        if (in_array($m0['modulname'], $moduleRights, true)) $selectedModules++;
                                    }
                                }
                                $catChecked = in_array($catKey, $categoryRights, true);
                                $statusText = $languageService->get('inactive');
                                $statusClass = 'text-bg-secondary';

                                if ($totalModules === 0) {
                                    if ($catChecked) {
                                        $statusText  = $languageService->get('active');
                                        $statusClass = 'text-bg-success';
                                    }
                                } else {
                                    if ($selectedModules === 0) {
                                        $statusText  = $languageService->get('inactive');
                                        $statusClass = 'text-bg-secondary';
                                    } elseif ($selectedModules === $totalModules) {
                                        $statusText  = $languageService->get('active');
                                        $statusClass = 'text-bg-success';
                                    } else {
                                        $statusText  = $languageService->get('partly_active');
                                        $statusClass = 'text-bg-warning';
                                    }
                                }
                                ?>
                                <div class="accordion-item border-0 shadow-sm mb-3" data-category="<?= $catID ?>">
                                    <h2 class="accordion-header" id="heading_<?= $catID ?>">
                                        <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#collapse_<?= $catID ?>"
                                                aria-expanded="false"
                                                aria-controls="collapse_<?= $catID ?>">
                                            <div class="d-flex align-items-center justify-content-between w-100 gap-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input class="form-check-input mt-0 category-checkbox"
                                                        type="checkbox"
                                                        name="category[]"
                                                        id="cat_<?= $catID ?>"
                                                        value="<?= htmlspecialchars($catKey) ?>"
                                                        <?= $catChecked ? 'checked' : '' ?>
                                                        onclick="event.stopPropagation();">
                                                    <label class="form-check-label fw-semibold" for="cat_<?= $catID ?>" onclick="event.stopPropagation();">
                                                        <?= htmlspecialchars($catTitle) ?>
                                                    </label>
                                                </div>

                                                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                                    <span class="badge text-bg-light border">
                                                        <span class="cat-count" data-selected="<?= (int)$selectedModules ?>" data-total="<?= (int)$totalModules ?>">
                                                            <?= (int)$selectedModules ?>/<?= (int)$totalModules ?>
                                                        </span>
                                                        <?= $languageService->get('modules') ?>
                                                    </span>
                                                    <span class="badge <?= $statusClass ?> cat-status">
                                                        <?= htmlspecialchars($statusText) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>

                                    <div id="collapse_<?= $catID ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?= $catID ?>" data-bs-parent="#rightsAccordion">
                                        <div class="accordion-body pt-3">
                                            <?php if (!empty($catModules)): ?>
                                                <div class="row g-2 module-grid" data-category="<?= $catID ?>">
                                                    <?php foreach ($catModules as $mod):
                                                        $translate->detectLanguages($mod['name']);
                                                        $modTitle = $translate->getTextByLanguage($mod['name']);
                                                        $modKey = $mod['modulname'];
                                                        $isChecked = in_array($modKey, $moduleRights, true);
                                                    ?>
                                                        <div class="col-12 col-md-6 col-xl-4 module-item">
                                                            <label class="d-flex align-items-start gap-2 p-2 w-100 h-100 module-card">
                                                                <input class="form-check-input mt-1 module-checkbox"
                                                                    type="checkbox"
                                                                    name="modules[]"
                                                                    value="<?= htmlspecialchars($modKey) ?>"
                                                                    <?= $isChecked ? 'checked' : '' ?>
                                                                    data-category="<?= $catID ?>">
                                                                <span class="small">
                                                                    <span class="fw-semibold module-title"><?= htmlspecialchars($modTitle) ?></span>
                                                                    <span class="d-block text-muted" style="font-size:.75rem; line-height:1.2;">
                                                                        <?= htmlspecialchars($modKey) ?>
                                                                    </span>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted small fst-italic">
                                                    <?= $languageService->get('no_modules_in_category') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-top pt-3 mt-4">
                            <button type="submit" name="save_rights" class="btn btn-primary">
                                <?= $languageService->get('save') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
            <!-- Suche -->
            <div class="card shadow-sm border-0 mb-4 mt-3">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-search"></i>
                        <span><?= $languageService->get('search') ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="vstack gap-3">

                        <div class="border rounded-3 p-3 bg-body-tertiary">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-12">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text"
                                            class="form-control"
                                            id="moduleSearch"
                                            placeholder="<?= $languageService->get('search') ?>">
                                    </div>
                                    <div class="form-text mb-0">
                                        <?= $languageService->get('search_catg_info') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-3">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2">
                                <div class="d-flex flex-wrap justify-content-lg-start gap-2">
                                    <button type="button" class="btn btn-secondary" id="selectVisible">
                                        <i class="bi bi-check2-square"></i> <?= $languageService->get('select_visible') ?>
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="clearVisible">
                                        <i class="bi bi-square"></i> <?= $languageService->get('clear_visible') ?>
                                    </button>
                                </div>
                                <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                                    <button type="button" class="btn btn-secondary" id="expandAll">
                                        <i class="bi bi-arrow-bar-down"></i> <?= $languageService->get('expand_all') ?>
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="collapseAll">
                                        <i class="bi bi-arrow-bar-up"></i> <?= $languageService->get('collapse_all') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
</form>

<style>
/* Kompaktere Cards im Rechte-Grid */
.module-card { background: #fff; }
.module-card:hover { background: rgba(0,0,0,.02); }
.rightsAccordion .accordion-button { gap: .5rem; }
.rightsAccordion .accordion-button .form-check-input { transform: translateY(1px); }
</style>

<script>
(function () {
    const moduleSearch = document.getElementById('moduleSearch');
    const selectVisible = document.getElementById('selectVisible');
    const clearVisible = document.getElementById('clearVisible');
    const expandAll = document.getElementById('expandAll');
    const collapseAll = document.getElementById('collapseAll');

    const categoryItems = Array.from(document.querySelectorAll('[data-category]'));
    const categoryCheckboxes = Array.from(document.querySelectorAll('.category-checkbox'));
    const moduleCheckboxes = Array.from(document.querySelectorAll('.module-checkbox'));

    function updateCategoryState(catID) {
        const catBox = document.querySelector('#cat_' + catID);
        const catItem = document.querySelector('.accordion-item[data-category="' + catID + '"]');
        const statusBadge = catItem ? catItem.querySelector('.cat-status') : null;
        const countEl = catItem ? catItem.querySelector('.cat-count') : null;

        const mods = moduleCheckboxes.filter(m => String(m.dataset.category) === String(catID));
        const total = mods.length;
        const selected = mods.filter(m => m.checked).length;

        if (total > 0) {
            if (selected === 0) {
                catBox.checked = false;
                catBox.indeterminate = false;
            } else if (selected === total) {
                catBox.checked = true;
                catBox.indeterminate = false;
            } else {
                catBox.checked = false;
                catBox.indeterminate = true;
            }
        }

        if (statusBadge) {
            const tActive = '<?= addslashes($languageService->get('active')) ?>';
            const tInactive = '<?= addslashes($languageService->get('inactive')) ?>';
            const tPartial = '<?= addslashes($languageService->get('partly_active')) ?>';

            statusBadge.classList.remove('text-bg-success', 'text-bg-secondary', 'text-bg-warning');

            if (total === 0) {
                if (catBox.checked) {
                statusBadge.classList.add('text-bg-success');
                statusBadge.textContent = tActive;
                } else {
                statusBadge.classList.add('text-bg-secondary');
                statusBadge.textContent = tInactive;
                }
            } else if (selected === 0) {
                statusBadge.classList.add('text-bg-secondary');
                statusBadge.textContent = tInactive;
            } else if (selected === total) {
                statusBadge.classList.add('text-bg-success');
                statusBadge.textContent = tActive;
            } else {
                statusBadge.classList.add('text-bg-warning');
                statusBadge.textContent = tPartial;
            }
        }
        if (countEl) {
            countEl.textContent = selected + '/' + total;
            countEl.dataset.selected = selected;
            countEl.dataset.total = total;
        }
    }

    // Kategorie -> alle Module in dieser Kategorie toggeln
    categoryCheckboxes.forEach(catCheckbox => {
        catCheckbox.addEventListener('change', () => {
            const catID = catCheckbox.id.replace('cat_', '');
            const mods = moduleCheckboxes.filter(m => String(m.dataset.category) === String(catID));
            mods.forEach(m => { m.checked = catCheckbox.checked; });
            catCheckbox.indeterminate = false;
            updateCategoryState(catID);
        });
    });

    // Module -> Kategorie-State aktualisieren
    moduleCheckboxes.forEach(mod => {
        mod.addEventListener('change', () => {
            updateCategoryState(mod.dataset.category);
        });
    });

    // Initial states
    const catIDs = new Set(moduleCheckboxes.map(m => m.dataset.category));
    catIDs.forEach(id => updateCategoryState(id));

    // Suche: Module/Kategorien filtern
    moduleSearch?.addEventListener('input', () => {
        const q = (moduleSearch.value || '').trim().toLowerCase();

        document.querySelectorAll('.accordion-item[data-category]').forEach(catItem => {
            const catId = catItem.getAttribute('data-category');

            // Kategorie-Titel (Label-Text)
            const catLabel = catItem.querySelector('label.form-check-label');
            const catText = (catLabel?.textContent || '').trim().toLowerCase();
            const catMatches = q !== '' && catText.includes(q);

            // Module dieser Kategorie
            const moduleItems = Array.from(catItem.querySelectorAll('.module-item'));
            let anyModuleVisible = false;

            moduleItems.forEach(mi => {
                const t = (mi.textContent || '').trim().toLowerCase();

                // Wenn Kategorie matched -> alle Module sichtbar, sonst nur Module die selbst matchen
                const show = (q === '') || catMatches || t.includes(q);
                mi.style.display = show ? '' : 'none';
                if (show) anyModuleVisible = true;
            });

            // Kategorie anzeigen, wenn:
            // - keine Suche (q leer) oder
            // - Kategorie matched oder
            // - mindestens ein Modul sichtbar ist
            const showCategory = (q === '') || catMatches || anyModuleVisible;
            catItem.style.display = showCategory ? '' : 'none';

            // Bei aktiver Suche passende Kategorien automatisch aufklappen
            const collapseEl = catItem.querySelector('.accordion-collapse');
            if (collapseEl) {
                const c = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                if (q !== '' && showCategory) c.show();
                if (q === '') c.hide(); // optional: zurück in “zugeklappt”
            }
        });
    });
    // Sichtbare auswählen/abwählen
    function setVisibleModules(checked) {
        document.querySelectorAll('.module-item').forEach(item => {
            if (item.style.display === 'none') return;
            const cb = item.querySelector('input.module-checkbox');
            if (cb) cb.checked = checked;
        });

        // alle Kategorie-States aktualisieren
        catIDs.forEach(id => updateCategoryState(id));
    }

    selectVisible?.addEventListener('click', () => setVisibleModules(true));
    clearVisible?.addEventListener('click', () => setVisibleModules(false));

    // Expand / Collapse all (Bootstrap Collapse)
    expandAll?.addEventListener('click', () => {
        document.querySelectorAll('#rightsAccordion .accordion-collapse').forEach(el => {
            const c = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            c.show();
        });
    });

    collapseAll?.addEventListener('click', () => {
        document.querySelectorAll('#rightsAccordion .accordion-collapse').forEach(el => {
            const c = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            c.hide();
        });
    });
})();
</script>

<?php

} 
elseif ($action === "user_role_details") {

    if (!isset($_GET['userID'])) nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_no_user_selected', false);

    $userID = (int)$_GET['userID'];
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
    $userResult = safe_query("SELECT username FROM users WHERE userID = $userID");
    if (!mysqli_num_rows($userResult)) nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_user_not_found', false);

    $user = mysqli_fetch_assoc($userResult);
    $username = htmlspecialchars((string)($user['username'] ?? ''));

    $rolesResult = safe_query("
        SELECT r.roleID, r.role_name
        FROM user_roles r
        JOIN user_role_assignments ur ON ur.roleID = r.roleID
        WHERE ur.userID = $userID
        ORDER BY r.role_name ASC
    ");

    if (!mysqli_num_rows($rolesResult)) nx_redirect('admincenter.php?site=user_roles', 'info', 'alert_user_no_roles', false);

    $roles = [];
    while ($roleRow = mysqli_fetch_assoc($rolesResult)) {
        $roles[] = $roleRow;
    }

    $roleNamesEscaped = [];
    foreach ($roles as $r0) {
        $roleNamesEscaped[] = htmlspecialchars((string)($r0['role_name'] ?? ''));
    }
    $rolesText = implode(', ', $roleNamesEscaped);

    if (!isset($lang)) $lang = nx_user_roles_nav_language($languageService ?? null);
    if (!class_exists('multiLanguage')) {
        require_once BASE_PATH . '/system/core/classes/multiLanguage.php';
    }
    $translate = new multiLanguage($lang);
    $navLangEsc = $_database->real_escape_string($lang);

    $rolesHtml = '';

// Durch jede Rolle iterieren
foreach ($roles as $role) {
    $roleID   = (int)$role['roleID'];
    $roleName = htmlspecialchars($role['role_name']);

    // Rechte dieser Rolle laden (Links + Kategorien)
    $catOrder     = [];
    $catTitlesRaw = [];
    $catModules   = [];
    $catUnlocked  = [];

    // Link-Rechte (Module)
    $linkRightsQuery = "
        SELECT
            l.catID,
            COALESCE(NULLIF(ct.content, ''), CONCAT('Kategorie ', c.catID)) AS category_name,
            COALESCE(NULLIF(lt.content, ''), CONCAT('Link ', l.linkID)) AS module_name,
            ar.modulname
        FROM user_role_admin_navi_rights ar
        JOIN navigation_dashboard_links l
            ON LOWER(CONVERT(ar.modulname USING utf8mb4)) COLLATE utf8mb4_general_ci = LOWER(l.modulname)
        LEFT JOIN navigation_dashboard_categories c
            ON l.catID = c.catID
        LEFT JOIN navigation_dashboard_lang ct
            ON ct.content_key = CONCAT('nav_cat_', c.catID)
           AND ct.language = '{$navLangEsc}'
        LEFT JOIN navigation_dashboard_lang lt
            ON lt.content_key = CONCAT('nav_link_', l.linkID)
           AND lt.language = '{$navLangEsc}'
        WHERE ar.roleID = $roleID AND ar.type = 'link'
        ORDER BY c.sort ASC, l.sort ASC
    ";
    $linkRightsRes = safe_query($linkRightsQuery);

    while ($r = mysqli_fetch_assoc($linkRightsRes)) {
        $catID = (int)($r['catID'] ?? 0);
        if (!isset($catTitlesRaw[$catID])) {
            $catTitlesRaw[$catID] = $r['category_name'];
            $catOrder[] = $catID;
        }
        $catModules[$catID][] = [
            'module_name' => $r['module_name'],
            'modulname'   => $r['modulname'],
        ];
    }

    // Kategorienrechte (Kategorie freigeschaltet, ggf. ohne einzelne Module)
    $catRightsQuery = "
        SELECT
            c.catID,
            COALESCE(NULLIF(ct.content, ''), CONCAT('Kategorie ', c.catID)) AS category_name,
            ar.modulname
        FROM user_role_admin_navi_rights ar
        JOIN navigation_dashboard_categories c
            ON LOWER(CONVERT(ar.modulname USING utf8mb4)) COLLATE utf8mb4_general_ci = LOWER(c.modulname)
        LEFT JOIN navigation_dashboard_lang ct
            ON ct.content_key = CONCAT('nav_cat_', c.catID)
           AND ct.language = '{$navLangEsc}'
        WHERE ar.roleID = $roleID AND ar.type = 'category'
        ORDER BY c.sort ASC
    ";
    $catRightsRes = safe_query($catRightsQuery);

    while ($r = mysqli_fetch_assoc($catRightsRes)) {
        $catID = (int)($r['catID'] ?? 0);
        $catUnlocked[$catID] = true;

        if (!isset($catTitlesRaw[$catID])) {
            $catTitlesRaw[$catID] = $r['category_name'];
            $catOrder[] = $catID;
        }
    }

    // Wenn keinerlei Rechte vorhanden sind, dennoch eine Info ausgeben
    $hasAnyRights = (count(array_filter($catModules)) > 0);
    $rolesHtml .= '
        <div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-shield-lock"></i>
                    <span>' . $languageService->get('role') . ': ' . $roleName . '</span>
                    <small class="small-muted">' . $languageService->get('role_rights') . '</small>
                </div>
            </div>
            <div class="card-body">';

    if (!$hasAnyRights) {
        $rolesHtml .= '<div class="alert alert-info mb-0">' . $languageService->get('role_no_rights') . '</div>';
        $rolesHtml .= '</div></div>';
        continue;
    }

    $rolesHtml .= '<div class="accordion rightsAccordion" id="rightsAccordion_' . $roleID . '">';

    // Kategorien in stabiler Reihenfolge
    $catOrder = array_values(array_unique($catOrder));
    sort($catOrder);

    foreach ($catOrder as $catID) {
        $rawTitle = $catTitlesRaw[$catID] ?? 'Allgemein';
        $translate->detectLanguages($rawTitle);
        $catTitle = htmlspecialchars($translate->getTextByLanguage($rawTitle));

        $modules = $catModules[$catID] ?? [];
        $totalModules = count($modules);
        $badgeModules = ($totalModules > 0)
            ? ($totalModules . ' Module')
            : ('0 Module');

        $collapseId = 'collapse_' . $roleID . '_' . $catID;
        $headingId  = 'heading_'  . $roleID . '_' . $catID;

        $rolesHtml .= '
            <div class="accordion-item border-0 shadow-sm mb-3">
                <h2 class="accordion-header" id="' . $headingId . '">
                    <button class="accordion-button collapsed" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#' . $collapseId . '"
                            aria-expanded="false"
                            aria-controls="' . $collapseId . '">
                        <div class="d-flex w-100 justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-list"></i>
                                <span class="fw-semibold">' . $catTitle . '</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto me-2">
                                <span class="badge text-bg-light">' . $badgeModules . '</span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="' . $collapseId . '" class="accordion-collapse collapse"
                    aria-labelledby="' . $headingId . '"
                    data-bs-parent="#rightsAccordion_' . $roleID . '">
                    <div class="accordion-body p-0">';

        if ($totalModules === 0) {
            $rolesHtml .= '
                <div class="p-3">
                    <span class="text-muted fst-italic">' . $languageService->get('no_modules_in_catg') . '</span>
                </div>';
        } else {
            $rolesHtml .= '
                <div class="row g-2 module-grid" data-category="' . $catID . '">';

            foreach ($modules as $m) {
                $translate->detectLanguages($m['module_name']);
                $displayName = htmlspecialchars($translate->getTextByLanguage($m['module_name']));
                $modulname   = htmlspecialchars($m['modulname']);

                $rolesHtml .= '
                    <div class="col-12 col-md-6 col-xl-4 module-item">
                        <div class="d-flex align-items-start gap-2 p-2 w-100 h-100 module-card" style="cursor:default;">
                            <span class="small">
                                <span class="fw-semibold module-title">' . $displayName . '</span>
                                <span class="d-block text-muted" style="font-size:.75rem; line-height:1.2;">
                                    ' . $modulname . '
                                </span>
                            </span>
                        </div>
                    </div>';
            }
            $rolesHtml .= '</div>';
        }
        $rolesHtml .= '
                    </div>
                </div>
            </div>';
    }

    $rolesHtml .= '</div></div></div>';
}
    } catch (Throwable $e) {
        nx_redirect('admincenter.php?site=user_roles', 'danger', 'Error: ' . $e->getMessage(), true, true);
    }
?>
<div class="row g-4">
    <!-- Linke Info-Card -->
    <div class="col-12 col-lg-3">
        <div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-person"></i>
                    <span><?=$languageService->get('user_info') ?></span>
                    <small class="small-muted"><?=$languageService->get('user_roles_and_rights') ?></small>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="fw-semibold"><?=$languageService->get('user') ?>:</span> <?= $username ?>
                </div>

                <div class="mb-2">
                    <span class="fw-semibold"><?=$languageService->get('roles') ?>:</span>
                    <?= ($rolesText !== '' ? $rolesText : '<span class="text-muted">—</span>') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rechte-Übersicht rechts -->
    <div class="col-12 col-lg-9">
        <?= $rolesHtml ?>
    </div>
</div>

<?php

} elseif ($action == "admins") {

/* =====================================================
   PATCH A: höchste Rolle korrekt ermitteln
   ===================================================== */

$currentUserID  = (int)($_SESSION['userID'] ?? 0);
$currentMaxRole = '';

$resMyRoles = safe_query("
    SELECT r.role_name
    FROM user_role_assignments ura
    INNER JOIN user_roles r ON r.roleID = ura.roleID
    WHERE ura.userID = $currentUserID
");

while ($r = mysqli_fetch_assoc($resMyRoles)) {
    $name = strtolower($r['role_name']);

    if (strpos($name, 'co-admin') !== false || strpos($name, 'coadmin') !== false) {
        $currentMaxRole = 'co-admin';
        continue;
    }

    if (strpos($name, 'admin') !== false) {
        $currentMaxRole = 'admin';
        break;
    }
}



    /* =====================================================
       CSRF
       ===================================================== */

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /* =====================================================
       Alle Rollen löschen
       ===================================================== */

    if (isset($_GET['delete_all_roles'])) {
        $userID = (int)($_GET['delete_all_roles'] ?? 0);

        if ($userID > 0) {
            safe_query("DELETE FROM user_role_assignments WHERE userID = $userID");
            nx_redirect('admincenter.php?site=user_roles&action=admins', 'success', 'alert_all_roles_removed', false);
        }

        nx_audit_action(
            'user_roles',
            'audit_action_named',
            (string)$userID,
            null,
            'admincenter.php?site=user_roles&action=admins',
            ['action' => nx_translate('alert_all_roles_removed')]
        );

        nx_redirect('admincenter.php?site=user_roles&action=admins', 'danger', 'alert_invalid_user_id', false);
    }

    /* =====================================================
       Einzelne Rolle löschen
       ===================================================== */

    if (isset($_GET['remove_role'])) {
        $assignmentID = (int)($_GET['remove_role'] ?? 0);

        if ($assignmentID > 0) {
            safe_query("DELETE FROM user_role_assignments WHERE assignmentID = $assignmentID");
            nx_redirect('admincenter.php?site=user_roles&action=admins', 'success', 'alert_deleted', false);
        }

        nx_audit_remove(
            'user_roles',
            (string)$assignmentID,
            null,
            'admincenter.php?site=user_roles&action=admins'
        );

        nx_redirect('admincenter.php?site=user_roles&action=admins', 'danger', 'alert_invalid_id', false);
    }

    /* =====================================================
       POST: Neue Rolle zuweisen
       ===================================================== */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!hash_equals(
            (string)($_SESSION['csrf_token'] ?? ''),
            (string)($_POST['csrf_token'] ?? '')
        )) {
            nx_redirect('admincenter.php?site=user_roles&action=admins', 'danger', 'alert_invalid_csrf', false);
        }

        if (isset($_POST['assign_role'])) {

            $userID = (int)($_POST['user_id'] ?? 0);
            $roleID = (int)($_POST['role_id'] ?? 0);

            if ($userID <= 0 || $roleID <= 0) {
                nx_redirect('admincenter.php?site=user_roles&action=admins', 'danger', 'alert_invalid_id', false);
            }

            /* =====================================================
               PATCH B: keine höhere Rolle als eigene vergeben
               ===================================================== */

$roleName = strtolower($roleRow['role_name']);

$isAdminRole = (
    strpos($roleName, 'admin') !== false &&
    strpos($roleName, 'co-admin') === false &&
    strpos($roleName, 'coadmin') === false
);

// Co-Admin darf KEIN echtes Admin vergeben
if ($currentMaxRole === 'co-admin' && $isAdminRole) {
    nx_redirect(
        'admincenter.php?site=user_roles&action=admins',
        'danger',
        'alert_not_allowed_higher_role',
        false
    );
}

// Kein Admin/Co-Admin darf Admin vergeben
if ($currentMaxRole === '' && $isAdminRole) {
    nx_redirect(
        'admincenter.php?site=user_roles&action=admins',
        'danger',
        'alert_not_allowed_higher_role',
        false
    );
}


            /* =====================================================
               bestehende Zuweisung prüfen
               ===================================================== */

            $existing = safe_query("
                SELECT 1
                FROM user_role_assignments
                WHERE userID = $userID
                  AND roleID = $roleID
                LIMIT 1
            ");

            if (mysqli_num_rows($existing) > 0) {
                nx_redirect(
                    'admincenter.php?site=user_roles&action=admins',
                    'warning',
                    'alert_role_already_assigned',
                    false
                );
            }

            safe_query("
                INSERT INTO user_role_assignments (userID, roleID)
                VALUES ($userID, $roleID)
            ");

            nx_audit_create(
                'user_roles',
                (string)$userID . ':' . (string)$roleID,
                null,
                'admincenter.php?site=user_roles&action=admins'
            );

            nx_redirect(
                'admincenter.php?site=user_roles&action=admins',
                'success',
                'alert_role_assigned',
                false
            );
        }
    }

 

/**
 * Gibt die kombinierten Forumrechte für eine bestimmte Rolle zurück.
 * Nutzt Tabelle: plugins_forum_permissions
 */
function nx_getForumPermissionsByRole(int $roleID): string
{
    global $_database, $languageService;

    $tables = [
        'plugins_forum_permissions_board',
        'plugins_forum_permissions_categories',
        'plugins_forum_permissions_threads'
    ];

    $rights = [
        'can_view'   => 0,
        'can_read'   => 0,
        'can_post'   => 0,
        'can_reply'  => 0,
        'can_edit'   => 0,
        'can_delete' => 0,
        'is_mod'     => 0
    ];

    foreach ($tables as $table) {

        // Tabelle prüfen
        $check = $_database->query("SHOW TABLES LIKE '{$table}'");
        if (!$check || $check->num_rows === 0) {
            continue;
        }

        $res = safe_query("
            SELECT
                MAX(can_view)   AS can_view,
                MAX(can_read)   AS can_read,
                MAX(can_post)   AS can_post,
                MAX(can_reply)  AS can_reply,
                MAX(can_edit)   AS can_edit,
                MAX(can_delete) AS can_delete,
                MAX(is_mod)     AS is_mod
            FROM {$table}
            WHERE role_id = " . (int)$roleID
        );

        if ($res && ($row = mysqli_fetch_assoc($res))) {
            foreach ($rights as $k => $v) {
                if ((int)$row[$k] === 1) {
                    $rights[$k] = 1;
                }
            }
        }
    }

    // Badges
    $out = [];
        if ($rights['can_view'])   $out[] = '<span class="badge bg-light text-dark"><i class="bi bi-eye"></i> ' . $languageService->get('forum_right_view') . '</span>';
        if ($rights['can_read'])   $out[] = '<span class="badge bg-success"><i class="bi bi-book"></i> ' . $languageService->get('forum_right_read') . '</span>';
        if ($rights['can_post'])   $out[] = '<span class="badge bg-primary"><i class="bi bi-plus-circle"></i> ' . $languageService->get('forum_right_post') . '</span>';
        if ($rights['can_reply'])  $out[] = '<span class="badge bg-info text-dark"><i class="bi bi-chat"></i> ' . $languageService->get('forum_right_reply') . '</span>';
        if ($rights['can_edit'])   $out[] = '<span class="badge bg-secondary"><i class="bi bi-pencil"></i> ' . $languageService->get('forum_right_edit') . '</span>';
        if ($rights['can_delete']) $out[] = '<span class="badge bg-danger"><i class="bi bi-trash"></i> ' . $languageService->get('forum_right_delete') . '</span>';
        if ($rights['is_mod'])     $out[] = '<span class="badge bg-warning text-dark"><i class="bi bi-shield-lock"></i> ' . $languageService->get('forum_right_moderator') . '</span>';

            return !empty($out)
                ? implode(' ', $out)
                : '<span class="text-muted">' . $languageService->get('no_rights_at_all') . '</span>';
        }
    ?>
    <!-- Rollen zuweisen -->
    <div class="col-12 mt-4">
        <div class="card shadow-sm px-3 py-2 mb-3">
            <form method="post" class="row align-items-end g-3">

                <!-- Benutzer -->
                <div class="col-12 col-md-4 col-lg-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="user_id" class="form-label mb-0 small text-muted">
                            <?= $languageService->get('username') ?>:
                        </label>
                        <select
                            name="user_id"
                            id="user_id"
                            class="form-select form-select-sm"
                            required
                        >

                        <option selected><?= $languageService->get('select_user') ?></option>
                            <?php
                            $admins = safe_query("SELECT * FROM users ORDER BY username");
                            while ($admin = mysqli_fetch_assoc($admins)) : ?>
                                <option value="<?= $admin['userID'] ?>">
                                    <?= htmlspecialchars($admin['username']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Rolle -->
                <div class="col-12 col-md-4 col-lg-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="role_id" class="form-label mb-0 small text-muted">
                            <?= $languageService->get('role_name') ?>:
                        </label>
<select
    name="role_id"
    id="role_id"
    class="form-select form-select-sm"
    required
>
<?php
$roles_for_assign = safe_query("
    SELECT * FROM user_roles
    WHERE is_active = 1
    AND (
        '$currentMaxRole' = 'admin'
        OR (
            '$currentMaxRole' = 'co-admin'
            AND role_name NOT LIKE '%admin%'
        )
    )
    ORDER BY role_name
");

if ($roles_for_assign && mysqli_num_rows($roles_for_assign) > 0):
?>
    <option selected><?= $languageService->get('select_role') ?></option>

    <?php while ($role = mysqli_fetch_assoc($roles_for_assign)) : ?>
        <option value="<?= $role['roleID'] ?>">
            <?= htmlspecialchars($role['role_name']) ?>
        </option>
    <?php endwhile; ?>

<?php else: ?>
    <option selected disabled>
        <?= $languageService->get('not_authorized_to_assign_roles') ?>
    </option>
<?php endif; ?>
</select>

                    </div>
                </div>

                <!-- Action -->
                <div class="col-12 col-md-auto">
                    <button
                        type="submit"
                        name="assign_role"
                        class="btn btn-secondary btn-sm px-4"
                    >
                        <i class="bi bi-person-plus me-1"></i>
                        <?= $languageService->get('assign_role') ?>
                    </button>
                </div>

                <!-- Helper -->
                <div class="col-12 col-md text-md-end">
                    <div class="form-text small mb-1">
                        <?= $languageService->get('assign_role_to_user_info') ?>
                    </div>
                </div>

                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            </form>
        </div>
    </div>

    <hr class="my-4">
    <!-- Übersicht -->
    <div class="col-12 col-lg-12">
        <div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-list-check"></i>
                    <span><?= $languageService->get('assigned_rights') ?></span>
                    <small class="small-muted"><?= $languageService->get('user_overview') ?></small>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-12 col-md-3 ms-auto">
                        <label for="userRoleSearch" class="form-label mb-1"><?= $languageService->get('search') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text"
                                    class="form-control"
                                    id="userRoleSearch"
                                    placeholder="<?= $languageService->get('username') ?> / <?= $languageService->get('role_name') ?>">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table" id="userRolesTable">
                        <thead>
                            <tr>
                                <th><?= $languageService->get('username') ?></th>
                                <th><?= $languageService->get('role_name') ?></th>
                                <th><?= $languageService->get('forum_permissions') ?></th>
                                <th class="text-end" style="width: 500px;"><?= $languageService->get('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        // Benutzer + Rollen laden (auch ohne Rollen)
                        $assignments = safe_query("
                            SELECT 
                                u.userID,
                                u.username,
                                COALESCE(r.role_name, '') AS role_name,
                                COALESCE(ura.assignmentID, 0) AS assignID
                            FROM users u
                            LEFT JOIN user_role_assignments ura ON u.userID = ura.userID
                            LEFT JOIN user_roles r ON ura.roleID = r.roleID
                            ORDER BY u.username ASC, r.role_name ASC
                        ");

                        if (!$assignments) {
                            echo '<tr><td colspan="4" class="text-danger">SQL-Fehler: ' . mysqli_error($_database) . '</td></tr>';
                        } elseif (mysqli_num_rows($assignments) === 0) {
                            echo '<tr><td colspan="4" class="text-muted text-center">' . $languageService->get('user_not_found') . '</td></tr>';
                        } else {
                            $userRoles = [];
                            while ($row = mysqli_fetch_assoc($assignments)) {
                                $uid = (int)$row['userID'];
                                if (!isset($userRoles[$uid])) {
                                    $userRoles[$uid] = [
                                        'username' => $row['username'],
                                        'roles' => []
                                    ];
                                }
                                if (!empty($row['role_name'])) {
                                    $userRoles[$uid]['roles'][] = [
                                        'assignID' => (int)$row['assignID'],
                                        'name' => $row['role_name']
                                    ];
                                }
                            }

                            foreach ($userRoles as $userID => $user) {
                                $username = htmlspecialchars($user['username']);
                                $roleBadges = [];

                                foreach ($user['roles'] as $role) {
                                    $cleanRole = htmlspecialchars($role['name']);
                                    $badge = '<span class="badge bg-secondary">' . $cleanRole . '</span>';
                                    if (stripos($cleanRole, 'admin') !== false) {
                                        $badge = '<span class="badge bg-danger">' . $cleanRole . '</span>';
                                        $username = '<strong class="text-danger">' . $username . '</strong>';
                                    } elseif (stripos($cleanRole, 'moderator') !== false) {
                                        $badge = '<span class="badge bg-warning text-dark">' . $cleanRole . '</span>';
                                    } elseif (stripos($cleanRole, 'editor') !== false || stripos($cleanRole, 'redakteur') !== false) {
                                        $badge = '<span class="badge bg-info text-dark">' . $cleanRole . '</span>';
                                    }

                                    // Einzelne Rolle löschen (Modal)
                                    $deleteUrl = 'admincenter.php?site=user_roles&action=admins&remove_role=' . intval($role['assignID']) . '&userID=' . $userID;
                                    $remove = '<a href="#" class="text-danger ms-1" title="Rolle löschen" '
                                        . 'data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" '
                                        . 'data-delete-url="' . htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') . '">' 
                                        . '<i class="bi bi-x-circle"></i></a><br>';

                                    $roleBadges[] = $badge . $remove;
                                }

                                echo '<tr>';
                                echo '<td>' . $username . '</td>';
                                echo '<td>' . (!empty($roleBadges) ? implode(' ', $roleBadges) : '<span class="text-muted">' . $languageService->get('no_roles') . '</span>') . '</td>';

                                // Forum-Rechte anzeigen
                                echo '<td>';
                                if (!empty($user['roles'])) {
                                    $forumRights = [];
                                    foreach ($user['roles'] as $role) {
                                        $res = safe_query("SELECT roleID FROM user_roles WHERE role_name = '" . escape($role['name']) . "' LIMIT 1");
                                        if (mysqli_num_rows($res) > 0) {
                                            $roleRow = mysqli_fetch_assoc($res);
                                            $forumRights[] = nx_getForumPermissionsByRole((int)$roleRow['roleID']);
                                        }
                                    }
                                    echo implode('<br>', $forumRights);
                                } else {
                                    echo '<span class="text-muted">' . $languageService->get('none') . '</span>';
                                }
                                echo '</td>';

                                echo '<td class="text-end">';
                                echo '<a href="admincenter.php?site=user_roles&action=user_role_details&userID=' . $userID . '" class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto me-2"><i class="bi bi-eye"></i> ' . $languageService->get('view_assigned_rights') . '</a>';
                                echo '<a href="#" class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-delete-url="' . htmlspecialchars('admincenter.php?site=user_roles&action=admins&delete_all_roles=' . $userID, ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-trash3"></i> ' . $languageService->get('remove_all_roles') . '</a>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php

} elseif ($action == "roles") {

// Aktivieren/Deaktivieren von Rollen
if (isset($_GET['toggle_role'])) {
    $roleID = (int)($_GET['toggle_role'] ?? 0);

    $res = safe_query("SELECT is_active FROM user_roles WHERE roleID = $roleID");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $newState = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;

        safe_query("UPDATE user_roles SET is_active = $newState WHERE roleID = $roleID");
        nx_audit_update('user_roles', (string)$roleID, true, null, 'admincenter.php?site=user_roles&action=roles', ['is_active' => $newState]);
        nx_redirect('admincenter.php?site=user_roles&action=roles', 'success', $newState ? 'alert_activated' : 'alert_deactivated', false);
    }

    nx_redirect('admincenter.php?site=user_roles&action=roles', 'danger', 'alert_invalid_id', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_invalid_csrf', false);

    if (isset($_POST['assign_role'])) {
        $userID = (int)($_POST['user_id'] ?? 0);
        $roleID = (int)($_POST['role_id'] ?? 0);

        if ($userID <= 0 || $roleID <= 0) nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_invalid_id', false);

        $existing = safe_query("SELECT 1 FROM user_role_assignments WHERE userID = $userID AND roleID = $roleID LIMIT 1");
        if (mysqli_num_rows($existing) > 0) nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_role_already_assigned', false);

        safe_query("INSERT INTO user_role_assignments (userID, roleID) VALUES ($userID, $roleID)");
        nx_audit_create('user_roles', (string)$userID . ':' . (string)$roleID, null, 'admincenter.php?site=user_roles');
        nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_role_assigned', false);
    }
}

?>
    <small class="small-muted d-none"><?= $languageService->get('available_roles') ?></small>
    <!-- Rollenliste -->
    <style>
        .role-card .card-title { line-height: 1.2; padding: 0px; }
        .role-card .role-desc { min-height: 1.25rem; }
        .role-card .btn { white-space: nowrap; }
        .role-card .form-switch .form-check-input { cursor: pointer; }
    </style>

    <div class="row g-3 mt-3">
        <?php
        $roles = safe_query("SELECT * FROM user_roles ORDER BY role_name");
        while ($role = mysqli_fetch_assoc($roles)) :
            $roleID = (int)$role['roleID'];
            $isActive = ((int)$role['is_active'] === 1);
            $desc = $role['description'] ?? ($role['desciption'] ?? '');
            $desc = trim((string)$desc);
            if ($desc === '') {
                $desc = $languageService->get('no_permissions_defined');
            }
        ?>
            <div class="col-12 col-md-6 col-xl-3 mb-3 mt-3">
                <div class="card shadow-sm h-100 role-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="me-2">
                                <h5 class="card-title mb-1"><?= htmlspecialchars($role['role_name']) ?></h5>
                                <div class="text-muted small role-desc"><?= htmlspecialchars($desc) ?></div>
                            </div>

                            <?php if ($isActive): ?>
                                <span class="badge bg-success role-status-badge">
                                    <?= $languageService->get('active') ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary role-status-badge">
                                    <?= $languageService->get('inactive') ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex align-items-center justify-content-between gap-2 mt-auto">
                            <a href="admincenter.php?site=user_roles&action=edit_role_rights&roleID=<?= $roleID ?>"
                                class="btn btn-secondary">
                                <?= $languageService->get('edit') ?>
                            </a>

                            <div class="form-check form-switch m-0">
                                <input class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="roleSwitch<?= $roleID ?>"
                                        <?= $isActive ? 'checked' : '' ?>
                                        onclick="window.location.href='admincenter.php?site=user_roles&action=roles&toggle_role=<?= $roleID ?>'">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php
    } elseif ($action == "edit_user") {

        // Benutzer-ID aus der URL holen
        $userID = isset($_GET['userID']) ? intval($_GET['userID']) : 0;

        if ($userID > 0) {
            $result = safe_query("SELECT * FROM users WHERE userID = $userID");

            if ($result && mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                $username = (string)($user['username'] ?? '');
                $email = (string)($user['email'] ?? '');

                if ((int)($user['is_active'] ?? 0) !== 1) nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_user_not_activated', false);
            } else {
                nx_redirect('admincenter.php?site=user_roles', 'info', 'alert_user_not_found', false);
            }

            if (isset($_POST['submit']) || isset($_POST['reset_password'])) {

        // CSRF-Schutz
        if (!function_exists('generate_csrf_token') || !function_exists('verify_csrf_token')) nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_csrf_functions_missing', false);
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_invalid_csrf', false);

        $username = mysqli_real_escape_string($_database, (string)($_POST['username'] ?? ''));
        $email = mysqli_real_escape_string($_database, (string)($_POST['email'] ?? ''));
        $new_password_plain = trim((string)($_POST['password'] ?? ''));
        $reset_password = isset($_POST['reset_password']) && (string)$_POST['reset_password'] === '1';

        // Seiteneinstellungen
        $hp_title = get_all_settings('hptitle');
        $hp_url = get_all_settings('hp_url');

        $send_password = false;

        if ($new_password_plain !== '' || $reset_password) {

            if ($reset_password && $new_password_plain === '') {
                $new_password_plain = LoginSecurity::generateTemporaryPassword();
                $send_password = true;
            }

            $new_pepper = LoginSecurity::generateRandomPepper();
            $pepper_encrypted = LoginSecurity::encryptPepper($new_pepper);
            $password_hash = password_hash($new_password_plain . $new_pepper, PASSWORD_DEFAULT);

            $stmt = $_database->prepare("UPDATE users SET username = ?, password_hash = ?, password_pepper = ? WHERE userID = ?");
            if ($stmt === false) nx_redirect('admincenter.php?site=user_roles', 'danger', 'SQL error: ' . (string)$_database->error, true, true);

            $stmt->bind_param("sssi", $username, $password_hash, $pepper_encrypted, $userID);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {

                if ($send_password) {
                    $adminID = (int)($_SESSION['userID'] ?? 0);
                    $admin_query = safe_query("SELECT email, username FROM users WHERE userID = $adminID");
                    $admin = mysqli_fetch_assoc($admin_query);
                    $admin_email = (string)($admin['email'] ?? '');
                    $admin_name = (string)($admin['username'] ?? '');

                    $vars = ['%pagetitle%', '%email%', '%new_password%', '%homepage_url%', '%admin_name%', '%admin_email%'];
                    $repl = [$hp_title, $email, $new_password_plain, $hp_url, $admin_name, $admin_email];

                    $subject = str_replace($vars, $repl, (string)$languageService->get('email_subject'));
                    $message = str_replace($vars, $repl, (string)$languageService->get('email_text'));

                    $sendmail = Email::sendEmail($admin_email, (string)$languageService->get('mail_password_reset_subject'), $email, $subject, $message);
                    if (($sendmail['result'] ?? 'fail') === 'fail') ac_redirect('admincenter.php?site=user_roles', 'danger', 'email_failed', false, true);
                }
                nx_audit_action('user_roles','audit_action_named',(string)$userID,null,'admincenter.php?site=user_roles',['action' => (string)$languageService->get('alert_password_reset')]);
                nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_password_reset', false);
            }

            $stmt->close();
            nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_save_failed', false);

        } else {

            $stmt = $_database->prepare("UPDATE users SET username = ? WHERE userID = ?");
            if ($stmt === false) nx_redirect('admincenter.php?site=user_roles', 'danger', 'SQL error: ' . (string)$_database->error, true, true);

            $stmt->bind_param("si", $username, $userID);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                nx_audit_update('user_roles', (string)$userID, true, $username, 'admincenter.php?site=user_roles');
                nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_saved', false);
            }
            $stmt->close();

            nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_save_failed', false);
        }
    }
        // HTML-Ausgabe
        $csrf_token = generate_csrf_token();
        ?>
        <div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-person-circle"></i>
                    <span><?= $languageService->get('user_info') ?></span>
                    <small class="small-muted"><?= $languageService->get('user_edit') ?></small>
                </div>
            </div>

            <div class="card-body">

                    <form method="post" class="row g-4 align-items-stretch">
                        <input type="hidden" name="userID" value="<?= htmlspecialchars($user['userID']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="col-md-6">
                            <label for="username" class="form-label"><?= $languageService->get('username') ?></label>
                            <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label"><?= $languageService->get('email') ?></label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        </div>

                        <!-- Manuelles Passwort -->
                        <div class="col-md-6 d-flex flex-column justify-content-between">
                            <div>
                                <label for="password" class="form-label"><?= $languageService->get('set_password_manually') ?></label>
                                <input type="password" id="password" name="password" class="form-control">
                            </div>
                            <div class="alert alert-warning d-flex align-items-center mt-3 mb-0 flex-grow-1" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                <div>
                                    <?= $languageService->get('manual_password_info') ?>
                                </div>
                            </div>
                        </div>

                        <!-- Automatisches Passwort -->
                        <div class="col-md-6 d-flex flex-column justify-content-between">
                            <div>
                                <label for="password_auto" class="form-label"><?= $languageService->get('new_password_send_auto') ?></label>
                                <button type="submit" name="reset_password" value="1" class="btn btn-danger w-100"
                                    onclick="return confirm('<?= $languageService->get('confirm_reset_password') ?>');">
                                    <?= $languageService->get('reset_password') ?>
                                </button>
                            </div>
                            <div class="alert alert-warning d-flex align-items-center mt-3 mb-0 flex-grow-1" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                <div>
                                    <?= $languageService->get('new_password_generate_info') ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" name="submit" class="btn btn-warning"><?= $languageService->get('save') ?></button>
                        </div>
                    </form>
                </div>
        <?php
    } else {
        nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_invalid_id', false);
    }
}
elseif ($action == "user_create") {

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim((string)($_POST['username'] ?? ''));
    $emailRaw = trim((string)($_POST['email'] ?? ''));
    $email = filter_var($emailRaw, FILTER_SANITIZE_EMAIL);
    $password = (string)($_POST['password'] ?? '');

    // Validierung
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_invalid_email', false);
    if (mb_strlen($password) < 8) nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_password_too_short', false);
    // Prüfen ob Email bereits vorhanden ist
    $stmt = $_database->prepare("SELECT userID FROM users WHERE email = ?");
    if ($stmt === false) nx_redirect('admincenter.php?site=user_roles', 'danger', 'SQL error: ' . (string)$_database->error, true, true);

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) { $stmt->close(); nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_email_already_taken', false); }


    $stmt->close();

    // Benutzer einfügen (registerdate als DATETIME)
    $query = "INSERT INTO users (username, email, registerdate) VALUES (?, ?, NOW())";
    if ($stmt = $_database->prepare($query)) {
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $userID = $_database->insert_id;

    if ($userID > 0) {

        $roleID = 12;

        $queryRole = "INSERT INTO user_role_assignments (userID, roleID) VALUES (?, ?)";
            if ($stmtRole = $_database->prepare($queryRole)) {
                $stmtRole->bind_param('ii', $userID, $roleID);
                $stmtRole->execute();
            }

            $pepper_plain     = LoginSecurity::generatePepper();
            $pepper_encrypted = LoginSecurity::encryptPepper($pepper_plain);
            $password_hash    = LoginSecurity::createPasswordHash($password, $email, $pepper_plain);

            $query = "UPDATE users SET password_hash = ?, password_pepper = ?, is_active = 1 WHERE userID = ?";
            if ($stmt = $_database->prepare($query)) {
                $stmt->bind_param('ssi', $password_hash, $pepper_encrypted, $userID);
                $stmt->execute();
                nx_audit_update('user_roles', (string)$userID, true, null, 'admincenter.php?site=user_roles');
                nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_saved', false);
            } else {
                nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_db_error', false);
            }
        } else {
            nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_save_failed', false);
        }
    } else {
        nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_db_error', false);
    }
}
?>

<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-person-fill-add"></i> 
            <span><?= $languageService->get('user_overview') ?></span>
            <small class="small-muted"><?= $languageService->get('add_user') ?></small>
        </div>
    </div>

    <div class="card-body">
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label"><?= $languageService->get('username') ?></label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label"><?= $languageService->get('email') ?></label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label"><?= $languageService->get('password') ?></label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary"><?= $languageService->get('add') ?></button>
        </form>
    </div>
</div>

<?php
} else { 

// Anzahl der Einträge pro Seite
$users_per_page = 10;

// Aktuelle Seite ermitteln
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $users_per_page;

// Anzahl der Benutzer ermitteln (für die Paginierung)
$total_users_query = safe_query("SELECT COUNT(*) as total FROM users");
$total_users = mysqli_fetch_assoc($total_users_query)['total'];
$total_pages = ceil($total_users / $users_per_page);

if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['userID'])) {
    $userID = (int)($_GET['userID'] ?? 0);
    $currentUserID = (int)($_SESSION['userID'] ?? 0);

    if ($userID === $currentUserID) nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_delete_own_account', false);

    $user_check = safe_query("SELECT 1 FROM users WHERE userID = $userID LIMIT 1");
    if (mysqli_num_rows($user_check) > 0) {
        safe_query("DELETE FROM user_role_assignments WHERE userID = $userID");
        safe_query("DELETE FROM users WHERE userID = $userID");
        nx_audit_delete('user_roles', (string)$userID, (string)$userID, 'admincenter.php?site=user_roles');
        nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_deleted', false);
    }

    nx_redirect('admincenter.php?site=user_roles', 'info', 'alert_user_not_found', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
    $userID = (int)($_POST['userID'] ?? 0);
    $currentUserID = (int)($_SESSION['userID'] ?? 0);

    if ($userID === $currentUserID)
        nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_ban_own_account', false);

    if (safe_query("UPDATE users SET is_locked = 1 WHERE userID = $userID")) {
        nx_audit_update('user_roles', (string)$userID, true, null, 'admincenter.php?site=user_roles', ['is_locked' => 1]);
        nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_user_banned', false);
    }

    nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_save_failed', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban_user'])) {
    $userID = (int)($_POST['userID'] ?? 0);

    if (safe_query("UPDATE users SET is_locked = 0 WHERE userID = $userID")) {
        nx_audit_update('user_roles', (string)$userID, true, null, 'admincenter.php?site=user_roles', ['is_locked' => 0]);
        nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_user_unbanned', false);
    }

    nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_save_failed', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_user'])) {
    $userID = (int)($_POST['userID'] ?? 0);
    $currentUserID = (int)($_SESSION['userID'] ?? 0);

    if ($userID === $currentUserID)
        nx_redirect('admincenter.php?site=user_roles', 'warning', 'alert_deactivate_own_account', false);

    if (safe_query("UPDATE users SET is_active = 0 WHERE userID = $userID")) {
        nx_audit_update('user_roles', (string)$userID, true, null, 'admincenter.php?site=user_roles', ['is_active' => 0]);
        nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_deactivated', false);
    }

    nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_save_failed', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_user'])) {
    $userID = (int)($_POST['userID'] ?? 0);

    if (safe_query("UPDATE users SET is_active = 1 WHERE userID = $userID")) {
        nx_audit_update('user_roles', (string)$userID, true, null, 'admincenter.php?site=user_roles', ['is_active' => 1]);
        nx_redirect('admincenter.php?site=user_roles', 'success', 'alert_activated', false);
    }

    nx_redirect('admincenter.php?site=user_roles', 'danger', 'alert_save_failed', false);
}

if (isset($_GET['clear_remember_device'])) {
    $clearRememberUid = (int)($_GET['clear_remember_device'] ?? 0);
    $twofaCheckRes = safe_query("SELECT twofa_force_all FROM settings LIMIT 1");
    $twofaCheckOn = false;
    if ($twofaCheckRes && ($twofaCheckRow = mysqli_fetch_assoc($twofaCheckRes))) {
        $twofaCheckOn = (int)($twofaCheckRow['twofa_force_all'] ?? 0) === 1;
    }
    $backUrl = 'admincenter.php?site=user_roles' . ($page > 1 ? '&page=' . $page : '');
    if ($clearRememberUid > 0 && $twofaCheckOn) {
        safe_query("UPDATE users SET remember_device_salt = NULL WHERE userID = {$clearRememberUid}");
        nx_audit_update('user_roles', (string)$clearRememberUid, true, null, $backUrl, ['remember_device_salt' => 'cleared']);
        nx_redirect($backUrl, 'success', 'alert_remember_device_cleared', false);
    }
    nx_redirect($backUrl, 'danger', 'alert_save_failed', false);
}

// Abfrage der Benutzer für die aktuelle Seite
$users = safe_query("SELECT * FROM users ORDER BY userID LIMIT $offset, $users_per_page");

$settingsTwofaForceAll = false;
$twofaSettingsRes = safe_query("SELECT twofa_force_all FROM settings LIMIT 1");
if ($twofaSettingsRes && ($twofaSettingsRow = mysqli_fetch_assoc($twofaSettingsRes))) {
    $settingsTwofaForceAll = (int)($twofaSettingsRow['twofa_force_all'] ?? 0) === 1;
}
?>
<div class="mb-4 mt-4">
    <div class="d-flex gap-2 flex-wrap">
        <a href="admincenter.php?site=user_roles&action=roles" class="btn btn-secondary">
            <?= $languageService->get('manage_admin_roles') ?>
        </a>
        <a href="admincenter.php?site=user_roles&action=admins" class="btn btn-secondary">
            <?= $languageService->get('assign_role_to_user') ?>
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-person-gear"></i>
            <span><?= $languageService->get('user_overview') ?></span>
        </div>
    </div>

    <div class="card-body p-4">
            
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <a href="admincenter.php?site=user_roles&action=user_create" class="btn btn-secondary mt-2">
                    <?= $languageService->get('add') ?>
                </a>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group input-group-sm" style="min-width: 260px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="userSearch" type="search" class="form-control" placeholder="<?= $languageService->get('search') ?>">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" id="usersTable">
                <thead>
                    <tr>
                        <th style="width: 72px;"><?= $languageService->get('id') ?></th>
                        <th><?= $languageService->get('username') ?></th>
                        <th class="d-none d-md-table-cell"><?= $languageService->get('email') ?></th>
                        <th class="d-none d-lg-table-cell"><?= $languageService->get('registered_on') ?></th>
                        <th style="width: 250px;"><?= $languageService->get('status') ?? $languageService->get('activated') ?></th>
                        <?php if ($settingsTwofaForceAll): ?>
                        <th style="width: 180px;" title="<?= htmlspecialchars($languageService->get('remember_device_trust_column_title')) ?>">
                            <?= htmlspecialchars($languageService->get('remember_device_trust_column')) ?>
                        </th>
                        <?php endif; ?>
                        <th style="width: 525px;"><?= $languageService->get('actions') ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php $currentUserID = (int)($_SESSION['userID'] ?? 0); ?>
                    <?php while ($user = mysqli_fetch_assoc($users)) : ?>
                        <?php
                            $isActive = (int)$user['is_active'] === 1;
                            $isLocked = (int)$user['is_locked'] === 1;
                            $isSelf = ((int)$user['userID'] === $currentUserID);
                        ?>
                        <tr class="<?= $isLocked ? 'table-danger' : '' ?>">
                            <td class="text-muted"><?= htmlspecialchars($user['userID']) ?></td>

                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="text-muted small d-md-none"><?= htmlspecialchars($user['email']) ?></div>
                            </td>

                            <td class="d-none d-md-table-cell"><?= htmlspecialchars($user['email']) ?></td>

                            <td class="d-none d-lg-table-cell text-muted">
                                <?= date('d.m.Y H:i', strtotime($user['registerdate'])) ?>
                            </td>

                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge <?= $isActive ? 'bg-success' : 'bg-warning' ?>">
                                        <?= $isActive ? $languageService->get('active') : $languageService->get('inactive') ?>
                                    </span>
                                    <span class="badge <?= $isLocked ? 'bg-danger' : 'bg-secondary' ?>">
                                        <?= $isLocked ? $languageService->get('banned') : $languageService->get('not_banned') ?>
                                    </span>
                                </div>
                            </td>

                            <?php if ($settingsTwofaForceAll): ?>
                            <td>
                                <?php
                                $rdSalt = $user['remember_device_salt'] ?? '';
                                $rdHas = ($rdSalt !== '' && $rdSalt !== null);
                                $clearRememberUrl = 'admincenter.php?site=user_roles&clear_remember_device=' . (int)$user['userID'];
                                if ($page > 1) {
                                    $clearRememberUrl .= '&page=' . $page;
                                }
                                ?>
                                <span class="d-inline-flex align-items-center flex-wrap gap-1">
                                    <span class="badge <?= $rdHas ? 'bg-info' : 'bg-secondary' ?>"
                                          title="<?= htmlspecialchars($languageService->get('remember_device_trust_column_title')) ?>">
                                        <?= htmlspecialchars($rdHas
                                            ? $languageService->get('active')
                                            : $languageService->get('inactive')
                                        ) ?>
                                    </span>
                                    <?php if ($rdHas) : ?>
                                        <a href="#"
                                           class="text-danger ms-1"
                                           role="button"
                                           title="<?= htmlspecialchars($languageService->get('remember_device_clear_title')) ?>"
                                           aria-label="<?= htmlspecialchars($languageService->get('remember_device_clear_title')) ?>"
                                           data-bs-toggle="modal"
                                           data-bs-target="#confirmDeleteModal"
                                           data-delete-url="<?= htmlspecialchars($clearRememberUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-x-circle" aria-hidden="true"></i>
                                        </a>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <?php endif; ?>

                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <!-- Bearbeiten -->
                                    <a href="admincenter.php?site=user_roles&action=edit_user&userID=<?= (int)$user['userID'] ?>" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 w-auto">
                                        <i class="bi bi-pencil-square"></i> <?= $languageService->get('edit') ?>
                                    </a>
                                    <!-- Aktivieren/Deaktivieren -->
                                    <?php if ($isSelf) : ?>
                                        <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 w-auto" disabled>
                                            <i class="bi bi-person-check"></i> <?= $languageService->get('activate_user') ?>
                                        </button>
                                    <?php else : ?>
                                        <?php if (!$isActive) : ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="userID" value="<?= (int)$user['userID'] ?>">
                                                <button type="submit" name="activate_user" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1 w-auto">
                                                    <i class="bi bi-check2-circle"></i> <?= $languageService->get('activate_user') ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="userID" value="<?= (int)$user['userID'] ?>">
                                                <button type="submit" name="deactivate_user" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1 w-auto">
                                                    <i class="bi bi-x-circle"></i> <?= $languageService->get('deactivate_user') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Sperren/Entsperren -->
                                    <?php if ($isSelf) : ?>
                                        <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 w-auto" disabled>
                                            <i class="bi bi-shield-lock"></i> <?= $languageService->get('ban_user') ?>
                                        </button>
                                    <?php else : ?>
                                        <?php if ($isLocked) : ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="userID" value="<?= (int)$user['userID'] ?>">
                                                <button type="submit" name="unban_user" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1 w-auto">
                                                    <i class="bi bi-unlock"></i> <?= $languageService->get('unban_user') ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="userID" value="<?= (int)$user['userID'] ?>">
                                                <button type="submit" name="ban_user" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 w-auto">
                                                    <i class="bi bi-ban"></i> <?= $languageService->get('ban_user') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Löschen (Modal) -->
                                    <?php if (!$isSelf): ?>
                                        <?php $deleteUserUrl = 'admincenter.php?site=user_roles&action=delete_user&userID=' . (int)$user['userID']; ?>
                                        <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 w-auto"
                                                data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"
                                                data-delete-url="<?= htmlspecialchars($deleteUserUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 w-auto" disabled>
                                            <i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php if ($total_pages > 1): ?>
    <!-- Pagination -->
<nav aria-label="Seiten-Navigation">
  <ul class="pagination justify-content-center">

    <!-- Prev -->
    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
      <?php if ($page <= 1): ?>
        <span class="page-link" aria-disabled="true" aria-label="<?= $languageService->get('previous') ?>">
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </span>
      <?php else: ?>
        <a class="page-link"
           href="admincenter.php?site=user_roles&page=<?= $page - 1 ?>"
           aria-label="<?= $languageService->get('previous') ?>"
           title="<?= $languageService->get('previous') ?>">
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </a>
      <?php endif; ?>
    </li>

    <!-- Seitenzahlen komplett -->
    <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
      <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
        <?php if ($i == $page): ?>
          <span class="page-link" aria-current="page"><?= $i ?></span>
        <?php else: ?>
          <a class="page-link" href="admincenter.php?site=user_roles&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      </li>
    <?php endfor; ?>

    <!-- Next -->
    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
      <?php if ($page >= $total_pages): ?>
        <span class="page-link" aria-disabled="true" aria-label="<?= $languageService->get('next') ?>">
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </span>
      <?php else: ?>
        <a class="page-link"
           href="admincenter.php?site=user_roles&page=<?= $page + 1 ?>"
           aria-label="<?= $languageService->get('next') ?>"
           title="<?= $languageService->get('next') ?>">
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
      <?php endif; ?>
    </li>

  </ul>
</nav>
    <?php endif; ?>
</div>

</div></div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function bindTextSearch(config) {
    var input = config.inputId ? document.getElementById(config.inputId) : null;
    if (!input) return;

    function apply() {
      var q = (input.value || '').toLowerCase().trim();
      var targets = Array.prototype.slice.call(document.querySelectorAll(config.targets));
      var visible = 0;

      for (var i = 0; i < targets.length; i++) {
        var el = targets[i];
        var txt = (el.textContent || '').toLowerCase();
        var show = (!q || txt.indexOf(q) !== -1);

        el.style.display = show ? (config.displayAs || '') : 'none';
        if (show) visible++;
      }

      if (countEl) countEl.textContent = visible + ' / ' + targets.length;

      if (config.groupSelector && config.groupItemSelector) {
        var groups = document.querySelectorAll(config.groupSelector);
        for (var g = 0; g < groups.length; g++) {
          var group = groups[g];
          var items = group.querySelectorAll(config.groupItemSelector);

          var anyVisible = false;
          for (var k = 0; k < items.length; k++) {
            if (items[k].style.display !== 'none') { anyVisible = true; break; }
          }
          group.style.display = (!q || anyVisible) ? '' : 'none';
        }
      }
    }

    input.addEventListener('input', apply);
    apply();
  }

  bindTextSearch({ inputId: 'userRoleSearch', targets: '#userRolesTable tbody tr', displayAs: 'table-row' });
  bindTextSearch({ inputId: 'userSearch',     targets: '#usersTable tbody tr', displayAs: 'table-row' });
  bindTextSearch({ inputId: 'moduleSearch',   targets: '.module-item', groupSelector: '.accordion-item[data-category]', groupItemSelector: '.module-item' });
});
</script>
