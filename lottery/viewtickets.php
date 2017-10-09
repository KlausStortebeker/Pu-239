<?php
$lconf = sql_query('SELECT * FROM lottery_config') or sqlerr(__FILE__, __LINE__);
while ($ac = mysqli_fetch_assoc($lconf)) {
    $lottery_config[$ac['name']] = $ac['value'];
}
if (!$lottery_config['enable']) {
    stderr('Sorry', 'Lottery is closed');
}
$html = begin_main_frame() . begin_frame('Lottery stats');
$html .= '<h2>Lottery started on <b>' . get_date($lottery_config['start_date'], 'LONG') . '</b> and ends on <b>' . get_date($lottery_config['end_date'], 'LONG') . "</b> remaining <span style='color:#ff0000;'>" . mkprettytime($lottery_config['end_date'] - TIME_NOW) . '</span></h2>';
$qs = sql_query('SELECT count(t.id) as tickets , u.username, u.id, u.seedbonus FROM tickets as t LEFT JOIN users as u ON u.id = t.user GROUP BY u.id ORDER BY tickets DESC, username ASC') or sqlerr(__FILE__, __LINE__);
if (!mysqli_num_rows($qs)) {
    $html .= '<h2>Not tickets were bought</h2>';
} else {
    $html .= "<table width='80%' cellpadding='5' cellspacing='0' border='1'>
    <tr>
      <td width='100%'>Username</td>
      <td style='white-space:nowrap;'>tickets</td>
      <td style='white-space:nowrap;'>seedbonus</td>
    </tr>";
    while ($ar = mysqli_fetch_assoc($qs)) {
        $html .= "<tr>
                  <td><a href='userdetails.php?id=" . (int)$ar['id'] . "'>" . htmlsafechars($ar['username']) . "</a></td>
                  <td>" . (int)$ar['tickets'] . "</td>
                  <td>" . (float)$ar['seedbonus'] . '</td>
        </tr>';
    }
    $html .= '</table>';
}
$html .= end_frame() . end_main_frame();
echo stdhead('Lottery tickets') . $html . stdfoot();
