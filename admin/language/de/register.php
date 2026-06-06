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
    'pass_text' => 'Das Passwort muss mindestens 8 Zeichen lang sein, eine Zahl und einen Großbuchstaben enthalten.',

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
<html>
  <body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: auto; padding: 20px; background-color: #f9f9f9;">
    <h2 style="color: #fe821d;">Hallo %username%,</h2>
    <p>vielen Dank für deine Registrierung bei <strong>%hp_title%</strong>.</p>
    <p>Bitte klicke auf den folgenden Link, um deinen Account zu aktivieren:</p>
    <p>
      <a href="%activation_link%" style="display: inline-block; padding: 10px 20px; background-color: #fe821d; color: #000; text-decoration: none; border-radius: 4px;">
        Account aktivieren
      </a>
    </p>
    <p>Falls du dich nicht registriert hast, kannst du diese Nachricht ignorieren.</p>
    <p>Viele Grüße,<br>Dein %hp_title%-Team</p>
    <hr style="border:none; border-top:1px solid #ddd; margin: 20px 0;">
    <p style="font-size: 0.9em; color: #777;">
      <a href="%hp_url%" style="color: #fe821d; text-decoration: none;">%hp_url%</a>
    </p>
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