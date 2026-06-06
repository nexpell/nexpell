<?php

$language_array = array(
    'title' => 'Forgot Password',
    'forgotten_your_password' => 'Forgot your password?',
    'info1' => 'No problem!',
    'info2' => 'Just enter the email address you used to register.',
    'info3' => 'No problem. You can easily reset your password and set a new one. <br>
                Simply enter your verified email address in the form above and you will receive a confirmation email. <br>
                This email will contain a newly generated password that you can use to log in. 
                You can then set your own new password in your profile.',
    'your_email' => 'Your email address',
    'get_password' => 'Request password reset link',
    'return_to' => 'Back to',
    'login' => 'Login',
    'email-address' => 'Email address',
    'reg' => 'Register',
    'need_account' => 'Don’t have an account yet?',
    'lastpassword_txt' => '
<b>Forgot your password?</b><br><br>
No problem. Simply enter your verified email address in the form above.
We will then send you an email with a secure link to reset your password.<br><br>
Using this link, you can set a new password yourself.
The link is only valid for a short time and can only be used once – keeping your account as secure as possible.
',

    'register_link' => 'Register now',
    'welcome_back' => 'Welcome back!',
    'reg_text' => 'Don’t have an account yet? Register now for free.',
    'login_text' => 'Please enter your login credentials to sign in.',
    'csrf_failed' => 'CSRF security check failed. Please try again.',

    // Email content
    'email_subject' => 'Reset your password – %pagetitle%',

    'email_text' => '
<!DOCTYPE html>
<html lang="en">
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
                  Password reset
                </p>
              </td>
            </tr>

            <!-- Content -->
            <tr>
              <td style="padding:32px;font-size:15px;line-height:1.6;color:#333;">

                <p>
                  You requested to reset your password.
                </p>

                <p>
                  Click the button below to set a new password:
                </p>

                <p style="text-align:center;margin:30px 0;">
                  <a href="%reset_link%"
                     style="background:#fe821d;color:#fff;
                            padding:14px 26px;
                            border-radius:6px;
                            text-decoration:none;
                            font-weight:600;">
                    Reset password
                  </a>
                </p>

                <p style="font-size:14px;color:#666;">
                  This link is valid for <strong>60 minutes</strong>.
                  If you did not request this password reset,
                  you can safely ignore this email.
                </p>

                <p style="margin-top:28px;">
                  Best regards<br>
                  <strong>Your %pagetitle% Team</strong>
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

    // Success / error messages
    'successful' => '✅ New password successfully sent.',
    'email_failed' => '❌ Email delivery failed.',
    'no_user_found' => '❌ No user found with this email address.',
    'no_mail_given' => '❌ Please enter an email address.',
    'error_no_pepper' => '❌ No pepper found in the database.',
    'error_decrypt_pepper' => '❌ Error decrypting the pepper.',

    'reset_mail_sent' =>
'If an account with this email exists, a message with further instructions has been sent.',

    'password_changed_mail_subject' =>
'Your password has been changed – %pagetitle%',

    'password_changed_mail_text' =>
'Your password has just been successfully changed.
If this was not you, please contact support immediately.'
);
