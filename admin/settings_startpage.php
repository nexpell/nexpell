<?php
use nexpell\LanguageService;
use nexpell\AccessControl;

if (session_status() === PHP_SESSION_NONE) session_start();

global $_database;
AccessControl::checkAdminAccess('ac_startpage');

$CAPCLASS = new \nexpell\Captcha;
$content_key = 'startpage'; // Der Key für diese Seite

// 1. Sprachen laden
$languages = [];
$res = mysqli_query($_database, "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $languages[$row['iso_639_1']] = $row['name_de'];
}

// 2. Aktive Sprache bestimmen
$currentLang = null;

// 2️⃣ Aktive Sprache bestimmen

if (!empty($_SESSION['startpage_active_lang'])) {
    $currentLang = $_SESSION['startpage_active_lang'];
    unset($_SESSION['startpage_active_lang']);
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


// 3. Speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_redirect('admincenter.php?site=settings_startpage','danger','alert_transaction_invalid',false);
    }

    $activeLang = $_POST['active_lang'] ?? $currentLang;

    /* ------------------------------
     * TITLE (mehrsprachig)
     * ------------------------------ */
    if (
        isset($_POST['title_lang'][$activeLang]) &&
        is_string($_POST['title_lang'][$activeLang])
    ) {

        $title_e = $_database->real_escape_string(
            trim($_POST['title_lang'][$activeLang])
        );
        $lang_e = $_database->real_escape_string($activeLang);

        $_database->query("
            INSERT INTO settings_content_lang (content_key, language, content, updated_at)
            VALUES ('startpage_title', '$lang_e', '$title_e', NOW())
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                updated_at = NOW()
        ");
    }


    /* ------------------------------
     * CONTENT (mehrsprachig)
     * ------------------------------ */
    if (
        isset($_POST['content'][$activeLang]) &&
        is_string($_POST['content'][$activeLang])
    ) {
        $html_e = $_database->real_escape_string(trim($_POST['content'][$activeLang]));
        $lang_e = $_database->real_escape_string($activeLang);

        $_database->query("
            INSERT INTO settings_content_lang (content_key, language, content, updated_at)
            VALUES ('$content_key', '$lang_e', '$html_e', NOW())
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                updated_at = NOW()
        ");
    }

    $_SESSION['startpage_active_lang'] = $activeLang;

    nx_audit_update(
        'settings_content_lang',
        null,
        true,
        null,
        'admincenter.php?site=settings_startpage'
    );

    nx_redirect(
        'admincenter.php?site=settings_startpage',
        'success',
        'alert_saved',
        false
    );
}


// 4. Daten laden

$content      = [];
$titles       = [];
$lastUpdate   = [];

$res_lang = $_database->query("
    SELECT content_key, language, content, updated_at
    FROM settings_content_lang
    WHERE content_key IN ('startpage', 'startpage_title')
");

while ($row = $res_lang->fetch_assoc()) {
    $lang = $row['language'];

    if ($row['content_key'] === 'startpage') {
        $content[$lang]    = $row['content'];
        $lastUpdate[$lang] = $row['updated_at'];
    }

    if ($row['content_key'] === 'startpage_title') {
        $titles[$lang] = $row['content'];
    }
}


$CAPCLASS->createTransaction();
$hash = $CAPCLASS->getHash();
?>
<script>
    const lastUpdateByLang = <?= json_encode($lastUpdate, JSON_UNESCAPED_UNICODE) ?>;
</script>
<form method="post" id="startpageForm" action="admincenter.php?site=settings_startpage">
    <div class="nx-lang-editor">

    <input type="hidden" name="captcha_hash" value="<?= $hash ?>">
    <input type="hidden" name="active_lang" id="active_lang" value="<?= $currentLang ?>">

    <div class="card shadow-sm border-0 mb-4 mt-3">

        <!-- CARD HEADER -->
        <div class="card-header d-flex align-items-center">
            <div class="card-title mb-0">
                <i class="bi bi-house-gear"></i> <?= $languageService->get('startpage') ?>
            </div>

            <div class="ms-auto d-flex align-items-center gap-4">
                <div class="btn-group" id="lang-switch">
                    <?php foreach ($languages as $iso => $label): ?>
                        <button type="button"
                                class="btn <?= $iso === $currentLang ? 'btn-primary' : 'btn-secondary' ?>"
                                data-lang="<?= $iso ?>">
                            <?= strtoupper($iso) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="text-end ps-3">
                    <div class="text-muted small" id="last-update-box">
                        <?php if (!empty($lastUpdate[$currentLang])): ?>
                            <i class="bi bi-clock-history me-1"></i>
                            <span id="last-update-text">
                                <?= date('d.m.Y H:i', strtotime($lastUpdate[$currentLang])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD BODY -->
        <div class="card-body">

            <div class="row mb-4">
                <div class="col-md-8">
                    <label class="form-label fw-bold"><?= $languageService->get('title_head') ?></label>

                    <input type="text"
                           id="nx-title-input"
                           class="form-control mb-2"
                           value="<?= htmlspecialchars($titles[$currentLang] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <?php foreach ($languages as $iso => $label): ?>
                        <input type="hidden"
                               name="title_lang[<?= $iso ?>]"
                               id="title_<?= $iso ?>"
                               value="<?= htmlspecialchars($titles[$iso] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                </div>
            </div>

            <textarea class="form-control"
                      id="nx-editor-main"
                      data-editor="nx_editor"
                      rows="15"><?= htmlspecialchars($content[$currentLang] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

            <?php foreach ($languages as $iso => $label): ?>
                <input type="hidden"
                       name="content[<?= $iso ?>]"
                       id="content_<?= $iso ?>"
                       value="<?= htmlspecialchars($content[$iso] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php endforeach; ?>

            <div class="mt-4">
                <button type="submit" name="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> <?= $languageService->get('save') ?>
                </button>
            </div>

        </div>
    </div>

    </div>
</form>