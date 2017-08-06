<?php
$start = microtime(true);

if (!file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php')) {
    header('Location: /install');
    die();
}

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php';

// start session on every page request
sessionStart();

require_once CACHE_DIR . 'free_cache.php';
require_once CACHE_DIR . 'site_settings.php';
require_once CACHE_DIR . 'staff_settings.php';
require_once CACHE_DIR . 'class_config.php';
//==Start memcache
require_once CLASS_DIR . 'class_cache.php';
$mc1 = new CACHE();

//==Block class
class curuser
{
    public static $blocks = [];
}

$CURBLOCK = &curuser::$blocks;
require_once CLASS_DIR . 'class_blocks_index.php';
require_once CLASS_DIR . 'class_blocks_stdhead.php';
require_once CLASS_DIR . 'class_blocks_userdetails.php';
require_once CLASS_DIR . 'class_bt_options.php';
require_once CACHE_DIR . 'block_settings_cache.php';

$load = sys_getloadavg();
if ($load[0] > 20) {
    die('Load is too high, Dont continuously refresh, or you will just make the problem last longer');
}
if (preg_match('/(?:\< *(?:java|script)|script\:|\+document\.)/i', serialize($_SERVER))) {
    die('Forbidden');
}
if (preg_match('/(?:\< *(?:java|script)|script\:|\+document\.)/i', serialize($_GET))) {
    die('Forbidden');
}
if (preg_match('/(?:\< *(?:java|script)|script\:|\+document\.)/i', serialize($_POST))) {
    die('Forbidden');
}
if (preg_match('/(?:\< *(?:java|script)|script\:|\+document\.)/i', serialize($_COOKIE))) {
    die('Forbidden');
}
function cleanquotes(&$in)
{
    if (is_array($in)) {
        return array_walk($in, 'cleanquotes');
    }

    return $in = stripslashes($in);
}

if (get_magic_quotes_gpc()) {
    array_walk($_GET, 'cleanquotes');
    array_walk($_POST, 'cleanquotes');
    array_walk($_COOKIE, 'cleanquotes');
    array_walk($_REQUEST, 'cleanquotes');
}
function htmlsafechars($txt = '')
{
    $txt = preg_replace('/&(?!#[0-9]+;)(?:amp;)?/s', '&amp;', $txt);
    $txt = str_replace([
        '<',
        '>',
        '"',
        "'",
    ], [
        '&lt;',
        '&gt;',
        '&quot;',
        '&#039;',
    ], $txt);

    return $txt;
}

function PostKey($ids = [])
{
    global $INSTALLER09;
    if (!is_array($ids)) {
        return false;
    }

    return md5($INSTALLER09['tracker_post_key'] . join('', $ids) . $INSTALLER09['tracker_post_key']);
}

function CheckPostKey($ids, $key)
{
    global $INSTALLER09;
    if (!is_array($ids) or !$key) {
        return false;
    }

    return $key == md5($INSTALLER09['tracker_post_key'] . join('', $ids) . $INSTALLER09['tracker_post_key']);
}

function validip($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP, [
        'flags' => FILTER_FLAG_NO_PRIV_RANGE,
        FILTER_FLAG_NO_RES_RANGE,
    ]) ? true : false;
}

