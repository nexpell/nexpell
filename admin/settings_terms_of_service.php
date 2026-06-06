<?php
use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\Captcha;

if (session_status() === PHP_SESSION_NONE) session_start();

global $_database;

// ACCESS
AccessControl::checkAdminAccess('ac_terms_of_service');

// CORE
$CAPCLASS = new Captcha;
$content_key = 'terms_of_service'; 

// 1. Sprachen laden
$languages = [];
$res = mysqli_query($_database, "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $languages[$row['iso_639_1']] = $row['name_de'];
}

// 2. Aktive Sprache bestimmen
$currentLang = null;

// 2️⃣ Aktive Sprache bestimmen

if (!empty($_SESSION['terms_of_service_active_lang'])) {
    $currentLang = $_SESSION['terms_of_service_active_lang'];
    unset($_SESSION['terms_of_service_active_lang']);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['active_lang'])) {
    $currentLang = $_POST['active_lang'];
}
elseif (!empty($_SESSION['language'])) {
    $currentLang = $_SESSION['language'];
}
else {
    $currentLang = $languageService->detectLanguage();
}

// 4️⃣ Sicherheit: nur erlaubte Sprachen
if (!isset($languages[$currentLang])) {
    $currentLang = array_key_first($languages); // meist 'de'
}

// 2. Speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {

        $activeLang = $_POST['active_lang'] ?? $currentLang;

        if (
            isset($_POST['content'][$activeLang]) &&
            is_string($_POST['content'][$activeLang])
        ) {
            $lang_e = $_database->real_escape_string($activeLang);
            $html_e = $_database->real_escape_string(trim($_POST['content'][$activeLang]));

            $_database->query("
                INSERT INTO settings_content_lang
                (content_key, language, content, updated_at)
                VALUES
                ('$content_key', '$lang_e', '$html_e', NOW())
                ON DUPLICATE KEY UPDATE
                    content = VALUES(content),
                    updated_at = NOW()
            ");
        }

        // aktive Sprache merken
        $_SESSION['terms_of_service_active_lang'] = $activeLang;

        nx_audit_update(
            'settings_content_lang',
            null,
            true,
            null,
            'admincenter.php?site=settings_terms_of_service'
        );

        nx_redirect(
            'admincenter.php?site=settings_terms_of_service',
            'success',
            'alert_saved',
            false
        );

    } else {
        nx_redirect(
            'admincenter.php?site=settings_terms_of_service',
            'danger',
            'alert_transaction_invalid',
            false
        );
    }
}


// 3. Daten aus der DB laden
$content    = [];
$lastUpdate = [];

$res = $_database->query("
    SELECT language, content, updated_at
    FROM settings_content_lang
    WHERE content_key = 'terms_of_service';
");

while ($row = $res->fetch_assoc()) {
    $lang = $row['language'];
    $content[$lang]    = $row['content'];
    $lastUpdate[$lang] = $row['updated_at'];
}

$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();
?>
<script>
    const lastUpdateByLang = <?= json_encode($lastUpdate, JSON_UNESCAPED_UNICODE) ?>;
</script>
<form method="post" id="terms_of_serviceForm">
<div class="nx-lang-editor"> <!-- 🔥 WICHTIGER CONTAINER -->

    <input type="hidden" name="captcha_hash" value="<?= $hash ?>">
    <input type="hidden" name="active_lang" id="active_lang" value="<?= $currentLang ?>">

    <div class="card shadow-sm border-0 mb-4 mt-3">

        <!-- HEADER -->
        <div class="card-header d-flex justify-content-between align-items-center">

            <div class="card-title mb-0">
                <i class="bi bi-shield-check"></i>
                <?= $languageService->get('terms_of_service') ?>
            </div>

            <div class="d-flex align-items-center gap-3 ms-auto">

                <!-- LANGUAGE SWITCH -->
                <div class="btn-group" id="lang-switch">
                    <?php foreach ($languages as $iso => $label): ?>
                        <button type="button"
                                class="btn <?= $iso === $currentLang ? 'btn-primary' : 'btn-secondary' ?>"
                                data-lang="<?= $iso ?>">
                            <?= strtoupper($iso) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <!-- LAST UPDATE -->
                <div class="text-end ps-3">
                    <div class="text-muted small" id="last-update-box">
                        <?php if (!empty($lastUpdate[$currentLang])): ?>
                            <i class="bi bi-clock-history me-1"></i>
                            <span id="last-update-text">
                                <?= date('d.m.Y H:i', strtotime($lastUpdate[$iso])) ?>
                            </span>
                        <?php else: ?>
                            <span id="last-update-text">–</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- BODY -->
        <div class="card-body">

            <!-- EDITOR -->
            <textarea
                id="nx-editor-main"
                class="form-control"
                data-editor="nx_editor"
                rows="20"><?= htmlspecialchars($content[$currentLang] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

            <!-- HIDDEN CONTENT FIELDS -->
            <?php foreach ($languages as $iso => $label): ?>
                <input type="hidden"
                       name="content[<?= $iso ?>]"
                       id="content_<?= $iso ?>"
                       value="<?= htmlspecialchars($content[$iso] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php endforeach; ?>

            <div class="mt-3">
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= $languageService->get('save') ?>
                </button>
            </div>

        </div>
    </div>

</div> 
</form>

