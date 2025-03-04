<?php

declare(strict_types = 1);

use Pu239\Database;
use Pu239\User;

require_once __DIR__ . '/../include/bittorrent.php';
require_once INCL_DIR . 'function_users.php';
require_once INCL_DIR . 'function_html.php';
require_once INCL_DIR . 'function_torrenttable.php';
require_once INCL_DIR . 'function_pager.php';
require_once INCL_DIR . 'function_searchcloud.php';
require_once CLASS_DIR . 'class_user_options.php';
require_once CLASS_DIR . 'class_user_options_2.php';
$user = check_user_status();
global $container, $site_config;

$users_class = $container->get(User::class);
$fluent = $container->get(Database::class);
$hide_simple = '';
$hide_advanced = "class='hidden'";
$today = isset($_GET['today']) ? $_GET['today'] : 0;
unset($_GET['today']);
if (!empty($_GET)) {
    if (!empty($_GET['sns'])) {
        unset($_GET['incldead'], $_GET['vip'], $_GET['only_free'], $_GET['unsnatched'], $_GET['sna'], $_GET['sd'], $_GET['sg'], $_GET['so'], $_GET['sys'], $_GET['sye'], $_GET['srs'], $_GET['sre'], $_GET['si'], $_GET['ss'], $_GET['sp'], $_GET['spf'], $_GET['st'], $_GET['sr']);
    } else {
        unset($_GET['sns']);
        $hide_simple = 'hidden';
        $hide_advanced = '';
    }
}
if (isset($_GET['clear_new']) && $_GET['clear_new'] == 1) {
    $set = [
        'last_browse' => TIME_NOW,
    ];
    $users_class->update($set, $user['id']);
    header("Location: {$site_config['paths']['baseurl']}/browse.php");
    die();
}

$count = $fluent->from('torrents AS t')
                ->select(null)
                ->select('COUNT(t.id) AS count');

$query = $fluent->from('torrents AS t')
                ->select("IF(t.num_ratings < {$site_config['site']['minvotes']}, NULL, ROUND(t.rating_sum / t.num_ratings, 1)) AS user_rating")
                ->select('u.username')
                ->select('u.class')
                ->select('f.doubleup AS doubleslot')
                ->select('f.free AS freeslot')
                ->select('f.addedup')
                ->select('f.addedfree')
                ->leftJoin('users AS u ON t.owner = u.id')
                ->leftJoin('freeslots AS f ON t.id = f.torrentid AND u.id = ?', $user['id']);

$HTMLOUT = $addparam = $new_button = $title = '';
$stdfoot = [
    'js' => [
        get_file_name('browse_js'),
        get_file_name('bookmarks_js'),
        get_file_name('categories_js'),
    ],
];

$lang = array_merge(load_language('global'), load_language('browse'), load_language('torrenttable_functions'), load_language('bookmark'));
$valid_search = [
    'sns',
    'sna',
    'sd',
    'sg',
    'so',
    'sys',
    'sye',
    'srs',
    'sre',
    'si',
    'ss',
    'sp',
    'spf',
    'sr',
    'st',
];
if (isset($_GET['sort'], $_GET['type'])) {
    $column = $ascdesc = '';
    $_valid_sort = [
        'id',
        'name',
        'numfiles',
        'comments',
        'added',
        'size',
        'times_completed',
        'seeders',
        'leechers',
        'owner',
    ];
    $column = isset($_GET['sort'], $_valid_sort[(int) $_GET['sort']]) ? $_valid_sort[(int) $_GET['sort']] : $_valid_sort[0];
    switch (htmlsafechars($_GET['type'])) {
        case 'asc':
            $ascdesc = 'ASC';
            $linkascdesc = 'asc';
            break;

        case 'desc':
            $ascdesc = 'DESC';
            $linkascdesc = 'desc';
            break;

        default:
            $ascdesc = 'DESC';
            $linkascdesc = 'desc';
            break;
    }
    $query->orderBy("t.{$column} $ascdesc");
    $pagerlink = 'sort=' . (int) $_GET['sort'] . "&amp;type={$linkascdesc}&amp;";
} else {
    $query->orderBy('t.staff_picks DESC')
          ->orderBy('t.sticky')
          ->orderBy('t.added DESC');
    $pagerlink = '';
}

if ($today) {
    $count->where('t.added >= ?', strtotime('today midnight'));
    $query->where('t.added >= ?', strtotime('today midnight'));
    $addparam .= 'today=1&amp;';
    $today = 1;
}

