<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$sf      = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';
$refresh = 90;

$data  = nagios_parse_status($sf);
$hosts = $data['hosts'];

/* Sort: DOWN → UNREACHABLE → PENDING → UP */
usort($hosts, function($a, $b) {
    return host_state_info($a)['ord'] <=> host_state_info($b)['ord'];
});

/* Count by state */
$counts = ['up'=>0,'down'=>0,'unreach'=>0,'pending'=>0];
foreach ($hosts as $h) {
    $st = host_state_info($h);
    if ($st['text']==='UP')          $counts['up']++;
    elseif ($st['text']==='DOWN')    $counts['down']++;
    elseif ($st['text']==='UNREACHABLE') $counts['unreach']++;
    else                             $counts['pending']++;
}
$now = time();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Host Status &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
			<span class="phd-page-title">Host Status</span>
		</div>
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
	<div class="sum-chip chip-up">   <span class="n"><?php echo $counts['up'];      ?></span> Up</div>
	<div class="sum-chip chip-down"> <span class="n"><?php echo $counts['down'];    ?></span> Down</div>
	<div class="sum-chip chip-unreach"><span class="n"><?php echo $counts['unreach'];?></span> Unreachable</div>
	<div class="sum-chip chip-pending"><span class="n"><?php echo $counts['pending'];?></span> Pending</div>
</div>

<!-- Filter bar -->
<div class="filter-bar">
	<span class="filter-lbl">Filter:</span>
	<button class="fbtn active" onclick="filterRows(this,'all')">All (<?php echo count($hosts); ?>)</button>
	<?php if($counts['up'])     echo '<button class="fbtn" onclick="filterRows(this,\'up\')">Up ('.$counts['up'].')</button>'; ?>
	<?php if($counts['down'])   echo '<button class="fbtn" onclick="filterRows(this,\'down\')">Down ('.$counts['down'].')</button>'; ?>
	<?php if($counts['unreach'])echo '<button class="fbtn" onclick="filterRows(this,\'unreach\')">Unreachable ('.$counts['unreach'].')</button>'; ?>
	<?php if($counts['pending'])echo '<button class="fbtn" onclick="filterRows(this,\'pending\')">Pending ('.$counts['pending'].')</button>'; ?>
</div>

<?php if (!$data['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Error reading status file: <?php echo h($data['error']); ?>
</div></div>
<?php elseif (empty($hosts)): ?>
<div class="data-card"><div class="cell-state">No hosts found in status file.</div></div>
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
<?php foreach ($hosts as $h):
    $st  = host_state_info($h);
    $dur = ($h['last_state_change'] ?? 0) ? fmt_dur($now - (int)$h['last_state_change']) : '-';
    $lc  = ($h['last_check'] ?? 0)        ? fmt_ago((int)$h['last_check'])                : '-';
    $ack = ($h['problem_has_been_acknowledged'] ?? '0') === '1';
    $dt  = ((int)($h['scheduled_downtime_depth'] ?? 0)) > 0;
    $hn  = h($h['host_name'] ?? '');
    $out = h($h['plugin_output'] ?? '');
    $hurl = h($cgi.'/status.cgi?host='.urlencode($h['host_name']??''));
    $eurl = h('host.php?host='.urlencode($h['host_name']??''));
    $aurl = expand_url($h['action_url'] ?? '', $h['host_name'] ?? '');
?>
<tr class="data-row r-<?php echo strtolower(str_replace(' ','-',$st['text'])); ?> <?php echo $st['row']; ?>"
    data-state="<?php echo strtolower($st['text']); ?>">
	<td><?php echo state_badge($st); ?></td>
	<td class="c-host">
		<a href="<?php echo $eurl; ?>" target="main"><?php echo $hn; ?></a>
		<?php if($ack) echo '<span class="tag-ack">ACK</span>'; ?>
		<?php if($dt)  echo '<span class="tag-dt">DOWNTIME</span>'; ?>
	</td>
	<td class="c-dur"><?php echo $dur; ?></td>
	<td class="c-lc"><?php echo $lc; ?></td>
	<td class="c-out" title="<?php echo $out; ?>"><?php echo $out; ?></td>
	<td style="white-space:nowrap;">
		<?php if ($aurl): ?>
		<a href="<?php echo h($aurl); ?>" target="_blank" title="Performance graph" class="action-graph" title="Graph">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
					</a>
		<?php endif; ?>
		<a href="<?php echo $eurl; ?>" target="main" title="Host detail" class="action-detail" title="Detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _stateFilter = 'all';

function applyAll() {
	document.querySelectorAll('tr.data-row').forEach(function(r) {
		r.style.display = (_stateFilter === 'all' || r.dataset.state === _stateFilter) ? '' : 'none';
	});
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
