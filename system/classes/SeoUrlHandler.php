<?php
namespace nexpell;


class SeoUrlHandler {


public static function route(?string $uri = null): void
{
    // ==================================================
    // âŒ UNGÃœLTIGE TEST-/THEME-PFAD-NAMEN HART BLOCKIEREN
    // ==================================================
    if (preg_match('#^/(de|en|it)/(de-test|d1e)(/|$)#i', $_SERVER['REQUEST_URI'])) {
        $lang = $_GET['lang'] ?? ($_SESSION['language'] ?? 'de');
        header("Location: /{$lang}", true, 301);
        exit;
    }

    // ==================================================
    // ðŸ”’ VERBIETE index.php Ã¶ffentlich (SEO)
    // ==================================================
    if (str_starts_with($_SERVER['REQUEST_URI'], '/index.php')) {
        $hasSiteInGet = !empty($_GET['site']);
        $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
        $hasSiteInQuery = stripos($queryString, 'site=') !== false;

        // Guard: keep direct query routing (index.php?site=...) to avoid redirect loops
        // on hosts that provide REQUEST_URI without query string.
        if (!$hasSiteInGet && !$hasSiteInQuery) {
            $seo = SeoUrlHandler::convertToSeoUrl($_SERVER['REQUEST_URI']);
            header("Location: {$seo}", true, 301);
            exit;
        }
    }

    // /{lang}/index -> /{lang} (kanonische Startseite)
    if (preg_match('#^/([a-z]{2})/index/?(?:\?.*)?$#i', $_SERVER['REQUEST_URI'], $m)) {
        header("Location: /" . strtolower($m[1]), true, 301);
        exit;
    }



    // setlang ist KEINE Ziel-URL â†’ immer SEO-konform weiterleiten
    $clean = strtok($_SERVER['REQUEST_URI'], '?');
    $hasQueryString = ((string)($_SERVER['QUERY_STRING'] ?? '')) !== '';

    // ðŸ”’ NUR redirecten, wenn index.php wirklich entfernt wird
    if (str_ends_with($clean, '/index.php') && !$hasQueryString) {

        $target = rtrim(substr($clean, 0, -10), '/');
        if ($target === '') $target = '/';

        // â— Self-Redirect verhindern
        if ($target !== $clean) {
            header("Location: {$target}", true, 301);
            exit;
        }
    }




    // ðŸ”’ Fehlendes SprachprÃ¤fix â†’ erzwingen
    if (
        !preg_match('#^/[a-z]{2}/#i', $_SERVER['REQUEST_URI'])
        && preg_match('#^/(forum|news|profile|wiki|downloads|articles)#i', $_SERVER['REQUEST_URI'])
    ) {
        $lang = $_SESSION['language'] ?? 'de';
        header("Location: /{$lang}{$_SERVER['REQUEST_URI']}", true, 301);
        exit;
    }


    // ==================================================
    // ðŸ”’ NIE eigenstÃ¤ndige URLs mit new_lang / setlang
    // ==================================================
    if (isset($_GET['new_lang']) || isset($_GET['setlang'])) {
        $clean = preg_replace('/[?&](new_lang|setlang)=[^&]+/', '', $_SERVER['REQUEST_URI']);
        header("Location: {$clean}", true, 301);
        exit;
    }

    // ==============================
    // LANGUAGE SWITCH (?setlang=xx)
    // ==============================
    if (isset($_GET['setlang'])) {
        $lang = strtolower(preg_replace('/[^a-z]/', '', $_GET['setlang']));
        $_SESSION['language'] = $lang;

        $query = $_GET;
        unset($query['setlang'], $query['lang']);
        $query['lang'] = $lang;

        $url = self::convertToSeoUrl(
            'index.php?' . http_build_query($query)
        );

        header("X-Robots-Tag: noindex", true);
        header("Location: {$url}", true, 301);
        exit;
    }

    // ==============================
    // BASIS-PARSING
    // ==============================
    $uri = $uri ?? $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));

    // ==================================================
    // âŒ UNGÃœLTIGE TEST-/THEME-PFAD-NAMEN HART BLOCKIEREN
    // ==================================================
    $invalidSites = ['de-test', 'd1e'];

    if (isset($_GET['site']) && in_array($_GET['site'], $invalidSites, true)) {
        $lang = $_GET['lang'] ?? ($_SESSION['language'] ?? 'de');
        header("Location: /{$lang}", true, 301);
        exit;
    }

    // ðŸš« /page/1 ist verboten â€“ EINMALIG abfangen
    if (preg_match('#/page/1/?$#', $_SERVER['REQUEST_URI'])) {
        $target = preg_replace('#/page/1/?$#', '/', $_SERVER['REQUEST_URI']);
        header('Location: ' . $target, true, 301);
        exit; // â›” WICHTIG
    }



    // ðŸ” LEGACY FORUM FIX
    if (
        ($_GET['site'] ?? null) === 'forum'
        && ($_GET['action'] ?? null) === 'thread'
        && isset($_GET['id'])
        && !isset($_GET['threadID'])
    ) {
        $_GET['threadID'] = (int)$_GET['id'];
        unset($_GET['id']);
    }


    // ==============================
    // SEO-ROUTING
    // ==============================
    if (isset($segments[0]) && preg_match('/^[a-z]{2}$/i', $segments[0])) {

        $_GET['lang'] = strtolower($segments[0]);
        $_GET['site'] = $segments[1] ?? 'index';

        $knownActions = [
            'show','watch','deletecomment','edit','new','list',

            // Forum
            'thread','post','category',
            'showthread','showpost','showcategory',

            // Spezial
            'new_thread','quote','quote_reply','edit_post','delete_post','reply',
        ];

        $_GET['action'] = (
            isset($segments[2]) &&
            in_array(strtolower($segments[2]), $knownActions, true)
        ) ? strtolower($segments[2]) : null;

        /* =======================
         * NEWS
         * ======================= */
        if ($_GET['site'] === 'news') {

            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;
            $s4 = $segments[4] ?? null;

            if ($s2 === 'page' && ctype_digit((string)$s3)) {
                $_GET['page'] = (int)$s3;
                $_GET['action'] = null;
                return;
            }

            if ($s2 && $s3 === 'page' && ctype_digit((string)$s4)) {
                if (ctype_digit($s2)) {
                    $_GET['id'] = (int)$s2;
                    $_GET['newsID'] = (int)$s2;
                } else {
                    $_GET['slug'] = $s2;
                }
                $_GET['page'] = (int)$s4;
                return;
            }

            if ($s2 && !in_array(strtolower($s2), $knownActions, true)) {
                if (ctype_digit($s2)) {
                    $_GET['id'] = (int)$s2;
                    $_GET['newsID'] = (int)$s2;
                } else {
                    $_GET['slug'] = $s2;
                }
                return;
            }
        }

        /* =======================
         * ARTICLES
         * ======================= */
        if ($_GET['site'] === 'articles') {

            $lang = $_GET['lang'];
            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;

            if ($s2 === 'page' && ctype_digit((string)$s3)) {
                header("Location: /{$lang}/articles?page={$s3}", true, 301);
                exit;
            }

            if ($s2 === 'watch' && ctype_digit((string)$s3)) {
                $_GET['action'] = 'watch';
                $_GET['id'] = (int)$s3;
                return;
            }

            $_GET['action'] = null;
            return;
        }

        /* =======================
         * RAIDPLANER
         * ======================= */
        if ($_GET['site'] === 'raidplaner') {

            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;

            if ($s2 && in_array($s2, ['show','edit','delete'], true)) {
                $_GET['action'] = $s2;
                if ($s3 && ctype_digit($s3)) {
                    $_GET['id'] = (int)$s3;
                }
                return;
            }

            $_GET['action'] = null;
            return;
        }

        
        /* =======================
         * WIKI
         * ======================= */
        if (($_GET['site'] ?? '') === 'wiki') {

            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;
            $s4 = $segments[4] ?? null;
            $s5 = $segments[5] ?? null;

            $lang = $_GET['lang'] ?? 'de';

            /* =======================
             * KATEGORIE + PAGINATION
             * /wiki/cat/{id}
             * /wiki/cat/{id}/page/{n}
             * ======================= */
            if ($s2 === 'cat' && ctype_digit($s3)) {

                $_GET['cat'] = (int)$s3;

                // /wiki/cat/{id}/page/{n}
                if ($s4 === 'page' && ctype_digit($s5)) {
                    if ((int)$s5 <= 1) {
                        header("Location: /{$lang}/wiki/cat/{$s3}", true, 301);
                        exit;
                    }
                    $_GET['page'] = (int)$s5;
                }

                $_GET['action'] = null;
                return;
            }

            /* =======================
             * DETAIL
             * /wiki/detail/{id}
             * ======================= */
            if ($s2 === 'detail' && ctype_digit($s3)) {
                $_GET['action'] = 'detail';
                $_GET['id'] = (int)$s3;
                return;
            }

            /* =======================
             * LEGACY: /wiki/{id}
             * ======================= */
            if ($s2 && ctype_digit($s2)) {
                header("Location: /{$lang}/wiki/detail/{$s2}", true, 301);
                exit;
            }

            /* =======================
             * ÃœBERSICHT + PAGINATION
             * /wiki/page/{n}
             * ======================= */
            /* =======================
         * LEGACY: /wiki/page/{id} â†’ DETAIL
         * ======================= */
        if ($s2 === 'page' && ctype_digit($s3)) {

            // page/1 ist KEIN Artikel
            if ((int)$s3 <= 1) {
                header("Location: /{$lang}/wiki", true, 301);
                exit;
            }

            // page/{id} = alter Artikel-Link
            header("Location: /{$lang}/wiki/detail/{$s3}", true, 301);
            exit;
        }

            // Basis: /wiki
            $_GET['action'] = null;
            return;
        }

        /* =======================
         * FORUM
         * ======================= */
        if ($_GET['site'] === 'forum') {



            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;
            $s4 = $segments[4] ?? null;
            $s5 = $segments[5] ?? null;

            $setThread = function(int $id) {
                $_GET['thread'] = $_GET['threadID'] = $_GET['id'] = $id;
            };
            $setPost = function(int $id) {
                $_GET['post'] = $_GET['postID'] = $_GET['id'] = $id;
            };
            $setCat = function(int $id) {
                $_GET['category'] = $_GET['categoryID'] = $_GET['id'] = $id;
            };

            if ($s2 === 'thread' && ctype_digit($s3)) {
                $setThread((int)$s3);
                $_GET['action'] ??= 'thread';
                if ($s4 === 'page' && ctype_digit($s5)) $_GET['page'] = (int)$s5;
                return;
            }

            if ($s2 === 'post' && ctype_digit($s3)) {
                $setPost((int)$s3);
                $_GET['action'] ??= 'post';
                return;
            }

            if ($s2 === 'category' && ctype_digit($s3)) {
                $setCat((int)$s3);
                $_GET['action'] ??= 'category';
                if ($s4 === 'page' && ctype_digit($s5)) $_GET['page'] = (int)$s5;
                return;
            }

            if ($s2 === 'new_thread') {
                $_GET['action'] = 'new_thread';

                if (strtolower((string)$s3) === 'catid' && ctype_digit((string)$s4)) {
                    $_GET['catID'] = (int)$s4;
                } elseif (ctype_digit((string)$s3)) {
                    $_GET['catID'] = (int)$s3;
                }

                return;
            }

            if ($s2 === 'reply') {
                $_GET['action'] = 'reply';

                if (strtolower((string)$s3) === 'threadid' && ctype_digit((string)$s4)) {
                    $_GET['threadID'] = (int)$s4;
                } elseif (ctype_digit((string)$s3)) {
                    $_GET['threadID'] = (int)$s3;
                }

                return;
            }
            if ($s2 === 'page' && ctype_digit($s3)) {
                $_GET['page'] = (int)$s3;
                return;
            }

            if (in_array($s2, ['showthread','showpost','showcategory'], true) && ctype_digit($s3)) {
                $_GET['action'] = $s2;
                $_GET['id'] = (int)$s3;
                return;
            }

            /* =======================
             * QUOTE ROUTE â€“ FINAL FIX
             * ======================= */
            if ($s2 === 'quote') {

                $_GET['action'] = 'quote';

                // Alles lowercase vergleichen
                $k1 = strtolower($s3 ?? '');
                $k2 = strtolower($s5 ?? '');

                if (
                    $k1 === 'postid' && ctype_digit((string)$s4) &&
                    $k2 === 'threadid' && isset($segments[6]) && ctype_digit((string)$segments[6])
                ) {
                    $_GET['postID']   = (int)$s4;
                    $_GET['threadID'] = (int)$segments[6];
                    return;
                }

                if ($k1 === 'threadid' && ctype_digit((string)$s4)) {
                    $_GET['threadID'] = (int)$s4;
                    return;
                }

                return;
            }

        }

        /* =======================
         * PROFILE
         * ===================== */
        if ($_GET['site'] === 'profile') {

            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;

            /* --------------------------------------------
             * 0) Legacy: /profile/userid/1 | /profile/id/1 | /profile/user/1
             * -------------------------------------------- */
            if (
                $s2 !== null
                && in_array(strtolower($s2), ['userid', 'id', 'user'], true)
                && $s3 !== null
                && ctype_digit($s3)
            ) {
                $_GET['action'] = null;
                $_GET['userID'] = (int)$s3;
                return;
            }

            /* --------------------------------------------
             * 1) /{lang}/profile/{id}
             * -------------------------------------------- */
            if ($s2 !== null && ctype_digit($s2)) {
                $_GET['action'] = null;
                $_GET['userID'] = (int)$s2;
                return;
            }

            /* --------------------------------------------
             * 2) /{lang}/profile/{slug}/{id}
             * -------------------------------------------- */
            if ($s2 !== null && $s3 !== null && ctype_digit($s3)) {
                $_GET['slug']   = $s2;
                $_GET['userID'] = (int)$s3;
                return;
            }

            /* --------------------------------------------
             * âŒ nichts gefunden â†’ sauberes Profil-404
             * -------------------------------------------- */
            $_GET['userID'] = null;
            $_GET['profile_error'] = "not_found";
            return;
        }



        /* =======================
         * GAMETRACKER
         * ======================= */
        if ($_GET['site'] === 'gametracker') {

            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;

            if ($s2 && $s3 && ctype_digit($s3)) {
                $_GET['action'] = 'serverdetails';
                $_GET['id'] = $_GET['serverID'] = (int)$s3;
            }
            return;
        }

        /* =======================
         * DOWNLOADS
         * ======================= */
        if ($_GET['site'] === 'downloads') {

            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;

            if ($s2 === 'download' && ctype_digit($s3)) {
                $_GET['action'] = 'download';
                $_GET['id'] = (int)$s3;
                return;
            }

            if ($s2 === 'cat_list' && ctype_digit($s3)) {
                $_GET['action'] = 'cat_list';
                $_GET['categoryID'] = (int)$s3;
                return;
            }

            if ($s2 === 'detail' && ctype_digit($s3)) {
                $_GET['action'] = 'detail';
                $_GET['id'] = (int)$s3;
                return;
            }

            if ($s2 && ctype_digit($s2)) {
                $_GET['action'] = 'detail';
                $_GET['id'] = (int)$s2;
                return;
            }

            if ($s2 === 'page' && ctype_digit($s3)) {
                $_GET['page'] = (int)$s3;
                return;
            }
        }

        /* =======================
         * USERLIST ROUTING
         * ======================= */

        // ==================================================
        // ðŸ” USERLIST LEGACY â†’ SEO (MUSS VOR CANONICAL)
        // ==================================================
        if (
            ($_GET['site'] ?? null) === 'userlist'
            && (
                strpos($_SERVER['REQUEST_URI'], 'index.php') !== false
                || strpos($_SERVER['REQUEST_URI'], '/userlist/page/') !== false
                || isset($_GET['type'])
                || isset($_GET['order'])
            )
        ) {
            $lang = $_GET['lang'] ?? ($_SESSION['language'] ?? 'de');

            // Sort
            $sort = $_GET['sort'] ?? 'lastlogin';

            // Legacy type â†’ order
            if (isset($_GET['order'])) {
                $order = strtoupper($_GET['order']);
            } elseif (isset($_GET['type']) && strtoupper($_GET['type']) === 'DESC') {
                $order = 'DESC';
            } else {
                $order = 'ASC';
            }
            $order = ($order === 'DESC') ? 'DESC' : 'ASC';

            // Page IMMER Ã¼bernehmen
            $page = isset($_GET['page']) && (int)$_GET['page'] > 1
                ? (int)$_GET['page']
                : null;

            // Ziel bauen (SEO-korrekt)
            $target = "/{$lang}/userlist/sort/{$sort}/order/{$order}";
            if ($page !== null) {
                $target .= "/page/{$page}";
            }

            // ðŸ”’ Loop-Schutz
            $current = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            if ($current !== $target) {
                header("Location: {$target}", true, 301);
                exit;
            }
        }

        /* =======================
         * STANDARD KEY/VALUE
         * ======================= */
        $start = ($_GET['action'] === null) ? 2 : 3;
        for ($i = $start; $i < count($segments); $i += 2) {
            $key = $segments[$i] ?? null;
            $val = $segments[$i + 1] ?? null;
            if ($key === null || $val === null) continue;
            if (preg_match('/^([a-z]+)id$/i', $key, $m)) {
                $key = $m[1] . 'ID';
            }
            $_GET[$key] = is_numeric($val) ? (int)$val : $val;
        }

        return;
    }

    /* =======================
     * NON-SEO
     * ======================= */
    parse_str(parse_url($uri, PHP_URL_QUERY) ?: '', $queryParams);
    foreach ($queryParams as $k => $v) $_GET[$k] = $v;
    $_GET['lang'] = $_GET['lang'] ?? 'de';
}




