<?php
declare(strict_types=1);

/**
 * Provider: News – Nexpell kompatibel
 *
 * URLs:
 *  SEO:
 *   /<lang>/news
 *   /<lang>/news/<slug|id>
 *
 *  non-SEO:
 *   /index.php?site=news
 *   /index.php?site=news&action=detail&id=<id>
 */

return function (array &$pages, array $CTX): void {

    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    /* -------------------------------------------------
     * Tabellen- & Spalten-Setup
     * ------------------------------------------------- */
    $table = 'plugins_news';

    $cols = [];
    $resCols = $db->query("SHOW COLUMNS FROM `{$table}`");
    if (!$resCols) {
        error_log('[sitemap] news: Tabelle fehlt');
        return;
    }
    while ($c = $resCols->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    $resCols->free();

    // Pflichtfelder
    $idCol   = $cols['newsid'] ?? ($cols['id'] ?? null);
    if (!$idCol) {
        error_log('[sitemap] news: keine ID-Spalte gefunden');
        return;
    }

    // Optionale Felder
    $slugCol    = $cols['slug'] ?? null;
    $createdCol = $cols['created_at'] ?? ($cols['date'] ?? null);
    $updatedCol = $cols['updated_at'] ?? null;
    $activeCol  = $cols['is_active'] ?? ($cols['active'] ?? null);

    /* -------------------------------------------------
     * SELECT bauen
     * ------------------------------------------------- */
    $selectCols = [$idCol];
    if ($slugCol)    $selectCols[] = $slugCol;
    if ($createdCol) $selectCols[] = $createdCol;
    if ($updatedCol) $selectCols[] = $updatedCol;
    if ($activeCol)  $selectCols[] = $activeCol;

    $select = implode(',', array_map(fn($c) => "`{$c}`", $selectCols));

    $where = '';
    if ($activeCol) {
        $where = " WHERE `{$activeCol}` IN (1,'1','true','TRUE')";
    }

    $orderBy = $updatedCol
        ? " ORDER BY `{$updatedCol}` DESC"
        : ($createdCol ? " ORDER BY `{$createdCol}` DESC" : "");

    /* -------------------------------------------------
     * Datumshelfer
     * ------------------------------------------------- */
    $pickDate = static function (array $row) use ($updatedCol, $createdCol): string {
        if ($updatedCol && !empty($row[$updatedCol])) {
            $v = $row[$updatedCol];
        } elseif ($createdCol && !empty($row[$createdCol])) {
            $v = $row[$createdCol];
        } else {
            return date('Y-m-d');
        }

        if (is_numeric($v)) {
            return date('Y-m-d', (int)$v);
        }

        $ts = strtotime((string)$v);
        return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
    };

    /* -------------------------------------------------
     * News laden
     * ------------------------------------------------- */
    $added = 0;
    $batch = 500;
    $offset = 0;

    while (true) {
        $sql = "SELECT {$select} FROM `{$table}`{$where}{$orderBy} LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql);
        if (!$res) {
            break;
        }

        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $count++;

            $id = (int)$row[$idCol];
            if ($id <= 0) {
                continue;
            }

            $slug = $slugCol ? trim((string)$row[$slugCol]) : '';
            $lastmod = $pickDate($row);

            // Canonical ContentKey (keine Pagination!)
            if ($slug !== '') {
                $contentKey = "news/{$slug}";
                $qBase = ['site' => 'news', 'slug' => $slug];
            } else {
                $contentKey = "news/{$id}";
                $qBase = ['site' => 'news', 'action' => 'detail', 'id' => $id];
            }

            foreach ($languages as $lang) {
                $loc = sitemap_build_loc(
                    $contentKey,
                    $lang,
                    $BASE,
                    $useSeoUrls,
                    $SLUG_MAP,
                    $qBase
                );

                if (!isset($pages[$contentKey])) {
                    $pages[$contentKey] = ['langs' => [], 'lastmods' => []];
                }

                $pages[$contentKey]['langs'][$lang]    = $loc;
                $pages[$contentKey]['lastmods'][$lang] = $lastmod;
            }

            $added++;
        }

        $res->free();
        if ($count < $batch) {
            break;
        }
        $offset += $batch;
    }

    /* -------------------------------------------------
     * News-Übersicht /news
     * ------------------------------------------------- */
    $listKey = 'news';
    $today   = date('Y-m-d');

    if (!isset($pages[$listKey])) {
        foreach ($languages as $lang) {
            $loc = sitemap_build_loc(
                $listKey,
                $lang,
                $BASE,
                $useSeoUrls,
                $SLUG_MAP
            );

            $pages[$listKey]['langs'][$lang]    = $loc;
            $pages[$listKey]['lastmods'][$lang] = $today;
        }
    }

    error_log("[sitemap] news: hinzugefügt {$added} Einträge (+ listing)");
};
