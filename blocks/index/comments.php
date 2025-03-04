<?php

declare(strict_types = 1);
require_once INCL_DIR . 'function_html.php';

use Pu239\Comment;
use Pu239\Image;
use Pu239\User;

$user = check_user_status();
global $container, $lang, $site_config;

$comment = $container->get(Comment::class);
$comments = $comment->get_comments();
$posted_comments .= "
        <a id='latest_comment-hash'></a>
        <div id='latest_comment' class='box'>
            <div class='table-wrapper has-text-centered'>
                <table class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th class='has-text-centered w-10'>Type</th>
                            <th class='w-50 min-150'>Last 5 Comments</th>
                            <th class='has-text-centered'>User</th>
                            <th class='has-text-centered'>When</th>
                            <th class='has-text-centered'>Likes</th>
                        </tr>
                    </thead>
                    <tbody>";

$images_class = $container->get(Image::class);
$users_class = $container->get(User::class);
foreach ($comments as $comment) {
    $torrname = format_comment($comment['name']);
    $formatted = $anonymous === 'yes' ? 'Anonymous' : format_username((int) $comment['user']);
    if (empty($comment['poster']) && !empty($imdb_id)) {
        $comment['poster'] = $images_class->find_images($imdb_id);
    }
    $comment['poster'] = empty($comment['poster']) ? "<img src='{$site_config['paths']['images_baseurl']}noposter.png' class='tooltip-poster' alt=''>" : "<img src='" . url_proxy($comment['poster'], true, 250) . "' alt='' class='tooltip-poster'>";
    if ($anonymous === 'yes' && ($user['class'] < UC_STAFF || (int) $comment['owner'] === $user['id'])) {
        $uploader = '<span>' . get_anonymous_name() . '</span>';
    } else {
        $users_data = $users_class->getUserFromId((int) $comment['owner']);
        $username = !empty($users_data['username']) ? format_comment($users_data['username']) : 'unknown';
        $uploader = "<span class='" . get_user_class_name((int) $comment['class'], true) . "'>" . $username . '</span>';
    }

    $caticon = !empty($comment['image']) ? "<img src='{$site_config['paths']['images_baseurl']}caticons/" . get_category_icons() . '/' . $comment['image'] . "' class='tooltipper' alt='" . format_comment($comment['cat']) . "' title='" . format_comment($comment['cat']) . "' height='20px' width='auto'>" : format_comment($comment['cat']);

    $posted_comments .= "
                        <tr>
                            <td class='has-text-centered'>$caticon</td>
                            <td>";
    $block_id = "comment_id_{$comment['comment_id']}";
    $posted_comments .= torrent_tooltip(format_comment($comment['text']), $comment['id'], $block_id, $comment['name'], $comment['poster'], $uploader, $added, $size, $comment['seeders'], $comment['leechers'], $comment['imdb_id'], $comment['rating'], $comment['year'], $comment['subtitles'], $genre, false, $comment['comment_id']);
    $posted_comments .= "
                            <td class='has-text-centered'>$formatted</td>
                            <td class='has-text-centered'>" . get_date((int) $added, 'LONG') . "</td>
                            <td class='has-text-centered'>" . number_format($comment['user_likes']) . '</td>
                        </tr>';
}

if (count($comments) === 0) {
    $posted_comments .= "
                        <tr>
                            <td colspan='5'>No Comments Found</td>
                        </tr>";
}

$posted_comments .= '
                    </tbody>
                </table>
            </div>
        </div>';
