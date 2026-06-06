<?php

use nexpell\LanguageService;
use nexpell\AccessControl;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache setzen
$_SESSION['language'] = $_POST['language'] ?? ($_SESSION['language'] ?? 'de');
$language = $_SESSION['language'];

global $languageService;
$languageService = new LanguageService($_database);
$languageService->readModule('startpage', true);

// Admin-Zugriff prüfen
AccessControl::checkAdminAccess('ac_startpage');

$CAPCLASS = new \nexpell\Captcha;
$tpl = new Template();

// Formularverarbeitung
if (isset($_POST['submit'])) {
    $title = $_POST['title'] ?? '';
    $editor = isset($_POST['editor']) ? '1' : '0';
    $nameArray = $_POST['name'] ?? [];

    // Mehrsprachigen Text zusammenbauen
    $startpage_text = '';
    foreach (['de', 'en', 'it'] as $lang) {
        $text = $nameArray[$lang] ?? '';
        $startpage_text .= "[[lang:$lang]]" . $text;
    }

    // CAPTCHA prüfen
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (mysqli_num_rows(safe_query("SELECT * FROM settings_startpage"))) {
            safe_query("UPDATE settings_startpage SET date=CURRENT_TIMESTAMP, title='" . escape($title) . "', startpage_text='" . $startpage_text . "', editor='" . $editor . "'");
        } else {
            safe_query("INSERT INTO settings_startpage (date, title, startpage_text, editor) VALUES (NOW(), '" . escape($title) . "', '" . $startpage_text . "', '" . $editor . "')");
        }

        echo '<div class="alert alert-success">' . $languageService->module['changes_successful'] . '</div>';
        echo '<script>setTimeout(() => window.location.href = "admincenter.php?site=settings_startpage", 3000);</script>';
    } else {
        echo '<div class="alert alert-danger">' . $languageService->module['transaction_invalid'] . '</div>';
        echo '<script>setTimeout(() => window.location.href = "admincenter.php?site=settings_startpage", 3000);</script>';
    }
}

// Daten laden
$ds = mysqli_fetch_array(safe_query("SELECT * FROM settings_startpage"));

// Mehrsprachigen Text extrahieren
function extractLangText(?string $multiLangText, string $lang): string {
    if (!$multiLangText) return '';
    if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s', $multiLangText, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

// Sprach-Array
$languages = [];

$query = "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC";
$result = mysqli_query($_database, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // $row['iso_639_1'] z.B. 'de', $row['name_de'] z.B. 'Deutsch'
        $languages[$row['iso_639_1']] = $row['name_de'];
    }
} else {
    // Fallback falls Query nicht klappt
    $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
}

// Editor-Status
$editor_checked = ($ds['editor'] ?? 0) == 1 ? 'checked' : '';

// CAPTCHA vorbereiten
$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();
?>

<div class="card">
    <div class="card-header"><?= $languageService->module['startpage'] ?></div>
    <nav class="breadcrumb bg-light px-3 py-2">
        <a class="breadcrumb-item" href="admincenter.php?site=settings_startpage"><?= $languageService->module['startpage'] ?></a>
        <span class="breadcrumb-item active">Edit</span>
    </nav>
    <div class="card-body">
        <form class="form-horizontal" method="post" id="post" name="post">
            <div class="mb-3 row">
                <label class="col-sm-2 col-form-label"><?= $languageService->module['title_head'] ?></label>
                <div class="col-sm-10">
                    <input class="form-control" type="text" name="title" value="<?= htmlspecialchars($ds['title'] ?? '') ?>" />
                </div>
            </div>

            <div class="mb-3 row">
                <label class="col-sm-2 col-form-label"><?= $languageService->module['editor_is_editor'] ?></label>
                <div class="col-sm-10">
                    <input class="form-check-input" type="checkbox" id="toggle-editor" name="editor" value="1" <?= $editor_checked ?>>
                </div>
            </div>
            <div class="alert alert-info" role="alert">
                 <label for="text" class="form-label"><h4><?= $languageService->module['text'] ?></h4></label>
            <?php foreach ($languages as $code => $label): ?>
                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label"><?= $label ?>:</label>
                    <div class="col-sm-10">
                        <textarea class="form-control lang-field" rows="6" id="editor_<?= $code ?>" name="name[<?= $code ?>]"><?= htmlspecialchars(extractLangText($ds['startpage_text'] ?? '', $code)) ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <input type="hidden" name="captcha_hash" value="<?= $hash ?>" />
            <button class="btn btn-warning" type="submit" name="submit"><?= $languageService->module['update'] ?></button>
        </form>
    </div>
</div>

<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('toggle-editor');
    const editors = document.querySelectorAll('.lang-field');

    function toggleEditors() {
        editors.forEach(textarea => {
            const id = textarea.id;
            if (toggle.checked) {
                if (!CKEDITOR.instances[id]) {
                    CKEDITOR.replace(id);
                }
            } else {
                if (CKEDITOR.instances[id]) {
                    CKEDITOR.instances[id].destroy(true);
                }
            }
        });
    }

    toggle.addEventListener('change', toggleEditors);
    toggleEditors(); // Initialer Zustand
});
</script>
