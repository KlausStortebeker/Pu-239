<?php

declare(strict_types = 1);
global $site_config;

$lang = [
    'ok_success' => 'Signup successful!',
    'ok_invsuccess' => 'Invite Signup successful!',
    'ok_email_confirm' => "A confirmation email has been sent to the address you specified (%s). You need to read and respond to this email before you can use your account. If you don't do this, the new account will be deleted automatically after a few days.",
    'ok_email' => "<p>Your account has been activated! You may now login <a href='{$site_config['paths']['baseurl']}/index.php'><b>here</b></a> and start using your account.</p>\n",
    'ok_email2' => "A confirmation email has been sent to the address you specified (%s). Your invitee needs to read and respond to this email before he/she can use their account. If they don't do this, the new account will be deleted automatically after a few days.",
    'ok_sysop_account' => 'Sysop Account activation',
    'ok_sysop_activated' => 'Sysop Account successfully activated!',
    'ok_account_activated' => "<p>Your account has been activated! You have been automatically logged in. You can now continue to the <a href='{$site_config['paths']['baseurl']}/index.php'><b>main page</b></a> and start using your account.</p>\n",
    'ok_account_login' => "<p>Your account has been activated! However, it appears that you could not be logged in automatically. A possible reason is that you disabled cookies in your browser. You have to enable cookies to use your account. Please do that and then <a href='{$site_config['paths']['baseurl']}/login.php'>log in</a> and try again.</p>\n",
    'ok_confirmed' => 'Already confirmed',
    'ok_user_confirmed' => "<p>This user account has already been confirmed. You can proceed to <a href='{$site_config['paths']['baseurl']}/login.php'>log in</a> with it.</p>\n",
    'ok_signup_confirm' => 'Signup confirmation',
    'ok_success_confirmed' => 'Account successfully confirmed!',
    'ok_account_active_login_link' => 'main page',
    'ok_account_active_login' => 'Your account has been activated! You have been automatically logged in. You can now continue to the %s and start using your account.',
    'ok_read_rules' => "<p>Before you start using %s we urge you to read the <a href='{$site_config['paths']['baseurl']}/rules.php'><b>RULES</b></a> and the <a href='{$site_config['paths']['baseurl']}/faq.php'><b>FAQ</b></a>.</p>\n",
    'ok_account_cookies' => "<p>Your account has been activated! However, it appears that you could not be logged in automatically. A possible reason is that you disabled cookies in your browser. You have to enable cookies to use your account. Please do that and then <a href='{$site_config['paths']['baseurl']}/login.php'>log in</a> and try again.</p>\n",
    'ok_user_error' => 'User Error',
    'ok_no_action' => 'no action to take',
];
