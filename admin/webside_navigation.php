<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $_database,$languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('webnavi', true);

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_webside_navigation');

if (isset($_GET[ 'delete' ])) {
    $snavID = $_GET[ 'snavID' ];
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_GET[ 'captcha_hash' ])) {
        safe_query("DELETE FROM navigation_website_sub WHERE snavID='$snavID' ");
    } else {
        echo '<div class="alert alert-warning" role="alert">' . htmlspecialchars($languageService->get('transaction_invalid')) . '</div>';

        redirect("admincenter.php?site=webside_navigation",3);
    return false;
    }
} elseif (isset($_GET[ 'delcat' ])) {
    $mnavID = $_GET[ 'mnavID' ];
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_GET[ 'captcha_hash' ])) {
        safe_query("UPDATE navigation_website_sub SET mnavID='0' WHERE mnavID='$mnavID' ");
        safe_query("DELETE FROM navigation_website_main WHERE mnavID='$mnavID' ");
    } else {
        echo '<div class="alert alert-warning" role="alert">' . htmlspecialchars($languageService->get('transaction_invalid')) . '</div>';

    }
} elseif (isset($_POST[ 'sortieren' ])) {
    if(isset($_POST[ 'sortcat' ])) { $sortcat = $_POST[ 'sortcat' ]; } else { $sortcat="";}
    $sortlinks = $_POST[ 'sortlinks' ];

    if (is_array($sortcat) AND !empty($sortcat)) {
        foreach ($sortcat as $sortstring) {
            $catsorter = explode("-", $sortstring);
            safe_query("UPDATE navigation_website_main SET sort='$catsorter[1]' WHERE mnavID='$catsorter[0]' ");
        }
    }
    if (is_array($sortlinks)) {
        foreach ($sortlinks as $sortstring) {
            $sorter = explode("-", $sortstring);
            safe_query("UPDATE navigation_website_sub SET sort='$sorter[1]' WHERE snavID='$sorter[0]' ");
        }
    }
} elseif (isset($_POST['save'])) {
    $CAPCLASS = new \nexpell\Captcha;

    $url   = $_POST['link'] ?? '';
    $mnavID = $_POST['mnavID'] ?? 0;

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {

        // Mehrsprachigen Namen zusammensetzen
        $name = '';
        if (isset($_POST['name']) && is_array($_POST['name'])) {
            $name = buildMultiLangString($_POST['name']);
        }

        // Sort ermitteln (z.B. nächster Wert)
        $anz = mysqli_num_rows(
            safe_query("SELECT snavID FROM navigation_website_sub WHERE mnavID='" . (int)$mnavID . "'")
        );
        $sort = $anz + 1; // neuer Eintrag hinten anhängen

        // Prepared Statement für Insert
        $stmt = $_database->prepare("
            INSERT INTO navigation_website_sub (mnavID, name, url, sort)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('issi', $mnavID, $name, $url, $sort);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($languageService->get('transaction_successful')) . '</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($languageService->get('transaction_failed')) . '</div>';
        }
        $stmt->close();

    } else {
        echo '<div class="alert alert-warning" role="alert">' . htmlspecialchars($languageService->get('transaction_invalid')) . '</div>';
    }
}
 elseif (isset($_POST['savecat'])) {
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {

        $url = $_POST['link'] ?? '';
        $windows = $_POST['windows'] ?? 0;
        $isdropdown = isset($_POST['isdropdown']) ? 1 : 0;

        // Mehrsprachigen Namen zusammensetzen
        $name = '';
        if (is_array($_POST['name'])) {
            $name = buildMultiLangString($_POST['name']);
        } else {
            $name = $_POST['name'];
        }

        // Prepared Statement für Insert
        $stmt = $_database->prepare("
            INSERT INTO navigation_website_main (name, url, windows, isdropdown, sort)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->bind_param('ssii', $name, $url, $windows, $isdropdown);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $id = $stmt->insert_id;
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($languageService->get('transaction_successful')) . '</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($languageService->get('transaction_failed')) . '</div>';
        }
        $stmt->close();

    } else {
        echo '<div class="alert alert-warning" role="alert">' . htmlspecialchars($languageService->get('transaction_invalid')) . '</div>';
    }
}
 elseif (isset($_POST['saveedit'])) {
    $CAPCLASS = new \nexpell\Captcha;

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        $url = $_POST['link'];
        $mnavID = (int)$_POST['mnavID'];
        $snavID = (int)$_POST['snavID'];
        $modulname = $_POST['modulname'] ?? '';

        // Mehrsprachigen Namen zusammensetzen (wenn $name ein Array ist)
        $name = '';
        if (is_array($_POST['name'])) {
            $name = buildMultiLangString($_POST['name']);
        } else {
            $name = $_POST['name'];
        }

        $stmt = $_database->prepare("
            UPDATE navigation_website_sub
            SET mnavID = ?, name = ?, url = ?, modulname = ?
            WHERE snavID = ?
        ");
        // 'i' = integer, 's' = string (mnavID=int, name=string, url=string, modulname=string, snavID=int)
        $stmt->bind_param('isssi', $mnavID, $name, $url, $modulname, $snavID);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($languageService->get('transaction_successful')) . '</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($languageService->get('transaction_failed')) . '</div>';
        }

        $stmt->close();
    } else {
        echo '<div class="alert alert-warning" role="alert">' . htmlspecialchars($languageService->get('transaction_invalid')) . '</div>';
    }
}
elseif (isset($_POST['saveeditcat'])) {
    $CAPCLASS = new \nexpell\Captcha;

    $url       = $_POST["link"] ?? '';
    $windows   = $_POST["windows"] ?? 0;
    $isdropdown = isset($_POST["isdropdown"]) ? 1 : 0;

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {

        // Mehrsprachigen Namen zusammensetzen
        $name = '';
        if (isset($_POST['name']) && is_array($_POST['name'])) {
            $name = buildMultiLangString($_POST['name']);
        }

        // Prepared Statement statt direkt SQL
        $stmt = $_database->prepare("
            UPDATE navigation_website_main 
            SET name = ?, url = ?, windows = ?, isdropdown = ? 
            WHERE mnavID = ?
        ");
        $stmt->bind_param('ssiii', $name, $url, $windows, $isdropdown, $_POST['mnavID']);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($languageService->get('transaction_successful')) . '</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($languageService->get('transaction_failed')) . '</div>';
        }
        $stmt->close();

    } else {
        echo '<div class="alert alert-warning" role="alert">' . htmlspecialchars($languageService->get('transaction_invalid')) . '</div>';
    }
}