$queryed = !empty($_GET['incldead']) ? (int) $_GET['incldead'] : '';
if ($queryed === 1) {
    $addparam .= 'incldead=1&amp;';
    if (!isset($user) || $user['class'] < UC_ADMINISTRATOR) {
        $count->where('t.banned != "yes"');
        $query->where('t.banned != "yes"');
    }
} else {
    if ($queryed === 2) {
        $addparam .= 'incldead=2&amp;';
        $count->where('t.visible = "no"');
        $query->where('t.visible = "no"');
    } else {
        $count->where('t.visible = "yes"');
        $query->where('t.visible = "yes"');
    }
}

if (isset($_GET['only_free']) && $_GET['only_free'] == 1) {
    $count->where('t.free >= 1');
    $query->where('t.free >= 1');
    $addparam .= 'only_free=1&amp;';
}
if (isset($_GET['vip'])) {
    if ($_GET['vip'] == 2) {
        $count->where('t.vip = 1');
        $query->where('t.vip = 1');
    } elseif ($_GET['vip'] == 1) {
        $count->where('t.vip = 0');
        $query->where('t.vip = 0');
    }
    $addparam .= "vip={$_GET['vip']}&amp;";
}
if (isset($_GET['unsnatched']) && $_GET['unsnatched'] == 1) {
    $count->where('s.to_go IS NULL')
          ->leftJoin('snatched AS s on s.torrentid = t.id AND s.userid = ?', $user['id']);
    $query->select('IF(s.to_go IS NOT NULL, (t.size - s.to_go) / t.size, -1) AS to_go')
          ->leftJoin('snatched AS s on s.torrentid = t.id AND s.userid = ?', $user['id'])
          ->having('to_go = -1');
    $addparam .= 'unsnatched=1&amp;';
} else {
    $query->select('IF(s.to_go IS NOT NULL, (t.size - s.to_go) / t.size, -1) AS to_go')
          ->leftJoin('snatched AS s on s.torrentid = t.id AND s.userid = ?', $user['id']);
}

$cats = [];
if (isset($_GET['cats'])) {
    if (is_array($_GET['cats'])) {
        $cats = $_GET['cats'];
    } else {
        $cats = explode(',', $_GET['cats']);
    }
}

if (!empty($cats)) {
    $addparam .= 'cats=' . implode(',', $cats) . '&amp;';
    $count->where('t.category', $cats);
    $query->where('t.category', $cats);
}

