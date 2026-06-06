<?php
declare(strict_types=1);

use nexpell\CMSDatabaseMigration;

return function (CMSDatabaseMigration $m): void {

    $langs = ['de', 'en', 'it'];

    $extractLang = static function (?string $raw, string $lang): string {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }

        $pattern = '/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/si';
        if (preg_match($pattern, $raw, $match)) {
            return trim((string)$match[1]);
        }

        return '';
    };

    $parseMultilang = static function (?string $raw) use ($langs, $extractLang): array {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }

        $out = [];
        foreach ($langs as $lang) {
            $txt = $extractLang($raw, $lang);
            if ($txt !== '') {
                $out[$lang] = $txt;
            }
        }

        if (empty($out)) {
            $out['de'] = $raw;
        }

        return $out;
    };

    $upsertSettingsContent = static function (CMSDatabaseMigration $m, string $contentKey, string $lang, string $content): void {
        $contentKeyEsc = $m->escape($contentKey);
        $langEsc = $m->escape($lang);
        $contentEsc = $m->escape($content);

        $m->runQuery("\n            INSERT INTO settings_content_lang (content_key, language, content, updated_at)\n            VALUES ('{$contentKeyEsc}', '{$langEsc}', '{$contentEsc}', NOW())\n            ON DUPLICATE KEY UPDATE\n                content = IF(content IS NULL OR content = '', VALUES(content), content),\n                updated_at = NOW()\n        ");
    };

    $upsertNavLang = static function (CMSDatabaseMigration $m, string $table, string $contentKey, string $lang, string $content, string $modulname = ''): void {
        $tableEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $contentKeyEsc = $m->escape($contentKey);
        $langEsc = $m->escape($lang);
        $contentEsc = $m->escape($content);
        $modulEsc = $m->escape($modulname);

        $m->runQuery("\n            INSERT INTO `{$tableEsc}` (content_key, language, content, modulname, updated_at)\n            VALUES ('{$contentKeyEsc}', '{$langEsc}', '{$contentEsc}', '{$modulEsc}', NOW())\n            ON DUPLICATE KEY UPDATE\n                content = IF(content IS NULL OR content = '', VALUES(content), content),\n                modulname = IF(modulname IS NULL OR modulname = '', VALUES(modulname), modulname),\n                updated_at = NOW()\n        ");
    };

    $upsertPluginLang = static function (CMSDatabaseMigration $m, string $contentKey, string $lang, string $content, string $modulname = ''): void {
        $contentKeyEsc = $m->escape($contentKey);
        $langEsc = $m->escape($lang);
        $contentEsc = $m->escape($content);
        $modulEsc = $m->escape($modulname);

        $m->runQuery("\n            INSERT INTO settings_plugins_lang (content_key, language, content, modulname, updated_at)\n            VALUES ('{$contentKeyEsc}', '{$langEsc}', '{$contentEsc}', '{$modulEsc}', NOW())\n            ON DUPLICATE KEY UPDATE\n                content = IF(content IS NULL OR content = '', VALUES(content), content),\n                modulname = IF(modulname IS NULL OR modulname = '', VALUES(modulname), modulname),\n                updated_at = NOW()\n        ");
    };

    $upsertFooterLang = static function (CMSDatabaseMigration $m, string $contentKey, string $lang, string $content): void {
        $contentKeyEsc = $m->escape($contentKey);
        $langEsc = $m->escape($lang);
        $contentEsc = $m->escape($content);

        $m->runQuery("\n            INSERT INTO plugins_footer_lang (content_key, language, content, updated_at)\n            VALUES ('{$contentKeyEsc}', '{$langEsc}', '{$contentEsc}', NOW())\n            ON DUPLICATE KEY UPDATE\n                content = IF(content IS NULL OR content = '', VALUES(content), content),\n                updated_at = NOW()\n        ");
    };

    // 1) New multilang content table
    $m->run("\n        CREATE TABLE IF NOT EXISTS settings_content_lang (\n            id INT(11) NOT NULL AUTO_INCREMENT,\n            content_key VARCHAR(191) NOT NULL,\n            language VARCHAR(8) NOT NULL DEFAULT 'de',\n            content MEDIUMTEXT NOT NULL,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            UNIQUE KEY uniq_content_lang (content_key, language),\n            KEY idx_content_key (content_key),\n            KEY idx_language (language)\n        ) ENGINE=InnoDB\n          DEFAULT CHARSET=utf8mb4\n          COLLATE=utf8mb4_unicode_ci\n    ");

    // 2) New dashboard navigation language table
    $m->run("\n        CREATE TABLE IF NOT EXISTS navigation_dashboard_lang (\n            id INT(11) NOT NULL AUTO_INCREMENT,\n            content_key VARCHAR(191) NOT NULL,\n            language VARCHAR(8) NOT NULL DEFAULT 'de',\n            content VARCHAR(255) NOT NULL DEFAULT '',\n            modulname VARCHAR(255) NOT NULL DEFAULT '',\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            UNIQUE KEY uniq_content_lang (content_key, language),\n            KEY idx_modulname (modulname),\n            KEY idx_language (language)\n        ) ENGINE=InnoDB\n          DEFAULT CHARSET=utf8mb4\n          COLLATE=utf8mb4_unicode_ci\n    ");

    // 3) New website navigation language table
    $m->run("\n        CREATE TABLE IF NOT EXISTS navigation_website_lang (\n            id INT(11) NOT NULL AUTO_INCREMENT,\n            content_key VARCHAR(191) NOT NULL,\n            language VARCHAR(8) NOT NULL DEFAULT 'de',\n            content VARCHAR(255) NOT NULL DEFAULT '',\n            modulname VARCHAR(255) NOT NULL DEFAULT '',\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            UNIQUE KEY uniq_content_lang (content_key, language),\n            KEY idx_modulname (modulname),\n            KEY idx_language (language)\n        ) ENGINE=InnoDB\n          DEFAULT CHARSET=utf8mb4\n          COLLATE=utf8mb4_unicode_ci\n    ");

    // 4) Missing tables requested: settings_plugins_lang + plugins_footer + plugins_footer_lang
    $m->run("\n        CREATE TABLE IF NOT EXISTS settings_plugins_lang (\n            id INT(11) NOT NULL AUTO_INCREMENT,\n            content_key VARCHAR(120) NOT NULL,\n            language CHAR(2) NOT NULL,\n            content MEDIUMTEXT NOT NULL,\n            modulname VARCHAR(255) NOT NULL DEFAULT '',\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            UNIQUE KEY uniq_content_lang (content_key, language),\n            KEY idx_content_key (content_key),\n            KEY idx_language (language),\n            KEY idx_modulname (modulname)\n        ) ENGINE=InnoDB\n          DEFAULT CHARSET=utf8mb4\n          COLLATE=utf8mb4_unicode_ci\n    ");

    $m->run("\n        CREATE TABLE IF NOT EXISTS plugins_footer (\n            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,\n            row_type ENUM('category','link','footer_text','footer_template') NOT NULL DEFAULT 'link',\n            category_key VARCHAR(64) NOT NULL DEFAULT '',\n            section_title VARCHAR(255) NOT NULL DEFAULT 'Navigation',\n            section_sort INT(10) UNSIGNED NOT NULL DEFAULT 1,\n            link_sort INT(10) UNSIGNED NOT NULL DEFAULT 1,\n            footer_link_name VARCHAR(255) NOT NULL DEFAULT '',\n            footer_link_url VARCHAR(255) NOT NULL DEFAULT '',\n            new_tab TINYINT(1) NOT NULL DEFAULT 0,\n            PRIMARY KEY (id),\n            KEY idx_section (section_sort, section_title),\n            KEY idx_section_links (section_sort, section_title, link_sort),\n            KEY idx_footer_cat (row_type, category_key),\n            KEY idx_footer_cat_title (row_type, section_title)\n        ) ENGINE=InnoDB\n          DEFAULT CHARSET=utf8mb4\n          COLLATE=utf8mb4_unicode_ci\n    ");

    $m->run("\n        CREATE TABLE IF NOT EXISTS plugins_footer_lang (\n            id INT(11) NOT NULL AUTO_INCREMENT,\n            content_key VARCHAR(80) NOT NULL,\n            language CHAR(2) NOT NULL,\n            content MEDIUMTEXT NOT NULL,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            UNIQUE KEY uniq_content_lang (content_key, language),\n            KEY idx_content_key (content_key),\n            KEY idx_language (language)\n        ) ENGINE=InnoDB\n          DEFAULT CHARSET=utf8mb4\n          COLLATE=utf8mb4_unicode_ci\n    ");

    $m->run("\n        CREATE TABLE IF NOT EXISTS settings_seo_meta_lang (\n            id INT(11) NOT NULL AUTO_INCREMENT,\n            content_key VARCHAR(191) NOT NULL,\n            language VARCHAR(8) NOT NULL DEFAULT 'de',\n            content MEDIUMTEXT NOT NULL,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            UNIQUE KEY uniq_content_lang (content_key, language),\n            KEY idx_content_key (content_key),\n            KEY idx_language (language)\n        ) ENGINE=InnoDB\n          DEFAULT CHARSET=utf8mb4\n          COLLATE=utf8mb4_unicode_ci\n    ");

    // 5) Ensure optional columns exist on pre-created tables
    if ($m->tableExists('navigation_dashboard_lang')) {
        if (!$m->columnExists('navigation_dashboard_lang', 'modulname')) {
            $m->run("ALTER TABLE navigation_dashboard_lang ADD COLUMN modulname VARCHAR(255) NOT NULL DEFAULT '' AFTER content");
        }
        if (!$m->columnExists('navigation_dashboard_lang', 'updated_at')) {
            $m->run("ALTER TABLE navigation_dashboard_lang ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER modulname");
        }
    }

    if ($m->tableExists('navigation_website_lang')) {
        if (!$m->columnExists('navigation_website_lang', 'modulname')) {
            $m->run("ALTER TABLE navigation_website_lang ADD COLUMN modulname VARCHAR(255) NOT NULL DEFAULT '' AFTER content");
        }
        if (!$m->columnExists('navigation_website_lang', 'updated_at')) {
            $m->run("ALTER TABLE navigation_website_lang ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER modulname");
        }
    }

    if ($m->tableExists('navigation_website_sub')) {
        if (!$m->columnExists('navigation_website_sub', 'sort')) {
            $m->run("ALTER TABLE navigation_website_sub ADD COLUMN sort INT(11) NOT NULL DEFAULT 0 AFTER url");
        }

        // Prepare existing data for the new unique(modulname, sort) index.
        // Older installs may contain duplicates like (about, 1), which would
        // make the index creation fail during the update.
        if ($m->columnExists('navigation_website_sub', 'modulname') && $m->columnExists('navigation_website_sub', 'snavID')) {
            $res = $m->query("
                SELECT snavID, modulname, sort
                FROM navigation_website_sub
                ORDER BY modulname ASC, sort ASC, snavID ASC
            ");

            if ($res instanceof mysqli_result) {
                $nextSortByModule = [];

                while ($row = $res->fetch_assoc()) {
                    $snavID = (int)($row['snavID'] ?? 0);
                    $modulname = trim((string)($row['modulname'] ?? ''));
                    if ($snavID <= 0) {
                        continue;
                    }

                    $nextSortByModule[$modulname] = ($nextSortByModule[$modulname] ?? 0) + 1;
                    $newSort = $nextSortByModule[$modulname];
                    $m->run("UPDATE navigation_website_sub SET sort = {$newSort} WHERE snavID = {$snavID}");
                }
            }
        }

        $hasLegacyUnique = false;
        $idxRes = $m->query("SHOW INDEX FROM navigation_website_sub WHERE Key_name = 'unique_modulname'");
        if ($idxRes instanceof mysqli_result && $idxRes->num_rows > 0) {
            $hasLegacyUnique = true;
        }

        $hasNewUnique = false;
        $newIdxRes = $m->query("SHOW INDEX FROM navigation_website_sub WHERE Key_name = 'unique_modulname_sort'");
        if ($newIdxRes instanceof mysqli_result && $newIdxRes->num_rows > 0) {
            $hasNewUnique = true;
        }

        if ($hasLegacyUnique) {
            $m->run("ALTER TABLE navigation_website_sub DROP INDEX unique_modulname");
        }
        if (!$hasNewUnique) {
            $m->run("ALTER TABLE navigation_website_sub ADD UNIQUE KEY unique_modulname_sort (modulname, sort)");
        }
    }

    if ($m->tableExists('settings_plugins') && $m->columnExists('settings_plugins', 'modulname')) {
        $res = $m->query("SELECT pluginID, modulname FROM settings_plugins");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $pluginID = (int)($row['pluginID'] ?? 0);
                $rawModulname = (string)($row['modulname'] ?? '');
                $normalized = strtolower(trim(trim(preg_replace('/\s+/', '', $rawModulname), ',')));
                $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized);

                if ($pluginID > 0 && $normalized !== '' && $normalized !== $rawModulname) {
                    $rawEsc = $m->escape($rawModulname);
                    $normEsc = $m->escape($normalized);
                    $m->run("UPDATE settings_plugins SET modulname = '{$normEsc}' WHERE pluginID = {$pluginID}");
                    if ($m->tableExists('settings_plugins_installed') && $m->columnExists('settings_plugins_installed', 'modulname')) {
                        $m->run("UPDATE settings_plugins_installed SET modulname = '{$normEsc}' WHERE modulname = '{$rawEsc}'");
                    }
                    if ($m->tableExists('navigation_dashboard_links') && $m->columnExists('navigation_dashboard_links', 'modulname')) {
                        $m->run("UPDATE navigation_dashboard_links SET modulname = '{$normEsc}' WHERE modulname = '{$rawEsc}'");
                    }
                    if ($m->tableExists('navigation_website_sub') && $m->columnExists('navigation_website_sub', 'modulname')) {
                        $m->run("UPDATE navigation_website_sub SET modulname = '{$normEsc}' WHERE modulname = '{$rawEsc}'");
                    }
                    if ($m->tableExists('user_role_admin_navi_rights') && $m->columnExists('user_role_admin_navi_rights', 'modulname')) {
                        $m->run("UPDATE user_role_admin_navi_rights SET modulname = '{$normEsc}' WHERE modulname = '{$rawEsc}'");
                    }
                    if ($m->tableExists('settings_widgets') && $m->columnExists('settings_widgets', 'modulname')) {
                        $m->run("UPDATE settings_widgets SET modulname = '{$normEsc}' WHERE modulname = '{$rawEsc}'");
                    }
                    if ($m->tableExists('settings_widgets') && $m->columnExists('settings_widgets', 'plugin')) {
                        $m->run("UPDATE settings_widgets SET plugin = '{$normEsc}' WHERE plugin = '{$rawEsc}'");
                    }
                }
            }
        }
    }

    if ($m->tableExists('settings_content_lang') && !$m->columnExists('settings_content_lang', 'updated_at')) {
        $m->run("ALTER TABLE settings_content_lang ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER content");
    }

    if ($m->tableExists('settings_plugins_lang')) {
        if (!$m->columnExists('settings_plugins_lang', 'modulname')) {
            $m->run("ALTER TABLE settings_plugins_lang ADD COLUMN modulname VARCHAR(255) NOT NULL DEFAULT '' AFTER content");
        }
        if (!$m->columnExists('settings_plugins_lang', 'updated_at')) {
            $m->run("ALTER TABLE settings_plugins_lang ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER modulname");
        }
    }

    if ($m->tableExists('settings_seo_meta_lang') && !$m->columnExists('settings_seo_meta_lang', 'updated_at')) {
        $m->run("ALTER TABLE settings_seo_meta_lang ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER content");
    }

    // 6) Migrate imprint / privacy text from legacy columns
    if ($m->tableExists('settings_imprint') && $m->columnExists('settings_imprint', 'disclaimer')) {
        $res = $m->query("SELECT disclaimer FROM settings_imprint LIMIT 1");
        if ($res instanceof mysqli_result && $row = $res->fetch_assoc()) {
            foreach ($parseMultilang((string)($row['disclaimer'] ?? '')) as $lang => $content) {
                $upsertSettingsContent($m, 'imprint', $lang, $content);
            }
        }
    }

    if ($m->tableExists('settings_privacy_policy') && $m->columnExists('settings_privacy_policy', 'privacy_policy_text')) {
        $res = $m->query("SELECT privacy_policy_text FROM settings_privacy_policy LIMIT 1");
        if ($res instanceof mysqli_result && $row = $res->fetch_assoc()) {
            foreach ($parseMultilang((string)($row['privacy_policy_text'] ?? '')) as $lang => $content) {
                $upsertSettingsContent($m, 'privacy_policy', $lang, $content);
            }
        }
    }

    // 7) Optional content migrations used by 1.0.3.3 modules
    if ($m->tableExists('settings_startpage')) {
        $res = $m->query("SELECT title, startpage_text FROM settings_startpage ORDER BY pageID ASC LIMIT 1");
        if ($res instanceof mysqli_result && $row = $res->fetch_assoc()) {
            foreach ($parseMultilang((string)($row['title'] ?? '')) as $lang => $content) {
                $upsertSettingsContent($m, 'startpage_title', $lang, $content);
            }
            foreach ($parseMultilang((string)($row['startpage_text'] ?? '')) as $lang => $content) {
                $upsertSettingsContent($m, 'startpage', $lang, $content);
            }
        }
    }

    if ($m->tableExists('settings_static')) {
        $titleCol = null;
        foreach (['title', 'headline', 'name'] as $candidate) {
            if ($m->columnExists('settings_static', $candidate)) {
                $titleCol = $candidate;
                break;
            }
        }

        $textCol = null;
        foreach (['text', 'content', 'static_text', 'body'] as $candidate) {
            if ($m->columnExists('settings_static', $candidate)) {
                $textCol = $candidate;
                break;
            }
        }

        if ($textCol !== null) {
            $selectParts = ['staticID'];
            if ($titleCol !== null) {
                $selectParts[] = $titleCol;
            }
            $selectParts[] = $textCol;
            $staticSelect = "SELECT " . implode(', ', $selectParts) . " FROM settings_static";

            $res = $m->query($staticSelect);
        }

        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $staticID = (int)($row['staticID'] ?? 0);
                if ($staticID <= 0) {
                    continue;
                }

                foreach ($parseMultilang((string)($row[$textCol] ?? '')) as $lang => $content) {
                    $upsertSettingsContent($m, 'static_' . $staticID, $lang, $content);
                }

                $titleRaw = $titleCol !== null ? trim((string)($row[$titleCol] ?? '')) : '';
                foreach ($parseMultilang($titleRaw) as $lang => $content) {
                    $upsertSettingsContent($m, 'static_title_' . $staticID, $lang, $content);
                }
            }
        }
    }

    if ($m->tableExists('settings_terms_of_service') && $m->columnExists('settings_terms_of_service', 'terms_of_service_text')) {
        $res = $m->query("SELECT terms_of_service_text FROM settings_terms_of_service ORDER BY id DESC LIMIT 1");
        if ($res instanceof mysqli_result && $row = $res->fetch_assoc()) {
            foreach ($parseMultilang((string)($row['terms_of_service_text'] ?? '')) as $lang => $content) {
                $upsertSettingsContent($m, 'terms_of_service', $lang, $content);
            }
        }
    }

    if (
        $m->tableExists('settings_seo_meta')
        && $m->columnExists('settings_seo_meta', 'site')
        && $m->columnExists('settings_seo_meta', 'language')
        && $m->columnExists('settings_seo_meta', 'title')
        && $m->columnExists('settings_seo_meta', 'description')
    ) {
        $res = $m->query("SELECT site, language, title, description FROM settings_seo_meta");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $site = trim((string)($row['site'] ?? ''));
                $lang = strtolower(trim((string)($row['language'] ?? 'de')));
                if ($site === '') {
                    continue;
                }
                if ($lang === '' || strlen($lang) > 8) {
                    $lang = 'de';
                }

                $title = trim((string)($row['title'] ?? ''));
                if ($title !== '') {
                    $titleKeyEsc = $m->escape('seo_title_' . $site);
                    $langEsc = $m->escape($lang);
                    $titleEsc = $m->escape($title);
                    $m->runQuery("
                        INSERT INTO settings_seo_meta_lang (content_key, language, content, updated_at)
                        VALUES ('{$titleKeyEsc}', '{$langEsc}', '{$titleEsc}', NOW())
                        ON DUPLICATE KEY UPDATE
                            content = IF(content IS NULL OR content = '', VALUES(content), content),
                            updated_at = NOW()
                    ");
                }

                $desc = trim((string)($row['description'] ?? ''));
                if ($desc !== '') {
                    $descKeyEsc = $m->escape('seo_description_' . $site);
                    $langEsc = $m->escape($lang);
                    $descEsc = $m->escape($desc);
                    $m->runQuery("
                        INSERT INTO settings_seo_meta_lang (content_key, language, content, updated_at)
                        VALUES ('{$descKeyEsc}', '{$langEsc}', '{$descEsc}', NOW())
                        ON DUPLICATE KEY UPDATE
                            content = IF(content IS NULL OR content = '', VALUES(content), content),
                            updated_at = NOW()
                    ");
                }
            }
        }
    }

    // 8) Migrate both navigations from legacy [[lang:*]] names
    if ($m->tableExists('navigation_dashboard_categories') && $m->columnExists('navigation_dashboard_categories', 'name')) {
        $res = $m->query("SELECT catID, modulname, name FROM navigation_dashboard_categories");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $catID = (int)($row['catID'] ?? 0);
                if ($catID <= 0) {
                    continue;
                }
                $modul = (string)($row['modulname'] ?? '');
                foreach ($parseMultilang((string)($row['name'] ?? '')) as $lang => $content) {
                    $upsertNavLang($m, 'navigation_dashboard_lang', 'nav_cat_' . $catID, $lang, $content, $modul);
                }
            }
        }
    }

    if ($m->tableExists('navigation_dashboard_links') && $m->columnExists('navigation_dashboard_links', 'name')) {
        $res = $m->query("SELECT linkID, modulname, name FROM navigation_dashboard_links");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $linkID = (int)($row['linkID'] ?? 0);
                if ($linkID <= 0) {
                    continue;
                }
                $modul = (string)($row['modulname'] ?? '');
                foreach ($parseMultilang((string)($row['name'] ?? '')) as $lang => $content) {
                    $upsertNavLang($m, 'navigation_dashboard_lang', 'nav_link_' . $linkID, $lang, $content, $modul);
                }
            }
        }
    }

    if ($m->tableExists('navigation_website_main') && $m->columnExists('navigation_website_main', 'name')) {
        $res = $m->query("SELECT mnavID, modulname, name FROM navigation_website_main");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $mnavID = (int)($row['mnavID'] ?? 0);
                if ($mnavID <= 0) {
                    continue;
                }
                $modul = (string)($row['modulname'] ?? '');
                foreach ($parseMultilang((string)($row['name'] ?? '')) as $lang => $content) {
                    $upsertNavLang($m, 'navigation_website_lang', 'nav_main_' . $mnavID, $lang, $content, $modul);
                }
            }
        }
    }

    if ($m->tableExists('navigation_website_sub') && $m->columnExists('navigation_website_sub', 'name')) {
        $res = $m->query("SELECT snavID, modulname, name FROM navigation_website_sub");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $snavID = (int)($row['snavID'] ?? 0);
                if ($snavID <= 0) {
                    continue;
                }
                $modul = (string)($row['modulname'] ?? '');
                foreach ($parseMultilang((string)($row['name'] ?? '')) as $lang => $content) {
                    $upsertNavLang($m, 'navigation_website_lang', 'nav_sub_' . $snavID, $lang, $content, $modul);
                }
            }
        }
    }

    // 8a) Backfill missing default website navigation labels for installs where legacy name
    // columns were already dropped before the migration copied their contents.
    if ($m->tableExists('navigation_website_main') && $m->tableExists('navigation_website_lang')) {
        $defaultWebsiteNav = [
            'nav_home' => [
                'de' => 'Aktuelles',
                'en' => 'News',
                'it' => 'Notizie',
            ],
            'nav_about' => [
                'de' => 'Über uns',
                'en' => 'About Us',
                'it' => 'Chi siamo',
            ],
            'nav_community' => [
                'de' => 'COMMUNITY',
                'en' => 'COMMUNITY',
                'it' => 'COMMUNITY',
            ],
            'nav_media' => [
                'de' => 'MEDIEN',
                'en' => 'MEDIA',
                'it' => 'MEDIA',
            ],
            'nav_service' => [
                'de' => 'Service',
                'en' => 'Service',
                'it' => 'Servizio',
            ],
            'nav_network' => [
                'de' => 'Netzwerk',
                'en' => 'Network',
                'it' => 'Rete',
            ],
        ];

        $res = $m->query("SELECT mnavID, modulname FROM navigation_website_main");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $mnavID = (int)($row['mnavID'] ?? 0);
                $modul = trim((string)($row['modulname'] ?? ''));

                if ($mnavID <= 0 || !isset($defaultWebsiteNav[$modul])) {
                    continue;
                }

                foreach ($defaultWebsiteNav[$modul] as $lang => $content) {
                    $upsertNavLang($m, 'navigation_website_lang', 'nav_main_' . $mnavID, $lang, $content, $modul);
                }
            }
        }
    }

    // 8b) Remove deprecated legacy "name" columns only after navigation migration ran
    foreach ([
        'navigation_dashboard_categories',
        'navigation_dashboard_links',
        'navigation_website_main',
        'navigation_website_sub'
    ] as $legacyNavTable) {
        if ($m->tableExists($legacyNavTable) && $m->columnExists($legacyNavTable, 'name')) {
            $m->run("ALTER TABLE `{$legacyNavTable}` DROP COLUMN `name`");
        }
    }

    // 9) Migrate settings_plugins -> settings_plugins_lang
    if ($m->tableExists('settings_plugins')) {
        $hasModulname = $m->columnExists('settings_plugins', 'modulname');
        $hasName = $m->columnExists('settings_plugins', 'name');
        $hasInfo = $m->columnExists('settings_plugins', 'info');

        if ($hasModulname && ($hasName || $hasInfo)) {
            $selectParts = ['modulname'];
            if ($hasName) {
                $selectParts[] = 'name';
            }
            if ($hasInfo) {
                $selectParts[] = 'info';
            }

            $res = $m->query("SELECT " . implode(', ', $selectParts) . " FROM settings_plugins");
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $modul = trim((string)($row['modulname'] ?? ''));
                    if ($modul === '') {
                        continue;
                    }

                    if ($hasName) {
                        $nameMap = $parseMultilang((string)($row['name'] ?? ''));
                        foreach ($nameMap as $lang => $content) {
                            $upsertPluginLang($m, 'plugin_name_' . $modul, $lang, $content, $modul);
                        }
                    }

                    if ($hasInfo) {
                        $infoMap = $parseMultilang((string)($row['info'] ?? ''));
                        foreach ($infoMap as $lang => $content) {
                            $upsertPluginLang($m, 'plugin_info_' . $modul, $lang, $content, $modul);
                        }
                    }
                }
            }
        }
    }

    // 10) Migrate footer link names/text -> plugins_footer_lang
    if ($m->tableExists('plugins_footer_easy')) {
        $res = $m->query("SELECT link_number, copyright_link_name FROM plugins_footer_easy");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $num = (int)($row['link_number'] ?? 0);
                if ($num <= 0) {
                    continue;
                }
                $nameRaw = (string)($row['copyright_link_name'] ?? '');
                foreach ($parseMultilang($nameRaw) as $lang => $content) {
                    $upsertFooterLang($m, 'link_name_' . $num, $lang, $content);
                }
            }
        }
    }

    if ($m->tableExists('plugins_footer')) {
        // Resolve schema dynamically to avoid hard failure on mixed/legacy footer schemas.
        $cols = [];
        $colRes = $m->query("SHOW COLUMNS FROM plugins_footer");
        if ($colRes instanceof mysqli_result) {
            while ($c = $colRes->fetch_assoc()) {
                $cols[] = (string)($c['Field'] ?? '');
            }
        }

        $hasId = in_array('id', $cols, true);
        $hasRowType = in_array('row_type', $cols, true);
        $hasSectionTitle = in_array('section_title', $cols, true);
        $hasFooterLinkName = in_array('footer_link_name', $cols, true);
        $hasLinkNumber = in_array('link_number', $cols, true);
        $hasCopyrightLinkName = in_array('copyright_link_name', $cols, true);

        if ($hasId && $hasRowType && $hasSectionTitle && $hasFooterLinkName) {
            $res = $m->query("SELECT id, row_type, section_title, footer_link_name FROM plugins_footer");
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $id = (int)($row['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }

                    $rowType = (string)($row['row_type'] ?? '');
                    $section = (string)($row['section_title'] ?? '');
                    $nameRaw = (string)($row['footer_link_name'] ?? '');

                    if ($rowType === 'link') {
                        foreach ($parseMultilang($nameRaw) as $lang => $content) {
                            $upsertFooterLang($m, 'link_name_' . $id, $lang, $content);
                        }
                    }

                    if ($rowType === 'footer_text' && $section === 'footer_description') {
                        foreach ($parseMultilang($nameRaw) as $lang => $content) {
                            $upsertFooterLang($m, 'footer_text', $lang, $content);
                        }
                    }
                }
            }
        } elseif ($hasLinkNumber && $hasCopyrightLinkName) {
            // Legacy schema fallback (same structure as plugins_footer_easy).
            $hasLegacyId = in_array('id', $cols, true);
            $selectLegacy = $hasLegacyId
                ? "SELECT id, link_number, copyright_link_name FROM plugins_footer"
                : "SELECT link_number, copyright_link_name FROM plugins_footer";
            $res = $m->query($selectLegacy);
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $num = (int)($row['link_number'] ?? 0);
                    if ($num <= 0) {
                        continue;
                    }
                    $id = (int)($row['id'] ?? 0);
                    $keySuffix = ($id > 0) ? $id : $num;
                    $nameRaw = (string)($row['copyright_link_name'] ?? '');
                    foreach ($parseMultilang($nameRaw) as $lang => $content) {
                        $upsertFooterLang($m, 'link_name_' . $keySuffix, $lang, $content);
                    }
                }
            }
        }
    }

    // Ensure footer defaults exist on modern schema.
    if (
        $m->tableExists('plugins_footer')
        && $m->columnExists('plugins_footer', 'row_type')
        && $m->columnExists('plugins_footer', 'category_key')
        && $m->columnExists('plugins_footer', 'section_title')
        && $m->columnExists('plugins_footer', 'section_sort')
        && $m->columnExists('plugins_footer', 'link_sort')
        && $m->columnExists('plugins_footer', 'footer_link_name')
        && $m->columnExists('plugins_footer', 'footer_link_url')
        && $m->columnExists('plugins_footer', 'new_tab')
    ) {
        $catLegal = '97cef6e0a43f670b6b06577a5530d1a4';
        $catLegalEsc = $m->escape($catLegal);
        $catNav = '846495f9ceed11accf8879f555936a7d';
        $catNavEsc = $m->escape($catNav);

        $seedCount = 0;
        $seedCountRes = $m->query("SELECT COUNT(*) AS cnt FROM plugins_footer");
        if ($seedCountRes instanceof mysqli_result && $seedRow = $seedCountRes->fetch_assoc()) {
            $seedCount = (int)($seedRow['cnt'] ?? 0);
        }
        if ($seedCount === 0) {
            $m->runQuery("
                INSERT INTO `plugins_footer` (`id`, `row_type`, `category_key`, `section_title`, `section_sort`, `link_sort`, `footer_link_name`, `footer_link_url`, `new_tab`) VALUES
                (1, 'category', '97cef6e0a43f670b6b06577a5530d1a4', 'Legal', 2, 1, '', '', 0),
                (2, 'category', '846495f9ceed11accf8879f555936a7d', 'Navigation', 1, 1, '', '', 0),
                (3, 'link', '97cef6e0a43f670b6b06577a5530d1a4', 'Rechtliches', 2, 1, '', 'index.php?site=privacy_policy', 0),
                (4, 'link', '97cef6e0a43f670b6b06577a5530d1a4', 'Rechtliches', 2, 2, '', 'index.php?site=imprint', 0),
                (5, 'link', '97cef6e0a43f670b6b06577a5530d1a4', 'Rechtliches', 2, 3, '', 'index.php?site=terms_of_service', 0),
                (6, 'link', '846495f9ceed11accf8879f555936a7d', 'Navigation', 1, 1, '', 'index.php?site=contact', 0),
                (7, 'footer_text', '', 'footer_description', 0, 0, '', '', 0),
                (8, 'footer_template', '', 'footer_template', 1, 1, 'standard', '', 0)
            ");
        }

        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'category', '{$catLegalEsc}', 'Legal', 2, 1, '', '', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='category' AND category_key='{$catLegalEsc}'
            )
        ");

        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'category', '{$catNavEsc}', 'Navigation', 1, 1, '', '', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='category' AND category_key='{$catNavEsc}'
            )
        ");

        // Legacy links often have empty category_key; normalize by canonical URLs.
        $m->run("
            UPDATE plugins_footer
            SET category_key='{$catLegalEsc}', section_title='Rechtliches', section_sort=2
            WHERE row_type='link'
              AND footer_link_url IN ('index.php?site=imprint', 'index.php?site=privacy_policy')
        ");

        $m->run("
            UPDATE plugins_footer
            SET category_key='{$catNavEsc}', section_title='Navigation', section_sort=1
            WHERE row_type='link'
              AND footer_link_url='index.php?site=contact'
        ");
        $m->run("
            UPDATE plugins_footer
            SET category_key='{$catLegalEsc}', section_title='Rechtliches', section_sort=2
            WHERE row_type='link'
              AND footer_link_url='index.php?site=terms_of_service'
        ");

        // Remove empty legacy placeholder links.
        if ($m->columnExists('plugins_footer', 'copyright_link')) {
            $m->run("
                DELETE FROM plugins_footer
                WHERE row_type='link'
                  AND (footer_link_url='' OR footer_link_url IS NULL)
                  AND (copyright_link='' OR copyright_link IS NULL)
            ");
        } else {
            $m->run("
                DELETE FROM plugins_footer
                WHERE row_type='link'
                  AND (footer_link_url='' OR footer_link_url IS NULL)
                  AND (footer_link_name='' OR footer_link_name IS NULL)
            ");
        }

        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'link', '{$catLegalEsc}', 'Rechtliches', 2, 1, '', 'index.php?site=privacy_policy', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='link' AND footer_link_url='index.php?site=privacy_policy'
            )
        ");
        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'link', '{$catLegalEsc}', 'Rechtliches', 2, 2, '', 'index.php?site=imprint', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='link' AND footer_link_url='index.php?site=imprint'
            )
        ");
        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'link', '{$catLegalEsc}', 'Rechtliches', 2, 3, '', 'index.php?site=terms_of_service', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='link' AND footer_link_url='index.php?site=terms_of_service'
            )
        ");
        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'link', '{$catNavEsc}', 'Navigation', 1, 1, '', 'index.php?site=contact', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='link' AND footer_link_url='index.php?site=contact'
            )
        ");
        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'footer_text', '', 'footer_description', 0, 0, '', '', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='footer_text' AND section_title='footer_description'
            )
        ");
        $m->runQuery("
            INSERT INTO plugins_footer
                (row_type, category_key, section_title, section_sort, link_sort, footer_link_name, footer_link_url, new_tab)
            SELECT 'footer_template', '', 'footer_template', 1, 1, 'standard', '', 0
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM plugins_footer
                WHERE row_type='footer_template' AND section_title='footer_template'
            )
        ");

        // Ensure multilingual defaults for core footer links in plugins_footer_lang.
        $privacyId = 0;
        $privacyRes = $m->query("
            SELECT id
            FROM plugins_footer
            WHERE row_type='link' AND footer_link_url='index.php?site=privacy_policy'
            ORDER BY id ASC
            LIMIT 1
        ");
        if ($privacyRes instanceof mysqli_result && $row = $privacyRes->fetch_assoc()) {
            $privacyId = (int)($row['id'] ?? 0);
        }
        if ($privacyId > 0) {
            $upsertFooterLang($m, 'link_name_' . $privacyId, 'de', 'Datenschutz');
            $upsertFooterLang($m, 'link_name_' . $privacyId, 'en', 'Privacy Policy');
            $upsertFooterLang($m, 'link_name_' . $privacyId, 'it', 'Informativa sulla Privacy');
        }

        $imprintId = 0;
        $imprintRes = $m->query("
            SELECT id
            FROM plugins_footer
            WHERE row_type='link' AND footer_link_url='index.php?site=imprint'
            ORDER BY id ASC
            LIMIT 1
        ");
        if ($imprintRes instanceof mysqli_result && $row = $imprintRes->fetch_assoc()) {
            $imprintId = (int)($row['id'] ?? 0);
        }
        if ($imprintId > 0) {
            $upsertFooterLang($m, 'link_name_' . $imprintId, 'de', 'Impressum');
            $upsertFooterLang($m, 'link_name_' . $imprintId, 'en', 'Imprint');
            $upsertFooterLang($m, 'link_name_' . $imprintId, 'it', 'Impronta Editoriale');
        }

        $contactId = 0;
        $contactRes = $m->query("
            SELECT id
            FROM plugins_footer
            WHERE row_type='link' AND footer_link_url='index.php?site=contact'
            ORDER BY id ASC
            LIMIT 1
        ");
        if ($contactRes instanceof mysqli_result && $row = $contactRes->fetch_assoc()) {
            $contactId = (int)($row['id'] ?? 0);
        }
        if ($contactId > 0) {
            $upsertFooterLang($m, 'link_name_' . $contactId, 'de', 'Kontakt');
            $upsertFooterLang($m, 'link_name_' . $contactId, 'en', 'Contact');
            $upsertFooterLang($m, 'link_name_' . $contactId, 'it', 'Contatti');
        }

        $termsId = 0;
        $termsRes = $m->query("
            SELECT id
            FROM plugins_footer
            WHERE row_type='link' AND footer_link_url='index.php?site=terms_of_service'
            ORDER BY id ASC
            LIMIT 1
        ");
        if ($termsRes instanceof mysqli_result && $row = $termsRes->fetch_assoc()) {
            $termsId = (int)($row['id'] ?? 0);
        }
        if ($termsId > 0) {
            $upsertFooterLang($m, 'link_name_' . $termsId, 'de', 'Nutzungsbedinungen');
            $upsertFooterLang($m, 'link_name_' . $termsId, 'en', 'Terms of Use');
            $upsertFooterLang($m, 'link_name_' . $termsId, 'it', 'Termini di utilizzo');
        }

        $upsertFooterLang($m, 'footer_text', 'de', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam.');
        $upsertFooterLang($m, 'footer_text', 'en', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam.');
        $upsertFooterLang($m, 'footer_text', 'it', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam.');

        // Normalize legacy inline text fields: move names to plugins_footer_lang, then clear legacy fields.
        $hasId = $m->columnExists('plugins_footer', 'id');
        $hasFooterLinkName = $m->columnExists('plugins_footer', 'footer_link_name');
        $hasCopyrightLinkName = $m->columnExists('plugins_footer', 'copyright_link_name');
        $hasLinkNumber = $m->columnExists('plugins_footer', 'link_number');
        $hasCopyrightLink = $m->columnExists('plugins_footer', 'copyright_link');

        if ($hasId && ($hasFooterLinkName || $hasCopyrightLinkName)) {
            $nameCols = [];
            if ($hasFooterLinkName) {
                $nameCols[] = 'footer_link_name';
            }
            if ($hasCopyrightLinkName) {
                $nameCols[] = 'copyright_link_name';
            }
            $selectParts = array_merge(['id', 'row_type'], $nameCols);
            $res = $m->query("SELECT " . implode(', ', $selectParts) . " FROM plugins_footer WHERE row_type='link'");
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $id = (int)($row['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $raw = '';
                    if ($hasFooterLinkName) {
                        $raw = trim((string)($row['footer_link_name'] ?? ''));
                    }
                    if ($raw === '' && $hasCopyrightLinkName) {
                        $raw = trim((string)($row['copyright_link_name'] ?? ''));
                    }
                    if ($raw === '') {
                        continue;
                    }
                    foreach ($parseMultilang($raw) as $lang => $content) {
                        $upsertFooterLang($m, 'link_name_' . $id, $lang, $content);
                    }
                }
            }
        }

        if ($hasFooterLinkName) {
            $m->run("UPDATE plugins_footer SET footer_link_name='' WHERE row_type='link'");
        }
        if ($hasLinkNumber) {
            $m->run("UPDATE plugins_footer SET link_number=0");
        }
        if ($hasCopyrightLinkName) {
            $m->run("UPDATE plugins_footer SET copyright_link_name=''");
        }
        if ($hasCopyrightLink) {
            $m->run("UPDATE plugins_footer SET copyright_link=''");
        }

        // Category titles are multilingual via plugins_footer_lang (cat_title_{category_key}).
        $catRes = $m->query("SELECT category_key, section_title FROM plugins_footer WHERE row_type='category'");
        if ($catRes instanceof mysqli_result) {
            while ($cat = $catRes->fetch_assoc()) {
                $catKey = trim((string)($cat['category_key'] ?? ''));
                if ($catKey === '') {
                    continue;
                }
                $rawTitle = trim((string)($cat['section_title'] ?? ''));
                if ($rawTitle === '') {
                    continue;
                }
                foreach ($parseMultilang($rawTitle) as $lang => $content) {
                    $upsertFooterLang($m, 'cat_title_' . $catKey, $lang, $content);
                }
            }
        }
    }

    // 11) Cleanup old schema parts after data migration
    if (
        $m->tableExists('settings_plugins')
        && $m->columnExists('settings_plugins', 'modulname')
        && $m->columnExists('settings_plugins', 'status_display')
        && $m->columnExists('settings_plugins', 'plugin_display')
        && $m->columnExists('settings_plugins', 'widget_display')
    ) {
        // Footer module rename fallback: footer_easy -> footer (if target not already present).
        $footerExists = false;
        $footerRes = $m->query("SELECT pluginID FROM settings_plugins WHERE modulname = 'footer' LIMIT 1");
        if ($footerRes instanceof mysqli_result && $footerRes->num_rows > 0) {
            $footerExists = true;
        }
        if (!$footerExists) {
            $m->run("UPDATE settings_plugins SET modulname = 'footer' WHERE modulname = 'footer_easy'");
        }
        $m->run("UPDATE settings_plugins SET modulname = 'footer' WHERE modulname = ', footer, '");

        $m->run("
            UPDATE settings_plugins
            SET status_display = 0,
                plugin_display = 0,
                widget_display = 1
            WHERE modulname IN ('edit_profile', 'navigation', 'footer_easy', 'footer', ', footer, ')
        ");

        if ($m->columnExists('settings_plugins', 'admin_file')) {
            $m->run("
                UPDATE settings_plugins
                SET admin_file = 'admin_footer'
                WHERE admin_file IN ('admin_footer_easy', 'footer_easy')
            ");
        }

        if ($m->columnExists('settings_plugins', 'path')) {
            $m->run("
                UPDATE settings_plugins
                SET path = 'includes/plugins/footer/'
                WHERE path IN ('includes/plugins/footer_easy/', 'includes/plugins/footer_easy')
            ");
        }
    }

    // Ensure footer plugin language rows match 1.0.3.3 data set.
    $m->runQuery("
        DELETE FROM settings_plugins_lang
        WHERE content_key IN ('plugin_name_footer_easy', 'plugin_info_footer_easy')
    ");

    $upsertPluginLang($m, 'plugin_name_footer', 'de', 'Footer', 'footer_easy');
    $upsertPluginLang($m, 'plugin_info_footer', 'de', 'Mit diesem Plugin könnt ihr einen neuen Footer anzeigen lassen.', 'footer');
    $upsertPluginLang($m, 'plugin_info_footer', 'en', 'With this plugin you can have a new Footer displayed.', 'footer');
    $upsertPluginLang($m, 'plugin_info_footer', 'it', 'Con questo plugin puoi visualizzare un nuovo piè di pagina.', 'footer');

    if ($m->tableExists('settings_plugins')) {
        if ($m->columnExists('settings_plugins', 'name')) {
            $m->run("ALTER TABLE settings_plugins DROP COLUMN name");
        }
        if ($m->columnExists('settings_plugins', 'info')) {
            $m->run("ALTER TABLE settings_plugins DROP COLUMN info");
        }
    }

    if ($m->tableExists('settings_privacy_policy')) {
        $m->run("DROP TABLE settings_privacy_policy");
    }

    if ($m->tableExists('settings_terms_of_service')) {
        $m->run("DROP TABLE settings_terms_of_service");
    }

    if ($m->tableExists('settings_seo_meta')) {
        $m->run("DROP TABLE settings_seo_meta");
    }

    if ($m->tableExists('settings_startpage')) {
        $m->run("DROP TABLE settings_startpage");
    }

    if ($m->tableExists('plugins_footer_easy')) {
        $m->run("DROP TABLE plugins_footer_easy");
    }

    if ($m->tableExists('settings_static')) {
        if ($m->columnExists('settings_static', 'title')) {
            $m->run("ALTER TABLE settings_static DROP COLUMN title");
        }
        if ($m->columnExists('settings_static', 'content')) {
            $m->run("ALTER TABLE settings_static DROP COLUMN content");
        }
        if ($m->columnExists('settings_static', 'editor')) {
            $m->run("ALTER TABLE settings_static DROP COLUMN editor");
        }
    }

    // 12) DB hotfixes for admin navigation and role assignments
    if ($m->tableExists('navigation_dashboard_links') && $m->columnExists('navigation_dashboard_links', 'modulname')) {
        $designCatID = 0;
        if ($m->tableExists('navigation_dashboard_categories') && $m->columnExists('navigation_dashboard_categories', 'modulname')) {
            $catRes = $m->query("SELECT catID FROM navigation_dashboard_categories WHERE modulname = 'cat_design' LIMIT 1");
            if ($catRes instanceof mysqli_result && $row = $catRes->fetch_assoc()) {
                $designCatID = (int)($row['catID'] ?? 0);
            }
            if ($designCatID <= 0) {
                // Legacy fallback for older category key.
                $catRes = $m->query("SELECT catID FROM navigation_dashboard_categories WHERE modulname = 'ac_theme' LIMIT 1");
                if ($catRes instanceof mysqli_result && $row = $catRes->fetch_assoc()) {
                    $designCatID = (int)($row['catID'] ?? 0);
                }
            }
        }

        if ($m->columnExists('navigation_dashboard_links', 'url')) {
            $m->run("
                UPDATE navigation_dashboard_links
                SET modulname = 'footer',
                    url = 'admincenter.php?site=admin_footer'
                WHERE modulname IN ('footer_easy', 'admin_footer_easy', 'plugin_footer', 'admin_footer')
                   OR url IN ('admincenter.php?site=admin_footer_easy', 'admincenter.php?site=admin_footer')
            ");
        } else {
            $m->run("
                UPDATE navigation_dashboard_links
                SET modulname = 'footer'
                WHERE modulname IN ('footer_easy', 'admin_footer_easy', 'plugin_footer', 'admin_footer')
            ");
        }

        $termLinkExists = false;
        $termLinkRes = $m->query("SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'ac_terms_of_service' LIMIT 1");
        if ($termLinkRes instanceof mysqli_result && $termLinkRes->num_rows > 0) {
            $termLinkExists = true;
        }

        if (!$termLinkExists && $m->tableExists('navigation_dashboard_categories')) {
            $catID = 0;
            if ($designCatID > 0) {
                $catID = $designCatID;
            }
            if ($catID <= 0) {
                $catRes = $m->query("SELECT catID FROM navigation_dashboard_categories ORDER BY sort ASC, catID ASC LIMIT 1");
                if ($catRes instanceof mysqli_result && $row = $catRes->fetch_assoc()) {
                    $catID = (int)($row['catID'] ?? 0);
                }
            }

            if ($catID > 0) {
                $sort = 1;
                if ($m->columnExists('navigation_dashboard_links', 'sort')) {
                    $sortRes = $m->query("SELECT COALESCE(MAX(sort), 0) + 1 AS nextSort FROM navigation_dashboard_links WHERE catID = {$catID}");
                    if ($sortRes instanceof mysqli_result && $row = $sortRes->fetch_assoc()) {
                        $sort = (int)($row['nextSort'] ?? 1);
                        if ($sort <= 0) {
                            $sort = 1;
                        }
                    }

                    // Place Terms of Service directly below Privacy Policy when available.
                    $privacySortRes = $m->query("SELECT sort FROM navigation_dashboard_links WHERE modulname = 'ac_privacy_policy' LIMIT 1");
                    if ($privacySortRes instanceof mysqli_result && $row = $privacySortRes->fetch_assoc()) {
                        $privacySort = (int)($row['sort'] ?? 0);
                        if ($privacySort > 0) {
                            $sort = $privacySort + 1;
                        }
                    }
                }

                if ($m->columnExists('navigation_dashboard_links', 'name') && $m->columnExists('navigation_dashboard_links', 'url') && $m->columnExists('navigation_dashboard_links', 'sort')) {
                    $m->runQuery("
                        INSERT INTO navigation_dashboard_links (catID, modulname, name, url, sort)
                        VALUES ('{$catID}', 'ac_terms_of_service', '[[lang:de]]Nutzungsbedingungen[[lang:en]]Terms and Conditions[[lang:it]]Termini e condizioni', 'admincenter.php?site=settings_terms_of_service', '{$sort}')
                    ");
                } elseif ($m->columnExists('navigation_dashboard_links', 'content_key') && $m->columnExists('navigation_dashboard_links', 'url') && $m->columnExists('navigation_dashboard_links', 'sort')) {
                    $m->runQuery("
                        INSERT INTO navigation_dashboard_links (catID, modulname, content_key, url, sort)
                        VALUES ('{$catID}', 'ac_terms_of_service', 'ac_terms_of_service', 'admincenter.php?site=settings_terms_of_service', '{$sort}')
                    ");
                }
            }
        }

        $termLinkID = 0;
        $termLinkRes = $m->query("SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'ac_terms_of_service' ORDER BY linkID DESC LIMIT 1");
        if ($termLinkRes instanceof mysqli_result && $row = $termLinkRes->fetch_assoc()) {
            $termLinkID = (int)($row['linkID'] ?? 0);
        }
        if ($termLinkID > 0 && $designCatID > 0 && $m->columnExists('navigation_dashboard_links', 'catID')) {
            $m->run("UPDATE navigation_dashboard_links SET catID = {$designCatID} WHERE linkID = {$termLinkID}");
        }
        if ($termLinkID > 0 && $m->tableExists('navigation_dashboard_lang')) {
            $upsertNavLang($m, 'navigation_dashboard_lang', 'nav_link_' . $termLinkID, 'de', 'Nutzungsbedingungen', 'ac_terms_of_service');
            $upsertNavLang($m, 'navigation_dashboard_lang', 'nav_link_' . $termLinkID, 'en', 'Terms and Conditions', 'ac_terms_of_service');
            $upsertNavLang($m, 'navigation_dashboard_lang', 'nav_link_' . $termLinkID, 'it', 'Termini e condizioni', 'ac_terms_of_service');
        }

        // Ensure ordering under privacy policy for existing link as well.
        if ($termLinkID > 0 && $m->columnExists('navigation_dashboard_links', 'sort')) {
            $privacySort = 0;
            $privacySortRes = $m->query("SELECT sort FROM navigation_dashboard_links WHERE modulname = 'ac_privacy_policy' LIMIT 1");
            if ($privacySortRes instanceof mysqli_result && $row = $privacySortRes->fetch_assoc()) {
                $privacySort = (int)($row['sort'] ?? 0);
            }
            if ($privacySort > 0) {
                $targetSort = $privacySort + 1;
                $m->run("UPDATE navigation_dashboard_links SET sort = {$targetSort} WHERE linkID = {$termLinkID}");
            }
        }

        // Short label for user roles in admin navigation (de/en/it).
        $userRolesLinkID = 0;
        $userRolesRes = $m->query("SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'ac_user_roles' ORDER BY linkID DESC LIMIT 1");
        if ($userRolesRes instanceof mysqli_result && $row = $userRolesRes->fetch_assoc()) {
            $userRolesLinkID = (int)($row['linkID'] ?? 0);
        }
        if ($userRolesLinkID > 0 && $m->tableExists('navigation_dashboard_lang')) {
            // Force update so legacy labels are replaced in all languages.
            $userRolesKeyEsc = $m->escape('nav_link_' . $userRolesLinkID);
            $userRolesModulEsc = $m->escape('ac_user_roles');
            foreach (['de' => 'Benutzer und Rollen', 'en' => 'Users and Roles', 'it' => 'Utenti e ruoli'] as $lang => $label) {
                $langEsc = $m->escape($lang);
                $labelEsc = $m->escape($label);
                $m->runQuery("
                    INSERT INTO navigation_dashboard_lang (content_key, language, content, modulname, updated_at)
                    VALUES ('{$userRolesKeyEsc}', '{$langEsc}', '{$labelEsc}', '{$userRolesModulEsc}', NOW())
                    ON DUPLICATE KEY UPDATE
                        content = VALUES(content),
                        modulname = VALUES(modulname),
                        updated_at = NOW()
                ");
            }
        }

        // Short label for footer settings in admin navigation (de/en/it).
        $footerLinkID = 0;
        $footerRes = $m->query("SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'footer' ORDER BY linkID DESC LIMIT 1");
        if ($footerRes instanceof mysqli_result && $row = $footerRes->fetch_assoc()) {
            $footerLinkID = (int)($row['linkID'] ?? 0);
        }

        if ($footerLinkID <= 0 && $m->tableExists('navigation_dashboard_categories')) {
            $catID = 0;
            if ($designCatID > 0) {
                $catID = $designCatID;
            } else {
                $catRes = $m->query("SELECT catID FROM navigation_dashboard_categories ORDER BY sort ASC, catID ASC LIMIT 1");
                if ($catRes instanceof mysqli_result && $row = $catRes->fetch_assoc()) {
                    $catID = (int)($row['catID'] ?? 0);
                }
            }

            if ($catID > 0 && $m->columnExists('navigation_dashboard_links', 'url')) {
                $sort = 1;
                if ($m->columnExists('navigation_dashboard_links', 'sort')) {
                    $sortRes = $m->query("SELECT COALESCE(MAX(sort), 0) + 1 AS nextSort FROM navigation_dashboard_links WHERE catID = {$catID}");
                    if ($sortRes instanceof mysqli_result && $row = $sortRes->fetch_assoc()) {
                        $sort = (int)($row['nextSort'] ?? 1);
                        if ($sort <= 0) {
                            $sort = 1;
                        }
                    }
                }

                if ($m->columnExists('navigation_dashboard_links', 'name') && $m->columnExists('navigation_dashboard_links', 'sort')) {
                    $m->runQuery("
                        INSERT INTO navigation_dashboard_links (catID, modulname, name, url, sort)
                        VALUES ('{$catID}', 'footer', 'Footer', 'admincenter.php?site=admin_footer', '{$sort}')
                    ");
                } elseif ($m->columnExists('navigation_dashboard_links', 'content_key') && $m->columnExists('navigation_dashboard_links', 'sort')) {
                    $m->runQuery("
                        INSERT INTO navigation_dashboard_links (catID, modulname, content_key, url, sort)
                        VALUES ('{$catID}', 'footer', 'footer', 'admincenter.php?site=admin_footer', '{$sort}')
                    ");
                }
            }

            $footerRes = $m->query("SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'footer' ORDER BY linkID DESC LIMIT 1");
            if ($footerRes instanceof mysqli_result && $row = $footerRes->fetch_assoc()) {
                $footerLinkID = (int)($row['linkID'] ?? 0);
            }
        }

        if ($footerLinkID > 0 && $m->columnExists('navigation_dashboard_links', 'name')) {
            // Force legacy label replacement ("Footer Easy" -> "Footer") in old schemas using the name column.
            $m->run("UPDATE navigation_dashboard_links SET name='Footer' WHERE linkID = {$footerLinkID}");
        }

        if ($footerLinkID > 0 && $m->tableExists('navigation_dashboard_lang')) {
            // Force update here (not only empty values), so legacy "Footer Easy" is fully replaced.
            $footerKeyEsc = $m->escape('nav_link_' . $footerLinkID);
            $footerModulEsc = $m->escape('footer');
            foreach (['de' => 'Footer', 'en' => 'Footer', 'it' => 'Footer'] as $lang => $label) {
                $langEsc = $m->escape($lang);
                $labelEsc = $m->escape($label);
                $m->runQuery("
                    INSERT INTO navigation_dashboard_lang (content_key, language, content, modulname, updated_at)
                    VALUES ('{$footerKeyEsc}', '{$langEsc}', '{$labelEsc}', '{$footerModulEsc}', NOW())
                    ON DUPLICATE KEY UPDATE
                        content = VALUES(content),
                        modulname = VALUES(modulname),
                        updated_at = NOW()
                ");
            }
        }
    }

    if ($m->tableExists('user_role_admin_navi_rights') && $m->columnExists('user_role_admin_navi_rights', 'modulname')) {
        $m->run("
            UPDATE user_role_admin_navi_rights
            SET modulname = 'footer'
            WHERE modulname IN ('footer_easy', 'admin_footer_easy', 'plugin_footer', 'admin_footer')
        ");

        $roleIdColumn = $m->columnExists('user_role_admin_navi_rights', 'roleID') ? 'roleID' : 'id';
        if ($m->columnExists('user_role_admin_navi_rights', $roleIdColumn) && $m->columnExists('user_role_admin_navi_rights', 'type')) {
            $m->runQuery("
                INSERT IGNORE INTO user_role_admin_navi_rights ({$roleIdColumn}, type, modulname)
                VALUES ('1', 'link', 'ac_terms_of_service')
            ");
            $m->runQuery("
                INSERT IGNORE INTO user_role_admin_navi_rights ({$roleIdColumn}, type, modulname)
                SELECT {$roleIdColumn}, 'link', 'ac_terms_of_service'
                FROM user_role_admin_navi_rights
                WHERE type = 'link' AND modulname = 'ac_privacy_policy'
            ");

            if ($m->tableExists('navigation_dashboard_links') && $m->columnExists('navigation_dashboard_links', 'modulname')) {
                $footerLinkID = 0;
                $footerRes = $m->query("SELECT linkID FROM navigation_dashboard_links WHERE modulname='footer' ORDER BY linkID DESC LIMIT 1");
                if ($footerRes instanceof mysqli_result && $row = $footerRes->fetch_assoc()) {
                    $footerLinkID = (int)($row['linkID'] ?? 0);
                }

                if ($footerLinkID > 0 && $m->columnExists('user_role_admin_navi_rights', 'accessID')) {
                    $m->runQuery("
                        INSERT IGNORE INTO user_role_admin_navi_rights ({$roleIdColumn}, type, modulname, accessID)
                        VALUES ('1', 'link', 'footer', '{$footerLinkID}')
                    ");
                    $m->run("UPDATE user_role_admin_navi_rights SET accessID='{$footerLinkID}' WHERE type='link' AND modulname='footer'");
                } else {
                    $m->runQuery("
                        INSERT IGNORE INTO user_role_admin_navi_rights ({$roleIdColumn}, type, modulname)
                        VALUES ('1', 'link', 'footer')
                    ");
                }
            }
        }
    }

    // 13) Ensure email defaults (kept from previous migration)
    $res = $m->query("SELECT emailID FROM email LIMIT 1");
    if ($res instanceof mysqli_result && $res->num_rows === 0) {
        $m->run("\n            INSERT INTO email\n                (emailID, `user`, `password`, `host`, `port`, `debug`, `auth`, `html`, `smtp`, `secure`)\n            VALUES\n                (1, '', '', '', 25, 0, 0, 1, 0, 0)\n        ");
    }

    // 14) Remove legacy footer_easy plugin directories from filesystem
    $deleteDirectoryRecursive = static function (string $dir) use (&$deleteDirectoryRecursive): bool {
        if (!is_dir($dir)) {
            return true;
        }

        $entries = @scandir($dir);
        if (!is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                if (!$deleteDirectoryRecursive($path)) {
                    return false;
                }
                continue;
            }

            if (!@unlink($path)) {
                return false;
            }
        }

        return @rmdir($dir);
    };

    $projectRoot = dirname(__DIR__, 3);
    $legacyFooterDirs = [
        $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'footer_easy',
        $projectRoot . DIRECTORY_SEPARATOR . '__plugins' . DIRECTORY_SEPARATOR . 'footer_easy',
    ];

    foreach ($legacyFooterDirs as $legacyFooterDir) {
        if (!is_dir($legacyFooterDir)) {
            continue;
        }

        if ($deleteDirectoryRecursive($legacyFooterDir)) {
            $m->log('Legacy directory removed: ' . $legacyFooterDir);
        } else {
            $m->log('Warning: could not fully remove legacy directory: ' . $legacyFooterDir);
        }
    }

    $m->log('Migration 1.0.3.3 completed (multilang DB compatibility incl. plugin/footer lang).');
};
