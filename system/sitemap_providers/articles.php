<?php
declare(strict_types=1);

/** Provider: Articles aus plugins_articles */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    $table            = 'plugins_articles';
    $idCandidates     = ['idPrimärschlüssel','id','article_id','post_id','news_id'];
    $slugCandidates   = ['slug'];
    $dateCandidates   = ['updated_at','last_modified','modified','changed','created_at','created','date'];
    $statusCandidates = ['is_active','status','published','visible','is_visible'];

    // Spalten ermitteln
    $cols = [];
    $cr = $db->query("SHOW COLUMNS FROM `{$table}`");
    if (!$cr) { error_log('[sitemap] articles: Tabelle fehlt'); return; }
    while ($c = $cr->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    $cr->free();

    $idCol = null; foreach ($idCandidates as $c) if (isset($cols[strtolower($c)])) { $idCol = $cols[strtolower($c)]; break; }
    if (!$idCol) { error_log('[sitemap] articles: keine ID-Spalte gefunden'); return; }

    $slugCol = null; foreach ($slugCandidates as $c) if (isset($cols[strtolower($c)])) { $slugCol = $cols[strtolower($c)]; break; }

    $dateUse = [];
    foreach ($dateCandidates as $c) if (isset($cols[strtolower($c)])) $dateUse[] = $cols[strtolower($c)];

    $statusCol = null; foreach ($statusCandidates as $c) if (isset($cols[strtolower($c)])) { $statusCol = $cols[strtolower($c)]; break; }

    // SELECT
    $selectCols = [$idCol];
    if ($slugCol) $selectCols[] = $slugCol;
    foreach ($dateUse as $dc) if (!in_array($dc, $selectCols, true)) $selectCols[] = $dc;
    if ($statusCol && !in_array($statusCol, $selectCols, true)) $selectCols[] = $statusCol;

    $select  = implode(',', array_map(fn($c)=>"`{$c}`", $selectCols));
    $orderBy = $dateUse ? " ORDER BY `{$dateUse[0]}` DESC" : "";

    // nur aktive Artikel, wenn Spalte vorhanden
    $where = '';
    if ($statusCol) {
        $where = " WHERE `{$statusCol}` IN (1,'1','true','TRUE')";
    }

    $added = 0;
    $batch = 1000; $offset = 0;
    while (true) {
        $sql = "SELECT {$select} FROM `{$table}`{$where}{$orderBy} LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql);
        if (!$res) break;

        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $count++;

            $id = (string)$row[$idCol];
            if ($id === '') continue;

            $slug = $slugCol ? trim((string)$row[$slugCol]) : '';

            // lastmod: updated_at ist int(14) → Unix-Timestamp
            $lastmod = date('Y-m-d');
            if ($dateUse) {
                $val = $row[$dateUse[0]] ?? null;
                if ($val !== null && $val !== '') {
                    if (is_numeric($val)) $lastmod = date('Y-m-d', (int)$val);
                    else {
                        $ts = strtotime((string)$val);
                        if ($ts !== false) $lastmod = date('Y-m-d', $ts);
                    }
                }
            }

            // Content-Key + non-SEO-Query
            $contentKey = $slug !== '' ? "articles/{$slug}" : "articles/articleid/{$id}";
            $qBase = ['site'=>'articles'];
            if ($slug === '') $qBase['articleid'] = $id;

            // keine Sprachspalte → für alle aktiven Sprachen
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

    error_log("[sitemap] articles: hinzugefügt {$added} keys");
};
