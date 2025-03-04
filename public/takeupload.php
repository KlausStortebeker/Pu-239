<?php

declare(strict_types = 1);

use DI\DependencyException;
use DI\NotFoundException;
use Pu239\Cache;
use Pu239\Database;
use Pu239\Message;
use Pu239\Peer;
use Pu239\Session;
use Pu239\Torrent;
use Pu239\User;

require_once __DIR__ . '/../include/bittorrent.php';
require_once INCL_DIR . 'function_users.php';
require_once CLASS_DIR . 'class.bencdec.php';
require_once INCL_DIR . 'function_announce.php';
require_once INCL_DIR . 'function_html.php';
require_once INCL_DIR . 'function_bbcode.php';
global $container, $site_config;

$data = $_POST;
$torrent_pass = isset($data['torrent_pass']) ? $data['torrent_pass'] : '';
$auth = isset($data['auth']) ? $data['auth'] : '';
$bot = isset($data['bot']) ? $data['bot'] : '';
$strip = isset($data['strip']) && is_bool($data['strip']) ? (bool) $data['strip'] : false;
$name = isset($data['name']) ? htmlsafechars($data['name']) : '';
$url = isset($data['url']) ? htmlsafechars($data['url']) : '';
$isbn = isset($data['isbn']) ? htmlsafechars($data['isbn']) : '';
$poster = isset($data['poster']) ? htmlsafechars($data['poster']) : '';
$youtube = isset($data['youtube']) ? htmlsafechars($data['youtube']) : '';
$tags = isset($data['tags']) ? htmlsafechars($data['tags']) : '';
$description = isset($data['description']) ? htmlsafechars($data['description']) : '';
$body = isset($data['body']) ? htmlsafechars($data['body']) : '';
$release_group = isset($data['release_group']) ? htmlsafechars($data['release_group']) : '';
$free_length = isset($data['free_length']) && is_valid_id((int) $data['free_length']) ? (int) $data['free_length'] : 0;
$half_length = isset($data['half_length']) && is_valid_id((int) $data['half_length']) ? (int) $data['half_length'] : 0;
$music = isset($data['music']) && is_array($data['music']) ? $data['music'] : [];
$movie = isset($data['movie']) && is_array($data['movie']) ? $data['movie'] : [];
$game = isset($data['game']) && is_array($data['game']) ? $data['game'] : [];
$apps = isset($data['apps']) && is_array($data['apps']) ? $data['apps'] : [];
$subs = isset($data['subs']) && is_array($data['subs']) ? $data['subs'] : [];
$genre = isset($data['genre']) ? $data['genre'] : '';
$catid = isset($data['type']) && is_valid_id((int) $data['type']) ? (int) $data['type'] : 0;
$request = isset($data['request']) && is_valid_id((int) $data['request']) ? (int) $data['request'] : 0;
$offer = isset($data['offer']) && is_valid_id((int) $data['offer']) ? (int) $data['offer'] : 0;
$uplver = isset($data['uplver']) && $data['uplver'] === 'yes' ? 'yes' : 'no';
$allow_comments = isset($data['allow_comments']) && $data['allow_comments'] === 'yes' ? 'yes' : 'no';
$descr = isset($data['descr']) ? htmlsafechars($data['descr']) : '';

$cache = $container->get(Cache::class);
$users_class = $container->get(User::class);
if (!empty($bot) && !empty($auth) && !empty($torrent_pass)) {
    $owner_id = $users_class->get_bot_id($site_config['allowed']['upload'], $bot, $torrent_pass, $auth);
    $user = $users_class->getUserFromId($owner_id);
} else {
    $user = check_user_status();
    $owner_id = $user['id'];
    $cache->set('user_upload_variables_' . $user['id'], serialize($data), 3600);
}

$dt = TIME_NOW;
ini_set('upload_max_filesize', (string) $site_config['site']['max_torrent_size']);
ini_set('memory_limit', '64M');
$lang = array_merge(load_language('global'), load_language('takeupload'));
$session = $container->get(Session::class);
if ($user['class'] < $site_config['allowed']['upload'] || $user['uploadpos'] != 1 || $user['suspended'] === 'yes') {
    $cache->delete('user_upload_variables_' . $owner_id);
    $session->set('is-warning', $lang['not_authorized']);
    why_die($lang['not_authorized']);
}
if (empty($body) || empty($catid) || empty($name) || empty($_FILES['file'])) {
    $session->set('is-warning', $lang['takeupload_no_formdata']);
    why_die($lang['takeupload_no_formdata']);
}
if (!empty($url)) {
    preg_match('/(tt\d{7})/i', $url, $imdb);
    $imdb = !empty($imdb[1]) ? $imdb[1] : '';
}
$f = $_FILES['file'];
$fname = unesc($f['name']);
if (empty($fname)) {
    $session->set('is-warning', $lang['takeupload_no_filename']);
    why_die($lang['takeupload_no_filename']);
}

