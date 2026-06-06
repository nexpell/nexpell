<?php

use nexpell\LanguageService;

function settitle($string)
{
    $base_title = isset($GLOBALS['hp_title']) ? $GLOBALS['hp_title'] : 'Website';
    return $base_title . ' - ' . $string;
}

function extractFirstElement($element)
{
    return is_array($element) && isset($element[0]) ? $element[0] : '';
}

function getPageTitle($url = null, $prefix = true)
{
    $data = parsenexpellURL($url);

    if (!is_array($data)) {
        return $prefix ? settitle('') : '';
    }

    // Metatags zusammenführen
    if (isset($data['metatags']) && is_array($data['metatags'])) {
        if (isset($GLOBALS['metatags']) && is_array($GLOBALS['metatags'])) {
            $GLOBALS['metatags'] = array_merge($GLOBALS['metatags'], $data['metatags']);
        } else {
            $GLOBALS['metatags'] = $data['metatags'];
        }
    }

    // Seitentitel erzeugen
    $titles = array();
    if (isset($data['titles']) && is_array($data['titles'])) {
        $titles = array_map("extractFirstElement", $data['titles']);
    }

    $title = implode('&nbsp;&raquo;&nbsp;', array_filter($titles));
    return $prefix ? settitle($title) : $title;
}


