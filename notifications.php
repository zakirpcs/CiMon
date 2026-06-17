<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi   = $cfg['cgi_base_url'];
$mcf   = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$log   = nagios_find_log($mcf);
$limit = 500;

$host_filter = isset($_GET['host']) && $_GET['host'] !== 'all' ? trim($_GET['host']) : '';

$data    = nagios_parse_log($log, 'notifications', 2000);
$entries = $data['entries'] ?? [];

if ($host_filter !== '') {
    $entries = array_values(array_filter($entries, function($e) use ($host_filter) {
        return strcasecmp($e['host'], $host_filter) === 0;
    }));
}

$total   = count($entries);
$entries = array_slice($entries, 0, $limit);

$state_cls = [
    'UP'=>'badge-up','DOWN'=>'badge-down','UNREACHABLE'=>'badge-unreach',
    'OK'=>'badge-ok','WARNING'=>'badge-warn','CRITICAL'=>'badge-crit','UNKNOWN'=>'badge-unkn',
    'RECOVERY'=>'badge-ok','ACKNOWLEDGEMENT'=>'badge-pending','CUSTOM'=>'badge-pending',
    'FLAPPINGSTART'=>'badge-warn','FLAPPINGSTOP'=>'badge-ok',
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Notifications &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
			<span class="phd-page-title">Notifications</span>
			<?php if($host_filter): ?><span class="phd-page-filter">&mdash; <?php echo h($host_filter); ?></span><?php endif; ?>
		</div>
	</div>
	<div class="phd-right">
		<div class="phd-count">
			<?php if ($total > $limit): ?>
			<?php echo $limit; ?> of <?php echo $total; ?> entries
			<?php else: ?>
			<?php echo $total; ?> entries
			<?php endif; ?>
		</div>
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<!-- Filter bar -->
<div class="filter-bar">
	<span class="filter-lbl">Type:</span>
	<button class="fbtn active" onclick="filterRows(this,'all')">All</button>
	<button class="fbtn"        onclick="filterRows(this,'host')">Host</button>
	<button class="fbtn"        onclick="filterRows(this,'service')">Service</button>
</div>

<?php if (!$data['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Cannot read log file: <?php echo h($data['error']); ?>
</div></div>
<?php elseif (empty($entries)): ?>
<div class="data-card"><div class="cell-state">No notification entries found.</div></div>
<?php else: ?>

<div class="data-card">
<table class="dtbl">
<thead><tr>
	<th style="width:130px">Time</th>
	<th style="width:60px">Type</th>
	<th style="width:120px">Contact</th>
	<th style="width:150px">Host</th>
	<th style="width:170px">Service</th>
	<th style="width:110px">State</th>
	<th>Output</th>
</tr></thead>
<tbody>
<?php foreach ($entries as $e):
    $scls = $state_cls[strtoupper($e['state'])] ?? 'badge-pending';
    $hurl = h('host.php?host='.urlencode($e['host']));
    $tkind = strtolower($e['kind']);
?>
<tr class="data-row" data-kind="<?php echo $tkind; ?>">
	<td class="c-time"><?php echo h(fmt_ts($e['ts'])); ?></td>
	<td>
		<?php if($e['kind']==='HOST')    echo '<span class="type-host">HOST</span>'; ?>
		<?php if($e['kind']==='SERVICE') echo '<span class="type-service">SVC</span>'; ?>
	</td>
	<td class="c-contact"><?php echo h($e['contact']); ?></td>
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($e['host']); ?></a></td>
	<td class="c-svc"><?php echo $e['service'] ? '<a href="'.h('service.php?host='.urlencode($e['host']).'&service='.urlencode($e['service'])).'" target="main">'.h($e['service']).'</a>' : '<span class="c-empty">—</span>'; ?></td>
	<td><span class="badge <?php echo $scls; ?>"><?php echo h($e['state']); ?></span></td>
	<td class="c-out" title="<?php echo h($e['output']); ?>"><?php echo h($e['output']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _kindFilter = 'all';

function applyAll() {
	document.querySelectorAll('tr.data-row').forEach(function(r) {
		r.style.display = (_kindFilter === 'all' || r.dataset.kind === _kindFilter) ? '' : 'none';
	});
}
function filterRows(btn, kind) {
	document.querySelectorAll('.fbtn').forEach(function(b){ b.classList.remove('active'); });
	btn.classList.add('active');
	_kindFilter = kind;
	applyAll();
}
</script>
</body>
</html>
