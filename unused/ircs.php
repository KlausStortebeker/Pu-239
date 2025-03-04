<?php

declare(strict_types = 1);

require_once __DIR__ . '/include/bittorrent.php';
require_once INCL_DIR . 'function_users.php';
require_once INCL_DIR . 'function_onlinetime.php';
$password = 'adlsadladadll'; // same as in staff.tcl;
$hash = 'adlsadladadll'; // same as in staff.tcl;
$modclass = '4'; // minumum staff class;
/**
 * @param $val
 *
 * @return string
 */
function calctime($val)
{
    $days = (int) ($val / 86400);
    $val -= $days * 86400;
    $hours = (int) ($val / 3600);
    $val -= $hours * 3600;
    $mins = (int) ($val / 60);
    //$secs = $val - ($mins * 60);

    return "$days days, $hours hrs, $mins minutes";
}

if ((isset($_GET['pass']) && $_GET['pass'] == $password) && (isset($_GET['hash']) && $_GET['hash'] == $hash)) {
    $seedingbonus = 0;
    $meinvite = $whominvite = [];
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
        $query = 'username = ' . sqlesc("$search") . " AND status='confirmed'";
        $res = sql_query("SELECT * FROM users WHERE $query ORDER BY username") or sqlerr(__FILE__, __LINE__);
        $num = mysqli_num_rows($res);
        if ($num < 1) {
            echo $search . ' - No such user, please try again.';
        }
        if ($num > 0) {
            $arr = mysqli_fetch_assoc($res);
            $id = isset($arr['id']) ? (int) $arr['id'] : 0;
            $seedingbonus = isset($arr['seedbonus']) ? (int) $arr['seedbonus'] : 0;
            $username = htmlsafechars($arr['username']);
            if (isset($_GET['func']) && $_GET['func'] === 'stats') {
                $ratio = (($arr['downloaded'] > 0) ? ($arr['uploaded'] / $arr['downloaded']) : '0.00');
                $lastseen = htmlsafechars($arr['last_access']);
                echo htmlsafechars($arr['username']) . ' - Uploaded: (' . mksize($arr['uploaded']) . ') - Downloaded: (' . mksize($arr['downloaded']) . ') - Ratio: (' . number_format($ratio, 2) . ') - Invites: (' . (int) $arr['invites'] . ') - Joined: (' . get_date((int) $arr['added'], 'DATE', 0, 1) . '' . ') - Online time: (' . time_return($arr['onlinetime']) . ') - Last Seen: (' . get_date((int) $lastseen, 'DATE', 0, 1) . ')';
            } elseif (isset($_GET['func']) && $_GET['func'] === 'check') {
                echo htmlsafechars($arr['username']) . ' - Seedbonus: (' . number_format((float) $arr['seedbonus'], 1) . ')';
            } elseif (isset($_GET['func']) && $_GET['func'] === 'ircbonus') {
                $ircbonus = (!empty($arr['irctotal']) ? number_format($arr['irctotal'] / ($site_config['irc']['autoclean_interval'] * 4), 1) : '0.0');
                echo $arr['username'] . ' - IRC Bonus: (' . $ircbonus . ')';
            } elseif (isset($_GET['func']) && $_GET['func'] === 'irctotal') {
                $irctotal = (!empty($arr['irctotal']) ? calctime($arr['irctotal']) : $arr['username'] . ' has never been on IRC!');
                echo $arr['username'] . ' - IRC Total: (' . $irctotal . ')';
            } elseif (isset($_GET['func']) && $_GET['func'] === 'connectable') {
                $res5 = sql_query('SELECT connectable FROM peers WHERE userid=' . sqlesc($arr['id'])) or sqlerr(__FILE__, __LINE__);
                if ($row = mysqli_fetch_row($res5)) {
                    $connect = $row[0];
                    if ($connect === 'yes') {
                        $connectable = 'Yes - ' . $username . ' is connectable';
                    } else {
                        $connectable = 'No - ' . $username . ' is not connectable';
                    }
                } else {
                    $connectable = 'Waiting - ' . $username . ' has an unknown connection';
                }
                echo $connectable;
            } elseif (isset($_GET['func']) && $_GET['func'] === 'online') {
                $dt = TIME_NOW - 180;
                $lastseen = isset($arr['last_access']) ? $arr['last_access'] : 0;
                if (!empty($lastseen)) {
                    $seen = (($lastseen >= $dt) ? $username . ' is Online' : $username . ' is Offline');
                } else {
                    '' . $username . ' has never been active';
                }
                echo $seen;
            } elseif (isset($_GET['func']) && $_GET['func'] === 'flushtorrents') {
                sql_query('DELETE FROM peers WHERE userid=' . $id) or sqlerr(__FILE__, __LINE__);
                $cache->delete('peers_' . $id);
                echo $username . 's torrents have been flushed';
            }
        }
    } elseif (isset($_GET['setusername'])) {
        $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
        $mod = isset($_GET['mod']) ? sqlesc($_GET['mod']) : '';
        $newname = isset($_GET['newname']) ? sqlesc($_GET['newname']) : '';
        $res = sql_query("SELECT id FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $nsetusername = mysqli_fetch_assoc($res);
        $res2 = sql_query("SELECT id FROM users WHERE username = $newname LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $nnewname = mysqli_fetch_assoc($res2);
        $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
        if ($nsetusername < 1) {
            echo $who . ' - No such user or is staff, please try again.';
        } else {
            if ($nnewname) {
                echo $newname . ' - Is taken, please try again.';
            } else {
                $modd = isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '';
                $newusername = isset($_GET['newname']) ? htmlsafechars($_GET['newname']) : '';
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's name was changed from: ' . $who . ' to ' . $newusername . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET username = $newname, modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $nsetusername['id'], [
                    'username' => $newname,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's name was changed from: ' . $who . ' to ' . $newusername . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['topirc'])) {
        $res = sql_query("SELECT id, username, class, irctotal FROM users WHERE onirc = 'yes' GROUP BY class ORDER BY irctotal DESC") or sqlerr(__FILE__, __LINE__);
        while ($arr = mysqli_fetch_assoc($res)) {
            $ircbonus = (!empty($arr['irctotal']) ? number_format($arr['irctotal'] / ($site_config['irc']['autoclean_interval'] * 4), 1) : '0.0');
            $ircusers = isset($ircusers) ? $ircusers : '';
            if ($ircusers) {
                $ircusers .= ",\n";
            }
            $arr['username'] = '' . get_user_class_name((int) $arr['class']) . ' Leader is : ' . $arr['username'] . '(' . $ircbonus . ')';
            $ircusers .= $arr['username'];
        }
        if (!isset($ircusers)) {
            $ircusers = 'wtf!';
        }
        echo $ircusers;
    } elseif (isset($_GET['torrents'])) {
        $res = sql_query("SELECT COUNT(id) FROM torrents WHERE visible='yes'") or sqlerr(__FILE__, __LINE__);
        $row = mysqli_fetch_array($res, MYSQLI_NUM);
        $count = $row[0];
        echo '-' . $count . ' torrents found';
    } elseif (isset($_GET['includedead'])) {
        $res = sql_query('SELECT COUNT(id) FROM torrents') or sqlerr(__FILE__, __LINE__);
        $row = mysqli_fetch_array($res, MYSQLI_NUM);
        $count = $row[0];
        echo '-' . $count . ' torrents found';
    } elseif (isset($_GET['onlydead'])) {
        $res = sql_query("SELECT COUNT(id) FROM torrents WHERE visible='no'") or sqlerr(__FILE__, __LINE__);
        $row = mysqli_fetch_array($res, MYSQLI_NUM);
        $count = $row[0];
        echo '-' . $count . ' torrents found';
    } elseif (isset($_GET['noseeds'])) {
        $res = sql_query("SELECT COUNT(id) FROM torrents WHERE seeders = '0'") or sqlerr(__FILE__, __LINE__);
        $row = mysqli_fetch_array($res, MYSQLI_NUM);
        $count = $row[0];
        echo '-' . $count . ' torrents found';
    } elseif (isset($_GET['func']) && $_GET['func'] === 'add') {
        if (isset($_GET['bonus'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $res = sql_query("SELECT id, seedbonus FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $nbonus = mysqli_fetch_assoc($res);
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            if ($nbonus < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldbonus = $nbonus['seedbonus'];
                $amount = isset($_GET['amount']) ? (int) ($_GET['amount']) : '';
                sql_query('UPDATE users SET seedbonus = seedbonus+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $nbonus['id'], [
                    'seedbonus' => $nbonus['seedbonus'] + $amount,
                ], $site_config['expires']['user_cache']);
                $res1 = sql_query("SELECT seedbonus FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $obonus = mysqli_fetch_assoc($res1);
                $newbonus = $obonus['seedbonus'];
                echo $who . 's Karma was changed from: ' . $oldbonus . ' to ' . $newbonus;
            }
        } elseif (isset($_GET['invites'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $res3 = sql_query("SELECT id, invites FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $ninvites = mysqli_fetch_assoc($res3);
            if ($ninvites < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldinvites = (int) $ninvites['invites'];
                $amount = isset($_GET['amount']) && $_GET['amount'] > 0 ? (int) $_GET['amount'] : '';
                sql_query('UPDATE users SET invites = invites+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $ninvites['id'], [
                    'invites' => $ninvites['invites'] + $amount,
                ], $site_config['expires']['user_cache']);
                $res4 = sql_query("SELECT invites FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $oinvites = mysqli_fetch_assoc($res4);
                $newinvites = (int) $oinvites['invites'];
                echo $who . 's Invites were changed from: ' . $oldinvites . ' to ' . $newinvites;
            }
        } elseif (isset($_GET['freeslots'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $res5 = sql_query("SELECT id, freeslots FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $nfreeslots = mysqli_fetch_assoc($res5);
            if ($nfreeslots < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldfreeslots = (int) $nfreeslots['freeslots'];
                $amount = isset($_GET['amount']) && $_GET['amount'] > 0 ? (int) $_GET['amount'] : 0;
                sql_query('UPDATE users SET freeslots = freeslots+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $nfreeslots['id'], [
                    'freeslots' => $nfreeslots['freeslots'] + $amount,
                ], $site_config['expires']['user_cache']);
                $res6 = sql_query("SELECT freeslots FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $ofreeslots = mysqli_fetch_assoc($res6);
                $newfreeslots = (int) $ofreeslots['freeslots'];
                echo $who . 's Freeslots were changed from: ' . $oldfreeslots . ' to ' . $newfreeslots;
            }
        } elseif (isset($_GET['reputation'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $res3 = sql_query("SELECT id, reputation FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $nreputation = mysqli_fetch_assoc($res3);
            if ($nreputation < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldreputation = (int) $nreputation['reputation'];
                $amount = isset($_GET['amount']) && $_GET['amount'] > 0 ? (int) $_GET['amount'] : 0;
                sql_query('UPDATE users SET reputation = reputation+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $nreputation['id'], [
                    'reputation' => $nreputation['reputation'] + $amount,
                ], $site_config['expires']['user_cache']);
                $res4 = sql_query("SELECT reputation FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $oreputation = mysqli_fetch_assoc($res4);
                $newreputation = (int) $oreputation['reputation'];
                echo $who . 's Reputation was changed from: ' . $oldreputation . ' to ' . $newreputation;
            }
        }
    } elseif (isset($_GET['func']) && $_GET['func'] === 'rem') {
        if (isset($_GET['bonus'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $res = sql_query("SELECT id, seedbonus FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $nbonus = mysqli_fetch_assoc($res);
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            if ($nbonus < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldbonus = $nbonus['seedbonus'];
                $amount = isset($_GET['amount']) ? number_format((int) $_GET['amount']) : 0;
                sql_query('UPDATE users SET seedbonus = seedbonus-' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $nbonus['id'], [
                    'seedbonus' => $nbonus['seedbonus'] - $amount,
                ], $site_config['expires']['user_cache']);
                $res1 = sql_query("SELECT seedbonus FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $obonus = mysqli_fetch_assoc($res1);
                $newbonus = $obonus['seedbonus'];
                echo $who . 's Karma was changed from: ' . $oldbonus . ' to ' . $newbonus;
            }
        } elseif (isset($_GET['invites'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $res3 = sql_query("SELECT id, invites FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $ninvites = mysqli_fetch_assoc($res3);
            if ($ninvites < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldinvites = (int) $ninvites['invites'];
                $amount = isset($_GET['amount']) && $_GET['amount'] > 0 ? (int) $_GET['amount'] : 0;
                sql_query('UPDATE users SET invites = invites-' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $ninvites['id'], [
                    'invites' => $ninvites['invites'] - $amount,
                ], $site_config['expires']['user_cache']);
                $res4 = sql_query("SELECT invites FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $oinvites = mysqli_fetch_assoc($res4);
                $newinvites = (int) $oinvites['invites'];
                echo $who . 's Invites were changed from: ' . $oldinvites . ' to ' . $newinvites;
            }
        } elseif (isset($_GET['freeslots'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $res5 = sql_query("SELECT id, freeslots FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $nfreeslots = mysqli_fetch_assoc($res5);
            if ($nfreeslots < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldfreeslots = (int) $nfreeslots['freeslots'];
                $amount = isset($_GET['amount']) && $_GET['amount'] > 0 ? (int) $_GET['amount'] : 0;
                sql_query('UPDATE users SET freeslots = freeslots-' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $nfreeslots['id'], [
                    'freeslots' => $nfreeslots['freeslots'] - $amount,
                ], $site_config['expires']['user_cache']);
                $res6 = sql_query("SELECT freeslots FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $ofreeslots = mysqli_fetch_assoc($res6);
                $newfreeslots = (int) $ofreeslots['freeslots'];
                echo $who . 's Freeslots were changed from: ' . $oldfreeslots . ' to ' . $newfreeslots;
            }
        } elseif (isset($_GET['reputation'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $res5 = sql_query("SELECT id, reputation FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $nreputation = mysqli_fetch_assoc($res5);
            if ($nreputation < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $oldreputation = (int) $nreputation['reputation'];
                $amount = isset($_GET['amount']) && $_GET['amount'] > 0 ? (int) $_GET['amount'] : 0;
                sql_query('UPDATE users SET reputation = reputation-' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $nreputation['id'], [
                    'reputation' => $nreputation['reputation'] - $amount,
                ], $site_config['expires']['user_cache']);
                $res6 = sql_query("SELECT reputation FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                $oreputation = mysqli_fetch_assoc($res6);
                $newreputation = (int) $oreputation['reputation'];
                echo $who . 's Reputation was changed from: ' . $oldreputation . ' to ' . $newreputation;
            }
        }
    } elseif (isset($_GET['func']) && $_GET['func'] === 'check') {
        echo $username . 's  - Seedbonus: (' . number_format($seedingbonus, 1) . ')';
    } elseif (isset($_GET['func']) && $_GET['func'] === 'give') {
        if (isset($_GET['bonus'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $me = isset($_GET['me']) ? sqlesc($_GET['me']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $m = isset($_GET['me']) ? htmlsafechars($_GET['me']) : '';
            $res9 = sql_query("SELECT id, seedbonus FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $mebonus = mysqli_fetch_assoc($res9);
            $res99 = sql_query("SELECT id, seedbonus FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $whombonus = mysqli_fetch_assoc($res99);
            if ($whombonus < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $meoldbonus = $mebonus['seedbonus'];
                $whomoldbonus = $whombonus['seedbonus'];
                $amount = isset($_GET['amount']) && ($_GET['amount'] > 0) ? (int) ($_GET['amount']) : 0;
                if ($amount <= $meoldbonus) {
                    sql_query('UPDATE users SET seedbonus = seedbonus-' . sqlesc($amount) . " WHERE username = $me") or sqlerr(__FILE__, __LINE__);
                    sql_query('UPDATE users SET seedbonus = seedbonus+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                    $cache->update_row('user_' . $mebonus['id'], [
                        'seedbonus' => $mebonus['seedbonus'] - $amount,
                    ], $site_config['expires']['user_cache']);
                    $cache->update_row('user_' . $whombonus['id'], [
                        'seedbonus' => $whombonus['seedbonus'] + $amount,
                    ], $site_config['expires']['user_cache']);
                    $res1 = sql_query("SELECT seedbonus FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $meobonus = mysqli_fetch_assoc($res1);
                    $res2 = sql_query("SELECT seedbonus FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $whomobonus = mysqli_fetch_assoc($res2);
                    $newmebonus = $meobonus['seedbonus'];
                    $newwhombonus = $whomobonus['seedbonus'];
                    echo $who . 's Karma was changed from: ' . $whomoldbonus . ' to ' . $newwhombonus, ' and ' . $m . 's Karma was changed from: ' . $meoldbonus . ' to ' . $newmebonus;
                } else {
                    echo 'insufficient funds';
                }
            }
        } elseif (isset($_GET['freeslots'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $me = isset($_GET['me']) ? sqlesc($_GET['me']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $m = isset($_GET['me']) ? htmlsafechars($_GET['me']) : '';
            $res9 = sql_query("SELECT id, freeslots FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $mefreeslots = mysqli_fetch_assoc($res9);
            $res99 = sql_query("SELECT id, freeslots FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $whomfreeslots = mysqli_fetch_assoc($res99);
            if ($whomfreeslots < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $meoldfreeslots = $mefreeslots['freeslots'];
                $whomoldfreeslots = $whomfreeslots['freeslots'];
                $amount = isset($_GET['amount']) && ($_GET['amount'] > 0) ? (int) ($_GET['amount']) : 0;
                if ($amount <= $meoldfreeslots) {
                    sql_query('UPDATE users SET freeslots = freeslots-' . sqlesc($amount) . " WHERE username = $me") or sqlerr(__FILE__, __LINE__);
                    sql_query('UPDATE users SET freeslots = freeslots+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                    $cache->update_row('user_' . $mefreeslots['id'], [
                        'freeslots' => $mefreeslots['freeslots'] - $amount,
                    ], $site_config['expires']['user_cache']);
                    $cache->update_row('user_' . $whomfreeslots['id'], [
                        'freeslots' => $whomfreeslots['freeslots'] + $amount,
                    ], $site_config['expires']['user_cache']);
                    $res1 = sql_query("SELECT freeslots FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $meofreeslots = mysqli_fetch_assoc($res1);
                    $res2 = sql_query("SELECT freeslots FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $whomofreeslots = mysqli_fetch_assoc($res2);
                    $newmefreeslots = $meofreeslots['freeslots'];
                    $newwhomfreeslots = $whomofreeslots['freeslots'];
                    echo $who . 's Freeslots were changed from: ' . $whomoldfreeslots . ' to ' . $newwhomfreeslots, ' and ' . $m . 's Freeslots were changed from: ' . $meoldfreeslots . ' to ' . $newmefreeslots;
                } else {
                    echo 'insufficient funds';
                }
            }
        } elseif (isset($_GET['reputation'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $me = isset($_GET['me']) ? sqlesc($_GET['me']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $m = isset($_GET['me']) ? htmlsafechars($_GET['me']) : '';
            $res9 = sql_query("SELECT id, reputation FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $mereputation = mysqli_fetch_assoc($res9);
            $res99 = sql_query("SELECT id, reputation FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $whomreputation = mysqli_fetch_assoc($res99);
            if ($whomreputation < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $meoldreputation = $mereputation['reputation'];
                $whomoldreputation = $whomreputation['reputation'];
                $amount = isset($_GET['amount']) && ($_GET['amount'] > 0) ? (int) ($_GET['amount']) : 0;
                if ($amount <= $meoldreputation) {
                    sql_query('UPDATE users SET reputation = reputation-' . sqlesc($amount) . " WHERE username = $me") or sqlerr(__FILE__, __LINE__);
                    sql_query('UPDATE users SET reputation = reputation+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                    $cache->update_row('user_' . $mereputation['id'], [
                        'reputation' => $mereputation['reputation'] - $amount,
                    ], $site_config['expires']['user_cache']);
                    $cache->update_row('user_' . $whomreputation['id'], [
                        'reputation' => $whomreputation['reputation'] + $amount,
                    ], $site_config['expires']['user_cache']);
                    $res1 = sql_query("SELECT reputation FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $meoreputation = mysqli_fetch_assoc($res1);
                    $res2 = sql_query("SELECT reputation FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $whomoreputation = mysqli_fetch_assoc($res2);
                    $newmereputation = $meoreputation['reputation'];
                    $newwhomreputation = $whomoreputation['reputation'];
                    echo $who . 's Reputation were changed from: ' . $whomoldreputation . ' to ' . $newwhomreputation, ' and ' . $m . 's Reputation were changed from: ' . $meoldreputation . ' to ' . $newmereputation;
                } else {
                    echo 'insufficient funds';
                }
            }
        } elseif (isset($_GET['invites'])) {
            $whom = isset($_GET['whom']) ? sqlesc($_GET['whom']) : '';
            $me = isset($_GET['me']) ? sqlesc($_GET['me']) : '';
            $who = isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '';
            $m = isset($_GET['me']) ? htmlsafechars($_GET['me']) : '';
            $res9 = sql_query("SELECT id, invites FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $meinvites = mysqli_fetch_assoc($res9);
            $res99 = sql_query("SELECT id, invites FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $whominvites = mysqli_fetch_assoc($res99);
            if ($whominvites < 1) {
                echo $who . ' - No such user, please try again.';
            } else {
                $meoldinvites = $meinvites['invites'];
                $whomoldinvites = $whominvites['invites'];
                $amount = (isset($_GET['amount']) && ($_GET['amount'] > 0) ? (int) ($_GET['amount']) : '');
                if ($amount <= $meoldinvites) {
                    sql_query('UPDATE users SET invites = invites-' . sqlesc($amount) . " WHERE username = $me") or sqlerr(__FILE__, __LINE__);
                    sql_query('UPDATE users SET invites = invites+' . sqlesc($amount) . " WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                    $cache->update_row('user_' . $meinvite['id'], [
                        'invite' => $meinvite['invite'] - $amount,
                    ], $site_config['expires']['user_cache']);
                    $cache->update_row('user_' . $whominvite['id'], [
                        'invite' => $whominvite['invite'] + $amount,
                    ], $site_config['expires']['user_cache']);
                    $res1 = sql_query("SELECT invites FROM users WHERE username = $me LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $meoinvites = mysqli_fetch_assoc($res1);
                    $res2 = sql_query("SELECT invites FROM users WHERE username = $whom LIMIT 1") or sqlerr(__FILE__, __LINE__);
                    $whomoinvites = mysqli_fetch_assoc($res2);
                    $newmeinvites = $meoinvites['invites'];
                    $newwhominvites = $whomoinvites['invites'];
                    echo $who . 's Invites were changed from: ' . $whomoldinvites . ' to ' . $newwhominvites, ' and ' . $m . 's Invites were changed from: ' . $meoldinvites . ' to ' . $newmeinvites;
                } else {
                    echo 'insufficient funds';
                }
            }
        }
    } elseif (isset($_GET['uploadpos'])) {
        if ((isset($_GET['toggle']) && $_GET['toggle'] == 1) || (isset($_GET['toggle']) && $_GET['toggle'] == 0)) {
            $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
            $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
            $res = sql_query("SELECT id, uploadpos FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $upos = mysqli_fetch_assoc($res);
            if ($upos < 1) {
                echo $who . ' - No such user or is staff, please try again.';
            } else {
                $newpos = (isset($upos['uploadpos']) ? htmlsafechars($upos['uploadpos']) : '');
                $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
                $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's uploadpos changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET uploadpos = '$toggle', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $upos['id'], [
                    'uploadpos' => $toggle,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's uploadpos changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['downloadpos'])) {
        if ((isset($_GET['toggle']) && $_GET['toggle'] == 1) || (isset($_GET['toggle']) && $_GET['toggle'] == 0)) {
            $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
            $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
            $res = sql_query("SELECT id, downloadpos FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $dpos = mysqli_fetch_assoc($res);
            if ($dpos < 1) {
                echo $who . ' - No such user or is staff, please try again.';
            } else {
                $newpos = (isset($dpos['downloadpos']) ? htmlsafechars($dpos['downloadpos']) : '');
                $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
                $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's downloadpos changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET downloadpos = '$toggle', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $dpos['id'], [
                    'downloadpos' => $toggle,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's downloadpos changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['forum_post'])) {
        if ((isset($_GET['toggle']) && $_GET['toggle'] === 'yes') || (isset($_GET['toggle']) && $_GET['toggle'] === 'no')) {
            $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
            $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
            $res = sql_query("SELECT id, forum_post FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $fpos = mysqli_fetch_assoc($res);
            if ($fpos < 1) {
                echo $who . ' - No such user or is staff, please try again.';
            } else {
                $newpos = (isset($fpos['forum_post']) ? htmlsafechars($fpos['forum_post']) : '');
                $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
                $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's forumpost changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET forum_post = '$toggle', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $fpos['id'], [
                    'forum_post' => $toggle,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's forumpost changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['chatpost'])) {
        if ((isset($_GET['toggle']) && $_GET['toggle'] == 1) || (isset($_GET['toggle']) && $_GET['toggle'] == 0)) {
            $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
            $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
            $res = sql_query("SELECT id, chatpost FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $cpos = mysqli_fetch_assoc($res);
            if ($cpos < 1) {
                echo $who . ' - No such user or is staff, please try again.';
            } else {
                $newpos = (isset($cpos['chatpost']) ? htmlsafechars($cpos['chatpost']) : '');
                $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
                $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's chatpost changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET chatpost = '$toggle', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $cpos['id'], [
                    'chatpost' => $toggle,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's chatpost changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['avatarpos'])) {
        if ((isset($_GET['toggle']) && $_GET['toggle'] == 1) || (isset($_GET['toggle']) && $_GET['toggle'] == 0)) {
            $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
            $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
            $res = sql_query("SELECT id, avatarpos FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $apos = mysqli_fetch_assoc($res);
            if ($apos < 1) {
                echo $who . ' - No such user or is staff, please try again.';
            } else {
                $newpos = (isset($apos['avatarpos']) ? htmlsafechars($apos['avatarpos']) : '');
                $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
                $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's avatarpos changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET avatarpos = '$toggle', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $apos['id'], [
                    'avatarpos' => $toggle,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's avatarpos changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['invite_rights'])) {
        if ((isset($_GET['toggle']) && $_GET['toggle'] === 'yes') || (isset($_GET['toggle']) && $_GET['toggle'] === 'no')) {
            $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
            $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
            $res = sql_query("SELECT id, invite_rights FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $ipos = mysqli_fetch_assoc($res);
            if ($ipos < 1) {
                echo $who . ' - No such user or is staff, please try again.';
            } else {
                $newpos = (isset($ipos['invite_on']) ? htmlsafechars($ipos['invite_on']) : '');
                $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
                $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's invite rights changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET invite_rights = '$toggle', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $ipos['id'], [
                    'invite_rights' => $toggle,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's invite rights changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['enabled'])) {
        if ((isset($_GET['toggle']) && $_GET['toggle'] === 'yes') || (isset($_GET['toggle']) && $_GET['toggle'] === 'no')) {
            $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
            $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
            $res = sql_query("SELECT id, enabled FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
            $epos = mysqli_fetch_assoc($res);
            if ($epos < 1) {
                echo $who . ' - No such user or is staff, please try again.';
            } else {
                $newpos = (isset($epos['enabled']) ? htmlsafechars($epos['enabled']) : '');
                $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
                $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
                $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's enabled changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd . "\n");
                sql_query("UPDATE users SET enabled = '$toggle', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
                $cache->update_row('user_' . $epos['id'], [
                    'enabled' => $toggle,
                    'modcomment' => $modcomment,
                ], $site_config['expires']['user_cache']);
                echo $who . 's enabled changed from: ' . $newpos . ' to ' . $toggle . ' by ' . $modd;
            }
        }
    } elseif (isset($_GET['addsupport'])) {
        //if((isset($_GET['toggle']) && $_GET['toggle'] == "yes") || (isset($_GET['toggle']) && $_GET['toggle'] == "no")){
        $whom = (isset($_GET['whom']) ? sqlesc($_GET['whom']) : '');
        $who = (isset($_GET['whom']) ? htmlsafechars($_GET['whom']) : '');
        $res = sql_query("SELECT id, support, supportfor FROM users WHERE username = $whom AND class < $modclass LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $support = mysqli_fetch_assoc($res);
        if ($support < 1) {
            echo $who . ' - No such user or is staff, please try again.';
        } else {
            $newsupp = (isset($support['support']) ? htmlsafechars($support['support']) : '');
            $modd = (isset($_GET['mod']) ? htmlsafechars($_GET['mod']) : '');
            $supportfors = (isset($_GET['supportfor']) ? htmlsafechars($_GET['supportfor']) : '');
            $toggle = (isset($_GET['toggle']) ? htmlsafechars($_GET['toggle']) : '');
            $modcomment = sqlesc(get_date((int) TIME_NOW, 'DATE', 1) . ' IRC: ' . $who . 's support changed by ' . $modd . "\n");
            sql_query("UPDATE users SET support = 'yes', supportfor ='$supportfors', modcomment = CONCAT($modcomment,modcomment) WHERE username = $whom") or sqlerr(__FILE__, __LINE__);
            $cache->update_row('user_' . $support['id'], [
                'support' => 'yes',
                'supportfor' => $supportfors,
                'modcomment' => $modcomment,
            ], $site_config['expires']['user_cache']);
            echo $who . 's support changed added to First line support to cover ' . $supportfors . ' by ' . $modd;
        }
    }
    //} from ' . $newsupp . ' to '. $toggle . ', //== from: " . $newsupp . " to ". $toggle . "
} else {
    die('your actions have been logged!');
}
