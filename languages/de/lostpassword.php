<?php

$language_array = array(
    'title' => 'Passwort vergessen',
    'forgotten_your_password' => 'Passwort vergessen?',
    'info1' => 'Kein Problem!',
    'info2' => 'Gib einfach deine E-Mail-Adresse ein, mit der du dich registriert hast.',
    'info3' => 'Kein Problem. Du kannst Dein Passwort ganz einfach zurücksetzen und dir ein neues vergeben. <br>Gib dazu Deine bestätigte E-Mail-Adresse in das obenstehenden Formular ein und du bekommst daraufhin eine Bestätigungs-Mail zugeschickt. <br>In dieser E-Mail bekommst du ein neu generiertes Passwort, mit dem du dich anmelden kannst. In deinem Profil kannst du dann ein eigenes neues Passwort bestimmen.',
    'your_email' => 'Deine E-Mail-Adresse',
    'get_password' => 'Link zum Zurücksetzen anfordern',
    'return_to' => 'Zurück zum',
    'login' => 'Login',
    'email-address' => 'E-Mail-Adresse',
    'reg' => 'Registrieren',
    'need_account' => 'Noch keinen Account?',
    'lastpassword_txt' => '
<b>Du hast dein Passwort vergessen?</b><br><br>
Kein Problem. Gib einfach deine bestätigte E-Mail-Adresse in das obenstehende Formular ein.
Wir senden dir anschließend eine E-Mail mit einem sicheren Link zum Zurücksetzen deines Passworts.<br><br>
Über diesen Link kannst du selbst ein neues Passwort festlegen.
Der Link ist nur für kurze Zeit gültig und kann nur einmal verwendet werden – so bleibt dein Konto bestmöglich geschützt.
',

    'register_link' => 'Jetzt registrieren',
    'welcome_back' => 'Willkommen zurück!',
    'reg_text' => 'Du hast noch keinen Account? Registriere dich jetzt kostenlos.',
    'login_text' => 'Bitte gib deine Zugangsdaten ein, um dich einzuloggen.',
    'csrf_failed' => 'CSRF-Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.',

    // E-Mail-Inhalte
    'email_subject' => 'Neues Passwort für %pagetitle%',
'email_subject' => 'Passwort zurücksetzen – %pagetitle%',

'email_text' => '
<!DOCTYPE html>
<html lang="de">
  <body style="margin:0;padding:0;background-color:#f4f6f8;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 0;">
      <tr>
        <td align="center">

          <table width="100%" cellpadding="0" cellspacing="0"
                 style="max-width:620px;background:#ffffff;border-radius:12px;
                        font-family:Arial,Helvetica,sans-serif;overflow:hidden;">

            <!-- Header -->
            <tr>
              <td style="background:linear-gradient(135deg,#fe821d,#ff9b3d);
                         padding:28px 32px;">
                <h1 style="margin:0;font-size:22px;color:#fff;">
                  %pagetitle%
                </h1>
                <p style="margin:6px 0 0;color:rgba(255,255,255,0.9);font-size:14px;">
                  Passwort zurücksetzen
                </p>
              </td>
            </tr>

            <!-- Content -->
            <tr>
              <td style="padding:32px;font-size:15px;line-height:1.6;color:#333;">

                <p>
                  Du hast eine Anfrage zum Zurücksetzen deines Passworts gestellt.
                </p>

                <p>
                  Klicke auf den folgenden Button, um ein neues Passwort zu vergeben:
                </p>

                <p style="text-align:center;margin:30px 0;">
                  <a href="%reset_link%"
                     style="background:#fe821d;color:#fff;
                            padding:14px 26px;
                            border-radius:6px;
                            text-decoration:none;
                            font-weight:600;">
                    Passwort zurücksetzen
                  </a>
                </p>

                <p style="font-size:14px;color:#666;">
                  Dieser Link ist <strong>60 Minuten gültig</strong>.
                  Falls du diese Anfrage nicht gestellt hast,
                  kannst du diese E-Mail ignorieren.
                </p>

                <p style="margin-top:28px;">
                  Viele Grüße<br>
                  <strong>Dein %pagetitle%-Team</strong>
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

        </td>
      </tr>
    </table>
  </body>
</html>
',



    // Erfolg / Fehler
    'successful' => '✅ Neues Passwort erfolgreich gesendet.',
    'email_failed' => '❌ E-Mail-Versand fehlgeschlagen.',
    'no_user_found' => '❌ Kein Benutzer mit dieser E-Mail-Adresse gefunden.',
    'no_mail_given' => '❌ Bitte gib eine E-Mail-Adresse ein.',
    'error_no_pepper' => '❌ Kein Pepper in der Datenbank vorhanden.',
    'error_decrypt_pepper' => '❌ Fehler beim Entschlüsseln des Peppers.',


    'reset_mail_sent' =>
'Wenn ein Konto mit dieser E-Mail existiert, wurde eine Nachricht mit weiteren Anweisungen versendet.',

'password_changed_mail_subject' =>
'Dein Passwort wurde geändert – %pagetitle%',

'password_changed_mail_text' =>
'Dein Passwort wurde soeben erfolgreich geändert.
Wenn du das nicht warst, kontaktiere bitte sofort den Support.'
);

