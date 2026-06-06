<?php
use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('contact', true);

use nexpell\AccessControl;

// Admin-Zugriff für das Modul prüfen
AccessControl::checkAdminAccess('ac_contact');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

// Kontakt löschen
if (isset($_GET['delete'])) {
    $contactID = (int)$_GET['contactID'];
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'])) {
        safe_query("DELETE FROM `contact` WHERE `contactID` = '$contactID'");
    } else {
        echo $languageService->get('transaction_invalid');
    }
}

// Kontakte sortieren
elseif (isset($_POST['sortieren'])) {
    $sortcontact = $_POST['sortcontact'];
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (is_array($sortcontact)) {
            foreach ($sortcontact as $sortstring) {
                list($id, $sort) = explode("-", $sortstring);
                safe_query("UPDATE `contact` SET `sort` = '$sort' WHERE `contactID` = '$id'");
            }
        }
    } else {
        echo $languageService->get('transaction_invalid');
    }
}

// Kontakt hinzufügen
elseif (isset($_POST['save'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (checkforempty(['name', 'email'])) {
            safe_query("INSERT INTO `contact` (`name`, `email`, `sort`) VALUES ('$name', '$email', '1')");
        } else {
            echo $languageService->get('information_incomplete');
        }
    } else {
        echo $languageService->get('transaction_invalid');
    }
}

// Kontakt bearbeiten
elseif (isset($_POST['saveedit'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contactID = (int)$_POST['contactID'];
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'])) {
        if (checkforempty(['name', 'email'])) {
            safe_query("UPDATE `contact` SET `name` = '$name', `email` = '$email' WHERE `contactID` = '$contactID'");
        } else {
            echo $languageService->get('information_incomplete');
        }
    } else {
        echo $languageService->get('transaction_invalid');
    }
}

// Kontaktformular anzeigen (Add/Edit)
if (isset($_GET['action'])) {
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    if ($_GET['action'] == "add") {
        echo '
        <div class="card">
            <div class="card-header">' . $languageService->get('contact') . '</div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb t-5 p-2 bg-light">
                    <li class="breadcrumb-item"><a href="admincenter.php?site=contact">' . $languageService->get('contact') . '</a></li>
                    <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('add_contact') . '</li>
                </ol>
            </nav>
            <div class="card-body">
            <div class="container py-5">
                <form method="post" action="admincenter.php?site=contact">
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">' . $languageService->get('contact_name') . ':</label>
                        <div class="col-sm-8"><input type="text" class="form-control" name="name" /></div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">' . $languageService->get('email') . ':</label>
                        <div class="col-sm-8"><input type="text" name="email" class="form-control" /></div>
                    </div>
                    <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                    <button class="btn btn-success" type="submit" name="save">' . $languageService->get('add_contact') . '</button>
                </form>
                </div>
            </div>
        </div>';
    } elseif ($_GET['action'] == "edit") {
        $contactID = (int)$_GET['contactID'];
        $result = safe_query("SELECT * FROM `contact` WHERE `contactID` = '$contactID'");
        $ds = mysqli_fetch_array($result);

        echo '
        <div class="card">
            <div class="card-header">' . $languageService->get('contact') . '</div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb t-5 p-2 bg-light">
                    <li class="breadcrumb-item"><a href="admincenter.php?site=contact">' . $languageService->get('contact') . '</a></li>
                    <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('edit_contact') . '</li>
                </ol>
            </nav>
            <div class="card-body">
            <div class="container py-5">
                <form method="post" action="admincenter.php?site=contact">
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">' . $languageService->get('contact_name') . ':</label>
                        <div class="col-sm-8"><input type="text" class="form-control" name="name" value="' . htmlspecialchars($ds['name']) . '" /></div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-2 col-form-label">' . $languageService->get('email') . ':</label>
                        <div class="col-sm-8"><input type="text" name="email" class="form-control" value="' . htmlspecialchars($ds['email']) . '" /></div>
                    </div>
                    <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                    <input type="hidden" name="contactID" value="' . $contactID . '" />
                    <button class="btn btn-warning" type="submit" name="saveedit">' . $languageService->get('edit_contact') . '</button>
                </form>
            </div>
            </div>
        </div>';
    }
}

// Kontaktliste
else {
    echo '
    <div class="card">
        <div class="card-header">' . $languageService->get('contact') . '</div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb t-5 p-2 bg-light">
                <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('contact') . '</li>
            </ol>
        </nav>
        <div class="card-body">
            <div class="mb-3 row">
                <label class="col-md-1 control-label">' . $languageService->get('options') . ':</label>
                <div class="col-md-8">
                    <a href="admincenter.php?site=contact&amp;action=add" class="btn btn-primary">' . $languageService->get('new_contact') . '</a>
                </div>
            </div>
            <div class="container py-5">
            <form method="post" action="admincenter.php?site=contact">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>' . $languageService->get('contact_name') . '</th>
                            <th>' . $languageService->get('email') . '</th>
                            <th>' . $languageService->get('actions') . '</th>
                            <th>' . $languageService->get('sort') . '</th>
                        </tr>
                    </thead>
                    <tbody>';

    $result = safe_query("SELECT * FROM `contact` ORDER BY `sort`");
    $count = mysqli_fetch_assoc(safe_query("SELECT COUNT(*) AS cnt FROM `contact`"))['cnt'];
    $i = 1;

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    while ($ds = mysqli_fetch_array($result)) {
        echo '
        <tr>
            <td>' . htmlspecialchars($ds['name']) . '</td>
            <td>' . htmlspecialchars($ds['email']) . '</td>
            <td>
                <a href="admincenter.php?site=contact&amp;action=edit&amp;contactID=' . $ds['contactID'] . '" class="btn btn-warning">' . $languageService->get('edit') . '</a>

                <!-- Button trigger modal -->
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirm-delete" data-href="admincenter.php?site=contact&amp;delete=true&amp;contactID=' . $ds['contactID'] . '&amp;captcha_hash=' . $hash . '">
                    ' . $languageService->get('delete') . '
                </button>
                <!-- Button trigger modal END -->

                <!-- Modal -->
                <div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
                    <div class="modal-dialog"><div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">' . $languageService->get('contact') . '</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $languageService->get('close') . '"></button>
                        </div>
                        <div class="modal-body"><p>' . $languageService->get('really_delete') . '</p></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $languageService->get('close') . '</button>
                            <a class="btn btn-danger btn-ok">' . $languageService->get('delete') . '</a>
                        </div>
                    </div></div>
                </div>
                <!-- Modal END -->
            </td>
            <td>
                <select name="sortcontact[]">';
        for ($n = 1; $n <= $count; $n++) {
            $selected = ($ds['sort'] == $n) ? ' selected' : '';
            echo '<option value="' . $ds['contactID'] . '-' . $n . '"' . $selected . '>' . $n . '</option>';
        }
        echo '</select>
            </td>
        </tr>';
        $i++;
    }

    echo '
                    <tr>
                        <td colspan="4" class="text-end">
                            <input type="hidden" name="captcha_hash" value="' . $hash . '" />
                            <input class="btn btn-primary" type="submit" name="sortieren" value="' . $languageService->get('to_sort') . '" />
                        </td>
                    </tr>
                    </tbody>
                </table>
            </form>
            </div>
        </div>
    </div>';
}