foreach ($valid_search as $search) {
    if (!empty($_GET[$search])) {
        $cleaned = searchfield($_GET[$search]);
        if (!empty($_POST['search']) && ($search === 'sns' || $search === 'sna')) {
            $cleaned = searchfield($_POST['search']);
        }
        $title .= " $cleaned";
        $insert_cloud = [
            'sns',
            'sna',
            'sd',
            'si',
            'ss',
            'spf',
            'sp',
            'sg',
            'sr',
        ];
        if (in_array($search, $insert_cloud)) {
            $column = str_replace([
                'sns',
                'sna',
                'sd',
                'si',
                'ss',
                'spf',
                'sp',
                'sg',
                'sr',
            ], [
                'name',
                'name',
                'descr',
                'imdb',
                'isbn',
                'fuzzy',
                'person',
                'genre',
                'role',
            ], $search);
            searchcloud_insert($cleaned, $column);
        }
        $addparam .= "{$search}=" . urlencode((string) $cleaned) . '&amp;';
        if ($search === 'sns') {
            $count->where('name LIKE ?', "%$cleaned%");
            $query->where('name LIKE ?', "%$cleaned%");
        } else {
            if ($search === 'sna') {
                $count->where('MATCH (t.name) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
                $query->where('MATCH (t.name) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
            }
            if ($search === 'sd') {
                $count->where('MATCH (search_text, descr) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
                $query->where('MATCH (search_text, descr) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
            }
            if ($search === 'sg') {
                $count->where('MATCH (newgenre) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
                $query->where('MATCH (newgenre) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
            }
            if ($search === 'so') {
                $count->where('u.username = ?', $cleaned);
                $query->where('u.username = ?', $cleaned);
            }
            if ($search === 'sys') {
                $count->where('t.year >= ?', (int) $_GET['sys']);
                $query->where('t.year >= ?', (int) $_GET['sys']);
            }
            if ($search === 'sye') {
                $count->where('t.year <= ?', (int) $_GET['sye']);
                $query->where('t.year <= ?', (int) $_GET['sye']);
            }
            if ($search === 'srs') {
                $count->where('t.rating >= ?', (float) $_GET['srs']);
                $query->where('t.rating >= ?', (float) $_GET['srs']);
            }
            if ($search === 'sre') {
                $count->where('t.rating <= ?', (float) $_GET['sre']);
                $query->where('t.rating <= ?', (float) $_GET['sre']);
            }
            if ($search === 'si') {
                $imdb = preg_match('/(tt\d{7})/', $cleaned, $match);
                if (!empty($match[1])) {
                    $count->where('t.imdb_id = ?', $match[1]);
                    $query->where('t.imdb_id = ?', $match[1]);
                }
            }
            if ($search === 'ss') {
                $isbn = preg_match('/\d{7,10}/', $cleaned, $match);
                if (!empty($match[1])) {
                    $count->where('t.isbn = ?', $match[1]);
                    $query->where('t.isbn = ?', $match[1]);
                }
            }
            if ($search === 'sp') {
                $count->where('p.name = ?', $cleaned)
                      ->innerJoin('imdb_person AS i ON t.imdb_id = CONCAT("tt", i.imdb_id)')
                      ->innerJoin('person AS p ON i.person_id = p.imdb_id');
                $query->where('p.name = ?', $cleaned)
                      ->innerJoin('imdb_person AS i ON t.imdb_id = CONCAT("tt", i.imdb_id)')
                      ->innerJoin('person AS p ON i.person_id = p.imdb_id');
            }
            if ($search === 'spf') {
                $count->where('MATCH (p.name) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned)
                      ->innerJoin('imdb_person AS i ON t.imdb_id = CONCAT("tt", i.imdb_id)')
                      ->innerJoin('person AS p ON i.person_id = p.imdb_id');
                $query->where('MATCH (p.name) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned)
                      ->innerJoin('imdb_person AS i ON t.imdb_id = CONCAT("tt", i.imdb_id)')
                      ->innerJoin('person AS p ON i.person_id = p.imdb_id');
            }
            if ($search === 'sr') {
                $count->where('MATCH (r.name) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned)
                      ->innerJoin('imdb_role AS r ON t.imdb_id = CONCAT("tt", r.imdb_id)');
                $query->where('MATCH (r.name) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned)
                      ->innerJoin('imdb_role AS r ON t.imdb_id = CONCAT("tt", r.imdb_id)');
            }
            if ($search === 'st') {
                $subs = explode(' ', $cleaned);
                foreach ($subs as $sub) {
                    $count->where('MATCH (t.subs) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
                    $query->where('MATCH (t.subs) AGAINST (? IN NATURAL LANGUAGE MODE)', $cleaned);
                }
            }
        }
    }
}

if (!empty($title)) {
    $title = $lang['browse_search'] . $title;
}
$count = $count->fetch('count');
$torrentsperpage = $user['torrentsperpage'];
if (!$torrentsperpage) {
    $torrentsperpage = 25;
}
if ($count > 0) {
    if ($addparam != '') {
        if ($pagerlink != '') {
            if ($addparam[strlen($addparam) - 1] != ';') {
                $addparam = $addparam . '&amp;' . $pagerlink;
            } else {
                $addparam = $addparam . $pagerlink;
            }
        }
    } else {
        $addparam = $pagerlink;
    }
    $pager = pager($torrentsperpage, $count, "{$site_config['paths']['baseurl']}/browse.php?" . $addparam);
    $query = $query->limit($pager['pdo']['limit'])
          ->offset($pager['pdo']['offset'])
          ->fetchAll();
}
if ($user['opt1'] & user_options::VIEWSCLOUD) {
    $HTMLOUT .= main_div("<div class='cloud has-text-centered round10 padding20'>" . cloud() . '</div>', 'bottom20');
}

$HTMLOUT .= "
                                <form id='catsids' method='get' action='{$site_config['paths']['baseurl']}/browse.php' accept-charset='utf-8'>";
if ($today) {
    $HTMLOUT .= "
                                    <input type='hidden' name='today' value='$today'>";
}

require_once PARTIALS_DIR . 'categories.php';

if ($user['opt1'] & user_options::CLEAR_NEW_TAG_MANUALLY) {
    $new_button = "
        <a href='{$site_config['paths']['baseurl']}/browse.php?clear_new=1'><input type='submit' value='clear new tag' class='button is-small'></a>
        <br>";
} else {
    $set = [
        'last_browse' => TIME_NOW,
    ];
    $users_class->update($set, $user['id']);
}

$vip = ((isset($_GET['vip'])) ? (int) $_GET['vip'] : '');
$vip_box = "
                    <select name='vip' class='w-100'>
                        <option value='0'>{$lang['browse_include_vip']}</option>
                        <option value='1'" . ($vip == 1 ? ' selected' : '') . ">{$lang['browse_no_vip']}</option>
                        <option value='2'" . ($vip == 2 ? ' selected' : '') . ">{$lang['browse_only_vip']}</option>
                    </select>";

$deadcheck = "
                    <select name='incldead' class='w-100'>
                        <option value='0'>{$lang['browse_active']}</option>
                        <option value='1'" . ($queryed == 1 ? ' selected' : '') . ">{$lang['browse_inc_dead']}</option>
                        <option value='2'" . ($queryed == 2 ? ' selected' : '') . ">{$lang['browse_dead']}</option>
                    </select>";

$only_free = ((isset($_GET['only_free'])) ? (int) $_GET['only_free'] : '');
$only_free_box = "
                    <select name='only_free' class='w-100'>
                        <option value='0'>{$lang['browse_all_free']}</option>
                        <option value='1'" . ($only_free == 1 ? ' selected' : '') . ">{$lang['browse_only_free']}</option>
                    </select>";

$unsnatched = ((isset($_GET['unsnatched'])) ? (int) $_GET['unsnatched'] : '');
$unsnatched_box = "
                    <select name='unsnatched' class='w-100'>
                        <option value='0'>{$lang['browse_all']}</option>
                        <option value='1'" . ($unsnatched == 1 ? ' selected' : '') . ">{$lang['browse_all_unsnatched']}</option>
                    </select>";

$HTMLOUT .= main_div("
                <div id='simple' class='has-text-centered w-50 $hide_simple'>
                    <div class='has-text-centered padding20 level-center-center is-wrapped'>
                        <span class='right10'>{$lang['browse_name']}</span>
                        <input id='search_sim' name='sns' type='text' placeholder='{$lang['search_name']}' class='search w-100 margin20' value='" . (!empty($_GET['sns']) ? $_GET['sns'] : '') . "' onkeyup='autosearch(event)'>
                        <span class='left10'>
                            <input type='submit' value='{$lang['search_search_btn']}' class='button is-small'>
                        </span>
                        <span id='simple_btn' class='left10 button is-small' onclick='toggle_search()'>{$lang['search_toggle_simple_btn']}</span>
                    </div>
                </div>
                <div id='advanced' $hide_advanced>
                    <div class='padding20 w-100'>
                        <div class='columns'>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_name']}</div>
                                <input name='sna' type='text' placeholder='{$lang['search_name_fuzzy']}' class='search w-100' value='" . (!empty($_GET['sna']) ? $_GET['sna'] : '') . "'>
                            </div>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_description']}</div>
                                <input name='sd' type='text' placeholder='{$lang['search_desc']}' class='search w-100' value='" . (!empty($_GET['sd']) ? $_GET['sd'] : '') . "'>
                            </div>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_uploader']}</div>
                                <input name='so' type='text' placeholder='{$lang['search_uploader']}' class='search w-100' value='" . (!empty($_GET['so']) ? $_GET['so'] : '') . "'>
                            </div>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_subs']}</div>
                                <input name='st' type='text' placeholder='{$lang['search_subs']}' class='search w-100' value='" . (!empty($_GET['st']) ? $_GET['st'] : '') . "'>
                            </div>
                        </div>
                        <div class='columns'>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_person']}</div>
                                <input name='sp' type='text' placeholder='{$lang['search_person']}' class='search w-100' value='" . (!empty($_GET['sp']) ? $_GET['sp'] : '') . "'>
                            </div>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_person_fuzzy']}</div>
                                <input name='spf' type='text' placeholder='{$lang['search_person_fuzzy']}' class='search w-100' value='" . (!empty($_GET['spf']) ? $_GET['spf'] : '') . "'>
                            </div>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_role']}</div>
                                <input name='sr' type='text' placeholder='{$lang['search_role']}' class='search w-100' value='" . (!empty($_GET['sr']) ? $_GET['sr'] : '') . "'>
                            </div>
                            <div class='column'>
                                <div class='has-text-centered bottom10'>{$lang['browse_genre']}</div>
                                <input name='sg' type='text' placeholder='{$lang['search_genre']}' class='search w-100' value='" . (!empty($_GET['sg']) ? $_GET['sg'] : '') . "'>
                            </div>
                        </div>
                        <div class='columns'>
                            <div class='column'>
                                <div class='columns'>
                                    <div class='column'>
                                        <div class='has-text-centered bottom10'>{$lang['browse_year_start']}</div>
                                        <input name='sys' type='number' min='1900' max='" . (date('Y') + 1) . "' placeholder='{$lang['search_year_start']}' class='search w-100' value='" . (!empty($_GET['sys']) ? $_GET['sys'] : '') . "'>
                                    </div>
                                    <div class='column'>
                                        <div class='has-text-centered bottom10'>{$lang['browse_year_end']}</div>
                                        <input name='sye' type='number' min='1900' max='" . (date('Y') + 1) . "' placeholder='{$lang['search_year_end']}' class='search w-100' value='" . (!empty($_GET['sye']) ? $_GET['sye'] : '') . "'>
                                    </div>
                                </div>
                            </div>
                            <div class='column'>
                                <div class='columns'>
                                    <div class='column'>
                                        <div class='has-text-centered bottom10'>{$lang['browse_rating_start']}</div>
                                        <input name='srs' type='number' min='0' max='10' step='0.1' placeholder='{$lang['search_rating_start']}' class='search w-100' value='" . (!empty($_GET['srs']) ? $_GET['srs'] : '') . "'>
                                    </div>
                                    <div class='column'>
                                        <div class='has-text-centered bottom10'>{$lang['browse_rating_end']}</div>
                                        <input name='sre' type='number' min='0' max='10' step='0.1' placeholder='{$lang['search_rating_end']}' class='search w-100' value='" . (!empty($_GET['sre']) ? $_GET['sre'] : '') . "'>
                                    </div>
                                </div>
                            </div>
                            <div class='column'>
                                <div class='columns'>
                                    <div class='column'>
                                        <div class='has-text-centered bottom10'>{$lang['browse_imdb']}</div>
                                        <input name='si' type='text' placeholder='{$lang['search_imdb']}' class='search w-100' value='" . (!empty($_GET['si']) ? $_GET['si'] : '') . "'>
                                    </div>
                                    <div class='column'>
                                        <div class='has-text-centered bottom10'>{$lang['browse_isbn']}</div>
                                        <input name='ss' type='text' placeholder='{$lang['search_isbn']}' class='search w-100' value='" . (!empty($_GET['ss']) ? $_GET['ss'] : '') . "'>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class='columns top20'>
                            <div class='column'>
                                $deadcheck
                            </div>
                            <div class='column'>
                                $vip_box
                            </div>
                            <div class='column'>
                                $only_free_box
                            </div>
                            <div class='column'>
                                $unsnatched_box
                            </div>
                        </div>
                        <div class='margin10 level-center-center'>
                            <input type='submit' value='{$lang['search_search_btn']}' class='button is-small'>
                            <span id='advanced_btn' class='left10 button is-small' onclick='toggle_search()'>{$lang['search_toggle_advanced_btn']}</span>
                        </div>
                    </div>
                </div>
                <div id='autocomplete' class='w-75 padding20 has-text-centered'>
                    <div class='padding20 bg-00 round10 autofill'>
                        <div id='autocomplete_list' class='margin10'>
                        </div>
                    </div>
                </div>");
$HTMLOUT .= '
            </form>';
$HTMLOUT .= "{$new_button}";
if ($count) {
    $HTMLOUT .= ($count > $torrentsperpage ? "
        <div class='top20'>{$pager['pagertop']}</div>" : '') . "
            <div class='table-wrapper top20'>" . torrenttable($query, 'index') . '</div>' . ($count > $torrentsperpage ? "
        <div class='top20'>{$pager['pagerbottom']}</div>" : '');
} else {
    if (isset($cleansearchstr)) {
        $text = "
                <div class='padding20'>
                    <h2>{$lang['browse_not_found']}</h2>
                    <p>{$lang['browse_tryagain']}</p>
                </div>";
    } else {
        $text = "
                <div class='padding20'>
                    <h2>{$lang['browse_nothing']}</h2>
                    <p>{$lang['browse_sorry']}</p>
                </div>";
    }
    $HTMLOUT .= main_div($text, 'top20 has-text-centered');
}

echo stdhead($title) . wrapper($HTMLOUT) . stdfoot($stdfoot);