function getip()
{
    foreach ([
                 'HTTP_CLIENT_IP',
                 'HTTP_X_FORWARDED_FOR',
                 'HTTP_X_FORWARDED',
                 'HTTP_X_CLUSTER_CLIENT_IP',
                 'HTTP_FORWARDED_FOR',
                 'HTTP_FORWARDED',
                 'REMOTE_ADDR',
             ] as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
}

function dbconn($autoclean = true)
{
    global $INSTALLER09;
    if (!@($GLOBALS['___mysqli_ston'] = mysqli_connect($INSTALLER09['mysql_host'], $INSTALLER09['mysql_user'], $INSTALLER09['mysql_pass']))) {
        switch (((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_errno($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_errno()) ? $___mysqli_res : false))) {
            case 1040:
            case 2002:
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    die("<html><head><meta http-equiv='refresh' content=\"5 $_SERVER[REQUEST_URI]\"></head><body><table border='0' width='100%' height='100%'><tr><td><h3 align='center'>The server load is very high at the moment. Retrying, please wait...</h3></td></tr></table></body></html>");
                } else {
                    die('Too many users. Please press the Refresh button in your browser to retry.');
                }
            // no break
            default:
                die('[' . ((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_errno($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_errno()) ? $___mysqli_res : false)) . '] dbconn: mysql_connect: ' . ((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_error($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false)));
        }
    }
    ((bool)mysqli_query($GLOBALS['___mysqli_ston'], "USE {$INSTALLER09['mysql_db']}")) or die('dbconn: mysql_select_db: ' . ((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_error($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false)));
    userlogin();
    referer();
    if ($autoclean) {
        register_shutdown_function('autoclean');
    }
}

function status_change($id)
{
    sql_query('UPDATE announcement_process SET status = 0 WHERE user_id = ' . sqlesc($id) . ' AND status = 1');
}

function hashit($var, $addtext = '')
{
    return md5('Th15T3xt' . $addtext . $var . $addtext . 'is5add3dto66uddy6he@water...');
}

function check_bans($ip, &$reason = '')
{
    global $INSTALLER09, $mc1;
    if (empty($ip)) {
        return false;
    }
    $key = 'bans:::' . $ip;
    if (($ban = $mc1->get_value($key)) === false) {
        $nip = ipToStorageFormat($ip);
        $ban_sql = sql_query('SELECT comment FROM bans WHERE (first <= ' . sqlesc($nip) . ' AND last >= ' . sqlesc($nip) . ') LIMIT 1');
        if (mysqli_num_rows($ban_sql)) {
            $comment = mysqli_fetch_row($ban_sql);
            $reason = 'Manual Ban (' . $comment[0] . ')';
            $mc1->cache_value($key, $reason, 86400); // 86400 // banned

            return true;
        }
        ((mysqli_free_result($ban_sql) || (is_object($ban_sql) && (get_class($ban_sql) == 'mysqli_result'))) ? true : false);
        $mc1->cache_value($key, 0, 86400); // 86400 // not banned

        return false;
    } elseif (!$ban) {
        return false;
    } else {
        $reason = $ban;

        return true;
    }
}

function userlogin()
{
    global $INSTALLER09, $mc1, $CURBLOCK, $mood, $whereis, $CURUSER;
    unset($GLOBALS['CURUSER']);
    $dt = TIME_NOW;
    $ip = getip();
    $nip = ipToStorageFormat($ip);
    $ipf = $_SERVER['REMOTE_ADDR'];
    if (isset($CURUSER)) {
        return;
    }
    if (!$INSTALLER09['site_online'] || !get_mycookie('uid') || !get_mycookie('pass') || !get_mycookie('hashv')) {
        return;
    }
    $id = (int)get_mycookie('uid');
    if (empty($id) || (strlen(get_mycookie('pass')) != 32) || (get_mycookie('hashv') != hashit($id, get_mycookie('pass')))) {
        return;
    }
    if (($row = $mc1->get_value('MyUser_' . $id)) === false) {
        $user_fields_ar_int = [
            'id',
            'added',
            'last_login',
            'last_access',
            'curr_ann_last_check',
            'curr_ann_id',
            'stylesheet',
            'class',
            'override_class',
            'language',
            'av_w',
            'av_h',
            'country',
            'warned',
            'torrentsperpage',
            'topicsperpage',
            'postsperpage',
            'ajaxchat_height',
            'reputation',
            'dst_in_use',
            'auto_correct_dst',
            'chatpost',
            'smile_until',
            'vip_until',
            'freeslots',
            'free_switch',
            'reputation',
            'invites',
            'invitedby',
            'uploadpos',
            'forumpost',
            'downloadpos',
            'immunity',
            'leechwarn',
            'last_browse',
            'sig_w',
            'sig_h',
            'forum_access',
            'hit_and_run_total',
            'donoruntil',
            'donated',
            'vipclass_before',
            'passhint',
            'avatarpos',
            'sendpmpos',
            'invitedate',
            'anonymous_until',
            'pirate',
            'king',
            'ssluse',
            'paranoia',
            'parked_until',
            'bjwins',
            'bjlosses',
            'irctotal',
            'last_access_numb',
            'onlinetime',
            'hits',
            'comments',
            'categorie_icon',
            'perms',
            'mood',
            'pms_per_page',
            'watched_user',
            'game_access',
            'opt1',
            'opt2',
            'can_leech',
            'wait_time',
            'torrents_limit',
            'peers_limit',
            'torrent_pass_version',
        ];
        $user_fields_ar_float = [
            'time_offset',
            'total_donated',
        ];
        $user_fields_ar_str = [
            'username',
            'passhash',
            'secret',
            'torrent_pass',
            'email',
            'status',
            'editsecret',
            'privacy',
            'info',
            'acceptpms',
            'ip',
            'avatar',
            'title',
            'notifs',
            'enabled',
            'donor',
            'deletepms',
            'savepms',
            'vip_added',
            'invite_rights',
            'anonymous',
            'disable_reason',
            'clear_new_tag_manually',
            'signatures',
            'signature',
            'highspeed',
            'hnrwarn',
            'parked',
            'hintanswer',
            'support',
            'supportfor',
            'invitees',
            'invite_on',
            'subscription_pm',
            'gender',
            'viewscloud',
            'tenpercent',
            'avatars',
            'offavatar',
            'hidecur',
            'signature_post',
            'forum_post',
            'avatar_rights',
            'offensive_avatar',
            'view_offensive_avatar',
            'google_talk',
            'msn',
            'aim',
            'yahoo',
            'website',
            'icq',
            'show_email',
            'gotgift',
            'hash1',
            'suspended',
            'warn_reason',
            'onirc',
            'birthday',
            'got_blocks',
            'pm_on_delete',
            'commentpm',
            'split',
            'browser',
            'got_moods',
            'show_pm_avatar',
            'watched_user_reason',
            'staff_notes',
            'where_is',
            'forum_sort',
            'browse_icons',
        ];
        $user_fields = implode(', ', array_merge($user_fields_ar_int, $user_fields_ar_float, $user_fields_ar_str));
        $res = sql_query('SELECT ' . $user_fields . ' ' . 'FROM users ' . 'WHERE id = ' . sqlesc($id) . ' ' . "AND enabled='yes' " . "AND status = 'confirmed'") or sqlerr(__FILE__, __LINE__);
        if (mysqli_num_rows($res) == 0) {
            $salty = salty($CURUSER['username']);
            header("Location: {$INSTALLER09['baseurl']}/logout.php?hash_please={$salty}");

            return;
        }
        $row = mysqli_fetch_assoc($res);
        foreach ($user_fields_ar_int as $i) {
            $row[$i] = (int)$row[$i];
        }
        foreach ($user_fields_ar_float as $i) {
            $row[$i] = (float)$row[$i];
        }
        foreach ($user_fields_ar_str as $i) {
            $row[$i] = $row[$i];
        }
        $mc1->cache_value('MyUser_' . $id, $row, $INSTALLER09['expires']['curuser']);
        unset($res);
    }
    if (get_mycookie('pass') !== md5($row['passhash'] . $_SERVER['REMOTE_ADDR'])) {
        $salty = salty($CURUSER['username']);
        header("Location: {$INSTALLER09['baseurl']}/logout.php?hash_please={$salty}");

        return;
    }
    if (!isset($row['perms']) || (!($row['perms'] & bt_options::PERMS_BYPASS_BAN))) {
        $banned = false;
        if (check_bans($ip, $reason)) {
            $banned = true;
        } else {
            if ($ip != $ipf) {
                if (check_bans($ipf, $reason)) {
                    $banned = true;
                }
            }
        }
        if ($banned) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
      <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"><head>
      <title>Forbidden</title>
      </head><body>
      <h1>403 Forbidden</h1>Unauthorized IP address!
      <p>Reason: <strong>' . htmlsafechars($reason) . '</strong></p>
      </body></html>';
            die;
        }
    }
    if ($row['class'] >= UC_STAFF) {
        $allowed_ID = $INSTALLER09['allowed_staff']['id'];
        if (!in_array(((int)$row['id']), $allowed_ID, true)) {
            $msg = 'Fake Account Detected: Username: ' . htmlsafechars($row['username']) . ' - UserID: ' . (int)$row['id'] . ' - UserIP : ' . getip();
            // Demote and disable
            sql_query("UPDATE users SET enabled = 'no', class = 0 WHERE id =" . sqlesc($row['id'])) or sqlerr(__FILE__, __LINE__);
            $mc1->begin_transaction('MyUser_' . $row['id']);
            $mc1->update_row(false, [
                'enabled' => 'no',
                'class'   => 0,
            ]);
            $mc1->commit_transaction($INSTALLER09['expires']['curuser']);
            $mc1->begin_transaction('user' . $row['id']);
            $mc1->update_row(false, [
                'enabled' => 'no',
                'class'   => 0,
            ]);
            $mc1->commit_transaction($INSTALLER09['expires']['user_cache']);
            write_log($msg);
            $salty = salty($CURUSER['username']);
            header("Location: {$INSTALLER09['baseurl']}/logout.php?hash_please={$salty}");
            die;
        }
    }
    $What_Cache = (XBT_TRACKER == true ? 'userstats_xbt_' : 'userstats_');
    if (($stats = $mc1->get_value($What_Cache . $id)) === false) {
        $What_Expire = (XBT_TRACKER == true ? $INSTALLER09['expires']['u_stats_xbt'] : $INSTALLER09['expires']['u_stats']);
        $stats_fields_ar_int = [
            'uploaded',
            'downloaded',
        ];
        $stats_fields_ar_float = [
            'seedbonus',
        ];
        $stats_fields_ar_str = [
            'modcomment',
            'bonuscomment',
        ];
        $stats_fields = implode(', ', array_merge($stats_fields_ar_int, $stats_fields_ar_float, $stats_fields_ar_str));
        $s = sql_query('SELECT ' . $stats_fields . ' FROM users WHERE id=' . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
        $stats = mysqli_fetch_assoc($s);
        foreach ($stats_fields_ar_int as $i) {
            $stats[$i] = (int)$stats[$i];
        }
        foreach ($stats_fields_ar_float as $i) {
            $stats[$i] = (float)$stats[$i];
        }
        foreach ($stats_fields_ar_str as $i) {
            $stats[$i] = $stats[$i];
        }
        $mc1->cache_value($What_Cache . $id, $stats, $What_Expire);
    }
    $row['seedbonus'] = $stats['seedbonus'];
    $row['uploaded'] = $stats['uploaded'];
    $row['downloaded'] = $stats['downloaded'];
    if (($ustatus = $mc1->get_value('userstatus_' . $id)) === false) {
        $sql2 = sql_query('SELECT * FROM ustatus WHERE userid = ' . sqlesc($id));
        if (mysqli_num_rows($sql2)) {
            $ustatus = mysqli_fetch_assoc($sql2);
        } else {
            $ustatus = [
                'last_status' => '',
                'last_update' => 0,
                'archive'     => '',
            ];
        }
        $mc1->add_value('userstatus_' . $id, $ustatus, $INSTALLER09['expires']['u_status']); // 30 days
    }
    $row['last_status'] = $ustatus['last_status'];
    $row['last_update'] = $ustatus['last_update'];
    $row['archive'] = $ustatus['archive'];
    if ($row['ssluse'] > 1 && !isset($_SERVER['HTTPS']) && !defined('NO_FORCE_SSL')) {
        $INSTALLER09['baseurl'] = str_replace('http', 'https', $INSTALLER09['baseurl']);
        header('Location: ' . $INSTALLER09['baseurl'] . $_SERVER['REQUEST_URI']);
        exit();
    }
    $blocks_key = 'blocks::' . $row['id'];
    if (($CURBLOCK = $mc1->get_value($blocks_key)) === false) {
        $c_sql = sql_query('SELECT * FROM user_blocks WHERE userid = ' . sqlesc($row['id'])) or sqlerr(__FILE__, __LINE__);
        if (mysqli_num_rows($c_sql) == 0) {
            sql_query('INSERT INTO user_blocks(userid) VALUES(' . sqlesc($row['id']) . ')');
            header('Location: index.php');
            die();
        }
        $CURBLOCK = mysqli_fetch_assoc($c_sql);
        $CURBLOCK['index_page'] = (int)$CURBLOCK['index_page'];
        $CURBLOCK['global_stdhead'] = (int)$CURBLOCK['global_stdhead'];
        $CURBLOCK['userdetails_page'] = (int)$CURBLOCK['userdetails_page'];
        $mc1->cache_value($blocks_key, $CURBLOCK, 0);
    }
    $where_is['username'] = htmlsafechars($row['username']);
    $whereis_array = [
        'index'        => '%s is viewing the <a href="%s">home page</a>',
        'browse'       => '%s is viewing the <a href="%s">torrents page</a>',
        'requests'     => '%s is viewing the <a href="%s">requests page</a>',
        'upload'       => '%s is viewing the <a href="%s">upload page</a>',
        'casino'       => '%s is viewing the <a href="%s">casino page</a>',
        'blackjack'    => '%s is viewing the <a href="%s">blackjack page</a>',
        'bet'          => '%s is viewing the <a href="%s">bet page</a>',
        'forums'       => '%s is viewing the <a href="%s">forums page</a>',
        'chat'         => '%s is viewing the <a href="%s">irc page</a>',
        'topten'       => '%s is viewing the <a href="%s">statistics page</a>',
        'faq'          => '%s is viewing the <a href="%s">faq page</a>',
        'rules'        => '%s is viewing the <a href="%s">rules page</a>',
        'staff'        => '%s is viewing the <a href="%s">staff page</a>',
        'announcement' => '%s is viewing the <a href="%s">announcements page</a>',
        'usercp'       => '%s is viewing the <a href="%s">usercp page</a>',
        'offers'       => '%s is viewing the <a href="%s">offers page</a>',
        'pm_system'    => '%s is viewing the <a href="%s">mailbox page</a>',
        'userdetails'  => '%s is viewing the <a href="%s">personal profile page</a>',
        'details'      => '%s is viewing the <a href="%s">torrents details page</a>',
        'games'        => '%s is viewing the <a href="%s">games page</a>',
        'arcade'       => '%s is viewing the <a href="%s">arcade page</a>',
        'flash'        => '%s is playing a <a href="%s">flash game</a>',
        'arcade_top_score' => '%s is viewing the <a href="%s">arcade top scores page</a>',
        'unknown'      => '%s location is unknown',
    ];
    if (preg_match('/\/(.*?)\.php/is', $_SERVER['REQUEST_URI'], $whereis_temp)) {
        if (isset($whereis_array[$whereis_temp[1]])) {
            $whereis = sprintf($whereis_array[$whereis_temp[1]], $where_is['username'], htmlsafechars($_SERVER['REQUEST_URI']));
        } else {
            $whereis = sprintf($whereis_array['unknown'], $where_is['username']);
        }
    } else {
        $whereis = sprintf($whereis_array['unknown'], $where_is['username']);
    }
    $userupdate0 = 'onlinetime = onlinetime + 0';
    $new_time = TIME_NOW - $row['last_access_numb'];
    $update_time = 0;
    if ($new_time < 300) {
        $userupdate0 = 'onlinetime = onlinetime + ' . $new_time;
        $update_time = $new_time;
    }
    $userupdate1 = 'last_access_numb = ' . TIME_NOW;
    $update_time = ($row['onlinetime'] + $update_time);
    if (($row['last_access'] != '0') and (($row['last_access']) < (TIME_NOW - 180))
        /* 3 mins **/) {
        sql_query('UPDATE users SET where_is =' . sqlesc($whereis) . ', last_access=' . TIME_NOW . ", $userupdate0, $userupdate1 WHERE id=" . sqlesc($row['id']));
        $mc1->begin_transaction('MyUser_' . $row['id']);
        $mc1->update_row(false, [
            'last_access'      => TIME_NOW,
            'onlinetime'       => $update_time,
            'last_access_numb' => TIME_NOW,
            'where_is'         => $whereis,
        ]);
        $mc1->commit_transaction($INSTALLER09['expires']['curuser']);
        $mc1->begin_transaction('user' . $row['id']);
        $mc1->update_row(false, [
            'last_access'      => TIME_NOW,
            'onlinetime'       => $update_time,
            'last_access_numb' => TIME_NOW,
            'where_is'         => $whereis,
        ]);
        $mc1->commit_transaction($INSTALLER09['expires']['user_cache']);
    }
    if ($row['override_class'] < $row['class']) {
        $row['class'] = $row['override_class'];
    }
    $GLOBALS['CURUSER'] = $row;
    get_template();
    $mood = create_moods();
}

function charset()
{
    global $CURUSER, $INSTALLER09;
    $lang_charset = $CURUSER['language'];
    switch ($lang_charset) {
        case $lang_charset == 2:
            return 'ISO-8859-1';
        case $lang_charset == 3:
            return 'ISO-8859-17';
        case $lang_charset == 4:
            return 'ISO-8859-15';
        default:
            return 'UTF-8';
    }
}

function autoclean()
{
    global $INSTALLER09, $mc1;
    // these clean_ids need to be run at specific interval, regardless of when they run
    $run_at_specified_times = [82, 83];
    $now = TIME_NOW;
    $sql = sql_query("SELECT * FROM cleanup WHERE clean_on = 1 AND clean_time <= {$now} ORDER BY clean_time ASC LIMIT 0,1");
    $row = mysqli_fetch_assoc($sql);
    if ($row['clean_id']) {
        $next_clean = intval($now + ($row['clean_increment'] ? $row['clean_increment'] : 15 * 60));
        if (in_array($row['clean_id'], $run_at_specified_times)) {
            $next_clean = intval($row['clean_time'] + $row['clean_increment']);
        }
        sql_query('UPDATE cleanup SET clean_time = ' . sqlesc($next_clean) . ' WHERE clean_id = ' . sqlesc($row['clean_id']));
        if (file_exists(CLEAN_DIR . '' . $row['clean_file'])) {
            require_once CLEAN_DIR . '' . $row['clean_file'];
            if (function_exists('docleanup')) {
                register_shutdown_function('docleanup', $row);
            }
        }
    }

    if (($tfreak_cron = $mc1->get_value('tfreak_cron_')) === false) {
        $mc1->cache_value('tfreak_cron_', TIME_NOW, 60);
        require_once INCL_DIR . 'newsrss.php';
        $fox = $tfreak = $github = false;

        $github = github_shout();
        if ($github) {
            $fox = foxnews_shout();
        }
        if ($fox) {
            $tfreak = tfreak_shout();
        }
    }
}

function get_template()
{
    global $CURUSER, $INSTALLER09;
    if (isset($CURUSER)) {
        if (file_exists(TEMPLATE_DIR . "{$CURUSER['stylesheet']}/template.php")) {
            require_once TEMPLATE_DIR . "{$CURUSER['stylesheet']}/template.php";
        } else {
            if (isset($INSTALLER09)) {
                if (file_exists(TEMPLATE_DIR . "{$INSTALLER09['stylesheet']}/template.php")) {
                    require_once TEMPLATE_DIR . "{$INSTALLER09['stylesheet']}/template.php";
                } else {
                    echo 'Sorry, Templates do not seem to be working properly and missing some code. Please report this to the programmers/owners.';
                }
            } else {
                if (file_exists(TEMPLATE_DIR . '1/template.php')) {
                    require_once TEMPLATE_DIR . '1/template.php';
                } else {
                    echo 'Sorry, Templates do not seem to be working properly and missing some code. Please report this to the programmers/owners.';
                }
            }
        }
    } else {
        if (file_exists(TEMPLATE_DIR . "{$INSTALLER09['stylesheet']}/template.php")) {
            require_once TEMPLATE_DIR . "{$INSTALLER09['stylesheet']}/template.php";
        } else {
            echo 'Sorry, Templates do not seem to be working properly and missing some code. Please report this to the programmers/owners.';
        }
    }
    if (!function_exists('stdhead')) {
        echo 'stdhead function missing';
        function stdhead($title = '', $message = true)
        {
            return "<html><head><title>$title</title></head><body>";
        }
    }
    if (!function_exists('stdfoot')) {
        echo 'stdfoot function missing';
        function stdfoot()
        {
            return '</body></html>';
        }
    }
    if (!function_exists('stdmsg')) {
        echo 'stdmgs function missing';
        function stdmsg($title, $message)
        {
            return '<b>' . $title . "</b><br>$message";
        }
    }
    if (!function_exists('StatusBar')) {
        echo 'StatusBar function missing';
        function StatusBar()
        {
            global $CURUSER, $lang;

            return "{$lang['gl_msg_welcome']}, {$CURUSER['username']}";
        }
    }
}

function make_freeslots($userid, $key)
{
    global $mc1, $INSTALLER09;
    if (($slot = $mc1->get_value($key . $userid)) === false) {
        $res_slots = sql_query('SELECT * FROM freeslots WHERE userid = ' . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
        $slot = [];
        if (mysqli_num_rows($res_slots)) {
            while ($rowslot = mysqli_fetch_assoc($res_slots)) {
                $slot[] = $rowslot;
            }
        }
        $mc1->cache_value($key . $userid, $slot, 86400 * 7);
    }

    return $slot;
}

function make_bookmarks($userid, $key)
{
    global $mc1, $INSTALLER09;
    if (($book = $mc1->get_value($key . $userid)) === false) {
        $res_books = sql_query('SELECT * FROM bookmarks WHERE userid = ' . sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
        $book = [];
        if (mysqli_num_rows($res_books)) {
            while ($rowbook = mysqli_fetch_assoc($res_books)) {
                $book[] = $rowbook;
            }
        }
        $mc1->cache_value($key . $userid, $book, 86400 * 7); // 7 days
    }

    return $book;
}

function genrelist()
{
    global $mc1, $INSTALLER09;
    if (($ret = $mc1->get_value('genrelist')) == false) {
        $ret = [];
        $res = sql_query('SELECT id, image, name FROM categories ORDER BY name');
        while ($row = mysqli_fetch_assoc($res)) {
            $ret[] = $row;
        }
        $mc1->cache_value('genrelist', $ret, $INSTALLER09['expires']['genrelist']);
    }

    return $ret;
}

function create_moods($force = false)
{
    global $mc1, $INSTALLER09;
    $key = 'moods';
    if (($mood = $mc1->get_value($key)) === false || $force) {
        $res_moods = sql_query('SELECT * FROM moods ORDER BY id ASC') or sqlerr(__FILE__, __LINE__);
        $mood = [];
        if (mysqli_num_rows($res_moods)) {
            while ($rmood = mysqli_fetch_assoc($res_moods)) {
                $mood['image'][$rmood['id']] = $rmood['image'];
                $mood['name'][$rmood['id']] = $rmood['name'];
            }
        }
        $mc1->cache_value($key, $mood, 86400 * 7);
    }

    return $mood;
}

//== delete
function delete_id_keys($keys, $keyname = false)
{
    global $mc1;
    if (!(is_array($keys) || $keyname)) { // if no key given or not an array
        return false;
    } else {
        foreach ($keys as $id) { // proceed
            $mc1->delete_value($keyname . $id);
        }
    }

    return true;
}

function unesc($x)
{
    if (get_magic_quotes_gpc()) {
        return stripslashes($x);
    }

    return $x;
}

//Extended mksize Function
function mksize($bytes)
{
    $bytes = max(0, (int)$bytes);

    if ($bytes < 1024000) {
        return number_format($bytes / 1024, 2) . ' KB';
    } //Kilobyte
    elseif ($bytes < 1048576000) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } //Megabyte
    elseif ($bytes < 1073741824000) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } //Gigebyte
    elseif ($bytes < 1099511627776000) {
        return number_format($bytes / 1099511627776, 3) . ' TB';
    } //Terabyte
    elseif ($bytes < 1125899906842624000) {
        return number_format($bytes / 1125899906842624, 3) . ' PB';
    } //Petabyte
    elseif ($bytes < 1152921504606846976000) {
        return number_format($bytes / 1152921504606846976, 3) . ' EB';
    } //Exabyte
    elseif ($bytes < 1180591620717411303424000) {
        return number_format($bytes / 1180591620717411303424, 3) . ' ZB';
    } //Zettabyte
    else {
        return number_format($bytes / 1208925819614629174706176, 3) . ' YB';
    } //Yottabyte
}

function mkprettytime($s)
{
    if ($s < 0) {
        $s = 0;
    }
    $t = [];
    foreach ([
                 '60:sec',
                 '60:min',
                 '24:hour',
                 '0:day',
             ] as $x) {
        $y = explode(':', $x);
        if ($y[0] > 1) {
            $v = $s % $y[0];
            $s = floor($s / $y[0]);
        } else {
            $v = $s;
        }
        $t[$y[1]] = $v;
    }
    if ($t['day']) {
        return $t['day'] . 'd ' . sprintf('%02d:%02d:%02d', $t['hour'], $t['min'], $t['sec']);
    }
    if ($t['hour']) {
        return sprintf('%d:%02d:%02d', $t['hour'], $t['min'], $t['sec']);
    }

    return sprintf('%d:%02d', $t['min'], $t['sec']);
}

function mkglobal($vars)
{
    if (!is_array($vars)) {
        $vars = explode(':', $vars);
    }
    foreach ($vars as $v) {
        if (isset($_GET[$v])) {
            $GLOBALS[$v] = unesc($_GET[$v]);
        } elseif (isset($_POST[$v])) {
            $GLOBALS[$v] = unesc($_POST[$v]);
        } else {
            return 0;
        }
    }

    return 1;
}

function validfilename($name)
{
    return preg_match('/^[^\0-\x1f:\\\\\/?*\xff#<>|]+$/si', $name);
}

function validemail($email)
{
    return preg_match('/^[\w.-]+@([\w.-]+\.)+[a-z]{2,6}$/is', $email);
}

function sqlesc($x)
{
    if (is_integer($x)) {
        return (int)$x;
    }

    return sprintf('\'%s\'', mysqli_real_escape_string($GLOBALS['___mysqli_ston'], $x));
}

function sqlwildcardesc($x)
{
    return str_replace(['%', '_'], ['\\%', '\\_'], mysqli_real_escape_string($GLOBALS['___mysqli_ston'], $x));
}

function httperr($code = 404)
{
    header('HTTP/1.0 404 Not found');
    echo '<h1>Not Found</h1>';
    echo '<p>Sorry pal :(</p>';
    exit();
}

function logincookie($id, $passhash, $updatedb = 1, $expires = 0x7fffffff)
{
    set_mycookie('uid', $id, $expires);
    set_mycookie('pass', $passhash, $expires);
    set_mycookie('hashv', hashit($id, $passhash), $expires);
    if ($updatedb) {
        sql_query('UPDATE users SET last_login = ' . TIME_NOW . ' WHERE id = ' . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
    }
}

function set_mycookie($name, $value = '', $expires_in = 0, $sticky = 1)
{
    global $INSTALLER09;
    if ($sticky == 1) {
        $expires = TIME_NOW + 60 * 60 * 24 * 365;
    } elseif ($expires_in) {
        $expires = TIME_NOW + ($expires_in * 86400);
    } else {
        $expires = false;
    }
    $INSTALLER09['cookie_domain'] = $INSTALLER09['cookie_domain'] == '' ? '' : $INSTALLER09['cookie_domain'];
    $INSTALLER09['cookie_path'] = $INSTALLER09['cookie_path'] == '' ? '/' : $INSTALLER09['cookie_path'];
    if (PHP_VERSION < 5.2) {
        if ($INSTALLER09['cookie_domain']) {
            @setcookie($INSTALLER09['cookie_prefix'] . $name, $value, $expires, $INSTALLER09['cookie_path'], $INSTALLER09['cookie_domain'] . '; HttpOnly');
        } else {
            @setcookie($INSTALLER09['cookie_prefix'] . $name, $value, $expires, $INSTALLER09['cookie_path']);
        }
    } else {
        @setcookie($INSTALLER09['cookie_prefix'] . $name, $value, $expires, $INSTALLER09['cookie_path'], $INSTALLER09['cookie_domain'], null, true);
    }
}

function get_mycookie($name)
{
    global $INSTALLER09;
    if (isset($_COOKIE[$INSTALLER09['cookie_prefix'] . $name]) and !empty($_COOKIE[$INSTALLER09['cookie_prefix'] . $name])) {
        return urldecode($_COOKIE[$INSTALLER09['cookie_prefix'] . $name]);
    } else {
        return false;
    }
}

function logoutcookie()
{
    set_mycookie('uid', '-1');
    set_mycookie('pass', '-1');
    set_mycookie('hashv', '-1');
    destroySession();
}

function loggedinorreturn()
{
    global $CURUSER, $INSTALLER09;
    if (!$CURUSER) {
        header("Location: {$INSTALLER09['baseurl']}/login.php?returnto=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

function searchfield($s)
{
    return preg_replace([
        '/[^a-z0-9]/si',
        '/^\s*/s',
        '/\s*$/s',
        '/\s+/s',
    ], [
        ' ',
        '',
        '',
        ' ',
    ], $s);
}

function get_row_count($table, $suffix = '')
{
    if ($suffix) {
        $suffix = " $suffix";
    }
    ($r = sql_query("SELECT COUNT(*) FROM $table$suffix")) or die(((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_error($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false)));
    ($a = mysqli_fetch_row($r)) or die(((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_error($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false)));

    return (int)$a[0];
}

function get_one_row($table, $suffix, $where)
{
    $r = sql_query("SELECT $suffix FROM $table $where") or sqlerr(__FILE__, __LINE__);
    $a = mysqli_fetch_row($r);
    if (isset($a[0])) {
        return $a[0];
    } else {
        return false;
    }
}

function stderr($heading, $text)
{
    $htmlout = stdhead();
    $htmlout .= stdmsg($heading, $text);
    $htmlout .= stdfoot();
    echo $htmlout;
    exit();
}

// Basic MySQL error handler
function sqlerr($file = '', $line = '')
{
    global $INSTALLER09, $CURUSER;
    $the_error = ((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_error($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false));
    $the_error_no = ((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_errno($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_errno()) ? $___mysqli_res : false));
    if (SQL_DEBUG == 0) {
        exit();
    } elseif ($INSTALLER09['sql_error_log'] and SQL_DEBUG == 1) {
        $_error_string = "\n===================================================";
        $_error_string .= "\n Date: " . date('r');
        $_error_string .= "\n Error Number: " . $the_error_no;
        $_error_string .= "\n Error: " . $the_error;
        $_error_string .= "\n IP Address: " . $_SERVER['REMOTE_ADDR'];
        $_error_string .= "\n in file " . $file . ' on line ' . $line;
        $_error_string .= "\n URL:" . $_SERVER['REQUEST_URI'];
        $_error_string .= "\n Username: {$CURUSER['username']}[{$CURUSER['id']}]";
        if ($FH = @fopen($INSTALLER09['sql_error_log'], 'a')) {
            @fwrite($FH, $_error_string);
            @fclose($FH);
        }
        echo '<html><head><title>MySQLI Error</title>
                    <style>P,BODY{ font-family:arial,sans-serif; font-size:11px; }</style></head><body>
                       <blockquote><h1>MySQLI Error</h1><b>There appears to be an error with the database.</b><br>
                       You can try to refresh the page by clicking <a href="javascript:window.location=window.location;">here</a>
                  </body></html>';
    } else {
        $the_error = "\nSQL error: " . $the_error . "\n";
        $the_error .= 'SQL error code: ' . $the_error_no . "\n";
        $the_error .= 'Date: ' . date("l dS \of F Y h:i:s A");
        $out = "<html>\n<head>\n<title>MySQLI Error</title>\n
                   <style>P,BODY{ font-family:arial,sans-serif; font-size:11px; }</style>\n</head>\n<body>\n
                   <blockquote>\n<h1>MySQLI Error</h1><b>There appears to be an error with the database.</b><br>
                   You can try to refresh the page by clicking <a href=\"javascript:window.location=window.location;\">here</a>.
                   <br><br><b>Error Returned</b><br>
                   <form name='mysql'><textarea rows=\"15\" cols=\"60\">" . htmlsafechars($the_error, ENT_QUOTES) . '</textarea></form><br>We apologise for any inconvenience</blockquote></body></html>';
        echo $out;
    }
    exit();
}

function get_dt_num()
{
    return gmdate('YmdHis');
}

function write_log($text)
{
    $text = sqlesc($text);
    $added = TIME_NOW;
    sql_query("INSERT INTO sitelog (added, txt) VALUES($added, $text)") or sqlerr(__FILE__, __LINE__);
}

function sql_timestamp_to_unix_timestamp($s)
{
    return mktime(substr($s, 11, 2), substr($s, 14, 2), substr($s, 17, 2), substr($s, 5, 2), substr($s, 8, 2), substr($s, 0, 4));
}

function unixstamp_to_human($unix = 0)
{
    $offset = get_time_offset();
    $tmp = gmdate('j,n,Y,G,i', $unix + $offset);
    list($day, $month, $year, $hour, $min) = explode(',', $tmp);

    return [
        'day'    => $day,
        'month'  => $month,
        'year'   => $year,
        'hour'   => $hour,
        'minute' => $min,
    ];
}

function get_time_offset()
{
    global $CURUSER, $INSTALLER09;
    $r = 0;
    $r = (($CURUSER['time_offset'] != '') ? $CURUSER['time_offset'] : $INSTALLER09['time_offset']) * 3600;
    if ($INSTALLER09['time_adjust']) {
        $r += ($INSTALLER09['time_adjust'] * 60);
    }
    if ($CURUSER['dst_in_use']) {
        $r += 3600;
    }

    return $r;
}

function get_date($date, $method, $norelative = 0, $full_relative = 0)
{
    global $INSTALLER09;
    static $offset_set = 0;
    static $today_time = 0;
    static $yesterday_time = 0;
    $time_options = [
        'JOINED' => $INSTALLER09['time_joined'],
        'SHORT'  => $INSTALLER09['time_short'],
        'LONG'   => $INSTALLER09['time_long'],
        'TINY'   => $INSTALLER09['time_tiny'] ? $INSTALLER09['time_tiny'] : 'j M Y - G:i',
        'DATE'   => $INSTALLER09['time_date'] ? $INSTALLER09['time_date'] : 'j M Y',
    ];
    if (!$date) {
        return '--';
    }
    if (empty($method)) {
        $method = 'LONG';
    }
    if ($offset_set == 0) {
        $GLOBALS['offset'] = get_time_offset();
        if ($INSTALLER09['time_use_relative']) {
            $today_time = gmdate('d,m,Y', (TIME_NOW + $GLOBALS['offset']));
            $yesterday_time = gmdate('d,m,Y', ((TIME_NOW - 86400) + $GLOBALS['offset']));
        }
        $offset_set = 1;
    }
    if ($INSTALLER09['time_use_relative'] == 3) {
        $full_relative = 1;
    }
    if ($full_relative and ($norelative != 1)) {
        $diff = TIME_NOW - $date;
        if ($diff < 3600) {
            if ($diff < 120) {
                return '< 1 minute ago';
            } else {
                return sprintf('%s minutes ago', intval($diff / 60));
            }
        } elseif ($diff < 7200) {
            return '< 1 hour ago';
        } elseif ($diff < 86400) {
            return sprintf('%s hours ago', intval($diff / 3600));
        } elseif ($diff < 172800) {
            return '< 1 day ago';
        } elseif ($diff < 604800) {
            return sprintf('%s days ago', intval($diff / 86400));
        } elseif ($diff < 1209600) {
            return '< 1 week ago';
        } elseif ($diff < 3024000) {
            return sprintf('%s weeks ago', intval($diff / 604900));
        } else {
            return gmdate($time_options[$method], ($date + $GLOBALS['offset']));
        }
    } elseif ($INSTALLER09['time_use_relative'] and ($norelative != 1)) {
        $this_time = gmdate('d,m,Y', ($date + $GLOBALS['offset']));
        if ($INSTALLER09['time_use_relative'] == 2) {
            $diff = TIME_NOW - $date;
            if ($diff < 3600) {
                if ($diff < 120) {
                    return '< 1 minute ago';
                } else {
                    return sprintf('%s minutes ago', intval($diff / 60));
                }
            }
        }
        if ($this_time == $today_time) {
            return str_replace('{--}', 'Today', gmdate($INSTALLER09['time_use_relative_format'], ($date + $GLOBALS['offset'])));
        } elseif ($this_time == $yesterday_time) {
            return str_replace('{--}', 'Yesterday', gmdate($INSTALLER09['time_use_relative_format'], ($date + $GLOBALS['offset'])));
        } else {
            return gmdate($time_options[$method], ($date + $GLOBALS['offset']));
        }
    } else {
        return gmdate($time_options[$method], ($date + $GLOBALS['offset']));
    }
}

function ratingpic($num)
{
    global $INSTALLER09;
    $r = round($num * 2) / 2;
    if ($r < 1 || $r > 5) {
        return;
    }

    return "<img src=\"{$INSTALLER09['pic_base_url']}ratings/{$r}.gif\" border=\"0\" alt=\"Rating: $num / 5\" title=\"Rating: $num / 5\" />";
}

function hash_pad($hash)
{
    return str_pad($hash, 20);
}

//== cutname = Laffin
function CutName($txt, $len = 40)
{
    return strlen($txt) > $len ? substr($txt, 0, $len - 1) . '...' : $txt;
}

function CutName_B($txt, $len = 20)
{
    return strlen($txt) > $len ? substr($txt, 0, $len - 1) . '...' : $txt;
}

function load_language($file = '')
{
    global $INSTALLER09, $CURUSER;
    if (!isset($GLOBALS['CURUSER']) or empty($GLOBALS['CURUSER']['language'])) {
        if (!file_exists(LANG_DIR . "{$INSTALLER09['language']}/lang_{$file}.php")) {
            stderr('System Error', 'Can\'t find language files');
        }
        require_once LANG_DIR . "{$INSTALLER09['language']}/lang_{$file}.php";

        return $lang;
    }
    if (!file_exists(LANG_DIR . "{$CURUSER['language']}/lang_{$file}.php")) {
        stderr('System Error', 'Can\'t find language files');
    } else {
        require_once LANG_DIR . "{$CURUSER['language']}/lang_{$file}.php";
    }

    return $lang;
}

function flood_limit($table)
{
    global $CURUSER, $INSTALLER09, $lang;
    if (!file_exists($INSTALLER09['flood_file']) || !is_array($max = unserialize(file_get_contents($INSTALLER09['flood_file'])))) {
        return;
    }
    if (!isset($max[$CURUSER['class']])) {
        return;
    }
    $tb = [
        'posts'    => 'posts.userid',
        'comments' => 'comments.user',
        'messages' => 'messages.sender',
    ];
    $q = sql_query('SELECT min(' . $table . '.added) as first_post, count(' . $table . '.id) as how_many FROM ' . $table . ' WHERE ' . $tb[$table] . ' = ' . $CURUSER['id'] . ' AND ' . TIME_NOW . ' - ' . $table . '.added < ' . $INSTALLER09['flood_time']);
    $a = mysqli_fetch_assoc($q);
    if ($a['how_many'] > $max[$CURUSER['class']]) {
        stderr($lang['gl_sorry'], $lang['gl_flood_msg'] . '' . mkprettytime($INSTALLER09['flood_time'] - (TIME_NOW - $a['first_post'])));
    }
}

function sql_query($query)
{
    global $query_stat;
    $query_start_time = microtime(true); // Start time
    $result = mysqli_query($GLOBALS['___mysqli_ston'], $query);
    $query_end_time = microtime(true); // End time
    $querytime = ($query_end_time - $query_start_time);
    $query_stat[] = [
        'seconds' => number_format($query_end_time - $query_start_time, 6),
        'query'   => $query,
    ];

    return $result;
}

function get_percent_completed_image($p)
{
    $img = 'progress-';
    switch (true) {
        case $p >= 100:
            $img .= 5;
            break;

        case ($p >= 0) && ($p <= 10):
            $img .= 0;
            break;

        case ($p >= 11) && ($p <= 40):
            $img .= 1;
            break;

        case ($p >= 41) && ($p <= 60):
            $img .= 2;
            break;

        case ($p >= 61) && ($p <= 80):
            $img .= 3;
            break;

        case ($p >= 81) && ($p <= 99):
            $img .= 4;
            break;
    }

    return '<img src="/pic/' . $img . '.gif" alt="percent" />';
}

function strip_tags_array($ar)
{
    if (is_array($ar)) {
        foreach ($ar as $k => $v) {
            $ar[strip_tags($k)] = strip_tags($v);
        }
    } else {
        $ar = strip_tags($ar);
    }

    return $ar;
}

function referer()
{
    $http_referer = getenv('HTTP_REFERER');
    if (!empty($_SERVER['HTTP_HOST']) && (strstr($http_referer, $_SERVER['HTTP_HOST']) == false) && ($http_referer != '')) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $http_agent = $_SERVER['HTTP_USER_AGENT'];
        $http_page = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        if (!empty($_SERVER['QUERY_STRING'])) {
            $http_page .= '?' . $_SERVER['QUERY_STRING'];
        }
        sql_query('INSERT INTO referrers (browser, ip, referer, page, date) VALUES (' . sqlesc($http_agent) . ', ' . sqlesc($ip) . ', ' . sqlesc($http_referer) . ', ' . sqlesc($http_page) . ', ' . sqlesc(TIME_NOW) . ')');
    }
}

function mysql_fetch_all($query, $default_value = [])
{
    $r = @sql_query($query);
    $result = [];
    if ($err = ((is_object($GLOBALS['___mysqli_ston'])) ? mysqli_error($GLOBALS['___mysqli_ston']) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false))) {
        return $err;
    }
    if (@mysqli_num_rows($r)) {
        while ($row = mysqli_fetch_array($r)) {
            $result[] = $row;
        }
    }
    if (count($result) == 0) {
        return $default_value;
    }

    return $result;
}

function write_bonus_log($userid, $amount, $type)
{
    $added = TIME_NOW;
    $donation_type = $type;
    sql_query('INSERT INTO bonuslog (id, donation, type, added_at) VALUES(' . sqlesc($userid) . ', ' . sqlesc($amount) . ', ' . sqlesc($donation_type) . ", $added)") or sqlerr(__FILE__, __LINE__);
}

function human_filesize($bytes, $dec = 2)
{
    $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function sessionStart()
{
    global $INSTALLER09;
    if (!session_id()) {
        // Set the session name:
        session_name($INSTALLER09['sessionName']);

        // Set session cookie parameters:
        session_set_cookie_params(
            $INSTALLER09['cookie_lifetime'] * 86400,
            $INSTALLER09['cookie_path'],
            $INSTALLER09['cookie_domain'],
            $INSTALLER09['sessionCookieSecure']
        );

        // enforce php settings before start session
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_trans_sid', 0);

        // Start the session:
        session_start();
    }

    // Create a new CSRF token.
    setSessionVar('csrf_token', bin2hex(random_bytes(64)));

    // Make sure we have a canary set and Regenerate session ID every five minutes:
    if (empty($_SESSION['canary']) || $_SESSION['canary'] < time() - 300) {
        regenerateSessionID();
        setSessionVar('canary', time());
    }
}

function destroySession()
{
    sessionStart();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

function regenerateSessionID()
{
    if (!empty($_SESSION)) {
        @session_regenerate_id(true);
    }
}

function validateToken($token, $key = null, $prefix = null) {
    global $INSTALLER09;
    if ($prefix === null) {
        $prefix = $INSTALLER09['sessionKeyPrefix'];
    }
    if ($key === null) {
        $key = $INSTALLER09['session_csrf'];
    }

    if (empty($token)) {
        return false;
    } elseif (hash_equals($_SESSION[$prefix . $key], $token)) {
        unsetSessionVar($key, $prefix);
        setSessionVar($key, $prefix, bin2hex(random_bytes(64)));
        return true;
    }
    return false;
}

function ipToStorageFormat($ip)
{
    if (function_exists('inet_pton')) {
        // ipv4 & ipv6:
        return @inet_pton($ip);
    }

    // Only ipv4:
    return @pack('N', @ip2long($ip));
}

function ipFromStorageFormat($ip)
{
    if (function_exists('inet_ntop')) {
        // ipv4 & ipv6:
        return @inet_ntop($ip);
    }
    // Only ipv4:
    $unpacked = @unpack('Nlong', $ip);
    if (isset($unpacked['long'])) {
        return @long2ip($unpacked['long']);
    }

    return null;
}

function setSessionVar($key, $value, $prefix = null)
{
    global $INSTALLER09;
    if ($prefix === null) {
        $prefix = $INSTALLER09['sessionKeyPrefix'];
    }

    // Set the session value:
    if (!empty($_SESSION[$prefix . $key])) {
        unsetSessionVar($_SESSION[$prefix . $key]);
    }
    $_SESSION[$prefix . $key] = $value;
}

function getSessionVar($key, $prefix = null)
{
    global $INSTALLER09;
    if ($prefix === null) {
        $prefix = $INSTALLER09['sessionKeyPrefix'];
    }

    // Return the session value if existing:
    if (isset($_SESSION[$prefix . $key])) {
        return $_SESSION[$prefix . $key];
    } else {
        return null;
    }
}

function unsetSessionVar($key, $prefix = null)
{
    global $INSTALLER09;
    if ($prefix === null) {
        $prefix = $INSTALLER09['sessionKeyPrefix'];
    }

    // Set the session value:
    unset($_SESSION[$prefix . $key]);
}

function salty($username)
{
    global $INSTALLER09;
    return bin2hex(random_bytes(64));
}

function replace_unicode_strings($text)
{
    $text = str_replace(['“', '”'], '"', $text);
    $text = str_replace(['&rsquo;', '’'], "'", $text);
    $text = str_replace(['&lsquo;', '‘'], "'", $text);
    $text = str_replace(['&rdquo;', '”'], '"', $text);
    $text = str_replace(['&ldquo;', '”'], '"', $text);
    $text = str_replace(['&#8212;', '–'], '-', $text);
    return html_entity_decode(htmlentities($text));
}

if (file_exists('install/index.php')) {
    $HTMLOUT = '';
    $HTMLOUT .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"
    \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
    <html xmlns='http://www.w3.org/1999/xhtml'>
    <head>
    <title>Warning</title>
    </head>
    <body><div style='font-size:33px;color:white;background-color:red;text-align:center;'>Delete the install directory</div></body></html>";
    echo $HTMLOUT;
    exit();
}
