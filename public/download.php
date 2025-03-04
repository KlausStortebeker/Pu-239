<?php

declare(strict_types = 1);

use Pu239\Cache;
use Pu239\Database;
use Pu239\Session;
use Pu239\Snatched;
use Pu239\Torrent;
use Pu239\User;

require_once __DIR__ . '/../include/bittorrent.php';
require_once INCL_DIR . 'function_users.php';
require_once INCL_DIR . 'function_happyhour.php';
require_once INCL_DIR . 'function_password.php';
require_once CLASS_DIR . 'class.bencdec.php';
$lang = array_merge(load_language('global'), load_language('download'));
global $container, $site_config;

$users_class = $container->get(User::class);
$fluent = $container->get(Database::class);
$torrent_class = $container->get(Torrent::class);
$session = $container->get(Session::class);
$cache = $container->get(Cache::class);
$T_Pass = isset($_GET['torrent_pass']) && strlen($_GET['torrent_pass']) === 64 ? $_GET['torrent_pass'] : '';
if (!empty($T_Pass)) {
    $user = $users_class->get_user_from_torrent_pass($T_Pass);
    if (!$user) {
        die($lang['download_passkey']);
    } elseif ($user['enabled'] === 'no') {
        die("Permission denied, you're account is disabled");
    } elseif ($user['parked'] === 'yes') {
        die("Permission denied, you're account is parked");
    }
} else {
    $user = check_user_status();
}
$id = isset($_GET['torrent']) ? (int) $_GET['torrent'] : 0;
$usessl = $session->get('scheme') === 'http' ? 'http' : 'https';
$zipuse = isset($_GET['zip']) && $_GET['zip'] == 1 ? true : false;
$text = isset($_GET['text']) && $_GET['text'] == 1 ? true : false;
if (!is_valid_id($id)) {
    stderr($lang['download_user_error'], $lang['download_no_id']);
}
$row = $torrent_class->get($id);
$fn = TORRENTS_DIR . $id . '.torrent';
if (!$row || !is_file($fn) || !is_readable($fn)) {
    stderr('Err', 'There was an error with the file or with the query, please contact staff', 'bottom20');
}
if (($user['downloadpos'] === 0 || $user['can_leech'] === 0 || $user['downloadpos'] > 1 || $user['suspended'] === 'yes') && !$user['id'] === $row['owner']) {
    stderr('Error', 'Your download rights have been disabled.', 'bottom20');
}
if ($user['seedbonus'] === 0 || $user['seedbonus'] < $site_config['bonus']['per_download']) {
    stderr('Error', "You don't have enough karma[seedbonus] to download, trying seeding back some torrents =]", 'bottom20');
}
if ($site_config['site']['require_credit'] && ($row['size'] > ($user['uploaded'] - $user['downloaded']))) {
    stderr('Error', "You don't have enough upload credit to download, trying seeding back some torrents =]", 'bottom20');
}
if ($row['vip'] === 1 && $user['class'] < UC_VIP) {
    stderr('VIP Access Required', 'You must be a VIP In order to view details or download this torrent! You may become a Vip By Donating to our site. Donating ensures we stay online to provide you more Vip-Only Torrents!', 'bottom20');
}
if (happyHour('check') && happyCheck('checkid', $row['category']) && $site_config['bonus']['happy_hour']) {
    $multiplier = happyHour('multiplier');
    happyLog($user['id'], $id, $multiplier);
    $values = [
        ';userid' => $user['id'],
        'torrentid' => $id,
        'multiplier' => $multiplier,
    ];
    $fluent->insertInto('happyhour')
           ->values($values)
           ->execute();
}
if ($site_config['bonus']['on'] && $row['owner'] != $user['id']) {
    $downloaded = $cache->get('downloaded_' . $user['id'] . '_' . $id);
    if ($downloaded === false || is_null($downloaded)) {
        $snatched = $container->get(Snatched::class);
        $has_snatched = $snatched->get_snatched($user['id'], $id);
        if (!$has_snatched) {
            $cache->set('downloaded_' . $user['id'] . '_' . $id, 'downloaded');
            $update = [
                'seedbonus' => $user['seedbonus'] - $site_config['bonus']['per_download'],
            ];
            $users_class->update($update, $user['id']);
        }
    }
}
$update = [
    'hits' => $row['hits'] + 1,
];
$torrent_class->update($update, $id);