function buildMultiLangString(array $texts): string {
    $result = '';
    foreach ($texts as $lang => $text) {
        $result .= "[[lang:$lang]]" . trim($text);
    }
    return $result;
}

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

if ($action == "add") {
    echo '<div class="card">
        <div class="card-header">'
            . $languageService->get('dashnavi') .
        '</div>
            
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="admincenter.php?site=webside_navigation">'
        . $languageService->get('dashnavi') . '</a></li>
    <li class="breadcrumb-item active" aria-current="page">'
        . $languageService->get('add_link') . '</li>
  </ol>
</nav>
     <div class="card-body">';

    // Kategorien aus navigation_website_main laden
    $ergebnis = safe_query("SELECT * FROM navigation_website_main ORDER BY sort");
    $cats = '<select class="form-select" name="mnavID">';
    while ($ds = mysqli_fetch_array($ergebnis)) {
        if ($ds['default'] == 0) {
            $name = $languageService->get('cat_' . htmlspecialchars($ds['name']));
        } else {
            $name = htmlspecialchars($ds['name']);
        }
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($ds['name']);
        $cats .= '<option value="' . $ds[ 'mnavID' ] . '">' . $translate->getTextByLanguage($ds['name']) . '</option>';
    }
    $cats .= '</select>';

    // Mehrsprachige Namen leer, da neu anlegen
    $languages = [];

    $query = "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC";
    $result = mysqli_query($_database, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // $row['iso_639_1'] z.B. 'de', $row['name_de'] z.B. 'Deutsch'
            $languages[$row['iso_639_1']] = $row['name_de'];
        }
    } else {
        // Fallback falls Query nicht klappt
        $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
    }

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    echo '<form class="form-horizontal" method="post" action="admincenter.php?site=webside_navigation">
    <div class="mb-3 row">
        <label class="col-sm-2 control-label">' . $languageService->get('category') . ':</label>
        <div class="col-sm-8"><span class="text-muted small"><em>' . $cats . '</em></span></div>
    </div>

    <div class="alert alert-info" role="alert">';

    foreach ($languages as $code => $label) {
        echo '<div class="mb-3 row">
            <label class="col-sm-2 control-label" for="text_' . $code . '">' . $label . '</label>
            <div class="col-sm-8"><span class="text-muted small"><em>
                <input class="form-control" type="text" id="text_' . $code . '" name="name[' . $code . ']" value="">
            </em></span></div>
        </div>';
    }

    echo '</div>

    <div class="mb-3 row">
        <label class="col-sm-2 control-label">' . $languageService->get('url') . ':</label>
        <div class="col-sm-8"><span class="text-muted small"><em>
            <input class="form-control" type="text" name="link" placeholder="URL"/>
        </em></span></div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-2 control-label">Modulname:</label>
        <div class="col-sm-8">
            <input class="form-control" type="text" name="module" placeholder="optional Modulname"/>
        </div>
    </div>

    <div class="mb-3 row">
        <div class="col-sm-offset-2 col-sm-10">
            <input type="hidden" name="captcha_hash" value="' . $hash . '">
            <input class="btn btn-success" type="submit" name="save" value="' . $languageService->get('add_link') . '">
        </div>
    </div>
    </form>
    </div></div>';
}
 elseif ($action == "edit") {
    echo '<div class="card">
        <div class="card-header">
            ' . $languageService->get('dashnavi') . '
        </div>
            
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="admincenter.php?site=webside_navigation">' . $languageService->get('dashnavi') . '</a></li>
    <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('edit_link') . '</li>
  </ol>
</nav>
     <div class="card-body">';

    $snavID = $_GET[ 'snavID' ];
    $ergebnis = safe_query("SELECT * FROM navigation_website_sub WHERE snavID='$snavID'");
    $ds = mysqli_fetch_array($ergebnis);

    $category = safe_query("SELECT * FROM navigation_website_main ORDER BY sort");
    $cats = '<select class="form-select" name="mnavID">';
    while ($dc = mysqli_fetch_array($category)) {
        if ($dc[ 'default' ] == 1) {
            $name = htmlspecialchars($dc[ 'name' ]);
        }
        if ($ds[ 'mnavID' ] == $dc[ 'mnavID' ]) {
            $selected = " selected=\"selected\"";
        } else {
            $selected = "";
        }
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($dc['name']);
        $cats .= '<option value="' . $dc[ 'mnavID' ] . '"' . $selected . '>' . $translate->getTextByLanguage($dc['name']) . '</option>';
    }
    $cats .= '</select>';

    

    
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    echo '<form class="form-horizontal" method="post" action="admincenter.php?site=webside_navigation">

    <div class="mb-3 row">
    <label class="col-sm-2 control-label">'.$languageService->get('category').':</label>
    <div class="col-sm-8"><span class="text-muted small"><em>
      ' . $cats . '</em></span>
    </div>
  </div>
  <div class="alert alert-info" role="alert">';






function extractLangText(?string $multiLangText, string $lang): string {
    if (!$multiLangText) {
        return '';
    }
    $pattern = '/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s';
    if (preg_match($pattern, $multiLangText, $matches)) {
        return trim($matches[1]);
    }
    return '';
}
$result = $_database->query("SELECT name FROM navigation_website_sub WHERE snavID = $snavID");
$multiLangText = '';
if ($result && $row = $result->fetch_assoc()) {
    $multiLangText = $row['name']; // z.B. [[lang:de]]Hallo[[lang:en]]Hello
}

// Sprachen-Array
$languages = [];

$query = "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC";
$result = mysqli_query($_database, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // $row['iso_639_1'] z.B. 'de', $row['name_de'] z.B. 'Deutsch'
        $languages[$row['iso_639_1']] = $row['name_de'];
    }
} else {
    // Fallback falls Query nicht klappt
    $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
}

