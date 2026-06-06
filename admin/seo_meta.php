<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\NavigationUpdater;

// 🔹 Sicherheits-Setup
AccessControl::checkAdminAccess('ac_seo_meta');

// 🔹 CMS-Sprache bleibt erhalten
$_SESSION['language'] = $_SESSION['language'] ?? 'de';
$cmsLanguage = $_SESSION['language'];

// 🔹 Initialisieren
global $_database, $languageService;
$languageService = new LanguageService($_database);
$languageService->readModule('seo_meta', true);

// 🔹 Action bestimmen
$action = $_GET['action'] ?? '';
$csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$message = '';

// 🔹 Seitenliste (Navigation + SEO)
$availableSites = [];
$resNav = $_database->query("SELECT modulname, name FROM navigation_website_main ORDER BY sort ASC");
if ($resNav) {
    while ($row = $resNav->fetch_assoc()) {
        $availableSites[$row['modulname']] = $row['name'];
    }
}
$resSeo = $_database->query("SELECT DISTINCT site FROM settings_seo_meta ORDER BY site ASC");
if ($resSeo) {
    while ($row = $resSeo->fetch_assoc()) {
        if (!isset($availableSites[$row['site']])) {
            $availableSites[$row['site']] = ucfirst($row['site']);
        }
    }
}

// 🔹 Seite bestimmen
$seo_page = $_POST['site'] ?? $_GET['page'] ?? $_GET['site'] ?? '';
if (empty($seo_page) && !empty($availableSites)) {
    $seo_page = array_key_first($availableSites);
}

// 🔹 Bearbeitungssprache bestimmen (nicht CMS!)
$editLanguage = $_POST['language'] ?? $_GET['lang'] ?? 'de';

/* -------------------------------------------------------
   🔹 SAVE (INSERT / UPDATE)
------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('<div class="alert alert-danger">Ungültiger CSRF-Token</div>');
    }

    $seo_page   = trim($_POST['site'] ?? '');
    $form_lang  = trim($_POST['language'] ?? $editLanguage);
    $title      = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($seo_page !== '' && $title !== '') {
        $stmt = $_database->prepare("
            REPLACE INTO settings_seo_meta (site, language, title, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $seo_page, $form_lang, $title, $description);
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">✅ SEO-Daten gespeichert.</div>';
        } else {
            $message = '<div class="alert alert-danger">❌ Fehler beim Speichern: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();

        NavigationUpdater::updateFromAdminFile($seo_page);
        $action = '';
    } else {
        $message = '<div class="alert alert-danger">⚠️ Bitte Titel und Seite angeben.</div>';
    }
}

/* -------------------------------------------------------
   🔹 DELETE
------------------------------------------------------- */
if (isset($_GET['del'])) {
    $delSite = $_GET['del'];
    $stmt = $_database->prepare("DELETE FROM settings_seo_meta WHERE site=? AND language=?");
    $stmt->bind_param("ss", $delSite, $editLanguage);
    $stmt->execute();
    $stmt->close();
    $message = "<div class='alert alert-danger'>🗑️ Eintrag gelöscht.</div>";
    $action = '';
}

