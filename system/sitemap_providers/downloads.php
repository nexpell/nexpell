<?php
declare(strict_types=1);

/** Provider: Downloads aus plugins_downloads – Detailseiten als action=detail&id=... */
return function (array &$pages, array $CTX): void {
    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    $table = 'plugins_downloads';

    // --- Spalten prüfen
    $cols = [];
    $cr = $db->query("SHOW COLUMNS FROM `{$table}`");
    if (!$cr) { error_log('[sitemap] downloads: Tabelle fehlt'); return; }
    while ($c = $cr->fetch_assoc()) $cols[strtolower($c['Field'])] = $c['Field'];
    $cr->free();

    $idCol        = $cols['idprimärschlüssel'] ?? ($cols['id'] ?? null);
    $titleCol     = $cols['title']     ?? null;
    $filenameCol  = $cols['filename']  ?? null;
    $updatedCol   = $cols['updated_at']?? null;
    $uploadedCol  = $cols['uploaded_at']?? null;
    // Optional: $accessRoles = $cols['access_roles'] ?? null;

    if (!$idCol) { error_log('[sitemap] downloads: keine ID-Spalte gefunden'); return; }

    // --- SELECT
    $selectCols = [$idCol];
    if ($titleCol)    $selectCols[] = $titleCol;
    if ($filenameCol) $selectCols[] = $filenameCol;
    if ($updatedCol)  $selectCols[] = $updatedCol;
    if ($uploadedCol) $selectCols[] = $uploadedCol;
    $select  = implode(',', array_map(fn($c)=>"`{$c}`", $selectCols));
    $orderBy = $updatedCol ? " ORDER BY `{$updatedCol}` DESC" : ($uploadedCol ? " ORDER BY `{$uploadedCol}` DESC" : "");

    // --- Helpers
    $pickDate = static function (array $row, ?string $col1, ?string $col2): string {
        $val = null;
        if ($col1 && !empty($row[$col1]))      $val = $row[$col1];
        elseif ($col2 && !empty($row[$col2]))  $val = $row[$col2];
        if (!$val) return date('Y-m-d');
        $ts = strtotime((string)$val);
        return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
    };

    $added = 0;
    $batch = 1000; $offset = 0;

    while (true) {
        $sql = "SELECT {$select} FROM `{$table}`{$orderBy} LIMIT {$batch} OFFSET {$offset}";
        $res = $db->query($sql);
        if (!$res) break;

        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $count++;

            // Optional: private/rollenbasierte Einträge filtern
            // if ($accessRoles && !empty($row[$accessRoles]) && strtolower((string)$row[$accessRoles]) !== 'public') continue;

            $id = trim((string)$row[$idCol]);
            if ($id === '') continue;

            $lastmod = $pickDate($row, $updatedCol, $uploadedCol);

            /**
             * Wir erzeugen ausschließlich Detailseiten-URLs:
             * - SEO:    /<lang>/downloads/detail/<id>
             * - nonSEO: index.php?site=downloads&action=detail&id=<id>&lang=<lang>
             *
             * Content-Key wird so gewählt, dass sitemap_build_loc daraus die SEO/Non-SEO-URL sauber baut.
             */
            $contentKey = "downloads/detail/{$id}";
            $qBase = ['site' => 'downloads', 'action' => 'detail', 'id' => $id];

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

    error_log("[sitemap] downloads: hinzugefügt {$added} detail-keys");
};
