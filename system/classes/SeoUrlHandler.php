<?php
namespace nexpell;


class SeoUrlHandler {


    public static function route(?string $uri = null): void 
    {
        $uri = $uri ?? $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));

        if (isset($segments[0]) && preg_match('/^[a-z]{2}$/i', $segments[0])) {
            $_GET['lang'] = strtolower($segments[0]);
            $_GET['site'] = $segments[1] ?? 'index';

            // WICHTIG: Hier sowohl alte als auch neue Aktionsnamen erlauben
            $knownActions = [
                'show','watch','deletecomment','edit','new','list',

                // Forum
                'thread','post','category',
                'showthread','showpost','showcategory',

                // WICHTIG
                'new_thread',
                'quote',
                'quote_reply', 
                'edit_post',
                'delete_post',
                'reply',
            ];
            $_GET['action'] = (isset($segments[2]) && in_array(strtolower($segments[2]), $knownActions, true))
                ? strtolower($segments[2])
                : null;

            /* =======================
             * NEWS: Slug/ID/Pagination
             * ======================= */
            if (($_GET['site'] ?? '') === 'news') {

    $s2 = $segments[2] ?? null;
    $s3 = $segments[3] ?? null;
    $s4 = $segments[4] ?? null;

    $reservedNewsKeywords = ['page'];

    /* --------------------------------------------------
     * /{lang}/news/page/5  → Übersicht
     * -------------------------------------------------- */
    if ($s2 !== null && strtolower($s2) === 'page' && ctype_digit((string)$s3)) {
        $_GET['page'] = (int)$s3;

        // 🔒 ABSOLUTER SCHUTZ
        $_GET['action'] = null;
        unset($_GET['id'], $_GET['newsID'], $_GET['slug']);

        return;
    }

    /* --------------------------------------------------
     * /{lang}/news/{slug|id}/page/2 → Kategorie
     * -------------------------------------------------- */
    if (
        $s2 !== null
        && $s3 !== null
        && !in_array(strtolower($s2), $reservedNewsKeywords, true)
        && strtolower($s3) === 'page'
        && ctype_digit((string)$s4)
    ) {
        if (ctype_digit((string)$s2)) {
            $_GET['id'] = (int)$s2;
            $_GET['newsID'] = (int)$s2;
        } else {
            $_GET['slug'] = $s2;
        }

        $_GET['page'] = (int)$s4;
        return;
    }

    /* --------------------------------------------------
     * /{lang}/news/{slug|id} → Detail / Kategorie
     * -------------------------------------------------- */
    if (
        $s2 !== null
        && !in_array(strtolower($s2), $reservedNewsKeywords, true)
        && !in_array(strtolower($s2), $knownActions, true)
    ) {
        if (ctype_digit((string)$s2)) {
            $_GET['id'] = (int)$s2;
            $_GET['newsID'] = (int)$s2;
        } else {
            $_GET['slug'] = $s2;
        }
        return;
    }
}


            /* =======================
             * FORUM: Threads/Posts/etc.
             * Setzt IMMER alle kompatiblen Keys,
             * damit alte Controller-Branches weiter laufen.
             * ======================= */
            if (($_GET['site'] ?? '') === 'forum') {
                $s2 = $segments[2] ?? null; // thread | post | category | showthread | showpost | showcategory | page
                $s3 = $segments[3] ?? null; // id | id | id | id | id | id | number
                $s4 = $segments[4] ?? null; // page |    | page | page |     | page | (optional "page")
                $s5 = $segments[5] ?? null; // number |  | number | number | | number | (page number)

                // Helper zum Setzen aller Varianten für Thread/Post/Category
                $setThreadKeys = function(int $id) {
                    $_GET['thread']   = $id;   // legacy
                    $_GET['threadID'] = $id;   // häufig verwendet
                    $_GET['id']       = $id;   // manche Controller nehmen id
                };
                $setPostKeys = function(int $id) {
                    $_GET['post']   = $id;
                    $_GET['postID'] = $id;
                    $_GET['id']     = $id;
                };
                $setCategoryKeys = function(int $id) {
                    $_GET['category']   = $id;
                    $_GET['categoryID'] = $id;
                    $_GET['id']         = $id;
                };

                // A) /{lang}/forum/thread/{id}[/page/{n}]
                if ($s2 !== null && strtolower($s2) === 'thread' && ctype_digit((string)$s3)) {
                    $tid = (int)$s3;
                    $setThreadKeys($tid);
                    // beide Aktions-Varianten setzen:
                    $_GET['action'] = $_GET['action'] ?? 'thread';
                    $_GET['action_showthread'] = 'showthread'; // nur Info; stört nicht
                    // optional page
                    if ($s4 !== null && strtolower($s4) === 'page' && ctype_digit((string)$s5)) {
                        $_GET['page'] = (int)$s5;
                    }
                    return;
                }

                // B) /{lang}/forum/post/{id}
                if ($s2 !== null && strtolower($s2) === 'post' && ctype_digit((string)$s3)) {
                    $pid = (int)$s3;
                    $setPostKeys($pid);
                    $_GET['action'] = $_GET['action'] ?? 'post';   // legacy
                    $_GET['action_showpost'] = 'showpost';
                    return;
                }

                // C) /{lang}/forum/category/{id}[/page/{n}]
                if ($s2 !== null && strtolower($s2) === 'category' && ctype_digit((string)$s3)) {
                    $cid = (int)$s3;
                    $setCategoryKeys($cid);
                    $_GET['action'] = $_GET['action'] ?? 'category';
                    $_GET['action_showcategory'] = 'showcategory';
                    if ($s4 !== null && strtolower($s4) === 'page' && ctype_digit((string)$s5)) {
                        $_GET['page'] = (int)$s5;
                    }
                    return;
                }

                // D) /{lang}/forum/page/{n}  → Foren-Übersicht paginiert
                if ($s2 !== null && strtolower($s2) === 'page' && ctype_digit((string)$s3)) {
                    $_GET['page'] = (int)$s3;
                    return;
                }

                // E) Direkt-Aktionspfade:
                //    /{lang}/forum/showthread/{id}[/page/{n}]
                if ($s2 !== null && strtolower($s2) === 'showthread' && ctype_digit((string)$s3)) {
                    $tid = (int)$s3;
                    $setThreadKeys($tid);
                    $_GET['action'] = 'showthread';
                    if ($s4 !== null && strtolower($s4) === 'page' && ctype_digit((string)$s5)) {
                        $_GET['page'] = (int)$s5;
                    }
                    return;
                }
                //    /{lang}/forum/showpost/{id}
                if ($s2 !== null && strtolower($s2) === 'showpost' && ctype_digit((string)$s3)) {
                    $pid = (int)$s3;
                    $setPostKeys($pid);
                    $_GET['action'] = 'showpost';
                    return;
                }
                //    /{lang}/forum/showcategory/{id}[/page/{n}]
                if ($s2 !== null && strtolower($s2) === 'showcategory' && ctype_digit((string)$s3)) {
                    $cid = (int)$s3;
                    $setCategoryKeys($cid);
                    $_GET['action'] = 'showcategory';
                    if ($s4 !== null && strtolower($s4) === 'page' && ctype_digit((string)$s5)) {
                        $_GET['page'] = (int)$s5;
                    }
                    return;
                }

                // F) /{lang}/forum/quote/postid/{pid}/threadid/{tid}
                if ($s2 !== null && strtolower($s2) === 'quote') {

                    $_GET['action'] = 'quote';

                    // ❗ KEIN return hier
                    // postid / threadid werden im Standard-Pair-Parser verarbeitet
                }
                // G) /{lang}/forum/quote_reply/threadid/{tid}
                if ($s2 !== null && strtolower($s2) === 'quote_reply') {

                    $_GET['action'] = 'quote_reply';

                    // KEIN return!
                    // threadid wird unten im Standard-Key/Value-Parser gesetzt
                }

                // G) /{lang}/forum/edit_post/postid/{pid}
                if ($s2 !== null && strtolower($s2) === 'edit_post') {
                    $_GET['action'] = 'edit_post';
                    // KEIN return → postid wird unten gemappt
                }

                // H) /{lang}/forum/new_thread/catid/{id}
                // /{lang}/forum/new_thread/catid/{id} → host/.../forum/new_thread/catid/1
                if ($s2 !== null && strtolower($s2) === 'new_thread') {
                    // Setze die richtige Action
                    $_GET['action'] = 'new_thread';

                    // KEIN return – damit unten catid/1 als Key/Value geparst wird
                    // und zu $_GET['catID'] wird
                }

                // ansonsten weiter zu Standard-Pairs
            }

            /* --------------------------------------
             * FORUM OVERVIEW PAGINATION
             * /{lang}/forum/overview/{page}
             * -------------------------------------- */
            $s2 = $segments[2] ?? null;
            $s3 = $segments[3] ?? null;

            if ($s2 !== null && strtolower($s2) === 'overview' && isset($s3) && ctype_digit($s3)) {
                $_GET['action'] = 'overview';
                $_GET['page']   = (int)$s3;
                return;
            }


            /* ============================================================
             * PROFILE ROUTING – SEO + SLUG + KOMPATIBEL + CANONICAL
             * ============================================================ */
            if (($_GET['site'] ?? '') === 'profile') {

                $s2 = $segments[2] ?? null;
                $s3 = $segments[3] ?? null;

                // --------------------------------------------
                // 1. /{lang}/profile/{id}
                // --------------------------------------------
                if ($s2 !== null && ctype_digit($s2)) {
                    $_GET['userID'] = (int)$s2;
                    return;
                }

                // --------------------------------------------
                // 2. /{lang}/profile/{slug}/{id}
                // --------------------------------------------
                if ($s2 !== null && $s3 !== null && ctype_digit($s3)) {
                    $_GET['userID'] = (int)$s3;
                    $_GET['slug'] = $s2;
                    return;
                }

                // --------------------------------------------
                // 3. /{lang}/profile/{slug} → slug = username
                //    Prüfen, ob slug eindeutig einem User zugeordnet ist
                // --------------------------------------------
                if ($s2 !== null && !ctype_digit($s2)) {

                    global $_database;

                    $stmt = $_database->prepare("
                        SELECT userID 
                        FROM user 
                        WHERE LOWER(username) = LOWER(?)
                        LIMIT 1
                    ");
                    $stmt->bind_param("s", $s2);
                    $stmt->execute();
                    $stmt->bind_result($uid);
                    $stmt->fetch();
                    $stmt->close();

                    if (!empty($uid)) {
                        $_GET['userID'] = (int)$uid;
                        $_GET['slug'] = $s2;
                        return;
                    }
                }

                // --------------------------------------------
                // 4. Alte URL-Variante: /profile/id/2
                // --------------------------------------------
                if ($s2 === "id" && $s3 !== null && ctype_digit($s3)) {
                    $_GET['userID'] = (int)$s3;
                    return;
                }

                // --------------------------------------------
                // 5. Wenn nichts passt → Profil 404
                // --------------------------------------------
                $_GET['userID'] = null;
                $_GET['profile_error'] = "not_found";
                return;
            }

            /* =======================
             * GAMETRACKER ROUTING
             * ======================= */
            if (($_GET['site'] ?? '') === 'gametracker') {

                $s2 = $segments[2] ?? null;
                $s3 = $segments[3] ?? null;

                // /{lang}/gametracker/serverdetails/{id}
                if ($s2 && strtolower($s2) === 'serverdetails' && $s3 && ctype_digit($s3)) {
                    $_GET['action'] = 'serverdetails';
                    $_GET['id'] = (int)$s3;
                    $_GET['serverID'] = (int)$s3;
                    return;
                }

                // /{lang}/gametracker/server/{id}
                if ($s2 && strtolower($s2) === 'server' && $s3 && ctype_digit($s3)) {
                    $_GET['action'] = 'serverdetails';
                    $_GET['id'] = (int)$s3;
                    $_GET['serverID'] = (int)$s3;
                    return;
                }

                // /{lang}/gametracker/details/{id}
                if ($s2 && strtolower($s2) === 'details' && $s3 && ctype_digit($s3)) {
                    $_GET['action'] = 'serverdetails';
                    $_GET['id'] = (int)$s3;
                    $_GET['serverID'] = (int)$s3;
                    return;
                }

                // Übersicht bleibt wie sie ist
                return;
            }

            /* =======================
             * DOWNLOADS
             * /{lang}/downloads/detail/{id}
             * /{lang}/downloads/{id}
             * ======================= */
            /* DOWNLOADS Routing (HIER MUSS ES HIN) */
            if (($_GET['site'] ?? '') === 'downloads') {
                $s2 = $segments[2] ?? null;
                $s3 = $segments[3] ?? null;

                // ✅ DOWNLOAD DATEI
                if ($s2 === 'download' && ctype_digit((string)$s3)) {
                    $_GET['action'] = 'download';
                    $_GET['id']     = (int)$s3;
                    return;
                }

                // Kategorie
                if ($s2 === 'cat_list' && ctype_digit((string)$s3)) {
                    $_GET['action']     = 'cat_list';
                    $_GET['categoryID'] = (int)$s3;
                    return;
                }

                // Detailseite
                if ($s2 === 'detail' && ctype_digit((string)$s3)) {
                    $_GET['action'] = 'detail';
                    $_GET['id']     = (int)$s3;
                    return;
                }

                // Kurzform: /downloads/{id}
                if ($s2 !== null && ctype_digit((string)$s2)) {
                    $_GET['action'] = 'detail';
                    $_GET['id']     = (int)$s2;
                    return;
                }

                // Pagination
                if ($s2 === 'page' && ctype_digit((string)$s3)) {
                    $_GET['page'] = (int)$s3;
                    return;
                }
            }





            // ===== Standard Key/Value-Paare ab Segment 2/3 =====
            $start = ($_GET['action'] === null) ? 2 : 3;
            for ($i = $start; $i < count($segments); $i += 2) {
                $key = $segments[$i] ?? null;
                $val = $segments[$i + 1] ?? null;
                if ($key === null || $val === null) continue;
                if (preg_match('/^([a-z]+)id$/i', $key, $m)) $key = $m[1] . 'ID';
                $_GET[$key] = is_numeric($val) ? (int)$val : $val;
            }
        } else {
            // klassische query-Parameter
            parse_str(parse_url($uri, PHP_URL_QUERY) ?: '', $queryParams);
            foreach ($queryParams as $k => $v) $_GET[$k] = $v;

            // Kompatibilität für News (Non-SEO)
            if (($_GET['site'] ?? '') === 'news') {
                if (!empty($_GET['newsID']) && empty($_GET['id']))     $_GET['id']     = (int) $_GET['newsID'];
                if (!empty($_GET['id'])     && empty($_GET['newsID'])) $_GET['newsID'] = (int) $_GET['id'];
                if (!empty($_GET['newsID']) && empty($_GET['action'])) $_GET['action'] = 'show';
            }

            $_GET['lang'] = $_GET['lang'] ?? 'de';
        }
    }

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

        $segments = [$lang, $site];

        // Speziell für News: immer Slug nutzen, auch ohne action
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

        // Restliche Query-Parameter hinten anhängen
        foreach ($query as $key => $value) {
            if ($value === null) continue;
            $segments[] = strtolower($key);
            $segments[] = $value;
        }

        $seoUrl = '/' . implode('/', $segments);

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
                case 'postID':
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

        // $_GET füllen
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

            case 'plugins_wiki':
                $catId = $db['cat'] ?? null;
                $url = "index.php?lang={$lang}&site=wiki";
                if ($catId !== null) $url .= "&cat={$catId}";
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
        $query->execute([$threadId]);
        $row = $query->fetch();

        return $row ? $row['title'] : null;
    }

    // Beispiel für News
    private static function getNewsTitle(int $newsID): ?string
    {
        global $db;

        $query = $db->prepare("SELECT title FROM " . PREFIX . "plugins_news WHERE id = ?");
        $query->execute([$newsId]);
        $row = $query->fetch();

        return $row ? $row['title'] : null;
    }

}