foreach ($languages as $code => $label) {
    $value = extractLangText($multiLangText, $code);
    echo '<div class="mb-3 row">
    <label class="col-sm-2 control-label" for="text_' . $code . '">' . $label . '</label>';
    echo '<div class="col-sm-8"><span class="text-muted small"><em><input class="form-control" type="text" id="text_' . $code . '" name="name[' . $code . ']" value="' . htmlspecialchars($value) . '"></em></span>
    </div>
  </div>';
}

echo'</div>

<div class="mb-3 row">
                <label class="col-md-2 control-label">' . $languageService->get('modulname') . ':</label>
                <div class="col-md-8"><span class="text-muted small"><em>
                    <input class="form-control" type="text" name="modulname" value="' . htmlspecialchars($ds['modulname']) . '" size="60"></em></span>
                </div>
            </div>
  <div class="mb-3 row">
    <label class="col-sm-2 control-label">'.$languageService->get('url').':</label>
    <div class="col-sm-8"><span class="text-muted small"><em>
      <input class="form-control" type="text" name="link" value="' . htmlspecialchars($ds[ 'url' ]) . '" size="60"></em></span>
    </div>
  </div>

  
<div class="mb-3 row">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="hidden" name="captcha_hash" value="'.$hash.'" /><input type="hidden" name="snavID" value="' . $snavID . '">
      <input class="btn btn-warning" type="submit" name="saveedit" value="' . $languageService->get('edit_link') . '">
    </div>
  </div>

    </form>
    </div></div>';

