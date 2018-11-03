<?php

function tvmaze_update($data)
{
    global $fluent, $BLOCKS;

    if (!$BLOCKS['tvmaze_api_on']) {
        return;
    }

    $max = $fluent->from('tvmaze')
        ->select(null)
        ->select('MAX(tvmaze_id) AS id')
        ->fetch('id');

    $pages[0] = floor($max / 250);
    $pages[1] = ceil($max / 250);

    $values = [];
    foreach ($pages as $page) {
        $url = "http://api.tvmaze.com/shows?page=$page";
        $json = fetch($url);
        if (empty($json)) {
            return false;
        }
        $shows = @json_decode($json, true);
        if ($shows) {
            foreach ($shows as $show) {
                if (!empty($show['id'])) {
                    $values[] = [
                        'name' => get_or_empty($show['name']),
                        'tvmaze_id' => get_or_empty($show['id']),
                        'tvrage_id' => get_or_empty($show['externals']['tvrage']),
                        'thetvdb_id' => get_or_empty($show['externals']['thetvdb']),
                        'imdb_id' => get_or_empty($show['externals']['imdb']),
                    ];
                }
            }
        }
    }
    if (!empty($values)) {
        $fluent->insertInto('tvmaze')
            ->values($values)
            ->ignore()
            ->execute();
    }

    if ($data['clean_log']) {
        write_log("TVMaze ID's Cleanup completed");
    }
}

function get_or_empty($param)
{
    if (!empty($param)) {
        return htmlsafechars($param);
    }

    return '';
}
