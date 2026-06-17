<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$mcf     = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$sf      = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$log     = nagios_find_log($mcf);
$refresh = 90;

$period      = in_array($_GET['period'] ?? '', ['7d','30d']) ? $_GET['period'] : '24h';
$period_secs = ['24h'=>86400,'7d'=>604800,'30d'=>2592000][$period];
$period_lbl  = ['24h'=>'Last 24 hours','7d'=>'Last 7 days','30d'=>'Last 30 days'][$period];
$now         = time();
$win_start   = $now - $period_secs;

/* Parse alert log */
$raw     = nagios_parse_log($log, 'alerts', 50000);
$entries = $raw['ok'] ? array_reverse($raw['entries']) : [];

$host_events = [];
$svc_events  = [];
foreach ($entries as $e) {
    if ($e['kind'] === 'HOST') {
        $host_events[$e['host']][] = ['ts'=>$e['ts'], 'state'=>$e['state']];
    } else {
        $svc_events[$e['host']."\0".$e['service']][] = ['ts'=>$e['ts'], 'state'=>$e['state']];
    }
}

$sdata   = nagios_parse_status($sf);
$hst_map = [];
foreach ($sdata['hosts'] as $h) $hst_map[$h['host_name']??''] = $h;

function avail_cls($pct) {
    if ($pct >= 99.0) return 'avail-pct-hi';
    if ($pct >= 95.0) return 'avail-pct-mid';
    return 'avail-pct-lo';
}

/* Build host rows */
$host_rows = [];
foreach ($hst_map as $hn => $h) {
    $lsc = (int)($h['last_state_change'] ?? 0);
    $st  = host_state_info($h);
    if (isset($host_events[$hn])) {
        $avail = compute_avail_segs($host_events[$hn], $win_start, $now);
    } elseif ($lsc > 0 && $lsc > $win_start) {
        $cur = $st['text']; $prev = ($cur==='UP') ? 'DOWN' : 'UP';
        $avail = compute_avail_segs([['ts'=>$lsc,'state'=>$cur]], $win_start, $now, $prev);
    } else {
        $cur = $st['text'];
        $avail = ['segs'=>[['state'=>$cur,'from'=>$win_start,'to'=>$now,'secs'=>$period_secs]],'pct'=>[$cur=>100.0],'total'=>$period_secs];
    }
    $up_pct  = $avail['pct']['UP'] ?? 0;
    $bad_pct = ($avail['pct']['DOWN'] ?? 0) + ($avail['pct']['UNREACHABLE'] ?? 0);
    $host_rows[] = ['host'=>$hn,'state'=>$st,'pct'=>$avail['pct'],'segs'=>$avail['segs'],'up_pct'=>$up_pct,'bad_pct'=>$bad_pct];
}
usort($host_rows, fn($a,$b) => $a['up_pct'] <=> $b['up_pct']);

/* Build service rows */
$svc_rows = [];
foreach ($sdata['services'] as $s) {
    $hn = $s['host_name']??''; $sn = $s['service_description']??'';
    $key = $hn."\0".$sn; $lsc = (int)($s['last_state_change']??0);
    $st  = svc_state_info($s);
    if (isset($svc_events[$key])) {
        $avail = compute_avail_segs($svc_events[$key], $win_start, $now);
    } elseif ($lsc > 0 && $lsc > $win_start) {
        $cur = $st['text']; $prev = ($cur==='OK') ? 'CRITICAL' : 'OK';
        $avail = compute_avail_segs([['ts'=>$lsc,'state'=>$cur]], $win_start, $now, $prev);
    } else {
        $cur = $st['text'];
        $avail = ['segs'=>[['state'=>$cur,'from'=>$win_start,'to'=>$now,'secs'=>$period_secs]],'pct'=>[$cur=>100.0],'total'=>$period_secs];
    }
    $ok_pct  = $avail['pct']['OK'] ?? 0;
    $bad_pct = ($avail['pct']['CRITICAL']??0) + ($avail['pct']['WARNING']??0) + ($avail['pct']['UNKNOWN']??0);
    $svc_rows[] = ['host'=>$hn,'svc'=>$sn,'state'=>$st,'pct'=>$avail['pct'],'segs'=>$avail['segs'],'ok_pct'=>$ok_pct,'bad_pct'=>$bad_pct];
}
usort($svc_rows, fn($a,$b) => $a['ok_pct'] <=> $b['ok_pct']);

/* Summary stats */
$h_total = count($host_rows);
$h_full  = count(array_filter($host_rows, fn($r) => $r['up_pct'] >= 99.9));
$h_issue = count(array_filter($host_rows, fn($r) => $r['bad_pct'] > 0));
$h_avg   = $h_total ? array_sum(array_column($host_rows,'up_pct')) / $h_total : 100.0;

$s_total = count($svc_rows);
$s_full  = count(array_filter($svc_rows, fn($r) => $r['ok_pct'] >= 99.9));
$s_issue = count(array_filter($svc_rows, fn($r) => $r['bad_pct'] > 0));
$s_avg   = $s_total ? array_sum(array_column($svc_rows,'ok_pct')) / $s_total : 100.0;