if ($uplver === 'yes') {
    $anonymous = 'yes';
    $anon = get_anonymous_name();
} else {
    $anonymous = 'no';
    $anon = $user['username'];
}

if (!empty($music)) {
    $genre = implode(',', $music);
} elseif (!empty($movie)) {
    $genre = implode(',', $movie);
} elseif (!empty($game)) {
    $genre = implode(',', $game);
} elseif (!empty($apps)) {
    $genre = implode(',', $apps);
}
$nfo = '';

if (!empty($_FILES['nfo']) && !empty($_FILES['nfo']['name'])) {
    $nfofile = $_FILES['nfo'];
    if ($nfofile['name'] == '') {
        $session->set('is-warning', $lang['takeupload_no_nfo']);
        why_die($lang['takeupload_no_nfo']);
    }
    if ($nfofile['size'] == 0) {
        $session->set('is-warning', $lang['takeupload_0_byte']);
        why_die($lang['takeupload_0_byte']);
    }
    if ($nfofile['size'] > $site_config['site']['nfo_size']) {
        $session->set('is-warning', $lang['takeupload_nfo_big']);
        why_die($lang['takeupload_nfo_big']);
    }
    $nfofilename = $nfofile['tmp_name'];
    if (@!is_uploaded_file($nfofilename)) {
        $session->set('is-warning', $lang['takeupload_nfo_failed']);
        why_die($lang['takeupload_nfo_failed']);
    }
    $nfo_content = str_ireplace([
        "\x0d\x0d\x0a",
        "\xb0",
    ], [
        "\x0d\x0a",
        '',
    ], file_get_contents($nfofilename));
    $nfo = $nfo_content;
    if ($strip) {
        $nfo = preg_replace('`/[^\\x20-\\x7e\\x0a\\x0d]`', ' ', $nfo);
        $nfo = preg_replace('`[\x00-\x08\x0b-\x0c\x0e-\x1f\x7f-\xff]`', '', $nfo);
    }
}

$free2 = 0;
if (!empty($free_length)) {
    if ($free_length === 255) {
        $free2 = 1;
    } elseif ($free_length === 42) {
        $free2 = 86400 + $dt;
    } else {
        $free2 = $dt + $free_length * 604800;
    }
}

$silver = 0;
if (!empty($half_length)) {
    if ($half_length === 255) {
        $silver = 1;
    } elseif ($half_length === 42) {
        $silver = 86400 + $dt;
    } else {
        $silver = $dt + $half_length * 604800;
    }
}

$freetorrent = !empty($freetorrent) && is_valid_id($freetorrent) ? (int) $freetorrent : 0;
if (!$descr && !empty($_FILES['nfo']) && !empty($_FILES['nfo']['name'])) {
    $descr = preg_replace('/[^\\x20-\\x7e\\x0a\\x0d]/', ' ', $nfo);
} else {
    $session->set('is-warning', $lang['takeupload_no_descr']);
    why_die($lang['takeupload_no_descr']);
}
if (!is_valid_id($catid)) {
    $session->set('is-warning', $lang['takeupload_no_cat']);
    why_die($lang['takeupload_no_cat']);
}
$subs = !empty($subs) ? implode('|', $subs) : '';
$release_group_array = [
    'scene' => 1,
    'p2p' => 1,
    'none' => 1,
];
$release_group = !empty($release_group) && !empty($release_group_array[$release_group]) ? $release_group : 'none';

if (!empty($youtube) && preg_match('#' . $site_config['youtube']['pattern'] . '#i', $youtube, $temp_youtube)) {
    $youtube = $temp_youtube[0];
}

if (!validfilename($fname)) {
    $session->set('is-warning', $lang['takeupload_invalid']);
    why_die($lang['takeupload_invalid']);
}

if (!empty($isbn)) {
    $isbn = str_replace([
        '-',
        ' ',
    ], '', $isbn);
}

