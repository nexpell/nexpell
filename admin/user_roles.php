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
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('user_roles', true);

use nexpell\LoginSecurity;
use nexpell\Email;
use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_user_roles');

$action = $_GET['action'] ?? '';

require_once "../system/config.inc.php";
require_once "../system/functions.php";

if ($action == "edit_role_rights") {
 
// CSRF-Token generieren
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Überprüfen, ob der Benutzer berechtigt ist
if (!$userID || !checkUserRoleAssignment($userID)) {
    die('<div class="alert alert-danger" role="alert">' . $languageService->get('no_role_assigned') . '</div>');
}

$categoryRights = [];
$moduleRights = [];

if (isset($_GET['roleID'])) {
    $roleID = (int)$_GET['roleID'];

    // Modul-Liste abrufen
    $modules = [];
    $result = safe_query("SELECT linkID, catID, modulname, name FROM navigation_dashboard_links ORDER BY sort ASC");
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
    $result = safe_query("SELECT catID, name, modulname FROM navigation_dashboard_categories ORDER BY sort ASC");
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roleID'], $_POST['save_rights'])) {
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('<div class="alert alert-danger" role="alert">' . $languageService->get('invalid_csrf') . '</div>');
        }

        $roleID = (int)$_POST['roleID'];

        // 🔹 Alte Rechte dieser Rolle löschen
        safe_query("DELETE FROM user_role_admin_navi_rights WHERE roleID = $roleID");

        // 🔹 Module (Links) speichern
        $grantedModules = array_unique($_POST['modules'] ?? []); // Duplikate entfernen
        foreach ($grantedModules as $modulname) {
            $modulnameEscaped = $_database->real_escape_string($modulname);
            $query = "
                INSERT IGNORE INTO user_role_admin_navi_rights (roleID, type, modulname)
                VALUES ($roleID, 'link', '$modulnameEscaped')
            ";
            safe_query($query);
        }

        // 🔹 Kategorien speichern
        $grantedCategories = array_unique($_POST['category'] ?? []); // Duplikate entfernen
        foreach ($grantedCategories as $modulname) {
            $modulnameEscaped = $_database->real_escape_string($modulname);
            $query = "
                INSERT IGNORE INTO user_role_admin_navi_rights (roleID, type, modulname)
                VALUES ($roleID, 'category', '$modulnameEscaped')
            ";
            safe_query($query);
        }

        // ✅ Erfolgsmeldung & Redirect
        $_SESSION['success_message'] = $languageService->get('rights_updated');
        header("Location: /admin/admincenter.php?site=user_roles&action=roles");
        exit;
    }

}

?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> <?= $languageService->get('regular_users') ?>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=user_roles"><?= $languageService->get('regular_users') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('edit_role_rights') ?></li>
        </ol>
    </nav>

    <div class="card-body">
        <div class="container py-5">
            <h4 class="mb-4"><i class="bi bi-shield-lock"></i> <?= $languageService->get('edit_role_rights') ?></h4>

            <p class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                Auf dieser Seite kannst du die <strong>Admincenter-Berechtigungen</strong> für eine bestimmte Benutzerrolle festlegen.
                <br>
                Jede Kategorie entspricht einem Bereich im Admincenter-Menü (z. B. „System & Einstellungen“, „Webinhalte“, „Plugins & Erweiterungen“).
                <br>
                Unter jeder Kategorie findest du die zugehörigen Module, die du individuell aktivieren oder deaktivieren kannst.
                <br>
                <small>
                    Aktivierte Rechte bestimmen, welche Seiten und Module Benutzer mit dieser Rolle im Adminbereich sehen und aufrufen dürfen.
                    <br>
                    Aktiviere die Checkboxen, um Zugriff zu gewähren. Benutzer mit dieser Rolle sehen dann nur die ausgewählten Bereiche. 
                </small>
            </p>

            <form method="post">
                <input type="hidden" name="roleID" value="<?= $roleID ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                <?php foreach ($categories as $cat): ?>
                    <?php
                    $translate = new multiLanguage($lang);
                    $translate->detectLanguages($cat['name']);
                    $catTitle = $translate->getTextByLanguage($cat['name']);
                    $catKey   = $cat['modulname'];
                    $catID    = (int)$cat['catID'];
                    $catModules = $modulesByCategory[$catID] ?? [];
                    ?>

                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-header bg-light d-flex align-items-center">
                            <input class="form-check-input me-2" type="checkbox"
                                   name="category[]" id="cat_<?= $catID ?>"
                                   value="<?= htmlspecialchars($catKey) ?>"
                                   <?= in_array($catKey, $categoryRights) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="cat_<?= $catID ?>">
                                <?= htmlspecialchars($catTitle) ?>
                            </label>
                        </div>

                        <?php if (!empty($catModules)): ?>
                            <div class="card-body p-0">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:70%"><?= $languageService->get('module') ?></th>
                                            <th style="width:30%"><?= $languageService->get('access') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($catModules as $mod):
                                        $translate->detectLanguages($mod['name']);
                                        $modTitle = $translate->getTextByLanguage($mod['name']);
                                    ?>
                                        <tr>
                                            <td class="ps-4"><?= htmlspecialchars($modTitle) ?></td>
                                            <td>
                                                <input class="form-check-input me-2" type="checkbox" name="modules[]"
                                                       value="<?= htmlspecialchars($mod['modulname']) ?>"
                                                       <?= in_array($mod['modulname'], $moduleRights) ? 'checked' : '' ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="card-body text-muted small fst-italic">
                                <?= $languageService->get('no_modules_in_category') ?? 'Keine Module in dieser Kategorie' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" name="save_rights" class="btn btn-warning mt-3">
                    <i class="bi bi-save"></i> <?= $languageService->get('save_rights') ?>
                </button>
            </form>


        </div>
    </div>