function parsenexpellURL($parameters = null)
{ 

global $languageService;
global $_database;

if (!isset($languageService) || !$languageService instanceof LanguageService) {
    $languageService = new LanguageService($_database);
}

    if ($parameters === null) {
        $parameters = $_GET;
    }

    if (isset($parameters['action'])) {
        $action = $parameters['action'];
    } else {
        $action = '';
    }

    $returned_title = array();
    $metadata = array();
    if (isset($parameters['site'])) {
        switch ($parameters['site']) {

            case 'about':
                $result = safe_query("SELECT title, intro FROM plugins_about ORDER BY id ASC LIMIT 1");
                $about = mysqli_fetch_assoc($result);

                if ($about && !empty($about['title'])) {
                    $returned_title[] = [
                        $languageService->get('about'),
                        'index.php?site=about'
                    ];
                    $returned_title[] = [$about['title']];

                    // Meta Description aus Intro (auf ~160 Zeichen beschränken)
                    $intro_excerpt = strip_tags($about['intro']);
                    if (strlen($intro_excerpt) > 160) {
                        $intro_excerpt = substr($intro_excerpt, 0, 157) . '...';
                    }
                    $metadata['description'] = $intro_excerpt;

                    // Optional: Meta Keywords aus Intro erzeugen (vereinfacht)
                    $keywords = implode(', ', array_slice(array_unique(explode(' ', strtolower($intro_excerpt))), 0, 10));
                    $metadata['keywords'] = $keywords;
                } else {
                    $returned_title[] = [$languageService->get('about')];
                }
                break;


            case 'articles':
                $id = isset($parameters['id']) ? (int)$parameters['id'] : 0;
                $articleID = isset($parameters['articleID']) ? (int)$parameters['articleID'] : 0;

                // Kategorie holen (inkl. Beschreibung)
                $category = null;
                if ($id > 0) {
                    $result = safe_query("SELECT name, description FROM plugins_articles_categories WHERE id = $id");
                    if ($row = mysqli_fetch_assoc($result)) {
                        $category = $row;
                    }
                }

                // Artikel holen (inkl. Content)
                $article = null;
                if ($articleID > 0) {
                    $result2 = safe_query("SELECT title, content FROM plugins_articles WHERE id = $articleID");
                    if ($row2 = mysqli_fetch_assoc($result2)) {
                        $article = $row2;
                    }
                }

                if ($action === 'articlecat') {
                    $returned_title[] = [$languageService->get('articles'), 'index.php?site=articles'];

                    if ($category) {
                        $returned_title[] = [$category['name']];
                        // Meta-Beschreibung aus Kategorie-Beschreibung (max. 160 Zeichen, ohne HTML)
                        $metadata['description'] = mb_substr(strip_tags($category['description']), 0, 160);
                    }
                } elseif ($action === 'articles') {
                    $returned_title[] = [$languageService->get('articles'), 'index.php?site=articles'];

                    if ($category) {
                        $returned_title[] = [
                            $category['name'],
                            'index.php?site=articles&amp;action=articlecat&amp;id=' . $id
                        ];
                    }

                    if ($article) {
                        $returned_title[] = [$article['title']];
                        // Meta-Beschreibung aus Artikel-Content (max. 160 Zeichen, ohne HTML)
                        $metadata['description'] = mb_substr(strip_tags($article['content']), 0, 160);
                        // Keywords aus Tags
                        $metadata['keywords'] = \nexpell\Tags::getTags('articles', $articleID);
                    }
                } else {
                    $returned_title[] = [$languageService->get('articles')];
                }
                break;


            case 'pricing':
                $planID = isset($parameters['planID']) ? (int)$parameters['planID'] : 0;

                // Alle Pläne holen (für Übersichtsseite)
                if ($planID === 0) {
                    $returned_title[] = [$languageService->get('pricing')];
                }
                // Einzelnen Plan mit Features holen (für Detailseite)
                else {
                    $plan = null;
                    $features = [];

                    // Plan holen
                    $resultPlan = safe_query("SELECT title FROM plugins_pricing_plans WHERE id = $planID");
                    if ($rowPlan = mysqli_fetch_assoc($resultPlan)) {
                        $plan = $rowPlan;
                    }

                    // Features holen
                    $resultFeatures = safe_query("SELECT feature_text FROM plugins_pricing_features WHERE plan_id = $planID AND available = 1 ORDER BY id ASC");
                    while ($rowFeature = mysqli_fetch_assoc($resultFeatures)) {
                        $features[] = $rowFeature['feature_text'];
                    }

                    // Titel setzen
                    if ($plan) {
                        $returned_title[] = [$languageService->get('pricing'), 'index.php?site=pricing'];
                        $returned_title[] = [$plan['title']];

                        // Keywords aus Features als CSV
                        $metadata['keywords'] = implode(', ', $features);
                        // Meta-Description: Plan-Titel plus kurze Feature-Liste (max. 160 Zeichen)
                        $metaDescription = $plan['title'] . ': ' . implode(', ', $features);
                        $metadata['description'] = mb_substr($metaDescription, 0, 160);
                    } else {
                        // Fallback
                        $returned_title[] = [$languageService->get('pricing')];
                    }
                }
                break;
    
   

            case 'awards':
                if (isset($parameters['awardID'])) {
                    $awardID = (int)$parameters['awardID'];
                } else {
                    $awardID = '';
                }
                if ($action == "details") {
                    $get = mysqli_fetch_array(
                        safe_query("SELECT award FROM plugins_awards WHERE awardID=" . (int)$awardID)
                    );
                    $returned_title[] = array(
                        $languageService->get('awards'),
                        'index.php?site=awards'
                    );
                    $returned_title[] = array($get['award']);
                } else {
                    $returned_title[] = array($languageService->get('awards'));
                }
                break;

            case 'calendar':
                $returned_title[] = array($languageService->get('calendar'));
                break;

            case 'cashbox':
                $returned_title[] = array($languageService->get('cash_box'));
                break;

            #case 'challenge':
            #    $returned_title[] = array($languageService->get('challenge']);
            #    break;

            case 'clanwars':
                if ($action == "stats") {
                    $returned_title[] = array(
                        $languageService->get('clanwars'),
                        'index.php?site=clanwars'
                    );
                    $returned_title[] = array($languageService->get('stats'));
                } else {
                    $returned_title[] = array($languageService->get('clanwars'));
                }
                break;

            case 'clanwars_details':
                if (isset($parameters['cwID'])) {
                    $cwID = (int)$parameters['cwID'];
                } else {
                    $cwID = '';
                }
                $get = mysqli_fetch_array(
                    safe_query("SELECT opponent FROM plugins_clanwars WHERE cwID=" . (int)$cwID)
                );
                $returned_title[] = array(
                    $languageService->get('clanwars'),
                    'index.php?site=clanwars'
                );
                $returned_title[] = array($languageService->get('clanwars_details'));
                $returned_title[] = array($get['opponent']);
                break;

            

            case 'counter_stats':
                $returned_title[] = array($languageService->get('stats'));
                break;

            case 'faq':
                if (isset($parameters['faqcatID'])) {
                    $faqcatID = (int)$parameters['faqcatID'];
                } else {
                    $faqcatID = 0;
                }
                if (isset($parameters['faqID'])) {
                    $faqID = (int)$parameters['faqID'];
                } else {
                    $faqID = '';
                }
                $get = mysqli_fetch_array(
                    safe_query(
                        "SELECT faqcatname FROM plugins_faq_categories WHERE faqcatID=" . (int)$faqcatID
                    )
                );
                $get2 = mysqli_fetch_array(
                    safe_query("SELECT question FROM plugins_faq WHERE faqID=" . (int)$faqID)
                );
                if ($action == "faqcat") {
                    $returned_title[] = array(
                        $languageService->get('faq'),
                        'index.php?site=faq'
                    );
                    $returned_title[] = array($get['faqcatname']);
                } elseif ($action == "faq") {
                    $returned_title[] = array(
                        $languageService->get('faq'),
                        'index.php?site=faq'
                    );
                    $returned_title[] = array(
                        $get['faqcatname'],
                        'index.php?site=faq&amp;action=faqcat&amp;faqcatID=' . $faqcatID
                    );
                    $returned_title[] = array($get2['question']);
                    $metadata['keywords'] = \nexpell\Tags::getTags('faq', $faqID);
                } else {
                    $returned_title[] = array($languageService->get('faq'));
                }
                break;

            case 'files':
                if (isset($parameters['cat'])) {
                    $cat = (int)$parameters['cat'];
                } else {
                    $cat = '';
                }
                if (isset($parameters['file'])) {
                    $file = (int)$parameters['file'];
                } else {
                    $file = '';
                }
                if (isset($parameters['cat'])) {
                    $cat = mysqli_fetch_array(
                        safe_query(
                            "SELECT
                                filecatID, name
                            FROM
                                plugins_files_categories
                            WHERE
                                filecatID='" . $cat . "'"
                        )
                    );
                    $returned_title[] = array(
                        $languageService->get('files'),
                        'index.php?site=files'
                    );
                    $returned_title[] = array($cat['name']);
                } elseif (isset($parameters['file'])) {
                    $file = mysqli_fetch_array(
                        safe_query(
                            "SELECT
                                fileID, filecatID, filename
                            FROM
                                plugins_files
                            WHERE
                                fileID=" . (int)$file
                        )
                    );
                    $catname = mysqli_fetch_array(
                        safe_query(
                            "SELECT
                                name
                            FROM
                                plugins_files_categories
                            WHERE
                                filecatID=" . (int)$file['filecatID']
                        )
                    );
                    $returned_title[] = array(
                        $languageService->get('files'),
                        'index.php?site=files'
                    );
                    $returned_title[] = array(
                        $catname['name'],
                        'index.php?site=files&amp;cat=' . $cat
                    );
                    $returned_title[] = array($file['filename']);
                } else {
                    $returned_title[] = array($languageService->get('files'));
                }
                break;

            case 'forum':
                if (isset($parameters['board'])) {
                    $board = (int)$parameters['board'];
                } else {
                    $board = '';
                }
                if (isset($parameters['board'])) {
                    $board = mysqli_fetch_array(
                        safe_query(
                            "SELECT boardID, name FROM plugins_forum_boards WHERE boardID='" . $board . "'"
                        )
                    );
                    $returned_title[] = array(
                        $languageService->get('forum'),
                        'index.php?site=forum'
                    );
                    $returned_title[] = array($board['name']);
                } else {
                    $returned_title[] = array($languageService->get('forum'));
                }
                break;

            case 'forum_topic':
                if (isset($parameters['topic'])) {
                    $topic = (int)$parameters['topic'];
                } else {
                    $topic = '';
                }
                if (isset($parameters['topic'])) {
                    $topic = mysqli_fetch_array(
                        safe_query(
                            "SELECT
                                topicID, boardID, topic
                            FROM
                                plugins_forum_topics
                            WHERE
                                topicID=" . (int)$topic
                        )
                    );
                    $boardname = mysqli_fetch_array(
                        safe_query(
                            "SELECT name FROM plugins_forum_boards WHERE boardID=" . (int)$topic['boardID']
                        )
                    );
                    $returned_title[] = array(
                        $languageService->get('forum'),
                        'index.php?site=forum'
                    );
                    $returned_title[] = array(
                        $boardname['name'],
                        'index.php?site=forum&amp;board=' . $topic['boardID']
                    );
                    $returned_title[] = array($topic['topic']);
                } else {
                    $returned_title[] = array($languageService->get('forum'));
                }
                break;

            case 'gallery':
                $picID = isset($parameters['picID']) ? (int)$parameters['picID'] : 0;

                if ($picID > 0) {
                    // Bildinfos holen
                    $pic = mysqli_fetch_assoc(
                        safe_query("SELECT id, filename, class FROM plugins_gallery WHERE id = $picID")
                    );

                    $returned_title[] = [
                        $languageService->get('gallery'),
                        'index.php?site=gallery'
                    ];

                    if (!empty($pic['filename'])) {
                        $returned_title[] = [$pic['filename']];
                    }
                } else {
                    $returned_title[] = [$languageService->get('gallery')];
                }
                break;

            case 'guestbook':
                $returned_title[] = array($languageService->get('guestbook'));
                break;

            case 'history':
                $returned_title[] = array($languageService->get('history'));
                break;

            case 'imprint':
                $returned_title[] = array($languageService->get('imprint'));
                break;

            case 'joinus':
                $returned_title[] = array($languageService->get('joinus'));
                break;

            case 'links':
                $category_id = isset($parameters['category_id']) ? (int)$parameters['category_id'] : 0;
                $link_id = isset($parameters['link_id']) ? (int)$parameters['link_id'] : 0;

                // Kategorie-Titel holen
                $category_title = '';
                if ($category_id > 0) {
                    $resCat = safe_query("SELECT title FROM plugins_links_categories WHERE id = $category_id");
                    if ($rowCat = mysqli_fetch_assoc($resCat)) {
                        $category_title = $rowCat['title'];
                    }
                }

                // Link-Daten holen (Titel, Beschreibung)
                $link_title = '';
                $link_description = '';
                if ($link_id > 0) {
                    $resLink = safe_query("SELECT title, description FROM plugins_links WHERE id = $link_id");
                    if ($rowLink = mysqli_fetch_assoc($resLink)) {
                        $link_title = $rowLink['title'];
                        $link_description = $rowLink['description'];
                    }
                }

                if ($action === "category") {
                    $returned_title[] = [
                        $languageService->get('links'),
                        'index.php?site=links'
                    ];
                    if ($category_title !== '') {
                        $returned_title[] = [$category_title];
                    }
                } elseif ($action === "link") {
                    $returned_title[] = [
                        $languageService->get('links'),
                        'index.php?site=links'
                    ];
                    if ($category_title !== '') {
                        $returned_title[] = [
                            $category_title,
                            'index.php?site=links&amp;action=category&amp;category_id=' . $category_id
                        ];
                    }
                    if ($link_title !== '') {
                        $returned_title[] = [$link_title];
                    }

                    // Meta Description aus Beschreibung, gekürzt auf 160 Zeichen
                    if ($link_description !== '') {
                        $desc_excerpt = strip_tags($link_description);
                        if (mb_strlen($desc_excerpt) > 160) {
                            $desc_excerpt = mb_substr($desc_excerpt, 0, 157) . '...';
                        }
                        $metadata['description'] = $desc_excerpt;
                    }

                    // Keywords aus Tags generieren (Tags-System bleibt gleich)
                    $metadata['keywords'] = \nexpell\Tags::getTags('links', $link_id);
                } else {
                    $returned_title[] = [$languageService->get('links')];
                }
                break;





            case 'linkus':
                $returned_title[] = array($languageService->get('linkus'));
                break;

            case 'contact':
                $returned_title[] = array($languageService->get('contact'));
                break;

            case 'login':
                $returned_title[] = [$languageService->get('login')];
                break;

            case 'loginoverview':
                $returned_title[] = [$languageService->get('loginoverview')];
                break;

            case 'lostpassword':
                $returned_title[] = [$languageService->get('lostpassword')];
                break;

            case 'register':
                $returned_title[] = [$languageService->get('register')];
                break;


            case 'members':
                if (isset($parameters['squadID'])) {
                    $squadID = (int)$parameters['squadID'];
                } else {
                    $squadID = '';
                }
                if ($action == "show") {
                    $get = mysqli_fetch_array(
                        safe_query("SELECT name FROM plugins_squads WHERE squadID=" . (int)$squadID)
                    );
                    $returned_title[] = array(
                        $languageService->get('members'),
                        'index.php?site=members'
                    );
                    $returned_title[] = array($get['name']);
                } else {
                    $returned_title[] = array($languageService->get('members'));
                }
                break;

            case 'messenger':
                $returned_title[] = array($languageService->get('messenger'));
                break;

            case 'myprofile':
                $returned_title[] = array($languageService->get('myprofile'));
                break;

            case 'news':
                if ($action == "archive") {
                    $returned_title[] = array(
                        $languageService->get('news'),
                        'index.php?site=news'
                    );
                    $returned_title[] = array($languageService->get('archive'));
                } else {
                    $returned_title[] = array($languageService->get('news'));
                }
                break;

            case 'news_contents':
                if (isset($parameters['rubricID'])) {
                    $rubricID = (int)$parameters['rubricID'];
                } else {
                    $rubricID = 0;
                }
                if (isset($parameters['newsID'])) {
                    $newsID = (int)$parameters['newsID'];
                } else {
                    $newsID = '';
                }
                $get = mysqli_fetch_array(
                    safe_query(
                        "SELECT rubric FROM plugins_news_rubrics WHERE rubricID=" . (int)$rubricID)
                );
                $get2 = mysqli_fetch_array(
                    safe_query("SELECT headline FROM plugins_news WHERE newsID=" . (int)$newsID)
                );
                if ($action == "newscat") {
                    $returned_title[] = array(
                        $languageService->get('news'),
                        'index.php?site=news'
                    );
                    $returned_title[] = array($get['rubric']);
                } elseif ($action == "news") {
                    $returned_title[] = array(
                        $languageService->get('news'),
                        'index.php?site=news'
                    );

                    $returned_title[] = array(
                        $get['rubric'],
                        'index.php?site=news_contents&amp;action=newscat&amp;rubricID=' . $rubricID 
                        
                    );
                    $returned_title[] = array($get2['headline']);
                    $metadata['keywords'] = \nexpell\Tags::getTags('news', $newsID);
                } else {
                    $returned_title[] = array($languageService->get('news'));
                    $returned_title[] = array($get2['headline']);
                   
                }
                break;

            case 'newsletter':
                $returned_title[] = array($languageService->get('newsletter'));
                break;

            case 'partners':
                $partnerID = isset($parameters['partnerID']) ? (int)$parameters['partnerID'] : 0;

                if ($partnerID > 0) {
                    // Partner-Daten aus DB holen
                    $res = safe_query("SELECT name, description FROM plugins_partners WHERE id = " . $partnerID . " AND active = 1");
                    if ($partner = mysqli_fetch_assoc($res)) {
                        // Title für Breadcrumb / Navigation
                        $returned_title[] = [
                            $languageService->get('partners'),
                            'index.php?site=partners'
                        ];
                        $returned_title[] = [$partner['name']];

                        // Meta Description (kurz, aus description)
                        if (!empty($partner['description'])) {
                            $desc = strip_tags($partner['description']);
                            if (strlen($desc) > 160) {
                                $desc = substr($desc, 0, 157) . '...';
                            }
                            $metadata['description'] = $desc;
                        }

                        // Beispiel Keywords: aus Name und ggf. Beschreibung
                        $keywords = explode(' ', $partner['name']);
                        // Optional noch weitere Keywords aus Beschreibung extrahieren oder aus Tags generieren
                        $metadata['keywords'] = implode(',', $keywords);

                    } else {
                        // Partner nicht gefunden
                        $returned_title[] = [$languageService->get('partners')];
                    }
                } else {
                    // Startseite oder Übersicht Partners
                    $returned_title[] = [$languageService->get('partners')];
                }
                break;

            case 'startpage':
                $pageID = isset($parameters['pageID']) ? (int)$parameters['pageID'] : 0;

                $title = '';
                $startpage_text = '';

                if ($pageID > 0) {
                    $result = safe_query("SELECT title, startpage_text FROM settings_startpage WHERE pageID = $pageID");
                    if ($row = mysqli_fetch_assoc($result)) {
                        $title = $row['title'];
                        $startpage_text = $row['startpage_text'];
                    }
                }

                // Titel fürs Breadcrumb oder so
                if ($title) {
                    $returned_title[] = [$title];
                } else {
                    $returned_title[] = ['Startseite'];
                }

                // Meta Description aus Text (HTML-Tags entfernen, auf max 160 Zeichen kürzen)
                if ($startpage_text) {
                    $desc = strip_tags($startpage_text);
                    if (mb_strlen($desc) > 160) {
                        $desc = mb_substr($desc, 0, 157) . '...';
                    }
                    $metadata['description'] = $desc;
                }
                break;





            case 'polls':
                if (isset($parameters['vote'])) {
                    $vote = (int)$parameters['vote'];
                } else {
                    $vote = '';
                }
                if (isset($parameters['pollID'])) {
                    $pollID = (int)$parameters['pollID'];
                } else {
                    $pollID = '';
                }
                if (isset($parameters['vote'])) {
                    $vote = mysqli_fetch_array(
                        safe_query("SELECT titel FROM plugins_polls WHERE pollID=" . (int)$vote)
                    );
                    $returned_title[] = array(
                        $languageService->get('polls'),
                        'index.php?site=polls'
                    );
                    $returned_title[] = array($vote['titel']);
                } elseif (isset($parameters['pollID'])) {
                    $pollID = mysqli_fetch_array(
                        safe_query("SELECT titel FROM plugins_polls WHERE pollID=" . (int)$pollID)
                    );
                    $returned_title[] = array(
                        $languageService->get('polls'),
                        'index.php?site=polls'
                    );
                    $returned_title[] = array($pollID['titel']);
                } else {
                    $returned_title[] = array($languageService->get('polls'));
                }
                break;

            case 'profile':
                $id = isset($parameters['id']) ? (int)$parameters['id'] : 0;

                $returned_title[] = [$languageService->get('profile')];

                if ($id > 0) {
                    $username = getusername($id);
                    if ($username) {
                        $returned_title[] = [$username];
                        $metadata['keywords'] = \nexpell\Tags::getTags('profile', $id);
                    }
                }
                break;


            case 'userlist':
                $returned_title[] = [$languageService->get('userlist')];
                // Optional: Meta-Daten, falls vorhanden
                // $metadata['keywords'] = \nexpell\Tags::getTags('userlist');
                break;


            case 'search':
                $returned_title[] = array($languageService->get('search'));
                break;

            case 'servers':
                $returned_title[] = array($languageService->get('servers'));
                break;

            case 'shoutbox':
                $returned_title[] = array($languageService->get('shoutbox'));
                break;

            case 'sponsors':
                $returned_title[] = [$languageService->get('sponsors')];

                if (isset($parameters['sponsorID'])) {
                    $sponsorID = (int)$parameters['sponsorID'];

                    // Name und optionale Beschreibung holen
                    $sponsor = mysqli_fetch_assoc(
                        safe_query("SELECT name, description FROM plugins_sponsors WHERE id = $sponsorID AND active = 1")
                    );

                    if (!empty($sponsor['name'])) {
                        $returned_title[] = [$sponsor['name']];
                    }

                    // Meta Description aus Beschreibung, falls vorhanden
                    if (!empty($sponsor['description'])) {
                        $desc = strip_tags($sponsor['description']);
                        if (strlen($desc) > 160) {
                            $desc = substr($desc, 0, 157) . '...';
                        }
                        $metadata['description'] = $desc;
                    }

                    // Meta Keywords aus Tags
                    $metadata['keywords'] = \nexpell\Tags::getTags('sponsors', $sponsorID);
                }
                break;



            case 'planning':
                $returned_title[] = array($languageService->get('planning'));
                break;    

            case 'squads':
                if (isset($parameters['squadID'])) {
                    $squadID = (int)$parameters['squadID'];
                } else {
                    $squadID = '';
                }
                if ($action == "show") {
                    $get = mysqli_fetch_array(
                        safe_query("SELECT name FROM plugins_squads WHERE squadID=" . (int)$squadID)
                    );
                    $returned_title[] = array(
                        $languageService->get('squads'),
                        'index.php?site=squads'
                    );
                    $returned_title[] = array($get['name']);
                } else {
                    $returned_title[] = array($languageService->get('squads'));
                }
                break;

            case 'static':
                $staticID = isset($parameters['staticID']) ? (int)$parameters['staticID'] : 0;

                $get = mysqli_fetch_assoc(
                    safe_query("SELECT title, content FROM settings_static WHERE staticID = " . $staticID)
                );

                if (!empty($get['title'])) {
                    $returned_title[] = [$get['title']];
                } else {
                    $returned_title[] = [$languageService->get('static')];
                }

                if (!empty($get['content'])) {
                    // Meta Description aus content (HTML-Tags entfernen, Länge begrenzen)
                    $desc = strip_tags($get['content']);
                    $desc = trim(preg_replace('/\s+/', ' ', $desc)); // Whitespace reduzieren
                    if (strlen($desc) > 160) {
                        $desc = substr($desc, 0, 157) . '...';
                    }
                    $metadata['description'] = $desc;
                }

                $metadata['keywords'] = \nexpell\Tags::getTags('static', $staticID);

                break;


            case 'usergallery':
                $returned_title[] = array($languageService->get('usergallery'));
                break;
# neu Anfang
            case 'todo':
                $returned_title[] = array($languageService->get('todo'));
                break;

            case 'news_archive':
                $returned_title[] = array($languageService->get('news_archive'));
                break; 

            case 'privacy_policy':
                $privacyPolicyID = isset($parameters['privacy_policyID']) ? (int)$parameters['privacy_policyID'] : 0;

                $get = mysqli_fetch_assoc(
                    safe_query("SELECT privacy_policy_text FROM settings_privacy_policy WHERE privacy_policyID = " . $privacyPolicyID)
                );

                $returned_title[] = [$languageService->get('privacy_policy')];

                if (!empty($get['privacy_policy_text'])) {
                    // Meta Description aus Datenschutztext (HTML entfernen, auf max. 160 Zeichen kürzen)
                    $desc = strip_tags($get['privacy_policy_text']);
                    $desc = trim(preg_replace('/\s+/', ' ', $desc));
                    if (strlen($desc) > 160) {
                        $desc = substr($desc, 0, 157) . '...';
                    }
                    $metadata['description'] = $desc;
                }

                // Optional: Keywords, wenn du welche hinterlegen willst (z.B. Tags)
                // $metadata['keywords'] = \nexpell\Tags::getTags('privacy_policy', $privacyPolicyID);

                break;

            case 'candidature':
                $returned_title[] = array($languageService->get('candidature'));
                break; 

            case 'twitter':
                $returned_title[] = array($languageService->get('twitter'));
                break; 

            case 'discord':
                $returned_title[] = array($languageService->get('discord'));
                break;
                
            case 'portfolio':
                $returned_title[] = array($languageService->get('portfolio'));
                break;
                
            case 'streams':
                $returned_title[] = array($languageService->get('streams'));
                break;
                
            case 'server_rules':
                $returned_title[] = array($languageService->get('server_rules'));
                break; 
                
            case 'clan_rules':
                $clanRulesID = isset($parameters['id']) ? (int)$parameters['id'] : 0;

                $get = mysqli_fetch_assoc(
                    safe_query("SELECT title, text FROM plugins_clan_rules WHERE id = " . $clanRulesID . " AND displayed = '1'")
                );

                $returned_title[] = [$languageService->get('clan_rules')];

                if (!empty($get['title'])) {
                    $returned_title[] = [$get['title']];
                }

                if (!empty($get['text'])) {
                    // Meta Description aus dem Text (HTML entfernen, auf max. 160 Zeichen kürzen)
                    $desc = strip_tags($get['text']);
                    $desc = trim(preg_replace('/\s+/', ' ', $desc));
                    if (strlen($desc) > 160) {
                        $desc = substr($desc, 0, 157) . '...';
                    }
                    $metadata['description'] = $desc;
                }

                // Optional: Keywords, falls Tags verwendet werden
                // $metadata['keywords'] = \nexpell\Tags::getTags('clan_rules', $clanRulesID);

                break;

                

            case 'videos':
                if (isset($parameters['videoscatID'])) {
                    $videoscatID = (int)$parameters['videoscatID'];
                } else {
                    $videoscatID = 0;
                }
                if (isset($parameters['videosID'])) {
                    $videosID = (int)$parameters['videosID'];
                } else {
                    $videosID = '';
                }
                $get = mysqli_fetch_array(
                    safe_query(
                        "SELECT catname FROM plugins_videos_categories WHERE videoscatID=" . (int)$videoscatID
                    )
                );
                $get2 = mysqli_fetch_array(
                    safe_query("SELECT videoname FROM plugins_videos WHERE videosID=" . (int)$videosID)
                );
                if ($action == "watch") {
                    $returned_title[] = array(
                        $languageService->get('videos'),
                        'index.php?site=videos'
                    );
                    #$returned_title[] = array($get['catname']);
                } elseif ($action == "videos") {
                    $returned_title[] = array(
                        $languageService->get('videos'),
                        'index.php?site=videos'
                    );
                    $returned_title[] = array(
                        $get['catname'],
                        'index.php?site=videos&amp;action=watch&amp;videoscatID=' . $videoscatID
                    );
                    $returned_title[] = array($get2['videoname']);
                    $metadata['keywords'] = \nexpell\Tags::getTags('videos', $videosID);
                } else {
                    $returned_title[] = array($languageService->get('videos'));
                    #$returned_title[] = array($get2['videoname']);
                }
                break; 


            case 'blog':
                if (isset($parameters['blogID'])) {
                    $blogID = (int)$parameters['blogID'];
                } else {
                    $blogID = 0;
                }
                $get2 = mysqli_fetch_array(
                    safe_query(
                        "SELECT headline FROM plugins_blog WHERE blogID=" . (int)$blogID)
                    );
                if ($action == "show") {
                    $get = mysqli_fetch_array(
                        safe_query("SELECT headline FROM plugins_blog WHERE blogID=" . (int)$blogID)
                    );
                    $returned_title[] = array(
                        $languageService->get('blog'),
                        'index.php?site=blog'
                    );
                    $returned_title[] = array($get['headline']);

                } elseif ($action == "blog") {
                    $returned_title[] = array(
                        $languageService->get('blog'),
                        'index.php?site=blog'
                    );

                    $returned_title[] = array(
                        $languageService->get('blog'),
                        'index.php?site=blog&amp;action=archiv'
                    );

                    $returned_title[] = array(
                        $languageService->get('blog'),
                        'index.php?site=blog&amp;action=show&amp;blogID=' . $blogID
                    );

                    $returned_title[] = array(
                        $languageService->get('blog'),
                        'index.php?site=blog&amp;action=archiv&amp;userID=' . $userID
                    );

                    $returned_title[] = array($get['headline']);
                    $returned_title[] = array($languageService->get('archive'));
                } else {
                    $returned_title[] = array($languageService->get('blog'));
                    $returned_title[] = array($languageService->get('archive'));
                }
                break;              
# neu ENDE
            case 'whoisonline':
                $returned_title[] = array($languageService->get('whoisonline'));
                break;

            default:
                $returned_title[] = array($languageService->get('news'));
                break;
        }
    } else {
        $returned_title[] = array($languageService->get('news'));
    }
    return array('titles' => $returned_title, 'metatags' => $metadata);
}
