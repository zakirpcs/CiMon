<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';
$now = time();

$sdata = nagios_parse_status($sf);

/* Build scheduling queue: collect all scheduled (active) checks */
$queue = [];

foreach ($sdata['hosts'] as $h) {
    $nc = (int)($h['next_check'] ?? 0);
    if ($nc <= 0) continue;
    if ((int)($h['checks_enabled'] ?? 0) === 0 && (int)($h['check_type'] ?? 0) === 0) continue;
    $queue[] = [
        'kind'      => 'host',
        'host'      => $h['host_name'] ?? '',
        'svc'       => '',
        'next_check'=> $nc,
        'interval'  => (float)($h['check_interval']  ?? 0),
        'latency'   => (float)($h['latency']          ?? 0),
        'exec_time' => (float)($h['execution_time']   ?? 0),
        'check_type'=> (int)($h['check_type']         ?? 0),
        'state'     => host_state_info($h),
    ];
}

foreach ($sdata['services'] as $s) {
    $nc = (int)($s['next_check'] ?? 0);
    if ($nc <= 0) continue;
    if ((int)($s['checks_enabled'] ?? 0) === 0 && (int)($s['check_type'] ?? 0) === 0) continue;
    $queue[] = [
        'kind'      => 'service',
        'host'      => $s['host_name'] ?? '',
        'svc'       => $s['service_description'] ?? '',
        'next_check'=> $nc,
        'interval'  => (float)($s['check_interval']  ?? 0),
        'latency'   => (float)($s['latency']          ?? 0),
        'exec_time' => (float)($s['execution_time']   ?? 0),
        'check_type'=> (int)($s['check_type']         ?? 0),
        'state'     => svc_state_info($s),
    ];
}

/* Sort by next_check ascending */
usort($queue, function($a,$b){ return $a['next_check'] - $b['next_check']; });

$n_hosts    = count(array_filter($queue, function($r){ return $r['kind']==='host'; }));
$n_svcs     = count(array_filter($queue, function($r){ return $r['kind']==='service'; }));
$n_overdue  = count(array_filter($queue, function($r) use($now){ return $r['next_check'] < $now; }));
$n_total    = count($queue);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Scheduling Queue &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
			<span class="phd-page-title">Scheduling Queue</span>
		</div>
		<div class="phd-count"><?php echo $n_total; ?> scheduled check<?php echo $n_total===1?'':'s'; ?></div>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<div class="summary-bar">
	<span class="sum-chip chip-total">Total <strong><?php echo $n_total; ?></strong></span>
	<span class="sum-chip chip-ok">Hosts <strong><?php echo $n_hosts; ?></strong></span>
	<span class="sum-chip chip-warn">Services <strong><?php echo $n_svcs; ?></strong></span>
	<?php if ($n_overdue): ?>
	<span class="sum-chip chip-down">Overdue <strong><?php echo $n_overdue; ?></strong></span>
	<?php endif; ?>
</div>

<div class="filter-bar">
	<span class="filter-lbl">Show:</span>
	<button class="fbtn active" id="f-all"    onclick="setFilter('all',    this)">All (<?php echo $n_total; ?>)</button>
	<button class="fbtn"        id="f-host"   onclick="setFilter('host',   this)">Hosts (<?php echo $n_hosts; ?>)</button>
	<button class="fbtn"        id="f-svc"    onclick="setFilter('svc',    this)">Services (<?php echo $n_svcs; ?>)</button>
	<?php if ($n_overdue): ?>
	<button class="fbtn"        id="f-overdue" onclick="setFilter('overdue',this)">Overdue (<?php echo $n_overdue; ?>)</button>
	<?php endif; ?>
</div>

<?php if (empty($queue)): ?>
<div class="data-card"><div class="cell-state">No scheduled checks found.</div></div>
<?php else: ?>

