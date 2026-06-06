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
$languageService->readModule('email', true);

use nexpell\AccessControl;

// Admin-Zugriff für das Modul prüfen
AccessControl::checkAdminAccess('ac_email');

if (isset($_GET[ 'action' ])) {
    $action = $_GET[ 'action' ];
} else {
    $action = '';
}

if (isset($_POST[ 'submit' ])) {
    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST[ 'captcha_hash' ])) {
        safe_query(
            "UPDATE
                email
            SET
                host='" . $_POST[ 'host' ] . "',
                user='" . $_POST[ 'user' ] . "',
                password='" . $_POST[ 'password' ] . "',
                port='" . intval($_POST[ 'port' ]) . "',
                secure='" . intval($_POST[ 'secure' ]) . "',
                auth='" . intval($_POST[ 'auth' ]) . "',
                debug='" . intval($_POST[ 'debug' ]) . "',
                smtp='" . intval($_POST[ 'smtp' ]) . "',
                html='" . intval($_POST[ 'html' ]) . "'"
        );
        redirect("admincenter.php?site=email", "", 0);
    } else {
        redirect("admincenter.php?site=email", $languageService->get('transaction_invalid'), 3);
    }
} elseif (isset($_POST[ 'send' ])) {
    $to = $_POST[ 'email' ];
    $subject = $languageService->get('test_subject');
    $message = $languageService->get('test_message');

    $CAPCLASS = new \nexpell\Captcha;
    if ($CAPCLASS->checkCaptcha(0, $_POST[ 'captcha_hash' ])) {
        $sendmail = \nexpell\Email::sendEmail($admin_email, 'Test eMail', $to, $subject, $message);
        if ($sendmail['result'] == 'fail') {
            if (isset($sendmail['debug'])) {
                echo '<b>' . $languageService->get('test_fail') . '</b>';
                echo '<br>' . $sendmail[ 'error' ];
                echo '<br>' . $sendmail[ 'debug' ];
                redirect("admincenter.php?site=email&amp;action=test", $languageService->get('test_fail'), 10);
            } else {
                echo '<b>' . $languageService->get('test_fail') . '</b>';
                echo '<br>' . $sendmail[ 'error' ];
                redirect("admincenter.php?site=email&amp;action=test", $languageService->get('test_fail'), 10);
            }
        } else {
            if (isset($sendmail[ 'debug' ])) {
                echo '<b> Debug </b>';
                echo '<br>' . $sendmail[ 'debug' ];
                redirect("admincenter.php?site=email&amp;action=test", $languageService->get('test_ok'), 10);
            } else {
                redirect("admincenter.php?site=email&amp;action=test", $languageService->get('test_ok'), 3);
            }
        }
    } else {
        redirect("admincenter.php?site=email&amp;action=test", $languageService->get('transaction_invalid'), 3);
    }
} elseif ($action == "test") {
    $CAPCLASS = new \nexpell\Captcha;
    $CAPCLASS->createTransaction();
    $hash = $CAPCLASS->getHash();

    echo'<div class="card">
            <div class="card-header">
                ' . $languageService->get('email') . '
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb t-5 p-2 bg-light">
                    <li class="breadcrumb-item"><a href="admincenter.php?site=email">' . $languageService->get('email') . '</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
                </ol>
            </nav>
            <div class="card-body">
                <div class="container py-5">
                    <form method="post" action="admincenter.php?site=email&amp;action=test" enctype="multipart/form-data">
                        <div class="mb-3 row">
                            <label class="col-sm-2 col-form-label">
                                ' . $languageService->get('email') . ':
                            </label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="email" />
                            </div>
                        </div>

                        <input type="hidden" name="captcha_hash" value="' . $hash . '" />

                        <div class="mb-3 row">
                            <div class="offset-sm-2 col-sm-8">
                                <button class="btn btn-success" type="submit" name="send">
                                    ' . $languageService->get('send') . '
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        ';
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

    echo '<div class="card">
        <div class="card-header">
            ' . $languageService->get('email') . '
        </div>
        <nav aria-label="breadcrumb">
                        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item active" aria-current="page">' . $languageService->get('email') . '</li>
          </ol>
        </nav>

<div class="card-body">

<div class="mb-3 row">
    <label class="col-md-1 control-label">' . $languageService->get('options') . ':</label>
    <div class="col-md-8">
      <a href="admincenter.php?site=email&amp;action=test" class="btn btn-primary" type="button">' . $languageService->get('test_email') . '</a>
    </div>
  </div>';

    echo '<script type="text/javascript">
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
<div class="container py-5">
    <form method="post" action="admincenter.php?site=email" enctype="multipart/form-data">

        <table class="table table-bordered table-striped">
            <tr>
                <td width="15%"><b>' . $languageService->get('type') . '</b></td>
                <td width="35%">
                    <div class="input-group">
                        <select class="form-select" id="select_smtp" name="smtp" onchange="javascript:HideFields2();"
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
                    <div class="input-group">
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
                    <div class="input-group">
                        <select class="form-select" id="select_debug" name="debug" 
                            onmouseover="showWMTT(\'id9\')" onmouseout="hideWMTT()">
                            ' . $debug . '
                        </select>
                    </div>
                </td>
            </tr>
        </table>


        <div style="clear: both; padding-top: 20px;">
            <input type="hidden" name="captcha_hash" value="' . $hash . '">
            <input class="btn btn-success" type="submit" name="submit" value="' . $languageService->get('update') . '">
        </div>

    </form>
</div>



</div></div>';
}
