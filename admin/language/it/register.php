<?php

$language_array = array(

    // Intestazioni del modulo
    'reg_title' => 'Registrazione',
    'reg_info_text' => 'Per favore compila il modulo sottostante per registrarti.',

    // Etichette del modulo
    'username' => 'Nome utente',
    'password' => 'Password',
    'password_repeat' => 'Ripeti password',
    'email_address_label' => 'Indirizzo e-mail',

    // Segnaposti / suggerimenti
    'enter_your_email' => 'Inserisci il tuo indirizzo e-mail',
    'enter_your_name' => 'Scegli un nome utente',
    'enter_password' => 'Inserisci la password',
    'enter_password_repeat' => 'Ripeti la password',
    'pass_text' => 'La password deve essere lunga almeno 8 caratteri e contenere un numero e una lettera maiuscola.',

    // Termini / note
    'terms_of_use_text' => "Accetto i termini di utilizzo",
    'terms_of_use' => 'termini di utilizzo',

    // Pulsanti
    'register' => 'Registrati',
    'login_text' => 'Già registrato?',
    'login_link' => 'Accedi ora',

    // E-mail
    'mail' => 'E-mail',
    'mail_subject' => 'Attiva il tuo account su %hp_title%',
    'mail_text' => '
<html>
  <body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: auto; padding: 20px; background-color: #f9f9f9;">
    <h2 style="color: #fe821d;">Ciao %username%,</h2>
    <p>Grazie per esserti registrato su <strong>%hp_title%</strong>.</p>
    <p>Per favore clicca sul link qui sotto per attivare il tuo account:</p>
    <p>
      <a href="%activation_link%" style="display: inline-block; padding: 10px 20px; background-color: #fe821d; color: #000; text-decoration: none; border-radius: 4px;">
        Attiva account
      </a>
    </p>
    <p>Se non ti sei registrato, puoi ignorare questo messaggio.</p>
    <p>Saluti,<br>Il team di %hp_title%</p>
    <hr style="border:none; border-top:1px solid #ddd; margin: 20px 0;">
    <p style="font-size: 0.9em; color: #777;">
      <a href="%hp_url%" style="color: #fe821d; text-decoration: none;">%hp_url%</a>
    </p>
  </body>
</html>
',
    'mail_from_module' => 'Registrazione',
    'mail_failed' => "L'e-mail di attivazione non può essere inviata. Contatta l'amministratore.",

    // Messaggi di errore
    'invalid_email' => 'Per favore inserisci un indirizzo e-mail valido.',
    'invalid_username' => 'Nome utente non valido. Sono consentiti solo lettere, numeri, underscore e trattini (3-30 caratteri).',
    'invalid_password' => 'La password non soddisfa i requisiti di sicurezza.',
    'password_mismatch' => 'Le password non corrispondono.',
    'terms_required' => 'Devi accettare i termini di utilizzo.',
    'email_exists' => "Questo indirizzo e-mail è già registrato.",
    'register_successful' => 'Registrazione avvenuta con successo! Controlla la tua e-mail per attivare il tuo account.',

    'security_code'       => 'Codice di sicurezza',
);
