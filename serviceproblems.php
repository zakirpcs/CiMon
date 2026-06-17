<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$sf      = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$mcf     = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$refresh = 90;

$data      = nagios_parse_status($sf);
$ocf       = nagios_find_objects_cache($mcf);
$aurl_map  = nagios_load_action_urls($ocf);

/* Host state lookup */
$host_state = [];
foreach ($data['hosts'] as $h) {
    $host_state[$h['host_name'] ?? ''] = host_state_info($h);
}

$total_svcs = count($data['services']);

/* Filter to problem states: CRITICAL, WARNING, UNKNOWN */
$counts   = ['crit'=>0,'warn'=>0,'unkn'=>0,'acked'=>0];
$problems = [];
foreach ($data['services'] as $s) {
    $st = svc_state_info($s);
    if ($st['text'] === 'CRITICAL' || $st['text'] === 'WARNING' || $st['text'] === 'UNKNOWN') {
        $problems[] = $s;
        if ($st['text'] === 'CRITICAL') $counts['crit']++;
        if ($st['text'] === 'WARNING')  $counts['warn']++;
        if ($st['text'] === 'UNKNOWN')  $counts['unkn']++;
        if (($s['problem_has_been_acknowledged'] ?? '0') === '1') $counts['acked']++;
    }
}

$total_prob = count($problems);
$unacked    = $total_prob - $counts['acked'];

/* Group by host, worst service first within each host */
$grouped = [];
foreach ($problems as $s) {
    $hn = $s['host_name'] ?? '';
    $grouped[$hn][] = $s;
}
foreach ($grouped as $hn => &$svcs) {
    usort($svcs, function($a, $b) {
        return svc_state_info($a)['ord'] <=> svc_state_info($b)['ord'];
    });
}
unset($svcs);

/* Sort hosts: worst-service-state first, then alpha */
uksort($grouped, function($a, $b) use ($grouped) {
    $worst = function($svcs) {
        $w = 99;
        foreach ($svcs as $s) { $o = svc_state_info($s)['ord']; if ($o < $w) $w = $o; }
        return $w;
    };
    $wa = $worst($grouped[$a]);
    $wb = $worst($grouped[$b]);
    return $wa !== $wb ? $wa <=> $wb : strcmp($a, $b);
});

$now = time();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Service Problems &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			<span class="phd-page-title">Service Problems</span>
		</div>
		<?php if ($total_prob > 0): ?>
		<span class="phd-count phd-count-alert"><?php echo $total_prob; ?> problem<?php echo $total_prob!==1?'s':''; ?> of <?php echo $total_svcs; ?> services</span>
		<?php else: ?>
		<span class="phd-count"><?php echo $total_svcs; ?> services monitored</span>
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
	<div class="sum-chip chip-crit">   <span class="n"><?php echo $counts['crit'];  ?></span> Critical</div>
	<div class="sum-chip chip-warn">   <span class="n"><?php echo $counts['warn'];  ?></span> Warning</div>
	<div class="sum-chip chip-unkn">   <span class="n"><?php echo $counts['unkn'];  ?></span> Unknown</div>
	<div class="sum-chip chip-acked">  <span class="n"><?php echo $counts['acked'];?></span> Acknowledged</div>
	<div class="sum-chip chip-unacked"><span class="n"><?php echo $unacked;          ?></span> Unacknowledged</div>
</div>

<!-- Filter bar -->
<div class="filter-bar">
	<span class="filter-lbl">Filter:</span>
	<button class="fbtn active" onclick="filterRows(this,'all')">All (<?php echo $total_prob; ?>)</button>
	<?php if ($counts['crit']) echo '<button class="fbtn" onclick="filterRows(this,\'critical\')">Critical ('.$counts['crit'].')</button>'; ?>
	<?php if ($counts['warn']) echo '<button class="fbtn" onclick="filterRows(this,\'warning\')">Warning ('.$counts['warn'].')</button>'; ?>
	<?php if ($counts['unkn']) echo '<button class="fbtn" onclick="filterRows(this,\'unknown\')">Unknown ('.$counts['unkn'].')</button>'; ?>
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
		<div>All services are <strong>OK</strong> &mdash; no problems detected.</div>
	</div>
</div>
<?php else: ?>

