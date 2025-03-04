<?php

declare(strict_types = 1);

use Delight\Auth\Auth;

require_once __DIR__ . '/../include/bittorrent.php';
require_once INCL_DIR . 'function_users.php';
require_once INCL_DIR . 'function_html.php';
global $container, $site_config;

$auth = $container->get(Auth::class);
if (!$auth->isLoggedIn()) {
    get_template();
} else {
    check_user_status();
}

$lang = array_merge(load_language('global'), load_language('useragreement'));

$HTMLOUT = "
    <div class='portlet padbottom20 has-text-centered'>
        <h1>{$site_config['site']['name']} {$lang['frame_usragrmnt']}</h1>
        <div class='text-justify'>
            {$lang['text_usragrmnt']}
        </div>
    </div>";

echo stdhead($lang['stdhead_usragrmnt']) . $HTMLOUT . stdfoot();
