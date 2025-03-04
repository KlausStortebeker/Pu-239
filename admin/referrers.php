<?php

declare(strict_types = 1);

require_once INCL_DIR . 'function_users.php';
require_once INCL_DIR . 'function_pager.php';
require_once CLASS_DIR . 'class_check.php';
$class = get_access(basename($_SERVER['REQUEST_URI']));
class_check($class);
$lang = array_merge($lang, load_language('referrers'));
global $site_config;

$HTMLOUT = '';
$_GET['page'] = !isset($_GET['page']) ? 0 : (int) $_GET['page'];
$res = sql_query('SELECT * FROM referrers') or sqlerr(__FILE__, __LINE__);
$count = mysqli_num_rows($res);
if ($count > 0) {
    $HTMLOUT .= "
    <h1 class='has-text-centered'>{$lang['ref_last']}</h1>";
    $heading = "
        <tr>
            <th>{$lang['ref_nr']}</th>
            <th>{$lang['ref_date']}</th>
            <th>{$lang['ref_browser']}</th>
            <th>{$lang['ref_ip']}</th>
            <th>{$lang['ref_user']}</th>
            <th>{$lang['ref_url']}</th>
            <th>{$lang['ref_result']}</th>
        </tr>";
    $perpage = 10;
    $i = (int) $_GET['page'] * $perpage;
    $pager = pager($perpage, $count, 'staffpanel.php?tool=referrers&amp;');
    $res = sql_query("SELECT r.*, u.id as uid, u.username FROM referrers AS r LEFT JOIN users AS u ON u.ip = r.ip ORDER BY date DESC {$pager['limit']}") or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($res) > 0) {
        $body = '';
        while ($data = mysqli_fetch_assoc($res)) {
            ++$i;
            $http_agent = htmlsafechars($data['browser']);
            if (strstr($http_agent, 'Opera')) {
                $browser = "<img src='{$site_config['paths']['images_baseurl']}referrers/opera.png' alt='Opera' title='Opera' width='25' height='25'>&#160;&#160;Opera";
            } elseif (strstr($http_agent, 'Konqueror')) {
                $browser = "<img src='{$site_config['paths']['images_baseurl']}referrers/konqueror.png' alt='konqueror' title='konqueror' width='25' height='25'>&#160;&#160;konqueror";
            } elseif (strstr($http_agent, 'MSIE')) {
                $browser = "<img src='{$site_config['paths']['images_baseurl']}referrers/ie.png' alt='IE' title='IE' width='25' height='25'>&#160;&#160;IE";
            } elseif (strstr($http_agent, 'Chrome')) {
                $browser = "<img src='{$site_config['paths']['images_baseurl']}referrers/chrome.png' alt='Chrome' title='Chrome' width='25' height='25'>&#160;&#160;Chrome";
            } elseif ((strstr($http_agent, 'Nav')) || (strstr($http_agent, 'Gold')) || (strstr($http_agent, 'X11')) || (strstr($http_agent, 'Mozilla')) || (strstr($http_agent, 'Netscape'))) {
                $browser = "<img src='{$site_config['paths']['images_baseurl']}referrers/firefox.png' alt='FireFox' title='FireFox' width='25' height='25'>&#160;&#160;Mozilla";
            } else {
                $browser = $lang['ref_unknow'];
            }
            $body .= '
        <tr>
            <td>' . $i . '</td>
            <td>' . get_date((int) $data['date'], '') . '</td>
            <td>' . $browser . '</td>
            <td>' . htmlsafechars($data['ip']) . '</td>
            <td>' . htmlsafechars($data['ip']) . ' ' . ((int) $data['uid'] ? format_username((int) $data['uid']) : $lang['ref_guest']) . "</td>
            <td><a href='" . htmlsafechars($data['referer']) . "'>" . htmlsafechars(CutName($data['referer'], 50)) . "</a></td>
            <td><a href='" . htmlsafechars($data['page']) . "'>{$lang['ref_view']}</a></td>
        </tr>";
            $browser = '';
        }
    }
    $HTMLOUT .= main_table($body, $heading);
    $HTMLOUT .= $pager['pagerbottom'];
} else {
    $HTMLOUT .= stdmsg($lang['ref_nothing'], $lang['ref_nothing_1']);
}

echo stdhead($lang['ref_stdhead']) . wrapper($HTMLOUT) . stdfoot();
