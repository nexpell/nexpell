<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
use nexpell\LanguageService;

// Admin-Berechtigung prüfen
AccessControl::checkAdminAccess('ac_stylesheet');

// LanguageService laden
global $languageService;
$languageService = new LanguageService($_database);
$languageService->readModule('stylesheet', true);

// Pfad zum CSS-Ordner
$cssDir = __DIR__ . '/../includes/themes/default/css/';

// CSS-Dateien ermitteln
$cssFiles = glob($cssDir . '*.css') ?: [];

$message = '';
$messageType = '';

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

// ------------------------------------------------------------
// 🔥 SPEICHERN der CSS-Datei (nur HIER wird EIN Backup erzeugt)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $filePath) {

    $newCss = $_POST['css_content'] ?? '';

    // Zeilenumbrüche normalisieren
    $newCss = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], $newCss);

    // EIN Backup erzeugen
    $backupName = $selectedFile . '.bak_' . date('Y-m-d_H-i-s');
    copy($filePath, $cssDir . $backupName);

    // Datei speichern
    if (file_put_contents($filePath, $newCss) !== false) {
        $message = $languageService->get('file_saved_success');
        $messageType = 'success';
    } else {
        $message = $languageService->get('file_saved_error');
        $messageType = 'danger';
    }
}

// ------------------------------------------------------------
// 🔥 Dateiinhalt laden
// ------------------------------------------------------------
$cssContent = '';
if ($filePath && file_exists($filePath)) {
    $cssContent = file_get_contents($filePath);
}

// ------------------------------------------------------------
// 🔥 Backup-Liste (NUR laden, NICHT kopieren!)
// ------------------------------------------------------------
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

// ------------------------------------------------------------
// 🔥 DOWNLOAD
// ------------------------------------------------------------
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

// ------------------------------------------------------------
// 🔥 BACKUP LÖSCHEN
// ------------------------------------------------------------
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $path = $cssDir . $file;

    if (file_exists($path)) {
        unlink($path);
        $message = $languageService->get('backup_deleted');
        $messageType = 'success';
    }

    // Liste aktualisieren
    $backups = glob($cssDir . $selectedFile . '.bak_*') ?: [];
}

$relativeCssPath = '/includes/themes/default/css/';
$displayPath = $selectedFile ? $relativeCssPath . $selectedFile : '(keine Datei ausgewählt)';

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.13/lib/codemirror.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.13/theme/dracula.css" />

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-journal-text"></i> <?= $languageService->get('edit_stylesheet') ?></div>
    </div>

    <div class="card-body">
        <div class="container py-3">

            <h4 class="mb-2">
                <?= $languageService->get('edit_stylesheet') ?>
                <br>
                <small class="text-muted">
                    <i class="bi bi-folder2-open"></i>
                    <?= htmlspecialchars($displayPath) ?>
                </small>
            </h4>

            <?= $languageService->get('stylesheet_description') ?>

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

            <!-- Meldung -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Editor -->
            <form method="post">
                <input type="hidden" name="file" value="<?= htmlspecialchars($selectedFile) ?>">

<textarea id="css-editor" class="form-control" name="css_content" 
style="height:600px; font-family:monospace;"
><?= htmlspecialchars($cssContent ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

                <button type="submit" class="btn btn-primary mt-3">
                    <?= $languageService->get('save') ?>
                </button>
            </form>

            <!-- Backup-Liste -->
            <h4 class="mt-5"><?= $languageService->get('backups_title') ?></h4>

            <?php if (empty($backups)): ?>
                <div class="alert alert-info"><?= $languageService->get('no_backups_found') ?></div>

            <?php else: ?>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?= $languageService->get('backup_file') ?></th>
                            <th><?= $languageService->get('backup_date') ?></th>
                            <th><?= $languageService->get('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $b): ?>
                            <?php $bn = basename($b); ?>
                            <tr>
                                <td><?= htmlspecialchars($bn) ?></td>
                                <td><?= date('d.m.Y H:i:s', filemtime($b)) ?></td>
                                <td>
                                    <a class="btn btn-success btn-sm"
                                       href="?site=edit_stylesheet&file=<?= urlencode($selectedFile) ?>&download=<?= urlencode($bn) ?>">
                                        <i class="bi bi-download"></i> <?= $languageService->get('download') ?>
                                    </a>

                                    <a class="btn btn-danger btn-sm"
                                       onclick="return confirm('Backup wirklich löschen?')"
                                       href="?site=edit_stylesheet&file=<?= urlencode($selectedFile) ?>&delete=<?= urlencode($bn) ?>">
                                        <i class="bi bi-trash"></i> <?= $languageService->get('delete') ?>
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