if (isset($_GET['slot'])) {
    $added = TIME_NOW + 14 * 86400;
    $slot = $fluent->from('freeslots')
                   ->where('torrentid = ?', $id)
                   ->where('userid = ?', $user['id'])
                   ->fetch();
    $used_slot = $slot['torrentid'] === $id && $slot['userid'] === $user['id'];
    if ($_GET['slot'] === 'free') {
        if ($used_slot && $slot['free'] === 'yes') {
            stderr('Doh!', 'Freeleech slot already in use.', 'bottom20');
        }
        if ($user['freeslots'] < 1) {
            stderr('Doh!', 'No Freeslots left.', 'bottom20');
        }
        $update = [
            'freeslots' => $user['freeslots'] - 1,
        ];
        $users_class->update($update, $user['id']);
        if ($used_slot && $slot['doubleup'] === 'yes') {
            $update = [
                'free' => 'yes',
                'addedfree' => $added,
            ];
            $fluent->update('freeslots')
                   ->set($update)
                   ->where('torrentid = ?', $id)
                   ->where('userid = ?', $user['id'])
                   ->where('doubleup = ?', 'yes')
                   ->execute();
        } else {
            $values = [
                'torrentid' => $id,
                'userid' => $user['id'],
                'free' => 'yes',
                'addedfree' => $added,
            ];
            $fluent->insertInto('freeslots')
                   ->values($values)
                   ->execute();
        }
    } elseif ($_GET['slot'] === 'double') {
        if ($used_slot && $slot['doubleup'] === 'yes') {
            stderr('Doh!', 'Doubleseed slot already in use.', 'bottom20');
        }
        if ($user['freeslots'] < 1) {
            stderr('Doh!', 'No Doubleseed Slots left.', 'bottom20');
        }
        $update = [
            'freeslots' => $user['freeslots'] - 1,
        ];
        $users_class->update($update, $user['id']);
        if ($used_slot && $slot['free'] === 'yes') {
            $update = [
                'doubleup' => 'yes',
                'addedfree' => $added,
            ];
            $fluent->update('freeslots')
                   ->set($update)
                   ->where('torrentid = ?', $id)
                   ->where('userid = ?', $user['id'])
                   ->where('free = ?', 'yes')
                   ->execute();
        } else {
            $values = [
                'torrentid' => $id,
                'userid' => $user['id'],
                'doubleup' => 'yes',
                'addedfree' => $added,
            ];
            $fluent->insertInto('freeslots')
                   ->values($values)
                   ->execute();
        }
    } else {
        stderr('ERROR', 'What\'s up doc?', 'bottom20');
    }
    make_freeslots($user['id'], 'fllslot_', true);
}
$cache->deleteMulti([
    'top_torrents_',
    'latest_torrents_',
    'scroller_torrents_',
    'slider_torrents_',
    'staff_picks_',
    'motw_',
]);

$dict = bencdec::decode_file($fn, $site_config['site']['max_torrent_size']);
$dict['announce'] = $site_config['announce_urls'][$usessl][0] . '?torrent_pass=' . $user['torrent_pass'];
$dict['uid'] = (int) $user['id'];
$tor = bencdec::encode($dict);
if ($zipuse) {
    $row['name'] = str_replace([
        ' ',
        '.',
        '-',
    ], '_', $row['name']);
    $file_name = TORRENTS_DIR . $row['name'] . '.torrent';
    if (file_put_contents($file_name, $tor)) {
        $files = $file_name;
        $zipfile = TORRENTS_DIR . $row['name'] . '.zip';
        $zip = $container->get(ZipArchive::class);
        $zip->open($zipfile, ZipArchive::CREATE);
        $zip->addFromString($zipfile, $tor);
        $zip->close();
        $zip->force_download($zipfile);
        unlink($zipfile);
        unlink($file_name);
    } else {
        stderr('Error', 'Can\'t create the new file, please contact staff', 'bottom20');
    }
} else {
    if ($text) {
        header('Content-Disposition: attachment; filename="[' . $site_config['site']['name'] . ']' . $row['name'] . '.txt"');
        header('Content-Type: text/plain');
        echo $tor;
    } else {
        header('Content-Disposition: attachment; filename="[' . $site_config['site']['name'] . ']' . $row['filename'] . '"');
        header('Content-Type: application/x-bittorrent');
        echo $tor;
    }
}
