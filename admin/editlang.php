<?php
use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_editlang');

$baseDir = dirname(__DIR__);
$languageDir = $baseDir . '/languages/';
$pluginLanguageDir = $baseDir . '/includes/plugins/';

function getLanguageFiles($dir) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];
    foreach ($rii as $file) {
        if (!$file->isDir() && strpos($file->getPathname(), 'languages') !== false) {
            $relativePath = str_replace($dir, '', $file->getPathname());
            $directory = dirname($relativePath);
            $files[$directory][] = $relativePath;
        }
    }

    ksort($files);
    foreach ($files as $directory => &$fileList) {
        sort($fileList);
    }

    return $files;
}

function readFileContent($file) {
    if (file_exists($file)) {
        return file_get_contents($file);
    }
    return '';
}

function saveFileContent($file, $content) {
    $content = stripcslashes($content);
    return file_put_contents($file, $content);
}

$action = isset($_POST['action']) ? $_POST['action'] : null;
$file = isset($_GET['file']) ? urldecode($_GET['file']) : null;
$listboxType = isset($_GET['listbox']) ? $_GET['listbox'] : 'main';

if ($action === 'save' && $file) {
    $content = (string)($_POST['content'] ?? '');

    if (saveFileContent($file, $content)) 
        nx_audit_action('editlang', 'audit_action_language_file_saved', basename((string)$file), null, 'admincenter.php?site=editlang', ['file' => basename((string)$file)]);
        nx_redirect('admincenter.php?site=editlang', 'success', 'alert_saved', false
    );
    nx_redirect('admincenter.php?site=editlang', 'danger', 'alert_save_failed', false);
}

