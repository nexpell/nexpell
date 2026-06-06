<?php

$language_array = array(
    'title' => 'Passwort vergessen',
    'forgotten_your_password' => 'Passwort vergessen?',
    'info1' => 'Kein Problem!',
    'info2' => 'Gib einfach deine E-Mail-Adresse ein, mit der du dich registriert hast.',
    'info3' => 'Kein Problem. Du kannst Dein Passwort ganz einfach zurücksetzen und dir ein neues vergeben. <br>Gib dazu Deine bestätigte E-Mail-Adresse in das obenstehenden Formular ein und du bekommst daraufhin eine Bestätigungs-Mail zugeschickt. <br>In dieser E-Mail bekommst du ein neu generiertes Passwort, mit dem du dich anmelden kannst. In deinem Profil kannst du dann ein eigenes neues Passwort bestimmen.',
    'your_email' => 'Deine E-Mail-Adresse',
    'get_password' => 'Neues Passwort anfordern',
    'return_to' => 'Zurück zum',
    'login' => 'Login',
    'email-address' => 'E-Mail-Adresse',
    'reg' => 'Registrieren',
    'need_account' => 'Noch keinen Account?',
    'lastpassword_txt' => '<b>Du hast dein Passwort vergessen?</b>
Kein Problem. Du kannst Dein Passwort ganz einfach zurücksetzen und dir ein neues vergeben. Gib dazu Deine bestätigte E-Mail-Adresse in das obenstehenden Formular ein und du bekommst daraufhin eine Bestätigungs-Mail zugeschickt. In dieser E-Mail bekommst du ein neu generiertes Passwort, mit dem du dich anmelden kannst. In deinem Profil kannst du dann ein eigenes neues Passwort bestimmen. ',
    'register_link' => 'Jetzt registrieren',
    'welcome_back' => 'Willkommen zurück!',
    'reg_text' => 'Du hast noch keinen Account? Registriere dich jetzt kostenlos.',
    'login_text' => 'Bitte gib deine Zugangsdaten ein, um dich einzuloggen.',
    'csrf_failed' => 'CSRF-Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.',

    // E-Mail-Inhalte
    'email_subject' => 'Neues Passwort für %pagetitle%',
    'email_text' => '
<html>
  <body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: auto; padding: 20px; background-color: #f9f9f9;">
    <h2 style="color: #fe821d;">Dein Konto für %pagetitle%</h2>
    <p><strong>ACHTUNG:</strong> Dein Passwort wurde erfolgreich zurückgesetzt!</p>
    <p>
      Deine E-Mail Adresse: <strong>%email%</strong><br>
      Dein neues Passwort: <strong>%new_password%</strong>
    </p>
    <p>Bitte ändere dein Passwort nach dem Login in deinem Benutzerprofil.</p>
    <p>Viel Spaß auf unserer Webseite!</p>
    <p>Viele Grüße,<br>Dein %pagetitle%-Team</p>
    <hr style="border:none; border-top:1px solid #ddd; margin: 20px 0;">
    <p style="font-size: 0.9em; color: #777;">
      <a href="%homepage_url%" style="color: #fe821d; text-decoration: none;">%homepage_url%</a>
    </p>
  </body>
</html>
',

    // Erfolg / Fehler
    'successful' => '✅ Neues Passwort erfolgreich gesendet.',
    'email_failed' => '❌ E-Mail-Versand fehlgeschlagen.',
    'no_user_found' => '❌ Kein Benutzer mit dieser E-Mail-Adresse gefunden.',
    'no_mail_given' => '❌ Bitte gib eine E-Mail-Adresse ein.',
    'error_no_pepper' => '❌ Kein Pepper in der Datenbank vorhanden.',
    'error_decrypt_pepper' => '❌ Fehler beim Entschlüsseln des Peppers.'
);

