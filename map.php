<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi     = $cfg['cgi_base_url'];
$mcf     = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$sf      = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$refresh = 90;

$sdata = nagios_parse_status($sf);
$ocf   = nagios_find_objects_cache($mcf);
$hobj  = nagios_parse_host_objects($ocf);

/* Host state lookup: hostname → status block */
$hst_map = [];
foreach ($sdata['hosts'] as $h) {
    $hst_map[$h['host_name'] ?? ''] = $h;
}

/* State totals */
$counts = ['up'=>0,'down'=>0,'unreach'=>0,'pending'=>0];
foreach ($sdata['hosts'] as $h) {
    $st = host_state_info($h);
    if      ($st['text']==='UP')          $counts['up']++;
    elseif  ($st['text']==='DOWN')        $counts['down']++;
    elseif  ($st['text']==='UNREACHABLE') $counts['unreach']++;
    else                                  $counts['pending']++;
}

/* Build parent → children adjacency */
$children   = [];   /* parent_name => [child_name, ...] */
$has_parent = [];   /* child_name => true */

if ($hobj['ok']) {
    foreach ($hobj['hosts'] as $hn => $ho) {
        foreach ($ho['parents'] as $parent) {
            if ($parent !== '') {
                $children[$parent][] = $hn;
                $has_parent[$hn]     = true;
            }
        }
    }
}

/* Root hosts: known to objects.cache with no parent */
$roots = [];
if ($hobj['ok']) {
    foreach ($hobj['hosts'] as $hn => $_) {
        if (!isset($has_parent[$hn])) $roots[] = $hn;
    }
    /* Also include status.dat hosts missing from objects.cache */
    foreach (array_keys($hst_map) as $hn) {
        if (!isset($hobj['hosts'][$hn]) && !isset($has_parent[$hn])) $roots[] = $hn;
    }
} else {
    /* No topology data — flat list of all hosts */
    $roots = array_keys($hst_map);
}
sort($roots);

$total = count($hst_map);
$now   = time();

/* Recursive tree renderer — outputs <li> elements directly */
function render_topo_node($hn, $depth, &$rendered, &$hst_map, $cgi, &$children, &$hobj, $now) {
    if ($depth > 25 || isset($rendered[$hn])) return;
    $rendered[$hn] = true;

    $blk  = $hst_map[$hn] ?? [];
    $ho   = ($hobj['ok'] && isset($hobj['hosts'][$hn]))
            ? $hobj['hosts'][$hn]
            : ['alias'=>$hn,'address'=>'','parents'=>[],'action_url'=>''];
    $st   = $blk ? host_state_info($blk) : ['text'=>'PENDING','cls'=>'badge-pending','row'=>'','ord'=>3];
    $dur  = (!empty($blk['last_state_change']) && $blk['last_state_change']!=='0')
            ? fmt_dur($now - (int)$blk['last_state_change']) : '-';
    $ack  = ($blk['problem_has_been_acknowledged'] ?? '0') === '1';
    $dt   = ((int)($blk['scheduled_downtime_depth'] ?? 0)) > 0;
    $hn_h = h($hn);
    $hurl = h($cgi.'/status.cgi?host='.urlencode($hn));
    $eurl = h('host.php?host='.urlencode($hn));
    $aurl = expand_url($ho['action_url'], $hn);

    /* Only children not yet rendered */
    $kids = [];
    foreach ($children[$hn] ?? [] as $c) {
        if (!isset($rendered[$c])) $kids[] = $c;
    }
    sort($kids);
    $has_kids = !empty($kids);
    $stl = strtolower(str_replace(' ', '', $st['text']));

    echo '<li class="topo-node" data-state="'.$stl.'">';
    echo '<div class="topo-row '.$st['row'].'"'
        .($has_kids ? ' onclick="togNode(this)"' : '')
        .' data-open="1">';

    /* Chevron / leaf dot */
    echo '<span class="topo-chevron'.(!$has_kids?' topo-leaf':'').'">';
    if ($has_kids) {
        echo '<svg class="chevron-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
    } else {
        echo '<svg width="7" height="7" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="3"/></svg>';
    }
    echo '</span>';

    echo state_badge($st);
    echo ' <a href="'.$eurl.'" target="main" class="topo-hname" onclick="event.stopPropagation()">'.$hn_h.'</a>';
    if (!empty($ho['address']) && $ho['address'] !== $hn)
        echo ' <span class="topo-addr">'.h($ho['address']).'</span>';
    if (!empty($ho['alias']) && $ho['alias'] !== $hn)
        echo ' <span class="topo-alias">'.h($ho['alias']).'</span>';
    if ($ack) echo ' <span class="tag-ack">ACK</span>';
    if ($dt)  echo ' <span class="tag-dt">DOWNTIME</span>';
    echo '<span class="topo-dur">'.$dur.'</span>';
    echo '<span class="topo-acts" onclick="event.stopPropagation()">';
    if ($aurl) {
        echo '<a href="'.h($aurl).'" target="_blank" class="action-graph">'
            .'<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>'
            .' Graph</a>';
    }
    echo '</span>';
    echo '</div>'; /* topo-row */

    if ($has_kids) {
        echo '<ul class="topo-children">';
        foreach ($kids as $child) {
            render_topo_node($child, $depth + 1, $rendered, $hst_map, $cgi, $children, $hobj, $now);
        }
        echo '</ul>';
    }
    echo '</li>';
}

