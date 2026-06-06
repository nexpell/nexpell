<?php
declare(strict_types=1);

/** Provider: Wiki-Artikel (NUR Detailseiten) aus plugins_wiki */
return function (array &$pages, array $CTX): void {

    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    $table = 'plugins_wiki';

    /* -----------------------------------------
     * Spalten prüfen
     * ----------------------------------------- */
    $cols = [];
    $cr = $db->query("SHOW COLUMNS FROM `{$table}`");
    if (!$cr) return;

    while ($c = $cr->fetch_assoc()) {
        $cols[strtolower($c['Field'])] = $c['Field'];
    }
    $cr->free();

    $idCol      = $cols['id'] ?? null;
    $slugCol    = $cols['slug'] ?? null;
    $updatedCol = $cols['updated_at'] ?? null;
    $activeCol  = $cols['is_active'] ?? null;

    if (!$idCol) return;

    /* -----------------------------------------
     * SELECT bauen
     * ----------------------------------------- */
    $select = ["`{$idCol}`"];
    if ($slugCol)    $select[] = "`{$slugCol}`";
    if ($updatedCol) $select[] = "`{$updatedCol}`";

    $sql = "SELECT " . implode(',', $select) . " FROM `{$table}`";
    if ($activeCol) {
        $sql .= " WHERE `{$activeCol}` = 1";
    }

    $res = $db->query($sql);
    if (!$res) return;

    /* -----------------------------------------
     * Datums-Helfer
     * ----------------------------------------- */
    $lastmodFromRow = static function (array $row) use ($updatedCol): string {
        if ($updatedCol && !empty($row[$updatedCol])) {
            if (is_numeric($row[$updatedCol])) {
                return date('Y-m-d', (int)$row[$updatedCol]);
            }
            $ts = strtotime((string)$row[$updatedCol]);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }
        return date('Y-m-d');
    };

    /* -----------------------------------------
     * Wiki-Detailseiten registrieren
     * ----------------------------------------- */
    while ($row = $res->fetch_assoc()) {

        $id = (int)$row[$idCol];
        if ($id <= 0) continue;

        $slug    = $slugCol ? trim((string)$row[$slugCol]) : '';
        $lastmod = $lastmodFromRow($row);

        /**
         * 👉 WICHTIG:
         * Detailseite – KEINE Pagination
         *
         * SEO:
         *   /de/wiki/<slug>
         *   /de/wiki/detail/22
         *
         * Non-SEO:
         *   index.php?site=wiki&action=detail&id=22
         */
        if ($slug !== '') {
            $contentKey = "wiki/{$slug}";
        } else {
            $contentKey = "wiki/detail/{$id}";
        }

        $qBase = [
            'site'   => 'wiki',
            'action' => 'detail',
            'id'     => $id
        ];

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
    }

    $res->free();

    /* -----------------------------------------
     * Wiki-Übersichtsseite /wiki
     * ----------------------------------------- */
    $listKey = 'wiki';
    if (!isset($pages[$listKey])) {
        foreach ($languages as $lang) {
            $pages[$listKey]['langs'][$lang] =
                sitemap_build_loc($listKey, $lang, $BASE, $useSeoUrls, $SLUG_MAP);
            $pages[$listKey]['lastmods'][$lang] = date('Y-m-d');
        }
    }
};
