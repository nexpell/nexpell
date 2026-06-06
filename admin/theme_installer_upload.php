<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../system/config.inc.php';

// DB-Verbindung
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) die("DB-Fehler: " . $_database->connect_error);
$_database->set_charset("utf8mb4");

// === Action setzen ===
$action = $_GET['action'] ?? 'list'; // list = Standard: Alle Themes anzeigen

$allThemes = ['brite', 'cerulean', 'cosmo', 'cyborg', 'darkly', 'flatly', 'journal', 'litera', 'lumen', 'lux', 'materia', 'minty', 'morph', 'pulse', 'quartz', 'sandstone', 'simplex', 'sketchy', 'slate', 'solar', 'spacelab', 'superhero', 'united', 'vapor', 'yeti', 'zephyr', 'default'];

// === Alle installierten Themes laden ===
$themes = [];
$result = $_database->query("SELECT * FROM settings_themes_installed ORDER BY name ASC");
while ($row = $result->fetch_assoc()) $themes[] = $row;

// === Löschen eines Themes ===
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $themeToDelete = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['delete']);

    // Theme-Ordner löschen
    $themeDir = __DIR__ . "/../includes/themes/default/css/dist/{$themeToDelete}/";
    if (is_dir($themeDir)) {
        $it = new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()) rmdir($file);
            else unlink($file);
        }
        rmdir($themeDir);
    }

    // DB-Eintrag löschen
    $stmt = $_database->prepare("DELETE FROM settings_themes_installed WHERE folder = ?");
    $stmt->bind_param("s", $themeToDelete);
    $stmt->execute();
    $stmt->close();

    echo "<div class='alert alert-success'>Theme <strong>{$themeToDelete}</strong> wurde gelöscht.</div>";
}

// === Bearbeiten eines Themes ===
$editing = false;
$themeData = null;
if ($action === 'edit' && isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editing = true;
    $themeID = intval($_GET['edit']);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_theme'])) {
        $name = trim($_POST['name']);
        $version = trim($_POST['version']);
        $author = trim($_POST['author']);
        $url = trim($_POST['url']);
        $description = trim($_POST['description']);

        $stmt = $_database->prepare("
            UPDATE settings_themes_installed
            SET name=?, version=?, author=?, url=?, description=?
            WHERE themeID=?
        ");
        $stmt->bind_param("sssssi", $name, $version, $author, $url, $description, $themeID);
        if ($stmt->execute()) {
            echo "<div class='alert alert-success'>Theme wurde aktualisiert.</div>";
        } else {
            echo "<div class='alert alert-danger'>Fehler beim Speichern: {$stmt->error}</div>";
        }
        $stmt->close();
    }

    // Aktuelle Werte laden
    $stmt = $_database->prepare("SELECT * FROM settings_themes_installed WHERE themeID=?");
    $stmt->bind_param("i", $themeID);
    $stmt->execute();
    $themeData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// === Upload-Funktion ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['themefile']) && !$editing) {
    $themeName = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['theme_name']));
    $version = trim($_POST['version']);
    $author = trim($_POST['author']);
    $url = trim($_POST['url']);
    $description = trim($_POST['description']);

    if (empty($themeName)) {
        echo "<div class='alert alert-warning'>Bitte gib einen gültigen Theme-Namen an.</div>";
    } else {
        $targetDir = '../includes/themes/default/css/dist/';
        $extractPath = $targetDir . $themeName . '/';
        $fileName = basename($_FILES['themefile']['name']);
        $fileTmp = $_FILES['themefile']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Prüfen ob DB-Eintrag existiert
        $check = $_database->prepare("SELECT COUNT(*) FROM settings_themes_installed WHERE folder = ?");
        $check->bind_param("s", $themeName);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            echo "<div class='alert alert-warning'>Theme <strong>$themeName</strong> ist bereits registriert.</div>";
        } else {
            if (!file_exists($extractPath)) mkdir($extractPath, 0755, true);
            $uploadSuccess = false;

            if ($fileExt === 'zip') {
                $zip = new ZipArchive;
                if ($zip->open($fileTmp) === TRUE) {
                    $zip->extractTo($extractPath);
                    $zip->close();
                    $uploadSuccess = true;
                } else {
                    echo "<div class='alert alert-danger'>Fehler beim Entpacken der ZIP.</div>";
                }
            } elseif ($fileExt === 'css') {
                $targetFile = $extractPath . 'bootstrap.min.css';
                if (move_uploaded_file($fileTmp, $targetFile)) $uploadSuccess = true;
                else echo "<div class='alert alert-danger'>Fehler beim Kopieren der CSS.</div>";
            } else {
                echo "<div class='alert alert-warning'>Nur ZIP oder CSS erlaubt.</div>";
            }

            if ($uploadSuccess) {
                $stmt = $_database->prepare("
                    INSERT INTO settings_themes_installed
                    (name, modulname, version, author, url, folder, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $modulname = 'Theme Manager';
                $stmt->bind_param("sssssss", $themeName, $modulname, $version, $author, $url, $themeName, $description);
                if ($stmt->execute()) {
                    echo "<div class='alert alert-success'>Theme <strong>$themeName</strong> erfolgreich hochgeladen und registriert!</div>";
                } else {
                    echo "<div class='alert alert-danger'>Fehler beim Eintragen in DB: {$stmt->error}</div>";
                }
                $stmt->close();
            }
        }
    }
}