# new main navi
} elseif ($action == "addcat") {
    echo '<div class="card">
        <div class="card-header">
            ' . $languageService->get('dashnavi') . '
        </div>

        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admincenter.php?site=webside_navigation">' . $languageService->get('dashnavi') . '</a></li>
            <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('add_category') . '</li>
          </ol>
        </nav>

        <div class="card-body">';

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    // Sprachen-Array
    $languages = [];

    $query = "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC";
    $result = mysqli_query($_database, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // $row['iso_639_1'] z.B. 'de', $row['name_de'] z.B. 'Deutsch'
            $languages[$row['iso_639_1']] = $row['name_de'];
        }
    } else {
        // Fallback falls Query nicht klappt
        $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
    }

    echo '<form class="form-horizontal" method="post" action="admincenter.php?site=webside_navigation">

        <div class="alert alert-info" role="alert">';

    // Mehrsprachige Eingabefelder für Namen ohne Vorbefüllung (neue Kategorie)
    foreach ($languages as $code => $label) {
        echo '<div class="mb-3 row">
            <label class="col-sm-2 control-label" for="text_' . $code . '">' . $label . '</label>
            <div class="col-sm-8">
                <input class="form-control" type="text" id="text_' . $code . '" name="name[' . $code . ']" value="">
            </div>
          </div>';
    }
    echo '</div>

        <div class="mb-3 row">
            <label class="col-sm-2 control-label">' . $languageService->get('url') . ':</label>
            <div class="col-sm-8">
                <input class="form-control" type="text" name="link" size="60" value="">
                <br>
                <select id="windows" name="windows" class="form-select">
                    <option value="0">' . $languageService->get('_blank') . '</option>
                    <option value="1">' . $languageService->get('_self') . '</option>
                </select>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-2 control-label">' . $languageService->get('dropdown') . ':</label>
            <div class="col-sm-8 form-check form-switch" style="padding: 0px 43px;">
                <input class="form-check-input" type="checkbox" name="isdropdown" id="isdropdown" checked="checked" />
            </div>
        </div>

        <div class="mb-3 row">
            <div class="col-sm-offset-2 col-sm-10">
                <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                <input class="btn btn-success" type="submit" name="savecat" value="' . $languageService->get('add_category') . '">
            </div>
        </div>

    </form>
    </div></div>';
}
 elseif ($action == "editcat") {
    echo '<div class="card">
        <div class="card-header">
            ' . $languageService->get('dashnavi') . '
        </div>
            
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="admincenter.php?site=webside_navigation">' . $languageService->get('dashnavi') . '</a></li>
    <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('edit_category') . '</li>
  </ol>
</nav>
     <div class="card-body">';

    $mnavID = $_GET[ 'mnavID' ];
    $ergebnis = safe_query("SELECT * FROM navigation_website_main WHERE mnavID='$mnavID'");
    $ds = mysqli_fetch_array($ergebnis);

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    if ($ds[ 'isdropdown' ] == 1) {
        $isdropdown = '<input class="form-check-input" type="checkbox" name="isdropdown" value="1" checked="checked" />';
    } else {
        $isdropdown = '<input class="form-check-input" type="checkbox" name="isdropdown" value="1" />';
    }

    if ($ds['windows'] == "1") {
                $windows_1 = '<option value="1" selected="selected">' . $languageService->get('_self') .
                    '</option><option value="0">' . $languageService->get('_blank') . '</option>';
            } else {
                $windows_1 = '<option value="1">' . $languageService->get('_self') .
                    '</option><option value="0" selected="selected">' . $languageService->get('_blank') . '</option>';
            }

    echo '<form class="form-horizontal" method="post" action="admincenter.php?site=webside_navigation">
<input type="hidden" name="mnavID" value="' . $ds[ 'mnavID' ] . '" />
<div class="alert alert-info" role="alert">';

  function extractLangText(?string $multiLangText, string $lang): string {
    if (!$multiLangText) {
        return '';
    }
    $pattern = '/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s';
    if (preg_match($pattern, $multiLangText, $matches)) {
        return trim($matches[1]);
    }
    return '';
}
$result = $_database->query("SELECT name FROM navigation_website_main WHERE mnavID = $mnavID");
$multiLangText = '';
if ($result && $row = $result->fetch_assoc()) {
    $multiLangText = $row['name']; // z.B. [[lang:de]]Hallo[[lang:en]]Hello
}

// Sprachen-Array
$languages = [];

$query = "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC";
$result = mysqli_query($_database, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // $row['iso_639_1'] z.B. 'de', $row['name_de'] z.B. 'Deutsch'
        $languages[$row['iso_639_1']] = $row['name_de'];
    }
} else {
    // Fallback falls Query nicht klappt
    $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
}

