<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$mcf     = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$sf      = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$refresh = 90;

$sdata = nagios_parse_status($sf);
$ocf   = nagios_find_objects_cache($mcf);
$hobj  = nagios_parse_host_objects($ocf);

/* Host state lookup */
$hst_map = [];
foreach ($sdata['hosts'] as $h) {
    $hst_map[$h['host_name'] ?? ''] = $h;
}

/* Build parent → children map */
$children = [];
if ($hobj['ok']) {
    foreach ($hobj['hosts'] as $hn => $ho) {
        foreach ($ho['parents'] as $parent) {
            if ($parent !== '') $children[$parent][] = $hn;
        }
    }
}

$topo_ok = $hobj['ok'];

/* Find network outages:
   A DOWN host that is the reason >= 1 hosts are UNREACHABLE.
   BFS through children; collect any that are UNREACHABLE. */
$outages = [];

if ($topo_ok) {
    foreach ($hst_map as $hn => $h) {
        $st = host_state_info($h);
        if ($st['text'] !== 'DOWN') continue;
        if (empty($children[$hn])) continue;

        $affected = [];
        $queue    = $children[$hn];
        $visited  = [];
        while (!empty($queue)) {
            $c = array_shift($queue);
            if (isset($visited[$c])) continue;
            $visited[$c] = true;
            $cblk = $hst_map[$c] ?? null;
            if ($cblk) {
                $cst = host_state_info($cblk);
                if ($cst['text'] === 'UNREACHABLE') $affected[] = $c;
            }
            foreach ($children[$c] ?? [] as $gc) {
                if (!isset($visited[$gc])) $queue[] = $gc;
            }
        }

        if (!empty($affected)) {
            sort($affected);
            $outages[] = [
                'host'     => $hn,
                'state'    => $st,
                'blk'      => $h,
                'affected' => $affected,
            ];
        }
    }
    /* Sort: most affected first */
    usort($outages, function($a,$b){ return count($b['affected']) - count($a['affected']); });
}

/* Totals */
$total_down    = 0;
$total_unreach = 0;
foreach ($sdata['hosts'] as $h) {
    $st = host_state_info($h);
    if ($st['text'] === 'DOWN')        $total_down++;
    if ($st['text'] === 'UNREACHABLE') $total_unreach++;
}

$blocking_count  = count($outages);
$affected_total  = array_sum(array_map(function($o){ return count($o['affected']); }, $outages));
$now = time();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Network Outages &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.56 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
			<span class="phd-page-title">Network Outages</span>
		</div>
		<?php if ($blocking_count > 0): ?>
		<span class="phd-count phd-count-alert"><?php echo $blocking_count; ?> blocking host<?php echo $blocking_count!==1?'s':''; ?> &middot; <?php echo $affected_total; ?> unreachable</span>
		<?php else: ?>
		<span class="phd-count"><?php echo count($hst_map); ?> hosts monitored</span>
		<?php endif; ?>
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
	<div class="sum-chip chip-down">  <span class="n"><?php echo $total_down;    ?></span> Down</div>
	<div class="sum-chip chip-unreach"><span class="n"><?php echo $total_unreach; ?></span> Unreachable</div>
	<div class="sum-chip chip-down"  style="opacity:.65"><span class="n"><?php echo $blocking_count; ?></span> Blocking</div>
	<div class="sum-chip chip-unreach" style="opacity:.65"><span class="n"><?php echo $affected_total; ?></span> Affected</div>
</div>