/* -------------------------------------------------------
   🔹 Alle Einträge für Übersicht
------------------------------------------------------- */
$allSeo = [];
$resAll = $_database->prepare("
    SELECT site, title, description 
    FROM settings_seo_meta 
    WHERE language=? 
    ORDER BY site ASC
");
$resAll->bind_param("s", $editLanguage);
$resAll->execute();
$resAllRes = $resAll->get_result();
while ($row = $resAllRes->fetch_assoc()) {
    $allSeo[] = $row;
}
$resAll->close();

/* -------------------------------------------------------
   🔹 Verfügbare Sprachen mit Flaggen laden
------------------------------------------------------- */
$availableLangs = [];
$resLangs = $_database->query("
    SELECT iso_639_1 AS code, name_native, flag 
    FROM settings_languages 
    WHERE active = 1 
    ORDER BY FIELD(iso_639_1, 'de','en','it') ASC
");
if ($resLangs) {
    while ($row = $resLangs->fetch_assoc()) {
        $availableLangs[] = $row;
    }
}

/* -------------------------------------------------------
   🔹 SEO-Eintrag für aktuelle Seite + Sprache laden
------------------------------------------------------- */
$seo = ['title' => '', 'description' => ''];
if ($action === 'edit' && !empty($seo_page)) {
    $stmt = $_database->prepare("
        SELECT title, description, language 
        FROM settings_seo_meta 
        WHERE site=? AND language=?
    ");
    $stmt->bind_param("ss", $seo_page, $editLanguage);
    $stmt->execute();
    $res = $stmt->get_result();
    $seo = $res->fetch_assoc() ?: $seo;
    $stmt->close();
}
?>

<style>
.language-select {
    background-repeat: no-repeat;
    background-position: 0.5rem center;
    background-size: 20px 14px;
    padding-left: 2rem;
}
</style>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- 🔹 ADD / EDIT FORM -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-paragraph"></i> <?= $languageService->get('edit_seo_metadata') ?></div>
        <div>
            <a href="admincenter.php?site=seo_meta" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> <?= $languageService->get('back') ?>
            </a>
        </div>
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light mb-0">
            <li class="breadcrumb-item"><a href="admincenter.php?site=seo_meta"><?= $languageService->get('seo_meta') ?></a></li>
            <li class="breadcrumb-item active"><?= $action === 'edit' ? $languageService->get('edit') : $languageService->get('add') ?></li>
        </ol>
    </nav>

    <div class="card-body">
        <?= $message ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="site" value="<?= htmlspecialchars($seo_page) ?>">

            <!-- 🔹 Seitennamen -->
            <div class="mb-3">
                <label class="form-label"><?= $languageService->get('page_name') ?? 'Seite' ?></label>
                <input type="text" class="form-control" 
                       value="<?= htmlspecialchars($availableSites[$seo_page] ?? ucfirst($seo_page)) ?>" 
                       readonly>
            </div>

            <!-- 🔹 Sprache auswählen -->
            <div class="mb-3">
                <label class="form-label"><?= $languageService->get('select_language') ?></label>
                <select name="language" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($availableLangs as $lang): ?>
                        <?php
                        $selected = $lang['code'] === $editLanguage ? 'selected' : '';
                        $style = "background-image: url('" . htmlspecialchars($lang['flag']) . "');";
                        ?>
                        <option value="<?= htmlspecialchars($lang['code']) ?>" 
                                class="language-select"
                                style="<?= $style ?>"
                                <?= $selected ?>>
                            <?= strtoupper($lang['code']) ?> – <?= htmlspecialchars($lang['name_native']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="mt-2">
                    <?php foreach ($availableLangs as $lang): ?>
                        <img src="<?= htmlspecialchars($lang['flag']) ?>" 
                             alt="<?= htmlspecialchars($lang['name_native']) ?>" 
                             title="<?= htmlspecialchars($lang['name_native']) ?>" 
                             class="me-1" 
                             style="height:20px; <?= $lang['code'] === $editLanguage ? 'filter: drop-shadow(0 0 3px var(--nx-orange));' : 'opacity:0.5;' ?>">
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 🔹 Meta Title -->
            <div class="mb-3">
                <label class="form-label"><?= $languageService->get('meta_title') ?></label>
                <input type="text" name="title" class="form-control" maxlength="255"
                       value="<?= htmlspecialchars($seo['title']) ?>" required>
            </div>

            <!-- 🔹 Meta Description -->
            <div class="mb-3">
                <label class="form-label"><?= $languageService->get('meta_description') ?></label>
                <textarea name="description" class="form-control ckeditor" rows="4" required><?= htmlspecialchars($seo['description']) ?></textarea>
            </div>

            <button type="submit" name="save" value="1" class="btn btn-primary">
                <i class="bi bi-save"></i> <?= $languageService->get('save_changes') ?>
            </button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- 🔹 LISTE -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-paragraph"></i> <?= $languageService->get('seo_meta') ?></div>
        <div>
            <a href="admincenter.php?site=seo_meta&action=add" class="btn btn-success btn-sm">
                <i class="bi bi-plus"></i> <?= $languageService->get('add') ?>
            </a>
        </div>
    </div>

    <div class="card-body">
        <?= $message ?>

        <div class="alert alert-info border p-4">
            <h5 class="mb-3">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?= $languageService->get('seo_meta_title') ?>
            </h5>

            <p class="mb-3"><?= $languageService->get('seo_meta_intro') ?></p>

            <p><?= $languageService->get('seo_meta_description') ?></p>

            <h6 class="mt-3"><?= $languageService->get('seo_meta_why_title') ?></h6>
            <ul>
                <li><?= $languageService->get('seo_meta_why_1') ?></li>
                <li><?= $languageService->get('seo_meta_why_2') ?></li>
                <li><?= $languageService->get('seo_meta_why_3') ?></li>
            </ul>

            <h6 class="mt-3"><?= $languageService->get('seo_meta_examples_title') ?></h6>
            <ul>
                <li><strong><?= $languageService->get('seo_meta_example_title_label') ?>:</strong> <?= $languageService->get('seo_meta_example_title') ?></li>
                <li>
                    <strong><?= $languageService->get('seo_meta_example_desc_label') ?>:</strong><br>
                    <?= $languageService->get('seo_meta_example_description') ?>
                </li>
            </ul>

            <p class="mt-3 mb-0">
                <strong><?= $languageService->get('seo_meta_hint_label') ?>:</strong>
                <?= $languageService->get('seo_meta_hint') ?>
            </p>
        </div>

        <table class="table table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= $languageService->get('site') ?></th>
                    <th><?= $languageService->get('meta_title') ?></th>
                    <th><?= $languageService->get('meta_description') ?></th>
                    <th class="text-end"><?= $languageService->get('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allSeo as $entry): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($entry['site']) ?></code></td>
                        <td><?= htmlspecialchars($entry['title']) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($entry['description'], 0, 80, '…')) ?></td>
                        <td class="text-end">
                            <a href="admincenter.php?site=seo_meta&action=edit&page=<?= urlencode($entry['site']) ?>&lang=<?= $editLanguage ?>" 
                               class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="admincenter.php?site=seo_meta&del=<?= urlencode($entry['site']) ?>&lang=<?= $editLanguage ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Wirklich löschen?');">
                               <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($allSeo)): ?>
                    <tr><td colspan="4" class="text-center text-muted"><?= $languageService->get('no_entries') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