// === Formular für Upload/Bearbeiten ===
if($action === 'add' || $action === 'edit'):
?>
<div class="card">
    <div class="card-header">
        <i class="bi <?= ($editing && $themeData) ? 'bi-pencil-square' : 'bi-upload' ?>"></i>
        <?= ($editing && $themeData) ? 'Theme bearbeiten: ' . htmlspecialchars($themeData['name']) : 'Manuelles Theme hochladen' ?>
    </div>
    <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item"><a href="admincenter.php?site=theme_installer">Themes verwalten</a></li>
                <li class="breadcrumb-item"><a href="admincenter.php?site=theme_installer&action=upload">Themes update</a></li>
                <li class="breadcrumb-item active" aria-current="page"><span class="breadcrumb-item active"><?= ($editing && $themeData) ? 'Bearbeiten' : 'Hinzufügen' ?></span></li>
            </ol>
        </nav>
    <div class="card-body">
        <div class="container py-5">
            <form method="post" <?= (!$editing) ? 'enctype="multipart/form-data"' : '' ?>>
                <?php if($editing): ?>
                    <input type="hidden" name="update_theme" value="1">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name:</label>
                        <input type="text" name="<?= ($editing) ? 'name' : 'theme_name' ?>" class="form-control" 
                               value="<?= ($editing) ? htmlspecialchars($themeData['name']) : '' ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Version:</label>
                        <input type="text" name="version" class="form-control" 
                               value="<?= ($editing) ? htmlspecialchars($themeData['version']) : '' ?>" 
                               placeholder="z. B. 1.0">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Autor:</label>
                        <input type="text" name="author" class="form-control" 
                               value="<?= ($editing) ? htmlspecialchars($themeData['author']) : '' ?>" 
                               placeholder="z. B. Max Mustermann">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Website / URL:</label>
                        <input type="text" name="url" class="form-control" 
                               value="<?= ($editing) ? htmlspecialchars($themeData['url']) : '' ?>" 
                               placeholder="https://example.com">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Beschreibung:</label>
                    <textarea name="description" class="form-control" rows="2" 
                              placeholder="Kurze Beschreibung des Themes..."><?= ($editing) ? htmlspecialchars($themeData['description']) : '' ?></textarea>
                </div>

                <?php if(!$editing): ?>
                <div class="mb-3">
                    <label for="themefile" class="form-label">Theme-Datei (ZIP oder CSS):</label>
                    <input type="file" name="themefile" id="themefile" class="form-control" required>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Speichern' : '<i class="bi bi-cloud-arrow-up"></i> Hochladen & Installieren' ?>
                </button>
                <a href="admincenter.php?site=theme_installer_upload" class="btn btn-secondary ms-2">Abbrechen</a>
            </form>
        </div>
    </div>
</div>
<?php
else:
?>
<!-- Liste installierter Themes -->
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>Installierte Themes</div>
        <div><a href="admincenter.php?site=theme_installer_upload&action=add" class="btn btn-success float-end">Neues Theme hochladen</a></div>   
  </div>
  <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item"><a href="admincenter.php?site=theme_installer">Themes verwalten</a></li>
                <li class="breadcrumb-item"><a href="admincenter.php?site=theme_installer&action=upload">Themes update</a></li>
                <li class="breadcrumb-item active" aria-current="page">ADD / EDIT</li>
            </ol>
        </nav> 
  <div class="card-body">
    <div class="container py-5">
        <?php if(empty($themes)): ?>
            <div class="alert alert-info">Keine Themes installiert.</div>
        <?php else: ?>
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Version</th>
                        <th>Autor</th>
                        <th>Ordner</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($themes as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['name']) ?></td>
                        <td><?= htmlspecialchars($t['version']) ?></td>
                        <td><?= htmlspecialchars($t['author']) ?></td>
                        <td><?= htmlspecialchars($t['folder']) ?></td>
                        <td class="text-end">
                            <?php if (!in_array($t['folder'], $allThemes)): ?>
                                <a href="?site=theme_installer_upload&action=edit&edit=<?= urlencode($t['themeID']) ?>" class="btn btn-warning">
                                    Bearbeiten
                                </a>
                            <?php endif; ?>
                            <a href="?site=theme_installer_upload&delete=<?= urlencode($t['folder']) ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Theme wirklich löschen?')">
                               Löschen
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
  </div>
</div>
<?php
endif;
?>
