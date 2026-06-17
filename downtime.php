<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';
$now = time();

$cd  = nagios_parse_comments_downtime($sf);
$all = $cd['downtimes'];

usort($all, function($a,$b){ return (int)($a['start_time']??0) - (int)($b['start_time']??0); });

$n_host   = 0; $n_svc = 0; $n_active = 0; $n_pending = 0;
foreach ($all as $d) {
    if ($d['kind'] === 'hostdowntime') $n_host++; else $n_svc++;
    if ((int)($d['is_in_effect']??0))  $n_active++; else $n_pending++;
}
$n_total = count($all);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Scheduled Downtime &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>
			<span class="phd-page-title">Scheduled Downtime</span>
		</div>
		<div class="phd-count"><?php echo $n_total; ?> downtime<?php echo $n_total===1?'':'s'; ?></div>
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
	<span class="sum-chip chip-down">Active <strong><?php echo $n_active; ?></strong></span>
	<span class="sum-chip chip-warn">Pending <strong><?php echo $n_pending; ?></strong></span>
	<span class="sum-chip chip-ok">Host <strong><?php echo $n_host; ?></strong></span>
	<span class="sum-chip chip-unkn">Service <strong><?php echo $n_svc; ?></strong></span>
</div>

<div class="filter-bar">
	<span class="filter-lbl">Show:</span>
	<button class="fbtn active" id="f-all"     onclick="setFilter('all',     this)">All (<?php echo $n_total; ?>)</button>
	<button class="fbtn"        id="f-active"  onclick="setFilter('active',  this)">Active (<?php echo $n_active; ?>)</button>
	<button class="fbtn"        id="f-pending" onclick="setFilter('pending', this)">Pending (<?php echo $n_pending; ?>)</button>
	<button class="fbtn"        id="f-host"    onclick="setFilter('host',    this)">Hosts (<?php echo $n_host; ?>)</button>
	<button class="fbtn"        id="f-svc"     onclick="setFilter('svc',     this)">Services (<?php echo $n_svc; ?>)</button>
</div>

<?php if (!$cd['ok']): ?>
<div class="data-card"><div class="cell-state is-error">Cannot read status file: <?php echo h($cd['error']); ?></div></div>
<?php elseif (empty($all)): ?>
<div class="data-card"><div class="cell-state cell-state-ok">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;margin-bottom:6px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
	No scheduled downtime.
</div></div>
<?php else: ?>

<div class="data-card">
<table class="dtbl" id="dt-tbl">
<thead><tr>
	<th style="width:65px">Status</th>
	<th style="width:24px"></th>
	<th style="width:140px">Host</th>
	<th>Service</th>
	<th style="width:80px">Author</th>
	<th>Comment</th>
	<th style="width:110px">Start</th>
	<th style="width:110px">End</th>
	<th style="width:60px">Duration</th>
	<th style="width:55px;text-align:center">Fixed</th>
</tr></thead>
<tbody>
<?php foreach ($all as $d):
	$is_svc    = ($d['kind'] === 'servicedowntime');
	$active    = (int)($d['is_in_effect'] ?? 0);
	$hn        = $d['host_name'] ?? '';
	$sn        = $d['service_description'] ?? '';
	$author    = $d['author'] ?? '';
	$ctext     = $d['comment_data'] ?? '';
	$start     = (int)($d['start_time'] ?? 0);
	$end       = (int)($d['end_time']   ?? 0);
	$fixed     = (int)($d['fixed']      ?? 0);
	$dur       = (int)($d['duration']   ?? ($end - $start));
	$hurl      = h('host.php?host='.urlencode($hn));
	$dtype     = $is_svc ? 'svc' : 'host';
	$dact      = $active ? 'active' : 'pending';
?>
<tr class="data-row" data-dtype="<?php echo $dtype; ?>" data-status="<?php echo $dact; ?>">
	<td>
		<?php if ($active): ?>
		<span class="badge badge-down" style="background:rgba(22,163,74,0.2);color:#4ade80;border-color:rgba(22,163,74,0.3)">Active</span>
		<?php else: ?>
		<span class="badge badge-pending">Pending</span>
		<?php endif; ?>
	</td>
	<td style="padding:4px 6px">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;color:var(--text-lo)">
		<?php if ($is_svc): ?>
			<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
		<?php else: ?>
			<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
		<?php endif; ?>
		</svg>
	</td>
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($hn); ?></a></td>
	<td class="c-svc"><?php echo $sn !== '' ? h($sn) : '<span style="color:var(--text-lo)">—</span>'; ?></td>
	<td style="font-size:0.68rem;color:var(--text-lo)"><?php echo h($author); ?></td>
	<td class="elog-msg"><?php echo h($ctext); ?></td>
	<td class="c-time"><?php echo $start ? date('Y-m-d H:i', $start) : '—'; ?></td>
	<td class="c-time"><?php echo $end   ? date('Y-m-d H:i', $end)   : '—'; ?></td>
	<td style="font-size:0.68rem;color:var(--text-lo);white-space:nowrap"><?php echo fmt_dur($dur); ?></td>
	<td style="text-align:center">
		<?php if ($fixed): ?>
		<span style="color:#4ade80;font-size:0.65rem;font-weight:700">Fixed</span>
		<?php else: ?>
		<span style="color:var(--text-lo);font-size:0.65rem">Flex</span>
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
    document.querySelectorAll('#dt-tbl tbody tr.data-row').forEach(function(r) {
        var show = true;
        if (_filter === 'active')  show = r.dataset.status === 'active';
        if (_filter === 'pending') show = r.dataset.status === 'pending';
        if (_filter === 'host')    show = r.dataset.dtype  === 'host';
        if (_filter === 'svc')     show = r.dataset.dtype  === 'svc';
        r.style.display = show ? '' : 'none';
    });
}
</script>
</body>
</html>
