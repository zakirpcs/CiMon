<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi   = $cfg['cgi_base_url'];
$mcf   = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$log   = nagios_find_log($mcf);

$raw     = nagios_parse_log($log, 'alerts', 20000);
$entries = $raw['entries'] ?? [];

/* Group by host */
$by_host = [];
foreach ($entries as $e) {
    $hn = $e['host'];
    if (!isset($by_host[$hn])) $by_host[$hn] = ['total'=>0,'down'=>0,'crit'=>0,'warn'=>0,'unkn'=>0,'up'=>0,'last_ts'=>0,'last_state'=>''];
    $by_host[$hn]['total']++;
    $st = strtoupper($e['state']);
    if ($st==='DOWN')         $by_host[$hn]['down']++;
    elseif($st==='CRITICAL')  $by_host[$hn]['crit']++;
    elseif($st==='WARNING')   $by_host[$hn]['warn']++;
    elseif($st==='UNKNOWN')   $by_host[$hn]['unkn']++;
    elseif($st==='UP'||$st==='OK'||$st==='RECOVERY') $by_host[$hn]['up']++;
    if ($e['ts'] > $by_host[$hn]['last_ts']) { $by_host[$hn]['last_ts']=$e['ts']; $by_host[$hn]['last_state']=$st; }
}
arsort_by_total($by_host);
function arsort_by_total(&$arr){ uasort($arr, function($a,$b){ return $b['total']-$a['total']; }); }

/* Group by service (host+service pair) */
$by_svc = [];
foreach ($entries as $e) {
    if ($e['kind'] !== 'SERVICE') continue;
    $key = $e['host']."\0".$e['service'];
    if (!isset($by_svc[$key])) $by_svc[$key] = ['host'=>$e['host'],'svc'=>$e['service'],'total'=>0,'crit'=>0,'warn'=>0,'unkn'=>0,'ok'=>0,'last_ts'=>0,'last_state'=>''];
    $by_svc[$key]['total']++;
    $st = strtoupper($e['state']);
    if ($st==='CRITICAL')    $by_svc[$key]['crit']++;
    elseif($st==='WARNING')  $by_svc[$key]['warn']++;
    elseif($st==='UNKNOWN')  $by_svc[$key]['unkn']++;
    elseif($st==='OK'||$st==='RECOVERY') $by_svc[$key]['ok']++;
    if ($e['ts'] > $by_svc[$key]['last_ts']) { $by_svc[$key]['last_ts']=$e['ts']; $by_svc[$key]['last_state']=$st; }
}
uasort($by_svc, function($a,$b){ return $b['total']-$a['total']; });

