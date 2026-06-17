<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';

$sdata   = nagios_parse_status($sf);
$program = $sdata['program'] ?? [];

/* Parse "x,y,z" stats fields into [1min, 5min, 15min] */
function parse_stats($val) {
    $parts = array_map('intval', explode(',', (string)$val));
    while (count($parts) < 3) $parts[] = 0;
    return [$parts[0], $parts[1], $parts[2]];
}

$stats_fields = [
    'active_scheduled_host_check_stats'   => 'Active Scheduled Host Checks',
    'active_ondemand_host_check_stats'    => 'Active On-Demand Host Checks',
    'passive_host_check_stats'            => 'Passive Host Checks',
    'cached_host_check_stats'             => 'Cached Host Checks',
    'parallel_host_check_stats'           => 'Parallel Host Checks',
    'serial_host_check_stats'             => 'Serial Host Checks',
    'active_scheduled_service_check_stats'=> 'Active Scheduled Service Checks',
    'active_ondemand_service_check_stats' => 'Active On-Demand Service Checks',
    'passive_service_check_stats'         => 'Passive Service Checks',
    'cached_service_check_stats'          => 'Cached Service Checks',
    'external_command_stats'              => 'External Commands Processed',
];

/* Compute latency + execution stats from all host/service blocks */
$lat_vals = []; $exec_vals = [];
foreach (array_merge($sdata['hosts'] ?? [], $sdata['services'] ?? []) as $b) {
    if (isset($b['latency'])        && is_numeric($b['latency']))        $lat_vals[]  = (float)$b['latency'];
    if (isset($b['execution_time']) && is_numeric($b['execution_time'])) $exec_vals[] = (float)$b['execution_time'];
}

function arr_avg($a) { return count($a) ? array_sum($a)/count($a) : 0; }
function arr_max($a) { return count($a) ? max($a) : 0; }
function arr_min($a) { return count($a) ? min($a) : 0; }

/* Host state counts */
$h_up = $h_dn = $h_ur = $h_uk = 0;
foreach ($sdata['hosts'] as $h) {
    $cs = (int)($h['current_state'] ?? 0);
    if ($cs === 0) $h_up++; elseif ($cs === 1) $h_dn++; elseif ($cs === 2) $h_ur++; else $h_uk++;
}
/* Service state counts */
$s_ok = $s_wn = $s_cr = $s_uk = 0;
foreach ($sdata['services'] as $s) {
    $cs = (int)($s['current_state'] ?? 0);
    if ($cs === 0) $s_ok++; elseif ($cs === 1) $s_wn++; elseif ($cs === 2) $s_cr++; else $s_uk++;
}

