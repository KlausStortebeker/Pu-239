<?php

function write_info($text)
{
    global $fluent;

    $values = [
        'added' => TIME_NOW,
        'txt' => $text,
    ];
    $id = $fluent->insertInto('infolog')
        ->values($values)
        ->execute();

    return $id;
}
