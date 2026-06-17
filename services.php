<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$sf      = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$mcf     = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$refresh = 90;

/* Optional host filter */
$filter_host = trim($_GET['host'] ?? '');

$data      = nagios_parse_status($sf);
$all_svcs  = $data['services'];
$hosts_raw = $data['hosts'];

/* Host-state lookup */
$host_state = [];
$host_block = null;
foreach ($hosts_raw as $h) {
    $host_state[$h['host_name'] ?? ''] = host_state_info($h);
    if ($filter_host !== '' && ($h['host_name'] ?? '') === $filter_host) $host_block = $h;
}

/* Action URL map */
$ocf      = nagios_find_objects_cache($mcf);
$aurl_map = nagios_load_action_urls($ocf);

/* Apply host filter */
$services = $filter_host !== ''
    ? array_values(array_filter($all_svcs, function($s) use($filter_host){ return ($s['host_name']??'') === $filter_host; }))
    : $all_svcs;

/* Count by state (within filtered set) */
$counts = ['ok'=>0,'warn'=>0,'crit'=>0,'unkn'=>0,'pending'=>0];
foreach ($services as $s) {
    $st = svc_state_info($s);
    $k  = ['OK'=>'ok','WARNING'=>'warn','CRITICAL'=>'crit','UNKNOWN'=>'unkn','PENDING'=>'pending'];
    $counts[$k[$st['text']] ?? 'pending']++;
}

/* Group by host, sort within each group by state (worst first) */
$grouped = [];
foreach ($services as $s) {
    $hn = $s['host_name'] ?? '';
    $grouped[$hn][] = $s;
}
foreach ($grouped as $hn => &$svcs) {
    usort($svcs, function($a, $b) {
        return svc_state_info($a)['ord'] <=> svc_state_info($b)['ord'];
    });
}
unset($svcs);

/* Sort hosts: worst service first, then alpha */
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

$now   = time();
$total = count($services);

/* ── Host state banner when filtered ── */
$hst_filtered = $filter_host !== '' ? ($host_state[$filter_host] ?? null) : null;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo $filter_host ? h($filter_host).' — ' : ''; ?>Service Status &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<?php if (!$filter_host): ?>
<meta http-equiv="refresh" content="<?php echo $refresh; ?>">
<?php endif; ?>
<style>
.phd-back-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px 5px 9px; border-radius: 7px; text-decoration: none;
    background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.09);
    transition: background 150ms ease, border-color 150ms ease;
    white-space: nowrap; color: inherit;
}
.phd-back-btn:hover  { background: rgba(0,0,0,0.06); border-color: rgba(0,0,0,0.16); }
.phd-back-btn svg    { width: 13px; height: 13px; flex-shrink: 0; color: var(--text-lo); stroke: currentColor; }
.phd-back-btn .sbth-label { font-size: 0.58rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.07em; color: var(--text-lo); }
.phd-back-btn .sbth-name  { font-size: 0.70rem; font-weight: 700; color: var(--text-hi); }
</style>
<style>
body     { padding: 10px 14px; }
.page-hd { margin: -10px -14px 10px; }
.data-card { margin-bottom: 8px; }
</style>
</head>
<body>

<!-- ── Page header ── -->
<div class="page-hd">
	<div class="phd-left">
		<div class="phd-page">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
			<?php if ($filter_host): ?>
			<a href="services.php" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.55">Services</a>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
			<a href="host.php?host=<?php echo urlencode($filter_host); ?>" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.55"><?php echo h($filter_host); ?></a>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
			<span class="phd-page-title">Services</span>
			<?php else: ?>
			<span class="phd-page-title">Service Status</span>
			<?php endif; ?>
		</div>
		<div class="phd-count">
			<?php echo $total; ?> service<?php echo $total===1?'':'s'; ?>
			<?php if ($counts['crit']): ?> &middot; <span style="color:#f87171;font-weight:700"><?php echo $counts['crit']; ?> critical</span><?php endif; ?>
			<?php if ($counts['warn']): ?> &middot; <span style="color:#fbbf24;font-weight:700"><?php echo $counts['warn']; ?> warning</span><?php endif; ?>
		</div>
	</div>
	<div class="phd-right">
		<?php if (!$filter_host): ?>
		<div class="refresh-pill"><span class="refresh-dot"></span>Auto-refresh <?php echo $refresh; ?>s</div>
		<?php else: ?>
		<a href="host.php?host=<?php echo urlencode($filter_host); ?>" target="main" class="phd-back-btn">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
			<span class="sbth-label">Host</span>
			<span class="sbth-name"><?php echo h($filter_host); ?></span>
		</a>
		<?php endif; ?>
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<?php if ($filter_host && $hst_filtered): ?>
<!-- ── Host context banner when filtered to a single host ── -->
<div class="svc-host-banner" style="--hac:<?php
    $hac_map = ['UP'=>'#4ade80','DOWN'=>'#ef4444','UNREACHABLE'=>'#a855f7','PENDING'=>'#94a3b8','UNKNOWN'=>'#f59e0b'];
    echo $hac_map[$hst_filtered['text']] ?? '#94a3b8';
