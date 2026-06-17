<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$sf      = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';
$refresh = 90;

$data = nagios_parse_status($sf);

/* Total monitored hosts (for context badge) */
$total_hosts = count($data['hosts']);

/* Filter to problem states only: DOWN + UNREACHABLE */
$counts  = ['down'=>0,'unreach'=>0,'acked'=>0];
$problems = [];
foreach ($data['hosts'] as $h) {
    $st = host_state_info($h);
    if ($st['text'] === 'DOWN' || $st['text'] === 'UNREACHABLE') {
        $problems[] = $h;
        if ($st['text'] === 'DOWN')        $counts['down']++;
        if ($st['text'] === 'UNREACHABLE') $counts['unreach']++;
        if (($h['problem_has_been_acknowledged'] ?? '0') === '1') $counts['acked']++;
    }
}

/* Sort: DOWN first, then UNREACHABLE, then alpha */
usort($problems, function($a, $b) {
    $oa = host_state_info($a)['ord'];
    $ob = host_state_info($b)['ord'];
    return $oa !== $ob ? $oa <=> $ob : strcmp($a['host_name']??'', $b['host_name']??'');
});

$total_prob  = count($problems);
$unacked     = $total_prob - $counts['acked'];
$now         = time();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Host Problems &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<meta http-equiv="refresh" content="<?php echo $refresh; ?>">
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
			<span class="phd-page-title">Host Problems</span>
		</div>
		<?php if ($total_prob > 0): ?>
		<span class="phd-count phd-count-alert"><?php echo $total_prob; ?> problem<?php echo $total_prob!==1?'s':''; ?> of <?php echo $total_hosts; ?> hosts</span>
		<?php else: ?>
		<span class="phd-count"><?php echo $total_hosts; ?> hosts monitored</span>
		<?php endif; ?>
	</div>
	<div class="phd-right">
		<div class="refresh-pill">
			<span class="refresh-dot"></span>
			Auto-refresh <?php echo $refresh; ?>s
		</div>
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<!-- Summary chips -->
<div class="summary-bar">
	<div class="sum-chip chip-down">   <span class="n"><?php echo $counts['down'];    ?></span> Down</div>
	<div class="sum-chip chip-unreach"><span class="n"><?php echo $counts['unreach'];?></span> Unreachable</div>
	<div class="sum-chip chip-acked">  <span class="n"><?php echo $counts['acked'];  ?></span> Acknowledged</div>
	<div class="sum-chip chip-unacked"><span class="n"><?php echo $unacked;           ?></span> Unacknowledged</div>
</div>

<!-- Filter bar -->
<div class="filter-bar">
	<span class="filter-lbl">Filter:</span>
	<button class="fbtn active" onclick="filterRows(this,'all')">All (<?php echo $total_prob; ?>)</button>
	<?php if ($counts['down'])    echo '<button class="fbtn" onclick="filterRows(this,\'down\')">Down ('.$counts['down'].')</button>'; ?>
	<?php if ($counts['unreach']) echo '<button class="fbtn" onclick="filterRows(this,\'unreachable\')">Unreachable ('.$counts['unreach'].')</button>'; ?>
	<?php if ($total_prob > 0): ?>
	<span class="filter-sep"></span>
	<button class="pbtn" id="unacked-toggle" onclick="toggleUnacked(this)">Unacked only (<?php echo $unacked; ?>)</button>
	<?php endif; ?>
</div>

<?php if (!$data['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Error reading status file: <?php echo h($data['error']); ?>
</div></div>
<?php elseif (empty($problems)): ?>
<div class="data-card">
	<div class="cell-state cell-state-ok">
		<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#4ade80;margin-bottom:10px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
		<div>All hosts are <strong>UP</strong> &mdash; no problems detected.</div>
	</div>
</div>
<?php else: ?>

<div class="data-card">
<table class="dtbl">
<thead><tr>
	<th style="width:90px">Status</th>
	<th>Host</th>
	<th style="width:100px">Duration</th>
	<th style="width:90px">Last Check</th>
	<th>Status Info</th>
	<th style="width:70px">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($problems as $h):
    $st   = host_state_info($h);
    $hn   = $h['host_name'] ?? '';
    $dur  = (!empty($h['last_state_change']) && $h['last_state_change']!=='0')
            ? fmt_dur($now - (int)$h['last_state_change']) : '-';
    $lc   = (!empty($h['last_check']) && $h['last_check']!=='0')
            ? fmt_ago((int)$h['last_check']) : '-';
    $ack  = ($h['problem_has_been_acknowledged'] ?? '0') === '1';
    $dt   = ((int)($h['scheduled_downtime_depth'] ?? 0)) > 0;
    $out  = h($h['plugin_output'] ?? '');
    $hurl = h($cgi.'/status.cgi?host='.urlencode($hn));
    $eurl = h('host.php?host='.urlencode($hn));
    $aurl = expand_url($h['action_url'] ?? '', $hn);
    $stl  = strtolower(str_replace(' ', '', $st['text']));
?>
<tr class="data-row <?php echo $st['row']; ?>"
    data-state="<?php echo $stl; ?>"
    data-acked="<?php echo $ack ? '1' : '0'; ?>">
	<td><?php echo state_badge($st); ?></td>
	<td class="c-host">
		<a href="<?php echo $eurl; ?>" target="main"><?php echo h($hn); ?></a>
		<?php if ($ack) echo '<span class="tag-ack">ACK</span>'; ?>
		<?php if ($dt)  echo '<span class="tag-dt">DOWNTIME</span>'; ?>
	</td>
	<td class="c-dur"><?php echo $dur; ?></td>
	<td class="c-lc"><?php echo $lc; ?></td>
	<td class="c-out" title="<?php echo $out; ?>"><?php echo $out; ?></td>
	<td style="white-space:nowrap">
		<?php if ($aurl): ?>
		<a href="<?php echo h($aurl); ?>" target="_blank" class="action-graph" title="Graph">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
					</a>
		<?php endif; ?>
		<a href="<?php echo $eurl; ?>" target="main" class="action-detail" title="Detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _stateFilter  = 'all';
var _unackedOnly  = false;

function applyAll() {
	document.querySelectorAll('tr.data-row').forEach(function(r) {
		var stateOk = (_stateFilter === 'all' || r.dataset.state === _stateFilter);
		var ackedOk = (!_unackedOnly || r.dataset.acked === '0');
		r.style.display = (stateOk && ackedOk) ? '' : 'none';
	});
}
function filterRows(btn, state) {
	document.querySelectorAll('.fbtn').forEach(function(b){ b.classList.remove('active'); });
	btn.classList.add('active');
	_stateFilter = state;
	applyAll();
}
function toggleUnacked(btn) {
	_unackedOnly = !_unackedOnly;
	btn.classList.toggle('active', _unackedOnly);
	applyAll();
}
</script>
</body>
</html>
