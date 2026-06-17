<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$mcf     = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$sf      = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$refresh = 90;

$ocf   = nagios_find_objects_cache($mcf);
$grpd  = nagios_parse_groups($ocf);
$sdata = nagios_parse_status($sf);

/* Host state lookup: hostname => status block */
$hst_map = [];
foreach ($sdata['hosts'] as $h) {
    $hst_map[$h['host_name'] ?? ''] = $h;
}

/* Overall host counts (all monitored hosts) */
$totals = ['up'=>0,'down'=>0,'unreach'=>0,'pending'=>0];
foreach ($sdata['hosts'] as $h) {
    $st = host_state_info($h);
    if ($st['text']==='UP')               $totals['up']++;
    elseif ($st['text']==='DOWN')         $totals['down']++;
    elseif ($st['text']==='UNREACHABLE')  $totals['unreach']++;
    else                                  $totals['pending']++;
}

/* Enrich groups with live member states */
$groups = [];
foreach ($grpd['hostgroups'] ?? [] as $g) {
    $gname = $g['hostgroup_name'] ?? '';
    $alias = $g['alias']          ?? $gname;
    $raw   = (isset($g['members']) && trim($g['members']) !== '')
             ? array_map('trim', explode(',', $g['members'])) : [];
    $mems  = array_values(array_filter($raw, function($m){ return $m !== ''; }));

    $gc   = ['up'=>0,'down'=>0,'unreach'=>0,'pending'=>0];
    $rows = [];
    foreach ($mems as $hn) {
        $blk = isset($hst_map[$hn]) ? $hst_map[$hn] : [];
        $st  = $blk
             ? host_state_info($blk)
             : ['text'=>'PENDING','cls'=>'badge-pending','row'=>'','ord'=>3];
        if      ($st['text']==='UP')          $gc['up']++;
        elseif  ($st['text']==='DOWN')        $gc['down']++;
        elseif  ($st['text']==='UNREACHABLE') $gc['unreach']++;
        else                                  $gc['pending']++;

        $dur  = (!empty($blk['last_state_change']) && $blk['last_state_change']!=='0')
                ? fmt_dur(time() - (int)$blk['last_state_change']) : '-';
        $lc   = (!empty($blk['last_check']) && $blk['last_check']!=='0')
                ? fmt_ago((int)$blk['last_check']) : '-';
        $ack  = ($blk['problem_has_been_acknowledged'] ?? '0') === '1';
        $dt   = ((int)($blk['scheduled_downtime_depth'] ?? 0)) > 0;
        $aurl = expand_url($blk['action_url'] ?? '', $hn);

        $rows[] = [
            'name'  => $hn,
            'state' => $st,
            'dur'   => $dur,
            'lc'    => $lc,
            'ack'   => $ack,
            'dt'    => $dt,
            'out'   => h($blk['plugin_output'] ?? ''),
            'aurl'  => $aurl,
        ];
    }
    usort($rows, function($a,$b){ return $a['state']['ord'] <=> $b['state']['ord']; });

    $has_prob = $gc['down']>0 || $gc['unreach']>0;
    $worst    = $gc['down']>0 ? 0 : ($gc['unreach']>0 ? 1 : ($gc['pending']>0 ? 2 : 3));

    $groups[] = [
        'name'     => $gname,
        'alias'    => $alias,
        'rows'     => $rows,
        'gc'       => $gc,
        'worst'    => $worst,
        'has_prob' => $has_prob,
    ];
}
usort($groups, function($a,$b){
    return $a['worst'] !== $b['worst'] ? $a['worst'] <=> $b['worst'] : strcmp($a['name'], $b['name']);
});

$prob_count = count(array_filter($groups, function($g){ return $g['has_prob']; }));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Host Groups &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
			<span class="phd-page-title">Host Groups</span>
		</div>
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

<!-- Summary chips — total host counts across all groups -->
<div class="summary-bar">
	<div class="sum-chip chip-up">     <span class="n"><?php echo $totals['up'];      ?></span> Up</div>
	<div class="sum-chip chip-down">   <span class="n"><?php echo $totals['down'];    ?></span> Down</div>
	<div class="sum-chip chip-unreach"><span class="n"><?php echo $totals['unreach'];?></span> Unreachable</div>
	<div class="sum-chip chip-pending"><span class="n"><?php echo $totals['pending'];?></span> Pending</div>
</div>

<!-- Filter bar -->
<div class="filter-bar">
	<span class="filter-lbl">Groups:</span>
	<button class="fbtn active" onclick="filterGroups(this,'all')">All (<?php echo count($groups); ?>)</button>
	<?php if ($prob_count): ?>
	<button class="fbtn" onclick="filterGroups(this,'problems')">Problems (<?php echo $prob_count; ?>)</button>
	<?php endif; ?>