<?php if (!$sdata['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Error reading status file: <?php echo h($sdata['error']); ?>
</div></div>

<?php elseif (!$topo_ok): ?>
<!-- No objects.cache: can't determine parent-child relationships -->
<div class="filter-bar">
	<span class="filter-lbl" style="color:#f87171">
		<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
		Objects cache not readable &mdash; cannot determine network topology. Showing all DOWN and UNREACHABLE hosts below.
	</span>
</div>
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
<?php
$prob_hosts = [];
foreach ($sdata['hosts'] as $h) {
    $st = host_state_info($h);
    if ($st['text'] === 'DOWN' || $st['text'] === 'UNREACHABLE') $prob_hosts[] = $h;
}
usort($prob_hosts, function($a,$b){ return host_state_info($a)['ord'] <=> host_state_info($b)['ord']; });
foreach ($prob_hosts as $h):
    $st   = host_state_info($h);
    $hn   = $h['host_name'] ?? '';
    $dur  = (!empty($h['last_state_change']) && $h['last_state_change']!=='0')
            ? fmt_dur($now - (int)$h['last_state_change']) : '-';
    $lc   = (!empty($h['last_check']) && $h['last_check']!=='0')
            ? fmt_ago((int)$h['last_check']) : '-';
    $ack  = ($h['problem_has_been_acknowledged'] ?? '0') === '1';
    $dt   = ((int)($h['scheduled_downtime_depth'] ?? 0)) > 0;
    $hurl = h($cgi.'/status.cgi?host='.urlencode($hn));
    $eurl = h('host.php?host='.urlencode($hn));
    $aurl = expand_url($h['action_url'] ?? '', $hn);
?>
<tr class="data-row <?php echo $st['row']; ?>">
	<td><?php echo state_badge($st); ?></td>
	<td class="c-host">
		<a href="<?php echo $eurl; ?>" target="main"><?php echo h($hn); ?></a>
		<?php if ($ack) echo '<span class="tag-ack">ACK</span>'; ?>
		<?php if ($dt)  echo '<span class="tag-dt">DOWNTIME</span>'; ?>
	</td>
	<td class="c-dur"><?php echo $dur; ?></td>
	<td class="c-lc"><?php echo $lc; ?></td>
	<td class="c-out" title="<?php echo h($h['plugin_output']??''); ?>"><?php echo h($h['plugin_output']??''); ?></td>
	<td style="white-space:nowrap">
		<?php if ($aurl): ?>
		<a href="<?php echo h($aurl); ?>" target="_blank" class="action-graph" title="Graph">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
					</a>
		<?php endif; ?>
		<a href="<?php echo $eurl; ?>" target="main" class="action-detail" title="Detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
	</td>
</tr>
<?php endforeach; ?>
<?php if (empty($prob_hosts)): ?>
<tr><td colspan="6">
	<div class="cell-state cell-state-ok">
		<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#4ade80;margin-bottom:10px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
		<div>No host problems detected.</div>
	</div>
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<?php elseif (empty($outages)): ?>
<div class="data-card">
	<div class="cell-state cell-state-ok">
		<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#4ade80;margin-bottom:10px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
		<div>No network outages &mdash; all hosts are reachable.</div>
	</div>
</div>

<?php else: ?>

<div class="data-card">
<table class="dtbl">
<thead><tr>
	<th style="width:90px">Status</th>
	<th>Blocking Host</th>
	<th style="width:100px">Duration</th>
	<th style="width:90px">Last Check</th>
	<th>Status Info</th>
	<th style="width:90px">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($outages as $oi => $o):
    $st   = $o['state'];
    $hn   = $o['host'];
    $blk  = $o['blk'];
    $dur  = (!empty($blk['last_state_change']) && $blk['last_state_change']!=='0')
            ? fmt_dur($now - (int)$blk['last_state_change']) : '-';
    $lc   = (!empty($blk['last_check']) && $blk['last_check']!=='0')
            ? fmt_ago((int)$blk['last_check']) : '-';
    $ack  = ($blk['problem_has_been_acknowledged'] ?? '0') === '1';
    $dt   = ((int)($blk['scheduled_downtime_depth'] ?? 0)) > 0;
    $out  = h($blk['plugin_output'] ?? '');
    $hurl = h($cgi.'/status.cgi?host='.urlencode($hn));
    $eurl = h('host.php?host='.urlencode($hn));
    $aurl = expand_url($blk['action_url'] ?? '', $hn);
    $acnt = count($o['affected']);
?>
<tr class="data-row <?php echo $st['row']; ?>" data-oi="<?php echo $oi; ?>" style="cursor:pointer" onclick="togAffected(this)">
	<td><?php echo state_badge($st); ?></td>
	<td class="c-host">
		<span class="hgrp-chevron" style="margin-right:4px">
			<svg class="chevron-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
		</span>
		<a href="<?php echo $eurl; ?>" target="main" onclick="event.stopPropagation()"><?php echo h($hn); ?></a>
		<?php if ($ack) echo '<span class="tag-ack">ACK</span>'; ?>
		<?php if ($dt)  echo '<span class="tag-dt">DOWNTIME</span>'; ?>
		<span class="outage-count" style="margin-left:8px"><?php echo $acnt; ?> unreachable</span>
	</td>
	<td class="c-dur"><?php echo $dur; ?></td>
	<td class="c-lc"><?php echo $lc; ?></td>
	<td class="c-out" title="<?php echo $out; ?>"><?php echo $out; ?></td>
	<td style="white-space:nowrap" onclick="event.stopPropagation()">
		<?php if ($aurl): ?>
		<a href="<?php echo h($aurl); ?>" target="_blank" class="action-graph" title="Graph">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
					</a>
		<?php endif; ?>
		<a href="<?php echo $eurl; ?>" target="main" class="action-detail" title="Detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
	</td>
</tr>
<tr class="affected-row" data-oi="<?php echo $oi; ?>" style="display:none">
	<td colspan="6">
		<div class="affected-pills">
			<span style="font-size:0.62rem;font-weight:700;color:var(--text-lo);text-transform:uppercase;letter-spacing:0.08em;align-self:center;white-space:nowrap;margin-right:4px">Unreachable:</span>
			<?php foreach ($o['affected'] as $aff):
			    $afurl = h('host.php?host='.urlencode($aff));
			    $afblk = $hst_map[$aff] ?? [];
			    $afack = ($afblk['problem_has_been_acknowledged'] ?? '0') === '1';
			?>
			<span class="affected-pill">
				<a href="<?php echo $afurl; ?>" target="main"><?php echo h($aff); ?></a><?php if ($afack) echo ' <span class="tag-ack" style="font-size:0.55rem;padding:1px 4px">ACK</span>'; ?>
			</span>
			<?php endforeach; ?>
		</div>
	</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
function togAffected(row) {
    var oi   = row.dataset.oi;
    var arow = document.querySelector('tr.affected-row[data-oi="' + oi + '"]');
    if (!arow) return;
    var open = arow.style.display !== 'none';
    arow.style.display = open ? 'none' : '';
    var icon = row.querySelector('.chevron-icon');
    if (icon) icon.style.transform = open ? 'rotate(-90deg)' : '';
}
</script>
</body>
</html>
