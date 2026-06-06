<?php
// === /includes/admin/admin_themes.php ===
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

#require_once __DIR__ . '/../../system/config.inc.php';
#require_once __DIR__ . '/../../system/core/init.php';

use nexpell\LanguageService;

// === Globals ===
global $_database, $languageService;

if (!$_database || $_database->connect_errno) {
    die('<div class="alert alert-danger">❌ Datenbankverbindung fehlgeschlagen.</div>');
}

$_database->set_charset('utf8mb4');

// CSRF-Schutz
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// === Aktionen ===
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// === Theme aktivieren ===
if (isset($_POST['set_active'])) {
    $activeId = (int)$_POST['active_theme'];
    safe_query("UPDATE settings_themes SET active = 0");
    safe_query("UPDATE settings_themes SET active = 1 WHERE themeID = $activeId");
    echo '<div class="alert alert-success">✅ Theme wurde erfolgreich aktiviert.</div>';
}

// === Neues Theme speichern ===
if (isset($_POST['save_theme'])) {
    $name = escape($_POST['name']);
    $modulname = escape($_POST['modulname']);
    $pfad = escape($_POST['pfad']);
    $version = escape($_POST['version']);
    $themename = escape($_POST['themename']);
    $navbar_class = escape($_POST['navbar_class']);
    $navbar_theme = escape($_POST['navbar_theme']);

    safe_query("
        INSERT INTO settings_themes 
        (name, modulname, pfad, version, active, themename, navbar_class, navbar_theme, sort) 
        VALUES ('$name', '$modulname', '$pfad', '$version', 0, '$themename', '$navbar_class', '$navbar_theme', 0)
    ");
    echo '<div class="alert alert-success">✅ Neues Theme hinzugefügt.</div>';
}

// === Theme bearbeiten ===
if (isset($_POST['update_theme'])) {
    $themeID = (int)$_POST['themeID'];
    $name = escape($_POST['name']);
    $modulname = escape($_POST['modulname']);
    $pfad = escape($_POST['pfad']);
    $version = escape($_POST['version']);
    $themename = escape($_POST['themename']);
    $navbar_class = escape($_POST['navbar_class']);
    $navbar_theme = escape($_POST['navbar_theme']);

    safe_query("
        UPDATE settings_themes SET
            name = '$name',
            modulname = '$modulname',
            pfad = '$pfad',
            version = '$version',
            themename = '$themename',
            navbar_class = '$navbar_class',
            navbar_theme = '$navbar_theme'
        WHERE themeID = $themeID
    ");
    echo '<div class="alert alert-success">✅ Theme wurde aktualisiert.</div>';
}

// === Theme löschen ===
if ($action === 'delete' && $id > 0) {
    safe_query("DELETE FROM settings_themes WHERE themeID = $id");
    echo '<div class="alert alert-danger">🗑️ Theme gelöscht.</div>';
}

// === Theme bearbeiten Formular ===
if ($action === 'edit' && $id > 0) {
    $res = safe_query("SELECT * FROM settings_themes WHERE themeID = $id");
    if ($row = mysqli_fetch_assoc($res)) {
        ?>
        <div class="card my-4">
            <div class="card-header bg-warning"><strong>Theme bearbeiten</strong></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="themeID" value="<?= $row['themeID'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modulname</label>
                            <input type="text" name="modulname" class="form-control" value="<?= htmlspecialchars($row['modulname']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pfad</label>
                            <input type="text" name="pfad" class="form-control" value="<?= htmlspecialchars($row['pfad']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Version</label>
                            <input type="text" name="version" class="form-control" value="<?= htmlspecialchars($row['version']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Theme-Name</label>
                            <input type="text" name="themename" class="form-control" value="<?= htmlspecialchars($row['themename']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Navbar Class</label>
                            <input type="text" name="navbar_class" class="form-control" value="<?= htmlspecialchars($row['navbar_class']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Navbar Theme</label>
                            <input type="text" name="navbar_theme" class="form-control" value="<?= htmlspecialchars($row['navbar_theme']) ?>">
                        </div>
                    </div>
                    <button type="submit" name="update_theme" class="btn btn-warning mt-3">💾 Änderungen speichern</button>
                    <a href="admin_themes.php" class="btn btn-secondary mt-3">⬅️ Zurück</a>
                </form>
            </div>
        </div>
        <?php
        exit;
    }
}
?>

<!-- === Theme-Verwaltung === -->
<div class="card my-4">
    <div class="card-header bg-primary text-white"><strong>🎨 Theme-Verwaltung</strong></div>
    <div class="card-body">

        <!-- Neues Theme -->
        <form method="post" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-2"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
                <div class="col-md-2"><input type="text" name="modulname" class="form-control" placeholder="Modulname" required></div>
                <div class="col-md-2"><input type="text" name="pfad" class="form-control" placeholder="Pfad" required></div>
                <div class="col-md-1"><input type="text" name="version" class="form-control" placeholder="v1.0"></div>
                <div class="col-md-2"><input type="text" name="themename" class="form-control" placeholder="Theme-Name"></div>
                <div class="col-md-2"><input type="text" name="navbar_class" class="form-control" placeholder="navbar-dark"></div>
                <div class="col-md-1"><input type="text" name="navbar_theme" class="form-control" placeholder="dark"></div>
                <div class="col-md-12 text-end">
                    <button type="submit" name="save_theme" class="btn btn-success mt-2">➕ Neues Theme hinzufügen</button>
                </div>
            </div>
        </form>

        <!-- Theme Liste -->
        <form method="post">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Modulname</th>
                        <th>Pfad</th>
                        <th>Version</th>
                        <th>Navbar</th>
                        <th>Aktiv</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res = safe_query("SELECT * FROM settings_themes ORDER BY sort ASC, themeID ASC");
                    if (mysqli_num_rows($res)) {
                        while ($row = mysqli_fetch_assoc($res)) {
                            echo '<tr>
                                <td>' . $row['themeID'] . '</td>
                                <td>' . htmlspecialchars($row['name']) . '</td>
                                <td>' . htmlspecialchars($row['modulname']) . '</td>
                                <td>' . htmlspecialchars($row['pfad']) . '</td>
                                <td>' . htmlspecialchars($row['version']) . '</td>
                                <td><span class="badge bg-secondary">' . htmlspecialchars($row['navbar_theme']) . '</span></td>
                                <td class="text-center">
                                    <input type="radio" name="active_theme" value="' . $row['themeID'] . '" ' . ($row['active'] ? 'checked' : '') . '>
                                </td>
                                <td>
                                    <a href="?action=edit&id=' . $row['themeID'] . '" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                    <a href="?action=delete&id=' . $row['themeID'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Wirklich löschen?\')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>';
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center text-muted">Keine Themes vorhanden.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <div class="text-end">
                <button type="submit" name="set_active" class="btn btn-primary">💡 Aktives Theme speichern</button>
            </div>
        </form>

    </div>
</div>
