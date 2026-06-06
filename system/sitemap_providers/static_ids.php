<?php
declare(strict_types=1);

/** Provider: statische Detailseiten (static/staticid/{id}) */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    // Tabellen-Kandidaten & Spalten
    $candidates = ['static_pages','pages_static','static'];
    $idCols     = ['id','static_id','page_id'];
    $dateCols   = ['updated_at','last_modified','modified','changed','created_at','created'];

    // Tabelle finden
    $table = null;
    foreach ($candidates as $t) {
        $check = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
        if ($check && $check->num_rows > 0) { $table = $t; $check->free(); break; }
        if ($check) $check->free();
    }
    if (!$table) return;

    // Spalten ermitteln
    $cols = [];
    if ($cr = $db->query("SHOW COLUMNS FROM `{$table}`")) {
        while ($c = $cr->fetch_assoc()) $cols[strtolower($c['Field'])] = $c['Field'];
        $cr->free();
    }
    $idCol = null; foreach ($idCols as $c) if (isset($cols[strtolower($c)])) { $idCol = $cols[strtolower($c)]; break; }
    $dates = [];  foreach ($dateCols as $c) if (isset($cols[strtolower($c)])) $dates[] = $cols[strtolower($c)];
    if (!$idCol) return;

    $selectCols = [$idCol];
    foreach ($dates as $dc) if (!in_array($dc, $selectCols, true)) $selectCols[] = $dc;
    $select = implode(',', array_map(fn($c)=>"`{$c}`", $selectCols));
    $orderBy = $dates ? " ORDER BY `{$dates[0]}` DESC" : "";

    // Batch laden
    $batch = 1000; $offset = 0;
    while (true) {
        $sql = "SELECT {$select} FROM `{$table}`{$orderBy} LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql);
        if (!$res) break;

        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $count++;
            $id = (string)$row[$idCol];
            if ($id === '') continue;
            $lastmod = pickDate($row, $dates ?: $dateCols);

            $contentKey = "static/staticid/{$id}";
            sitemap_register_page($pages, $contentKey, $lastmod, [], $languages, $BASE, $useSeoUrls, $SLUG_MAP);
        }

        $res->free();
        if ($count < $batch) break;
        $offset += $batch;
    }
};