if (!preg_match('/^(.+)\.torrent$/si', $fname, $matches)) {
    $session->set('is-warning', $lang['takeupload_not_torrent']);
    why_die($lang['takeupload_not_torrent']);
}
$shortfname = $torrent = $matches[1];
if (!empty($name)) {
    $torrent = unesc($name);
}
$tmpname = $f['tmp_name'];
if (!is_uploaded_file($tmpname)) {
    $session->set('is-warning', $lang['takeupload_eek']);
    why_die($lang['takeupload_eek']);
}
if (!filesize($tmpname)) {
    $session->set('is-warning', $lang['takeupload_no_file']);
    why_die($lang['takeupload_no_file']);
}
$dict = bencdec::decode_file($tmpname, $site_config['site']['max_torrent_size'], bencdec::OPTION_EXTENDED_VALIDATION);
if ($dict === false) {
    $session->set('is-warning', $lang['takeupload_what']);
    why_die($lang['takeupload_what']);
}
if (!empty($dict['announce-list'])) {
    unset($dict['announce-list']);
}
$dict['info']['private'] = 1;
if (empty($dict['info'])) {
    $session->set('is-warning', $lang['takeupload_empty_dict']);
    why_die($lang['takeupload_empty_dict']);
}
$info = &$dict['info'];
$infohash = pack('H*', sha1(bencdec::encode($info)));
$fluent = $container->get(Database::class);
$count = $fluent->from('torrents')
                ->select(null)
                ->select('COUNT(id) AS count')
                ->where('info_hash = ?', $infohash)
                ->fetch('count');

if ($count > 0) {
    $session->set('is-warning', $lang['takeupload_already']);
    why_die($lang['takeupload_already']);
}
if (bencdec::get_type($info) != 'dictionary') {
    $session->set('is-warning', $lang['takeupload_not_dict']);
    why_die($lang['takeupload_not_dict']);
}
if (empty($info['name']) || empty($info['piece length']) || empty($info['pieces'])) {
    $session->set('is-warning', $lang['takeupload_missing_parts']);
    why_die($lang['takeupload_missing_parts']);
}
if (bencdec::get_type($info['name']) != 'string' || bencdec::get_type($info['piece length']) != 'integer' || bencdec::get_type($info['pieces']) != 'string') {
    $session->set('is-warning', $lang['takeupload_invalid_types']);
    why_die($lang['takeupload_invalid_types']);
}
$dname = $info['name'];
$plen = $info['piece length'];
$pieces_len = strlen($info['pieces']);
if ($pieces_len % 20 != 0) {
    $session->set('is-warning', $lang['takeupload_pieces']);
    why_die($lang['takeupload_pieces']);
}
if ($plen % 4096) {
    $session->set('is-warning', $lang['takeupload_piece_size']);
    why_die($lang['takeupload_piece_size']);
}
$filelist = [];
if (!empty($info['length'])) {
    if (bencdec::get_type($info['length']) != 'integer') {
        $session->set('is-warning', $lang['takeupload_invalid']);
        why_die($lang['takeupload_invalid']);
    }
    $totallen = $info['length'];
    $filelist[] = [
        $dname,
        $totallen,
    ];
} else {
    if (empty($info['files'])) {
        $session->set('is-warning', $lang['takeupload_both']);
        why_die($lang['takeupload_both']);
    }
    if (bencdec::get_type($info['files']) != 'list') {
        $session->set('is-warning', $lang['takeupload_file_list']);
        why_die($lang['takeupload_file_list']);
    }
    $flist = &$info['files'];
    if (!count($flist)) {
        $session->set('is-warning', $lang['takeupload_no_files']);
        why_die($lang['takeupload_no_files']);
    }
    $totallen = 0;
    $fn = [
        'length' => 0,
        'path' => '',
    ];
    foreach ($flist as $fn) {
        if (empty($fn['length']) || empty($fn['path'])) {
            $session->set('is-warning', $lang['takeupload_no_info']);
            why_die($lang['takeupload_no_info']);
        }
        if (bencdec::get_type($fn['length']) != 'integer' || bencdec::get_type($fn['path']) != 'list') {
            $session->set('is-warning', $lang['takeupload_invalid_info']);
            why_die($lang['takeupload_invalid_info']);
        }
        $ll = $fn['length'];
        $ff = $fn['path'];
        $totallen += $ll;
        $ffa = [];
        foreach ($ff as $ffe) {
            if (bencdec::get_type($ffe) != 'string') {
                $session->set('is-warning', $lang['takeupload_type_error']);
                why_die($lang['takeupload_type_error']);
            }
            $ffa[] = $ffe;
        }
        if (!count($ffa)) {
            $session->set('is-warning', $lang['takeupload_error']);
            why_die($lang['takeupload_error']);
        }
        $ffe = implode('/', $ffa);
        $filelist[] = [
            $ffe,
            $ll,
        ];
    }
}

