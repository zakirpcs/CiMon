<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi  = $cfg['cgi_base_url'];
$mcf  = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$log  = nagios_find_log($mcf);

$period = in_array($_GET['period'] ?? '', ['7d','30d']) ? $_GET['period'] : '24h';
$period_secs   = ['24h'=>86400,'7d'=>604800,'30d'=>2592000][$period];
$window_start  = time() - $period_secs;

/* Bucket configuration */
if ($period === '24h') {
    $bucket_secs = 3600;    /* 1-hour buckets → 24 bars */
    $fmt_lbl     = function($ts) { return date('H:i', $ts); };
} elseif ($period === '7d') {
    $bucket_secs = 86400;   /* 1-day buckets → 7 bars */
    $fmt_lbl     = function($ts) { return date('D d', $ts); };
} else {
    $bucket_secs = 86400;   /* 1-day buckets → 30 bars */
    $fmt_lbl     = function($ts) { return date('M d', $ts); };
}

$n_buckets = (int)ceil($period_secs / $bucket_secs);

/* Build bucket array (oldest → newest) */
$buckets = [];
for ($i = 0; $i < $n_buckets; $i++) {
    $bstart = $window_start + $i * $bucket_secs;
    $buckets[$i] = ['label' => $fmt_lbl($bstart), 'host'=>0, 'svc'=>0];
}

$raw     = nagios_parse_log($log, 'alerts', 30000);
$entries = $raw['entries'] ?? [];

foreach ($entries as $e) {
    if ($e['ts'] < $window_start) continue;
    $bi = (int)floor(($e['ts'] - $window_start) / $bucket_secs);
    if ($bi < 0 || $bi >= $n_buckets) continue;
    if ($e['kind'] === 'HOST')    $buckets[$bi]['host']++;
    else                          $buckets[$bi]['svc']++;
}

/* Find max for scaling */
$max_val = 0;
foreach ($buckets as $b) $max_val = max($max_val, $b['host'] + $b['svc']);
$total_alerts = array_sum(array_column($buckets,'host')) + array_sum(array_column($buckets,'svc'));
$total_host   = array_sum(array_column($buckets,'host'));
$total_svc    = array_sum(array_column($buckets,'svc'));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Alert Histogram &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
			<span class="phd-page-title">Alert Histogram</span>
		</div>
		<div class="phd-count"><?php echo $total_alerts; ?> alerts in last <?php echo $period; ?></div>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<!-- Period selector -->
<form method="get" action="" class="filter-bar period-form">
	<span class="filter-lbl">Period:</span>
	<button class="fbtn<?php echo $period==='24h'?' active':''; ?>" name="period" value="24h">24h</button>
	<button class="fbtn<?php echo $period==='7d'?' active':''; ?>"  name="period" value="7d">7d</button>
	<button class="fbtn<?php echo $period==='30d'?' active':''; ?>" name="period" value="30d">30d</button>
	<span class="filter-sep"></span>
	<span class="filter-lbl">Host alerts: <strong style="color:#f87171"><?php echo $total_host; ?></strong></span>
	<span class="filter-lbl">Svc alerts: <strong style="color:#fbbf24"><?php echo $total_svc; ?></strong></span>
</form>

<?php if (!$raw['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Cannot read log file: <?php echo h($raw['error']); ?>
</div></div>
<?php else: ?>

<div class="data-card">
<?php if ($total_alerts === 0): ?>
<div class="hist-empty">No alerts in the selected period.</div>
<?php else: ?>
<div class="hist-wrap">
<div class="hist-chart">
<?php foreach ($buckets as $b):
    $tot = $b['host'] + $b['svc'];
    if ($max_val > 0) {
        $h_pct  = round($b['host'] / $max_val * 100);
        $s_pct  = round($b['svc']  / $max_val * 100);
    } else {
        $h_pct = $s_pct = 0;
    }
    $title = h($b['label'].': '.$b['host'].' host, '.$b['svc'].' svc ('.$tot.' total)');
?>
<div class="hist-col" title="<?php echo $title; ?>">
	<?php if ($h_pct): ?><div class="hist-bar hist-host" style="height:<?php echo $h_pct; ?>%"></div><?php endif; ?>
	<?php if ($s_pct): ?><div class="hist-bar hist-svc"  style="height:<?php echo $s_pct; ?>%"></div><?php endif; ?>
	<?php if (!$h_pct && !$s_pct): ?><div class="hist-bar" style="height:1px;opacity:.2;background:var(--text-lo)"></div><?php endif; ?>
	<div class="hist-lbl"><?php echo h($b['label']); ?></div>
</div>
<?php endforeach; ?>
</div>
</div>
<div class="hist-axis-line"></div>
<div class="hist-legend">
	<span><span class="hist-legend-box" style="background:rgba(239,68,68,.7)"></span>Host alerts</span>
	<span><span class="hist-legend-box" style="background:rgba(245,158,11,.55)"></span>Service alerts</span>
	<span style="margin-left:auto;color:var(--text-lo)">Each bar = <?php echo $bucket_secs >= 86400 ? '1 day' : '1 hour'; ?></span>
</div>
<?php endif; ?>
</div>

<?php endif; ?>
</body>
</html>