<div class="data-card">
<table class="dtbl">
<thead><tr>
	<th style="width:90px">Status</th>
	<th>Service</th>
	<th style="width:100px">Duration</th>
	<th style="width:90px">Last Check</th>
	<th>Status Info</th>
	<th style="width:100px">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($grouped as $hn => $svcs):
    $hst   = $host_state[$hn] ?? ['text'=>'UNKNOWN','cls'=>'badge-pending','row'=>'','ord'=>2];
    $hurl  = h($cgi.'/status.cgi?host='.urlencode($hn));
    $heurl = h('host.php?host='.urlencode($hn));
    $haurl = get_action_url(['action_url'=>''], $aurl_map, $hn, '');

    /* Worst state in this host's problem services */
    $hw = 99; foreach ($svcs as $s) { $o = svc_state_info($s)['ord']; if ($o < $hw) $hw = $o; }
    /* Per-host acked count for data-acked attribute */
    $h_acked = 0; foreach ($svcs as $s) { if (($s['problem_has_been_acknowledged']??'0')==='1') $h_acked++; }
    $h_all_acked = $h_acked === count($svcs) ? '1' : '0';
?>
<tr class="host-grp-hdr" data-host="<?php echo h($hn); ?>" data-open="1"
    data-hacked="<?php echo $h_all_acked; ?>"
    onclick="toggleGroup(this)">
	<td colspan="6">
		<span class="hgrp-chevron">
			<svg class="chevron-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
		</span>
		<span class="hgrp-name">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;opacity:.6"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
			<a href="<?php echo $heurl; ?>" target="main" onclick="event.stopPropagation()"><?php echo h($hn); ?></a>
		</span>
		<?php echo state_badge($hst); ?>
		<span class="hgrp-count"><?php echo count($svcs); ?> problem<?php echo count($svcs)!==1?'s':''; ?></span>
		<span class="hgrp-actions" onclick="event.stopPropagation()">
			<?php if ($haurl): ?>
			<a href="<?php echo h($haurl); ?>" target="_blank" title="Graph">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></a>
			<?php endif; ?>
			<a href="<?php echo $heurl; ?>" target="main" class="detail-lnk" title="Host detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
		</span>
	</td>
</tr>
<?php foreach ($svcs as $s):
    $st   = svc_state_info($s);
    $dur  = (!empty($s['last_state_change']) && $s['last_state_change']!=='0')
            ? fmt_dur($now - (int)$s['last_state_change']) : '-';
    $lc   = (!empty($s['last_check']) && $s['last_check']!=='0')
            ? fmt_ago((int)$s['last_check']) : '-';
    $ack  = ($s['problem_has_been_acknowledged'] ?? '0') === '1';
    $dt   = ((int)($s['scheduled_downtime_depth'] ?? 0)) > 0;
    $sn   = h($s['service_description'] ?? '');
    $out  = h($s['plugin_output'] ?? '');
    $durl = h('service.php?host='.urlencode($hn).'&service='.urlencode($s['service_description']??''));
    $aurl = get_action_url($s, $aurl_map, $hn, $s['service_description'] ?? '');
    $stl  = strtolower($st['text']);
?>
<tr class="data-row <?php echo $st['row']; ?>"
    data-host="<?php echo h($hn); ?>"
    data-state="<?php echo $stl; ?>"
    data-acked="<?php echo $ack ? '1' : '0'; ?>">
	<td><?php echo state_badge($st); ?></td>
	<td class="c-svc">
		<a href="<?php echo $durl; ?>" target="main"><?php echo $sn; ?></a>
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
		<a href="<?php echo $durl; ?>" target="main" class="action-detail" title="Detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
	</td>
</tr>
<?php endforeach; ?>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _stateFilter = 'all';
var _unackedOnly = false;

function applyAll() {
	document.querySelectorAll('tr.data-row').forEach(function(r) {
		if (r.dataset.collapsed === '1') { r.style.display = 'none'; return; }
		var stateOk = (_stateFilter === 'all' || r.dataset.state === _stateFilter);
		var ackedOk = (!_unackedOnly || r.dataset.acked === '0');
		r.style.display = (stateOk && ackedOk) ? '' : 'none';
	});
	/* Hide host header row if none of its services are visible */
	document.querySelectorAll('tr.host-grp-hdr').forEach(function(hdr) {
		var anyVisible = false;
		document.querySelectorAll('tr.data-row[data-host="' + hdr.dataset.host + '"]').forEach(function(r) {
			if (r.style.display !== 'none') anyVisible = true;
		});
		hdr.style.display = anyVisible ? '' : 'none';
	});
}
function toggleGroup(hdr) {
	var host   = hdr.dataset.host;
	var isOpen = hdr.dataset.open === '1';
	hdr.dataset.open = isOpen ? '0' : '1';
	hdr.querySelector('.chevron-icon').style.transform = isOpen ? 'rotate(-90deg)' : '';
	document.querySelectorAll('tr.data-row[data-host="' + host + '"]').forEach(function(r) {
		r.dataset.collapsed = isOpen ? '1' : '0';
		r.style.display = isOpen ? 'none' : '';
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