$n_hosts = count($sdata['hosts']);
$n_svcs  = count($sdata['services']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Performance Information &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<style>
body     { padding: 10px 14px; }
.page-hd { margin: -10px -14px 10px; }
.data-card { margin-bottom: 8px; }
</style>
</head>
<body>

<div class="page-hd">
	<div class="phd-left">
		<div class="phd-page">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
			<span class="phd-page-title">Performance Information</span>
		</div>
		<div class="phd-count"><?php echo $n_hosts; ?> hosts &middot; <?php echo $n_svcs; ?> services</div>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<?php if (empty($program)): ?>
<div class="data-card"><div class="cell-state is-error">Could not read process status from status file.</div></div>
<?php else: ?>

<!-- Host / Service State Summary -->
<div class="data-card" style="padding:14px">
<div class="perf-section-title" style="margin-top:0">Current State Summary</div>
<div class="perf-grid">
	<div class="perf-card">
		<div class="perf-card-label">Hosts UP</div>
		<div class="perf-card-val" style="color:#4ade80"><?php echo $h_up; ?></div>
		<div class="perf-card-sub"><?php echo $n_hosts ? round($h_up/$n_hosts*100,1) : 0; ?>% of <?php echo $n_hosts; ?></div>
	</div>
	<div class="perf-card">
		<div class="perf-card-label">Hosts DOWN</div>
		<div class="perf-card-val" style="color:<?php echo $h_dn?'#f87171':'var(--text-lo)'; ?>"><?php echo $h_dn; ?></div>
		<div class="perf-card-sub"><?php echo $h_ur ? $h_ur.' unreachable' : 'none unreachable'; ?></div>
	</div>
	<div class="perf-card">
		<div class="perf-card-label">Services OK</div>
		<div class="perf-card-val" style="color:#4ade80"><?php echo $s_ok; ?></div>
		<div class="perf-card-sub"><?php echo $n_svcs ? round($s_ok/$n_svcs*100,1) : 0; ?>% of <?php echo $n_svcs; ?></div>
	</div>
	<div class="perf-card">
		<div class="perf-card-label">Services CRITICAL</div>
		<div class="perf-card-val" style="color:<?php echo $s_cr?'#f87171':'var(--text-lo)'; ?>"><?php echo $s_cr; ?></div>
		<div class="perf-card-sub"><?php echo $s_wn; ?> warning, <?php echo $s_uk; ?> unknown</div>
	</div>
</div>
</div>

<!-- Latency & Execution Time -->
<div class="data-card" style="padding:14px">
<div class="perf-section-title" style="margin-top:0">Check Latency &amp; Execution Time</div>
<div class="perf-grid">
	<div class="perf-card">
		<div class="perf-card-label">Avg Latency</div>
		<div class="perf-card-val"><?php echo number_format(arr_avg($lat_vals), 3); ?>s</div>
		<div class="perf-card-sub">across all hosts &amp; services</div>
	</div>
	<div class="perf-card">
		<div class="perf-card-label">Max Latency</div>
		<div class="perf-card-val"><?php echo number_format(arr_max($lat_vals), 3); ?>s</div>
		<div class="perf-card-sub">&nbsp;</div>
	</div>
	<div class="perf-card">
		<div class="perf-card-label">Avg Execution</div>
		<div class="perf-card-val"><?php echo number_format(arr_avg($exec_vals), 3); ?>s</div>
		<div class="perf-card-sub">across all checks</div>
	</div>
	<div class="perf-card">
		<div class="perf-card-label">Max Execution</div>
		<div class="perf-card-val"><?php echo number_format(arr_max($exec_vals), 3); ?>s</div>
		<div class="perf-card-sub">&nbsp;</div>
	</div>
</div>
</div>

<!-- Check Stats (from programstatus) -->
<div class="data-card" style="padding:14px 14px 4px">
<div class="perf-section-title" style="margin-top:0">Check Statistics (per interval)</div>
<table class="dtbl" style="margin-bottom:10px">
<thead><tr>
	<th>Metric</th>
	<th style="width:80px;text-align:right">Last 1 min</th>
	<th style="width:80px;text-align:right">Last 5 min</th>
	<th style="width:80px;text-align:right">Last 15 min</th>
</tr></thead>
<tbody>
<?php foreach ($stats_fields as $key => $label):
	if (!isset($program[$key])) continue;
	[$v1, $v5, $v15] = parse_stats($program[$key]);
	$any = $v1 || $v5 || $v15;
?>
<tr class="data-row">
	<td style="font-size:0.70rem"><?php echo h($label); ?></td>
	<td style="text-align:right;font-variant-numeric:tabular-nums">
		<strong style="color:<?php echo $v1?'var(--text-hi)':'var(--text-lo)'; ?>"><?php echo $v1; ?></strong>
	</td>
	<td style="text-align:right;font-variant-numeric:tabular-nums">
		<strong style="color:<?php echo $v5?'var(--text-hi)':'var(--text-lo)'; ?>"><?php echo $v5; ?></strong>
	</td>
	<td style="text-align:right;font-variant-numeric:tabular-nums">
		<strong style="color:<?php echo $v15?'var(--text-hi)':'var(--text-lo)'; ?>"><?php echo $v15; ?></strong>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>
</body>
</html>