public static function enforceCanonical(): void
{
    $requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');

    // Guard against redirect loops on hosts that rewrite pretty URLs back to index.php.
    // For direct query URLs we keep the request as-is instead of forcing a canonical redirect.
    if ($requestPath === '/index.php' && !empty($_SERVER['QUERY_STRING'])) {
        return;
    }

    $lang = $_GET['lang'] ?? ($_SESSION['language'] ?? 'de');
    $query = $_GET;


    // ðŸ”¥ LEGACY-FIX: type â†’ order
    if (isset($query['type']) && !isset($query['order'])) {
        $query['order'] = strtoupper($query['type']) === 'DESC' ? 'DESC' : 'ASC';
        unset($query['type']);
    }

    $segments = [$lang, 'userlist'];

    if (!empty($query['sort'])) {
        $segments[] = 'sort';
        $segments[] = $query['sort'];
    }

    if (!empty($query['order'])) {
        $segments[] = 'order';
        $segments[] = $query['order'];
    }

    $seoUrl = '/' . implode('/', $segments);



    // ðŸ”’ SEO-URL hat Vorrang vor Query-Parametern
    if (
        strpos($_SERVER['REQUEST_URI'], '?') !== false
        && preg_match('#^/[a-z]{2}/userlist/#', $_SERVER['REQUEST_URI'])
    ) {
        // Query-String komplett entfernen
        $clean = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        header("Location: {$clean}", true, 301);
        exit;
    }



    // ðŸ”’ Pagination niemals canonicalisieren
    if (isset($_GET['page']) && (int)$_GET['page'] > 1) {
        return;
    }
    if (!defined('USE_SEO_URLS') || !USE_SEO_URLS) return;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;
    if (strpos($_SERVER['REQUEST_URI'], '/admin') === 0) return;

    // ðŸ”’ setlang darf NIE canonical triggern
    if (isset($_GET['setlang']) || strpos($_SERVER['REQUEST_URI'], 'setlang=') !== false) {
        return;
    }

    // ðŸ”’ erst Canonical, wenn Routing sauber ist
    if (empty($_GET['site'])) return;

    $lang    = $_GET['lang'] ?? 'de';
    $current = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    // ðŸ”’ STARTSEITE: Canonical DARF HIER NIE LAUFEN
    // /it , /it/ , /it/index
    if (($_GET['site'] ?? '') === 'index') {
        return;
    }

    // interne Helper-Keys entfernen
    foreach ([
        'new_lang','setlang',
        'action_showthread','action_showpost','action_showcategory'
    ] as $k) {
        unset($_GET[$k]);
    }

    // âœ… Eindeutige EntitÃ¤ten BEVORZUGEN
    /*if (isset($_GET['threadID'])) {
        $canonical = "/{$lang}/forum/thread/{$_GET['threadID']}";
    }*/
    if (
        isset($_GET['threadID']) &&
        ($_GET['action'] ?? null) === 'thread'
    ) {
        $canonical = "/{$lang}/forum/thread/{$_GET['threadID']}";
    }
    elseif (isset($_GET['categoryID']) && $_GET['site'] === 'forum') {
        $canonical = "/{$lang}/forum/category/{$_GET['categoryID']}";
    }
    elseif (isset($_GET['userID']) && $_GET['site'] === 'profile') {
        $canonical = "/{$lang}/profile/{$_GET['userID']}";
    }
    else {
        $canonical = self::convertToSeoUrl(
            'index.php?' . http_build_query($_GET)
        );
        $canonical = rtrim(parse_url($canonical, PHP_URL_PATH), '/');
    }

    if (!empty($canonical) && $current !== rtrim($canonical, '/')) {
        header("Location: {$canonical}", true, 301);
        exit;
    }
}




    /**
     * Wandelt einen Query-String in eine SEO-URL um
     */
    /**
     * Wandelt einen Query-String in eine SEO-URL um
     */
    public static function convertToSeoUrl(string $url): string
    {
        if (!defined('USE_SEO_URLS') || !USE_SEO_URLS) return $url;

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);

        $lang   = $query['lang'] ?? ($_SESSION['language'] ?? 'de');
        $site   = $query['site'] ?? 'index';
        $action = $query['action'] ?? null;
        $id     = isset($query['id']) ? (int)$query['id'] : null;
        $cat    = isset($query['cat']) ? (int)$query['cat'] : null;
        $slug   = $query['slug'] ?? null;

        // Startseite: kein "/index" im SEO-Pfad ausgeben
        $segments = [$lang];
        if ($site !== 'index') {
            $segments[] = $site;
        }

