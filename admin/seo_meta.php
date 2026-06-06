<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\AccessControl;
use nexpell\NavigationUpdater;

AccessControl::checkAdminAccess('ac_seo_meta');

$action = $_GET['action'] ?? '';
$seo_page = $_GET['page'] ?? $_POST['site'] ?? '';
$csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$CAPCLASS = new \nexpell\Captcha;
$content_key = 'seo_meta';

$_database->query("
    CREATE TABLE IF NOT EXISTS settings_seo_meta_lang (
        id INT(11) NOT NULL AUTO_INCREMENT,
        content_key VARCHAR(191) NOT NULL,
        language VARCHAR(8) NOT NULL DEFAULT 'de',
        content MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_content_lang (content_key, language),
        KEY idx_content_key (content_key),
        KEY idx_language (language)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
");

// Fallback migration when DB migration was skipped: move old SEO rows into *_lang table.
$legacySeoExists = false;
$legacySeoCols = ['site' => false, 'language' => false, 'title' => false, 'description' => false];
$legacyCheck = mysqli_query($_database, "SHOW TABLES LIKE 'settings_seo_meta'");
if ($legacyCheck && mysqli_num_rows($legacyCheck) > 0) {
    $legacySeoExists = true;
    foreach (array_keys($legacySeoCols) as $col) {
        $colRes = mysqli_query($_database, "SHOW COLUMNS FROM settings_seo_meta LIKE '" . $col . "'");
        $legacySeoCols[$col] = ($colRes && mysqli_num_rows($colRes) > 0);
    }
}

if (
    $legacySeoExists
    && $legacySeoCols['site']
    && $legacySeoCols['language']
    && $legacySeoCols['title']
    && $legacySeoCols['description']
) {
    $migratedRows = 0;
    $legacyRes = mysqli_query($_database, "SELECT site, language, title, description FROM settings_seo_meta");
    if ($legacyRes) {
        $stmtM = $_database->prepare("
            INSERT INTO settings_seo_meta_lang (content_key, language, content)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE content = VALUES(content)
        ");
        if ($stmtM) {
            while ($r = mysqli_fetch_assoc($legacyRes)) {
                $site = trim((string)($r['site'] ?? ''));
                $lang = strtolower(trim((string)($r['language'] ?? 'de')));
                if ($site === '') {
                    continue;
                }
                if ($lang === '' || strlen($lang) > 8) {
                    $lang = 'de';
                }

                $title = trim((string)($r['title'] ?? ''));
                if ($title !== '') {
                    $key = 'seo_title_' . $site;
                    $stmtM->bind_param("sss", $key, $lang, $title);
                    $stmtM->execute();
                    $migratedRows++;
                }

                $desc = trim((string)($r['description'] ?? ''));
                if ($desc !== '') {
                    $key = 'seo_description_' . $site;
                    $stmtM->bind_param("sss", $key, $lang, $desc);
                    $stmtM->execute();
                    $migratedRows++;
                }
            }
            $stmtM->close();
        }
    }

    // Remove legacy table once data exists in new table.
    $langCount = 0;
    $countRes = mysqli_query($_database, "SELECT COUNT(*) AS cnt FROM settings_seo_meta_lang");
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $langCount = (int)($countRow['cnt'] ?? 0);
    }
    if ($migratedRows > 0 || $langCount > 0) {
        @mysqli_query($_database, "DROP TABLE settings_seo_meta");
    }
}


// 1. Sprachen laden
$languages = [];
$res = mysqli_query($_database, "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $languages[$row['iso_639_1']] = $row['name_de'];
}

// 2. Aktive Sprache bestimmen
$currentLang = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['active_lang'])) {
    $currentLang = $_POST['active_lang'];
} elseif (!empty($_SESSION['language'])) {
    $currentLang = $_SESSION['language'];
} else {
    $currentLang = $languageService->detectLanguage();
}

if (!isset($languages[$currentLang])) {
    $currentLang = array_key_first($languages);
}

// ================================
// SEO DATA INITIALISIEREN (PATCH)
// ================================
$seo_data = [];
foreach ($languages as $iso => $label) {
    $seo_data[$iso] = ['title' => '', 'description' => ''];
}

