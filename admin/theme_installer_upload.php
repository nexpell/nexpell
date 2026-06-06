<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../system/config.inc.php';

if (!defined('THEME_INSTALLER_CONTEXT')) {
    header('Location: admincenter.php?site=theme_installer&action=upload');
    exit;
}

// DB-Verbindung
$_database = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($_database->connect_error) die("DB-Fehler: " . $_database->connect_error);
$_database->set_charset("utf8mb4");

$action = $_GET['upload_action'] ?? ($_GET['action'] ?? 'list');
if ($action === 'upload') {
    $action = 'list';
}

$allThemes = ['brite', 'cerulean', 'cosmo', 'cyborg', 'darkly', 'flatly', 'journal', 'litera', 'lumen', 'lux', 'materia', 'minty', 'morph', 'pulse', 'quartz', 'sandstone', 'simplex', 'sketchy', 'slate', 'solar', 'spacelab', 'superhero', 'united', 'vapor', 'yeti', 'zephyr', 'default'];

// Alle installierten Themes laden
$themes = [];
$result = $_database->query("SELECT * FROM settings_themes_installed ORDER BY name ASC");
while ($row = $result->fetch_assoc()) $themes[] = $row;

// Löschen eines Themes ===
if (isset($_GET['delete']) && !empty($_GET['delete'])) {

    $themeToDelete = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['delete']);

    // Captcha / Transaction prüfen
    $CAPCLASS = new \nexpell\Captcha;
    $captchaHash = $_GET['captcha_hash'] ?? '';

    if (!$CAPCLASS->checkCaptcha(0, $captchaHash)) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'transaction_invalid', false);
    }

    // Theme-Ordner löschen
    $themeDir = __DIR__ . "/../includes/themes/default/css/dist/{$themeToDelete}/";
    if (is_dir($themeDir)) {
        $it = new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        @rmdir($themeDir);
    }

    // DB-Eintrag löschen
    if (!$stmt = $_database->prepare("DELETE FROM settings_themes_installed WHERE folder = ?")) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }

    $stmt->bind_param("s", $themeToDelete);

    if (!$stmt->execute()) {
        $stmt->close();
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }

    $stmt->close();
    nx_audit_action('theme_installer', 'audit_action_theme_deleted', $themeToDelete, null, 'admincenter.php?site=theme_installer', ['theme' => $themeToDelete]);
    nx_redirect('admincenter.php?site=theme_installer','success',sprintf($languageService->get('alert_theme_deleted'), htmlspecialchars($themeToDelete, ENT_QUOTES, 'UTF-8')),false,true);
}

// Bearbeiten eines Themes
$editing = false;
$themeData = null;

if ($action === 'edit' && isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editing = true;
    $themeID = (int)$_GET['edit'];

    // Ordner (für Erfolgsmeldung) vorab laden
    if (!$stmt = $_database->prepare("SELECT folder FROM settings_themes_installed WHERE themeID=?")) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }
    $stmt->bind_param("i", $themeID);
    if (!$stmt->execute()) {
        $stmt->close();
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }
    $res = $stmt->get_result();
    $rowFolder = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$rowFolder || empty($rowFolder['folder'])) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_theme_not_found', false);
    }

    $themeFolder = (string)$rowFolder['folder'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_theme'])) {

        $name = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$stmt = $_database->prepare("
            UPDATE settings_themes_installed
            SET name=?, version=?, author=?, url=?, description=?
            WHERE themeID=?
        ")) {
            nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
        }

        $stmt->bind_param("sssssi", $name, $version, $author, $url, $description, $themeID);

        if (!$stmt->execute()) {
            $stmt->close();
            nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
        }

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            nx_redirect('admincenter.php?site=theme_installer', 'warning', 'alert_no_changes', false);
        }

        $stmt->close();
        nx_audit_update('theme_installer', (string)$themeID, true, $themeFolder, 'admincenter.php?site=theme_installer', ['folder' => $themeFolder, 'version' => $version]);
        nx_redirect('admincenter.php?site=theme_installer','success',sprintf($languageService->get('alert_theme_saved'), htmlspecialchars($themeFolder, ENT_QUOTES, 'UTF-8')),false,true);
    }

    // Aktuelle Werte laden
    if (!$stmt = $_database->prepare("SELECT * FROM settings_themes_installed WHERE themeID=?")) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }

    $stmt->bind_param("i", $themeID);

    if (!$stmt->execute()) {
        $stmt->close();
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }

    $themeData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$themeData) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_theme_not_found', false);
    }
}