<div class="data-card">
<table class="dtbl" id="sq-tbl">
<thead><tr>
	<th style="width:24px"></th>
	<th style="width:140px">Host</th>
	<th>Service</th>
	<th style="width:30px">State</th>
	<th style="width:130px">Next Check</th>
	<th style="width:80px">In / Ago</th>
	<th style="width:75px;text-align:right">Interval</th>
	<th style="width:75px;text-align:right">Latency</th>
	<th style="width:75px;text-align:right">Exec Time</th>
	<th style="width:55px;text-align:center">Type</th>
</tr></thead>
<tbody>
<?php foreach ($queue as $r):
	$hurl    = h('host.php?host='.urlencode($r['host']));
	$diff    = $r['next_check'] - $now;
	$overdue = ($diff < 0);
	$abs_diff = abs($diff);
	if ($abs_diff < 60)        $diff_str = $abs_diff.'s';
	elseif ($abs_diff < 3600)  $diff_str = intdiv($abs_diff,60).'m '.($abs_diff%60).'s';
	else                       $diff_str = intdiv($abs_diff,3600).'h '.intdiv($abs_diff%3600,60).'m';
	$dtype = $r['kind']; /* host or service */
	$od    = $overdue ? '1' : '0';
?>
<tr class="data-row" data-dtype="<?php echo $dtype; ?>" data-od="<?php echo $od; ?>">
	<td style="padding:4px 6px">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;color:var(--text-lo)">
		<?php if ($r['kind'] === 'service'): ?>
			<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
		<?php else: ?>
			<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
		<?php endif; ?>
		</svg>
	</td>
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($r['host']); ?></a></td>
	<td class="c-svc"><?php echo $r['svc'] !== '' ? '<a href="'.h('service.php?host='.urlencode($r['host']).'&service='.urlencode($r['svc'])).'" target="main">'.h($r['svc']).'</a>' : '<span style="color:var(--text-lo)">—</span>'; ?></td>
	<td><?php echo state_badge($r['state']); ?></td>
	<td class="c-time <?php echo $overdue?'sched-overdue':''; ?>"><?php echo date('H:i:s', $r['next_check']); ?></td>
	<td style="font-size:0.68rem;font-variant-numeric:tabular-nums;<?php echo $overdue?'color:#f87171':'color:var(--text-lo)'; ?>">
		<?php echo $overdue ? '-'.$diff_str : '+'.$diff_str; ?>
	</td>
	<td style="text-align:right;font-size:0.68rem;color:var(--text-lo);font-variant-numeric:tabular-nums">
		<?php echo $r['interval'] ? number_format($r['interval'],1).'min' : '—'; ?>
	</td>
	<td style="text-align:right;font-size:0.68rem;color:var(--text-lo);font-variant-numeric:tabular-nums">
		<?php echo number_format($r['latency'],3).'s'; ?>
	</td>
	<td style="text-align:right;font-size:0.68rem;color:var(--text-lo);font-variant-numeric:tabular-nums">
		<?php echo number_format($r['exec_time'],3).'s'; ?>
	</td>
	<td style="text-align:center">
		<?php if ($r['check_type'] === 1): ?>
		<span style="font-size:0.58rem;color:#93c5fd;font-weight:700;letter-spacing:.05em">PASSIVE</span>
		<?php else: ?>
		<span style="font-size:0.58rem;color:var(--text-lo);font-weight:700;letter-spacing:.05em">ACTIVE</span>
		<?php endif; ?>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _filter = 'all';
function setFilter(f, btn) {
    _filter = f;
    document.querySelectorAll('.filter-bar .fbtn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    applyFilter();
}
function applyFilter() {
    document.querySelectorAll('#sq-tbl tbody tr.data-row').forEach(function(r) {
        var show = true;
        if (_filter === 'host')    show = r.dataset.dtype === 'host';
        if (_filter === 'svc')     show = r.dataset.dtype === 'service';
        if (_filter === 'overdue') show = r.dataset.od   === '1';
        r.style.display = show ? '' : 'none';
    });
}
</script>
</body>
</html>
