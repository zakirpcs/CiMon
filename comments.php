<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';

$cd  = nagios_parse_comments_downtime($sf);
$all = $cd['comments'];

usort($all, function($a,$b){ return (int)($b['entry_time']??0) - (int)($a['entry_time']??0); });

$n_host = 0; $n_svc = 0; $n_ack = 0; $n_persist = 0;
foreach ($all as $c) {
    if ($c['kind'] === 'hostcomment') $n_host++;
    else                              $n_svc++;
    if ((int)($c['entry_type']??0) === 4) $n_ack++;
    if ((int)($c['persistent']??0))       $n_persist++;
}
$n_total = count($all);

$etype_label = [1=>'User', 2=>'Downtime', 3=>'Flapping', 4=>'Ack'];
$etype_cls   = [1=>'etype-user-cmt', 2=>'etype-downtime-cmt', 3=>'etype-flap-cmt', 4=>'etype-ack'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Comments &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
			<span class="phd-page-title">Comments</span>
		</div>
		<div class="phd-count"><?php echo $n_total; ?> comment<?php echo $n_total===1?'':'s'; ?></div>
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
	<span class="sum-chip chip-down">Host <strong><?php echo $n_host; ?></strong></span>
	<span class="sum-chip chip-warn">Service <strong><?php echo $n_svc; ?></strong></span>
	<span class="sum-chip chip-ok">Ack <strong><?php echo $n_ack; ?></strong></span>
	<span class="sum-chip chip-unkn">Persistent <strong><?php echo $n_persist; ?></strong></span>
</div>

<div class="filter-bar">
	<span class="filter-lbl">Type:</span>
	<button class="fbtn active" id="f-all"  onclick="setFilter('all',  this)">All (<?php echo $n_total; ?>)</button>
	<button class="fbtn"        id="f-host" onclick="setFilter('host', this)">Host (<?php echo $n_host; ?>)</button>
	<button class="fbtn"        id="f-svc"  onclick="setFilter('svc',  this)">Service (<?php echo $n_svc; ?>)</button>
	<button class="fbtn"        id="f-ack"  onclick="setFilter('ack',  this)">Acknowledgements (<?php echo $n_ack; ?>)</button>
</div>

<?php if (!$cd['ok']): ?>
<div class="data-card"><div class="cell-state is-error">Cannot read status file: <?php echo h($cd['error']); ?></div></div>
<?php elseif (empty($all)): ?>
<div class="data-card"><div class="cell-state cell-state-ok">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;margin-bottom:6px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
	No comments found.
</div></div>
<?php else: ?>

<div class="data-card">
<table class="dtbl" id="cmt-tbl">
<thead><tr>
	<th style="width:24px"></th>
	<th style="width:140px">Host</th>
	<th>Service / Description</th>
	<th style="width:90px">Author</th>
	<th style="width:80px">Type</th>
	<th>Comment</th>
	<th style="width:110px">Added</th>
	<th style="width:55px;text-align:center">Persist</th>
	<th style="width:55px;text-align:center">Expires</th>
</tr></thead>
<tbody>
<?php foreach ($all as $c):
	$is_svc   = ($c['kind'] === 'servicecomment');
	$et       = (int)($c['entry_type'] ?? 1);
	$el       = $etype_label[$et] ?? 'User';
	$ec       = $etype_cls[$et]   ?? 'etype-user-cmt';
	$hn       = $c['host_name'] ?? '';
	$sn       = $c['service_description'] ?? '';
	$author   = $c['author'] ?? '';
	$ctext    = $c['comment_data'] ?? '';
	$etime    = (int)($c['entry_time']  ?? 0);
	$persist  = (int)($c['persistent'] ?? 0);
	$expires  = (int)($c['expires']    ?? 0);
	$exp_ts   = (int)($c['expire_time']?? 0);
	$hurl     = h('host.php?host='.urlencode($hn));
	$dtype    = $is_svc ? 'svc' : 'host';
	$is_ack   = ($et === 4) ? '1' : '0';
?>
<tr class="data-row" data-dtype="<?php echo $dtype; ?>" data-ack="<?php echo $is_ack; ?>">
	<td style="width:24px;padding:4px 6px">
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
	<td><span class="etype <?php echo $ec; ?>"><?php echo h($el); ?></span></td>
	<td class="elog-msg"><?php echo h($ctext); ?></td>
	<td class="c-time"><?php echo $etime ? date('Y-m-d H:i', $etime) : '—'; ?></td>
	<td style="text-align:center">
		<?php if ($persist): ?>
		<svg viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px"><polyline points="20 6 9 17 4 12"/></svg>
		<?php else: ?>
		<span style="color:var(--text-lo);font-size:0.6rem">—</span>
		<?php endif; ?>
	</td>
	<td style="text-align:center;font-size:0.65rem">
		<?php if ($expires && $exp_ts): ?>
		<span style="color:var(--text-lo)" title="<?php echo date('Y-m-d H:i', $exp_ts); ?>"><?php echo fmt_ago($exp_ts); ?></span>
		<?php else: ?>
		<span style="color:var(--text-lo)">Never</span>
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
    document.querySelectorAll('#cmt-tbl tbody tr.data-row').forEach(function(r) {
        var show = true;
        if (_filter === 'host') show = r.dataset.dtype === 'host';
        if (_filter === 'svc')  show = r.dataset.dtype === 'svc';
        if (_filter === 'ack')  show = r.dataset.ack   === '1';
        r.style.display = show ? '' : 'none';
    });
}
</script>
</body>
</html>