// Upload-Funktion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['themefile']) && !$editing) {

    $themeName = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['theme_name'] ?? ''));
    $version = trim($_POST['version'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($themeName)) {
        nx_redirect('admincenter.php?site=theme_installer', 'warning', 'alert_theme_name_invalid', false);
    }

    $targetDir   = '../includes/themes/default/css/dist/';
    $extractPath = $targetDir . $themeName . '/';
    $fileName    = basename($_FILES['themefile']['name'] ?? '');
    $fileTmp     = $_FILES['themefile']['tmp_name'] ?? '';
    $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Prüfen ob DB-Eintrag existiert
    if (!$check = $_database->prepare("SELECT COUNT(*) FROM settings_themes_installed WHERE folder = ?")) {
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }

    $check->bind_param("s", $themeName);

    if (!$check->execute()) {
        $check->close();
        nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
    }

    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ((int)$count > 0) {
        nx_redirect('admincenter.php?site=theme_installer','warning',sprintf($languageService->get('alert_theme_already_installed'), htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8')),false,true);
    }

    if (!is_dir($extractPath)) {
        @mkdir($extractPath, 0755, true);
    }

    $uploadSuccess = false;

    if ($fileExt === 'zip') {
        $zip = new ZipArchive();

        if ($zip->open($fileTmp) === true) {
            if (!$zip->extractTo($extractPath)) {
                $zip->close();
                nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_theme_zip_extract_failed', false);
            }
            $zip->close();
            $uploadSuccess = true;
        } else {
            nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_theme_zip_extract_failed', false);
        }

    } elseif ($fileExt === 'css') {

        $targetFile = $extractPath . 'bootstrap.min.css';

        if (move_uploaded_file($fileTmp, $targetFile)) {
            $uploadSuccess = true;
        } else {
            nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_theme_copy_css_failed', false);
        }

    } else {
        nx_redirect('admincenter.php?site=theme_installer', 'warning', 'alert_theme_only_zipcss', false);
    }

    if ($uploadSuccess) {

        if (!$stmt = $_database->prepare("
            INSERT INTO settings_themes_installed
            (name, modulname, version, author, url, folder, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")) {
            nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
        }

        $modulname = 'Theme Manager';
        $stmt->bind_param("sssssss", $themeName, $modulname, $version, $author, $url, $themeName, $description);

        if (!$stmt->execute()) {
            $stmt->close();
            nx_redirect('admincenter.php?site=theme_installer', 'danger', 'alert_db_error', false);
        }

        if ($stmt->affected_rows === 0) {
            $stmt->close();
            nx_redirect('admincenter.php?site=theme_installer', 'warning', 'alert_no_changes', false);
        }

        $stmt->close();
        nx_audit_create('theme_installer', (string)$stmt->insert_id, $themeName, 'admincenter.php?site=theme_installer', ['folder' => $themeName, 'version' => $version]);
        nx_redirect('admincenter.php?site=theme_installer','success',sprintf($languageService->get('alert_theme_uploaded'), htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8')),false,true);
    }
}

// Formular für Upload/Bearbeiten
if($action === 'add' || $action === 'edit'):
?>
<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi <?= ($editing && $themeData) ? 'bi-pencil-square' : 'bi-upload' ?>"></i>
            <span>
                <?= ($editing && $themeData)
                    ? $languageService->get('theme_edit_label') . ': ' . htmlspecialchars($themeData['name'])
                    : $languageService->get('theme_upload_manual')
                ?>
            </span>  
        </div>
    </div>
    <div class="card-body">
            <form method="post" <?= (!$editing) ? 'enctype="multipart/form-data"' : '' ?>>
                <?php if($editing): ?>
                    <input type="hidden" name="update_theme" value="1">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= $languageService->get('name') ?>:</label>
                        <input type="text" name="<?= ($editing) ? 'name' : 'theme_name' ?>" class="form-control" 
                               value="<?= ($editing) ? htmlspecialchars($themeData['name']) : '' ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= $languageService->get('version') ?>:</label>
                        <input type="text" name="version" class="form-control" 
                               value="<?= ($editing) ? htmlspecialchars($themeData['version']) : '' ?>" 
                               placeholder="<?=$languageService->get('example_version') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Autor:</label>
                        <input type="text" name="author" class="form-control" 
                               value="<?= ($editing) ? htmlspecialchars($themeData['author']) : '' ?>" 
                               placeholder="<?=$languageService->get('example_author') ?>">
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
                              placeholder="<?=$languageService->get('example_description') ?>"><?= ($editing) ? htmlspecialchars($themeData['description']) : '' ?></textarea>
                </div>

                <?php if(!$editing): ?>
                <div class="mb-3">
                    <label for="themefile" class="form-label"><?=$languageService->get('label_zip_css') ?>:</label>
                    <input type="file" name="themefile" id="themefile" class="form-control" required>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">
                    <?= $editing
                        ? $languageService->get('save')
                        : '<i class="bi bi-cloud-arrow-up"></i> ' . $languageService->get('theme_upload_install')
                    ?>
                </button>
                <a href="admincenter.php?site=theme_installer&action=upload" class="btn btn-danger ms-2"><?= $languageService->get('cancel') ?></a>
            </form>
    </div>
</div>
<?php
else:
?>
<!-- Liste installierter Themes -->
<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-palette"></i> <span><?= $languageService->get('installed_themes') ?></span>
            <small class="text-muted"><?= $languageService->get('overview') ?></small>
        </div>
    </div>
  <div class="card-body">
    <div>
        <a href="admincenter.php?site=theme_installer&action=upload&upload_action=add" class="btn btn-secondary"><?= $languageService->get('upload_theme') ?></a>
    </div>   
        <?php
        if (!empty($flashMessage)) {
            echo $flashMessage;
        }
        ?>
        <?php if(empty($themes)): ?>
            <div class="alert alert-info"><?= $languageService->get('theme_error_no_themes') ?></div>
        <?php else: ?>
            <table class="table mt-4">
                <thead>
                    <tr>
                        <th><?= $languageService->get('name') ?></th>
                        <th><?= $languageService->get('version') ?></th>
                        <th><?= $languageService->get('author') ?></th>
                        <th><?= $languageService->get('folder') ?></th>
                        <th class="text-end"><?= $languageService->get('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $CAPCLASS = new \nexpell\Captcha;
                $CAPCLASS->createTransaction();
                $hash = $CAPCLASS->getHash();
                ?>
                <?php foreach($themes as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['name']) ?></td>
                        <td><?= htmlspecialchars($t['version']) ?></td>
                        <td><?= htmlspecialchars($t['author']) ?></td>
                        <td><?= htmlspecialchars($t['folder']) ?></td>
                        <td class="text-end">
                            <?php if (!in_array($t['folder'], $allThemes)): ?>
                                <a href="admincenter.php?site=theme_installer&action=upload&upload_action=edit&edit=<?= urlencode($t['themeID']) ?>" class="btn btn-warning">
                                    <?= $languageService->get('edit') ?>
                                </a>
                            <?php endif; ?>
                            <?php
                            $deleteUrl = 'admincenter.php?site=theme_installer&action=upload&delete=' . rawurlencode($t['folder']) . '&captcha_hash=' . rawurlencode($hash);
                            ?>
                            <button type="button"
                                    class="btn btn-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmDeleteModal"
                                    data-delete-url="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <?= $languageService->get('delete') ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
  </div>
</div>
<?php
endif;
?>