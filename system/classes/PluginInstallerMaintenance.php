<?php

namespace nexpell;

class PluginInstallerMaintenance
{
    public static function backfillMissingModuleNames(): void
    {
        self::backfillPluginLanguageModuleNames();
        self::backfillNavigationLanguageModuleNames(
            'navigation_dashboard_lang',
            'navigation_dashboard_links',
            'linkID',
            'nav_link_'
        );
        self::backfillNavigationLanguageModuleNames(
            'navigation_website_lang',
            'navigation_website_sub',
            'snavID',
            'nav_sub_'
        );
    }

    public static function ensureLegacyLanguageColumns(array $tables): void
    {
        $legacyCols = [
            'name' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'lang' => "VARCHAR(10) NOT NULL DEFAULT 'de'",
            'translation' => "TEXT NULL",
        ];

        foreach ($tables as $tableName) {
            $tableRes = safe_query("SHOW TABLES LIKE '" . escape($tableName) . "'");
            if (!$tableRes || mysqli_num_rows($tableRes) === 0) {
                continue;
            }

            foreach ($legacyCols as $colName => $colType) {
                $colRes = safe_query("SHOW COLUMNS FROM `$tableName` LIKE '" . escape($colName) . "'");
                if (!$colRes || mysqli_num_rows($colRes) === 0) {
                    safe_query("ALTER TABLE `$tableName` ADD COLUMN `$colName` $colType");
                }
            }
        }
    }