$state_cls = ['DOWN'=>'badge-down','CRITICAL'=>'badge-crit','WARNING'=>'badge-warn',
              'UNKNOWN'=>'badge-unkn','UP'=>'badge-up','OK'=>'badge-ok','RECOVERY'=>'badge-ok'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Alert Summary &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
			<span class="phd-page-title">Alert Summary</span>
		</div>
		<div class="phd-count">
			<?php echo count($entries); ?> total alerts &middot; <?php echo count($by_host); ?> hosts &middot; <?php echo count($by_svc); ?> services
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
	<span class="filter-lbl">View:</span>
	<button class="fbtn active" id="tab-host" onclick="showTab('host')">By Host (<?php echo count($by_host); ?>)</button>
	<button class="fbtn"        id="tab-svc"  onclick="showTab('svc')">By Service (<?php echo count($by_svc); ?>)</button>
</div>

<?php if (!$raw['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Cannot read log file: <?php echo h($raw['error']); ?>
</div></div>
<?php elseif (empty($entries)): ?>
<div class="data-card"><div class="cell-state">No alert entries found in log.</div></div>
<?php else: ?>

<!-- By Host table -->
<div id="view-host" class="data-card">
<table class="dtbl">
<thead><tr>
	<th>Host</th>
	<th style="width:60px;text-align:center">Total</th>
	<th style="width:55px;text-align:center">Down</th>
	<th style="width:55px;text-align:center">Crit</th>
	<th style="width:55px;text-align:center">Warn</th>
	<th style="width:55px;text-align:center">Unkn</th>
	<th style="width:55px;text-align:center">Up/OK</th>
	<th style="width:90px">Breakdown</th>
	<th style="width:100px">Last Alert</th>
</tr></thead>
<tbody>
<?php foreach ($by_host as $hn => $row):
    $hurl = h('host.php?host='.urlencode($hn));
    $max  = max($row['total'], 1);
    $dp   = round($row['down']/$max*100); $cp = round($row['crit']/$max*100);
    $wp   = round($row['warn']/$max*100); $up = round($row['up']/$max*100);
    $scls = $state_cls[$row['last_state']] ?? 'badge-pending';
?>
<tr class="data-row">
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($hn); ?></a></td>
	<td style="text-align:center"><strong class="sum-num"><?php echo $row['total']; ?></strong></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['down']?'#f87171':'var(--text-lo)'; ?>"><?php echo $row['down']; ?></span></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['crit']?'#f87171':'var(--text-lo)'; ?>"><?php echo $row['crit']; ?></span></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['warn']?'#fbbf24':'var(--text-lo)'; ?>"><?php echo $row['warn']; ?></span></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['unkn']?'#c084fc':'var(--text-lo)'; ?>"><?php echo $row['unkn']; ?></span></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['up']?'#4ade80':'var(--text-lo)'; ?>"><?php echo $row['up']; ?></span></td>
	<td>
		<div class="sum-bar-wrap">
			<?php if ($dp) echo '<div class="sum-bar-down" style="width:'.$dp.'%"></div>'; ?>
			<?php if ($cp) echo '<div class="sum-bar-crit" style="width:'.$cp.'%"></div>'; ?>
			<?php if ($wp) echo '<div class="sum-bar-warn" style="width:'.$wp.'%"></div>'; ?>
			<?php if ($up) echo '<div class="sum-bar-ok"   style="width:'.$up.'%"></div>'; ?>
		</div>
	</td>
	<td>
		<span class="badge <?php echo $scls; ?>"><?php echo h($row['last_state']); ?></span>
		<span class="avail-sub"><?php echo $row['last_ts'] ? fmt_ago($row['last_ts']) : '-'; ?></span>
	</td>
</tr>
<?php endforeach; ?>
<?php if (empty($by_host)): ?><tr><td colspan="9"><div class="cell-state">No host alerts found.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- By Service table -->
<div id="view-svc" class="data-card" style="display:none">
<table class="dtbl">
<thead><tr>
	<th style="width:160px">Host</th>
	<th>Service</th>
	<th style="width:60px;text-align:center">Total</th>
	<th style="width:55px;text-align:center">Crit</th>
	<th style="width:55px;text-align:center">Warn</th>
	<th style="width:55px;text-align:center">Unkn</th>
	<th style="width:55px;text-align:center">OK</th>
	<th style="width:90px">Breakdown</th>
	<th style="width:100px">Last Alert</th>
</tr></thead>
<tbody>
<?php foreach ($by_svc as $key => $row):
    $hurl = h('host.php?host='.urlencode($row['host']));
    $max  = max($row['total'], 1);
    $cp   = round($row['crit']/$max*100); $wp = round($row['warn']/$max*100);
    $kp   = round($row['unkn']/$max*100); $op = round($row['ok']/$max*100);
    $scls = $state_cls[$row['last_state']] ?? 'badge-pending';
?>
<tr class="data-row">
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($row['host']); ?></a></td>
	<td class="c-svc"><a href="<?php echo h('service.php?host='.urlencode($row['host']).'&service='.urlencode($row['svc'])); ?>" target="main"><?php echo h($row['svc']); ?></a></td>
	<td style="text-align:center"><strong class="sum-num"><?php echo $row['total']; ?></strong></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['crit']?'#f87171':'var(--text-lo)'; ?>"><?php echo $row['crit']; ?></span></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['warn']?'#fbbf24':'var(--text-lo)'; ?>"><?php echo $row['warn']; ?></span></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['unkn']?'#c084fc':'var(--text-lo)'; ?>"><?php echo $row['unkn']; ?></span></td>
	<td style="text-align:center"><span class="sum-num" style="color:<?php echo $row['ok']?'#4ade80':'var(--text-lo)'; ?>"><?php echo $row['ok']; ?></span></td>
	<td>
		<div class="sum-bar-wrap">
			<?php if ($cp) echo '<div class="sum-bar-crit" style="width:'.$cp.'%"></div>'; ?>
			<?php if ($wp) echo '<div class="sum-bar-warn" style="width:'.$wp.'%"></div>'; ?>
			<?php if ($op) echo '<div class="sum-bar-ok"   style="width:'.$op.'%"></div>'; ?>
		</div>
	</td>
	<td>
		<span class="badge <?php echo $scls; ?>"><?php echo h($row['last_state']); ?></span>
		<span class="avail-sub"><?php echo $row['last_ts'] ? fmt_ago($row['last_ts']) : '-'; ?></span>
	</td>
</tr>
<?php endforeach; ?>
<?php if (empty($by_svc)): ?><tr><td colspan="9"><div class="cell-state">No service alerts found.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
function showTab(tab) {
    document.getElementById('view-host').style.display = tab==='host' ? '' : 'none';
    document.getElementById('view-svc').style.display  = tab==='svc'  ? '' : 'none';
    document.getElementById('tab-host').classList.toggle('active', tab==='host');
    document.getElementById('tab-svc').classList.toggle('active',  tab==='svc');
}
</script>
</body>
</html>
