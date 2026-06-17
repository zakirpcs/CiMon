<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$mcf = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$cf  = nagios_find_objects_cache($mcf);
$res = nagios_parse_objects_config($cf);
$data= $res['ok'] ? $res['data'] : ['hosts'=>[],'services'=>[],'contacts'=>[],'contactgroups'=>[],'hostgroups'=>[],'servicegroups'=>[],'timeperiods'=>[],'commands'=>[]];

$tab_counts = [
    'hosts'         => count($data['hosts']),
    'services'      => count($data['services']),
    'hostgroups'    => count($data['hostgroups']),
    'servicegroups' => count($data['servicegroups']),
    'contacts'      => count($data['contacts']),
    'contactgroups' => count($data['contactgroups']),
    'timeperiods'   => count($data['timeperiods']),
    'commands'      => count($data['commands']),
];
$tab_default = 'hosts';

function cfg_tag($val) {
    if ($val === '' || $val === null) return '<span style="color:var(--text-lo)">—</span>';
    $items = array_filter(array_map('trim', explode(',', $val)));
    $out = '';
    foreach ($items as $it) $out .= '<span class="cfg-tag">'.h($it).'</span>';
    return $out !== '' ? $out : '<span style="color:var(--text-lo)">—</span>';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Configuration &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
</head>
<body>

<div class="page-hd">
	<div class="phd-left">
		<div class="phd-page">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
			<span class="phd-page-title">Configuration</span>
		</div>
		<div class="phd-count"><?php echo $tab_counts['hosts']; ?> hosts &middot; <?php echo $tab_counts['services']; ?> services &middot; <?php echo $tab_counts['contacts']; ?> contacts</div>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<?php if (!$res['ok']): ?>
<div class="data-card"><div class="cell-state is-error">Cannot read objects cache: <?php echo h($res['error']); ?></div></div>
<?php else: ?>

<div class="filter-bar" style="flex-wrap:wrap;gap:4px 6px">
	<button class="fbtn active" id="tab-btn-hosts"         onclick="showTab('hosts')">Hosts (<?php echo $tab_counts['hosts']; ?>)</button>
	<button class="fbtn"        id="tab-btn-services"      onclick="showTab('services')">Services (<?php echo $tab_counts['services']; ?>)</button>
	<button class="fbtn"        id="tab-btn-hostgroups"    onclick="showTab('hostgroups')">Host Groups (<?php echo $tab_counts['hostgroups']; ?>)</button>
	<button class="fbtn"        id="tab-btn-servicegroups" onclick="showTab('servicegroups')">Service Groups (<?php echo $tab_counts['servicegroups']; ?>)</button>
	<button class="fbtn"        id="tab-btn-contacts"      onclick="showTab('contacts')">Contacts (<?php echo $tab_counts['contacts']; ?>)</button>
	<button class="fbtn"        id="tab-btn-contactgroups" onclick="showTab('contactgroups')">Contact Groups (<?php echo $tab_counts['contactgroups']; ?>)</button>
	<button class="fbtn"        id="tab-btn-timeperiods"   onclick="showTab('timeperiods')">Time Periods (<?php echo $tab_counts['timeperiods']; ?>)</button>
	<button class="fbtn"        id="tab-btn-commands"      onclick="showTab('commands')">Commands (<?php echo $tab_counts['commands']; ?>)</button>
</div>

<div class="cfg-search-wrap">
	<input type="search" class="cfg-search-input" id="cfg-search" placeholder="Filter by name…" oninput="doSearch(this.value)" autocomplete="off">
</div>

<!-- Hosts -->
<div id="tab-hosts" class="data-card">
<table class="dtbl" id="tbl-hosts">
<thead><tr>
	<th>Host Name</th>
	<th style="width:150px">Alias</th>
	<th style="width:130px">Address</th>
	<th style="width:50px;text-align:center">Max<br>Attempts</th>
	<th style="width:60px;text-align:center">Interval</th>
	<th>Host Groups</th>
	<th>Parents</th>
</tr></thead>
<tbody>
<?php foreach ($data['hosts'] as $h):
	$hurl = h('host.php?host='.urlencode($h['host_name']??''));
?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower($h['host_name']??'')); ?>">
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($h['host_name']??''); ?></a></td>
	<td style="font-size:0.70rem;color:var(--text-lo)"><?php echo h($h['alias']??''); ?></td>
	<td style="font-size:0.70rem"><?php echo h($h['address']??''); ?></td>
	<td style="text-align:center;font-size:0.70rem"><?php echo h($h['max_check_attempts']??'—'); ?></td>
	<td style="text-align:center;font-size:0.70rem"><?php echo isset($h['check_interval']) ? h($h['check_interval']).'min' : '—'; ?></td>
	<td><?php echo cfg_tag($h['hostgroups']??''); ?></td>
	<td><?php echo cfg_tag($h['parents']??''); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['hosts'])): ?><tr><td colspan="7"><div class="cfg-empty">No hosts defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- Services -->