    public static function cleanupDuplicateNavigationLanguageRows(string $table, array $contentKeys = []): void
    {
        $whereSql = '';
        if (!empty($contentKeys)) {
            $escapedKeys = [];
            foreach ($contentKeys as $contentKey) {
                $contentKey = trim((string)$contentKey);
                if ($contentKey !== '') {
                    $escapedKeys[] = "'" . escape($contentKey) . "'";
                }
            }

            if (!empty($escapedKeys)) {
                $whereSql = 'WHERE content_key IN (' . implode(', ', $escapedKeys) . ')';
            }
        }

        $result = safe_query("
            SELECT content_key, language, content, modulname, updated_at, lang, translation, name
            FROM {$table}
            {$whereSql}
            ORDER BY content_key ASC, language ASC, updated_at DESC
        ");

        $groups = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $contentKey = trim((string)($row['content_key'] ?? ''));
            $language = trim((string)($row['language'] ?? ''));
            if ($contentKey === '' || $language === '') {
                continue;
            }

            $groupKey = strtolower($contentKey . '|' . $language);
            $groups[$groupKey][] = $row;
        }

        foreach ($groups as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $canonical = $rows[0];
            foreach ($rows as $row) {
                foreach (['content', 'modulname', 'lang', 'translation', 'name'] as $field) {
                    if (trim((string)($canonical[$field] ?? '')) === '' && trim((string)($row[$field] ?? '')) !== '') {
                        $canonical[$field] = $row[$field];
                    }
                }
            }

            $contentKeyEsc = escape((string)$canonical['content_key']);
            $languageEsc = escape((string)$canonical['language']);
            $contentEsc = escape((string)($canonical['content'] ?? ''));
            $modulnameEsc = escape((string)($canonical['modulname'] ?? ''));
            $legacyLangEsc = escape((string)($canonical['lang'] ?? ''));
            $translationEsc = escape((string)($canonical['translation'] ?? ''));
            $nameEsc = escape((string)($canonical['name'] ?? ''));

            safe_query("
                DELETE FROM {$table}
                WHERE content_key = '{$contentKeyEsc}'
                  AND language = '{$languageEsc}'
            ");

            safe_query("
                INSERT INTO {$table}
                    (content_key, language, content, modulname, updated_at, lang, translation, name)
                VALUES
                    (
                        '{$contentKeyEsc}',
                        '{$languageEsc}',
                        '{$contentEsc}',
                        '{$modulnameEsc}',
                        NOW(),
                        '{$legacyLangEsc}',
                        '{$translationEsc}',
                        '{$nameEsc}'
                    )
            ");
        }
    }

    public static function ensureUniqueContentLanguageIndex(string $table): void
    {
        $indexResult = safe_query("SHOW INDEX FROM {$table} WHERE Key_name = 'uniq_content_lang'");
        if ($indexResult && mysqli_num_rows($indexResult) > 0) {
            return;
        }

        safe_query("
            ALTER TABLE {$table}
            ADD UNIQUE KEY uniq_content_lang (content_key, language)
        ");
    }

    public static function cleanupDuplicateAdminNavigationEntries(string $modulname): void
    {
        $modulnameEscaped = escape($modulname);
        $navResult = safe_query("
            SELECT linkID, catID, modulname, url, sort
            FROM navigation_dashboard_links
            WHERE modulname = '" . $modulnameEscaped . "'
            ORDER BY url ASC, sort ASC, linkID ASC
        ");

        $groups = [];
        while ($row = mysqli_fetch_assoc($navResult)) {
            $urlKey = trim((string)($row['url'] ?? ''));
            $groups[$urlKey !== '' ? $urlKey : '__empty_url__'][] = $row;
        }

        foreach ($groups as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $keepRow = $rows[0];
            $keepId = (int)$keepRow['linkID'];

            safe_query("
                UPDATE navigation_dashboard_links
                SET catID = " . (int)($keepRow['catID'] ?? 0) . ",
                    modulname = '" . $modulnameEscaped . "',
                    url = '" . escape((string)($keepRow['url'] ?? '')) . "',
                    sort = " . (int)($keepRow['sort'] ?? 0) . "
                WHERE linkID = " . $keepId
            );

            for ($i = 1, $count = count($rows); $i < $count; $i++) {
                $deleteId = (int)$rows[$i]['linkID'];
                safe_query("DELETE FROM navigation_dashboard_lang WHERE content_key = 'nav_link_" . $deleteId . "'");
                safe_query("DELETE FROM navigation_dashboard_links WHERE linkID = " . $deleteId);
            }
        }

        $contentKeys = [];
        $langResult = safe_query("
            SELECT linkID
            FROM navigation_dashboard_links
            WHERE modulname = '" . $modulnameEscaped . "'
        ");
        while ($langResult && ($row = mysqli_fetch_assoc($langResult))) {
            $contentKeys[] = 'nav_link_' . (int)($row['linkID'] ?? 0);
        }

        self::cleanupDuplicateNavigationLanguageRows('navigation_dashboard_lang', $contentKeys);
    }

    public static function cleanupDuplicateWebsiteNavigationEntries(string $modulname): void
    {
        $aliases = self::navigationAliases($modulname);
        if (empty($aliases)) {
            $aliases = [$modulname];
        }

        $aliasSql = array_map(static function (string $alias): string {
            return "'" . escape($alias) . "'";
        }, $aliases);

        $navResult = safe_query("
            SELECT snavID, mnavID, modulname, url, sort, indropdown
            FROM navigation_website_sub
            WHERE modulname IN (" . implode(', ', $aliasSql) . ")
            ORDER BY url ASC, sort ASC, snavID ASC
        ");

        $groups = [];
        while ($row = mysqli_fetch_assoc($navResult)) {
            $urlKey = trim((string)($row['url'] ?? ''));
            $groups[$urlKey !== '' ? $urlKey : '__empty_url__'][] = $row;
        }

        foreach ($groups as $rows) {
            if (empty($rows)) {
                continue;
            }

            $keepRow = $rows[0];
            $keepId = (int)$keepRow['snavID'];

            safe_query("
                UPDATE navigation_website_sub
                SET mnavID = " . (int)($keepRow['mnavID'] ?? 0) . ",
                    modulname = '" . escape((string)($keepRow['modulname'] ?? $modulname)) . "',
                    url = '" . escape((string)($keepRow['url'] ?? '')) . "',
                    sort = " . (int)($keepRow['sort'] ?? 0) . ",
                    indropdown = " . (int)($keepRow['indropdown'] ?? 0) . ",
                    last_modified = NOW()
                WHERE snavID = " . $keepId
            );

            if (count($rows) < 2) {
                continue;
            }

            for ($i = 1, $count = count($rows); $i < $count; $i++) {
                $deleteId = (int)$rows[$i]['snavID'];
                safe_query("DELETE FROM navigation_website_lang WHERE content_key = 'nav_sub_" . $deleteId . "'");
                safe_query("DELETE FROM navigation_website_sub WHERE snavID = " . $deleteId);
            }
        }

        $contentKeys = [];
        $langResult = safe_query("
            SELECT snavID
            FROM navigation_website_sub
            WHERE modulname IN (" . implode(', ', $aliasSql) . ")
        ");
        while ($langResult && ($row = mysqli_fetch_assoc($langResult))) {
            $contentKeys[] = 'nav_sub_' . (int)($row['snavID'] ?? 0);
        }

        self::cleanupDuplicateNavigationLanguageRows('navigation_website_lang', $contentKeys);
    }

    public static function syncCoreNavDemoMenus(): void
    {
        $liveMenu = self::buildPluginNavigationMenu();
        if (empty($liveMenu)) {
            return;
        }

        $result = safe_query("
            SELECT id, settings
            FROM settings_widgets_positions
            WHERE widget_key = 'core_nav_demo'
        ");

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $settings = [];
            $rawSettings = trim((string)($row['settings'] ?? ''));
            if ($rawSettings !== '') {
                $decoded = json_decode($rawSettings, true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }

            if ((string)($settings['menuSource'] ?? '') !== 'plugin') {
                continue;
            }

            $existingMenu = is_array($settings['menu'] ?? null) ? $settings['menu'] : [];
            $mergedMenu = self::mergeCoreNavDemoMenu($existingMenu, $liveMenu);

            if ($mergedMenu === $existingMenu) {
                continue;
            }

            $settings['menu'] = $mergedMenu;
            $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($settingsJson) || $settingsJson === '') {
                continue;
            }

            safe_query("
                UPDATE settings_widgets_positions
                SET settings = '" . escape($settingsJson) . "'
                WHERE id = " . $id . "
            ");
        }
    }

    private static function navigationAliases(string $modulname): array
    {
        $aliases = [$modulname];
        $settingsRes = safe_query("
            SELECT modulname, index_link, hiddenfiles
            FROM settings_plugins
            WHERE modulname = '" . escape($modulname) . "'
            LIMIT 1
        ");

        if ($settingsRes && ($settingsRow = mysqli_fetch_assoc($settingsRes))) {
            foreach (['modulname', 'index_link', 'hiddenfiles'] as $field) {
                $raw = trim((string)($settingsRow[$field] ?? ''));
                if ($raw === '') {
                    continue;
                }

                foreach (explode(',', $raw) as $part) {
                    $alias = trim((string)$part);
                    if ($alias !== '') {
                        $aliases[] = $alias;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($aliases, static function (string $alias): bool {
            return $alias !== '';
        })));
    }

    private static function buildPluginNavigationMenu(): array
    {
        $menu = [];
        $mainResult = safe_query("
            SELECT mnavID, modulname, url, sort, isdropdown
            FROM navigation_website_main
            ORDER BY sort ASC, mnavID ASC
        ");

        while ($mainResult && ($mainRow = mysqli_fetch_assoc($mainResult))) {
            $mnavID = (int)($mainRow['mnavID'] ?? 0);
            if ($mnavID <= 0) {
                continue;
            }

            $entry = [
                'label' => self::resolveNavigationLabel('nav_main_' . $mnavID, (string)($mainRow['modulname'] ?? '')),
                'url' => self::normalizeNavigationUrl((string)($mainRow['url'] ?? '#')),
                'children' => [],
            ];

            $isDropdown = (int)($mainRow['isdropdown'] ?? 0) === 1;
            if ($isDropdown) {
                $subResult = safe_query("
                    SELECT snavID, modulname, url, sort
                    FROM navigation_website_sub
                    WHERE mnavID = " . $mnavID . "
                    ORDER BY sort ASC, snavID ASC
                ");

                while ($subResult && ($subRow = mysqli_fetch_assoc($subResult))) {
                    $snavID = (int)($subRow['snavID'] ?? 0);
                    if ($snavID <= 0) {
                        continue;
                    }

                    $entry['children'][] = [
                        'label' => self::resolveNavigationLabel('nav_sub_' . $snavID, (string)($subRow['modulname'] ?? '')),
                        'url' => self::normalizeNavigationUrl((string)($subRow['url'] ?? '#')),
                    ];
                }
            }

            $menu[] = $entry;
        }

        return $menu;
    }

    private static function resolveNavigationLabel(string $contentKey, string $fallbackModulname): string
    {
        $contentKeyEsc = escape($contentKey);
        $preferredLanguages = ['de', 'en', 'it'];

        foreach ($preferredLanguages as $language) {
            $result = safe_query("
                SELECT content
                FROM navigation_website_lang
                WHERE content_key = '" . $contentKeyEsc . "'
                  AND language = '" . escape($language) . "'
                LIMIT 1
            ");
            if ($result && ($row = mysqli_fetch_assoc($result))) {
                $content = trim((string)($row['content'] ?? ''));
                if ($content !== '') {
                    return $content;
                }
            }
        }

        $fallbackResult = safe_query("
            SELECT content
            FROM navigation_website_lang
            WHERE content_key = '" . $contentKeyEsc . "'
            ORDER BY id ASC
            LIMIT 1
        ");
        if ($fallbackResult && ($fallbackRow = mysqli_fetch_assoc($fallbackResult))) {
            $content = trim((string)($fallbackRow['content'] ?? ''));
            if ($content !== '') {
                return $content;
            }
        }

        return trim($fallbackModulname) !== '' ? trim($fallbackModulname) : $contentKey;
    }

    private static function normalizeNavigationUrl(string $url): string
    {
        $url = trim($url);
        return $url !== '' ? $url : '#';
    }

    private static function mergeCoreNavDemoMenu(array $existingMenu, array $liveMenu): array
    {
        $merged = $existingMenu;

        foreach ($liveMenu as $liveItem) {
            $existingIndex = self::findCoreNavDemoItemIndex($merged, $liveItem);
            if ($existingIndex === null) {
                $merged[] = self::sanitizeCoreNavDemoItem($liveItem);
                continue;
            }

            $existingItem = is_array($merged[$existingIndex] ?? null) ? $merged[$existingIndex] : [];
            $existingChildren = is_array($existingItem['children'] ?? null) ? $existingItem['children'] : [];
            $liveChildren = is_array($liveItem['children'] ?? null) ? $liveItem['children'] : [];

            foreach ($liveChildren as $liveChild) {
                if (self::findCoreNavDemoItemIndex($existingChildren, $liveChild) !== null) {
                    continue;
                }

                $existingChildren[] = self::sanitizeCoreNavDemoItem($liveChild);
            }

            if (!empty($liveChildren)) {
                $existingItem['children'] = $existingChildren;
                $merged[$existingIndex] = $existingItem;
            }
        }

        return $merged;
    }

    private static function findCoreNavDemoItemIndex(array $items, array $needle): ?int
    {
        $needleUrl = self::normalizeNavigationUrl((string)($needle['url'] ?? ''));
        $needleLabel = trim((string)($needle['label'] ?? ''));

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemUrl = self::normalizeNavigationUrl((string)($item['url'] ?? ''));
            $itemLabel = trim((string)($item['label'] ?? ''));

            if ($needleUrl !== '#' && $itemUrl === $needleUrl) {
                return $index;
            }

            if ($needleUrl === '#' && $itemUrl === '#' && $needleLabel !== '' && strcasecmp($itemLabel, $needleLabel) === 0) {
                return $index;
            }
        }

        return null;
    }

    private static function sanitizeCoreNavDemoItem(array $item): array
    {
        $sanitized = [
            'label' => trim((string)($item['label'] ?? '')),
            'url' => self::normalizeNavigationUrl((string)($item['url'] ?? '#')),
        ];

        if (isset($item['children']) && is_array($item['children'])) {
            $children = [];
            foreach ($item['children'] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $children[] = self::sanitizeCoreNavDemoItem($child);
            }
            $sanitized['children'] = $children;
        }

        return $sanitized;
    }

    private static function backfillPluginLanguageModuleNames(): void
    {
        $result = safe_query("
            SELECT id, content_key
            FROM settings_plugins_lang
            WHERE modulname IS NULL OR modulname = ''
        ");

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $id = (int)($row['id'] ?? 0);
            $contentKey = trim((string)($row['content_key'] ?? ''));
            if ($id <= 0 || $contentKey === '') {
                continue;
            }

            if (!preg_match('/^plugin_(?:name|info)_(.+)$/', $contentKey, $match)) {
                continue;
            }

            $modulname = trim((string)($match[1] ?? ''));
            if ($modulname === '') {
                continue;
            }

            safe_query("
                UPDATE settings_plugins_lang
                SET modulname = '" . escape($modulname) . "'
                WHERE id = " . $id . "
            ");
        }
    }

    private static function backfillNavigationLanguageModuleNames(
        string $languageTable,
        string $sourceTable,
        string $sourceIdColumn,
        string $contentKeyPrefix
    ): void {
        $result = safe_query("
            SELECT id, content_key
            FROM {$languageTable}
            WHERE modulname IS NULL OR modulname = ''
        ");

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $id = (int)($row['id'] ?? 0);
            $contentKey = trim((string)($row['content_key'] ?? ''));
            if ($id <= 0 || $contentKey === '' || strpos($contentKey, $contentKeyPrefix) !== 0) {
                continue;
            }

            $sourceId = (int)substr($contentKey, strlen($contentKeyPrefix));
            if ($sourceId <= 0) {
                continue;
            }

            $sourceRes = safe_query("
                SELECT modulname
                FROM {$sourceTable}
                WHERE {$sourceIdColumn} = " . $sourceId . "
                LIMIT 1
            ");
            if (!$sourceRes) {
                continue;
            }

            $sourceRow = mysqli_fetch_assoc($sourceRes);
            $modulname = trim((string)($sourceRow['modulname'] ?? ''));
            if ($modulname === '') {
                continue;
            }

            safe_query("
                UPDATE {$languageTable}
                SET modulname = '" . escape($modulname) . "'
                WHERE id = " . $id . "
            ");
        }
    }
}
