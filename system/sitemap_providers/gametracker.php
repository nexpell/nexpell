<?php
declare(strict_types=1);

/** Provider: Gametracker-Serverdetails aus plugins_gametracker_servers */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    $table = 'plugins_gametracker_servers';

    // Spalten prüfen
    $cols = [];
    $cr = $db->query("SHOW COLUMNS FROM `{$table}`");
    if (!$cr) { error_log('[sitemap] gametracker: Tabelle fehlt'); return; }
    while ($c = $cr->fetch_assoc()) $cols[strtolower($c['Field'])] = $c['Field'];
    $cr->free();

    $idCol     = $cols['idprimärschlüssel'] ?? ($cols['id'] ?? null);
    $activeCol = $cols['active'] ?? ($cols['is_active'] ?? null);
    if (!$idCol) { error_log('[sitemap] gametracker: keine ID-Spalte gefunden'); return; }

    // SELECT
    $selectCols = [$idCol];
    if ($activeCol) $selectCols[] = $activeCol;
    $select  = implode(',', array_map(fn($c)=>"`{$c}`", $selectCols));

    // WHERE
    $where = '';
    if ($activeCol) {
        $where = " WHERE `{$activeCol}` IN (1,'1','true','TRUE')";
    }

    $added = 0;
    $batch = 1000; $offset = 0;
    $today = date('Y-m-d');

    while (true) {
        $sql = "SELECT {$select} FROM `{$table}`{$where} ORDER BY `{$idCol}` DESC LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql);
        if (!$res) break;

        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $count++;
            $id = trim((string)$row[$idCol]);
            if ($id === '') continue;

            // ContentKey nach gewünschtem Muster:
            // SEO:    /<lang>/gametracker/serverdetails/<id>
            // NonSEO: /index.php?site=gametracker&action=serverdetails&id=<id>&lang=<lang>
            $contentKey = "gametracker/serverdetails/{$id}";
            $qBase = ['site' => 'gametracker', 'action' => 'serverdetails', 'id' => $id];

            foreach ($languages as $lang) {
                $loc = sitemap_build_loc($contentKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP, $qBase);
                if (!isset($pages[$contentKey])) $pages[$contentKey] = ['lastmods'=>[],'langs'=>[]];
                $pages[$contentKey]['langs'][$lang]    = $loc;
                $pages[$contentKey]['lastmods'][$lang] = $today;
            }
            $added++;
        }

        $res->free();
        if ($count < $batch) break;
        $offset += $batch;
    }

    error_log("[sitemap] gametracker: hinzugefügt {$added} serverdetails-keys");
};
