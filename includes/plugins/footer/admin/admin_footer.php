<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
global $languageService;

use nexpell\AccessControl;
AccessControl::checkAdminAccess('footer');

$db = $GLOBALS['_database'] ?? null;
$action = $_GET['action'] ?? 'overview';

if ($db instanceof mysqli) {
    $hasFooterLinkName = false;
    $hasFooterLinkUrl = false;
    $hasLegacyName = false;
    $hasLegacyUrl = false;
    $hasSectionSort = false;
    $hasLinkSort = false;

    $r = mysqli_query($db, "SHOW COLUMNS FROM plugins_footer LIKE 'footer_link_name'");
    $hasFooterLinkName = (bool)($r && mysqli_num_rows($r) > 0);
    $r = mysqli_query($db, "SHOW COLUMNS FROM plugins_footer LIKE 'footer_link_url'");
    $hasFooterLinkUrl = (bool)($r && mysqli_num_rows($r) > 0);
    $r = mysqli_query($db, "SHOW COLUMNS FROM plugins_footer LIKE 'copyright_link_name'");
    $hasLegacyName = (bool)($r && mysqli_num_rows($r) > 0);
    $r = mysqli_query($db, "SHOW COLUMNS FROM plugins_footer LIKE 'copyright_link'");
    $hasLegacyUrl = (bool)($r && mysqli_num_rows($r) > 0);
    $r = mysqli_query($db, "SHOW COLUMNS FROM plugins_footer LIKE 'section_sort'");
    $hasSectionSort = (bool)($r && mysqli_num_rows($r) > 0);
    $r = mysqli_query($db, "SHOW COLUMNS FROM plugins_footer LIKE 'link_sort'");
    $hasLinkSort = (bool)($r && mysqli_num_rows($r) > 0);

    if (!$hasFooterLinkName) {
        @mysqli_query($db, "ALTER TABLE plugins_footer ADD COLUMN footer_link_name varchar(255) NOT NULL DEFAULT ''");
        $hasFooterLinkName = true;
    }
    if (!$hasFooterLinkUrl) {
        @mysqli_query($db, "ALTER TABLE plugins_footer ADD COLUMN footer_link_url varchar(255) NOT NULL DEFAULT ''");
        $hasFooterLinkUrl = true;
    }
    if (!$hasSectionSort) {
        @mysqli_query($db, "ALTER TABLE plugins_footer ADD COLUMN section_sort int(10) unsigned NOT NULL DEFAULT 1");
        $hasSectionSort = true;
    }
    if (!$hasLinkSort) {
        @mysqli_query($db, "ALTER TABLE plugins_footer ADD COLUMN link_sort int(10) unsigned NOT NULL DEFAULT 1");
        $hasLinkSort = true;
    }

    // Einmalige Legacy-Spiegelung, falls alte Felder befüllt sind.
    if ($hasFooterLinkName && $hasLegacyName) {
        @mysqli_query($db, "
            UPDATE plugins_footer
            SET footer_link_name = copyright_link_name
            WHERE footer_link_name = ''
              AND copyright_link_name <> ''
        ");
    }
    if ($hasFooterLinkUrl && $hasLegacyUrl) {
        @mysqli_query($db, "
            UPDATE plugins_footer
            SET footer_link_url = copyright_link
            WHERE footer_link_url = ''
              AND copyright_link <> ''
        ");
    }
}

// Legacy-Fix: alte Tabellen haben teilweise UNIQUE(link_number),
// was neue Inserts mit Defaultwert 0 blockiert.
if ($db instanceof mysqli) {
    $idxRes = mysqli_query($db, "SHOW INDEX FROM plugins_footer WHERE Key_name='link_number'");
    if ($idxRes && mysqli_num_rows($idxRes) > 0) {
        @mysqli_query($db, "ALTER TABLE plugins_footer DROP INDEX `link_number`");
    }
}

$languages = [];
$resLang = mysqli_query($db, "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
if ($resLang) {
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[strtolower((string)$row['iso_639_1'])] = (string)$row['name_de'];
    }
}
if (empty($languages)) {
    $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
}

if (!empty($_SESSION['footer_active_lang'])) {
    $currentLang = strtolower((string)$_SESSION['footer_active_lang']);
    unset($_SESSION['footer_active_lang']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['active_lang'])) {
    $currentLang = strtolower((string)$_POST['active_lang']);
} elseif (!empty($_SESSION['language'])) {
    $currentLang = strtolower((string)$_SESSION['language']);
} else {
    $currentLang = strtolower((string)$languageService->detectLanguage());
}
if (!isset($languages[$currentLang])) {
    $currentLang = (string)array_key_first($languages);
}

function footer_lang_table_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $res = mysqli_query($db, "SHOW TABLES LIKE 'plugins_footer_lang'");
    $ready = ($res && mysqli_num_rows($res) > 0);
    return $ready;
}

function footer_lang_get(mysqli $db, string $contentKey, string $lang, string $fallback = ''): string
{
    if (!footer_lang_table_ready($db)) {
        return $fallback;
    }

    $keyEsc = mysqli_real_escape_string($db, $contentKey);
    $order = array_values(array_unique([strtolower($lang), 'en', 'gb', 'de', 'it']));

    foreach ($order as $iso) {
        $isoEsc = mysqli_real_escape_string($db, $iso);
        $res = mysqli_query($db, "
            SELECT content
            FROM plugins_footer_lang
            WHERE content_key='{$keyEsc}' AND language='{$isoEsc}'
            LIMIT 1
        ");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $txt = trim((string)($row['content'] ?? ''));
            if ($txt !== '') {
                return $txt;
            }
        }
    }

    return $fallback;
}

function footer_lang_get_all(mysqli $db, string $contentKey, array $langs, string $fallback = ''): array
{
    $out = [];
    foreach ($langs as $iso => $_label) {
        $out[$iso] = footer_lang_get($db, $contentKey, (string)$iso, $fallback);
    }
    return $out;
}

function footer_lang_upsert(mysqli $db, string $contentKey, array $valuesByLang, array $langs): void
{
    if (!footer_lang_table_ready($db)) {
        return;
    }
    $keyEsc = mysqli_real_escape_string($db, $contentKey);
    foreach ($langs as $iso => $_label) {
        $isoEsc = mysqli_real_escape_string($db, (string)$iso);
        $valEsc = mysqli_real_escape_string($db, (string)($valuesByLang[$iso] ?? ''));
        mysqli_query($db, "
            INSERT INTO plugins_footer_lang (content_key, language, content, updated_at)
            VALUES ('{$keyEsc}','{$isoEsc}','{$valEsc}', NOW())
            ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()
        ");
    }
}

function footer_lang_delete_key(mysqli $db, string $contentKey): void
{
    if (!footer_lang_table_ready($db)) {
        return;
    }
    $keyEsc = mysqli_real_escape_string($db, $contentKey);
    mysqli_query($db, "DELETE FROM plugins_footer_lang WHERE content_key='{$keyEsc}'");
}

function footer_category_lang_key(string $categoryKey): string
{
    return 'cat_title_' . $categoryKey;
}

function footer_category_title_get(mysqli $db, string $categoryKey, string $lang, string $fallback = ''): string
{
    $categoryKey = trim($categoryKey);
    if ($categoryKey === '') {
        return $fallback;
    }
    return footer_lang_get($db, footer_category_lang_key($categoryKey), $lang, $fallback);
}

function footer_category_title_get_all(mysqli $db, string $categoryKey, array $langs, string $fallback = ''): array
{
    $categoryKey = trim($categoryKey);
    if ($categoryKey === '') {
        return [];
    }
    return footer_lang_get_all($db, footer_category_lang_key($categoryKey), $langs, $fallback);
}

function footer_category_title_upsert(mysqli $db, string $categoryKey, array $valuesByLang, array $langs): void
{
    $categoryKey = trim($categoryKey);
    if ($categoryKey === '') {
        return;
    }
    footer_lang_upsert($db, footer_category_lang_key($categoryKey), $valuesByLang, $langs);
}

function footer_reconcile_data(mysqli $db): void
{
    $legalKey = md5('Rechtliches');
    $navKey = md5('Navigation');
    $legalKeyEsc = mysqli_real_escape_string($db, $legalKey);
    $navKeyEsc = mysqli_real_escape_string($db, $navKey);

    // Normalize core categories/keys.
    mysqli_query($db, "UPDATE plugins_footer SET category_key='{$legalKeyEsc}', section_title='Rechtliches' WHERE row_type='category' AND section_title='Rechtliches'");
    mysqli_query($db, "UPDATE plugins_footer SET category_key='{$navKeyEsc}', section_title='Navigation' WHERE row_type='category' AND section_title='Navigation'");

    mysqli_query($db, "
        INSERT INTO plugins_footer
            (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
        SELECT 'category', '{$legalKeyEsc}', 'Rechtliches', 2, 1, '', '', 0
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM plugins_footer WHERE row_type='category' AND category_key='{$legalKeyEsc}'
        )
    ");
    mysqli_query($db, "
        INSERT INTO plugins_footer
            (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
        SELECT 'category', '{$navKeyEsc}', 'Navigation', 3, 1, '', '', 0
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM plugins_footer WHERE row_type='category' AND category_key='{$navKeyEsc}'
        )
    ");

    $catByKey = [];
    $catByTitle = [];
    $catRes = mysqli_query($db, "SELECT category_key, section_title FROM plugins_footer WHERE row_type='category'");
    if ($catRes) {
        while ($c = mysqli_fetch_assoc($catRes)) {
            $k = (string)($c['category_key'] ?? '');
            $t = trim((string)($c['section_title'] ?? ''));
            if ($k !== '') {
                $catByKey[$k] = $t;
            }
            if ($t !== '') {
                $catByTitle[mb_strtolower($t)] = $k;
            }
        }
    }

    $linkRes = mysqli_query($db, "SELECT id, category_key, section_title, footer_link_url FROM plugins_footer WHERE row_type='link'");
    if ($linkRes) {
        while ($l = mysqli_fetch_assoc($linkRes)) {
            $id = (int)($l['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $curKey = (string)($l['category_key'] ?? '');
            $curTitle = trim((string)($l['section_title'] ?? ''));
            $url = trim((string)($l['footer_link_url'] ?? ''));
            $urlLower = mb_strtolower($url);

            $targetKey = $curKey;
            $targetTitle = $curTitle;

            // Canonical URL mapping for legal/core links.
            if (
                strpos($urlLower, 'site=imprint') !== false
                || strpos($urlLower, 'site=privacy_policy') !== false
                || strpos($urlLower, 'site=terms_of_service') !== false
            ) {
                $targetKey = $legalKey;
                $targetTitle = 'Rechtliches';
            } elseif (strpos($urlLower, 'site=contact') !== false) {
                $targetKey = $navKey;
                $targetTitle = 'Navigation';
            } elseif ($targetKey !== '' && isset($catByKey[$targetKey])) {
                $targetTitle = (string)$catByKey[$targetKey];
            } elseif ($curTitle !== '' && isset($catByTitle[mb_strtolower($curTitle)])) {
                $targetKey = (string)$catByTitle[mb_strtolower($curTitle)];
                $targetTitle = $curTitle;
            } elseif ($curTitle !== '') {
                $newKey = md5($curTitle);
                $newKeyEsc = mysqli_real_escape_string($db, $newKey);
                $titleEsc = mysqli_real_escape_string($db, $curTitle);
                mysqli_query($db, "
                    INSERT INTO plugins_footer
                        (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
                    SELECT 'category', '{$newKeyEsc}', '{$titleEsc}', 9, 1, '', '', 0
                    FROM DUAL
                    WHERE NOT EXISTS (
                        SELECT 1 FROM plugins_footer WHERE row_type='category' AND category_key='{$newKeyEsc}'
                    )
                ");
                $targetKey = $newKey;
                $targetTitle = $curTitle;
            } else {
                $targetKey = $navKey;
                $targetTitle = 'Navigation';
            }

            $keyEsc = mysqli_real_escape_string($db, $targetKey);
            $titleEsc = mysqli_real_escape_string($db, $targetTitle);
            mysqli_query($db, "
                UPDATE plugins_footer
                SET category_key='{$keyEsc}', section_title='{$titleEsc}'
                WHERE id={$id} AND row_type='link'
                LIMIT 1
            ");

        }
    }

    // Remove duplicate categories by category_key (keep lowest id).
    $catKeep = [];
    $catDupRes = mysqli_query($db, "SELECT id, category_key FROM plugins_footer WHERE row_type='category' ORDER BY id ASC");
    if ($catDupRes) {
        while ($row = mysqli_fetch_assoc($catDupRes)) {
            $id = (int)($row['id'] ?? 0);
            $k = (string)($row['category_key'] ?? '');
            if ($id <= 0 || $k === '') {
                continue;
            }
            if (!isset($catKeep[$k])) {
                $catKeep[$k] = $id;
                continue;
            }
            mysqli_query($db, "DELETE FROM plugins_footer WHERE id={$id} AND row_type='category' LIMIT 1");
        }
    }

    // Remove duplicate links by (category_key + footer_link_url), keep lowest id.
    $linkKeep = [];
    $dupLinkRes = mysqli_query($db, "
        SELECT id, category_key, footer_link_url
        FROM plugins_footer
        WHERE row_type='link' AND footer_link_url<>''
        ORDER BY id ASC
    ");
    if ($dupLinkRes) {
        while ($row = mysqli_fetch_assoc($dupLinkRes)) {
            $id = (int)($row['id'] ?? 0);
            $k = (string)($row['category_key'] ?? '');
            $u = trim((string)($row['footer_link_url'] ?? ''));
            if ($id <= 0 || $u === '') {
                continue;
            }
            $group = $k . '|' . mb_strtolower($u);
            if (!isset($linkKeep[$group])) {
                $linkKeep[$group] = $id;
                continue;
            }

            $keepId = (int)$linkKeep[$group];
            // Merge multilingual values from duplicate id into keep id where keep is empty.
            if (footer_lang_table_ready($db)) {
                $srcKey = mysqli_real_escape_string($db, 'link_name_' . $id);
                $dstKey = mysqli_real_escape_string($db, 'link_name_' . $keepId);
                $langRes = mysqli_query($db, "SELECT language, content FROM plugins_footer_lang WHERE content_key='{$srcKey}'");
                if ($langRes) {
                    while ($lr = mysqli_fetch_assoc($langRes)) {
                        $iso = strtolower(trim((string)($lr['language'] ?? '')));
                        if ($iso === '') {
                            continue;
                        }
                        $dstCur = footer_lang_get($db, 'link_name_' . $keepId, $iso, '');
                        if ($dstCur !== '') {
                            continue;
                        }
                        footer_lang_upsert($db, 'link_name_' . $keepId, [$iso => (string)($lr['content'] ?? '')], [$iso => '']);
                    }
                }
                footer_lang_delete_key($db, 'link_name_' . $id);
            }

            mysqli_query($db, "DELETE FROM plugins_footer WHERE id={$id} AND row_type='link' LIMIT 1");
        }
    }

    // Ensure single rows for footer_text/footer_template.
    $singleRows = [
        ['row_type' => 'footer_text', 'section_title' => 'footer_description'],
        ['row_type' => 'footer_template', 'section_title' => 'footer_template'],
    ];
    foreach ($singleRows as $sr) {
        $rtEsc = mysqli_real_escape_string($db, (string)$sr['row_type']);
        $stEsc = mysqli_real_escape_string($db, (string)$sr['section_title']);
        $oneRes = mysqli_query($db, "
            SELECT id
            FROM plugins_footer
            WHERE row_type='{$rtEsc}' AND section_title='{$stEsc}'
            ORDER BY id ASC
        ");
        $keepId = 0;
        if ($oneRes) {
            while ($or = mysqli_fetch_assoc($oneRes)) {
                $id = (int)($or['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if ($keepId === 0) {
                    $keepId = $id;
                    continue;
                }
                mysqli_query($db, "DELETE FROM plugins_footer WHERE id={$id} LIMIT 1");
            }
        }
    }

    // Normalize sort order per category.
    $kRes = mysqli_query($db, "SELECT DISTINCT category_key FROM plugins_footer WHERE row_type='link' AND category_key<>''");
    if ($kRes) {
        while ($kr = mysqli_fetch_assoc($kRes)) {
            $k = (string)($kr['category_key'] ?? '');
            if ($k === '') {
                continue;
            }
            $kEsc = mysqli_real_escape_string($db, $k);
            $rRes = mysqli_query($db, "SELECT id FROM plugins_footer WHERE row_type='link' AND category_key='{$kEsc}' ORDER BY link_sort ASC, id ASC");
            $i = 1;
            if ($rRes) {
                while ($rr = mysqli_fetch_assoc($rRes)) {
                    $id = (int)($rr['id'] ?? 0);
                    if ($id > 0) {
                        mysqli_query($db, "UPDATE plugins_footer SET link_sort={$i} WHERE id={$id} LIMIT 1");
                        $i++;
                    }
                }
            }
        }
    }
}

if ($db instanceof mysqli) {
    footer_reconcile_data($db);
}

// Pflichtlinks in der Kategorie "Rechtliches"
function footer_isProtectedLegalLink(
    string $categoryTitle,
    string $linkName,
    string $linkUrl
): bool {

    if ($categoryTitle !== 'Rechtliches') {
        return false;
    }

    $name = strtolower(trim($linkName));
    if (in_array($name, ['imprint', 'privacy_policy', 'terms_of_service'], true)) {
        return true;
    }

    $url = strtolower($linkUrl);
    return (
        strpos($url, 'site=imprint') !== false
        || strpos($url, 'site=privacy_policy') !== false
        || strpos($url, 'site=terms_of_service') !== false
    );
}

// ACTIONS (POST/GET)
if ($action === 'save_sort') {
    header('Content-Type: application/json');

    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'invalid_payload']);
        exit;
    }

    $categoryKey = (string)($payload['category_key'] ?? '');
    $order = $payload['order'] ?? [];
    if ($categoryKey === '' || !is_array($order)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'invalid_data']);
        exit;
    }

    $categoryKeyEsc = mysqli_real_escape_string($db, $categoryKey);
    foreach ($order as $row) {
        $id = (int)($row['id'] ?? 0);
        $sort = (int)($row['sort_order'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        if ($sort < 1) {
            $sort = 1;
        }
        mysqli_query($db, "
            UPDATE plugins_footer
            SET link_sort={$sort}
            WHERE id={$id}
              AND row_type='link'
              AND category_key='{$categoryKeyEsc}'
            LIMIT 1
        ");
    }

    echo json_encode(['status' => 'ok']);
    exit;
}

// LINK DELETE
if ($action === 'link_delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Schutz: Pflichtlinks in "Rechtliches" dÃ¼rfen nicht gelÃ¶scht werden
    $chkRes = mysqli_query($db, "
        SELECT category_key, section_title, footer_link_name, footer_link_url
        FROM plugins_footer
        WHERE id={$id} AND row_type='link'
        LIMIT 1
    ");
    if ($chkRes && ($chk = mysqli_fetch_assoc($chkRes))) {
        $catKey   = (string)($chk['category_key'] ?? '');
        $catTitle = (string)($chk['section_title'] ?? '');
        $ln = footer_lang_get($db, 'link_name_' . $id, 'en', (string)($chk['footer_link_name'] ?? ''));
        $lu = (string)($chk['footer_link_url'] ?? '');

        $isLegalCategory = ($catKey !== '' && $catKey === md5('Rechtliches'));
        $isProtectedLink = $isLegalCategory
            ? footer_isProtectedLegalLink('Rechtliches', $ln, $lu)
            : footer_isProtectedLegalLink($catTitle, $ln, $lu);

        if ($isProtectedLink) {
            nx_alert('danger', 'alert_legit_links_delete', false);
            return;
        }
    }

    mysqli_query($db, "DELETE FROM plugins_footer WHERE id={$id} LIMIT 1");
    footer_lang_delete_key($db, 'link_name_' . $id);

    nx_audit_delete('footer', (string)$id, (string)$id, 'admincenter.php?site=admin_footer');
    nx_redirect('admincenter.php?site=admin_footer', 'success', 'alert_deleted', false);
}

// CATEGORY DELETE
if ($action === 'category_delete' && isset($_GET['key'])) {
    $key = (string)$_GET['key'];

    // Schutz: Standard-Kategorie "Rechtliches" darf nie gelÃ¶scht werden
    if ($key === md5('Rechtliches')) {
        nx_alert('danger', 'alert_legit_delete', false);
        return;
    }

    $keyEsc = mysqli_real_escape_string($db, $key);

    $delLinksRes = mysqli_query($db, "SELECT id FROM plugins_footer WHERE row_type='link' AND category_key='{$keyEsc}'");
    if ($delLinksRes) {
        while ($dr = mysqli_fetch_assoc($delLinksRes)) {
            footer_lang_delete_key($db, 'link_name_' . (int)$dr['id']);
        }
    }
    footer_lang_delete_key($db, footer_category_lang_key($key));
    mysqli_query($db, "DELETE FROM plugins_footer WHERE row_type='link' AND category_key='{$keyEsc}'");
    mysqli_query($db, "DELETE FROM plugins_footer WHERE row_type='category' AND category_key='{$keyEsc}'");

    nx_audit_delete('footer', $key, $key, 'admincenter.php?site=admin_footer');
    nx_redirect('admincenter.php?site=admin_footer', 'success', 'alert_deleted', false);
}

// CATEGORY SAVE (ADD/EDIT)
if (isset($_POST['save_category'])) {
    $mode = $_POST['mode'] ?? 'add';
    $activeLang = strtolower(trim((string)($_POST['active_lang'] ?? $currentLang)));
    if (!isset($languages[$activeLang])) {
        $activeLang = $currentLang;
    }
    $_SESSION['footer_active_lang'] = $activeLang;

    $titleByLang = $_POST['category_title_lang'] ?? [];
    if (!is_array($titleByLang)) {
        $titleByLang = [];
    }
    if (empty($titleByLang) && isset($_POST['new_title'])) {
        $titleByLang[$activeLang] = (string)$_POST['new_title'];
    }

    $newTitle = trim((string)($titleByLang[$activeLang] ?? ($_POST['new_title'] ?? '')));
    if ($newTitle === '') {
        foreach ($titleByLang as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '') {
                $newTitle = $candidate;
                break;
            }
        }
    }
    $newEsc = mysqli_real_escape_string($db, $newTitle);

    if ($newTitle === '') {
        nx_alert('warning', 'alert_missing_required', false);
    } else {
        if ($mode === 'add') {
            $dupRes = mysqli_query($db, "SELECT id FROM plugins_footer WHERE row_type='category' AND section_title='{$newEsc}' LIMIT 1");
            if ($dupRes && mysqli_fetch_assoc($dupRes)) {
                nx_alert('warning', 'alert_no_changes', false);
            } else {
                $sRes = mysqli_query($db, "SELECT COALESCE(MAX(section_sort),0)+1 AS next_sort FROM plugins_footer WHERE row_type='category'");
                $nextSort = 1;
                if ($sRes && ($sr = mysqli_fetch_assoc($sRes))) {
                    $nextSort = (int)$sr['next_sort'];
                    if ($nextSort < 1) { $nextSort = 1; }
                }

                mysqli_query($db, "
                    INSERT INTO plugins_footer
                        (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
                    VALUES
                        ('category', MD5('{$newEsc}'), '{$newEsc}', {$nextSort}, 1, '', '', 0)
                ");
                $newKey = md5($newTitle);
                footer_category_title_upsert($db, $newKey, $titleByLang, $languages);

                nx_audit_create('footer', $newKey, $newTitle, 'admincenter.php?site=admin_footer');
                nx_redirect('admincenter.php?site=admin_footer', 'success', 'alert_saved', false);
            }
        }

        if ($mode === 'edit') {
            $key = (string)($_POST['category_key'] ?? '');
            $keyEsc = mysqli_real_escape_string($db, $key);

            mysqli_query($db, "UPDATE plugins_footer SET section_title='{$newEsc}' WHERE row_type='category' AND category_key='{$keyEsc}'");
            mysqli_query($db, "UPDATE plugins_footer SET section_title='{$newEsc}' WHERE row_type='link' AND category_key='{$keyEsc}'");
            footer_category_title_upsert($db, $key, $titleByLang, $languages);

            nx_audit_update('footer', $key, true, $newTitle, 'admincenter.php?site=admin_footer');
            nx_redirect('admincenter.php?site=admin_footer', 'success', 'alert_saved', false);
        }
    }
}

// LINK SAVE (ADD/EDIT)
if (isset($_POST['save_link'])) {
    $mode = $_POST['mode'] ?? 'add';
    $id = (int)($_POST['id'] ?? 0);
    $activeLang = strtolower(trim((string)($_POST['active_lang'] ?? $currentLang)));
    if (!isset($languages[$activeLang])) {
        $activeLang = $currentLang;
    }
    $_SESSION['footer_active_lang'] = $activeLang;

    $categoryKey = trim((string)($_POST['category_key'] ?? ''));
    $nameByLang = $_POST['footer_link_name_lang'] ?? [];
    if (!is_array($nameByLang)) {
        $nameByLang = [];
    }
    if (empty($nameByLang) && isset($_POST['footer_link_name'])) {
        $nameByLang[$activeLang] = (string)$_POST['footer_link_name'];
    }
    $nameActive = trim((string)($nameByLang[$activeLang] ?? ''));
    $url  = trim((string)($_POST['footer_link_url'] ?? ''));
    $newTab = !empty($_POST['new_tab']) ? 1 : 0;

    // Wenn Pflichtlink (imprint/privacy_policy): URL darf nicht geÃ¤ndert werden
    if ($mode === 'edit' && $id > 0) {
        $exRes = mysqli_query($db, "SELECT section_title, category_key, footer_link_name, footer_link_url FROM plugins_footer WHERE id={$id} AND row_type='link' LIMIT 1");
        if ($exRes && ($ex = mysqli_fetch_assoc($exRes))) {
            $exName = footer_lang_get($db, 'link_name_' . $id, 'en', (string)($ex['footer_link_name'] ?? ''));
            $exUrl  = (string)($ex['footer_link_url'] ?? '');
            $isLegalCategory = (!empty($ex['category_key']) && (string)$ex['category_key'] === md5('Rechtliches'));
            if ($isLegalCategory
                ? footer_isProtectedLegalLink('Rechtliches', $exName, $exUrl)
                : footer_isProtectedLegalLink((string)$ex['section_title'], $exName, $exUrl)
            ) {
                $url = $exUrl;
                if (!empty($ex['category_key'])) {
                    $categoryKey = (string)$ex['category_key'];
                }
            }
        }
    }

    // Titel anhand category_key ermitteln
    $categoryTitle = '';
    if ($categoryKey !== '') {
        $ckEsc = mysqli_real_escape_string($db, $categoryKey);

        $resC = mysqli_query($db, "SELECT section_title FROM plugins_footer WHERE row_type='category' AND category_key='{$ckEsc}' LIMIT 1");
        if ($resC && ($rc = mysqli_fetch_assoc($resC))) {
            $categoryTitle = (string)$rc['section_title'];
        }
    }

    if ($categoryKey === '' || $categoryTitle === '' || $nameActive === '' || $url === '') {
        nx_alert('warning', 'alert_missing_required', false);
    } else {
        $catTitleEsc = mysqli_real_escape_string($db, $categoryTitle);
        $nameEsc = mysqli_real_escape_string($db, $nameActive);
        $urlEsc  = mysqli_real_escape_string($db, $url);
        $storeKeyEsc = mysqli_real_escape_string($db, $categoryKey);

        if ($mode === 'edit' && $id > 0) {
            mysqli_query($db, "
                UPDATE plugins_footer
                SET category_key='{$storeKeyEsc}',
                    section_title='{$catTitleEsc}',
                    footer_link_name='',
                    footer_link_url='{$urlEsc}',
                    new_tab={$newTab}
                WHERE id={$id} AND row_type='link'
                LIMIT 1
            ");
            footer_lang_upsert($db, 'link_name_' . $id, $nameByLang, $languages);
            nx_audit_update('footer', (string)$id, true, $nameActive, 'admincenter.php?site=admin_footer');
        } else {
            $lsRes = mysqli_query($db, "
                SELECT COALESCE(MAX(link_sort),0)+1 AS next_sort
                FROM plugins_footer
                WHERE row_type='link'
                  AND category_key='{$storeKeyEsc}'
            ");
            $nextLinkSort = 1;
            if ($lsRes && ($lr = mysqli_fetch_assoc($lsRes))) {
                $nextLinkSort = (int)$lr['next_sort'];
                if ($nextLinkSort < 1) { $nextLinkSort = 1; }
            }

            mysqli_query($db, "
                INSERT INTO plugins_footer
                    (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
                VALUES
                    ('link', '{$storeKeyEsc}', '{$catTitleEsc}', 1, {$nextLinkSort}, '', '{$urlEsc}', {$newTab})
            ");
            $newId = (int)mysqli_insert_id($db);
            footer_lang_upsert($db, 'link_name_' . $newId, $nameByLang, $languages);
            nx_audit_create('footer', (string)$newId, $nameActive, 'admincenter.php?site=admin_footer');
        }

        nx_redirect('admincenter.php?site=admin_footer', 'success', 'alert_saved', false);
    }
}

// FOOTER SETTINGS SAVE
if (isset($_POST['save_footer_settings'])) {

    // Footer-Text speichern
    $activeLang = strtolower(trim((string)($_POST['active_lang'] ?? $currentLang)));
    if (!isset($languages[$activeLang])) {
        $activeLang = $currentLang;
    }
    $_SESSION['footer_active_lang'] = $activeLang;

    $txtByLang = $_POST['footer_text_lang'] ?? [];
    if (!is_array($txtByLang)) {
        $txtByLang = [];
    }
    if (empty($txtByLang) && isset($_POST['footer_text'])) {
        $txtByLang[$activeLang] = (string)$_POST['footer_text'];
    }
    $txtActive = trim((string)($txtByLang[$activeLang] ?? ''));
    $txtEsc = mysqli_real_escape_string($db, $txtActive);

    // Default-Row fÃ¼r Footer-Text sicherstellen
    mysqli_query($db, "
        INSERT INTO plugins_footer
            (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
        SELECT
            'footer_text', '', 'footer_description', 0, 0,
            'Klar, modern und responsiv â€“ eine solide Basis fÃ¼r deine Website.',
            '', 0
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM plugins_footer
            WHERE row_type='footer_text' AND section_title='footer_description'
        )
    ");

    mysqli_query($db, "
        UPDATE plugins_footer
        SET footer_link_name='{$txtEsc}'
        WHERE row_type='footer_text'
          AND section_title='footer_description'
        LIMIT 1
    ");
    footer_lang_upsert($db, 'footer_text', $txtByLang, $languages);

    // Footer Template speichern
    $tplSel = trim((string)($_POST['footer_template'] ?? 'standard'));

    // Minimal-Check: nur erlaubte Werte akzeptieren, sonst fallback
    $allowed = ['standard','simple','agency','modern'];
    if (!in_array($tplSel, $allowed, true)) {
        $tplSel = 'standard';
    }

    $tplSelEsc = mysqli_real_escape_string($db, $tplSel);

    // Default-Row fÃ¼r Template sicherstellen
    mysqli_query($db, "
        INSERT INTO plugins_footer
            (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
        SELECT
            'footer_template', '', 'footer_template', 0, 0,
            'standard',
            '', 0
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM plugins_footer
            WHERE row_type='footer_template' AND section_title='footer_template'
        )
    ");

    mysqli_query($db, "
        UPDATE plugins_footer
        SET footer_link_name='{$tplSelEsc}'
        WHERE row_type='footer_template'
          AND section_title='footer_template'
        LIMIT 1
    ");

    nx_audit_update('footer', null, true, null, 'admincenter.php?site=admin_footer&action=footer_settings');
    nx_redirect('admincenter.php?site=admin_footer&action=footer_settings', 'success', 'alert_saved', false);
}

// Kategorien
$catRes = mysqli_query($db, "
    SELECT
        c.section_title,
        c.category_key,
        (
            SELECT COUNT(*)
            FROM plugins_footer l
            WHERE
              (
                l.row_type='link'
                OR (l.row_type='' AND l.footer_link_name<>'' AND l.footer_link_url<>'')
              )
              AND
              (
                l.category_key = c.category_key
                OR (l.category_key='' AND l.section_title = c.section_title)
              )
        ) AS cnt
    FROM plugins_footer c
    WHERE c.row_type='category'
      AND c.section_title <> ''
    ORDER BY c.section_sort ASC, c.section_title ASC
");

$categories = [];
if ($catRes) {
    while ($c = mysqli_fetch_assoc($catRes)) {
        $catKey = (string)($c['category_key'] ?? '');
        $fallbackTitle = (string)($c['section_title'] ?? '');
        $c['section_title_display'] = footer_category_title_get($db, $catKey, $currentLang, $fallbackTitle);
        $categories[] = $c;
    }
}

// VIEW: OVERVIEW
if ($action === 'overview') {

    echo '<div class="d-flex flex-wrap gap-2 mb-3">
      <a class="btn btn-secondary" href="admincenter.php?site=admin_footer&action=link_add">' . $languageService->get('btn_add_link') . '</a>
      <a class="btn btn-secondary" href="admincenter.php?site=admin_footer&action=category_add">' . $languageService->get('btn_add_category') . '</a>
      <a class="btn btn-secondary" href="admincenter.php?site=admin_footer&action=footer_settings">' . $languageService->get('settings') . '</a>
    </div>';

    $footerError = (string)($_GET['footer_error'] ?? '');
    if ($footerError === 'protected_category') {
        nx_alert('warning', 'alert_legit_delete', false);
    }

    if (empty($categories)) {
        nx_alert('info', 'no_entries_found', false);
        return;
    }

    echo '
    <style>
      .cursor-move { cursor: grab; }
      .sortable-ghost { opacity: 0.5; }
      .fe-url code { font-size: .9em; }
      .fe-url a { text-decoration: none; }
      .fe-url a:hover code { text-decoration: underline; }
    </style>

    <div class="card shadow-sm mt-4">
      <div class="card-header">
        <div class="card-title">
          <i class="bi bi-link-45deg"></i> <span>' . $languageService->get('title_links') . '</span>
          <small class="text-muted">' . $languageService->get('overview') . '</small>
        </div>
      </div>

      <div class="card-body">

        <div class="alert alert-info alert-dismissible fade show" role="alert">
          <h6>' . $languageService->get('alert_info_title') . '</h6>
            <p>' . $languageService->get('alert_info_text') . '</p>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th style="width:40px"></th>
            <th style="width:25%">' . $languageService->get('name') . '</th>
            <th style="width:45%">' . $languageService->get('url') . '</th>
            <th style="width:10%">' . $languageService->get('th_tab') . '</th>
            <th style="width:20%" class="text-end">' . $languageService->get('actions') . '</th>
          </tr>
        </thead>';

    foreach ($categories as $cat) {
        $titleRaw = (string)$cat['section_title'];
        $title = (string)($cat['section_title_display'] ?? $titleRaw);
        $key   = (string)$cat['category_key'];
        $keyEsc = mysqli_real_escape_string($db, $key);
        $titleEsc = mysqli_real_escape_string($db, $titleRaw);

        // Kategorie-Gruppenzeile
        echo '
        <tbody class="footer-links-sortable" data-category-key="'.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'">
          <tr class="table-light">
            <td class="text-muted"><i class="bi bi-list"></i></td>
            <td class="fw-semibold">'.htmlspecialchars($title, ENT_QUOTES).'</td>
            <td></td>
            <td></td>
            <td class="text-end">
              <a class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto me-2"
                 href="admincenter.php?site=admin_footer&action=category_edit&key='.urlencode($key).'"><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '</a>';

              if ($key === md5('Rechtliches')) {
                  echo '<button class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto" type="button" disabled
                        title="Standard-Kategorie kann nicht gelÃ¶scht werden"><i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '</button>';
              } else {
                $deleteUrl = '?site=admin_footer&action=category_delete&key=' . urlencode($key);
                $encodedDeleteUrl = base64_encode($deleteUrl);

                echo '<a href="#"
                          class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto"
                          data-bs-toggle="modal"
                          data-bs-target="#confirmDeleteModal"
                          data-delete-url="' . htmlspecialchars($encodedDeleteUrl, ENT_QUOTES, 'UTF-8') . '">
                          <i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '
                      </a>';
              }

        echo '
            </td>
          </tr>';

        // Links laden
        $linksRes = mysqli_query($db, "
            SELECT id, footer_link_name, footer_link_url, new_tab
            FROM plugins_footer
            WHERE
              (
                row_type='link'
                OR (row_type='' AND footer_link_name<>'' AND footer_link_url<>'')
              )
              AND
              (
                category_key='{$keyEsc}'
                OR (category_key='' AND section_title='{$titleEsc}')
              )
            ORDER BY link_sort ASC, id ASC
        ");

        $hasLinks = false;

        if ($linksRes) {
            while ($lr = mysqli_fetch_assoc($linksRes)) {
                $hasLinks = true;

                $lid    = (int)$lr['id'];
                $nameRaw = (string)$lr['footer_link_name'];
                $name   = footer_lang_get($db, 'link_name_' . $lid, $currentLang, $nameRaw);
                $url    = (string)$lr['footer_link_url'];
                $isNew  = ((int)$lr['new_tab'] === 1);

                $newTabLabel = $isNew ? 'Ja' : 'Nein';

                // URL
                $urlCode = htmlspecialchars($url, ENT_QUOTES);
                if ($url !== '') {
                    $target = $isNew ? ' target="_blank" rel="noopener"' : '';
                    $urlCode = '<code>'.$urlCode.'</code>';
                } else {
                    $urlCode = '<code class="text-muted">â€”</code>';
                }

                echo '
                  <tr class="footer-link-row" data-link-id="'.$lid.'">
                    <td class="text-muted cursor-move"><i class="bi bi-list"></i></td>
                    <td class="ps-2">- '.htmlspecialchars($name, ENT_QUOTES).'</td>
                    <td class="fe-url">'.$urlCode.'</td>
                    <td>'.$newTabLabel.'</td>
                    <td class="text-end">
                      <a class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto me-2"
                         href="admincenter.php?site=admin_footer&action=link_edit&id='.$lid.'"><i class="bi bi-pencil-square"></i> ' . $languageService->get('edit') . '</a>
                        '.(
                          (
                              ($key === md5('Rechtliches'))
                                ? footer_isProtectedLegalLink('Rechtliches', $name, $url)
                                : footer_isProtectedLegalLink($titleRaw, $name, $url)
                          )
                          ? '<button class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto" type="button" disabled title="Pflichtlink kann nicht gelÃ¶scht werden"><i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '</button>'
                          : '<a href="#"
                                class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto"
                                data-bs-toggle="modal"
                                data-bs-target="#confirmDeleteModal"
                                data-delete-url="' . htmlspecialchars(
                                    base64_encode('?site=admin_footer&action=link_delete&id=' . intval($lid)),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) . '"><i class="bi bi-trash3"></i> ' . $languageService->get('delete') . '</a>'
                        ).'
                    </td>
                  </tr>';
            }
        }

        if (!$hasLinks) {
            echo '
              <tr class="no-sort">
                <td></td>
                <td class="ps-2 text-muted small" colspan="4">
                  ' . $languageService->get('td_no_links') . '
                </td>
              </tr>';
        }

        echo '</tbody>';
    }

    echo '
      </table>
    </div></div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
      const bodies = document.querySelectorAll(".footer-links-sortable");
      if (!bodies.length || typeof Sortable === "undefined") return;

      bodies.forEach(function (tbody) {
        const categoryKey = tbody.getAttribute("data-category-key") || "";
        Sortable.create(tbody, {
          handle: ".cursor-move",
          draggable: "tr.footer-link-row",
          animation: 150,
          onEnd: function () {
            const order = [];
            tbody.querySelectorAll("tr.footer-link-row").forEach(function (tr, idx) {
              order.push({
                id: tr.getAttribute("data-link-id"),
                sort_order: idx + 1
              });
            });

            fetch("admincenter.php?site=admin_footer&action=save_sort", {
              method: "POST",
              headers: {"Content-Type": "application/json"},
              body: JSON.stringify({
                category_key: categoryKey,
                order: order
              })
            });
          }
        });
      });
    });
    </script>';
}

// VIEW: FOOTER SETTINGS
if ($action === 'footer_settings') {

    // Default-Row sicherstellen (idempotent)
    mysqli_query($db, "
        INSERT INTO plugins_footer
            (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
        SELECT
            'footer_text', '', 'footer_description', 0, 0,
            'Klar, modern und responsiv â€“ eine solide Basis fÃ¼r deine Website.',
            '', 0
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM plugins_footer
            WHERE row_type='footer_text' AND section_title='footer_description'
        )
    ");

    $footerText = '';
    $r = mysqli_query($db, "
        SELECT footer_link_name
        FROM plugins_footer
        WHERE row_type='footer_text'
          AND section_title='footer_description'
        LIMIT 1
    ");
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $footerText = (string)$row['footer_link_name'];
    }
    $footerTextByLang = footer_lang_get_all($db, 'footer_text', $languages, $footerText);

    // Default-Row fÃ¼r Template sicherstellen
    mysqli_query($db, "
        INSERT INTO plugins_footer
            (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
        SELECT
            'footer_template', '', 'footer_template', 0, 0,
            'standard',
            '', 0
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM plugins_footer
            WHERE row_type='footer_template' AND section_title='footer_template'
        )
    ");

    $footerTemplate = 'standard';
    $rTpl = mysqli_query($db, "
        SELECT footer_link_name
        FROM plugins_footer
        WHERE row_type='footer_template'
          AND section_title='footer_template'
        LIMIT 1
    ");
    if ($rTpl && ($rowTpl = mysqli_fetch_assoc($rTpl))) {
        $footerTemplate = (string)$rowTpl['footer_link_name'];
    }

    echo '
    <form method="post">

      <style>
      .footer-tpl-preview {
        position: relative;
        width: 100%;
        overflow: hidden;
        border-radius: 8px;
        padding: 10px;
      }
      .footer-tpl-preview::before {
        content:"";
        position:absolute;
        inset:0;
        border:1px solid rgba(255,255,255,.08);
        border-radius:12px;
        pointer-events:none;
      }
      .footer-tpl-preview img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center bottom;
        border-radius: 8px;
        display:block;
      }
      </style>

    <div class="card shadow-sm mt-4">
      <div class="card-header">
        <div class="card-title">
          <i class="bi bi-palette"></i> <span>' . $languageService->get('title_footer_style') . '</span>
          <small class="text-muted">' . $languageService->get('subtitle_templates') . '</small>
        </div>
      </div>

      <div class="card-body">

        <div class="mb-4">
          <div class="row g-3">

            <p>' . $languageService->get('info_footer_templates') . '</p>

            <div class="col-12 col-md-6 col-lg-3">
              <label class="card h-100 p-3" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="fw-semibold">' . $languageService->get('template_standard') . '</div>
                  <input type="radio" name="footer_template" value="standard" '.($footerTemplate==='standard'?'checked':'').'>
                </div>
                <div class="text-muted small mt-2">' . $languageService->get('template_standard_info') . '</div>
                <img class="footer-tpl-preview"
                    src="../includes/plugins/footer/images/prev_footer_standard.png"
                    alt="' . $languageService->get('template_standard_preview') . '">
              </label>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
              <label class="card h-100 p-3" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="fw-semibold">' . $languageService->get('template_simple') . '</div>
                  <input type="radio" name="footer_template" value="simple" '.($footerTemplate==='simple'?'checked':'').'>
                </div>
                <div class="text-muted small mt-2">' . $languageService->get('template_simple_info') . '</div>
                <img class="footer-tpl-preview"
                    src="../includes/plugins/footer/images/prev_footer_simple.png"
                    alt="' . $languageService->get('template_simple_preview') . '">
              </label>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
              <label class="card h-100 p-3" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="fw-semibold">' . $languageService->get('template_agency') . '</div>
                  <input type="radio" name="footer_template" value="agency" '.($footerTemplate==='agency'?'checked':'').'>
                </div>
                <div class="text-muted small mt-2">' . $languageService->get('template_agency_info') . '</div>
                <img class="footer-tpl-preview"
                    src="../includes/plugins/footer/images/prev_footer_agency.png"
                    alt="' . $languageService->get('template_agency_preview') . '">
              </label>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
              <label class="card h-100 p-3" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="fw-semibold">' . $languageService->get('template_modern') . '</div>
                  <input type="radio" name="footer_template" value="modern" '.($footerTemplate==='modern'?'checked':'').'>
                </div>
                <div class="text-muted small mt-2">' . $languageService->get('template_modern_info') . '</div>
                <img class="footer-tpl-preview"
                    src="../includes/plugins/footer/images/prev_footer_modern.png"
                    alt="' . $languageService->get('template_modern_preview') . '">
              </label>
            </div>

          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-4">
      <div class="card-header">
        <div class="card-title">
          <i class="bi bi-card-text"></i> <span>' . $languageService->get('title_footer_text') . '</span>
          <small class="text-muted">' . $languageService->get('subtitle_footer_text') . '</small>
        </div>
      </div>

      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">' . $languageService->get('label_footer_text') . '</label>
          <div class="btn-group btn-group-sm mb-2" id="footer-text-lang-switch">';
            foreach ($languages as $iso => $label) {
                $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                echo '<button type="button" class="btn ' . $activeClass . '" data-lang="'.htmlspecialchars($iso, ENT_QUOTES).'">'.strtoupper(htmlspecialchars($iso, ENT_QUOTES)).'</button>';
            }
    echo '  </div>
          <textarea class="form-control" id="footer_text_main" data-nx-lang-hidden-prefix="footer_text_lang_" data-nx-lang-switch="#footer-text-lang-switch" data-nx-lang-active="#footer_text_active_lang" name="footer_text" rows="3" maxlength="240"
            placeholder="' . $languageService->get('placeholder_footer_text') . '">'.htmlspecialchars((string)($footerTextByLang[$currentLang] ?? ''), ENT_QUOTES).'</textarea>
          <input type="hidden" name="active_lang" id="footer_text_active_lang" value="'.htmlspecialchars($currentLang, ENT_QUOTES).'">';
            foreach ($languages as $iso => $label) {
                echo '<input type="hidden" name="footer_text_lang['.htmlspecialchars($iso, ENT_QUOTES).']" id="footer_text_lang_'.htmlspecialchars($iso, ENT_QUOTES).'" value="'.htmlspecialchars((string)($footerTextByLang[$iso] ?? ''), ENT_QUOTES).'">';
            }
    echo '  <div class="form-text">' . $languageService->get('formtext_footer_text') . '</div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary" name="save_footer_settings">' . $languageService->get('save') . '</button>
        </div>
      </div>
    </div>
</form>';
}

// VIEW: CATEGORY ADD
if ($action === 'category_add') {
    echo '<div class="card shadow-sm mt-4">
      <div class="card-header">
        <div class="card-title">
          <i class="bi bi-plus-circle"></i> <span>' . $languageService->get('title_category') . '</span>
          <small class="text-muted">' . $languageService->get('add') . '</small>
        </div>
      </div>

    <form method="post">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">' . $languageService->get('label_category') . '</label>
          <div class="btn-group btn-group-sm mb-2" id="footer-cat-lang-switch-add">';
            foreach ($languages as $iso => $label) {
                $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                echo '<button type="button" class="btn ' . $activeClass . '" data-lang="'.htmlspecialchars($iso, ENT_QUOTES).'">'.strtoupper(htmlspecialchars($iso, ENT_QUOTES)).'</button>';
            }
    echo '  </div>
          <input class="form-control" id="footer_category_name_main_add" data-nx-lang-hidden-prefix="footer_category_name_add_" data-nx-lang-switch="#footer-cat-lang-switch-add" data-nx-lang-active="#footer_category_active_lang_add" name="new_title" type="text" required>
          <input type="hidden" name="active_lang" id="footer_category_active_lang_add" value="'.htmlspecialchars($currentLang, ENT_QUOTES).'">';
            foreach ($languages as $iso => $label) {
                echo '<input type="hidden" name="category_title_lang['.htmlspecialchars($iso, ENT_QUOTES).']" id="footer_category_name_add_'.htmlspecialchars($iso, ENT_QUOTES).'" value="">';
            }
    echo '
        </div>

        <input type="hidden" name="mode" value="add">
        <button class="btn btn-primary" name="save_category">' . $languageService->get('save') . '</button>
      </div>
    </div>
    </form>';
} 

// VIEW: CATEGORY EDIT
if ($action === 'category_edit' && isset($_GET['key'])) {
    $key = (string)$_GET['key'];
    $keyEsc = mysqli_real_escape_string($db, $key);

    $title = '';
    $resT = mysqli_query(
        $db,
        "SELECT section_title
         FROM plugins_footer
         WHERE row_type='category'
           AND category_key='{$keyEsc}'
         LIMIT 1"
    );

    if ($resT && ($rT = mysqli_fetch_assoc($resT))) {
        $title = (string)$rT['section_title'];
    }

    if ($title === '') {
        nx_alert('warning', 'alert_not_found', false);
        return;
    }

    $titleByLang = footer_category_title_get_all($db, $key, $languages, $title);

    // Kategorie Ã¤ndern
    echo '<div class="row g-3">
            <div class="col-12">
              <form method="post">
                <div class="card shadow-sm mt-4">
                  <div class="card-header">
                    <div class="card-title">
                      <i class="bi bi-pencil-square"></i> <span>' . $languageService->get('title_category') . '</span>
                      <small class="text-muted">' . $languageService->get('edit') . '</small>
                    </div>
                  </div>
                  <div class="card-body">

                    <div class="mb-3">
                      <label class="form-label">' . $languageService->get('label_category') . '</label>
                      <div class="btn-group btn-group-sm mb-2" id="footer-cat-lang-switch-edit">';
                        foreach ($languages as $iso => $label) {
                            $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                            echo '<button type="button" class="btn ' . $activeClass . '" data-lang="'.htmlspecialchars($iso, ENT_QUOTES).'">'.strtoupper(htmlspecialchars($iso, ENT_QUOTES)).'</button>';
                        }
    echo '            </div>
                      <input class="form-control" id="footer_category_name_main_edit" data-nx-lang-hidden-prefix="footer_category_name_edit_" data-nx-lang-switch="#footer-cat-lang-switch-edit" data-nx-lang-active="#footer_category_active_lang_edit" name="new_title" type="text"
                             value="'.htmlspecialchars((string)($titleByLang[$currentLang] ?? ''), ENT_QUOTES).'" required>
                      <input type="hidden" name="active_lang" id="footer_category_active_lang_edit" value="'.htmlspecialchars($currentLang, ENT_QUOTES).'">';
                        foreach ($languages as $iso => $label) {
                            echo '<input type="hidden" name="category_title_lang['.htmlspecialchars($iso, ENT_QUOTES).']" id="footer_category_name_edit_'.htmlspecialchars($iso, ENT_QUOTES).'" value="'.htmlspecialchars((string)($titleByLang[$iso] ?? ''), ENT_QUOTES).'">';
                        }
    echo '
                    </div>

                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="category_key" value="'.htmlspecialchars($key, ENT_QUOTES).'">

                    <div class="d-flex gap-2">
                      <button class="btn btn-primary" name="save_category">' . $languageService->get('save') . '</button>
                    </div>

                  </div>
                </div>
              </form>
            </div>
          </div>';
}

// VIEW: LINK ADD
if ($action === 'link_add') {

    // Kategorien alphabetisch sortieren
    usort($categories, function ($a, $b) {
        return strcasecmp(
            (string)($a['section_title_display'] ?? $a['section_title']),
            (string)($b['section_title_display'] ?? $b['section_title'])
        );
    });

    $prefillKey = trim((string)($_GET['prefill_key'] ?? ''));

    $prefCat = (string)($_GET['cat'] ?? '');
    $prefCatEsc = mysqli_real_escape_string($db, $prefCat);

    $prefTitle = '';
    if ($prefCat !== '') {
        $r = mysqli_query($db, "SELECT section_title FROM plugins_footer WHERE row_type='category' AND category_key='{$prefCatEsc}' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) {
            $prefTitle = (string)$row['section_title'];
        }
        if ($prefTitle === '') {
            $prefCat = '';
        }
    }

    $effectiveKey = ($prefCat !== '') ? $prefCat : $prefillKey;
    $disabledAttr = ($prefCat !== '') ? ' disabled' : '';

    echo '<div class="card shadow-sm mt-4">
      <div class="card-header">
        <div class="card-title">
          <i class="bi bi-box-arrow-up-right"></i> <span>' . $languageService->get('title_links') . '</span>
          <small class="text-muted">' . $languageService->get('add') . '</small>
        </div>
      </div>

    <form method="post">
      <div class="card-body">

        <div class="mb-3">
          <label class="form-label">' . $languageService->get('label_category') . '</label>
          <select class="form-select" name="category_key" required'.$disabledAttr.'>
            <option value="">' . $languageService->get('select_choose') . '</option>';

            foreach ($categories as $cat) {
                $t = (string)($cat['section_title_display'] ?? $cat['section_title']);
                $k = (string)$cat['category_key'];
                $sel = ($effectiveKey !== '' && $effectiveKey === $k) ? ' selected' : '';
                echo '<option value="'.htmlspecialchars($k, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($t, ENT_QUOTES).'</option>';
            }

    echo '</select>';

    if ($prefCat !== '') {
        echo '<input type="hidden" name="category_key" value="'.htmlspecialchars($prefCat, ENT_QUOTES).'">';
    }

    echo '</div>

        <div class="mb-3">
          <label class="form-label">' . $languageService->get('label_linkname') . '</label>
          <div class="btn-group btn-group-sm mb-2" id="footer-link-lang-switch-add">';
            foreach ($languages as $iso => $label) {
                $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                echo '<button type="button" class="btn ' . $activeClass . '" data-lang="'.htmlspecialchars($iso, ENT_QUOTES).'">'.strtoupper(htmlspecialchars($iso, ENT_QUOTES)).'</button>';
            }
    echo '  </div>
          <input class="form-control" id="footer_link_name_main_add" data-nx-lang-hidden-prefix="footer_link_name_add_" data-nx-lang-switch="#footer-link-lang-switch-add" data-nx-lang-active="#footer_active_lang_add" name="footer_link_name" type="text" required>
          <input type="hidden" name="active_lang" id="footer_active_lang_add" value="'.htmlspecialchars($currentLang, ENT_QUOTES).'">';
            foreach ($languages as $iso => $label) {
                echo '<input type="hidden" name="footer_link_name_lang['.htmlspecialchars($iso, ENT_QUOTES).']" id="footer_link_name_add_'.htmlspecialchars($iso, ENT_QUOTES).'" value="">';
            }
    echo '</div>

        <div class="mb-3">
          <label class="form-label">' . $languageService->get('url') . '</label>
          <input class="form-control" name="footer_link_url" type="text" placeholder="index.php?site=imprint" required>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="new_tab" value="1" id="newTabChk">
          <label class="form-check-label" for="newTabChk">' . $languageService->get('label_new_tab') . '</label>
        </div>

        <input type="hidden" name="mode" value="add">
        <input type="hidden" name="back" value="'.htmlspecialchars($backUrl, ENT_QUOTES).'">

        <div class="d-flex gap-2">
          <button class="btn btn-primary" name="save_link">' . $languageService->get('save') . '</button>
        </div>
      </div>
    </form>
    ';
}

// VIEW: LINK EDIT
if ($action === 'link_edit' && isset($_GET['id'])) {
    $id = (int)($_GET['id']);

    $rowRes = mysqli_query($db, "SELECT * FROM plugins_footer WHERE id={$id} AND row_type='link' LIMIT 1");
    $row = $rowRes ? mysqli_fetch_assoc($rowRes) : null;

    if (!$row) {
        nx_alert('warning', 'alert_not_found', false);
    } else {
        $prefKey = '';
        if (!empty($row['category_key'])) {
            $prefKey = (string)$row['category_key'];
        }
        $nameRawEdit = (string)($row['footer_link_name'] ?? '');
        $nameByLangEdit = footer_lang_get_all($db, 'link_name_' . $id, $languages, $nameRawEdit);

        echo '<div class="card shadow-sm mt-4">
                <div class="card-header">
                  <div class="card-title">
                    <i class="bi bi-box-arrow-up-right"></i> <span>' . $languageService->get('title_links') . '</span>
                    <small class="text-muted">' . $languageService->get('edit') . '</small>
                  </div>
                </div>
                <form method="post">
                  <div class="card-body">';

          $lockCategory = (strpos($back, 'action=category_edit') !== false);

          $isLegalCategory = ($prefKey !== '' && $prefKey === md5('Rechtliches'));

          $linkNameForCheck = footer_lang_get($db, 'link_name_' . $id, 'en', (string)$row['footer_link_name']);

          $isCoreProtected = $isLegalCategory
              ? footer_isProtectedLegalLink(
                    'Rechtliches',
                    $linkNameForCheck,
                    (string)$row['footer_link_url']
                )
              : footer_isProtectedLegalLink(
                    (string)$row['section_title'],
                    $linkNameForCheck,
                    (string)$row['footer_link_url']
                );

          echo '<div class="mb-3">
            <label class="form-label">' . $languageService->get('label_category') . '</label>
            <select class="form-select" name="category_key" required'.(($lockCategory || $isCoreProtected) ? ' disabled' : '').'>
              <option value="">' . $languageService->get('select_choose') . '</option>';

          foreach ($categories as $cat) {
              $t = (string)($cat['section_title_display'] ?? $cat['section_title']);
              $k = (string)$cat['category_key'];
              $sel = ($prefKey !== '' && $k === $prefKey) ? ' selected' : '';
              echo '<option value="'.htmlspecialchars($k, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($t, ENT_QUOTES).'</option>';
          }

          echo '</select>';

          if ($lockCategory && $prefKey !== '') {
              echo '<input type="hidden" name="category_key" value="'.htmlspecialchars($prefKey, ENT_QUOTES).'">';
          }

          echo '</div>';

          echo '<div class="mb-3">
              <label class="form-label">' . $languageService->get('label_linkname') . '</label>
              <div class="btn-group btn-group-sm mb-2" id="footer-link-lang-switch-edit">';
                foreach ($languages as $iso => $label) {
                    $activeClass = ($iso === $currentLang) ? 'btn-primary' : 'btn-secondary';
                    echo '<button type="button" class="btn ' . $activeClass . '" data-lang="'.htmlspecialchars($iso, ENT_QUOTES).'">'.strtoupper(htmlspecialchars($iso, ENT_QUOTES)).'</button>';
                }
          echo '</div>
              <input class="form-control" id="footer_link_name_main_edit" data-nx-lang-hidden-prefix="footer_link_name_edit_" data-nx-lang-switch="#footer-link-lang-switch-edit" data-nx-lang-active="#footer_active_lang_edit" name="footer_link_name" type="text" value="'.htmlspecialchars((string)($nameByLangEdit[$currentLang] ?? ''), ENT_QUOTES).'" required>
              <input type="hidden" name="active_lang" id="footer_active_lang_edit" value="'.htmlspecialchars($currentLang, ENT_QUOTES).'">';
                foreach ($languages as $iso => $label) {
                    echo '<input type="hidden" name="footer_link_name_lang['.htmlspecialchars($iso, ENT_QUOTES).']" id="footer_link_name_edit_'.htmlspecialchars($iso, ENT_QUOTES).'" value="'.htmlspecialchars((string)($nameByLangEdit[$iso] ?? ''), ENT_QUOTES).'">';
                }
          echo '</div>

            <div class="mb-3">
              <label class="form-label">' . $languageService->get('url') . '</label>
              <input class="form-control" name="footer_link_url" type="text" value="'.htmlspecialchars((string)$row['footer_link_url'], ENT_QUOTES).'" required'.($isCoreProtected ? ' disabled' : '').'>
              '.($isCoreProtected ? '<div class="form-text">' . $languageService->get('info_url_disabled') . '</div><input type="hidden" name="footer_link_url" value="'.htmlspecialchars((string)$row['footer_link_url'], ENT_QUOTES).'">' : '').'
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="new_tab" value="1" id="newTabChk" '.(!empty($row['new_tab']) ? 'checked' : '').'>
              <label class="form-check-label" for="newTabChk">' . $languageService->get('label_new_tab') . '</label>
            </div>

            <input type="hidden" name="mode" value="edit">
            <input type="hidden" name="id" value="'.$id.'">
            <input type="hidden" name="back" value="'.htmlspecialchars((string)$back, ENT_QUOTES).'">

            <div class="d-flex gap-2">
              <button class="btn btn-primary" name="save_link">' . $languageService->get('save') . '</button>
            </div>

          </div>
        </form>
        ';
    }
}

echo '</div></div>';



