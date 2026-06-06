<?php

$language_array = array(

    // Form Headings
    'reg_title' => 'Registrierung',
    'reg_info_text' => 'Bitte fülle das folgende Formular aus, um dich zu registrieren.',

    // Formularlabels
    'username' => 'Benutzername',
    'password' => 'Passwort',
    'password_repeat' => 'Passwort wiederholen',
    'email_address_label' => 'E-Mail-Adresse',

    // Placeholder / Eingabehinweise
    'enter_your_email' => 'Deine E-Mail-Adresse eingeben',
    'enter_your_name' => 'Wähle einen Benutzernamen',
    'enter_password' => 'Passwort eingeben',
    'enter_password_repeat' => 'Passwort erneut eingeben',
    'password_hint' =>
        'Verwende mindestens 8 Zeichen mit Groß- und Kleinbuchstaben, Zahlen oder Sonderzeichen. <br>
Vermeide einfache oder sich wiederholende Passwörter wie „aaaaaa“ oder „123456“.',

    // Terms / Hinweise
    'terms_of_use_text' => 'Ich akzeptiere die Nutzungsbedingungen',
    'terms_of_use' => 'Nutzungsbedingungen',

    // Buttons
    'register' => 'Registrieren',
    'login_text' => 'Bereits registriert?',
    'login_link' => 'Jetzt einloggen',

    // E-Mail
    'mail' => 'E-Mail',
    'mail_subject' => 'Aktiviere deinen Account auf %hp_title%',
    'mail_text' => '
<!DOCTYPE html>
<html lang="de">
  <body style="margin:0;padding:0;background-color:#f4f6f8;">
    <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f6f8" style="padding:30px 0;">
      <tr>
        <td align="center">

          <!-- Container -->
          <table width="100%" cellpadding="0" cellspacing="0"
                 bgcolor="#ffffff"
                 style="max-width:620px;background-color:#ffffff;border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">

            <!-- Header -->
            <tr>
              <td
                bgcolor="#fe821d"
                style="
                  background-color:#fe821d;
                  background-image:linear-gradient(135deg,#fe821d,#ff9b3d);
                  padding:28px 32px;
                "
              >
                <h1 style="margin:0;font-size:22px;color:#ffffff;font-weight:600;">
                  %hp_title%
                </h1>
                <p style="margin:6px 0 0;color:#ffffff;font-size:14px;">
                  Registrierung bestätigen
                </p>
              </td>
            </tr>

            <!-- Content -->
            <tr>
              <td style="padding:32px;color:#333333;font-size:15px;line-height:1.6;">

                <h2 style="margin-top:0;font-size:20px;color:#222;">
                  Willkommen, %username%!
                </h2>

                <p>
                  vielen Dank für deine Registrierung bei
                  <strong>%hp_title%</strong>.
                </p>

                <p>
                  Bitte bestätige deine E-Mail-Adresse, um deinen Account zu aktivieren:
                </p>

                <!-- Button -->
                <table cellpadding="0" cellspacing="0" style="margin:24px 0;">
                  <tr>
                    <td
                      align="center"
                      bgcolor="#fe821d"
                      style="
                        background-color:#fe821d;
                        border-radius:6px;
                      "
                    >
                      <a href="%activation_link%"
                         style="
                           display:inline-block;
                           padding:14px 26px;
                           font-size:15px;
                           font-weight:600;
                           color:#000000;
                           text-decoration:none;
                         ">
                        Account aktivieren
                      </a>
                    </td>
                  </tr>
                </table>

                <p style="font-size:14px;color:#555;">
                  Falls du dich nicht selbst registriert hast, kannst du diese E-Mail
                  ignorieren.
                </p>

                <p style="margin-top:28px;">
                  Viele Grüße<br>
                  <strong>Dein %hp_title%-Team</strong>
                </p>

              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td bgcolor="#f9fafb"
                  style="padding:18px 32px;font-size:13px;color:#777;text-align:center;">
                <a href="%hp_url%" style="color:#fe821d;text-decoration:none;">
                  %hp_url%
                </a>
                <div style="margin-top:6px;">
                  © %hp_title% · Alle Rechte vorbehalten
                </div>
              </td>
            </tr>

          </table>

        </td>
      </tr>
    </table>
  </body>
</html>
',

    'mail_from_module' => 'Registrierung',
    'mail_failed' => 'Die Aktivierungs-E-Mail konnte nicht versendet werden. Bitte kontaktiere den Administrator.',

    // Fehlertexte
    'invalid_email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
    'invalid_username' => 'Benutzername ungültig. Erlaubt sind nur Buchstaben, Zahlen, Unterstriche und Bindestriche (3-30 Zeichen).',
    'invalid_password' => 'Das Passwort erfüllt nicht die Sicherheitsanforderungen.',
    'password_mismatch' => 'Die Passwörter stimmen nicht überein.',
    'terms_required' => 'Du musst die Nutzungsbedingungen akzeptieren.',
    'email_exists' => 'Diese E-Mail-Adresse ist bereits registriert.',
    'register_successful' => 'Die Registrierung war erfolgreich! Bitte überprüfe deine E-Mails zur Aktivierung deines Kontos.',

    'security_code'       => 'Sicherheitscode',


);