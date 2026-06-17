<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$mcf = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$sf  = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$log = nagios_find_log($mcf);

$period      = in_array($_GET['period'] ?? '', ['7d','48h']) ? $_GET['period'] : '24h';
$period_secs = ['24h'=>86400,'48h'=>172800,'7d'=>604800][$period];
$now         = time();
$win_start   = $now - $period_secs;

/* Parse all alert log entries, reverse to chronological */
$raw     = nagios_parse_log($log, 'alerts', 50000);
$entries = $raw['ok'] ? array_reverse($raw['entries']) : [];

/* Separate host events */
$host_events = [];
foreach ($entries as $e) {
    if ($e['kind'] === 'HOST') $host_events[$e['host']][] = ['ts'=>$e['ts'], 'state'=>$e['state']];
}

/* All known hosts */
$sdata   = nagios_parse_status($sf);
$hst_map = [];
foreach ($sdata['hosts'] as $h) $hst_map[$h['host_name']??''] = $h;

/* Build trend rows */
$rows = [];
foreach ($hst_map as $hn => $h) {
    $lsc  = (int)($h['last_state_change'] ?? 0);
    $st   = host_state_info($h);
    if (isset($host_events[$hn])) {
        $avail = compute_avail_segs($host_events[$hn], $win_start, $now);
    } elseif ($lsc > 0 && $lsc > $win_start) {
        $cur   = $st['text'];
        $prev  = ($cur === 'UP') ? 'DOWN' : 'UP';
        $avail = compute_avail_segs([['ts'=>$lsc,'state'=>$cur]], $win_start, $now, $prev);
    } else {
        $cur   = $st['text'];
        $avail = ['segs'=>[['state'=>$cur,'from'=>$win_start,'to'=>$now,'secs'=>$period_secs]],'pct'=>[$cur=>100.0],'total'=>$period_secs];
    }
    $up_pct = $avail['pct']['UP'] ?? 0;
    $has_prob = ($avail['pct']['DOWN'] ?? 0) + ($avail['pct']['UNREACHABLE'] ?? 0) > 0;
    $rows[] = ['host'=>$hn, 'st'=>$st, 'segs'=>$avail['segs'], 'total'=>$avail['total'], 'up_pct'=>$up_pct, 'has_prob'=>$has_prob];
}
/* Sort: worst uptime first */
usort($rows, function($a,$b){ return $a['up_pct'] <=> $b['up_pct']; });

/* Time axis labels */
function trend_axis_labels($win_start, $now, $period) {
    $n     = ($period === '7d') ? 7 : (($period === '48h') ? 8 : 4);
    $step  = ($now - $win_start) / $n;
    $lbls  = [];
    for ($i = 0; $i <= $n; $i++) {
        $ts   = $win_start + $i * $step;
        $pct  = $i / $n * 100;
        $fmt  = ($period === '7d') ? date('D', $ts) : date('H:i', $ts);
        $lbls[] = ['pct'=>$pct, 'label'=>$fmt];
    }
    return $lbls;
}
$axis_labels = trend_axis_labels($win_start, $now, $period);