// ================================
// Übersicht laden (PATCH)
// ================================
$allSeo = [];

$stmt = $_database->prepare("
    SELECT content_key, content
    FROM settings_seo_meta_lang
    WHERE language = ?
    AND content_key LIKE 'seo_title_%'
    ORDER BY content_key ASC
");

$stmt->bind_param("s", $currentLang);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {

    $site = str_replace('seo_title_', '', $row['content_key']);

    $descStmt = $_database->prepare("
        SELECT content
        FROM settings_seo_meta_lang
        WHERE language = ?
        AND content_key = ?
    ");

    $descKey = 'seo_description_' . $site;

    $descStmt->bind_param("ss", $currentLang, $descKey);
    $descStmt->execute();
    $descRes = $descStmt->get_result();
    $descRow = $descRes->fetch_assoc();

    $allSeo[] = [
        'site' => $site,
        'title' => $row['content'],
        'description' => $descRow['content'] ?? ''
    ];

    $descStmt->close();
}

$stmt->close();

// ================================
// SAVE
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        nx_redirect('admincenter.php?site=seo_meta', 'danger', 'alert_invalid_csrf', false);
    }

    $activeLang = $_POST['active_lang'] ?? $currentLang;

    $titleKey = 'seo_title_' . $seo_page;
    $descKey  = 'seo_description_' . $seo_page;

    $stmt = $_database->prepare("
        INSERT INTO settings_seo_meta_lang (content_key, language, content)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE content = VALUES(content)
    ");

    $title = $_POST['storage_title_' . $activeLang] ?? '';
    $stmt->bind_param("sss", $titleKey, $activeLang, $title);
    $stmt->execute();

    $desc = $_POST['storage_desc_' . $activeLang] ?? '';
    $stmt->bind_param("sss", $descKey, $activeLang, $desc);
    $stmt->execute();

    $stmt->close();

    nx_redirect('admincenter.php?site=seo_meta', 'success', 'alert_saved', false);
}

// ================================
// EDIT LADEN
// ================================
if ($action === 'edit' && !empty($seo_page)) {

    $titleKey = 'seo_title_' . $seo_page;
    $descKey  = 'seo_description_' . $seo_page;

    $stmt = $_database->prepare("
        SELECT language, content_key, content
        FROM settings_seo_meta_lang
        WHERE content_key IN (?, ?)
    ");

    $stmt->bind_param("ss", $titleKey, $descKey);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $l = $row['language'];
        if (isset($seo_data[$l])) {
            if ($row['content_key'] === $titleKey) {
                $seo_data[$l]['title'] = $row['content'];
            }
            if ($row['content_key'] === $descKey) {
                $seo_data[$l]['description'] = $row['content'];
            }
        }
    }

    $stmt->close();
}
?>


<?php if ($action === 'add' || $action === 'edit'): ?>

<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header">
        <i class="bi bi-search-heart"></i>
        <?= $languageService->get('seo_meta') ?>
    </div>

    <div class="card-body">
        <form method="post" id="seoForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="site" value="<?= htmlspecialchars($seo_page) ?>">
            <input type="hidden" name="active_lang" id="active_lang" value="<?= htmlspecialchars($currentLang) ?>">

            <?php foreach ($seo_data as $langCode => $data): ?>
                <input type="hidden" name="storage_title_<?= $langCode ?>" id="storage_title_<?= $langCode ?>" value="<?= htmlspecialchars($data['title']) ?>">
                <input type="hidden" name="storage_desc_<?= $langCode ?>" id="storage_desc_<?= $langCode ?>" value="<?= htmlspecialchars($data['description']) ?>">
            <?php endforeach; ?>

            <div class="btn-group mb-3" id="lang-switch">
                <?php foreach ($languages as $iso => $label): ?>
                    <button type="button"
                            class="btn <?= $iso === $currentLang ? 'btn-primary' : 'btn-secondary' ?>"
                            data-lang="<?= $iso ?>">
                        <?= strtoupper($iso) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="mb-3">
                <label><?= $languageService->get('meta_title') ?></label>
                <input type="text" id="seoTitle" class="form-control"
                       value="<?= htmlspecialchars($seo_data[$currentLang]['title'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label><?= $languageService->get('meta_description') ?></label>
                <textarea id="seoDescription" class="form-control" rows="4"><?= htmlspecialchars($seo_data[$currentLang]['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="save" class="btn btn-primary">
                <?= $languageService->get('save') ?>
            </button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('#lang-switch button').forEach(btn => {
    btn.addEventListener('click', function () {

        const newLang = this.getAttribute('data-lang');
        const activeBtn = document.querySelector('#lang-switch .btn-primary');
        const oldLang = activeBtn.getAttribute('data-lang');

        if (newLang === oldLang) return;

        document.getElementById('storage_title_' + oldLang).value =
            document.getElementById('seoTitle').value;

        document.getElementById('storage_desc_' + oldLang).value =
            document.getElementById('seoDescription').value;

        document.getElementById('seoTitle').value =
            document.getElementById('storage_title_' + newLang).value;

        document.getElementById('seoDescription').value =
            document.getElementById('storage_desc_' + newLang).value;

        document.getElementById('active_lang').value = newLang;

        activeBtn.classList.replace('btn-primary', 'btn-secondary');
        this.classList.replace('btn-secondary', 'btn-primary');
    });
});

document.getElementById('seoForm').addEventListener('submit', function () {
    const activeLang =
        document.querySelector('#lang-switch .btn-primary').getAttribute('data-lang');

    document.getElementById('storage_title_' + activeLang).value =
        document.getElementById('seoTitle').value;

    document.getElementById('storage_desc_' + activeLang).value =
        document.getElementById('seoDescription').value;
});
</script>

<?php else: ?>

<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="card-title">
            <i class="bi bi-search-heart"></i>
            <?= $languageService->get('seo_meta') ?>
        </div>
    </div>

    <div class="card-body">

        <div class="alert alert-info">
            <h5 class="mb-0">
                <button
                    class="btn btn-link btn-alert-link ps-0 pe-1"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#seoMetaCollapse"
                    aria-expanded="false"
                    aria-controls="seoMetaCollapse"
                >
                    <?= $languageService->get('seo_meta_title') ?>
                </button>
            </h5>

            <div class="collapse mt-3" id="seoMetaCollapse">

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
                    <li>
                        <strong><?= $languageService->get('seo_meta_example_title_label') ?>:</strong>
                        <?= $languageService->get('seo_meta_example_title') ?>
                    </li>
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
        </div>

        <a href="admincenter.php?site=seo_meta&action=add" class="btn btn-secondary mb-4">
            <i class="bi bi-plus-circle"></i>
            <?= $languageService->get('add') ?>
        </a>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
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
                            <td>
                                <small class="text-muted">
                                    <?= htmlspecialchars(mb_strimwidth($entry['description'], 0, 60, '...')) ?>
                                </small>
                            </td>
                            <td class="text-end">
                                
                                    <a href="admincenter.php?site=seo_meta&action=edit&page=<?= urlencode($entry['site']) ?>"
                                       class="btn btn-warning">
                                        <i class="bi bi-pencil-square"></i> <?= $languageService->get('edit') ?></a>
                                    </a>
                                    <button type="button"
                                            class="btn btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteModal"
                                            data-delete-url="admincenter.php?site=seo_meta&del=<?= urlencode($entry['site']) ?>">
                                        <i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?>
                                    </button>
                               
                            </td>
                            <!-- Bootstrap Modal for Confirm Delete -->
                            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                    
                                        <div class="modal-header">
                                            <h5 class="modal-title">' . $languageService->get('modulname') . ': 
                                                <span id="modalPluginTitle"></span>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        
                                        <div class="modal-body">
                                            ' . $languageService->get('really_delete_plugin') . '
                                        </div>
                                        
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="bi bi-x-square"></i> ' . $languageService->get('close') . '
                                            </button>
                                            <a id="confirmDeleteBtn" href="#" class="btn btn-danger">
                                                <i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '
                                            </a>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($allSeo)): ?>
                        <tr>
                            <td colspan="4" class="text-center p-4 text-muted">
                                <?= $languageService->get('no_entries') ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>
</div>

<?php endif; ?>