</div>

<?php if (!$grpd['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Cannot read objects cache: <?php echo h($grpd['error'] ?? ''); ?>
</div></div>
<?php elseif (empty($groups)): ?>
<div class="data-card"><div class="cell-state">No host groups defined.</div></div>
<?php else: ?>

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
<?php foreach ($groups as $gi => $g):
    $gc  = $g['gc'];
    /* no group-detail page — group name is display-only */
?>
<tr class="grp-hdr<?php echo $g['has_prob']?' grp-problem':''; ?>"
    data-gi="<?php echo $gi; ?>" data-open="1"
    data-problems="<?php echo $g['has_prob']?'1':'0'; ?>"
    onclick="toggleGrp(this)">
	<td colspan="6">
		<span class="hgrp-chevron">
			<svg class="chevron-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
		</span>
		<span class="grp-name">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;opacity:.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
			<span><?php echo h($g['name']); ?></span>
		</span>
		<?php if ($g['alias'] !== $g['name']): ?><span class="grp-alias"><?php echo h($g['alias']); ?></span><?php endif; ?>
		<span class="grp-count"><?php echo count($g['rows']); ?> host<?php echo count($g['rows'])!==1?'s':''; ?></span>
		<span class="grp-state-summary">
			<?php if ($gc['down'])    echo '<span class="gsb gsb-down">'   .$gc['down']   .' Down</span>'; ?>
			<?php if ($gc['unreach']) echo '<span class="gsb gsb-unreach">'.$gc['unreach'].' Unreach</span>'; ?>
			<?php if ($gc['pending']) echo '<span class="gsb gsb-pending">'.$gc['pending'].' Pending</span>'; ?>
			<?php if ($gc['up'] && !$gc['down'] && !$gc['unreach']) echo '<span class="gsb gsb-ok">All Up</span>'; ?>
		</span>
	</td>
</tr>
<?php foreach ($g['rows'] as $r):
    $st   = $r['state'];
    $hurl = h($cgi.'/status.cgi?host='.urlencode($r['name']));
    $eurl = h('host.php?host='.urlencode($r['name']));
?>
<tr class="data-row <?php echo $st['row']; ?>" data-state="<?php echo strtolower($st['text']); ?>" data-gi="<?php echo $gi; ?>">
	<td><?php echo state_badge($st); ?></td>
	<td class="c-host">
		<a href="<?php echo $eurl; ?>" target="main"><?php echo h($r['name']); ?></a>
		<?php if ($r['ack']) echo '<span class="tag-ack">ACK</span>'; ?>
		<?php if ($r['dt'])  echo '<span class="tag-dt">DOWNTIME</span>'; ?>
	</td>
	<td class="c-dur"><?php echo $r['dur']; ?></td>
	<td class="c-lc"><?php echo $r['lc']; ?></td>
	<td class="c-out" title="<?php echo $r['out']; ?>"><?php echo $r['out']; ?></td>
	<td style="white-space:nowrap">
		<?php if ($r['aurl']): ?>
		<a href="<?php echo h($r['aurl']); ?>" target="_blank" class="action-graph" title="Graph">
			<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
					</a>
		<?php endif; ?>
		<a href="<?php echo $eurl; ?>" target="main" class="action-detail" title="Detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
	</td>
</tr>
<?php endforeach; ?>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _grpFilter = 'all';

function toggleGrp(hdr) {
	var gi     = hdr.dataset.gi;
	var isOpen = hdr.dataset.open === '1';
	hdr.dataset.open = isOpen ? '0' : '1';
	hdr.querySelector('.chevron-icon').style.transform = isOpen ? 'rotate(-90deg)' : '';
	document.querySelectorAll('tr.data-row[data-gi="' + gi + '"]').forEach(function(r) {
		r.dataset.collapsed = isOpen ? '1' : '0';
		r.style.display = isOpen ? 'none' : '';
	});
}
function filterGroups(btn, f) {
	document.querySelectorAll('.fbtn').forEach(function(b){ b.classList.remove('active'); });
	btn.classList.add('active');
	_grpFilter = f;
	document.querySelectorAll('tr.grp-hdr').forEach(function(hdr) {
		var show = f === 'all' || hdr.dataset.problems === '1';
		hdr.style.display = show ? '' : 'none';
		var gi = hdr.dataset.gi;
		document.querySelectorAll('tr.data-row[data-gi="' + gi + '"]').forEach(function(r) {
			r.style.display = (show && r.dataset.collapsed !== '1') ? '' : 'none';
		});
	});
}
</script>
</body>
</html>
