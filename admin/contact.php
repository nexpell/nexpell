<?php
use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

// Admin-Zugriff für das Modul prüfen
AccessControl::checkAdminAccess('ac_contact');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

if (isset($_GET['delete'])) {
    $contactID = (int)($_GET['contactID'] ?? 0);

    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_GET['captcha_hash'] ?? '')) {

        $contactName = '';
        $res = mysqli_query($_database, "SELECT `name` FROM `contact` WHERE `contactID` = '$contactID' LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) $contactName = trim((string)($row['name'] ?? ''));

        safe_query("DELETE FROM `contact` WHERE `contactID` = '$contactID'");

        nx_audit_delete('contact', (string)$contactID, ($contactName !== '' ? $contactName : (string)$contactID), 'admincenter.php?site=contact');
        nx_redirect('admincenter.php?site=contact', 'success', 'alert_deleted', false);
    }

    nx_redirect('admincenter.php?site=contact', 'danger', 'alert_transaction_invalid', false);
}

elseif (isset($_POST['sortieren'])) {
    $sortcontact = $_POST['sortcontact'] ?? [];
    $CAPCLASS = new \nexpell\Captcha;

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {

        if (is_array($sortcontact)) {
            foreach ($sortcontact as $sortstring) {
                [$id, $sort] = array_pad(explode("-", (string)$sortstring, 2), 2, null);
                $id   = (int)$id;
                $sort = (int)$sort;

                safe_query("UPDATE `contact` SET `sort` = '$sort' WHERE `contactID` = '$id'");
            }
        }

        nx_redirect('admincenter.php?site=contact', 'success', 'alert_sorted', false);
    }

    nx_redirect('admincenter.php?site=contact', 'danger', 'alert_transaction_invalid', false);
}

elseif (isset($_POST['save'])) {
    $name  = (string)($_POST['name'] ?? '');
    $email = (string)($_POST['email'] ?? '');

    $CAPCLASS = new \nexpell\Captcha;
    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_redirect('admincenter.php?site=contact&action=add', 'danger', 'alert_transaction_invalid', false);
    }

    if (!checkforempty(['name', 'email'])) {
        nx_redirect('admincenter.php?site=contact&action=add', 'warning', 'alert_information_incomplete', false);
    }

    safe_query("INSERT INTO `contact` (`name`, `email`, `sort`) VALUES ('$name', '$email', '1')");
    $newContactID = (int)mysqli_insert_id($_database);

    nx_audit_create('contact', (string)$newContactID, $name, 'admincenter.php?site=contact', ['email' => $email]);
    nx_redirect('admincenter.php?site=contact', 'success', 'alert_saved', false);
}

