<?php
declare(strict_types=1);

/** Provider: TODO/Tasks-Detailseiten */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    // Kandidaten (tabellen/kolumnen)
    $tables     = ['todo_items','todos','tasks','todo']; // passe hier an, falls nötig
    $idCols     = ['id','todo_id','task_id'];
    $slugCols   = ['slug','seo_slug','url_key','title_slug'];
    $dateCols   = ['updated_at','last_modified','modified','changed','completed_at','created_at','created','date'];
    $langCols   = ['lang','language','locale'];
    $statusCols = ['status','is_published','published','visible','is_visible','done','completed'];

    // Tabelle finden
    $table = null;
    foreach ($tables as $t) {
        $chk = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
        if ($chk && $chk->num_rows > 0) { $table = $t; $chk->free(); break; }
        if ($chk) $chk->free();
    }
    if (!$table) return;

    // Spalten ermitteln
    $cols = [];
    if ($cr = $db->query("SHOW COLUMNS FROM `{$table}`")) {
        while ($c = $cr->fetch_assoc()) $cols[strtolower($c['Field'])] = $c['Field'];
        $cr->free();
    }

    $idCol = null; foreach ($idCols as $c) if (isset($cols[strtolower($c)])) { $idCol = $cols[strtolower($c)]; break; }
    if (!$idCol) return;

    $slugCol = null; foreach ($slugCols as $c) if (isset($cols[strtolower($c)])) { $slugCol = $cols[strtolower($c)]; break; }
    $langCol = null; foreach ($langCols as $c) if (isset($cols[strtolower($c)])) { $langCol = $cols[strtolower($c)]; break; }

    $dateUse = []; foreach ($dateCols as $c) if (isset($cols[strtolower($c)])) $dateUse[] = $cols[strlower($c)];
    $statusCol = null; foreach ($statusCols as $c) if (isset($cols[strtolower($c)])) { $statusCol = $cols[strtolower($c)]; break; }

    // SELECT
    $selectCols = [$idCol];
    if ($slugCol) $selectCols[] = $slugCol;
    if ($langCol) $selectCols[] = $langCol;
    foreach ($dateUse as $dc) if (!in_array($dc, $selectCols, true)) $selectCols[] = $dc;
    if ($statusCol && !in_array($statusCol, $selectCols, true)) $selectCols[] = $statusCol;
    $select = implode(',', array_map(fn($c)=>"`{$c}`", $selectCols));

    $orderBy = $dateUse ? " ORDER BY `{$dateUse[0]}` DESC" : "";

    // Sichtbarkeits-Filter (wenn vorhanden)
    $where = '';
    if ($statusCol) {
        $where = " WHERE (
            (LOWER(`{$statusCol}`) NOT IN ('draft','hidden','private','archived')) OR
            (`{$statusCol}` IN (1,'1','true','TRUE','done','completed','published','visible'))
        )";
    }

    // Batch
    $batch = 1000; $offset = 0;
    while (true) {
        $sql = "SELECT {$select} FROM `{$table}`{$where}{$orderBy} LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql); if (!$res) break;
        $count=0;

        while ($row = $res->fetch_assoc()) {
            $count++;
            $id = (string)$row[$idCol]; if ($id === '') continue;

            $slug = $slugCol ? trim((string)$row[$slugCol]) : '';
            $langVal = $langCol ? strtolower(trim((string)$row[$langCol])) : '';
            $lastmod = pickDate($row, $dateUse ?: $dateCols);

            // URL-Muster: /todo/{slug} ODER /todo/todoid/{id}
            $contentKey = $slug !== '' ? "todo/{$slug}" : "todo/todoid/{$id}";
            $langsToBuild = $langVal !== '' ? [$langVal] : $languages;

            $qBase = ['site'=>'todo']; if ($slug === '') $qBase['todoid'] = $id;

            foreach ($langsToBuild as $lang) {
                $loc = sitemap_build_loc($contentKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP, $qBase);
                if (!isset($pages[$contentKey])) $pages[$contentKey] = ['lastmods'=>[],'langs'=>[]];
                $pages[$contentKey]['langs'][$lang]    = $loc;
                $pages[$contentKey]['lastmods'][$lang] = $lastmod;
            }
        }

        $res->free();
        if ($count < $batch) break;
        $offset += $batch;
    }
};