</div>
<script>
document.querySelectorAll('input[name="category[]"]').forEach(catCheckbox => {
    catCheckbox.addEventListener('change', () => {
        const card = catCheckbox.closest('.card');
        card.querySelectorAll('input[name="modules[]"]').forEach(mod => {
            mod.checked = catCheckbox.checked;
        });
    });
});
</script>

<?php

}elseif ($action === "user_role_details") {

    if (!isset($_GET['userID'])) {
        echo '<div class="alert alert-warning">Kein Benutzer ausgewählt.</div>';
        exit;
    }

    $userID = (int)$_GET['userID'];
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        // --- Benutzer laden ---
        $userResult = safe_query("SELECT username FROM users WHERE userID = $userID");
        if (!mysqli_num_rows($userResult)) {
            echo '<div class="alert alert-danger">Benutzer nicht gefunden.</div>';
            exit;
        }
        $user = mysqli_fetch_assoc($userResult);
        $username = htmlspecialchars($user['username']);

        // --- Alle Rollen des Benutzers laden ---
        $rolesResult = safe_query("
            SELECT r.roleID, r.role_name
            FROM user_roles r
            JOIN user_role_assignments ur ON ur.roleID = r.roleID
            WHERE ur.userID = $userID
            ORDER BY r.role_name ASC
        ");

        if (!mysqli_num_rows($rolesResult)) {
            echo '<div class="alert alert-info">Dieser Benutzer hat keine Rollen.</div>';
            $output .= '</div>';
            exit;
        }

        // Sprachsystem vorbereiten
        if (!isset($lang)) $lang = 'de';
        if (!class_exists('multiLanguage')) {
            require_once BASE_PATH . '/system/core/classes/multiLanguage.php';
        }
        $translate = new multiLanguage($lang);

        $output = '';

        // --- Durch jede Rolle iterieren ---
        while ($role = mysqli_fetch_assoc($rolesResult)) {
            $roleID = (int)$role['roleID'];
            $roleName = htmlspecialchars($role['role_name']);
            $output .= "<div class='card card-body bg-info-subtle'><h4 class='mt-4'><i class='bi bi-shield-lock'></i> Rolle: {$roleName}</h4>";

            // --- Kategorien + Module dieser Rolle laden ---
            $rights_query = "
                SELECT 
                    c.name AS category_name,
                    l.name AS module_name,
                    ar.type,
                    ar.modulname
                FROM user_role_admin_navi_rights ar
                LEFT JOIN navigation_dashboard_links l 
                    ON LOWER(CONVERT(ar.modulname USING utf8mb4)) COLLATE utf8mb4_general_ci = LOWER(l.modulname)
                LEFT JOIN navigation_dashboard_categories c 
                    ON l.catID = c.catID
                WHERE ar.roleID = $roleID
                ORDER BY c.sort ASC, l.sort ASC
            ";

            $rights_result = safe_query($rights_query);
            if (!mysqli_num_rows($rights_result)) {
                $output .= '<p class="text-muted fst-italic">Keine Rechte zugewiesen.</p>';
                $output .= '</div>';
                continue;
            }

            // --- Nach Kategorien gruppieren ---
            $rights = [];
            while ($r = mysqli_fetch_assoc($rights_result)) {
                $cat = $r['category_name'] ?: 'Allgemein';
                $rights[$cat][] = $r;
                #$output .= '</div>';
            }

            // --- Darstellung ---
            foreach ($rights as $catName => $items) {
                $translate->detectLanguages($catName);
                $catTitle = htmlspecialchars($translate->getTextByLanguage($catName));

                $output .= '
                <div class="list-group mb-4 shadow-sm">
                    <div class="list-group-item bg-secondary text-white d-flex justify-content-between align-items-center">

                        <div><i class="bi bi-folder2-open me-2"></i> ' . $catTitle . '</div>
                        <small class="text-light">' . count($items) . ' Module</small>
                    </div>
                ';

                foreach ($items as $item) {
                    $modulname = htmlspecialchars($item['modulname']);
                    $translate->detectLanguages($item['module_name']);
                    $displayName = htmlspecialchars($translate->getTextByLanguage($item['module_name']));

                    $output .= '
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <p class="mb-1"><i class="bi bi-puzzle"></i> ' . $displayName . '</p>
                            <small class="text-muted">' . $modulname . '</small>
                        </div>
                    </div>
                    ';
                }

                $output .= '</div>'; // Ende list-group
            }
$output .= '</div>';
        }

    } catch (Throwable $e) {
        echo '<div class="alert alert-danger"><b>Datenbankfehler:</b> ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-person-badge"></i> Benutzerrechte & Rollen
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=user_roles">Benutzerrollen</a></li>
            <li class="breadcrumb-item active" aria-current="page">Rechte des Benutzers</li>
        </ol>
    </nav>

    <div class="card-body">
        <div class="container py-4">
            <h4 class="mb-3"><i class="bi bi-person"></i> Benutzerinfo</h4>
            <h6><strong>Benutzername:</strong> <?= $username ?></h6>

            <h6 class="mt-4 mb-3"><i class="bi bi-key"></i> Zugewiesene Rollen & Rechte im Admincenter</h6>
            <!-- Info-Hinweis -->
            <div class="alert alert-info d-flex align-items-center mt-3" role="alert">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                    Diese Übersicht zeigt alle Rollen und die dazugehörigen Rechte, 
                    die diesem Benutzer im Admincenter zugewiesen wurden.
                </div>
            </div>
            <?= $output ?>

            <a href="admincenter.php?site=user_roles&action=admins" class="btn btn-primary mt-4">
                <i class="bi bi-arrow-left"></i> Zurück
            </a>
        </div>
    </div>
</div>

<?php

      
} elseif ($action == "admins") {

    // === CSRF-Token generieren ===
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // --- ALLE ROLLEN LÖSCHEN ---
    if (isset($_GET['delete_all_roles'])) {
        $userID = (int)$_GET['delete_all_roles'];

        if ($userID > 0) {
            safe_query("DELETE FROM user_role_assignments WHERE userID = $userID");
            $_SESSION['csrf_success'] = $languageService->get('all_roles_removed_successfully') ?? 'Alle Rollen wurden entfernt.';
            header("Location: admincenter.php?site=user_roles&action=admins");
            exit();
        } else {
            $_SESSION['csrf_error'] = $languageService->get('invalid_user_id') ?? 'Ungültige Benutzer-ID.';
            header("Location: admincenter.php?site=user_roles&action=admins");
            exit();
        }
    }

    // --- EINZELNE ROLLE LÖSCHEN ---
    if (isset($_GET['remove_role'])) {
        $assignmentID = (int)$_GET['remove_role'];
        if ($assignmentID > 0) {
            // Nexpell verwendet standardmäßig "assignmentID" als Primärschlüssel
            safe_query("DELETE FROM user_role_assignments WHERE assignmentID = $assignmentID");
            $_SESSION['csrf_success'] = $languageService->get('role_removed_successfully') ?? 'Rolle wurde entfernt.';
        } else {
            $_SESSION['csrf_error'] = 'Ungültige Rollen-ID.';
        }
        header("Location: admincenter.php?site=user_roles&action=admins");
        exit();
    }

    // === POST: Neue Rolle zuweisen ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF-Prüfung
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['csrf_error'] = $languageService->get('csrf_error_message') ?? 'CSRF-Token ungültig.';
            header("Location: admincenter.php?site=user_roles&action=admins");
            exit();
        }

        // Rolle zuweisen
        if (isset($_POST['assign_role'])) {
            $userID = (int)$_POST['user_id'];
            $roleID = (int)$_POST['role_id'];

            // Prüfen, ob Zuweisung bereits existiert
            $existing_assignment = safe_query("SELECT * FROM user_role_assignments WHERE userID = '$userID' AND roleID = '$roleID'");
            if (mysqli_num_rows($existing_assignment) > 0) {
                $_SESSION['csrf_error'] = $languageService->get('role_already_assigned') ?? 'Diese Rolle wurde bereits zugewiesen.';
                header("Location: admincenter.php?site=user_roles&action=admins");
                exit();
            }

            safe_query("INSERT INTO user_role_assignments (userID, roleID) VALUES ('$userID', '$roleID')");
            $_SESSION['csrf_success'] = $languageService->get('role_assigned_successfully') ?? 'Rolle wurde erfolgreich zugewiesen.';
            header("Location: admincenter.php?site=user_roles&action=admins");
            exit();
        }
    }

    // === Fehlermeldungen anzeigen ===
    if (isset($_SESSION['csrf_error'])) {
        echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['csrf_error']) . '</div>';
        unset($_SESSION['csrf_error']);
    }

    // === Erfolgsmeldungen anzeigen ===
    if (isset($_SESSION['csrf_success'])) {
        echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['csrf_success']) . '</div>';
        unset($_SESSION['csrf_success']);
    }

