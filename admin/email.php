<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

// Admin-Zugriff für das Modul prüfen
AccessControl::checkAdminAccess('ac_email');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

if (isset($_POST['submit'])) {

    $CAPCLASS = new \nexpell\Captcha;

    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {

        safe_query("
            UPDATE email SET
                host='" . escape($_POST['host'] ?? '') . "',
                user='" . escape($_POST['user'] ?? '') . "',
                password='" . escape($_POST['password'] ?? '') . "',
                port=" . intval($_POST['port'] ?? 0) . ",
                secure=" . intval($_POST['secure'] ?? 0) . ",
                auth=" . intval($_POST['auth'] ?? 0) . ",
                debug=" . intval($_POST['debug'] ?? 0) . ",
                smtp=" . intval($_POST['smtp'] ?? 0) . ",
                html=" . intval($_POST['html'] ?? 0) . "
            WHERE emailID=1
            LIMIT 1
        ");

        nx_audit_update('email', null, true, null, 'admincenter.php?site=email');
        nx_redirect('admincenter.php?site=email', 'success', 'alert_saved', true, false);
    }

    nx_redirect('admincenter.php?site=email', 'danger', 'alert_transaction_invalid', true, false);
}
elseif (isset($_POST['send'])) {

    $to      = $_POST['email'] ?? '';
    $subject = 'test_subject';
    $message = 'test_message';

    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST['captcha_hash'] ?? '')) {

        $sendmail = \nexpell\Email::sendEmail($admin_email, 'Test eMail', $to, $subject, $message);

        $result = $sendmail['result'] ?? 'fail';
        $error  = (string)($sendmail['error'] ?? '');
        $debug  = (string)($sendmail['debug'] ?? '');

        if ($result === 'fail') {
            nx_audit_action('email', 'audit_action_email_test_fail', 'test', (string)$to, 'admincenter.php?site=email&action=test', ['to' => (string)$to, 'error' => ($error !== '' ? $error : null)]);
            $msg = nx_translate('alert_test_fail');
            if ($error !== '') $msg .= ' | Error: ' . $error;
            if ($debug !== '') {
                $dbg = mb_substr($debug, 0, 500);
                if (strlen($debug) > 500) $dbg .= '…';
                $msg .= ' | Debug: ' . $dbg;
            }
            nx_redirect('admincenter.php?site=email&action=test', 'danger', $msg, true, true);
        }

        nx_audit_action('email', 'audit_action_email_test_ok', 'test', (string)$to, 'admincenter.php?site=email&action=test', ['to' => (string)$to]);
        nx_redirect('admincenter.php?site=email&action=test', 'success', 'alert_test_ok', false);
    }

    nx_redirect('admincenter.php?site=email&action=test', 'danger', 'alert_transaction_invalid', false);
}
elseif ($action == 'test') {

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-envelope-check"></i>
                    <span>' . $languageService->get('email') . '</span>
                    <small class="small-muted">' . $languageService->get('test_email') . '</small>
                </div>
            </div>

            <div class="card-body p-4">
                <div class="row">
                    <div class="col-12 col-lg-8">
                        <form method="post" action="admincenter.php?site=email&action=test" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">' . $languageService->get('email') . '</label>
                                <input class="form-control" type="email" name="email" value="" placeholder="name@example.com" required>
                                <div class="form-text text-muted">' . $languageService->get('test_subject') . ' / ' . $languageService->get('test_message') . '</div>
                            </div>

                            <input type="hidden" name="captcha_hash" value="' . htmlspecialchars($hash) . '">

                            <button class="btn btn-primary" type="submit" name="send">
                                ' . $languageService->get('send') . '
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>';

} else {

    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();
    $settings = safe_query("SELECT * FROM email");
    $ds = mysqli_fetch_array($settings);

    if ($ds[ 'smtp' ] == '0') {
        if ($ds[ 'auth' ]) {
            $auth = " checked=\"checked\"";
        } else {
            $auth = "";
        }
        $show_auth = " style=\"display: none;\"";
        $show_auth2 = " style=\"display: none;\"";
    } else {
        if ($ds[ 'auth' ]) {
            $auth = " checked=\"checked\"";
            $show_auth = "";
        } else {
            $auth = "";
            $show_auth = " style=\"display: none;\"";
        }
        $show_auth2 = "";
    }

    if ($ds[ 'html' ]) {
        $html = " checked=\"checked\"";
    } else {
        $html = "";
    }

    $smtp = "<option value='0'>" . $languageService->get('type_phpmail') . "</option><option value='1'>" .
        $languageService->get('type_smtp') . "</option><option value='2'>" . $languageService->get('type_pop') .
        "</option>";
    $smtp = str_replace("value='" . $ds[ 'smtp' ] . "'", "value='" . $ds[ 'smtp' ] . "' selected='selected'", $smtp);

    if (extension_loaded('openssl')) {
        $secure = "<option value='0'>" . $languageService->get('secure_none') . "</option><option value='1'>" .
            $languageService->get('secure_tls') . "</option><option value='2'>" . $languageService->get('secure_ssl') .
            "</option>";
    } else {
        $secure = "<option value='0'>" . $languageService->get('secure_none') . "</option>";
    }

    $secure =
        str_replace("value='" . $ds[ 'secure' ] . "'", "value='" . $ds[ 'secure' ] . "' selected='selected'", $secure);

    $debug = "<option value='0'>" . $languageService->get('debug_0') . "</option><option value='1'>" .
        $languageService->get('debug_1') . "</option><option value='2'>" . $languageService->get('debug_2') .
        "</option><option value='3'>" . $languageService->get('debug_3') . "</option><option value='4'>" .
        $languageService->get('debug_4') . "</option>";
    $debug =
        str_replace("value='" . $ds[ 'debug' ] . "'", "value='" . $ds[ 'debug' ] . "' selected='selected'", $debug);

    echo '<div class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-envelope"></i>
                <span>' . $languageService->get('email') . '</span>
                <small class="small-muted">' . $languageService->get('settings') . '</small>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row mb-4">
                <div class="col-md-8">
                    <a class="btn btn-secondary" href="admincenter.php?site=email&action=test">
                        ' . $languageService->get('test_email') . '</a>
                </div>
            </div>

            <script type="text/javascript">
function HideFields(state) {
        if (state == true) {
            document.getElementById(\'tr_user\').style.display = "";
            document.getElementById(\'tr_password\').style.display = "";
        } else {
            document.getElementById(\'tr_user\').style.display = "none";
            document.getElementById(\'tr_password\').style.display = "none";
        }
    }

    function SetPort() {
        var x = document.getElementById(\'select_secure\').selectedIndex;
        switch (x) {
            case 0:
                var port = \'25\';
                break;
            case 1:
                var port = \'587\';
                break;
            case 2:
                var port = \'465\';
                break;
            default:
                var port = \'25\';
        }
        document.getElementById(\'input_port\').value = port;
    }

    function HideFields2() {
        var x = document.getElementById(\'select_smtp\').selectedIndex;
        if (x == \'0\') {
            document.getElementById(\'tr_user\').style.display = "none";
            document.getElementById(\'tr_password\').style.display = "none";
            document.getElementById(\'tr_auth\').style.display = "none";
            document.getElementById(\'tr_host\').style.display = "none";
            document.getElementById(\'tr_debug\').style.display = "none";
            document.getElementById(\'tr_port\').style.display = "none";
            document.getElementById(\'tr_secure\').style.display = "none";
        } else {
            var y = document.getElementById(\'check_auth\').checked;
            if (y === true) {
                document.getElementById(\'tr_user\').style.display = "";
                document.getElementById(\'tr_password\').style.display = "";
                document.getElementById(\'tr_auth\').style.display = "";
                document.getElementById(\'tr_host\').style.display = "";
                document.getElementById(\'tr_port\').style.display = "";
                document.getElementById(\'tr_secure\').style.display = "";
                document.getElementById(\'tr_debug\').style.display = "";
            } else {
                document.getElementById(\'tr_host\').style.display = "";
                document.getElementById(\'tr_auth\').style.display = "";
                document.getElementById(\'tr_port\').style.display = "";
                document.getElementById(\'tr_secure\').style.display = "";
                document.getElementById(\'tr_debug\').style.display = "";
            }
        }
    }
</script>
    <form method="post" action="admincenter.php?site=email" enctype="multipart/form-data">
        <table class="table">
            <tr>
                <td width="15%"><b>' . $languageService->get('type') . '</b></td>
                <td width="35%">
                    <div class="input-group no-border">
                        <select class="form-select no-border" id="select_smtp" name="smtp" onchange="javascript:HideFields2();"
                            onmouseover="showWMTT(\'id1\')" onmouseout="hideWMTT()">' . $smtp . '</select>
                    </div>
                </td>
            </tr>

            <tr id="tr_auth"' . $show_auth2 . '>
                <td width="15%"><b>' . $languageService->get('auth') . '</b></td>
                <td width="35%">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="check_auth" name="auth"
                            onchange="javascript:HideFields(this.checked);" 
                            onmouseover="showWMTT(\'id2\')" onmouseout="hideWMTT()" 
                            value="1" ' . $auth . '/>
                    </div>
                </td>
            </tr>

            <tr id="tr_user"' . $show_auth . '>
                <td width="15%"><b>' . $languageService->get('user') . '</b></td>
                <td width="35%">
                    <div class="input-group">
                        <input class="form-control" name="user" type="text" 
                            value="' . htmlspecialchars($ds['user']) . '" size="35"
                            onmouseover="showWMTT(\'id3\')" onmouseout="hideWMTT()"/>
                    </div>
                </td>
            </tr>

            <tr id="tr_password"' . $show_auth . '>
                <td width="15%"><b>' . $languageService->get('password') . '</b></td>
                <td width="35%">
                    <div class="input-group">
                        <input class="form-control" type="password" name="password" 
                            value="' . htmlspecialchars($ds['password']) . '" size="35"
                            onmouseover="showWMTT(\'id4\')" onmouseout="hideWMTT()"/>
                    </div>
                </td>
            </tr>

            <tr id="tr_host"' . $show_auth2 . '>
                <td width="15%"><b>' . $languageService->get('host') . '</b></td>
                <td width="35%">
                    <div class="input-group">
                        <input class="form-control" type="text" name="host" 
                            value="' . htmlspecialchars($ds['host']) . '" size="35"
                            onmouseover="showWMTT(\'id6\')" onmouseout="hideWMTT()"/>
                    </div>
                </td>
            </tr>

            <tr id="tr_port"' . $show_auth2 . '>
                <td width="15%"><b>' . $languageService->get('port') . '</b></td>
                <td width="35%">
                    <div class="input-group">
                        <input class="form-control" id="input_port" type="text" name="port" 
                            value="' . htmlspecialchars($ds['port']) . '" size="5"
                            onmouseover="showWMTT(\'id5\')" onmouseout="hideWMTT()"/>
                    </div>
                </td>
            </tr>

            <tr id="tr_html">
                <td width="15%"><b>' . $languageService->get('html') . '</b></td>
                <td width="35%">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="check_html" name="html"
                            onmouseover="showWMTT(\'id7\')" onmouseout="hideWMTT()" 
                            value="1" ' . $html . '/>
                    </div>
                </td>
            </tr>

            <tr id="tr_secure"' . $show_auth2 . '>
                <td width="15%"><b>' . $languageService->get('secure') . '</b></td>
                <td width="35%">
                    <div class="input-group no-border">
                        <select class="form-select" id="select_secure" name="secure" 
                            onmouseover="showWMTT(\'id8\')" onchange="javascript:SetPort();" onmouseout="hideWMTT()">
                            ' . $secure . '
                        </select>
                    </div>
                </td>
            </tr>

            <tr id="tr_debug"' . $show_auth2 . '>
                <td width="15%"><b>' . $languageService->get('debug') . '</b></td>
                <td width="35%">
                    <div class="input-group no-border">
                        <select class="form-select" id="select_debug" name="debug" 
                            onmouseover="showWMTT(\'id9\')" onmouseout="hideWMTT()">
                            ' . $debug . '
                        </select>
                    </div>
                </td>
            </tr>
        </table>

        <div class="d-flex justify-content-start gap-2 pt-3 mt-4">
            <input type="hidden" name="captcha_hash" value="' . $hash . '">
            <button class="btn btn-primary" type="submit" name="submit">' . $languageService->get('save') . '</button>
        </div>
        </form>
        </div>
    </div>';
}
?>