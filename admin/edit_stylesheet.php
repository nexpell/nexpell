<?php
use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

// Admin-Berechtigung prüfen
AccessControl::checkAdminAccess('ac_stylesheet');

// Pfad zum CSS-Ordner
$cssDir = __DIR__ . '/../includes/themes/default/css/';

// CSS-Dateien ermitteln
$cssFiles = glob($cssDir . '*.css') ?: [];


// Datei auswählen
$selectedFile = $_GET['file'] ?? ($_POST['file'] ?? null);

// Wenn keine Datei gewählt wurde → erste nehmen
if (!$selectedFile && !empty($cssFiles)) {
    $selectedFile = basename($cssFiles[0]);
}

// Sicherheitsprüfung
if ($selectedFile && !in_array($cssDir . $selectedFile, $cssFiles)) {
    $selectedFile = null;
}

$filePath = $selectedFile ? $cssDir . $selectedFile : null;

// SPEICHERN der CSS-Datei (nur HIER wird EIN Backup erzeugt)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $filePath) {

    $newCss = $_POST['css_content'] ?? '';
    $newCss = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], $newCss);

    $backupName = $selectedFile . '.bak_' . date('Y-m-d_H-i-s');
    copy($filePath, $cssDir . $backupName);

    $returnUrl = 'admincenter.php?site=edit_stylesheet' . ($selectedFile ? '&file=' . urlencode($selectedFile) : '');

    if (file_put_contents($filePath, $newCss) !== false) {
        nx_audit_action('edit_stylesheet', 'audit_action_stylesheet_saved', $selectedFile, null, $returnUrl, ['file' => $selectedFile, 'backup' => $backupName]);
        nx_redirect($returnUrl, 'success', 'alert_saved', false);
    }
    nx_redirect($returnUrl, 'danger', 'alert_save_failed', false);
}

// Dateiinhalt laden
$cssContent = '';
if ($filePath && file_exists($filePath)) {
    $cssContent = file_get_contents($filePath);
}

// Backup-Liste (NUR laden, NICHT kopieren!)
$backups = glob($cssDir . $selectedFile . '.bak_*') ?: [];

// Alte Backups löschen (30 Tage)
$maxAgeDays = 30;
foreach ($backups as $b) {
    if (filemtime($b) < time() - ($maxAgeDays * 86400)) {
        unlink($b);
    }
}

// Liste neu laden & sortieren
$backups = glob($cssDir . $selectedFile . '.bak_*') ?: [];
usort($backups, fn($a, $b) => filemtime($b) - filemtime($a));

// DOWNLOAD
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = $cssDir . $file;

    if (file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$file.'"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// BACKUP LÖSCHEN
if (isset($_GET['delete'])) {
    $file = basename((string)($_GET['delete'] ?? ''));
    $path = $cssDir . $file;

    $returnUrl = 'admincenter.php?site=edit_stylesheet' . ($selectedFile ? '&file=' . urlencode($selectedFile) : '');

    if (file_exists($path)) {
        unlink($path);
        nx_audit_action('edit_stylesheet', 'audit_action_stylesheet_backup_deleted', $file, null, $returnUrl, ['file' => $file]);
        nx_redirect($returnUrl, 'success', 'alert_deleted', false);
    }

    nx_redirect($returnUrl, 'warning', 'alert_file_not_found', false);
}

$relativeCssPath = '/includes/themes/default/css/';
$displayPath = $selectedFile ? $relativeCssPath . $selectedFile : $languageService->get('no_file_selected');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.13/lib/codemirror.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.13/theme/dracula.css" />
<style>
  .CodeMirror {
      height: 55vh;
  }
</style>

<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-code-slash"></i> <span><?= $languageService->get('edit_stylesheet') ?></span>
            <small class="text-muted"><?= htmlspecialchars($displayPath) ?></small>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info">
            <div>
                <?= $languageService->get('stylesheet_description') ?>
            </div>
        </div>
            <div class="row g-4">
                <div class="col-12 col-lg-8">
                <!-- Datei-Auswahl -->
                <form method="get" class="mb-3">
                    <input type="hidden" name="site" value="edit_stylesheet">

                    <label class="form-label fw-bold"><?= $languageService->get('choose_file') ?></label>
                    <select name="file" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($cssFiles as $file): ?>
                            <?php $fname = basename($file); ?>
                            <option value="<?= $fname ?>" <?= ($selectedFile === $fname ? 'selected' : '') ?>>
                                <?= $fname ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                    <form method="post">
                        <input type="hidden" name="file" value="<?= htmlspecialchars($selectedFile) ?>">

                        <textarea id="css-editor" class="form-control" name="css_content" style="font-family:monospace;">
                            <?= htmlspecialchars($cssContent ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </textarea>

                        <button type="submit" class="btn btn-primary mt-3">
                            <?= $languageService->get('save') ?>
                        </button>
                    </form>
                </div>

                <!-- Backup-Liste -->
                <div class="col-12 col-lg-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="mb-2"><?= $languageService->get('backups_title') ?></h5>
                    </div>

                    <?php if (empty($backups)): ?>
                        <div class="alert alert-info mb-0"><?= $languageService->get('no_backups_found') ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= $languageService->get('backup_file') ?></th>
                                        <th class="text-nowrap"><?= $languageService->get('backup_date') ?></th>
                                        <th class="text-nowrap"><?= $languageService->get('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $b): ?>
                                        <?php $bn = basename($b); ?>
                                        <tr>
                                            <td class="text-break"><?= htmlspecialchars($bn) ?></td>
                                            <td class="text-nowrap"><?= date('d.m.Y H:i:s', filemtime($b)) ?></td>
                                            <td class="text-nowrap">
                                                <a class="btn btn-primary d-inline-flex align-items-center gap-1 flex-row w-auto"
                                                   href="?site=edit_stylesheet&file=<?= urlencode($selectedFile) ?>&download=<?= urlencode($bn) ?>">
                                                    <i class="bi bi-download"></i> <?= $languageService->get('download') ?>
                                                </a>

                                                <button type="button"
                                                        class="btn btn-danger d-inline-flex align-items-center gap-1 flex-row w-auto ms-2"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#confirmDeleteModal"
                                                        data-delete-url="<?= htmlspecialchars('?site=edit_stylesheet&file=' . urlencode($selectedFile) . '&delete=' . urlencode($bn), ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.13/lib/codemirror.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.13/mode/css/css.js"></script>

<script>
var editor = CodeMirror.fromTextArea(document.getElementById('css-editor'), {
    mode: 'css',
    theme: 'dracula',
    lineNumbers: true,
    lineWrapping: true,
    matchBrackets: true,
    styleActiveLine: true
});
</script>