$num_pieces = $pieces_len / 20;
$expected_pieces = (int) ceil($totallen / $plen);
if ($num_pieces != $expected_pieces) {
    $session->set('is-warning', $lang['takeupload_no_match']);
    why_die($lang['takeupload_no_match']);
}

$tmaker = !empty($dict['created by']) && !empty($dict['created by']) ? $dict['created by'] : $lang['takeupload_unknown'];
$dict['comment'] = $lang['takeupload_agreement'];

$visible = 'no';
$torrent = str_replace('_', ' ', $torrent);
$vip = (!empty($vip) ? '1' : '0');

$values = [
    'isbn' => $isbn,
    'search_text' => searchfield("$shortfname $dname $torrent"),
    'filename' => $fname,
    'owner' => $owner_id,
    'visible' => $visible,
    'vip' => $vip,
    'release_group' => $release_group,
    'newgenre' => $genre,
    'poster' => $poster,
    'anonymous' => $anonymous,
    'allow_comments' => $allow_comments,
    'info_hash' => $infohash,
    'name' => $torrent,
    'size' => $totallen,
    'numfiles' => count($filelist),
    'offer' => $offer,
    'request' => $request,
    'url' => $url,
    'subs' => $subs,
    'descr' => $descr,
    'ori_descr' => $descr,
    'description' => $description,
    'category' => $catid,
    'free' => $free2,
    'silver' => $silver,
    'save_as' => $dname,
    'youtube' => $youtube,
    'tags' => $tags,
    'added' => $dt,
    'last_action' => $dt,
    'mtime' => $dt,
    'ctime' => $dt,
    'freetorrent' => $freetorrent,
    'nfo' => $nfo,
    'client_created_by' => $tmaker,
];
if (!empty($imdb)) {
    $values['imdb_id'] = $imdb;
}
$torrents_class = $container->get(Torrent::class);
$id = $torrents_class->add($values);

if (!$id) {
    $session->set('is-warning', $lang['takeupload_failed']);
    why_die($lang['takeupload_failed']);
}

$torrents_class->remove_torrent($infohash);
$torrents_class->get_torrent_from_hash($infohash);
$cache->delete('peers_' . $owner_id);
$peer_class = $container->get(Peer::class);
$peer_class->getPeersFromUserId($owner_id);
clear_image_cache();

if (!empty($uplver) && $uplver === 'yes') {
    $msg = "New Torrent : [url={$site_config['paths']['baseurl']}/details.php?id=$id&hit=1] [b][i]" . htmlsafechars($torrent) . '[/i][/b][/url] Uploaded by ' . get_anonymous_name();
} else {
    $msg = "New Torrent : [url={$site_config['paths']['baseurl']}/details.php?id=$id&hit=1] [b][i]" . htmlsafechars($torrent) . '[/i][/b][/url] Uploaded by ' . htmlsafechars($user['username']);
}
$messages = "{$site_config['site']['name']} New Torrent: $torrent Uploaded By: $anon " . mksize($totallen) . " {$site_config['paths']['baseurl']}/details.php?id=$id";
sql_query('DELETE FROM files WHERE torrent = ' . sqlesc($id)) or sqlerr(__FILE__, __LINE__);

/**
 * @param $arr
 * @param $id
 *
 * @throws NotFoundException
 * @throws DependencyException
 *
 * @return string
 */
function file_list($arr, $id)
{
    $new = [];
    foreach ($arr as $v) {
        $new[] = "($id," . sqlesc($v[0]) . ',' . $v[1] . ')';
    }

    return implode(',', $new);
}

sql_query('INSERT INTO files (torrent, filename, size) VALUES ' . file_list($filelist, $id)) or sqlerr(__FILE__, __LINE__);

$dir = TORRENTS_DIR . $id . '.torrent';
if (!bencdec::encode_file($dir, $dict)) {
    $session->set('is-warning', $lang['takeupload_encode_error']);
    why_die($lang['takeupload_encode_error']);
}
@unlink($tmpname);