/**
 * Gibt die kombinierten Forumrechte für eine bestimmte Rolle zurück.
 * Nutzt Tabelle: plugins_forum_permissions
 */
function nx_getForumPermissionsByRole(int $roleID): string
{
    global $_database;

    // 🛡 Tabelle prüfen (verhindert Fatal Error, wenn Forum-Plugin fehlt)
    $check = $_database->query("SHOW TABLES LIKE 'plugins_forum_permissions'");
    if (!$check || $check->num_rows === 0) {
        // Tabelle existiert nicht → Plugin Forum nicht installiert
        return '<span class="text-muted">Forum-Plugin nicht installiert</span>';
    }

    // 🔹 Rechte abfragen
    $res = safe_query("
        SELECT 
            MAX(can_view)   AS can_view,
            MAX(can_read)   AS can_read,
            MAX(can_post)   AS can_post,
            MAX(can_reply)  AS can_reply,
            MAX(is_mod)     AS is_mod,
            MAX(can_delete) AS can_delete
        FROM plugins_forum_permissions
        WHERE role_id = $roleID
    ");

    if (!$res || mysqli_num_rows($res) === 0) {
        return '<span class="text-muted">Keine</span>';
    }

    $p = mysqli_fetch_assoc($res);
    $rights = [];

    // 🔸 Rechte-Badges
    if (!empty($p['can_view']))   $rights[] = '<span class="badge bg-light text-dark">👁 Ansicht</span>';
    if (!empty($p['can_read']))   $rights[] = '<span class="badge bg-success">📖 Lesen</span>';
    if (!empty($p['can_post']))   $rights[] = '<span class="badge bg-primary">📝 Thema erstellen</span>';
    if (!empty($p['can_reply']))  $rights[] = '<span class="badge bg-info text-dark">💬 Antworten</span>';
    if (!empty($p['can_delete'])) $rights[] = '<span class="badge bg-danger">🗑 Löschen</span>';

    // 🔸 Moderator-Rechte
    if (!empty($p['is_mod'])) {
        $rights[] = '<span class="badge bg-warning text-dark">🛡 Moderator</span>';
    }

    // 🔸 Ausgabe
    return !empty($rights)
        ? implode(' ', $rights)
        : '<span class="text-muted">Keine Rechte</span>';
}


    ?>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-paragraph"></i> <?= $languageService->get('regular_users') ?>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item"><a href="admincenter.php?site=user_roles"><?= $languageService->get('regular_users') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('assign_role_to_user') ?></li>
            </ol>
        </nav>

        <div class="card-body">
            <div class="container py-4">
                <!-- Benutzerrolle zuweisen -->
                <h3 class="mb-4"><?= $languageService->get('assign_role_to_user') ?></h3>
                <form method="post" class="row g-3 mb-5">
                    <div class="col-auto">
                        <label for="user_id" class="form-label"><?= $languageService->get('username') ?></label>
                        <select name="user_id" class="form-select" required>
                            <?php
                            $admins = safe_query("SELECT * FROM users ORDER BY username");
                            while ($admin = mysqli_fetch_assoc($admins)) : ?>
                                <option value="<?= $admin['userID'] ?>"><?= htmlspecialchars($admin['username']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-auto">
                        <label for="role_id" class="form-label"><?= $languageService->get('role_name') ?></label>
                        <select name="role_id" class="form-select" required>
                            <?php
                            $roles_for_assign = safe_query("SELECT * FROM user_roles WHERE is_active = 1 ORDER BY role_name");
                            while ($role = mysqli_fetch_assoc($roles_for_assign)) :
                            ?>
                                <option value="<?= $role['roleID'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-auto align-self-end">
                        <button type="submit" name="assign_role" class="btn btn-primary">
                            <?= $languageService->get('assign_role_to_user') ?>
                        </button>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                </form>

                <!-- Zuweisungen anzeigen -->
                <h3 class="mb-4"><?= $languageService->get('available_roles') ?></h3>
                <table class="table table-bordered table-striped bg-white shadow-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= $languageService->get('username') ?></th>
                            <th><?= $languageService->get('role_name') ?></th>
                            <th><?= $languageService->get('forum_permissions') ?? 'Forum-Rechte' ?></th>
                            <th style="width: 350px"><?= $languageService->get('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // === Benutzer + Rollen laden (auch ohne Rollen) ===
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
                        echo '<tr><td colspan="3" class="text-danger">SQL-Fehler: ' . mysqli_error($_database) . '</td></tr>';
                    } elseif (mysqli_num_rows($assignments) === 0) {
                        echo '<tr><td colspan="3" class="text-muted text-center">Keine Benutzer gefunden.</td></tr>';
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

                                // ❌ Einzelne Rolle löschen
                                $remove = '<a href="admincenter.php?site=user_roles&action=admins&remove_role='
                                    . intval($role['assignID']) . '&userID=' . $userID . '" '
                                    . 'class="text-danger ms-1" title="Rolle löschen" '
                                    . 'onclick="return confirm(\'Rolle ' . $cleanRole . ' wirklich entfernen?\')">'
                                    . '<i class="bi bi-x-circle"></i></a><br>';

                                $roleBadges[] = $badge . $remove;
                            }

                            echo '<tr>';
                            echo '<td>' . $username . '</td>';
                            echo '<td>' . (!empty($roleBadges) ? implode(' ', $roleBadges) : '<span class="text-muted">Keine Rollen</span>') . '</td>';
                            // 🔹 Forum-Rechte anzeigen
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
                                echo '<span class="text-muted">Keine</span>';
                            }
                            echo '</td>';
                            echo '<td>';
                            echo '<a href="admincenter.php?site=user_roles&action=user_role_details&userID=' . $userID . '" class="btn btn-warning btn-sm">'
                                . $languageService->get('view_assigned_rights') . '</a> ';
                            echo '<a href="admincenter.php?site=user_roles&action=admins&delete_all_roles=' . $userID . '" '
                                . 'class="btn btn-danger btn-sm" '
                                . 'onclick="return confirm(\'' . $languageService->get('remove_all_roles_confirm') . '\')">'
                                . ($languageService->get('remove_all_roles') ?? 'Alle Rollen entfernen') . '</a>';
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
<?php

 // Ende if $action == admins


    // Überprüfen, ob die Parameter 'delete_assignment' und 'roleID' in der URL gesetzt sind
/*if (isset($_GET['delete_assignment']) && isset($_GET['roleID'])) {
    // Sichere die Parameter und konvertiere sie in Ganzzahlen
    $userID = (int)$_GET['delete_assignment'];
    $roleID = (int)$_GET['roleID'];

    // SQL-Abfrage ausführen, um die Zuweisung zu entfernen
    $result = safe_query("DELETE FROM user_role_assignments WHERE userID = '$userID' AND roleID = '$roleID'");

    // Erfolgreiche Löschung und Weiterleitung
    if ($result) {
        $_SESSION['success_message'] = "Rolle erfolgreich entfernt.";
    } else {
        $_SESSION['error_message'] = "Fehler beim Entfernen der Rolle.";
    }

    // Weiterleitung zur Admin-Seite für Benutzerrollen
    header("Location: admincenter.php?site=user_roles&action=admins");
    exit();
}*/


} elseif ($action == "roles") {


// === Aktivieren/Deaktivieren von Rollen ===
if (isset($_GET['toggle_role'])) {
    $roleID = (int)$_GET['toggle_role'];

    // aktuellen Zustand abrufen
    $res = safe_query("SELECT is_active FROM user_roles WHERE roleID = $roleID");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $newState = ($row['is_active'] == 1) ? 0 : 1;
        safe_query("UPDATE user_roles SET is_active = $newState WHERE roleID = $roleID");

        $_SESSION['success_message'] = $newState
            ? $languageService->get('role_activated')
            : $languageService->get('role_deactivated');

        header("Location: admincenter.php?site=user_roles&action=roles");
        exit;
    }
}    


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Überprüfung
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['csrf_error'] = $languageService->get('csrf_error_message');
        header("Location: admincenter.php?site=user_roles"); // Weiterleitung zur vorherigen Seite
        exit();
    }

    // Rolle zuweisen
    if (isset($_POST['assign_role'])) {
        $userID = (int)$_POST['user_id'];  // Benutzer-ID
        $roleID = (int)$_POST['role_id'];  // Rollen-ID

        // Überprüfen, ob die Rolle bereits zugewiesen wurde
        $existing_assignment = safe_query("SELECT * FROM user_role_assignments WHERE userID = '$userID' AND roleID = '$roleID'");
        if (mysqli_num_rows($existing_assignment) > 0) {
            $_SESSION['csrf_error'] = $languageService->get('role_already_assigned');
            header("Location: admincenter.php?site=user_roles");
            exit();
        }

        // Zuweisung in der Tabelle speichern
        safe_query("INSERT INTO user_role_assignments (userID, roleID) VALUES ('$userID', '$roleID')");

        // Erfolgsmeldung
        $_SESSION['success_message'] = $languageService->get('role_assigned_successfully');
        header("Location: admincenter.php?site=user_roles");
        exit();
    }
}

