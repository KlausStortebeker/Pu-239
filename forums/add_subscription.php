<?php

declare(strict_types = 1);

global $CURUSER, $lang, $site_config;

$topic_id = isset($_GET['topic_id']) ? (int) $_GET['topic_id'] : (isset($_POST['topic_id']) ? (int) $_POST['topic_id'] : 0);
$forum_id = isset($_GET['forum_id']) ? (int) $_GET['forum_id'] : (isset($_POST['forum_id']) ? (int) $_POST['forum_id'] : 0);
//=== first see if they are being norty...
$norty_res = sql_query('SELECT min_class_read FROM forums WHERE id = ' . sqlesc($forum_id)) or sqlerr(__FILE__, __LINE__);
$norty_arr = mysqli_fetch_row($norty_res);
if (!is_valid_id($topic_id) || $norty_arr[0] > $CURUSER['class'] || !is_valid_id($forum_id)) {
    stderr($lang['gl_error'], $lang['gl_bad_id']);
}
//=== see if they are subscribed already
$res = sql_query('SELECT id FROM subscriptions WHERE user_id = ' . sqlesc($CURUSER['id']) . ' AND topic_id = ' . sqlesc($topic_id)) or sqlerr(__FILE__, __LINE__);
$arr = mysqli_fetch_row($res);
if ($arr[0] > 0) {
    stderr($lang['gl_error'], $lang['fe_you_already_subscib']);
}
//=== ok, that the hell, let's add it \o/
sql_query('INSERT INTO `subscriptions` (`user_id`, `topic_id`) VALUES (' . sqlesc($CURUSER['id']) . ', ' . sqlesc($topic_id) . ')') or sqlerr(__FILE__, __LINE__);
header('Location: ' . $_SERVER['PHP_SELF'] . '?action=view_topic&topic_id=' . $topic_id . '&s=1');
die();