/* =======================
 * FORUM â€“ QUOTE (FIXED)
 * ======================= */
if ($site === 'forum' && $action === 'quote') {

    $postID   = $query['postID']   ?? null;
    $threadID = $query['threadID'] ?? null;

    // Fallback: falls Controller nur id setzt
    if (!$postID && isset($query['id'])) {
        $postID = (int)$query['id'];
    }

    if ($postID && $threadID) {
        return '/' . $lang
            . '/forum/quote/postid/' . (int)$postID
            . '/threadid/' . (int)$threadID;
    }
}

        if ($site === 'forum' && $action === 'new_thread') {
            $catID = isset($query['catID'])
                ? (int)$query['catID']
                : (isset($query['catid']) ? (int)$query['catid'] : null);

            if ($catID !== null && $catID > 0) {
                return '/' . $lang . '/forum/new_thread/catid/' . $catID;
            }

            return '/' . $lang . '/forum/new_thread';
        }

        if ($site === 'forum' && $action === 'reply') {
            $threadID = isset($query['threadID'])
                ? (int)$query['threadID']
                : (isset($query['threadid']) ? (int)$query['threadid'] : null);

            if (!$threadID && $id !== null) {
                $threadID = $id;
            }

            if ($threadID) {
                return '/' . $lang . '/forum/reply/threadid/' . $threadID;
            }

            return '/' . $lang . '/forum';
        }
        // Speziell fÃ¼r News: immer Slug nutzen, auch ohne action
        if ($site === 'news') {
            if ($slug) {
                $segments[] = $slug;
            } elseif ($id !== null) {
                $segments[] = $id;
            }
            unset($query['action'], $query['slug'], $query['id']);
        } else {
            // sonst wie bisher
            if ($action) {
                $segments[] = $action;
                unset($query['action']);
            }

            if ($slug) {
                $segments[] = $slug;
                unset($query['slug'], $query['id']);
            } elseif ($id !== null) {
                $segments[] = $id;
                unset($query['id']);
            }
        }

        if ($cat !== null) {
            $segments[] = 'cat';
            $segments[] = $cat;
            unset($query['cat']);
        }

        unset($query['lang'], $query['site']);

        // Restliche Query-Parameter hinten anhÃ¤ngen
        foreach ($query as $key => $value) {

            // ðŸ”’ Leere / sinnlose Werte niemals als Segment schreiben
            if (
                $value === null
                || $value === ''
                || (is_string($value) && trim($value) === '')
            ) {
                continue;
            }

            $segments[] = strtolower($key);
            $segments[] = rawurlencode((string)$value);
        }


        $seoUrl = '/' . implode('/', $segments);

        // ðŸ”¥ DOPPELTE SLASHES ENTFERNEN (PATCH)
        $seoUrl = preg_replace('#/{2,}#', '/', $seoUrl);

        if (isset($parsed['fragment'])) {
            $seoUrl .= '#' . $parsed['fragment'];
        }

        return $seoUrl;
    }


    /**
     * Liest SEO-URL und schreibt $_GET-Werte
     */
    public static function parseSeoUrl()
    {

        $uriPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $segments = explode('/', $uriPath);

        $params = [];

        // Sprache
        $params['lang'] = $segments[0] ?? 'de';

        // Site
        $params['site'] = $segments[1] ?? 'index';

        // Action
        if (isset($segments[2]) && !is_numeric($segments[2])) {
            $params['action'] = $segments[2];
            $startIndex = 3;
        } else {
            $startIndex = 2;
        }

        // Rest als Key/Value-Paare
        for ($i = $startIndex; $i < count($segments); $i += 2) {
            $key = strtolower($segments[$i] ?? '');
            $val = $segments[$i + 1] ?? null;

            if ($key === '' || $val === null) continue;

            switch ($key) {
                case 'postid':
                    $params['postID'] = $val;
                    if ($params['action'] === 'quote') {
                        $params['id'] = $val;
                    } elseif (!isset($params['postID'])) {
                        $params['id'] = $val;
                    }
                    break;

                case 'threadid':
                    $params['threadID'] = $val;
                    if (!isset($params['id']) && $params['action'] !== 'quote') {
                        $params['id'] = $val;
                    }
                    break;

                default:
                    $params[$key] = $val;
                    break;
            }
        }

        // $_GET fÃ¼llen
        foreach ($params as $k => $v) {
            $_GET[$k] = $v;
        }

        return $params;
    }

    public static function buildPluginUrl(string $type, int $id, string $lang = 'de', $db = null): string
    {
        switch ($type) {
            case 'plugins_articles':
                $url = "index.php?lang={$lang}&site=articles&action=watch&id={$id}";
                break;

            case 'plugins_forum_threads':
                $threadTitle = self::getThreadTitle($id);
                $slug = $threadTitle ? self::slugify($threadTitle) : "thread{$id}";
                $url = "index.php?lang={$lang}&site=forum&action=showthread&threadID={$id}&slug={$slug}";
                break;

            case 'plugins_forum_posts':
                $threadId = self::getThreadIdByPost($id);
                $postTitle = self::getPostTitle($id);
                $slug = $postTitle ? self::slugify($postTitle) : "post{$id}";

                if ($threadId > 0) {
                    $url = "index.php?lang={$lang}&site=forum&action=showthread&threadID={$threadId}#{$slug}";
                } else {
                    $url = "index.php?lang={$lang}&site=forum&action=showpost&postID={$id}&slug={$slug}";
                }
                break;

            case 'plugins_news_categories': 
                $catSlug = self::getCategorySlug($id)
                    ?: (self::getCategoryTitle($id) ? self::slugify(self::getCategoryTitle($id)) : "category{$id}");

                if (defined('USE_SEO_URLS') && USE_SEO_URLS) {
                    $url = "index.php?site=news&slug={$catSlug}";     // KEIN /news/... direkt!
                } else {
                    $url = "index.php?site=news&cat={$id}";
                }
                break;

            case 'plugins_news': 
                $newsSlug = self::getNewsSlug($id)
                    ?: (self::getNewsTitle($id) ? self::slugify(self::getNewsTitle($id)) : null);

                if (defined('USE_SEO_URLS') && USE_SEO_URLS) {
                    $url = $newsSlug
                        ? "index.php?site=news&slug={$newsSlug}"      // KEIN /news/... direkt!
                        : "index.php?site=news&id={$id}";
                } else {
                    $url = "index.php?site=news&newsID={$id}";
                }
                break;

            case 'plugins_gallery':
                $url = "index.php?lang={$lang}&site=gallery&picID={$id}";
                break;

            case 'plugins_downloads':
                $downloadTitle = self::getDownloadTitle($id);
                $slug = $downloadTitle ? self::slugify($downloadTitle) : "download{$id}";
                $url = "index.php?lang={$lang}&site=downloads&action=show&id={$id}&slug={$slug}";
                break;

            case 'plugins_userlist':
                $userName = self::getUserName($id);
                $slug = $userName ? self::slugify($userName) : "user{$id}";
                $url = "index.php?lang={$lang}&site=user&id={$id}&slug={$slug}";
                break;

            case 'plugins_team':
                $memberName = self::getTeamMemberName($id);
                $slug = $memberName ? self::slugify($memberName) : "member{$id}";
                $url = "index.php?lang={$lang}&site=team&action=member&id={$id}&slug={$slug}";
                break;

            case 'plugins_calendar':
                $eventTitle = self::getEventTitle($id);
                $slug = $eventTitle ? self::slugify($eventTitle) : "event{$id}";
                $url = "index.php?lang={$lang}&site=calendar&action=show&id={$id}&slug={$slug}";
                break;

            case 'plugins_gametracker':
                $action = $db['action'] ?? '';
                $id     = $db['id'] ?? 0;
                $url = "index.php?lang={$lang}&site=gametracker";
                if ($action) $url .= "&action={$action}";
                if ($id > 0) $url .= "&id={$id}";
                break;

            case 'plugins_messenger':
                $threadId = $db['thread'] ?? null;
                $page = $db['page'] ?? null;
                $url = "index.php?lang={$lang}&site=messenger";
                if ($threadId !== null) $url .= "&thread={$threadId}";
                if ($page !== null) $url .= "&page={$page}";
                break;

            case 'plugins_raidplaner':
                $url = "index.php?lang={$lang}&site=raidplaner&action=show&id={$id}";
                break;    

            case 'plugins_wiki':
                $action = $db['action'] ?? '';
                $id     = isset($db['id']) ? (int)$db['id'] : 0;
                $catId  = $db['cat'] ?? null;

                $url = "index.php?lang={$lang}&site=wiki";

                if ($action !== '') {
                    $url .= "&action={$action}";
                }

                if ($id > 0) {
                    $url .= "&id={$id}";
                }

                if ($catId !== null) {
                    $url .= "&cat={$catId}";
                }
                break;    

            default:
                $url = "index.php?lang={$lang}&site=plugin&plugin={$type}&id={$id}";
                break;
        }

        return self::convertToSeoUrl($url);
    }

    /**
     * Slugify-Funktion innerhalb der Klasse
     */
    public static function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text); // Leerzeichen zu Bindestrichen
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // Umlaute & Sonderzeichen
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return $text ?: 'item';
    }

    public static function getNewsSlug(int $id): string
    {
        $result = safe_query("SELECT slug, title FROM plugins_news WHERE id = " . intval($id));
        if ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['slug'])) {
                return $row['slug'];
            }
            // Fallback: Titel in Slug umwandeln
            return self::slugify($row['title']);
        }
        return 'news' . $id;
    }

    public static function getCategorySlug(int $id): ?string {
        global $_database;
        $slug = null;
        $stmt = $_database->prepare("SELECT slug FROM plugins_news_categories WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($slug);
        $stmt->fetch();
        $stmt->close();
        return $slug ?: null;
    }

    public static function getCategoryTitle(int $id): ?string {
        global $_database;
        $title = null;
        $stmt = $_database->prepare("SELECT name FROM plugins_news_categories WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($title);
        $stmt->fetch();
        $stmt->close();
        return $title ?: null;
    }

    /**
     * Hilfsmethode: Thread-ID anhand der Post-ID ermitteln
     */
    protected static function getThreadIdByPost(int $postID): int
    {
        global $_database;
        $sql = "SELECT threadID FROM plugins_forum_posts WHERE postID = ?";
        $stmt = $_database->prepare($sql);
        $stmt->bind_param('i', $postID);
        $stmt->execute();
        $stmt->bind_result($threadID);
        $stmt->fetch();
        $stmt->close();
        return $threadID ?? 0;
    }

    private static function getPostTitle(int $postID): ?string
    {
        $db = $GLOBALS['db'] ?? null; // DB-Objekt holen
        if (!$db) return null; // kein DB-Objekt vorhanden

        $query = $db->prepare("SELECT title FROM " . PREFIX . "plugins_forum_posts WHERE id = ?");
        $query->execute([$postID]);
        $row = $query->fetch();

        return $row ? $row['title'] : null;
    }

    private static function getThreadTitle(int $threadID): ?string
    {
        global $db;

        $query = $db->prepare("SELECT title FROM " . PREFIX . "plugins_forum_threads WHERE id = ?");
        $query->execute([$threadID]);
        $row = $query->fetch();

        return $row ? $row['title'] : null;
    }

    // Beispiel fÃ¼r News
    private static function getNewsTitle(int $newsID): ?string
    {
        global $db;

        $query = $db->prepare("SELECT title FROM " . PREFIX . "plugins_news WHERE id = ?");
        $query->execute([$newsID]);
        $row = $query->fetch();

        return $row ? $row['title'] : null;
    }

}