// Fehler nach CSRF-Überprüfung anzeigen
if (isset($_SESSION['csrf_error'])): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($_SESSION['csrf_error']) ?>
    </div>
    <?php unset($_SESSION['csrf_error']); ?> <!-- Fehlernachricht nach einmaligem Anzeigen entfernen -->
<?php endif; ?>

<!-- Erfolgsnachricht anzeigen -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?> <!-- Erfolgsnachricht nach einmaligem Anzeigen entfernen -->
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> <?= $languageService->get('regular_users') ?>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=user_roles"><?= $languageService->get('regular_users') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('manage_admin_roles') ?></li>
        </ol>
    </nav>

    <div class="card-body">
        <div class="container py-5">
            <h2 class="mb-4"><?= $languageService->get('manage_admin_roles') ?></h2>

            <!-- Rollenliste -->
            <h3 class="mb-4"><?= $languageService->get('available_roles') ?></h3>
            <table class="table table-bordered table-striped bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th><?= $languageService->get('role_name') ?></th>
                        <th><?= $languageService->get('permissions') ?></th>
                        <th style="width: 200px"><?= $languageService->get('actions') ?></th>
                        <th style="width: 150px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $roles = safe_query("SELECT * FROM user_roles ORDER BY role_name");
                    while ($role = mysqli_fetch_assoc($roles)) : ?>
                        <tr>
                            <td><?= htmlspecialchars($role['role_name']) ?></td>
                            <td><?= htmlspecialchars($role['description'] ?? $languageService->get('no_permissions_defined')) ?></td>
                            <td>
                                <a href="admincenter.php?site=user_roles&action=edit_role_rights&roleID=<?= (int)$role['roleID'] ?>" class="btn btn-warning">
                                    <?= $languageService->get('edit_rights') ?>
                                </a>
                            </td>
                            <td>
                                <?php if ((int)$role['is_active'] === 1): ?>
                                    <span class="badge bg-success"><?= $languageService->get('active') ?></span>
                                    <a href="admincenter.php?site=user_roles&action=roles&toggle_role=<?= (int)$role['roleID'] ?>"
                                       class="btn btn-outline-danger btn-sm ms-2">
                                        <i class="bi bi-x-circle"></i> <?= $languageService->get('deactivate') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= $languageService->get('inactive') ?></span>
                                    <a href="admincenter.php?site=user_roles&action=roles&toggle_role=<?= (int)$role['roleID'] ?>"
                                       class="btn btn-outline-success btn-sm ms-2">
                                        <i class="bi bi-check-circle"></i> <?= $languageService->get('activate') ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
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
            $username = $user['username'];
            $email = $user['email'];

            if ($user['is_active'] != 1) {
                echo "<div class=\"alert alert-warning\" role=\"alert\">Benutzerkonto ist noch nicht aktiviert.</div>";
                exit();
            }
        } else {
            echo "<div class=\"alert alert-info\" role=\"alert\">Benutzer nicht gefunden.</div>";
            exit();
        }

        if (isset($_POST['submit']) || isset($_POST['reset_password'])) {
            // CSRF-Schutz
            if (!function_exists('generate_csrf_token') || !function_exists('verify_csrf_token')) {
                die("CSRF-Funktionen nicht verfügbar. Bitte sicherstellen, dass csrf_helper.php eingebunden ist.");
            }

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                die("Ungültiges CSRF-Token.");
            }

            $username = mysqli_real_escape_string($_database, $_POST['username']);
            $email = mysqli_real_escape_string($_database, $_POST['email']);
            $new_password_plain = trim($_POST['password']);
            $reset_password = isset($_POST['reset_password']) && $_POST['reset_password'] == "1";

            // Seiteinstellungen
            $hp_title = get_all_settings('hptitle');
            $hp_url = get_all_settings('hp_url');

            $send_password = false;

            if (!empty($new_password_plain) || $reset_password) {
                if ($reset_password && empty($new_password_plain)) {
                    $new_password_plain = LoginSecurity::generateTemporaryPassword();
                    $send_password = true;
                }

                $new_pepper = LoginSecurity::generateRandomPepper();
                $pepper_encrypted = LoginSecurity::encryptPepper($new_pepper); // <-- geändert!
                $password_hash = password_hash($new_password_plain . $new_pepper, PASSWORD_DEFAULT);

                $query = "UPDATE users SET username = ?, email = ?, password_hash = ?, password_pepper = ? WHERE userID = ?";
                $stmt = $_database->prepare($query);

                if ($stmt === false) {
                    die("SQL-Fehler: " . $_database->error);
                }

                $stmt->bind_param("ssssi", $username, $email, $password_hash, $pepper_encrypted, $userID);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $adminID = $_SESSION['userID'];
                    $admin_query = safe_query("SELECT email, username FROM users WHERE userID = $adminID");
                    $admin = mysqli_fetch_assoc($admin_query);
                    $admin_email = $admin['email'];
                    $admin_name = $admin['username'];

                    if ($send_password) {
                        $vars = ['%pagetitle%', '%email%', '%new_password%', '%homepage_url%', '%admin_name%', '%admin_email%'];
                        $repl = [$hp_title, $email, $new_password_plain, $hp_url, $admin_name, $admin_email];

                        $subject = str_replace($vars, $repl, $languageService->get('email_subject'));
                        $message = str_replace($vars, $repl, $languageService->get('email_text'));

                        $sendmail = Email::sendEmail($admin_email, 'Passwort zurückgesetzt', $email, $subject, $message);

                        if ($sendmail['result'] === 'fail') {
                            echo generateErrorBoxFromArray($languageService->get('email_failed'), [$sendmail['error']]);
                        } else {
                            echo $languageService->get('password_reset_success') ?? 'E-Mail wurde erfolgreich versendet.';
                        }
                    }

                    $_SESSION['success_message'] = $languageService->get('password_reset_success') ?? 'Passwort wurde neu gesetzt.';
                } else {
                    echo generateErrorBoxFromArray($languageService->get('user_update_failed'), []);
                }
            } else {
                $query = "UPDATE users SET username = ?, email = ? WHERE userID = ?";
                $stmt = $_database->prepare($query);

                if ($stmt === false) {
                    die("SQL-Fehler: " . $_database->error);
                }

                $stmt->bind_param("ssi", $username, $email, $userID);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = $languageService->get('user_updated');
                } else {
                    $_SESSION['error_message'] = $languageService->get('user_update_failed');
                }
            }

            header("Location: admincenter.php?site=user_roles");
            exit();
        }

        // HTML-Ausgabe
        $csrf_token = generate_csrf_token();
        ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-paragraph"></i> <?= $languageService->get('regular_users') ?>
            </div>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb t-5 p-2 bg-light">
                    <li class="breadcrumb-item">
                        <a href="admincenter.php?site=user_roles"><?= $languageService->get('regular_users') ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('user_edit') ?></li>
                </ol>
            </nav>

            <div class="card-body">
                <div class="container py-5">
                    <h2 class="mb-4"><?= $languageService->get('user_edit') ?></h2>

                    <form method="post" class="row g-4 align-items-stretch">
                        <input type="hidden" name="userID" value="<?= htmlspecialchars($user['userID']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="col-md-6">
                            <label for="username" class="form-label"><?= $languageService->get('username') ?></label>
                            <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label"><?= $languageService->get('email') ?></label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        </div>

                        <!-- Manuelles Passwort -->
                        <div class="col-md-6 d-flex flex-column justify-content-between">
                            <div>
                                <label for="password" class="form-label"><?= $languageService->get('set_password_manually') ?? 'Neues Passwort manuell setzen (optional)' ?></label>
                                <input type="password" id="password" name="password" class="form-control">
                            </div>
                            <div class="alert alert-warning d-flex align-items-center mt-3 mb-0 flex-grow-1" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                <div>
                                    <?= $languageService->get('manual_password_info') ?? 'Nur ausfüllen, wenn du selbst ein neues Passwort setzen möchtest.' ?>
                                </div>
                            </div>
                        </div>

                        <!-- Automatisches Passwort -->
                        <div class="col-md-6 d-flex flex-column justify-content-between">
                            <div>
                                <label for="password_auto" class="form-label">Neues Passwort automatisch setzen</label>
                                <button type="submit" name="reset_password" value="1" class="btn btn-danger w-100"
                                    onclick="return confirm('<?= $languageService->get('confirm_reset_password') ?? 'Automatisch neues Passwort setzen?' ?>');">
                                    🔄 <?= $languageService->get('reset_password') ?? 'Passwort automatisch zurücksetzen' ?>
                                </button>
                            </div>
                            <div class="alert alert-warning d-flex align-items-center mt-3 mb-0 flex-grow-1" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                <div>
                                    Mit dieser Funktion wird automatisch ein neues, zufälliges Passwort für den Benutzer generiert. 
                                    Das bisherige Passwort wird dabei sofort ungültig. 
                                    Der Benutzer muss sich anschließend mit dem neuen Passwort anmelden.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" name="submit" class="btn btn-warning"><?= $languageService->get('save_user') ?></button>
                        </div>
                    </form>
                </div>
            </div>

        <?php
    } else {
        echo "Ungültige Benutzer-ID.";
        exit();
    }
}


 elseif ($action == "user_create") {





if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = htmlspecialchars(trim($_POST['username']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validierung
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "❌ Ungültige E-Mail-Adresse.";
        header("Location: admincenter.php?site=user_roles");
        exit();
    }

    if (strlen($password) < 8) {
        $_SESSION['error_message'] = "❌ Das Passwort muss mindestens 8 Zeichen lang sein.";
        header("Location: admincenter.php?site=user_roles");
        exit();
    }

    // Prüfen, ob E-Mail bereits vorhanden ist
    $query = "SELECT userID FROM users WHERE email = ?";
    if ($stmt = $_database->prepare($query)) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error_message'] = "❌ Diese E-Mail-Adresse wird bereits verwendet.";
            header("Location: admincenter.php?site=user_roles");
            exit();
        }
    }

    // Benutzer einfügen (registerdate als DATETIME)
    $query = "INSERT INTO users (username, email, registerdate) VALUES (?, ?, NOW())";
    if ($stmt = $_database->prepare($query)) {
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $userID = $_database->insert_id;

        if ($userID > 0) {
            // Pepper erzeugen und verschlüsseln
            $pepper_plain = LoginSecurity::generatePepper();
            $pepper_encrypted = LoginSecurity::encryptPepper($pepper_plain);

            // Passwort-Hash erstellen
            $password_hash = LoginSecurity::createPasswordHash($password, $email, $pepper_plain);

            // Passwort und verschlüsselten Pepper speichern
            $query = "UPDATE users SET password_hash = ?, password_pepper = ?, is_active = 1 WHERE userID = ?";
            if ($stmt = $_database->prepare($query)) {
                $stmt->bind_param('ssi', $password_hash, $pepper_encrypted, $userID);
                $stmt->execute();

                $_SESSION['success_message'] = $languageService->get('user_created_successfully');
                header("Location: admincenter.php?site=user_roles");
                exit();
            } else {
                $_SESSION['error_message'] = "❌ Fehler beim Speichern des Passworts.";
                header("Location: admincenter.php?site=user_roles");
                exit();
            }
        } else {
            $_SESSION['error_message'] = $languageService->get('user_creation_error');
            header("Location: admincenter.php?site=user_roles");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "❌ Fehler bei der Benutzererstellung.";
        header("Location: admincenter.php?site=user_roles");
        exit();
    }
}




