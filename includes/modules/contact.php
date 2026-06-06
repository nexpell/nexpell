<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('contact');

$get = mysqli_fetch_assoc(safe_query("SELECT * FROM settings"));
    $webkey = $get['webkey'];
    $seckey = $get['seckey'];


$loggedin = (isset($_SESSION['userID']) && $_SESSION['userID'] > 0);

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->module['title'],
    'subtitle' => 'Contact Us',
];
echo $tpl->loadTemplate("contact", "head", $data_array, 'theme');

// Default-Initialisierung, falls Formular neu geladen
$name = '';
$from = '';
$subject = '';
$text = '';
$showerror = '';

$action = $_POST["action"] ?? '';

if ($action == "send") {
    $getemail = $_POST['getemail'];
    $subject = $_POST['subject'];
    $text = str_replace('\r\n', "\n", $_POST['text']);
    $name = $_POST['name'];
    $from = $_POST['from'];
    $run = 0;

    $fehler = array();
    if (!mb_strlen(trim($name))) $fehler[] = $languageService->module['enter_name'];
    if (!validate_email($from)) $fehler[] = $languageService->module['enter_mail'];
    if (!mb_strlen(trim($subject))) $fehler[] = $languageService->module['enter_subject'];
    if (!mb_strlen(trim($text))) $fehler[] = $languageService->module['enter_message'];

    $ergebnis = safe_query("SELECT * FROM contact WHERE email='" . $getemail . "'");
    if (mysqli_num_rows($ergebnis) == 0) {
        $fehler[] = $languageService->module['unknown_receiver'];
    }

    if ($loggedin) {
        $run = 1;
    } else {
        $runregister = "false";
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $recaptcha_response = $_POST['g-recaptcha-response'];
            if (!empty($recaptcha_response)) {
                include("system/curl_recaptcha.php");
                $google_url = "https://www.google.com/recaptcha/api/siteverify";
                $secret = $seckey;
                $ip = $_SERVER['REMOTE_ADDR'];
                $url = $google_url . "?secret=" . $secret . "&response=" . $recaptcha_response . "&remoteip=" . $ip;
                $res = getCurlData($url);
                $res = json_decode($res, true);
                if ($res['success']) {
                    $runregister = "true";
                    $run = 1;
                } else {
                    $fehler[] = "reCAPTCHA Error";
                }
            } else {
                $fehler[] = "reCAPTCHA Error";
            }
        }
    }

    if (!count($fehler) && $run) {
        $settings_result = safe_query("SELECT * FROM `settings`");
        $settings = mysqli_fetch_assoc($settings_result);
        $hp_title = $settings['hptitle'] ?? 'nexpell';
        $hp_url = $settings['hpurl'] ?? 'https://' . $_SERVER['HTTP_HOST'];

        $message = '
            <html>
              <body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: auto; padding: 20px; background-color: #f9f9f9;">
                <h2 style="color: #fe821d;">Nachricht über das Kontaktformular</h2>
                <p>Diese E-Mail wurde über das Kontaktformular auf deiner <strong>' . htmlspecialchars($hp_title) . '</strong>-Website gesendet.</p>
                <p><strong>IP-Adresse:</strong> ' . htmlspecialchars($GLOBALS['ip']) . '</p>
                <p><strong>Von:</strong> ' . htmlspecialchars($name) . '</p>
                <p><strong>Nachricht:</strong></p>
                <p style="white-space: pre-wrap;">' . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>
                <hr style="border:none; border-top:1px solid #ddd; margin: 20px 0;">
                <p style="font-size: 0.9em; color: #777;">
                  <a href="' . htmlspecialchars($hp_url) . '" style="color: #fe821d; text-decoration: none;">' . htmlspecialchars($hp_url) . '</a>
                </p>
              </body>
            </html>
            ';


        $sendmail = \nexpell\Email::sendEmail($from, 'Contact', $getemail, stripslashes($subject), $message);

        if ($sendmail['result'] == 'fail') {
            $fehler[] = $sendmail['error'];
            if (isset($sendmail['debug'])) $fehler[] = $sendmail['debug'];
            $showerror = generateErrorBoxFromArray($languageService->module['errors_there'], $fehler);
        } else {
            if (isset($sendmail['debug'])) {
                $fehler[] = $sendmail['debug'];
                redirect('index.php?site=contact', generateBoxFromArray($languageService->module['send_successfull'], 'alert-success', $fehler), 3);
            } else {
                echo '<div class="alert alert-success" role="alert">' . $languageService->module['send_successfull']. '</div>';
                redirect('index.php?site=contact', '', 3);
            }
            unset($_POST['name'], $_POST['from'], $_POST['text'], $_POST['subject']);
        }
    } else {
        $showerror = generateErrorBoxFromArray($languageService->module['errors_there'], $fehler);
    }
}

$getemail = '';
$ergebnis = safe_query("SELECT * FROM contact ORDER BY `sort`");
if (mysqli_num_rows($ergebnis) < 1) {
    $data_array = array();
    $data_array['$showerror'] = generateErrorBoxFromArray($languageService->module['errors_there'], [$languageService->module['no_contact_setup']]);
    echo $tpl->loadTemplate("contact", "failure", $data_array);
    return false;
} else {
    while ($ds = mysqli_fetch_array($ergebnis)) {
        $getemail .= '<option value="' . $ds['email'] . '"' . ($getemail === $ds['email'] ? ' selected="selected"' : '') . '>' . $ds['name'] . '</option>';
    }
}

if ($loggedin) {
    if (!isset($showerror)) $showerror = '';
    $name = htmlspecialchars(stripslashes(getusername($_SESSION['userID'])));
    $from = htmlspecialchars(getemail($_SESSION['userID']));
    $subject = isset($_POST['subject']) ? getforminput($_POST['subject']) : '';
    $text = isset($_POST['text']) ? getforminput($_POST['text']) : '';
}

// Template vorbereiten
$data_array = [
    'description' => $languageService->module['description'],
    'showerror' => $showerror ?? '',
    'getemail' => $getemail,
    'name' => htmlspecialchars($name ?? ''),
    'from' => htmlspecialchars($from ?? ''),
    'subject' => htmlspecialchars($subject ?? ''),
    'text' => htmlspecialchars($text ?? ''),
    'security_code' => $languageService->module['security_code'],
    'user' => $languageService->module['user'],
    'mail' => $languageService->module['mail'],
    'e_mail_info' => $languageService->module['e_mail_info'],
    'subject' => $languageService->module['subject'],
    'message' => $languageService->module['message'],
    'lang_GDPRinfo' => $languageService->get('GDPRinfo'),
    'send' => $languageService->get('send'),
    'info_captcha' => !$loggedin
        ? '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($webkey) . '"></div>'
        : '',
    'loggedin' => $loggedin,
    'userID' => $_SESSION['userID'] ?? 0
];

echo $tpl->loadTemplate("contact", "form", $data_array, 'theme');