if ($site_config['bonus']['on']) {
    $seedbonus = $user['seedbonus'];
    sql_query('UPDATE users SET seedbonus = seedbonus + ' . sqlesc($site_config['bonus']['per_upload']) . ', numuploads = numuploads + 1  WHERE id=' . sqlesc($owner_id)) or sqlerr(__FILE__, __LINE__);
    $update['seedbonus'] = ($seedbonus + $site_config['bonus']['per_upload']);
    $cache->update_row('user_' . $owner_id, [
        'seedbonus' => $update['seedbonus'],
    ], $site_config['expires']['user_cache']);
}
if ($site_config['site']['autoshout_chat'] || $site_config['site']['autoshout_irc']) {
    autoshout($msg);
    autoshout($msg, 2, 0);
}
$messages_class = $container->get(Message::class);
if ($offer > 0) {
    $res_offer = sql_query("SELECT user_id FROM offer_votes WHERE vote = 'yes' AND user_id != " . sqlesc($owner_id) . ' AND offer_id=' . sqlesc($offer)) or sqlerr(__FILE__, __LINE__);
    $subject = $lang['takeupload_offer_subject'];
    $msg = "Hi, \n An offer you were interested in has been uploaded!!! \n\n Click  [url=" . $site_config['paths']['baseurl'] . '/details.php?id=' . $id . ']' . htmlsafechars($torrent) . '[/url] to see the torrent details page!';
    while ($arr_offer = mysqli_fetch_assoc($res_offer)) {
        $msgs_buffer[] = [
            'receiver' => $arr_offer['user_id'],
            'added' => $dt,
            'msg' => $msg,
            'subject' => $subject,
        ];
    }
    if (!empty($msgs_buffer)) {
        $messages_class->insert($msgs_buffer);
    }
    write_log('Offered torrent ' . $id . ' (' . htmlsafechars($torrent) . ') was uploaded by ' . $user['username']);
    $filled = 1;
}
$filled = 0;
if ($request > 0) {
    $res_req = sql_query("SELECT user_id FROM request_votes WHERE vote = 'yes' AND request_id=" . sqlesc($request)) or sqlerr(__FILE__, __LINE__);
    $subject = $lang['takeupload_request_subject'];
    $msg = "Hi :D \n A request you were interested in has been uploaded!!! \n\n Click  [url=" . $site_config['paths']['baseurl'] . '/details.php?id=' . $id . ']' . htmlsafechars($torrent) . '[/url] to see the torrent details page!';
    while ($arr_req = mysqli_fetch_assoc($res_req)) {
        $msgs_buffer[] = [
            'receiver' => $arr_req['user_id'],
            'added' => $dt,
            'msg' => $msg,
            'subject' => $subject,
        ];
    }
    if (!empty($msgs_buffer)) {
        $messages_class->insert($msgs_buffer);
    }
    if ($site_config['bonus']['on']) {
        $set = [
            'seedbonus' => $update['seedbonus'] + $site_config['bonus']['per_request'],
        ];
        $users_class->update($set, $user['id']);
    }
    sql_query('UPDATE requests SET filled_by_user_id=' . sqlesc($owner_id) . ', filled_torrent_id=' . sqlesc($id) . ' WHERE id=' . sqlesc($request)) or sqlerr(__FILE__, __LINE__);
    sql_query('UPDATE usersachiev SET reqfilled = reqfilled + 1 WHERE userid=' . sqlesc($owner_id)) or sqlerr(__FILE__, __LINE__);
    write_log('Request for torrent ' . $id . ' (' . htmlsafechars($torrent) . ') was filled by ' . $user['username']);
    $filled = 1;
}
if ($filled == 0) {
    write_log(sprintf($lang['takeupload_log'], $id, $torrent, $user['username']));
}

$notify = $users_class->get_notifications($catid);
if (!empty($notify)) {
    $subject = $lang['takeupload_email_subject'];
    $msg = "A torrent in one of your default categories has been uploaded! \n\n Click  [url=" . $site_config['paths']['baseurl'] . '/details.php?id=' . $id . ']' . htmlsafechars($torrent) . '[/url] to see the torrent details page!';
    foreach ($notify as $notif) {
        file_put_contents('/var/log/nginx/email.log', $notif['notifs'] . PHP_EOL, FILE_APPEND);
        if ($site_config['mail']['smtp_enable'] && strpos($notif['notifs'], 'email') !== false) {
            $body = format_comment($msg);
            send_mail(strip_tags($notif['email']), $subject, $body, strip_tags($body));
        }
        if (strpos($notif['notifs'], 'pmail') !== false) {
            $msgs_buffer[] = [
                'receiver' => $notif['id'],
                'added' => $dt,
                'msg' => $msg,
                'subject' => $subject,
            ];
        }
    }
    if (!empty($msgs_buffer)) {
        $messages_class->insert($msgs_buffer);
    }
}

$cache->delete('user_upload_variables_' . $owner_id);
$session->set('is-success', $lang['takeupload_success']);
header("Location: {$site_config['paths']['baseurl']}/details.php?id=$id&uploaded=1");

/**
 * @param string $why
 */
function why_die(string $why)
{
    if (!empty($_SERVER['HTTP_REFERER'])) {
        header("Location: {$_SERVER['HTTP_REFERER']}");
        die();
    }
    die($why);
}
