<?php

$language_array = array(




'reset_password_title' => 'Neues Passwort festlegen',


    /* ===============================
       RESET PASSWORD – FORM
    ================================ */

    'label_new_password' => 'Neues Passwort',
    'label_repeat_password' => 'Passwort wiederholen',

    'password_hint' =>
        'Verwende mindestens 8 Zeichen mit Groß- und Kleinbuchstaben, Zahlen oder Sonderzeichen. <br>
Vermeide einfache oder sich wiederholende Passwörter wie „aaaaaa“ oder „123456“.',

    'submit_password' => 'Passwort setzen',

    /* ===============================
       RESET PASSWORD – ERRORS
    ================================ */

    'error_required' => 'Bitte fülle alle Felder aus.',
    'password_mismatch' => 'Die Passwörter stimmen nicht überein.',
    'password_too_short' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
    'security_error' => 'Sicherheitsfehler. Bitte versuche es erneut.',
    'reset_failed' => 'Das Passwort konnte nicht geändert werden. Bitte versuche es erneut.',

    /* ===============================
       RESET PASSWORD – STATUS
    ================================ */

    'invalid_link' => 'Ungültiger Passwort-Reset-Link.',
    'invalid_or_expired' => 'Der Reset-Link ist ungültig oder abgelaufen.',
    'reset_success' => 'Dein Passwort wurde erfolgreich geändert.',

    /* ===============================
       CSRF
    ================================ */

    'csrf_failed' => 'Sicherheitsprüfung fehlgeschlagen. Bitte lade die Seite neu.',


    'password_reset_success' => 'Passwort erfolgreich geändert. Bitte melde dich an.',


'password_changed_subject' => 'Dein Passwort wurde geändert – %pagetitle%',

'password_changed_text' => '
<!DOCTYPE html>
<html lang="de">
<body style="margin:0;padding:0;background:#f4f6f8;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 0;">
<tr><td align="center">

<table width="100%" cellpadding="0" cellspacing="0"
style="max-width:620px;background:#ffffff;border-radius:12px;
font-family:Arial,Helvetica,sans-serif;overflow:hidden;">

<tr>
<td style="background:linear-gradient(135deg,#fe821d,#ff9b3d);
padding:28px 32px;">
<h1 style="margin:0;font-size:22px;color:#fff;">
%hp_title%
</h1>
<p style="margin:6px 0 0;color:#fff;font-size:14px;">
Passwort geändert
</p>
</td>
</tr>

<tr>
<td style="padding:32px;font-size:15px;line-height:1.6;color:#333;">
<p>
Hallo,
</p>

<p>
das Passwort für dein Benutzerkonto wurde soeben erfolgreich geändert.
</p>
<p style="font-size:14px;color:#666;">
Erkannte IP-Adresse zum Zeitpunkt der Änderung: <strong>%ip%</strong>
</p>

<p>
<strong>Wenn du diese Änderung nicht selbst vorgenommen hast,</strong>
setze dein Passwort bitte umgehend erneut zurück oder kontaktiere den Support.
</p>

<p style="margin-top:24px;">
<a href="%hp_url%" style="color:#fe821d;text-decoration:none;">
%hp_url%
</a>
</p>

<p style="margin-top:28px;">
Viele Grüße<br>
<strong>Dein %hp_title%-Team</strong>
</p>
</td>
</tr>

<!-- Footer -->
            <tr>
              <td style="background:#f9fafb;padding:18px 32px;
                         font-size:13px;color:#777;text-align:center;">
                <a href="%homepage_url%"
                   style="color:#fe821d;text-decoration:none;">
                  %homepage_url%
                </a>
                <div style="margin-top:6px;">
                  © %pagetitle%
                </div>
              </td>
            </tr>

</table>

</td></tr>
</table>
</body>
</html>',

);