?>">
	<div class="shb-state">
		<?php echo state_badge($hst_filtered); ?>
		<span class="shb-hostname"><?php echo h($filter_host); ?></span>
	</div>
	<?php if ($host_block): ?>
	<div class="shb-meta">
		<?php if ($host_block['last_state_change']??0): ?>
		<span>State for <?php echo fmt_dur($now - (int)$host_block['last_state_change']); ?></span>
		<?php endif; ?>
		<?php if ($host_block['last_check']??0): ?>
		<span>Checked <?php echo fmt_ago((int)$host_block['last_check']); ?></span>
		<?php endif; ?>
		<?php if ($host_block['plugin_output']??''): ?>
		<span class="shb-output"><?php echo h($host_block['plugin_output']); ?></span>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Summary chips ── -->
<div class="summary-bar">
	<div class="sum-chip chip-ok">    <span class="n"><?php echo $counts['ok'];      ?></span> OK</div>
	<div class="sum-chip chip-warn">  <span class="n"><?php echo $counts['warn'];    ?></span> Warning</div>
	<div class="sum-chip chip-crit">  <span class="n"><?php echo $counts['crit'];    ?></span> Critical</div>
	<div class="sum-chip chip-unkn">  <span class="n"><?php echo $counts['unkn'];    ?></span> Unknown</div>
	<div class="sum-chip chip-pending"><span class="n"><?php echo $counts['pending'];?></span> Pending</div>
</div>

<!-- ── Filter bar ── -->
<div class="filter-bar">
	<span class="filter-lbl">Filter:</span>
	<button class="fbtn active" onclick="filterRows(this,'all')">All (<?php echo $total; ?>)</button>
	<?php if($counts['ok'])     echo '<button class="fbtn" onclick="filterRows(this,\'ok\')">OK ('.$counts['ok'].')</button>'; ?>
	<?php if($counts['warn'])   echo '<button class="fbtn" onclick="filterRows(this,\'warning\')">Warning ('.$counts['warn'].')</button>'; ?>
	<?php if($counts['crit'])   echo '<button class="fbtn" onclick="filterRows(this,\'critical\')">Critical ('.$counts['crit'].')</button>'; ?>
	<?php if($counts['unkn'])   echo '<button class="fbtn" onclick="filterRows(this,\'unknown\')">Unknown ('.$counts['unkn'].')</button>'; ?>
	<?php if($counts['pending'])echo '<button class="fbtn" onclick="filterRows(this,\'pending\')">Pending ('.$counts['pending'].')</button>'; ?>
</div>