foreach ($languages as $code => $label) {
    $value = extractLangText($multiLangText, $code);
    echo '<div class="mb-3 row">
    <label class="col-sm-2 control-label" for="text_' . $code . '">' . $label . '</label>';
    echo '<div class="col-sm-8"><span class="text-muted small"><em><input class="form-control" type="text" id="text_' . $code . '" name="name[' . $code . ']" value="' . htmlspecialchars($value) . '"></em></span>
    </div>
  </div>';
}
echo'</div>

  <div class="mb-3 row">
    <label class="col-sm-2 control-label">'.$languageService->get('url').':</label>
    <div class="col-sm-8"><span class="text-muted small"><em>
        
        <input class="form-control" id="link" rows="10" cols="" name="link" value="' . htmlspecialchars($ds[ 'url' ]) .
        '" size="60"></em></span><br>
        <select id="windows" name="windows" class="form-select">'.$windows_1.'</select>
    </div>
  </div>

  <div class="mb-3 row">
    <label class="col-sm-2 control-label">'.$languageService->get('dropdown').':</label>
    <div class="col-sm-8 form-check form-switch" style="padding: 0px 43px;">
    '.$isdropdown.'
    </div>
  </div>

  <div class="mb-3 row">
    <div class="col-sm-offset-2 col-sm-10">
      <input type="hidden" name="captcha_hash" value="'.$hash.'" /><br>
      <input class="btn btn-warning" type="submit" name="saveeditcat" value="' . $languageService->get('edit_category') . '">
    </div>
  </div>
    </form></div></div>';
} else {

    echo '<div class="card">
        <div class="card-header">
            ' . $languageService->get('dashnavi') . '
        </div>
 <nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('dashnavi') . '</li>
  </ol>
</nav>

<div class="card-body">';


  echo'

<div class="mb-3 row">
    <label class="col-md-1 control-label">' . $languageService->get('options') . ':</label>
    <div class="col-md-8">
      <a class="btn btn-primary" href="admincenter.php?site=webside_navigation&amp;action=addcat" class="input">' .
        $languageService->get('new_category') . '</a>
        <a class="btn btn-primary" href="admincenter.php?site=webside_navigation&amp;action=add" class="input">' .
        $languageService->get('new_link') . '</a>
    </div>
  </div>';

    echo '<form method="post" action="admincenter.php?site=webside_navigation">
    <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
    <tr>
      <th width="25%" ><b>' . $languageService->get('name') . '</b></th>
      <th width="25%" ><b>Link</b></th>
            <th width="20%" ><b>' . $languageService->get('actions') . '</b></th>
            <th width="8%" ><b>' . $languageService->get('sort') . '</b></th>
    </tr></thead>';

    $ergebnis = safe_query("SELECT * FROM navigation_website_main ORDER BY sort");
    $tmp = mysqli_fetch_assoc(safe_query("SELECT count(mnavID) as cnt FROM navigation_website_main"));
    $anz = $tmp[ 'cnt' ];
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();
    while ($ds = mysqli_fetch_array($ergebnis)) {

        $list = '<select name="sortcat[]">';
                for ($n = 1; $n <= $anz; $n++) {
                    $list .= '<option value="' . $ds[ 'mnavID' ] . '-' . $n . '">' . $n . '</option>';
                }
                $list .= '</select>';
                $list = str_replace(
                    'value="' . $ds[ 'mnavID' ] . '-' . $ds[ 'sort' ] . '"',
                    'value="' . $ds[ 'mnavID' ] . '-' . $ds[ 'sort' ] . '" selected="selected"',
                    $list
                );

        if ($ds[ 'default' ] == 0) {
            $list = '<b>' . $ds[ 'list' ] . '</b>';
            $catactions = '';
            $name = $languageService->get( 'cat_' . htmlspecialchars($ds[ 'name' ]));
        } else {
            $sort = $list;
            $catactions =
                '<a class="btn btn-warning" href="admincenter.php?site=webside_navigation&amp;action=editcat&amp;mnavID=' . $ds[ 'mnavID' ] .
                '" class="input">' . $languageService->get('edit') . '</a>
                
<!-- Button trigger modal -->
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-delete" data-href="admincenter.php?site=webside_navigation&amp;delcat=true&amp;mnavID=' . $ds[ 'mnavID' ] .
                '&amp;captcha_hash=' . $hash . '">
    ' . $languageService->get('delete') . '
    </button>
    <!-- Button trigger modal END-->

     <!-- Modal -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">' . $languageService->get('dashnavi') . '</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $languageService->get('close') . '"></button>
      </div>
      <div class="modal-body"><p>' . $languageService->get('really_delete_category') . '</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $languageService->get('close') . '</button>
        <a class="btn btn-danger btn-ok">' . $languageService->get('delete') . '</a>
      </div>
    </div>
  </div>
</div>
<!-- Modal END -->

                ';

            $name = $ds['name'];
                $translate = new multiLanguage($lang);
                $translate->detectLanguages($name);
                $name = $translate->getTextByLanguage($name);
                
                $data_array = array();
                $data_array['$name'] = $ds['name'];
        }

        echo '<tr class="table-info">
            <td width="25%" class="td_head admin-nav-modal"><b>' . $name . '</b></td>
            <td width="25%" class="td_head admin-nav-modal"><small>' . $ds[ 'url' ] . '</small></td>
            <td width="25%" td_head">' . $catactions . '</td>
            <td width="15%" td_head">' . $sort . '</td>
        </tr>';
        
        $links = safe_query("SELECT * FROM navigation_website_sub WHERE mnavID='" . $ds[ 'mnavID' ] . "' ORDER BY sort");
        $tmp = mysqli_fetch_assoc(safe_query("SELECT count(snavID) as cnt FROM navigation_website_sub WHERE mnavID='" . $ds[ 'mnavID' ] . "'"));
        $anzlinks = $tmp[ 'cnt' ];

        $i = 1;
        $CAPCLASS = new \nexpell\Captcha;
        $CAPCLASS->createTransaction();
        $hash = $CAPCLASS->getHash();
        if (mysqli_num_rows($links)) {
            while ($db = mysqli_fetch_array($links)) {
                if ($i % 2) {
                    $td = 'td1';
                } else {
                    $td = 'td2';
                }

                $name = $db['name'];
                $translate = new multiLanguage($lang);
                $translate->detectLanguages($name);
                $name = $translate->getTextByLanguage($name);
                
                $data_array = array();
                $data_array['$name'] = $db['name'];

                $linklist = '<select name="sortlinks[]">';
                for ($n = 1; $n <= $anzlinks; $n++) {
                    $linklist .= '<option value="' . $db[ 'snavID' ] . '-' . $n . '">' . $n . '</option>';
                }
                $linklist .= '</select>';
                $linklist = str_replace(
                    'value="' . $db[ 'snavID' ] . '-' . $db[ 'sort' ] . '"',
                    'value="' . $db[ 'snavID' ] . '-' . $db[ 'sort' ] . '" selected="selected"',
                    $linklist
                );

                echo '<tr>
                     <td class="' . $td . '">&nbsp;-&nbsp;<b>' . $name . '</b></td>
                    <td class="' . $td . '"><small>' . $db[ 'url' ] . '</small></td>
                   
                   <td class="' . $td . '">
<a href="admincenter.php?site=webside_navigation&amp;action=edit&amp;snavID=' . $db[ 'snavID' ] .'" class="btn btn-warning">' . $languageService->get('edit') . '</a>

<!-- Button trigger modal -->
<button type="button" class="btn btn-danger" 
        data-bs-toggle="modal" 
        data-bs-target="#confirm-delete-link" 
        data-href="admincenter.php?site=webside_navigation&delete=true&snavID=' . $db['snavID'] . '&captcha_hash=' . $hash . '">
  ' . $languageService->get('delete') . '
</button>

<!-- Modal -->
<div class="modal fade" id="confirm-delete-link" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">' . $languageService->get('dashnavi') . '</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $languageService->get('close') . '"></button>
      </div>
      <div class="modal-body">
        <p>' . $languageService->get('really_delete_link') . '</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $languageService->get('close') . '</button>
        <a class="btn btn-danger btn-ok">' . $languageService->get('delete') . '</a>
      </div>
    </div>
  </div>
</div>
                    </td>
                    <td class="' . $td . '">' . $linklist . '</td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr>'.
                    '<td class="td1" colspan="4">' . $languageService->get('no_additional_links_available') . '</td>'.
                 '</tr>';
        }
    }
    
    echo '	<tr>
                <td class="td_head" colspan="4" align="right"><input class="btn btn-primary" type="submit" name="sortieren" value="' .
        $languageService->get('to_sort') . '"></td>
            </tr>
        </table>
    </form></div></div>';
}


?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const confirmModal = document.getElementById('confirm-delete');
    confirmModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const href = button.getAttribute('data-href');
        const confirmBtn = confirmModal.querySelector('.btn-ok');
        confirmBtn.setAttribute('href', href);
    });
});
document.addEventListener('DOMContentLoaded', function () {
    var confirmModal = document.getElementById('confirm-delete-link');
    confirmModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var href = button.getAttribute('data-href');
        var confirmBtn = confirmModal.querySelector('.btn-ok');
        confirmBtn.setAttribute('href', href);
    });
});
</script>