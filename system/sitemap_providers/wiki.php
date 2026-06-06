<?php
declare(strict_types=1);

/** Provider: Wiki-Artikel aus plugins_wiki */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    $table = 'plugins_wiki';

    // ---- Spalten prüfen
    $cols = [];
    $cr = $db->query("SHOW COLUMNS FROM `{$table}`");
    if (!$cr) { error_log('[sitemap] wiki: Tabelle fehlt'); return; }
    while ($c = $cr->fetch_assoc()) $cols[strtolower($c['Field'])] = $c['Field'];
    $cr->free();

    $idCol      = $cols['idprimärschlüssel'] ?? ($cols['id'] ?? null);
    $slugCol    = $cols['slug'] ?? null;
    $updatedCol = $cols['updated_at'] ?? null;       // int(14) Unixzeit
    $activeCol  = $cols['is_active'] ?? ($cols['active'] ?? null);

    if (!$idCol) { error_log('[sitemap] wiki: keine ID-Spalte gefunden'); return; }

    // ---- SELECT
    $selectCols = [$idCol];
    if ($slugCol)    $selectCols[] = $slugCol;
    if ($updatedCol) $selectCols[] = $updatedCol;
    if ($activeCol)  $selectCols[] = $activeCol;

    $select  = implode(',', array_map(fn($c)=>"`{$c}`", $selectCols));
    $where   = '';
    if ($activeCol) { $where = " WHERE `{$activeCol}` IN (1,'1','true','TRUE')"; }
    $orderBy = $updatedCol ? " ORDER BY `{$updatedCol}` DESC" : "";

    $added = 0;
    $batch = 1000; $offset = 0;

    // ---- Helper: lastmod aus updated_at (Unix) oder heute
    $pickDate = static function (array $row, ?string $updatedCol): string {
        if ($updatedCol && isset($row[$updatedCol]) && $row[$updatedCol] !== '' && $row[$updatedCol] !== null) {
            $v = $row[$updatedCol];
            if (is_numeric($v)) return date('Y-m-d', (int)$v);
            $ts = strtotime((string)$v);
            if ($ts !== false) return date('Y-m-d', $ts);
        }
        return date('Y-m-d');
    };

    while (true) {
        $sql = "SELECT {$select} FROM `{$table}`{$where}{$orderBy} LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql);
        if (!$res) break;

        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $count++;

            $id = trim((string)$row[$idCol]);
            if ($id === '') continue;

            $slug = $slugCol ? trim((string)$row[$slugCol]) : '';
            $lastmod = $pickDate($row, $updatedCol);

            /**
             * ContentKey & Non-SEO-Fallback:
             * - mit Slug:
             *   SEO:    /<lang>/wiki/<slug>
             *   NonSEO: index.php?site=wiki&slug=<slug>&lang=<lang>
             * - ohne Slug:
             *   SEO:    /<lang>/wiki/page/<id>
             *   NonSEO: index.php?site=wiki&page=<id>&lang=<lang>
             */
            if ($slug !== '') {
                $contentKey = "wiki/{$slug}";
                $qBase = ['site' => 'wiki', 'slug' => $slug];
            } else {
                $contentKey = "wiki/page/{$id}";
                $qBase = ['site' => 'wiki', 'page' => $id];
            }

            foreach ($languages as $lang) {
                $loc = sitemap_build_loc($contentKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP, $qBase);
                if (!isset($pages[$contentKey])) $pages[$contentKey] = ['lastmods'=>[],'langs'=>[]];
                $pages[$contentKey]['langs'][$lang]    = $loc;
                $pages[$contentKey]['lastmods'][$lang] = $lastmod;
            }
            $added++;
        }

        $res->free();
        if ($count < $batch) break;
        $offset += $batch;
    }

    // ---- Listing-Seite /wiki zusätzlich aufnehmen (falls nicht über Navigation enthalten)
    $listKey = 'wiki';
    $today   = date('Y-m-d');
    if (!isset($pages[$listKey])) {
        foreach ($languages as $lang) {
            $loc = sitemap_build_loc($listKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP);
            $pages[$listKey]['langs'][$lang]    = $loc;
            $pages[$listKey]['lastmods'][$lang] = $today;
        }
    }

    error_log("[sitemap] wiki: hinzugefügt {$added} detail-keys (+ listing)");
};
