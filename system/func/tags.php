<?php

namespace nexpell;

class Tags
{
    /**
     * Setzt die Tags für einen bestimmten Relationen-Typ und Relationen-ID
     * 
     * @param string $relType Der Relationstyp (z.B. "news", "article")
     * @param int $relID Die Relation-ID
     * @param string|array $tags Eine durch Komma getrennte Liste von Tags oder ein Array von Tags
     */
    public static function setTags($relType, $relID, $tags)
    {
        self::removeTags($relType, $relID);
        if (is_string($tags)) {
            $tags = explode(",", $tags);
        }
        $tags = array_map("trim", $tags);
        $tags = array_unique($tags);
        $values = array();
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                $values[] = '("' . $tag . '","' . $relType . '","' . $relID . '")';
            }
        }
        if (count($values)) {
            safe_query("INSERT INTO `tags` (`tag`, `rel`, `ID`) VALUES " . implode(",", $values));
        }
    }

    /**
     * Holt alle Tags für eine bestimmte Relation
     * 
     * @param string $relType Der Relationstyp
     * @param int $relID Die Relation-ID
     * @param bool $asArray Gibt die Tags als Array zurück, wenn true, ansonsten als String
     * @return array|string Die Tags als Array oder durch Komma getrennt als String
     */
    public static function getTags($relType, $relID, $asArray = false)
    {
        $tags = array();
        $get = safe_query("SELECT * FROM `tags` WHERE `rel`='" . $relType . "' AND `ID`='" . $relID . "'");
        while ($ds = mysqli_fetch_assoc($get)) {
            $tags[] = $ds['tag'];
        }
        $tags = array_unique($tags);
        return ($asArray === true) ? $tags : implode(", ", $tags);
    }

    /**
     * Holt alle Tags als verlinkte HTML-Tags
     * 
     * @param string $relType Der Relationstyp
     * @param int $relID Die Relation-ID
     * @return string Die verlinkten Tags als HTML
     */
    public static function getTagsLinked($relType, $relID)
    {
        $tags = array();
        foreach (self::getTags($relType, $relID, true) as $tag) {
            $tags[] = '<a href="index.php?site=tags&amp;tag=' . $tag . '">' . $tag . '</a>';
        }
        return implode(", ", $tags);
    }

    /**
     * Holt alle Tags aus der Datenbank als Array oder durch Komma getrennt als String
     * 
     * @param bool $array Wenn true, wird ein Array der Tags zurückgegeben, ansonsten ein String
     * @return array|string Die Tags als Array oder als durch Komma getrennten String
     */
    public static function getTagsPlain($array = false)
    {
        $tags = array();
        $get = safe_query("SELECT * FROM `tags`");
        while ($ds = mysqli_fetch_assoc($get)) {
            if (!empty($ds['tag'])) {
                $tags[] = $ds['tag'];
            }
        }
        $tags = array_unique($tags);
        return ($array === true) ? $tags : implode(", ", $tags);
    }

    /**
     * Holt die Tag-Cloud basierend auf der Häufigkeit der Tags
     * 
     * @return array Ein Array mit den Tags und ihrer Häufigkeit
     */
    public static function getTagCloud()
    {
        $get = safe_query("SELECT `tag`, COUNT(`ID`) AS `count` FROM `tags` GROUP BY `tag`");
        $data = array();
        $data['min'] = 999999999999;
        $data['max'] = 0;
        $data['tags'] = array();
        while ($ds = mysqli_fetch_assoc($get)) {
            $data['tags'][] = array('name' => $ds['tag'], 'count' => $ds['count']);
            $data['min'] = min($data['min'], $ds['count']);
            $data['max'] = max($data['max'], $ds['count']);
        }
        return $data;
    }

    /**
     * Entfernt alle Tags für eine bestimmte Relation
     * 
     * @param string $relType Der Relationstyp
     * @param int $relID Die Relation-ID
     */
    public static function removeTags($relType, $relID)
    {
        safe_query("DELETE FROM `tags` WHERE `rel`='" . $relType . "' AND `ID`='" . $relID . "'");
    }

    /**
     * Berechnet die Größe eines Tags basierend auf einer logarithmischen Skalierung
     * 
     * @param int $count Die Häufigkeit des Tags
     * @param int $mincount Der minimale Wert der Häufigkeit
     * @param int $maxcount Der maximale Wert der Häufigkeit
     * @param int $minsize Die minimale Größe des Tags
     * @param int $maxsize Die maximale Größe des Tags
     * @param int $tresholds Die Anzahl der Schwellenwerte
     * @return int Die berechnete Größe des Tags
     */
    public static function getTagSizeLogarithmic($count, $mincount, $maxcount, $minsize, $maxsize, $tresholds)
    {
        if (!is_int($tresholds) || $tresholds < 2) {
            $tresholds = $maxsize - $minsize;
            $treshold = 1;
        } else {
            $treshold = ($maxsize - $minsize) / ($tresholds - 1);
        }
        $a = $tresholds * log($count - $mincount + 2) / log($maxcount - $mincount + 2) - 1;
        return round($minsize + round($a) * $treshold);
    }

    /**
     * Holt die News-Details für eine bestimmte News-ID
     * 
     * @param int $newsID Die News-ID
     * @return array|false Die News-Details als Array oder false, wenn die News nicht gefunden wurde
     */
    public static function getNews($newsID)
    {
        global $userID;
        $result = safe_query(
            "SELECT
                *,
                `content`,
                `headline`
            FROM
                `plugins_news_manager`
            WHERE
                `newsID` = " . (int)$newsID
        );
        if ($result->num_rows) {
            $ds = mysqli_fetch_array($result);
            $content = $ds['content'];
            $string = preg_replace('/[,]/', ',', substr($content, 0, 255));

            // Sprachdatei laden
            $plugin_language_path = './includes/plugins/tags/languages/';
            $language_file = $plugin_language_path . $_SESSION['language'] . '/tags.php';
            $_language = new \nexpell\Language();
            $_language->language = $_SESSION['language'];
            include $language_file;
            $_language->module = array_merge($_language->module, $language_array);
            $_language->readModule('tags', false, false, $plugin_language_path);

            return array(
                'date' => time(),
                'type' => 'News',
                'content' => $string,
                'title' => $ds['headline'],
                'link' => 'index.php?site=news_manager&action=news_contents&amp;newsID=' . $newsID,
                'cat' => $_language->module['news'],
                'link_cat' => $_language->module['news_link']
            );
        } else {
            return false;
        }
    }

    /**
     * Holt die Artikel-Details für eine bestimmte Artikel-ID
     * 
     * @param int $articleID Die Artikel-ID
     * @return array|false Die Artikel-Details als Array oder false, wenn der Artikel nicht gefunden wurde
     */
    public static function getArticle($articleID)
    {
        global $userID;

        $get = safe_query(
            "SELECT
                `articleID`,
                `articlecatID`,
                `date`,
                `question`,
                `answer`
            FROM
                `plugins_articles`
            WHERE
                `articleID` = " . (int)$articleID
        );
        if ($get->num_rows) {
            $ds = mysqli_fetch_array($get);
            $answer = $ds['answer'];
            $string = preg_replace('/[,]/', ',', substr($answer, 0, 255));

            // Sprachdatei laden
            $plugin_language_path = './includes/plugins/tags/languages/';
            $language_file = $plugin_language_path . $_SESSION['language'] . '/tags.php';
            $_language = new \nexpell\Language();
            $_language->language = $_SESSION['language'];
            include $language_file;
            $_language->module = array_merge($_language->module, $language_array);
            $_language->readModule('tags', false, false, $plugin_language_path);

            return array(
                'date' => $ds['date'],
                'type' => 'Artikel',
                'content' => $string,
                'title' => $ds['question'],
                'link' => 'index.php?site=articles&action=watch&articleID=' . $articleID,
                'cat' => $_language->module['articles'],
                'link_cat' => $_language->module['articles_link']
            );
        } else {
            return false;
        }
    }

    /**
     * Holt die statische Seiten-Details für eine bestimmte statische Seiten-ID
     * 
     * @param int $staticID Die statische Seiten-ID
     * @return array|false Die statischen Seiten-Details als Array oder false, wenn die Seite nicht gefunden wurde
     */
    public static function getStaticPage($staticID)
    {
        global $userID;

        $get = safe_query(
            "SELECT
                `staticID`,
                `title`,
                `content`
            FROM
                `plugins_static_pages`
            WHERE
                `staticID` = " . (int)$staticID
        );
        if ($get->num_rows) {
            $ds = mysqli_fetch_array($get);
            $string = preg_replace('/[,]/', ',', substr($ds['content'], 0, 255));

            // Sprachdatei laden
            $plugin_language_path = './includes/plugins/tags/languages/';
            $language_file = $plugin_language_path . $_SESSION['language'] . '/tags.php';
            $_language = new \nexpell\Language();
            $_language->language = $_SESSION['language'];
            include $language_file;
            $_language->module = array_merge($_language->module, $language_array);
            $_language->readModule('tags', false, false, $plugin_language_path);

            return array(
                'date' => time(),
                'type' => 'Seite',
                'content' => $string,
                'title' => $ds['title'],
                'link' => 'index.php?site=static_page&staticID=' . $staticID,
                'cat' => $_language->module['static_pages'],
                'link_cat' => $_language->module['static_pages_link']
            );
        } else {
            return false;
        }
    }
}
