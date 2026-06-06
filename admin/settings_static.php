<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\NavigationUpdater;// SEO Anpassung

// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $_database,$languageService;
$lang = $languageService->detectLanguage();
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('static', true);

use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_static');

if (isset($_POST['save'])) {
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {

        // Eingabedaten sicherstellen
        $title = mysqli_real_escape_string($_database, $_POST['title']);
        $nameArray = $_POST['message'];
        $tags = $_POST['tags'];
        $editor = isset($_POST['editor']) ? '1' : '0';
        $date = time();
        $categoryID = (int)$_POST['categoryID'];
        $staticID = isset($_POST['staticID']) ? (int)$_POST['staticID'] : null;

        // Mehrsprachigen Text zusammenbauen
        $content = '';
        foreach (['de', 'en', 'it'] as $lang) {
            $text = $nameArray[$lang] ?? '';
            $content .= "[[lang:$lang]]" . $text;
        }

        // Rollen verarbeiten (Checkboxen)
        $access_roles = isset($_POST['access_roles']) ? $_POST['access_roles'] : [];
        $access_roles_json = mysqli_real_escape_string($_database, json_encode($access_roles, JSON_UNESCAPED_UNICODE));

        // Überprüfen, ob eine statische Seite mit der angegebenen staticID existiert
        if (!empty($staticID)) {
            // Update der statischen Seite in settings_static
            safe_query("
                UPDATE settings_static
                SET title = '$title',
                    content = '$content',
                    access_roles = '$access_roles_json',
                    date = '$date',
                    editor = '$editor',
                    categoryID = '$categoryID'
                WHERE staticID = '$staticID'
            ");

            // Navigationsmenü aktualisieren
            safe_query("
                DELETE FROM navigation_website_sub
                WHERE url = 'index.php?site=static&staticID=$staticID'
            ");

            safe_query("
                INSERT INTO navigation_website_sub (
                    mnavID, name, modulname, url, sort, indropdown, last_modified
                ) VALUES (
                    '$categoryID',
                    '$title',
                    'static',
                    'index.php?site=static&staticID=$staticID',
                    1,
                    1,
                     NOW()
                )
            ");
        } else {
            // Neue statische Seite erstellen
            safe_query("
                INSERT INTO settings_static (title, content, access_roles, date, editor, categoryID)
                VALUES ('$title', '$content', '$access_roles_json', '$date', '$editor', '$categoryID')
            ");
            $staticID = mysqli_insert_id($_database);

            // Navigationsmenü-Eintrag erstellen
            safe_query("
                INSERT INTO navigation_website_sub (
                    mnavID, name, modulname, url, sort, indropdown, last_modified
                ) VALUES (
                    '$categoryID',
                    '$title',
                    'static',
                    'index.php?site=static&amp;staticID=$staticID',
                    1,
                    1,
                    NOW()
                )
            ");
        }

        // Tags setzen
        \nexpell\Tags::setTags('static', $staticID, $tags);

        echo '<div class="alert alert-success" role="alert">' . $languageService->get('changes_successful') . '</div>';
        echo '<script type="text/javascript">
                setTimeout(function() {
                    window.location.href = "admincenter.php?site=settings_static";
                }, 3000); // 3 Sekunden warten
            </script>';

    } else {
        echo '<div class="alert alert-danger" role="alert">' . $languageService->get('transaction_invalid') . '</div>';
        echo '<script type="text/javascript">
                setTimeout(function() {
                    window.location.href = "admincenter.php?site=settings_static";
                }, 3000); // 3 Sekunden warten
            </script>';
    }
}

 elseif (isset($_GET['delete'])) {
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'])) {

        $staticID = (int)$_GET['staticID'];  // Sicher casten

        \nexpell\Tags::removeTags('static', $staticID);

        // Navigationseintrag löschen (ohne &amp;)
        safe_query("DELETE FROM `navigation_website_sub` WHERE `url` LIKE 'index.php?site=static&amp;staticID=" . $staticID . "%'");


        // Statischen Eintrag löschen
        safe_query("DELETE FROM `settings_static` WHERE `staticID` = '$staticID'");

    } else {
        echo '<div class="alert alert-danger" role="alert">' . $languageService->get('transaction_invalid') . '</div>';
        echo '<script type="text/javascript">
                setTimeout(function() {
                    window.location.href = "admincenter.php?site=settings_static";
                }, 3000); // 3 Sekunden warten
            </script>';
    }
}


if (isset($_GET['action']) && $_GET['action'] == "add") {
    // CAPTCHA-Hash generieren
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    
 

    // Rollen aus DB laden
    $role_result = safe_query("SELECT role_name FROM user_roles ORDER BY role_name ASC");
    $allRoles = [];
    while ($role = mysqli_fetch_array($role_result)) {
        $allRoles[] = $role['role_name'];
    }

    // Vorhandene Rollen auslesen
    $selectedRoles = [];
    if (!empty($ds['access_roles'])) {
        $selectedRoles = json_decode($ds['access_roles'], true);
    }

    // Checkboxen generieren
    $leftColumn = '';
    $rightColumn = '';
    $half = ceil(count($allRoles) / 2);
    $i = 0;

    foreach ($allRoles as $role) {
        $checked = in_array($role, $selectedRoles) ? 'checked="checked"' : '';
        $checkbox = '<div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" name="access_roles[]" value="' . htmlspecialchars($role) . '" ' . $checked . '>
            <label class="form-check-label">' . htmlspecialchars($role) . '</label>
        </div>';

        if ($i < $half) {
            $leftColumn .= $checkbox;
        } else {
            $rightColumn .= $checkbox;
        }
        $i++;
    }

    // Mehrsprachigen Text extrahieren
    function extractLangText(?string $multiLangText, string $lang): string {
        if (!$multiLangText) return '';
        if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s', $multiLangText, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    // Sprach-Array
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

    // Editor-Status
    $editor_checked = ($ds['editor'] ?? 0) == 1 ? 'checked' : '';




    // Kategorien für Select laden
    $category_select = '<select name="categoryID" class="form-select">';
    $category_select .= '<option value="0">-- keine Kategorie --</option>';
    $category_query = safe_query("SELECT mnavID, name FROM navigation_website_main ORDER BY sort ASC");

    while ($row = mysqli_fetch_array($category_query)) {
        // Erstelle ein neues multiLanguage-Objekt für die aktuelle Sprache
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($row['name']);
        
        #$selected = ($row['mnavID'] == $ds['categoryID']) ? ' selected' : '';
        $category_select .= '<option value="' . (int)$row['mnavID'] . '">' . htmlspecialchars($translate->getTextByLanguage($row['name'])) . '</option>';
    }
    $category_select .= '</select>';

    $roleCheckboxes = '
    <div class="row">
        <div class="col-md-6">' . $leftColumn . '</div>
        <div class="col-md-6">' . $rightColumn . '</div>
    </div>';       

        // HTML-Formular für die Eingabe von Daten
        echo '<div class="card">
                <div class="card-header">
                    ' . $languageService->get('static_pages') . '
                </div>

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admincenter.php?site=settings_static">' . $languageService->get('static_pages') . '</a></li>
                        <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('add') . '</li>
                    </ol>
                </nav>

                <div class="card-body">

                <form class="form-horizontal" method="post" id="post" name="post" action="">
                <div class="row">
                    <div class="col-md-6">

                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('category') . ':</label>
                            <div class="col-sm-8">
                                ' . $category_select . '
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('title') . ':</label>
                            <div class="col-sm-8"><span class="text-muted small"><em>
                                <input class="form-control" type="text" name="title" size="60" value="new" /></em></span>
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('tags') . ':</label>
                            <div class="col-sm-8"><span class="text-muted small"><em>
                                <input class="form-control" type="text" name="tags" size="60" value="" /></em></span>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('editor_is_displayed') . ':</label>
                            <div class="col-sm-8 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggle-editor" name="editor" value="1" checked>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                         <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('accesslevel') . ':</label>
                            <div class="col-sm-8">
                                ' . $roleCheckboxes . '
                            </div>
                        </div>
                    </div>
                </div>

                

                <div class="alert alert-info" role="alert">
                <label class="form-label"><h4>' . $languageService->get('text') . '</h4></label>';

                foreach ($languages as $code => $label) {
                    echo '
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">' . $label . ':</label>
                        <div class="col-sm-8"><textarea class="form-control lang-field" rows="6" id="editor_' . $code . '" name="message[' . $code . ']">'
                            . htmlspecialchars(extractLangText($ds['name'] ?? '', $code)) . '</textarea>
                        </div>
                    </div>';
                }

        echo '</div>


        

                <div class="mb-3 row">
                    <div class="col-md-12">
                        <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                        <button class="btn btn-success" type="submit" name="save"  />' . $languageService->get('add') . '</button>
                    </div>
                </div>
            </form>
        </div>
    </div>';
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('toggle-editor');
    const editors = document.querySelectorAll('.lang-field');

    function toggleEditors() {
        editors.forEach(textarea => {
            const id = textarea.id;
            if (toggle.checked) {
                if (!CKEDITOR.instances[id]) {
                    CKEDITOR.replace(id);
                }
            } else {
                if (CKEDITOR.instances[id]) {
                    CKEDITOR.instances[id].destroy(true);
                }
            }
        });
    }

    toggle.addEventListener('change', toggleEditors);
    toggleEditors(); // Initialer Zustand
});
</script>
<?php
  
} elseif (isset($_GET['action']) && $_GET['action'] == "edit") {

    $staticID = (int)$_GET['staticID'];
    $ergebnis = safe_query("SELECT * FROM `settings_static` WHERE staticID='" . $staticID . "'");
    $ds = mysqli_fetch_array($ergebnis);
    $content = $ds['content'];
    $title = $ds['title'];
    $tags = \nexpell\Tags::getTags('static', $staticID);

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    // Rollen aus DB laden
    $role_result = safe_query("SELECT role_name FROM user_roles ORDER BY role_name ASC");
    $allRoles = [];
    while ($role = mysqli_fetch_array($role_result)) {
        $allRoles[] = $role['role_name'];
    }

    // Vorhandene Rollen auslesen
    $selectedRoles = [];
    if (!empty($ds['access_roles'])) {
        $selectedRoles = json_decode($ds['access_roles'], true);
    }

    // Checkboxen generieren
    $leftColumn = '';
    $rightColumn = '';
    $half = ceil(count($allRoles) / 2);
    $i = 0;

    foreach ($allRoles as $role) {
        $checked = in_array($role, $selectedRoles) ? 'checked="checked"' : '';
        $checkbox = '<div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" name="access_roles[]" value="' . htmlspecialchars($role) . '" ' . $checked . '>
            <label class="form-check-label">' . htmlspecialchars($role) . '</label>
        </div>';

        if ($i < $half) {
            $leftColumn .= $checkbox;
        } else {
            $rightColumn .= $checkbox;
        }
        $i++;
    }

    // Mehrsprachigen Text extrahieren
    function extractLangText(?string $multiLangText, string $lang): string {
        if (!$multiLangText) return '';
        if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s', $multiLangText, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    // Sprach-Array
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

    // Editor-Status
    $editor_checked = ($ds['editor'] ?? 0) == 1 ? 'checked' : '';

    // Kategorien für Select laden
    $category_select = '<select name="categoryID" class="form-select">';
    $category_select .= '<option value="0">-- keine Kategorie --</option>';
    $category_query = safe_query("SELECT mnavID, name FROM navigation_website_main ORDER BY sort ASC");

    while ($row = mysqli_fetch_array($category_query)) {
        // Erstelle ein neues multiLanguage-Objekt für die aktuelle Sprache
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($row['name']);
        
        $selected = ($row['mnavID'] == $ds['categoryID']) ? ' selected' : '';
        $category_select .= '<option value="' . (int)$row['mnavID'] . '"' . $selected . '>' . htmlspecialchars($translate->getTextByLanguage($row['name'])) . '</option>';
    }
    $category_select .= '</select>';

    $roleCheckboxes = '
    <div class="row">
        <div class="col-md-6">' . $leftColumn . '</div>
        <div class="col-md-6">' . $rightColumn . '</div>
    </div>';

    // Formularausgabe
    echo '
    <div class="card">
        <div class="card-header">' . $languageService->get('static_pages') . '</div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="admincenter.php?site=settings_static">' . $languageService->get('static_pages') . '</a></li>
                <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('edit') . '</li>
            </ol>
        </nav>

        <div class="card-body">
            <div class="container py-5">
            <!-- Benutzerrolle zuweisen -->
            <h3 class="mb-4">' . $languageService->get('static_pages') . '</h3>

            <form class="form-horizontal" method="post" action="">

                <div class="row">
                    <div class="col-md-6">

                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('category') . ':</label>
                            <div class="col-sm-8">
                                ' . $category_select . '
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('title') . ':</label>
                            <div class="col-sm-8">
                                <input class="form-control" type="text" name="title" value="' . htmlspecialchars($title) . '" />
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('tags') . ':</label>
                            <div class="col-sm-8">
                                <input class="form-control" type="text" name="tags" value="' . htmlspecialchars($tags) . '" />
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('editor_is_displayed') . ':</label>
                            <div class="col-sm-8 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggle-editor" name="editor" value="1"' . ($ds['editor'] == 1 ? ' checked' : '') . '>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3 row">
                            <label class="col-sm-3 control-label">' . $languageService->get('accesslevel') . ':</label>
                            <div class="col-sm-8">
                                ' . $roleCheckboxes . '
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info" role="alert">
                <label class="form-label"><h4>' . $languageService->get('text') . '</h4></label>';

                foreach ($languages as $code => $label) {
                    echo '
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">' . $label . ':</label>
                        <div class="col-sm-8"><textarea class="form-control lang-field" rows="6" id="editor_' . $code . '" name="message[' . $code . ']">'
                            . htmlspecialchars(extractLangText($content ?? '', $code)) . '</textarea>
                        </div>
                    </div>';
                }

        echo '</div>

                <div class="mb-3 row">
                    <div class="col-md-12">
                        <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                        <input type="hidden" name="staticID" value="' . $staticID . '" />
                        <button class="btn btn-warning" type="submit" name="save">' . $languageService->get('edit') . '</button>
                    </div>
                </div>

            </form>
            </div>
        </div>
    </div>';

?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('toggle-editor');
    const editors = document.querySelectorAll('.lang-field');

    function toggleEditors() {
        editors.forEach(textarea => {
            const id = textarea.id;
            if (toggle.checked) {
                if (!CKEDITOR.instances[id]) {
                    CKEDITOR.replace(id);
                }
            } else {
                if (CKEDITOR.instances[id]) {
                    CKEDITOR.instances[id].destroy(true);
                }
            }
        });
    }

    toggle.addEventListener('change', toggleEditors);
    toggleEditors(); // Initialer Zustand
});
</script>
<?php
} else {

    echo '<div class="card">
            <div class="card-header">
                ' . $languageService->get('static_pages') . '
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb t-5 p-2 bg-light">
                    <li class="breadcrumb-item">
                        <a href="admincenter.php?site=settings_static">' . $languageService->get('static_pages') . '</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">News / Edit</li>
                </ol>
            </nav>

            <div class="card-body">
                <div class="form-group row">
                    <label class="col-md-1 control-label">' . $languageService->get('options') . ':</label>
                    <div class="col-md-8">
                        <a href="admincenter.php?site=settings_static&amp;action=add" class="btn btn-primary" type="button">
                            ' . $languageService->get('new_static_page') . '
                        </a>
                    </div>
                </div>

                <div class="container py-5">
                    <h3 class="mb-4">' . $languageService->get('static_pages') . '</h3>';

    $ergebnis = safe_query("SELECT * FROM settings_static ORDER BY staticID");

    echo '<table class="table table-bordered table-striped bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th><b>' . $languageService->get('id') . '</b></th>
                        <th><b>' . $languageService->get('title') . '</b></th>
                        <th><b>' . $languageService->get('accesslevel') . '</b></th>
                        <th><b>' . $languageService->get('actions') . '</b></th>
                    </tr>
                </thead>';

    $i = 1;
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    while ($ds = mysqli_fetch_array($ergebnis)) {
        $td = ($i % 2) ? 'td1' : 'td2';

        $roles = [];

        if (!empty($ds['access_roles'])) {
            // Als JSON gespeichert, z. B. ["Clanmitglied", "Moderator"]
            $roles = json_decode($ds['access_roles'], true);
        }

        $accesslevel = empty($roles)
            ? $languageService->get('public')
            : implode(', ', array_map(function($role) use ($languageService) {
                return $languageService->get(strtolower($role)) ?? htmlspecialchars($role);
            }, $roles));

        $title = $ds['title'];

        // Mehrsprachigkeit für Titel
        $translate = new multiLanguage($lang);
        $translate->detectLanguages($title);
        $title = $translate->getTextByLanguage($title);

        echo '<tr>
                <td>' . $ds['staticID'] . '</td>
                <td><a href="../index.php?site=static&amp;staticID=' . $ds['staticID'] . '" target="_blank">' . $title . '</a></td>
                <td>' . $accesslevel . '</td>
                <td>
                    <a href="admincenter.php?site=settings_static&amp;action=edit&amp;staticID=' . $ds['staticID'] . '" class="hidden-xs hidden-sm btn btn-warning" type="button">' . $languageService->get('edit') . '</a>

                    <!-- Button trigger modal -->
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-delete-' . $ds['staticID'] . '" data-href="admincenter.php?site=settings_static&amp;delete=true&amp;staticID=' . $ds['staticID'] . '&amp;captcha_hash=' . $hash . '">
                        ' . $languageService->get('delete') . '
                    </button>

                    <!-- Modal -->
                    <div class="modal fade" id="confirm-delete-' . $ds['staticID'] . '" tabindex="-1" role="dialog" aria-labelledby="myModalLabel-' . $ds['staticID'] . '" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="myModalLabel-' . $ds['staticID'] . '">' . $languageService->get('static_pages') . '</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $languageService->get('close') . '"></button>
                                </div>
                                <div class="modal-body"><p>' . $languageService->get('really_delete') . '</p></div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $languageService->get('close') . '</button>
                                    <a href="admincenter.php?site=settings_static&amp;delete=true&amp;staticID=' . $ds['staticID'] . '&amp;captcha_hash=' . $hash . '" class="btn btn-danger">' . $languageService->get('delete') . '</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Modal END -->
                </td>
            </tr>';

        
        $i++;
    }

    echo '</table>';
    echo '</div></div></div>';
}

?>