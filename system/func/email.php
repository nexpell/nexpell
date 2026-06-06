<?php

namespace nexpell;

// PHPMailer einbinden
if (file_exists('components/PHPMailer/PHPMailerAutoload.php')) {
    require 'components/PHPMailer/PHPMailerAutoload.php';
}

class Email
{
    /**
     * Versendet eine E-Mail über PHPMailer mit SMTP- oder Standard-Mail-Funktion.
     *
     * @param string  $from     Absender-E-Mail
     * @param string  $module   Modulname für Absenderbezeichnung
     * @param string  $to       Empfänger-E-Mail
     * @param string  $subject  Betreff der E-Mail
     * @param string  $message  Nachricht (HTML/Text)
     * @param boolean $pop      POP3 vor SMTP verwenden (optional)
     *
     * @return array Ergebnisarray mit "result" (done/fail), ggf. "error" und "debug"
     */
    public static function sendEmail($from, $module, $to, $subject, $message, $pop = true)
    {
        $GLOBALS['mail_debug'] = '';

        // E-Mail-Einstellungen aus Datenbank lesen
        $result = safe_query("SELECT * FROM `email`");
        if (mysqli_num_rows($result)) {
            $ds       = mysqli_fetch_assoc($result);
            $host     = $ds['host'];
            $user     = $ds['user'];
            $password = $ds['password'];
            $port     = $ds['port'];
            $debug    = $ds['debug'];
            $auth     = $ds['auth'];
            $html     = $ds['html'];
            $smtp     = $ds['smtp'];
            $secure   = $ds['secure'];
        } else {
            $smtp = 0;
            $auth = 0;
            $debug = 0;
        }

        if ($smtp == 0) {
            $debug = 0;
        }

        // POP3 vor SMTP falls aktiviert
        if ($smtp == 2) {
            $pop = \POP3::popBeforeSmtp($host, 110, 30, $user, $password, $debug);
        }

        $mail = new \PHPMailer();

        $mail->SMTPDebug = $debug;
        $mail->Debugoutput = function ($str, $level) {
            $GLOBALS['mail_debug'] .= $str . '<br>';
        };

        if (isset($pop)) {
            if ($smtp == 1) {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = $port;

                if ($auth == 1) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $user;
                    $mail->Password = $password;
                } else {
                    $mail->SMTPAuth = false;
                }

                if (extension_loaded('openssl')) {
                    switch ($secure) {
                        case 1:
                            $mail->SMTPSecure = 'tls';
                            break;
                        case 2:
                            $mail->SMTPSecure = 'ssl';
                            break;
                        default:
                            $mail->SMTPSecure = '';
                    }
                } else {
                    $mail->SMTPSecure = '';
                }
            } else {
                $mail->isMail();
            }

            $fromTitle = $GLOBALS['hp_title'] . ' - (' . $module . ')';

            $mail->setFrom($from, $fromTitle);
            $mail->addAddress($to);
            $mail->addReplyTo($from);
            $mail->Subject = $subject;
            $mail->CharSet = 'utf-8';
            $mail->WordWrap = 78;

            if ($html == 1) {
                $mail->isHTML(true);
                $mail->msgHTML($message);
            } else {
                $mail->isHTML(false);
                $plain = $mail->html2text($message);
                $mail->Body = $plain;
                $mail->AltBody = $plain;
            }

            if (!$mail->send()) {
                return [
                    "result" => "fail",
                    "error" => $mail->ErrorInfo,
                    "debug" => $debug ? $GLOBALS['mail_debug'] : null
                ];
            } else {
                return [
                    "result" => "done",
                    "debug" => $debug ? $GLOBALS['mail_debug'] : null
                ];
            }
        } else {
            return [
                "result" => "fail",
                "error" => $mail->ErrorInfo,
                "debug" => $debug ? $GLOBALS['mail_debug'] : null
            ];
        }
    }
}