?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> <?= $languageService->get('regular_users') ?>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=user_roles"><?= $languageService->get('regular_users') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $languageService->get('add_user') ?></li>
        </ol>
    </nav>

    <div class="card-body">

        <div class="container py-5">
            <h2 class="mb-4"><?= $languageService->get('add_user') ?></h2>

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
                <button type="submit" class="btn btn-success"><?= $languageService->get('add_user') ?></button>
            </form>
        </div>

    </div>
</div>





<?php
} else { 


// Anzahl der Einträge pro Seite
$users_per_page = 5;

// Aktuelle Seite ermitteln
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $users_per_page;

// Anzahl der Benutzer ermitteln (für die Paginierung)
$total_users_query = safe_query("SELECT COUNT(*) as total FROM users");
$total_users = mysqli_fetch_assoc($total_users_query)['total'];
$total_pages = ceil($total_users / $users_per_page);

if (isset($_GET['action']) && $_GET['action'] == 'delete_user' && isset($_GET['userID'])) {
    $userID = (int)$_GET['userID'];

    // Überprüfe, ob der Benutzer existiert
    $user_check = safe_query("SELECT * FROM users WHERE userID = '$userID'");
    if (mysqli_num_rows($user_check) > 0) {
        // Zuerst die zugehörigen Einträge aus der rm_216_user_role_assignments Tabelle löschen
        safe_query("DELETE FROM user_role_assignments WHERE userID = '$userID'");

        // Jetzt den Benutzer aus der user Tabelle löschen
        safe_query("DELETE FROM users WHERE userID = '$userID'");

        $_SESSION['success_message'] = "<div class=\"alert alert-success\" role=\"alert\">Benutzer wurde erfolgreich gelöscht.</div>";
    } else {
        $_SESSION['error_message'] = "<div class=\"alert alert-info\" role=\"alert\">Benutzer nicht gefunden.</div>";
    }

    // Weiterleitung zurück zur Benutzerverwaltung
    header("Location: admincenter.php?site=user_roles");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ban_user'])) {
    $userID = $_POST['userID'];
    $userID = intval($userID);  // Sicherheit: Umwandlung in eine ganze Zahl

    // Bann den Benutzer (Setze das Feld 'is_locked' auf 1)
    $query = "UPDATE users SET is_locked = 1 WHERE userID = $userID";
    if (safe_query($query)) {
        $_SESSION['success_message'] = "<div class=\"alert alert-success\" role=\"alert\">Benutzer wurde erfolgreich gebannt.</div>";
    } else {
        $_SESSION['error_message'] = "<div class=\"alert alert-info\" role=\"alert\">Fehler beim Bann des Benutzers.</div>";
    }

    // Weiterleitung oder Fehleranzeige

    header("Location: admincenter.php?site=user_roles");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unban_user'])) {
    $userID = $_POST['userID'];
    $userID = intval($userID);  // Sicherheit: Umwandlung in eine ganze Zahl

    // Hebe den Bann des Benutzers auf (Setze das Feld 'is_locked' auf 0)
    $query = "UPDATE users SET is_locked = 0 WHERE userID = $userID";
    if (safe_query($query)) {
        $_SESSION['success_message'] = "<div class=\"alert alert-success\" role=\"alert\">Benutzer wurde erfolgreich entbannt.</div>";
    } else {
        $_SESSION['error_message'] = "<div class=\"alert alert-info\" role=\"alert\">Fehler beim Entbannen des Benutzers.</div>";
    }

    // Weiterleitung oder Fehleranzeige
    header("Location: admincenter.php?site=user_roles");
    exit();
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deactivate_user'])) {
    $userID = intval($_POST['userID']); // Sicherheit: Umwandlung in eine ganze Zahl

    // Deaktiviere den Benutzer (Setze das Feld 'is_active' auf 0)
    $query = "UPDATE users SET is_active = 0 WHERE userID = $userID";
    if (safe_query($query)) {
        $_SESSION['success_message'] = "<div class=\"alert alert-success\" role=\"alert\">Benutzer wurde erfolgreich deaktiviert.</div>";
    } else {
        $_SESSION['error_message'] = "<div class=\"alert alert-info\" role=\"alert\">Fehler beim Deaktivieren des Benutzers.</div>";
    }

    header("Location: admincenter.php?site=user_roles");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['activate_user'])) {
    $userID = intval($_POST['userID']); // Sicherheit: Umwandlung in eine ganze Zahl

    // Aktiviere den Benutzer (Setze das Feld 'is_active' auf 1)
    $query = "UPDATE users SET is_active = 1 WHERE userID = $userID";
    if (safe_query($query)) {
        $_SESSION['success_message'] = "<div class=\"alert alert-success\" role=\"alert\">Benutzer wurde erfolgreich aktiviert.</div>";
    } else {
        $_SESSION['error_message'] = "<div class=\"alert alert-info\" role=\"alert\">Fehler beim Aktivieren des Benutzers.</div>";
    }

    header("Location: admincenter.php?site=user_roles");
    exit();
}