<div id="tab-services" class="data-card" style="display:none">
<table class="dtbl" id="tbl-services">
<thead><tr>
	<th style="width:140px">Host</th>
	<th>Service Description</th>
	<th>Check Command</th>
	<th style="width:50px;text-align:center">Max<br>Attempts</th>
	<th style="width:60px;text-align:center">Interval</th>
</tr></thead>
<tbody>
<?php foreach ($data['services'] as $s):
	$hurl = h('host.php?host='.urlencode($s['host_name']??''));
	$surl = h('service.php?host='.urlencode($s['host_name']??'').'&service='.urlencode($s['service_description']??''));
	$cmd  = $s['check_command'] ?? '';
	$cmd_short = strlen($cmd) > 50 ? substr($cmd,0,50).'…' : $cmd;
?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower(($s['host_name']??'').' '.($s['service_description']??''))); ?>">
	<td class="c-host"><a href="<?php echo $hurl; ?>" target="main"><?php echo h($s['host_name']??''); ?></a></td>
	<td class="c-svc"><a href="<?php echo $surl; ?>" target="main"><?php echo h($s['service_description']??''); ?></a></td>
	<td style="font-size:0.65rem;color:var(--text-lo)" title="<?php echo h($cmd); ?>"><?php echo h($cmd_short); ?></td>
	<td style="text-align:center;font-size:0.70rem"><?php echo h($s['max_check_attempts']??'—'); ?></td>
	<td style="text-align:center;font-size:0.70rem"><?php echo isset($s['check_interval']) ? h($s['check_interval']).'min' : '—'; ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['services'])): ?><tr><td colspan="5"><div class="cfg-empty">No services defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- Host Groups -->
<div id="tab-hostgroups" class="data-card" style="display:none">
<table class="dtbl" id="tbl-hostgroups">
<thead><tr>
	<th>Host Group Name</th>
	<th>Alias</th>
	<th>Members</th>
</tr></thead>
<tbody>
<?php foreach ($data['hostgroups'] as $g): ?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower($g['hostgroup_name']??'')); ?>">
	<td><strong style="font-size:0.72rem"><?php echo h($g['hostgroup_name']??''); ?></strong></td>
	<td style="font-size:0.70rem;color:var(--text-lo)"><?php echo h($g['alias']??''); ?></td>
	<td><?php echo cfg_tag($g['members']??''); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['hostgroups'])): ?><tr><td colspan="3"><div class="cfg-empty">No host groups defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- Service Groups -->
<div id="tab-servicegroups" class="data-card" style="display:none">
<table class="dtbl" id="tbl-servicegroups">
<thead><tr>
	<th>Service Group Name</th>
	<th>Alias</th>
	<th>Members (host, service pairs)</th>
</tr></thead>
<tbody>
<?php foreach ($data['servicegroups'] as $g): ?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower($g['servicegroup_name']??'')); ?>">
	<td><strong style="font-size:0.72rem"><?php echo h($g['servicegroup_name']??''); ?></strong></td>
	<td style="font-size:0.70rem;color:var(--text-lo)"><?php echo h($g['alias']??''); ?></td>
	<td style="font-size:0.65rem"><?php
		$raw = isset($g['members']) ? array_map('trim', explode(',', $g['members'])) : [];
		$pairs = [];
		for ($i = 0; $i+1 < count($raw); $i += 2) {
			if ($raw[$i] !== '') $pairs[] = h($raw[$i]).':'.h($raw[$i+1]);
		}
		echo $pairs ? implode('<span style="color:var(--text-lo)"> / </span>', $pairs) : '<span style="color:var(--text-lo)">—</span>';
	?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['servicegroups'])): ?><tr><td colspan="3"><div class="cfg-empty">No service groups defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- Contacts -->
<div id="tab-contacts" class="data-card" style="display:none">
<table class="dtbl" id="tbl-contacts">
<thead><tr>
	<th>Contact Name</th>
	<th>Alias</th>
	<th style="width:180px">Email</th>
	<th style="width:120px">Host Notif Period</th>
	<th style="width:120px">Svc Notif Period</th>
	<th style="width:100px">Host Options</th>
	<th style="width:100px">Svc Options</th>
