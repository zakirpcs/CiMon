<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi   = $cfg['cgi_base_url'];
$mcf   = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$log   = nagios_find_log($mcf);
$limit = 1000;

$data    = nagios_parse_log_all($log, $limit + 500);
$entries = $data['entries'] ?? [];
$total   = count($entries);
$entries = array_slice($entries, 0, $limit);

$type_labels = [
    'host_alert'  => 'Host Alert',
    'svc_alert'   => 'Svc Alert',
    'host_notif'  => 'Host Notif',
    'svc_notif'   => 'Svc Notif',
    'downtime'    => 'Downtime',
    'external'    => 'Ext Cmd',
    'process'     => 'Process',
    'other'       => 'Other',
];
$type_groups = [
    'alerts'  => ['host_alert','svc_alert'],
    'notifs'  => ['host_notif','svc_notif'],
    'system'  => ['downtime','external','process','other'],
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Event Log &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
			<span class="phd-page-title">Event Log</span>
		</div>
		<div class="phd-count">
			<?php echo ($total > $limit) ? $limit.' of '.$total.' entries' : $total.' entries'; ?>
		</div>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<div class="filter-bar">
	<span class="filter-lbl">Type:</span>
	<button class="fbtn active" onclick="setFilter(this,'all')">All</button>
	<button class="fbtn"        onclick="setFilter(this,'alerts')">Alerts</button>
	<button class="fbtn"        onclick="setFilter(this,'notifs')">Notifications</button>
	<button class="fbtn"        onclick="setFilter(this,'downtime')">Downtime</button>
	<button class="fbtn"        onclick="setFilter(this,'external')">Ext Commands</button>
	<button class="fbtn"        onclick="setFilter(this,'process')">Process</button>
</div>

<?php if (!$data['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Cannot read log file: <?php echo h($data['error']); ?>
</div></div>
<?php elseif (empty($entries)): ?>
<div class="data-card"><div class="cell-state">No log entries found.</div></div>
<?php else: ?>

<div class="data-card">
<table class="dtbl">
<thead><tr>
	<th style="width:130px">Time</th>
	<th style="width:82px">Type</th>
	<th>Message</th>
</tr></thead>
<tbody>
<?php foreach ($entries as $e): ?>
<tr class="data-row" data-etype="<?php echo h($e['type']); ?>">
	<td class="c-time"><?php echo h(fmt_ts($e['ts'])); ?></td>
	<td><span class="etype etype-<?php echo h($e['type']); ?>"><?php echo h($type_labels[$e['type']] ?? $e['type']); ?></span></td>
	<td class="elog-msg"><?php echo h($e['message']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _filter = 'all';
var _groups = {
    'alerts':  ['host_alert','svc_alert'],
    'notifs':  ['host_notif','svc_notif'],
    'downtime':['downtime'],
    'external':['external'],
    'process': ['process']
};
function setFilter(btn, f) {
    document.querySelectorAll('.fbtn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    _filter = f;
    var allowed = _groups[f] || null;
    document.querySelectorAll('tr.data-row').forEach(function(r) {
        r.style.display = (!allowed || allowed.indexOf(r.dataset.etype) > -1) ? '' : 'none';
    });
}
</script>
</body>
</html>