elseif (isset($_POST['saveedit'])) {
    $name      = (string)($_POST['name'] ?? '');
    $email     = (string)($_POST['email'] ?? '');
    $contactID = (int)($_POST['contactID'] ?? 0);

    $editUrl = 'admincenter.php?site=contact&action=edit&contactID=' . $contactID;

    $CAPCLASS = new \nexpell\Captcha;
    if (!$CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {
        nx_redirect($editUrl, 'danger', 'alert_transaction_invalid', false);
    }

    if (!checkforempty(['name', 'email'])) {
        nx_redirect($editUrl, 'warning', 'alert_information_incomplete', false);
    }

    // Aktuelle Daten laden (für Änderungsprüfung)
    $oldName = '';
    $oldEmail = '';
    $res = mysqli_query($_database, "SELECT name, email FROM `contact` WHERE contactID = '$contactID' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $oldName  = (string)$row['name'];
        $oldEmail = (string)$row['email'];
    }

    $hasChanged = ($oldName !== $name || $oldEmail !== $email);

    // Update immer ausführen (Save bleibt Save)
    safe_query("UPDATE `contact` SET `name` = '$name', `email` = '$email' WHERE `contactID` = '$contactID'");

    nx_audit_update('contact', (string)$contactID, $hasChanged, $name, 'admincenter.php?site=contact', ['email' => $email]);
    nx_redirect('admincenter.php?site=contact', 'success', 'alert_saved', false);
}

// Kontaktformular anzeigen (Add/Edit)

if (isset($_GET['action'])) {
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    if ($_GET['action'] == "add") {
        echo '<div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-person-plus"></i>
                        <span>' . $languageService->get('contact') . '</span>
                        <small class="small-muted">' . $languageService->get('add') . '</small>
                    </div>
                </div>

                <div class="card-body p-4">

                    <div class="row">
                        <div class="col-12 col-lg-12">
                            <form method="post" action="admincenter.php?site=contact">
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label class="form-label">' . $languageService->get('contact_name') . '</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>

                                    <div class="col-12 col-md-6 mb-3">
                                        <label class="form-label">' . $languageService->get('email') . '</label>
                                        <input type="email" class="form-control" name="email" placeholder="name@example.com" required>
                                    </div>
                                </div>

                                <input type="hidden" name="captcha_hash" value="' . htmlspecialchars($hash) . '">

                                <button class="btn btn-primary" type="submit" name="save">
                                    ' . $languageService->get('save') . '
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>';
    } elseif ($_GET['action'] == "edit") {
        $contactID = (int)$_GET['contactID'];
        $result = safe_query("SELECT * FROM `contact` WHERE `contactID` = '$contactID'");
        $ds = mysqli_fetch_array($result);
        echo '<div class="card shadow-sm border-0 mb-4 mt-4">
                <div class="card-header">
                    <div class="card-title">
                        <i class="bi bi-pencil-square"></i>
                        <span>' . $languageService->get('contact') . '</span>
                        <small class="small-muted">' . $languageService->get('edit') . '</small>
                    </div>
                </div>

                <div class="card-body p-4">

                    <div class="row">
                        <div class="col-12 col-lg-12">
                            <form method="post" action="admincenter.php?site=contact">
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label class="form-label">' . $languageService->get('contact_name') . '</label>
                                        <input type="text" class="form-control" name="name"
                                            value="' . htmlspecialchars((string)$ds['name']) . '" required>
                                    </div>

                                    <div class="col-12 col-md-6 mb-3">
                                        <label class="form-label">' . $languageService->get('email') . '</label>
                                        <input type="email" class="form-control" name="email"
                                            value="' . htmlspecialchars((string)$ds['email']) . '" placeholder="name@example.com" required>
                                    </div>
                                </div>

                                <input type="hidden" name="captcha_hash" value="' . htmlspecialchars($hash) . '">
                                <input type="hidden" name="contactID" value="' . (int)$contactID . '">

                                <button class="btn btn-primary" type="submit" name="saveedit">
                                    ' . $languageService->get('save') . '
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>';
    }
}

// Kontaktliste
else {
    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>' . $languageService->get('contact') . '</span>
                    <small class="small-muted">' . $languageService->get('settings') . '</small>
                </div>
            </div>

            <div class="card-body p-4">
                <a href="admincenter.php?site=contact&amp;action=add" class="btn btn-secondary mb-4">
                    ' . $languageService->get('add') . '
                </a>
                <form method="post" action="admincenter.php?site=contact">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>' . $languageService->get('contact_name') . '</th>
                                    <th>' . $languageService->get('email') . '</th>
                                    <th class="text-nowrap" style="width: 20%;">' . $languageService->get('actions') . '</th>
                                    <th class="text-nowrap" style="width: 1%;">' . $languageService->get('sort') . '</th>
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
            $contactIdInt = (int)$ds['contactID'];
            $deleteUrl = 'admincenter.php?site=contact&delete=true&contactID=' . $contactIdInt . '&captcha_hash=' . rawurlencode($hash);
            $deleteUrlAttr = htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8');
            echo '
        <tr>
            <td>' . htmlspecialchars($ds['name']) . '</td>
            <td>' . htmlspecialchars($ds['email']) . '</td>
            <td class="text-nowrap">
                <div class="d-inline-flex flex-wrap gap-2">
                    <a href="admincenter.php?site=contact&amp;action=edit&amp;contactID=' . $contactIdInt . '"
                       class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto">
                        <i class="bi bi-pencil-square"></i>' . $languageService->get('edit') . '
                    </a>

                    <button type="button"
                            class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto"
                            data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal"
                            data-delete-url="' . $deleteUrlAttr . '">
                        <i class="bi bi-trash"></i>' . $languageService->get('delete') . '
                    </button>
                </div>
            </td>
            <td>
                <select class="form-select no-border text-center" name="sortcontact[]">';
        for ($n = 1; $n <= $count; $n++) {
            $selected = ($ds['sort'] == $n) ? ' selected' : '';
            echo '<option value="' . $ds['contactID'] . '-' . $n . '"' . $selected . '>' . $n . '</option>';
        }
        echo '</select>
            </td>
        </tr>';
        $i++;
    }

    echo '<tr>
            <td colspan="4" class="text-end">
                <input type="hidden" name="captcha_hash" value="' . htmlspecialchars($hash) . '">
                <button class="btn btn-primary mb-2" type="submit" name="sortieren">
                    <i class="bi bi-arrow-down-up"></i> ' . $languageService->get('sort') . '
                </button>
            </td>
        </tr>
    </tbody>
</table>
</div>
</form>
</div>
</div>';
}
?>