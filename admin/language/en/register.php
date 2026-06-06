<?php

$language_array = array(

    // Form Headings
    'reg_title' => 'Registration',
    'reg_info_text' => 'Please fill out the form below to register.',

    // Form labels
    'username' => 'Username',
    'password' => 'Password',
    'password_repeat' => 'Repeat password',
    'email_address_label' => 'E-mail address',

    // Placeholders / input hints
    'enter_your_email' => 'Enter your e-mail address',
    'enter_your_name' => 'Choose a username',
    'enter_password' => 'Enter password',
    'enter_password_repeat' => 'Repeat password',
    'pass_text' => 'The password must be at least 8 characters long and contain a number and an uppercase letter.',

    // Terms / notes
    'terms_of_use_text' => 'I accept the terms of use',
    'terms_of_use' => 'terms of use',

    // Buttons
    'register' => 'Register',
    'login_text' => 'Already registered?',
    'login_link' => 'Log in now',

    // E-mail
    'mail' => 'E-mail',
    'mail_subject' => 'Activate your account on %hp_title%',
    'mail_text' => '
<html>
  <body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: auto; padding: 20px; background-color: #f9f9f9;">
    <h2 style="color: #fe821d;">Hello %username%,</h2>
    <p>Thank you for registering at <strong>%hp_title%</strong>.</p>
    <p>Please click the link below to activate your account:</p>
    <p>
      <a href="%activation_link%" style="display: inline-block; padding: 10px 20px; background-color: #fe821d; color: #000; text-decoration: none; border-radius: 4px;">
        Activate account
      </a>
    </p>
    <p>If you did not register, you can ignore this message.</p>
    <p>Best regards,<br>Your %hp_title% Team</p>
    <hr style="border:none; border-top:1px solid #ddd; margin: 20px 0;">
    <p style="font-size: 0.9em; color: #777;">
      <a href="%hp_url%" style="color: #fe821d; text-decoration: none;">%hp_url%</a>
    </p>
  </body>
</html>
',
    'mail_from_module' => 'Registration',
    'mail_failed' => 'Activation e-mail could not be sent. Please contact the administrator.',

    // Error messages
    'invalid_email' => 'Please enter a valid e-mail address.',
    'invalid_username' => 'Invalid username. Only letters, numbers, underscores and hyphens are allowed (3-30 characters).',
    'invalid_password' => 'The password does not meet the security requirements.',
    'password_mismatch' => 'The passwords do not match.',
    'terms_required' => 'You must accept the terms of use.',
    'email_exists' => 'This e-mail address is already registered.',
    'register_successful' => 'Registration successful! Please check your e-mails to activate your account.',

    'security_code'       => 'Security code',
);
