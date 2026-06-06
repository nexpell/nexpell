<?php

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\Captcha;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['language'] = $_SESSION['language'] ?? 'de';

global $languageService;
$languageService = new LanguageService($_database);
$languageService->readModule('privacy_policy', true);

// Zugriff prüfen
AccessControl::checkAdminAccess('ac_privacy_policy');

$CAPCLASS = new Captcha;
$tpl = new Template();

// Sprachen
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

if (isset($_POST['submit'])) {
    $current_datetime = date("Y-m-d H:i:s");
    #$nameArray = $_POST['privacy_policy_text'] ?? [];
    $editor = isset($_POST['editor']) ? '1' : '0';

    // Mehrsprachigen privacy_policy_text zusammensetzen
        $languages = ['de', 'en', 'it']; // Definiere deine Sprachen hier, passend zum Frontend
        $privacy_policy_text = '';
        if (isset($_POST['privacy_policy_text']) && is_array($_POST['privacy_policy_text'])) {
            foreach ($languages as $lang) {
                $text = trim($_POST['privacy_policy_text'][$lang] ?? '');
                if ($text !== '') {
                    $privacy_policy_text .= "[[lang:$lang]]" . $text . "\n";
                }
            }
        }

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (mysqli_num_rows(safe_query("SELECT * FROM settings_privacy_policy"))) {
            safe_query("UPDATE settings_privacy_policy SET date='" . $current_datetime . "', privacy_policy_text='" . $privacy_policy_text . "', editor='" . $editor . "'");
        } else {
            safe_query("INSERT INTO settings_privacy_policy (date, privacy_policy_text, editor) VALUES (NOW(), '" . $privacy_policy_text . "', '" . $editor . "')");
        }
        echo '<div class="alert alert-success">' . $languageService->module['changes_successful'] . '</div>';
        echo '<script>setTimeout(() => window.location.href="admincenter.php?site=settings_privacy_policy", 3000);</script>';
    } else {
        echo '<div class="alert alert-danger">' . $languageService->module['transaction_invalid'] . '</div>';
        echo '<script>setTimeout(() => window.location.href="admincenter.php?site=settings_privacy_policy", 3000);</script>';
    }
}

// Datenbank holen
$ds = mysqli_fetch_array(safe_query("SELECT * FROM settings_privacy_policy"));

// Lang extrahieren
function extractLangText(?string $multiLangText, string $lang): string {
    if (!$multiLangText) return '';
    if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s', $multiLangText, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

$editor_checked = ($ds['editor'] ?? 0) == 1 ? 'checked' : '';

$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();
?>


<div class="card">
    <div class="card-header">
        <?= $languageService->module['privacy_policy'] ?>
    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item">
                <a href="admincenter.php?site=settings_privacy_policy"><?= $languageService->module['privacy_policy'] ?></a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav>

    <div class="card-body">
        <div class="container py-5">
            <h3 class="mb-4"><?= $languageService->module['privacy_policy'] ?></h3>
            <form method="post" action="admincenter.php?site=settings_privacy_policy">
                <div class="mb-3 row">
                    <label class="col-sm-2 col-form-label"><?= $languageService->module['editor_is_editor'] ?></label>
                    <div class="col-sm-10">
                        <input class="form-check-input" type="checkbox" id="toggle-editor" name="editor" value="1" <?= $editor_checked ?>>
                    </div>
                </div>

                <div class="alert alert-info">
                    <h4><?= $languageService->module['text'] ?></h4>
                    <?php foreach ($languages as $code => $label): ?>
                        <div class="mb-3">
                            <label for="editor_<?= $code ?>" class="form-label"><?= $label ?></label>
                            <textarea class="form-control lang-field" rows="6" id="editor_<?= $code ?>" name="privacy_policy_text[<?= $code ?>]"><?= htmlspecialchars(extractLangText($ds['privacy_policy_text'] ?? '', $code)) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="captcha_hash" value="<?= $hash ?>" />
                <button type="submit" name="submit" class="btn btn-warning"><?= $languageService->module['update'] ?></button>
            </form>
        </div>
    </div>
</div>

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