function avail_bar($segs, $total) {
    $out = '<div class="avail-bar">';
    foreach ($segs as $sg) {
        if ($sg['secs'] <= 0) continue;
        $pct = round($sg['secs'] / max($total,1) * 100, 2);
        $cls = 'avail-seg-'.strtolower(str_replace([' ','UNREACHABLE'],['','unreach'], $sg['state']));
        $out .= '<div class="avail-seg '.$cls.'" style="width:'.$pct.'%"></div>';
    }
    return $out.'</div>';
}

function stat_border($pct) {
    if ($pct >= 99.0) return 'avail-stat-hi';
    if ($pct >= 95.0) return 'avail-stat-warn';
    return 'avail-stat-bad';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Availability &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<meta http-equiv="refresh" content="<?php echo $refresh; ?>">
<style>
body     { padding: 10px 14px; }
.page-hd { margin: -10px -14px 10px; }
.data-card { margin-bottom: 8px; }

/* ── Summary stat cards ── */
.avail-summary { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.avail-stat {
    flex: 1; min-width: 130px; padding: 10px 14px;
    background: var(--pg-card); border: 1px solid rgba(0,0,0,0.08);
    border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border-top: 2px solid transparent;
}
.avail-stat-hi   { border-top-color: #16a34a; }
.avail-stat-warn { border-top-color: #d97706; }
.avail-stat-bad  { border-top-color: #dc2626; }
.avail-stat-label { font-size: 0.55rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.08em; color: var(--text-lo); margin-bottom: 5px; }
.avail-stat-val { font-size: 1.15rem; font-weight: 800; line-height: 1;
    font-variant-numeric: tabular-nums; }
.avail-stat-sub { font-size: 0.59rem; color: var(--text-lo); margin-top: 4px; }

/* ── Filter bar (period + view in one row) ── */
.avail-filter { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }

/* ── Wider availability bar ── */
.avail-bar { width: 150px; height: 8px; border-radius: 4px; }

/* ── Compact table ── */
.dtbl th, .dtbl td { padding: 6px 10px; }
.dtbl th:first-child, .dtbl td:first-child { padding-left: 14px; }

/* ── Clickable service name ── */
.c-svc a { color: var(--text-body); font-weight: 500; }
.c-svc a:hover { color: var(--amber); }
</style>
</head>
<body>

<div class="page-hd">
    <div class="phd-left">
        <div class="phd-page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span class="phd-page-title">Availability</span>
        </div>
        <div class="phd-count"><?php echo $h_total; ?> host<?php echo $h_total!==1?'s':''; ?> &middot; <?php echo $s_total; ?> service<?php echo $s_total!==1?'s':''; ?></div>
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

<!-- Summary stats -->
<div class="avail-summary">
    <div class="avail-stat <?php echo stat_border($h_avg); ?>">
        <div class="avail-stat-label">Host Uptime (avg)</div>
        <div class="avail-stat-val <?php echo avail_cls($h_avg); ?>"><?php echo number_format($h_avg,2); ?>%</div>
        <div class="avail-stat-sub"><?php echo $period_lbl; ?></div>
    </div>
    <div class="avail-stat <?php echo $h_issue===0?'avail-stat-hi':($h_issue<ceil($h_total*0.1)?'avail-stat-warn':'avail-stat-bad'); ?>">
        <div class="avail-stat-label">Hosts Fully Up</div>
        <div class="avail-stat-val <?php echo $h_full===$h_total?'avail-pct-hi':'avail-pct-lo'; ?>"><?php echo $h_full; ?> <span style="font-size:0.7rem;font-weight:500;color:var(--text-lo)">/ <?php echo $h_total; ?></span></div>
        <div class="avail-stat-sub"><?php echo $h_issue; ?> had outage<?php echo $h_issue!==1?'s':''; ?></div>
    </div>
    <div class="avail-stat <?php echo stat_border($s_avg); ?>">
        <div class="avail-stat-label">Service Availability (avg)</div>
        <div class="avail-stat-val <?php echo avail_cls($s_avg); ?>"><?php echo number_format($s_avg,2); ?>%</div>
        <div class="avail-stat-sub"><?php echo $period_lbl; ?></div>
    </div>
    <div class="avail-stat <?php echo $s_issue===0?'avail-stat-hi':($s_issue<ceil($s_total*0.1)?'avail-stat-warn':'avail-stat-bad'); ?>">
        <div class="avail-stat-label">Services Fully OK</div>
        <div class="avail-stat-val <?php echo $s_full===$s_total?'avail-pct-hi':'avail-pct-lo'; ?>"><?php echo $s_full; ?> <span style="font-size:0.7rem;font-weight:500;color:var(--text-lo)">/ <?php echo $s_total; ?></span></div>
        <div class="avail-stat-sub"><?php echo $s_issue; ?> had issue<?php echo $s_issue!==1?'s':''; ?></div>
    </div>
</div>

<!-- Period + view filter -->
<form method="get" action="" class="avail-filter">
    <span class="filter-lbl">Period:</span>
    <button class="fbtn<?php echo $period==='24h'?' active':''; ?>" name="period" value="24h">24h</button>
    <button class="fbtn<?php echo $period==='7d'?' active':''; ?>"  name="period" value="7d">7d</button>
    <button class="fbtn<?php echo $period==='30d'?' active':''; ?>" name="period" value="30d">30d</button>
    <span class="filter-sep"></span>
    <span class="filter-lbl">View:</span>
    <button class="fbtn active" id="tab-h-btn" type="button" onclick="showTab('h')">Hosts (<?php echo $h_total; ?>)</button>
    <button class="fbtn"        id="tab-s-btn" type="button" onclick="showTab('s')">Services (<?php echo $s_total; ?>)</button>
</form>

<!-- HOST TABLE -->
<div id="tab-h" class="data-card">
<table class="dtbl">
<thead><tr>
    <th>Host</th>
    <th style="width:34px">Now</th>
    <th style="width:180px">Timeline</th>
    <th style="width:64px;text-align:right">UP %</th>
    <th style="width:64px;text-align:right">Down %</th>
    <th style="width:72px;text-align:right">Unreach %</th>
</tr></thead>
<tbody>
<?php foreach ($host_rows as $r):
    $eurl = h('host.php?host='.urlencode($r['host']));
    $up   = $r['pct']['UP']          ?? 0;
    $dn   = $r['pct']['DOWN']        ?? 0;
    $ur   = $r['pct']['UNREACHABLE'] ?? 0;
    $row_cls = $dn > 0 ? 'r-down' : ($ur > 0 ? 'r-unreach' : '');
?>
<tr class="data-row <?php echo $row_cls; ?>">
    <td class="c-host"><a href="<?php echo $eurl; ?>" target="main"><?php echo h($r['host']); ?></a></td>
    <td><?php echo state_badge($r['state']); ?></td>
    <td><?php echo avail_bar($r['segs'], $r['segs'] ? array_sum(array_column($r['segs'],'secs')) : $period_secs); ?></td>
    <td style="text-align:right"><span class="avail-pct <?php echo avail_cls($up); ?>"><?php echo number_format($up,2); ?>%</span></td>
    <td style="text-align:right"><span class="avail-pct <?php echo $dn?'avail-pct-lo':'avail-na'; ?>"><?php echo $dn ? number_format($dn,2).'%' : '—'; ?></span></td>
    <td style="text-align:right"><span class="avail-pct <?php echo $ur?'avail-pct-lo':'avail-na'; ?>"><?php echo $ur ? number_format($ur,2).'%' : '—'; ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- SERVICE TABLE -->
<div id="tab-s" class="data-card" style="display:none">
<table class="dtbl">
<thead><tr>
    <th style="width:130px">Host</th>
    <th>Service</th>
    <th style="width:34px">Now</th>
    <th style="width:180px">Timeline</th>
    <th style="width:64px;text-align:right">OK %</th>
    <th style="width:64px;text-align:right">Warn %</th>
    <th style="width:64px;text-align:right">Crit %</th>
</tr></thead>
<tbody>
<?php foreach ($svc_rows as $r):
    $heurl = h('host.php?host='.urlencode($r['host']));
    $seurl = h('service.php?host='.urlencode($r['host']).'&service='.urlencode($r['svc']));
    $ok    = $r['pct']['OK']       ?? 0;
    $wn    = $r['pct']['WARNING']  ?? 0;
    $cr    = $r['pct']['CRITICAL'] ?? 0;
    $row_cls = $cr > 0 ? 'r-crit' : ($wn > 0 ? 'r-warn' : '');
?>
<tr class="data-row <?php echo $row_cls; ?>">
    <td class="c-host"><a href="<?php echo $heurl; ?>" target="main"><?php echo h($r['host']); ?></a></td>
    <td class="c-svc"><a href="<?php echo $seurl; ?>" target="main"><?php echo h($r['svc']); ?></a></td>
    <td><?php echo state_badge($r['state']); ?></td>
    <td><?php echo avail_bar($r['segs'], $r['segs'] ? array_sum(array_column($r['segs'],'secs')) : $period_secs); ?></td>
    <td style="text-align:right"><span class="avail-pct <?php echo avail_cls($ok); ?>"><?php echo number_format($ok,2); ?>%</span></td>
    <td style="text-align:right"><span class="avail-pct <?php echo $wn?'avail-pct-mid':'avail-na'; ?>"><?php echo $wn ? number_format($wn,2).'%' : '—'; ?></span></td>
    <td style="text-align:right"><span class="avail-pct <?php echo $cr?'avail-pct-lo':'avail-na'; ?>"><?php echo $cr ? number_format($cr,2).'%' : '—'; ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<script>
function showTab(t) {
    document.getElementById('tab-h').style.display = t==='h' ? '' : 'none';
    document.getElementById('tab-s').style.display = t==='s' ? '' : 'none';
    document.getElementById('tab-h-btn').classList.toggle('active', t==='h');
    document.getElementById('tab-s-btn').classList.toggle('active', t==='s');
}
</script>
</body>
</html>
