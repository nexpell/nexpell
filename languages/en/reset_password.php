<?php

$language_array = array(

    'reset_password_title' => 'Set new password',

    /* ===============================
       RESET PASSWORD – FORM
    ================================ */

    'label_new_password' => 'New password',
    'label_repeat_password' => 'Repeat password',

    'password_hint' =>
        'Use at least 8 characters including uppercase and lowercase letters, numbers, or special characters. <br>
Avoid simple or repetitive passwords such as “aaaaaa” or “123456”.',

    'submit_password' => 'Set password',

    /* ===============================
       RESET PASSWORD – ERRORS
    ================================ */

    'error_required' => 'Please fill in all fields.',
    'password_mismatch' => 'The passwords do not match.',
    'password_too_short' => 'The password must be at least 8 characters long.',
    'security_error' => 'Security error. Please try again.',
    'reset_failed' => 'The password could not be changed. Please try again.',

    /* ===============================
       RESET PASSWORD – STATUS
    ================================ */

    'invalid_link' => 'Invalid password reset link.',
    'invalid_or_expired' => 'The reset link is invalid or has expired.',
    'reset_success' => 'Your password has been changed successfully.',

    /* ===============================
       CSRF
    ================================ */

    'csrf_failed' => 'Security check failed. Please reload the page.',

    'password_reset_success' => 'Password successfully changed. Please log in.',

    /* ===============================
       PASSWORD CHANGED – EMAIL
    ================================ */

    'password_changed_subject' => 'Your password has been changed – %pagetitle%',

    'password_changed_text' => '
<!DOCTYPE html>
<html lang="en">
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
Password changed
</p>
</td>
</tr>

<tr>
<td style="padding:32px;font-size:15px;line-height:1.6;color:#333;">
<p>
Hello,
</p>

<p>
The password for your user account has just been successfully changed.
</p>
<p style="font-size:14px;color:#666;">
Detected IP address at the time of change: <strong>%ip%</strong>
</p>

<p>
<strong>If you did not make this change yourself,</strong>
please reset your password immediately or contact support.
</p>

<p style="margin-top:24px;">
<a href="%hp_url%" style="color:#fe821d;text-decoration:none;">
%hp_url%
</a>
</p>

<p style="margin-top:28px;">
Best regards<br>
<strong>Your %hp_title% Team</strong>
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