</tr></thead>
<tbody>
<?php foreach ($data['contacts'] as $c): ?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower($c['contact_name']??'')); ?>">
	<td><strong style="font-size:0.72rem"><?php echo h($c['contact_name']??''); ?></strong></td>
	<td style="font-size:0.70rem;color:var(--text-lo)"><?php echo h($c['alias']??''); ?></td>
	<td style="font-size:0.68rem"><?php
		$em = $c['email']??'';
		echo $em ? '<a href="mailto:'.h($em).'" style="color:var(--amber-light)">'.h($em).'</a>' : '<span style="color:var(--text-lo)">—</span>';
	?></td>
	<td style="font-size:0.65rem;color:var(--text-lo)"><?php echo h($c['host_notification_period']??'—'); ?></td>
	<td style="font-size:0.65rem;color:var(--text-lo)"><?php echo h($c['service_notification_period']??'—'); ?></td>
	<td style="font-size:0.63rem;color:var(--text-lo)"><?php echo h($c['host_notification_options']??'—'); ?></td>
	<td style="font-size:0.63rem;color:var(--text-lo)"><?php echo h($c['service_notification_options']??'—'); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['contacts'])): ?><tr><td colspan="7"><div class="cfg-empty">No contacts defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- Contact Groups -->
<div id="tab-contactgroups" class="data-card" style="display:none">
<table class="dtbl" id="tbl-contactgroups">
<thead><tr>
	<th>Contact Group Name</th>
	<th>Alias</th>
	<th>Members</th>
</tr></thead>
<tbody>
<?php foreach ($data['contactgroups'] as $g): ?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower($g['contactgroup_name']??'')); ?>">
	<td><strong style="font-size:0.72rem"><?php echo h($g['contactgroup_name']??''); ?></strong></td>
	<td style="font-size:0.70rem;color:var(--text-lo)"><?php echo h($g['alias']??''); ?></td>
	<td><?php echo cfg_tag($g['members']??''); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['contactgroups'])): ?><tr><td colspan="3"><div class="cfg-empty">No contact groups defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- Time Periods -->
<div id="tab-timeperiods" class="data-card" style="display:none">
<table class="dtbl" id="tbl-timeperiods">
<thead><tr>
	<th>Time Period Name</th>
	<th>Alias</th>
	<th style="width:80px">Exclude</th>
</tr></thead>
<tbody>
<?php foreach ($data['timeperiods'] as $t): ?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower($t['timeperiod_name']??'')); ?>">
	<td><strong style="font-size:0.72rem"><?php echo h($t['timeperiod_name']??''); ?></strong></td>
	<td style="font-size:0.70rem;color:var(--text-lo)"><?php echo h($t['alias']??''); ?></td>
	<td style="font-size:0.65rem;color:var(--text-lo)"><?php echo h($t['exclude']??'—'); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['timeperiods'])): ?><tr><td colspan="3"><div class="cfg-empty">No time periods defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<!-- Commands -->
<div id="tab-commands" class="data-card" style="display:none">
<table class="dtbl" id="tbl-commands">
<thead><tr>
	<th style="width:220px">Command Name</th>
	<th>Command Line</th>
</tr></thead>
<tbody>
<?php foreach ($data['commands'] as $c): ?>
<tr class="data-row cfg-row" data-name="<?php echo h(strtolower($c['command_name']??'')); ?>">
	<td><code style="font-size:0.68rem;color:var(--amber-light)"><?php echo h($c['command_name']??''); ?></code></td>
	<td class="cfg-cmd"><?php echo h($c['command_line']??''); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($data['commands'])): ?><tr><td colspan="2"><div class="cfg-empty">No commands defined.</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>

<?php endif; ?>

<script>
var _currentTab = 'hosts';

function showTab(tab) {
    var tabs = ['hosts','services','hostgroups','servicegroups','contacts','contactgroups','timeperiods','commands'];
    tabs.forEach(function(t) {
        document.getElementById('tab-'+t).style.display     = t===tab ? '' : 'none';
        document.getElementById('tab-btn-'+t).classList.toggle('active', t===tab);
    });
    _currentTab = tab;
    document.getElementById('cfg-search').value = '';
    doSearch('');
}

function doSearch(q) {
    q = q.toLowerCase().trim();
    var rows = document.querySelectorAll('#tab-'+_currentTab+' tr.cfg-row');
    var count = 0;
    rows.forEach(function(r) {
        var name = r.dataset.name || '';
        var text = r.textContent.toLowerCase();
        var show = q === '' || name.indexOf(q) !== -1 || text.indexOf(q) !== -1;
        r.style.display = show ? '' : 'none';
        if (show) count++;
    });
}
</script>
</body>
</html>
