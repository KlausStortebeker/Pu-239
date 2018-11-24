<?php

global $lang;

$colour = $topicpoll = $topic_status_image = '';
$HTMLOUT .= $mini_menu . '<h1 class="has-text-centered">' . $lang['vup_unread_post_since_visit'] . '</h1>';
$time = $readpost_expiry;
$res_count = sql_query('SELECT t.id, t.last_post FROM topics AS t LEFT JOIN posts AS p ON t.last_post = p.id LEFT JOIN forums AS f ON f.id = t.forum_id WHERE ' . ($CURUSER['class'] < UC_STAFF ? 'p.status = \'ok\' AND t.status = \'ok\' AND' : ($CURUSER['class'] < $min_delete_view_class ? 'p.status != \'deleted\' AND t.status != \'deleted\'  AND' : '')) . ' f.min_class_read <= ' . $CURUSER['class'] . ' AND p.added > ' . $time) or sqlerr(__FILE__, __LINE__);
$count = 0;
while ($arr_count = mysqli_fetch_assoc($res_count)) {
    $res_post_read = sql_query('SELECT last_post_read FROM read_posts WHERE user_id = ' . sqlesc($CURUSER['id']) . ' AND topic_id = ' . sqlesc($arr_count['id'])) or sqlerr(__FILE__, __LINE__);
    $arr_post_read = mysqli_fetch_row($res_post_read);
    if ($arr_post_read[0] < $arr_count['last_post']) {
        ++$count;
    }
}
if ($count === 0) {
    $heading = '
            <tr>
                <th>
                    ' . $lang['fe_no_unread_posts'] . '
                </th>
            </tr>';
    $body = '
            <tr>
                <td>
                    ' . $lang['fe_you_are_uptodate_topics'] . '.<br><br>
                </td>
            </tr>';
    $HTMLOUT .= main_table($body, $heading);
} else {
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 0;
    $perpage = isset($_GET['perpage']) ? (int) $_GET['perpage'] : 20;
    $link = $site_config['baseurl'] . '/forums.php?action=view_unread_posts&amp;' . (isset($_GET['perpage']) ? "perpage={$perpage}&amp;" : '');
    $pager = pager($perpage, $count, $link);
    $menu_top = $pager['pagertop'];
    $menu_bottom = $pager['pagerbottom'];
    $LIMIT = $pager['limit'];

    $res_unread = sql_query('SELECT t.id AS topic_id, t.topic_name AS topic_name, t.last_post, t.post_count, t.views, t.topic_desc, t.locked, t.sticky, t.poll_id, t.forum_id, t.rating_sum, t.num_ratings, t.status, t.anonymous AS tan, f.name AS forum_name, f.description AS forum_desc, p.post_title, p.body, p.icon, p.user_id, p.anonymous AS pan, u.id, u.username, u.class, u.donor, u.suspended, u.warned, u.enabled, u.chatpost, u.leechwarn, u.pirate, u.king FROM topics AS t LEFT JOIN posts AS p ON t.last_post = p.id LEFT JOIN forums AS f ON f.id = t.forum_id LEFT JOIN users AS u ON u.id = t.user_id WHERE ' . ($CURUSER['class'] < UC_STAFF ? 'p.status = \'ok\' AND t.status = \'ok\' AND' : ($CURUSER['class'] < $min_delete_view_class ? 'p.status != \'deleted\' AND t.status != \'deleted\'  AND' : '')) . ' f.min_class_read <= ' . $CURUSER['class'] . ' AND p.added > ' . $time . ' ORDER BY t.last_post DESC ' . $LIMIT) or sqlerr(__FILE__, __LINE__);
    $HTMLOUT .= ($count > $perpage ? $menu_top : '');
    $heading = '
        <tr>
            <th><img src="' . $site_config['pic_baseurl'] . 'forums/topic.gif" class="icon tooltipper" alt="' . $lang['fe_topic'] . '" title="' . $lang['fe_topic'] . '"></th>
            <th><img src="' . $site_config['pic_baseurl'] . 'forums/topic_normal.gif" class="icon tooltipper" alt=' . $lang['fe_thread_icon'] . '" title=' . $lang['fe_thread_icon'] . '"></th>
            <th>' . $lang['fe_new_posts'] . '!</th>
            <th>' . $lang['fe_replies'] . '</th>
            <th>' . $lang['fe_views'] . '</th>
            <th>' . $lang['fe_started_by'] . '</th>
        </tr>';
    $body = '';
    while ($arr_unread = mysqli_fetch_assoc($res_unread)) {
        $res_post_read = sql_query('SELECT last_post_read FROM read_posts WHERE user_id = ' . sqlesc($CURUSER['id']) . ' AND topic_id = ' . sqlesc($arr_unread['topic_id'])) or sqlerr(__FILE__, __LINE__);
        $arr_post_read = mysqli_fetch_row($res_post_read);
        if ($arr_post_read[0] < $arr_unread['last_post']) {
            //=== topic status
            $topic_status = htmlsafechars($arr_unread['status']);
            switch ($topic_status) {
                case 'ok':
                    $topic_status_image = '';
                    break;

                case 'recycled':
                    $topic_status_image = '<img src="' . $site_config['pic_baseurl'] . 'forums/recycle_bin.gif" class="icon tooltipper" alt="' . $lang['fe_recycled'] . '" title="' . $lang['fe_this_thread_is_currently'] . ' ' . $lang['fe_in_the_recycle_bin'] . '">';
                    break;

                case 'deleted':
                    $topic_status_image = '<img src="' . $site_config['pic_baseurl'] . 'forums/delete_icon.gif" class="icon tooltipper" alt="' . $lang['fe_deleted'] . '" title="' . $lang['fe_this_thread_is_currently'] . ' ' . $lang['fe_deleted'] . '">';
                    break;
            }
            $locked = $arr_unread['locked'] === 'yes';
            $sticky = $arr_unread['sticky'] === 'yes';
            $topic_poll = $arr_unread['poll_id'] > 0;
            $first_unread_poster = sql_query('SELECT added FROM posts WHERE topic_id = ' . sqlesc($arr_unread['topic_id']) . ' ORDER BY id ASC LIMIT 1') or sqlerr(__FILE__, __LINE__);
            $first_unread_poster_arr = mysqli_fetch_row($first_unread_poster);
            if ($arr_unread['tan'] === 'yes') {
                if ($CURUSER['class'] < UC_STAFF && $arr_unread['user_id'] != $CURUSER['id']) {
                    $thread_starter = (!empty($arr_unread['username']) ? '<i>' . get_anonymous_name() . '</i>' : '' . $lang['fe_lost'] . ' [' . (int) $arr_unread['id'] . ']') . '<br>' . get_date($first_unread_poster_arr[0], '');
                } else {
                    $thread_starter = (!empty($arr_unread['username']) ? '<i>' . get_anonymous_name() . '</i> [' . format_username($arr_unread['id']) . ']' : '' . $lang['fe_lost'] . ' [' . (int) $arr_unread['id'] . ']') . '<br>' . get_date($first_unread_poster_arr[0], '');
                }
            } else {
                $thread_starter = (!empty($arr_unread['username']) ? format_username($arr_unread['id']) : '' . $lang['fe_lost'] . ' [' . (int) $arr_unread['id'] . ']') . '<br>' . get_date($first_unread_poster_arr[0], '');
            }
            $topicpic = ($arr_unread['post_count'] < 30 ? ($locked ? 'lockednew' : 'topicnew') : ($locked ? 'lockednew' : 'hot_topic_new'));
            $rpic = ($arr_unread['num_ratings'] != 0 ? ratingpic_forums(round($arr_unread['rating_sum'] / $arr_unread['num_ratings'], 1)) : '');
            $did_i_post_here = sql_query('SELECT user_id FROM posts WHERE user_id = ' . sqlesc($CURUSER['id']) . ' AND topic_id = ' . sqlesc($arr_unread['topic_id'])) or sqlerr(__FILE__, __LINE__);
            $posted = (mysqli_num_rows($did_i_post_here) > 0 ? 1 : 0);
            $sub = sql_query('SELECT user_id FROM subscriptions WHERE user_id = ' . sqlesc($CURUSER['id']) . ' AND topic_id = ' . sqlesc($arr_unread['topic_id'])) or sqlerr(__FILE__, __LINE__);
            $subscriptions = (mysqli_num_rows($sub) > 0 ? 1 : 0);
            $icon = (empty($arr_unread['icon']) ? '<img src="' . $site_config['pic_baseurl'] . 'forums/topic_normal.gif" class="icon tooltipper" alt="' . $lang['fe_topic'] . '" title="' . $lang['fe_topic'] . '">' : '<img src="' . $site_config['pic_baseurl'] . 'smilies/' . htmlsafechars($arr_unread['icon']) . '.gif" class="icon tooltipper" alt="' . $lang['fe_unread'] . '" title="' . $lang['fe_unread'] . '">');
            $first_post_text = bubble(' <img src="' . $site_config['pic_baseurl'] . 'forums/mg.gif" class="icon tooltipper" alt="' . $lang['fe_preview'] . '">', format_comment($arr_unread['body'], true, true, false), '' . $lang['fe_last_post'] . ' ' . $lang['fe_preview'] . '');
            $topic_name = ($sticky ? '<img src="' . $site_config['pic_baseurl'] . 'forums/pinned.gif" class="icon tooltipper" alt="' . $lang['fe_pinned'] . '" title="' . $lang['fe_pinned'] . '"> ' : ' ') . ($topicpoll ? '<img src="' . $site_config['pic_baseurl'] . 'forums/poll.gif" class="icon tooltipper" alt="' . $lang['fe_poll'] . '" title="' . $lang['fe_poll'] . '"> ' : ' ') . ' <a class="altlink" href="' . $site_config['baseurl'] . '/forums.php?action=view_topic&amp;topic_id=' . (int) $arr_unread['topic_id'] . '" title="' . $lang['fe_1st_post_in_tread'] . '">' . htmlsafechars($arr_unread['topic_name'], ENT_QUOTES) . '</a><a class="altlink" href="' . $site_config['baseurl'] . '/forums.php?action=view_topic&amp;topic_id=' . (int) $arr_unread['topic_id'] . '&amp;page=0#' . (int) $arr_post_read[0] . '" title="' . $lang['fe_1st_unread_post_topic'] . '"><img src="' . $site_config['pic_baseurl'] . 'forums/last_post.gif" class="icon tooltipper" alt="' . $lang['fe_last_post'] . '" title="' . $lang['fe_last_post'] . '"></a>' . ($posted ? '<img src="' . $site_config['pic_baseurl'] . 'forums/posted.gif" class="icon tooltipper" alt="Posted" title="Posted"> ' : ' ') . ($subscriptions ? '<img src="' . $site_config['pic_baseurl'] . 'forums/subscriptions.gif" class="icon tooltipper" alt="' . $lang['fe_subscribed'] . '" title="' . $lang['fe_subscribed'] . '"> ' : ' ') . ' <img src="' . $site_config['pic_baseurl'] . 'forums/new.gif" class="icon tooltipper" alt="' . $lang['fe_new_post_in_topic'] . '!" title="' . $lang['fe_new_post_in_topic'] . '!">';
            $body .= '
            <tr>
                <td><img src="' . $site_config['pic_baseurl'] . 'forums/' . $topicpic . '.gif" class="icon tooltipper" alt="' . $lang['fe_topic'] . '" title="' . $lang['fe_topic'] . '"></td>
                <td>' . $icon . '</td>
                <td>
                    <table>
                        <tr>
                            <td>' . $topic_name . $first_post_text . ' [ <a class="altlink" href="' . $site_config['baseurl'] . '/forums.php?action=clear_unread_post&amp;topic_id=' . (int) $arr_unread['topic_id'] . '&amp;last_post=' . (int) $arr_unread['last_post'] . '" title="' . $lang['fe_remove'] . ' ' . $lang['vup_remove_topic_from_unread_list'] . '.">' . $lang['fe_remove'] . '</a> ] ' . $topic_status_image . '</td>
                            <td>' . $rpic . '</td>
                        </tr>
                    </table>
                    ' . (!empty($arr_unread['topic_desc']) ? '&#9658; <span style="font-size: x-small;">' . htmlsafechars($arr_unread['topic_desc'], ENT_QUOTES) . '</span>' : '') . '
                    <hr>in: <a class="altlink" href="' . $site_config['baseurl'] . '/forums.php?action=view_forum&amp;forum_id=' . (int) $arr_unread['forum_id'] . '">' . htmlsafechars($arr_unread['forum_name'], ENT_QUOTES) . '</a>
                    ' . (!empty($arr_unread['topic_desc']) ? ' [ <span style="font-size: x-small;">' . htmlsafechars($arr_unread['topic_desc'], ENT_QUOTES) . '</span> ]' : '') . '
                </td>
                <td>' . number_format($arr_unread['post_count']) . '</td>
                <td>' . number_format($arr_unread['views']) . '</td>
                <td>' . $thread_starter . '</td>
            </tr>';
        }
    }
    $HTMLOUT .= main_table($body, $heading) . ($count > $perpage ? $menu_bottom : '');
}