// Abfrage der Benutzer für die aktuelle Seite
$users = safe_query("SELECT * FROM users ORDER BY userID LIMIT $offset, $users_per_page");
?>
<div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> Benutzer- und Rechteverwaltung
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=user_roles"><?= $languageService->get('regular_users') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav>

    <div class="card-body">

        <div class="form-group row">
            <label class="col-md-1 control-label"><?= $languageService->get('options') ?>:</label>
            <div class="col-md-8">
                <a href="admincenter.php?site=user_roles&action=roles" class="btn btn-primary" type="button"><?= $languageService->get('manage_admin_roles') ?></a>      
                <a href="admincenter.php?site=user_roles&action=admins" class="btn btn-primary" type="button"><?= $languageService->get('assign_role_to_user') ?></a>
            </div>
        </div>
        <div class="container py-5">
            <h2 class="mb-4"><?= $languageService->get('regular_users') ?></h2>
        <!-- Button zum Hinzufügen eines neuen Benutzers -->
        <div class="mb-3">
            <a href="admincenter.php?site=user_roles&action=user_create" class="btn btn-success">
                <?= $languageService->get('add_user') ?>
            </a>
        </div>

        <!-- Benutzerliste -->
        <table class="table table-bordered table-striped bg-white shadow-sm">
            <thead class="table-light">
                <tr>
                    <th><?= $languageService->get('id') ?></th>
                    <th><?= $languageService->get('username') ?></th>
                    <th><?= $languageService->get('email') ?></th>
                    <th><?= $languageService->get('registered_on') ?></th>
                    <th><?= $languageService->get('activated') ?></th>
                    <th width="350"><?= $languageService->get('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($users)) : ?>
                    <tr>
                        <td><?= htmlspecialchars($user['userID']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= date('d.m.Y H:i:s', strtotime($user['registerdate'])) ?></td>
                        <td><?= $user['is_active'] ? '✔️' : '❌' ?>

                            <?php if (!$user['is_active']) : ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="userID" value="<?= $user['userID'] ?>">
                                <button type="submit" name="activate_user" class="btn btn-success">
                                    <?= $languageService->get('activate_user') ?>
                                </button>
                            </form>
                        <?php else : ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="userID" value="<?= $user['userID'] ?>">
                                <button type="submit" name="deactivate_user" class="btn btn-warning">
                                    <?= $languageService->get('deactivate_user') ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        </td>
                        <td>


                            <?php if ($user['is_locked']) : ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="userID" value="<?= $user['userID'] ?>">
                                    <button type="submit" name="unban_user" class="btn btn-success">
                                        <?= $languageService->get('unban_user') ?>
                                    </button>
                                </form>
                            <?php else : ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="userID" value="<?= $user['userID'] ?>">
                                    <button type="submit" name="ban_user" class="btn btn-danger">
                                        <?= $languageService->get('ban_user') ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="admincenter.php?site=user_roles&action=edit_user&userID=<?= $user['userID'] ?>" class="btn btn-warning">
                                <?= $languageService->get('edit') ?>
                            </a>

                            <a href="admincenter.php?site=user_roles&action=delete_user&userID=<?= $user['userID'] ?>" class="btn btn-danger" onclick="return confirm('<?= $languageService->get('confirm_delete') ?>')">
                                <?= $languageService->get('delete') ?>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>


    <!-- Paginierung -->
    <nav aria-label="Seiten-Navigation">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= ($page == 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="admincenter.php?site=user_roles&page=1">
                <?= $languageService->get('first') ?>
            </a>
        </li>
        <li class="page-item <?= ($page == 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="admincenter.php?site=user_roles&page=<?= ($page - 1) ?>">
                <?= $languageService->get('previous') ?>
            </a>
        </li>

        <!-- Dynamische Seitenzahlen -->
        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="admincenter.php?site=user_roles&page=<?= $i ?>">
                    <?= $i ?>
                </a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="admincenter.php?site=user_roles&page=<?= ($page + 1) ?>">
                <?= $languageService->get('next') ?>
            </a>
        </li>
        <li class="page-item <?= ($page == $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="admincenter.php?site=user_roles&page=<?= $total_pages ?>">
                <?= $languageService->get('last') ?>
            </a>
        </li>
    </ul>
</nav>
</div>

</div></div>
<?php
}