$state_colors = [
    'UP'          => 'rgba(34,197,94,0.7)',
    'DOWN'        => 'rgba(239,68,68,0.85)',
    'UNREACHABLE' => 'rgba(168,85,247,0.75)',
    'UNKNOWN'     => 'rgba(100,116,139,0.4)',
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Trends &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
			<span class="phd-page-title">Trends</span>
		</div>
		<div class="phd-count"><?php echo count($rows); ?> hosts &middot; last <?php echo $period; ?></div>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<!-- Period + filter selectors -->
<form method="get" action="" class="filter-bar period-form">
	<span class="filter-lbl">Period:</span>
	<button class="fbtn<?php echo $period==='24h'?' active':''; ?>" name="period" value="24h">24h</button>
	<button class="fbtn<?php echo $period==='48h'?' active':''; ?>" name="period" value="48h">48h</button>
	<button class="fbtn<?php echo $period==='7d'?' active':''; ?>"  name="period" value="7d">7d</button>
	<span class="filter-sep"></span>
	<button class="fbtn active" id="btn-all"  type="button" onclick="showAll(this)">All hosts</button>
	<button class="fbtn"        id="btn-prob" type="button" onclick="showProblems(this)">Problems only</button>
</form>

<?php if (!$raw['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Cannot read log file: <?php echo h($raw['error']); ?>
</div></div>
<?php elseif (empty($rows)): ?>
<div class="data-card"><div class="cell-state">No hosts found.</div></div>
<?php else: ?>

<div class="data-card" style="padding-bottom:4px">

<!-- Time axis header -->
<div class="trend-hdr">
	<div class="trend-hdr-host">Host</div>
	<div class="trend-hdr-bar" style="position:relative;height:14px;">
		<?php foreach ($axis_labels as $lbl): ?>
		<span style="position:absolute;left:<?php echo round($lbl['pct'],1); ?>%;font-size:0.55rem;color:var(--text-lo);transform:translateX(-50%)"><?php echo h($lbl['label']); ?></span>
		<?php endforeach; ?>
	</div>
	<div class="trend-hdr-pct">UP %</div>
</div>

<!-- Trend rows -->
<?php foreach ($rows as $r):
    $hurl  = h('host.php?host='.urlencode($r['host']));
    $total = max($r['total'], 1);
    $up    = $r['up_pct'];
    $cls   = $up >= 99 ? 'avail-pct-hi' : ($up >= 95 ? 'avail-pct-mid' : 'avail-pct-lo');
?>
<div class="trend-row" data-has-prob="<?php echo $r['has_prob']?'1':'0'; ?>">
	<div class="trend-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($r['host']); ?></a></div>
	<div class="trend-bar" title="<?php echo h($r['host']); ?>: <?php echo number_format($up,2); ?>% UP">
		<?php foreach ($r['segs'] as $sg):
		    $w   = round($sg['secs'] / $total * 100, 3);
		    $cls2 = 'trend-seg-'.strtoupper($sg['state']);
		    $col  = $state_colors[$sg['state']] ?? 'rgba(100,116,139,0.4)';
		    if ($w < 0.1) continue;
		?>
		<div class="trend-seg" style="width:<?php echo $w; ?>%;background:<?php echo $col; ?>"
		     title="<?php echo h($sg['state'].' ('.gmdate('H:i:s', $sg['secs']).')'); ?>"></div>
		<?php endforeach; ?>
	</div>
	<div class="trend-pct <?php echo $cls; ?>"><?php echo number_format($up,1); ?>%</div>
</div>
<?php endforeach; ?>

<!-- Legend -->
<div class="trend-legend">
	<div class="trend-legend-item"><div class="trend-legend-dot" style="background:rgba(34,197,94,.7)"></div>UP</div>
	<div class="trend-legend-item"><div class="trend-legend-dot" style="background:rgba(239,68,68,.85)"></div>DOWN</div>
	<div class="trend-legend-item"><div class="trend-legend-dot" style="background:rgba(168,85,247,.75)"></div>UNREACHABLE</div>
	<div class="trend-legend-item"><div class="trend-legend-dot" style="background:rgba(100,116,139,.4)"></div>UNKNOWN</div>
</div>
</div>

<?php endif; ?>

<script>
function showAll(btn) {
    document.querySelectorAll('.trend-row').forEach(function(r){ r.style.display=''; });
    document.getElementById('btn-all').classList.add('active');
    document.getElementById('btn-prob').classList.remove('active');
}
function showProblems(btn) {
    document.querySelectorAll('.trend-row').forEach(function(r){
        r.style.display = r.dataset.hasProb === '1' ? '' : 'none';
    });
    document.getElementById('btn-prob').classList.add('active');
    document.getElementById('btn-all').classList.remove('active');
}
</script>
</body>
</html>