<?php if (!$data['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Error reading status file: <?php echo h($data['error']); ?>
</div></div>
<?php elseif (empty($services)): ?>
<div class="data-card"><div class="cell-state">
	<?php if ($filter_host): ?>
	No services found for host <strong><?php echo h($filter_host); ?></strong>.
	<?php else: ?>
	No services found in status file.
	<?php endif; ?>
</div></div>
<?php else: ?>

<div class="data-card">
<table class="dtbl" id="svc-tbl">
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
    $hst   = $host_state[$hn] ?? ['text'=>'UNKNOWN','cls'=>'badge-pending'];
    $heurl = h('host.php?host='.urlencode($hn));
    $haurl = get_action_url(['action_url' => ''], $aurl_map, $hn, '');
    $show_host_row = !$filter_host || count($grouped) > 1;
?>
<?php if ($show_host_row): ?>
<tr class="host-grp-hdr" data-host="<?php echo h($hn); ?>" data-open="1" onclick="toggleGroup(this)">
	<td colspan="6">
		<span class="hgrp-chevron">
			<svg class="chevron-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
		</span>
		<span class="hgrp-name">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;opacity:.6"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
			<a href="<?php echo $heurl; ?>" target="main" onclick="event.stopPropagation()"><?php echo h($hn); ?></a>
		</span>
		<?php echo state_badge($hst); ?>
		<span class="hgrp-count"><?php echo count($svcs); ?> service<?php echo count($svcs)!==1?'s':''; ?></span>
		<span class="hgrp-actions" onclick="event.stopPropagation()">
			<?php if($haurl): ?><a href="<?php echo h($haurl); ?>" target="_blank" title="Performance graph">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
				</a><?php endif; ?>
			<a href="<?php echo $heurl; ?>" target="main" class="detail-lnk" title="Host detail">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
			</a>
		</span>
	</td>
</tr>
<?php endif; ?>
<?php foreach ($svcs as $s):
    $st   = svc_state_info($s);
    $dur  = ($s['last_state_change'] ?? 0) ? fmt_dur($now - (int)$s['last_state_change']) : '-';
    $lc   = ($s['last_check'] ?? 0)        ? fmt_ago((int)$s['last_check'])                : '-';
    $ack  = ($s['problem_has_been_acknowledged'] ?? '0') === '1';
    $dt   = ((int)($s['scheduled_downtime_depth'] ?? 0)) > 0;
    $sn   = $s['service_description'] ?? '';
    $out  = h($s['plugin_output'] ?? '');
    $sdurl = h('service.php?host='.urlencode($hn).'&service='.urlencode($sn));
    $aurl  = get_action_url($s, $aurl_map, $hn, $sn);
    $stl   = strtolower($st['text']);
?>
<tr class="data-row <?php echo $st['row']; ?>" data-host="<?php echo h($hn); ?>" data-state="<?php echo $stl; ?>">
	<td><?php echo state_badge($st); ?></td>
	<td class="c-svc">
		<a href="<?php echo $sdurl; ?>" target="main"><?php echo h($sn); ?></a>
		<?php if($ack) echo '<span class="tag-ack">ACK</span>'; ?>
		<?php if($dt)  echo '<span class="tag-dt">DOWNTIME</span>'; ?>
	</td>
	<td class="c-dur"><?php echo $dur; ?></td>
	<td class="c-lc"><?php echo $lc; ?></td>
	<td class="c-out" title="<?php echo $out; ?>"><?php echo $out; ?></td>
	<td style="white-space:nowrap;">
		<?php if ($aurl): ?>
		<a href="<?php echo h($aurl); ?>" target="_blank" title="Performance graph" class="action-graph">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
		</a>
		<?php endif; ?>
		<a href="<?php echo $sdurl; ?>" target="main" class="action-detail" title="Service detail">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
		</a>
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

function applyAll() {
	document.querySelectorAll('#svc-tbl tbody tr.data-row').forEach(function(r) {
		if (r.dataset.collapsed === '1') { r.style.display = 'none'; return; }
		r.style.display = (_stateFilter === 'all' || r.dataset.state === _stateFilter) ? '' : 'none';
	});
	document.querySelectorAll('#svc-tbl tbody tr.host-grp-hdr').forEach(function(hdr) {
		if (hdr.dataset.open === '0') { hdr.style.display = ''; return; }
		var anyVisible = false;
		document.querySelectorAll('#svc-tbl tbody tr.data-row[data-host="' + hdr.dataset.host + '"]').forEach(function(r) {
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
	document.querySelectorAll('#svc-tbl tbody tr.data-row[data-host="' + host + '"]').forEach(function(r) {
		r.dataset.collapsed = isOpen ? '1' : '0';
		r.style.display = isOpen ? 'none' : '';
	});
	applyAll();
}
function filterRows(btn, state) {
	document.querySelectorAll('.fbtn').forEach(function(b){ b.classList.remove('active'); });
	btn.classList.add('active');
	_stateFilter = state;
	applyAll();
}
</script>
</body>
</html>