$files = getLanguageFiles($languageDir);
$pluginFiles = getLanguageFiles($pluginLanguageDir);
$selectedFile = $file ? urldecode($file) : null;
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
    <style>
        .container { margin-left: 0!important; margin-right: 0!important; }        
        .form-select { max-width: 300px; }
        .CodeMirror { height: auto; border: 1px solid #000;font-size: 13px; }
    </style>
    <script>
        function loadFiles() {
            var listboxType = document.getElementById('listbox-type').value;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'admin/editlang.php?site=editlang&listbox=' + listboxType, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById('file-list-container').innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        }

        function changeFontSize() {
            var fontSize = document.getElementById('font-size').value;
            document.querySelector('.CodeMirror').style.fontSize = fontSize + 'px';
            editor.refresh();
        }

        var editor;
        document.addEventListener('DOMContentLoaded', function() {
            var textarea = document.getElementById('file-content');
            editor = CodeMirror.fromTextArea(textarea, {
                lineNumbers: true,
                mode: "javascript",
                matchBrackets: true,
                autoCloseBrackets: true
            });

            document.getElementById('font-size').addEventListener('change', changeFontSize);
        });
    </script>
  <div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
      <div class="card-title">
        <i class="bi bi-translate"></i> <?php echo $languageService->get('title'); ?>
      </div>
    </div>

    <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <form class="card-body form-group" method="get" action="admincenter.php">
                        <div class="form-group">
                            <input type="hidden" name="site" value="editlang">
                            <label class="fw-bold" for="listbox-type"><i class="bi bi-translate"></i> <?php echo $languageService->get('type_lang'); ?></label>
                            <select name="listbox" id="listbox-type" class="form-select" onchange="this.form.submit()">
                                <option value="main" <?= ($listboxType === 'main') ? 'selected' : '' ?>><?php echo $languageService->get('system_lang'); ?></option>
                                <option value="plugin" <?= ($listboxType === 'plugin') ? 'selected' : '' ?>><?php echo $languageService->get('plugins_lang'); ?></option>
                            </select>
                        </div>
                    </form>

                </div>
                <div class="col-md-2">
            <div id="file-list-container" class="file-list">
                <?php if ($listboxType === 'main'): ?>
                    <form class="card-body form-group" method="get" action="admincenter.php">
                        <div class="form-group">
                            <input type="hidden" name="site" value="editlang">
                            <input type="hidden" name="listbox" value="main">
                            <label class="fw-bold" for="language-files"><i class="bi bi-card-text"></i> <?php echo $languageService->get('system_lang_file'); ?></label>
                            <select class="form-select" name="file" id="language-files" onchange="this.form.submit()">
                                <option value=""><?php echo $languageService->get('select_file'); ?></option>
                                <?php foreach ($files as $directory => $fileList): ?>
                                    <optgroup style="text-transform: uppercase;" label="<?= htmlspecialchars($directory) ?>">
                                        <?php foreach ($fileList as $fileItem): ?>
                                            <?php $filePath = $languageDir . $fileItem; ?>
                                            <option style="text-transform: lowercase;" value="<?= htmlspecialchars($filePath) ?>" <?= ($selectedFile === $filePath) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(basename($fileItem)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php elseif ($listboxType === 'plugin'): ?>
                    <form class="card-body form-group" method="get" action="admincenter.php">
                        <div class="form-group">
                            <input type="hidden" name="site" value="editlang">
                            <input type="hidden" name="listbox" value="plugin">
                            <label class="fw-bold" for="plugin-language-files"><i class="bi bi-plugin"></i> <?php echo $languageService->get('plugins_lang_file'); ?></label>
                            <select class="form-select" name="file" id="plugin-language-files" onchange="this.form.submit()">
                                <option value=""><?php echo $languageService->get('select_file'); ?></option>
                                <?php foreach ($pluginFiles as $directory => $fileList): ?>
                                    <optgroup style="text-transform: uppercase;" label="<?= htmlspecialchars($directory) ?>">
                                        <?php foreach ($fileList as $fileItem): ?>
                                            <?php $filePath = $pluginLanguageDir . $fileItem; ?>
                                            <option style="text-transform: lowercase;" value="<?= htmlspecialchars($filePath) ?>" <?= ($selectedFile === $filePath) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(basename($fileItem)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

                </div>
            </div>

            <?php if ($selectedFile && file_exists($selectedFile)): ?>
                <div class="file-edit bg-light border rounded" style="margin: 17px; padding: 1rem;">
                    <p class="fw-bold"><i class="bi bi-pencil"></i> <?php echo $languageService->get('edit_file'); ?> <?= htmlspecialchars(str_replace([$languageDir, $pluginLanguageDir], '', $selectedFile)) ?>
                    <?php if (strpos($selectedFile, '/it/') !== false): ?>
                    <img src="/admin/images/flags/it.svg" style="width: 21px;" title="<?php echo $languageService->get('it_lang'); ?>">
                    <?php endif; ?>
                    <?php if (strpos($selectedFile, '/de/') !== false): ?>
                    <img src="/admin/images/flags/de.svg" style="width: 21px;" title="<?php echo $languageService->get('de_lang'); ?>">
                    <?php endif; ?>
                    <?php if (strpos($selectedFile, '/en/') !== false): ?>
                    <img src="/admin/images/flags/gb.svg" style="width: 21px;" title="<?php echo $languageService->get('en_lang'); ?>">
                    <?php endif; ?></p>

                    <form action="admincenter.php?site=editlang&file=<?= urlencode($selectedFile) ?>&listbox=<?= htmlspecialchars($listboxType) ?>" method="post">
                        <div class="form-group">
                            <label class="form-label" for="font-size"><i class="bi bi-fonts"></i> <?php echo $languageService->get('font_size'); ?></label>
                            <select style="width: 68px;" class="form-select" id="font-size" onchange="changeFontSize()">
                                <option value="13">13</option>
                                <option value="15">15</option>
                                <option value="17">17</option>
                                <option value="19">19</option>
                                <option value="21">21</option>
                                <option value="23">23</option>
                            </select><br>
                            <textarea id="file-content" name="content" class="form-control" rows="20"><?= htmlspecialchars(readFileContent($selectedFile)) ?></textarea><br>
                            <button class="btn btn-primary" type="submit" name="action" value="save"><?php echo $languageService->get('save'); ?></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
</div>
