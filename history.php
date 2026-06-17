<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi    = $cfg['cgi_base_url'];
$mcf    = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$log    = nagios_find_log($mcf);
$limit  = 500;

/* Optional host filter from query string */
$host_filter = isset($_GET['host']) && $_GET['host'] !== 'all' ? trim($_GET['host']) : '';

$data    = nagios_parse_log($log, 'alerts', 2000);
$entries = $data['entries'] ?? [];

/* Apply host filter */
if ($host_filter !== '') {
    $entries = array_filter($entries, function($e) use ($host_filter) {
        return strcasecmp($e['host'], $host_filter) === 0;
    });
    $entries = array_values($entries);
}

/* Trim to display limit */
$total   = count($entries);
$entries = array_slice($entries, 0, $limit);

$state_cls = [
    'UP'=>'badge-up','DOWN'=>'badge-down','UNREACHABLE'=>'badge-unreach',
    'OK'=>'badge-ok','WARNING'=>'badge-warn','CRITICAL'=>'badge-crit','UNKNOWN'=>'badge-unkn',
    'RECOVERY'=>'badge-ok',
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Alert History &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
			<span class="phd-page-title">Alert History</span>
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
	<span class="filter-lbl">Show:</span>
	<button class="fbtn active"  onclick="filterRows(this,'all')">All</button>
	<button class="fbtn"         onclick="filterRows(this,'host')">Host</button>
	<button class="fbtn"         onclick="filterRows(this,'service')">Service</button>
	<span class="filter-lbl filter-lbl-sep">State:</span>
	<button class="fbtn"         onclick="filterState(this,'DOWN')">Down</button>
	<button class="fbtn"         onclick="filterState(this,'CRITICAL')">Critical</button>
	<button class="fbtn"         onclick="filterState(this,'WARNING')">Warning</button>
	<button class="fbtn"         onclick="filterState(this,'UNREACHABLE')">Unreachable</button>
	<button class="fbtn"         onclick="filterState(this,'UP')">Up / Recovery</button>
</div>

<?php if (!$data['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Cannot read log file: <?php echo h($data['error']); ?>
</div></div>
<?php elseif (empty($entries)): ?>
<div class="data-card"><div class="cell-state">No alert entries found.</div></div>
<?php else: ?>

<div class="data-card">
<table class="dtbl">
<thead><tr>
	<th style="width:130px">Time</th>
	<th style="width:60px">Type</th>
	<th style="width:150px">Host</th>
	<th style="width:170px">Service</th>
	<th style="width:95px">State</th>
	<th>Output</th>
</tr></thead>
<tbody>
<?php foreach ($entries as $e):
    $scls  = $state_cls[strtoupper($e['state'])] ?? 'badge-pending';
    $tkind = strtolower($e['kind']);
    $hurl  = h('host.php?host='.urlencode($e['host']));
?>
<tr class="data-row" data-kind="<?php echo $tkind; ?>" data-state="<?php echo h(strtoupper($e['state'])); ?>">
	<td class="c-time"><?php echo h(fmt_ts($e['ts'])); ?></td>
	<td>
		<?php if($e['kind']==='HOST')   echo '<span class="type-host">HOST</span>'; ?>
		<?php if($e['kind']==='SERVICE') echo '<span class="type-service">SVC</span>'; ?>
	</td>
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($e['host']); ?></a></td>
	<td class="c-svc"><?php echo $e['service'] ? '<a href="'.h('service.php?host='.urlencode($e['host']).'&service='.urlencode($e['service'])).'" target="main">'.h($e['service']).'</a>' : '<span class="c-empty">—</span>'; ?></td>
	<td><span class="badge <?php echo $scls; ?>"><?php echo h($e['state']); ?></span>
		<?php if ($e['state_type']) echo '<span class="c-state-type">'.h($e['state_type']).'</span>'; ?>
	</td>
	<td class="c-out" title="<?php echo h($e['output']); ?>"><?php echo h($e['output']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _kindFilter  = 'all';
var _stateFilter = 'all';

function applyFilters() {
	document.querySelectorAll('tr.data-row').forEach(function(r) {
		var kOk = _kindFilter === 'all' || r.dataset.kind === _kindFilter;
		var sOk = _stateFilter === 'all' || r.dataset.state === _stateFilter
		       || (_stateFilter === 'UP' && (r.dataset.state === 'UP' || r.dataset.state === 'RECOVERY' || r.dataset.state === 'OK'));
		r.style.display = (kOk && sOk) ? '' : 'none';
	});
}
function filterRows(btn, kind) {
	document.querySelectorAll('.fbtn').forEach(function(b){
		if (b.onclick && b.onclick.toString().indexOf('filterRows') > -1) b.classList.remove('active');
	});
	btn.classList.add('active');
	_kindFilter = kind;
	applyFilters();
}
function filterState(btn, state) {
	document.querySelectorAll('.fbtn').forEach(function(b){
		if (b.onclick && b.onclick.toString().indexOf('filterState') > -1) b.classList.remove('active');
	});
	if (_stateFilter === state) { _stateFilter = 'all'; } else { btn.classList.add('active'); _stateFilter = state; }
	applyFilters();
}
</script>
</body>
</html>