$rendered = [];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Network Map &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<meta http-equiv="refresh" content="<?php echo $refresh; ?>">
<style>
body        { padding: 10px 14px; }
.page-hd    { margin: -10px -14px 10px; }
.map-bar    { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; flex-wrap:wrap; }
.map-bar-l  { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.map-bar-r  { display:flex; align-items:center; gap:6px; flex-shrink:0; }
.sum-chip   { padding:3px 9px; }
.topo-row   { padding:4px 12px; min-height:28px; }
.data-card  { margin-bottom:8px; }
</style>
</head>
<body>

<div class="page-hd">
	<div class="phd-left">
		<div class="phd-page">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><line x1="12" y1="8" x2="5" y2="16"/><line x1="12" y1="8" x2="19" y2="16"/></svg>
			<span class="phd-page-title">Network Map</span>
		</div>
		<span class="phd-count<?php echo ($counts['down']||$counts['unreach'])?' phd-count-alert':''; ?>">
			<?php echo $total; ?> host<?php echo $total!==1?'s':''; ?> monitored
		</span>
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

<div class="map-bar">
	<div class="map-bar-l">
		<div class="sum-chip chip-up">    <span class="n"><?php echo $counts['up'];      ?></span> Up</div>
		<div class="sum-chip chip-down">  <span class="n"><?php echo $counts['down'];    ?></span> Down</div>
		<div class="sum-chip chip-unreach"><span class="n"><?php echo $counts['unreach'];?></span> Unreachable</div>
		<div class="sum-chip chip-pending"><span class="n"><?php echo $counts['pending'];?></span> Pending</div>
	</div>
	<div class="map-bar-r">
		<span class="filter-lbl">Tree:</span>
		<button class="fbtn active" id="btn-expand"   onclick="expandAll(this)">Expand All</button>
		<button class="fbtn"        id="btn-collapse" onclick="collapseAll(this)">Collapse All</button>
		<?php if (!$hobj['ok']): ?>
		<span style="display:inline-flex;align-items:center;gap:4px;font-size:0.65rem;color:#f87171">
			<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			Topology unavailable — flat list
		</span>
		<?php endif; ?>
	</div>
</div>

<?php if (!$sdata['ok']): ?>
<div class="data-card"><div class="cell-state is-error">
	Error reading status file: <?php echo h($sdata['error']); ?>
</div></div>
<?php elseif (empty($hst_map)): ?>
<div class="data-card"><div class="cell-state">No hosts found in status file.</div></div>
<?php else: ?>

<div class="data-card">
<ul class="topo-tree">
<?php foreach ($roots as $root): render_topo_node($root, 0, $rendered, $hst_map, $cgi, $children, $hobj, $now); endforeach; ?>
</ul>

<?php
/* Any hosts that slipped through (multi-parent already rendered, or orphaned) */
$remaining = [];
foreach (array_keys($hst_map) as $hn) {
    if (!isset($rendered[$hn])) $remaining[] = $hn;
}
sort($remaining);
if (!empty($remaining)):
?>
<div class="topo-standalone-hdr">Remaining Hosts</div>
<ul class="topo-tree">
<?php foreach ($remaining as $r): render_topo_node($r, 0, $rendered, $hst_map, $cgi, $children, $hobj, $now); endforeach; ?>
</ul>
<?php endif; ?>
</div>

<?php endif; ?>

<script>
function togNode(row) {
    var isOpen = row.dataset.open === '1';
    row.dataset.open = isOpen ? '0' : '1';
    var icon = row.querySelector('.chevron-icon');
    if (icon) icon.style.transform = isOpen ? 'rotate(-90deg)' : '';
    var ul = row.parentElement.querySelector(':scope > .topo-children');
    if (ul) ul.style.display = isOpen ? 'none' : '';
}
function expandAll(btn) {
    document.querySelectorAll('.topo-children').forEach(function(u){ u.style.display=''; });
    document.querySelectorAll('.topo-row[data-open]').forEach(function(r){
        r.dataset.open = '1';
        var ic = r.querySelector('.chevron-icon'); if (ic) ic.style.transform = '';
    });
    document.querySelectorAll('.fbtn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
}
function collapseAll(btn) {
    document.querySelectorAll('.topo-children').forEach(function(u){ u.style.display='none'; });
    document.querySelectorAll('.topo-row[data-open]').forEach(function(r){
        r.dataset.open = '0';
        var ic = r.querySelector('.chevron-icon'); if (ic) ic.style.transform = 'rotate(-90deg)';
    });
    document.querySelectorAll('.fbtn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
}
</script>
</body>
</html>
