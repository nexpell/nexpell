<?php
declare(strict_types=1);

/**
 * Provider: Forum – angepasst für neues Nexpell-Forum
 *
 * ✔️ NUR Canonical-URLs (keine /page/x in der Sitemap)
 * ✔️ Kategorien + Forum-Startseite
 * ✔️ lastmod aus letztem sichtbaren Post
 *
 * URLs:
 *  SEO:
 *   /<lang>/forum
 *   /<lang>/forum/overview/<catId>
 *   /<lang>/forum/thread/<slug|id>
 *
 *  non-SEO:
 *   /index.php?site=forum
 *   /index.php?site=forum&action=overview&id=<catId>
 *   /index.php?site=forum&action=thread&id=<id>
 */

return function (array &$pages, array $CTX): void {

    /** @var mysqli $db */
    $db         = $CTX['db'];
    $languages  = $CTX['languages'];
    $BASE       = $CTX['BASE'];
    $useSeoUrls = $CTX['useSeoUrls'];
    $SLUG_MAP   = $CTX['SLUG_MAP'];

    /* -------------------------------------------------
     * Helfer
     * ------------------------------------------------- */
    $dateFromUnix = static function (?int $ts): string {
        return ($ts && $ts > 0) ? date('Y-m-d', $ts) : date('Y-m-d');
    };

    /* -------------------------------------------------
     * Letzter sichtbarer Post je Thread
     * ------------------------------------------------- */
    $lastPostTs = []; // threadID => unix timestamp

    $sql = "
        SELECT
            threadID,
            MAX(GREATEST(
                IFNULL(edited_at, 0),
                IFNULL(created_at, 0)
            )) AS last_ts
        FROM plugins_forum_posts
        WHERE is_deleted = 0
        GROUP BY threadID
    ";

    if ($res = $db->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $lastPostTs[(int)$row['threadID']] = (int)$row['last_ts'];
        }
        $res->free();
    }

    /* -------------------------------------------------
     * Threads laden
     * ------------------------------------------------- */
    $threads = [];
    $catsSeen = []; // catID => lastmod

    $sql = "
        SELECT
            t.threadID,
            t.slug,
            t.catID,
            t.updated_at,
            t.created_at
        FROM plugins_forum_threads t
        ORDER BY t.updated_at DESC, t.threadID DESC
    ";

    if ($res = $db->query($sql)) {
        while ($row = $res->fetch_assoc()) {

            $threadID = (int)$row['threadID'];
            if ($threadID <= 0) {
                continue;
            }

            $slug = trim((string)$row['slug']);
            $cat  = isset($row['catID']) ? (int)$row['catID'] : null;

            // lastmod: letzter Post → thread.updated → thread.created
            $tsCandidates = [];
            if (isset($lastPostTs[$threadID])) $tsCandidates[] = $lastPostTs[$threadID];
            if (!empty($row['updated_at']))    $tsCandidates[] = (int)$row['updated_at'];
            if (!empty($row['created_at']))    $tsCandidates[] = (int)$row['created_at'];

            $lastmod = $dateFromUnix($tsCandidates ? max($tsCandidates) : null);

            /* -------------------------------------------------
             * Thread-Canonical (OHNE Pagination!)
             * ------------------------------------------------- */
            if ($slug !== '') {
                $contentKey = "forum/thread/{$slug}";
                $qBase = ['site' => 'forum', 'action' => 'thread', 'slug' => $slug];
            } else {
                $contentKey = "forum/thread/{$threadID}";
                $qBase = ['site' => 'forum', 'action' => 'thread', 'id' => $threadID];
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

            /* -------------------------------------------------
             * Kategorie-Tracking
             * ------------------------------------------------- */
            if ($cat !== null) {
                if (!isset($catsSeen[$cat]) || $lastmod > $catsSeen[$cat]) {
                    $catsSeen[$cat] = $lastmod;
                }
            }
        }

        $res->free();
    }

    /* -------------------------------------------------
     * Kategorie-Übersichten
     * ------------------------------------------------- */
    foreach ($catsSeen as $catId => $catLastmod) {

        $contentKey = "forum/overview/{$catId}";
        $qBase = ['site' => 'forum', 'action' => 'overview', 'id' => $catId];

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
            $pages[$contentKey]['lastmods'][$lang] = $catLastmod;
        }
    }

    /* -------------------------------------------------
     * Forum-Startseite
     * ------------------------------------------------- */
    $listKey = 'forum';
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
};
