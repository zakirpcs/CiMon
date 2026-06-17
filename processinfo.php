<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';
$now = time();

$sdata   = nagios_parse_status($sf);
$program = $sdata['program'] ?? [];

/* Derive human-readable values */
$pid     = $program['nagios_pid']     ?? '—';
$start   = (int)($program['program_start'] ?? 0);
$uptime  = $start ? ($now - $start) : 0;

function fmt_uptime($secs) {
    if ($secs <= 0) return '—';
    $d = intdiv($secs, 86400); $h = intdiv($secs % 86400, 3600);
    $m = intdiv($secs % 3600, 60); $s = $secs % 60;
    if ($d) return "{$d}d {$h}h {$m}m";
    if ($h) return "{$h}h {$m}m {$s}s";
    return "{$m}m {$s}s";
}

function flag_cell($val, $on_label = 'Enabled', $off_label = 'Disabled') {
    return (int)$val
        ? '<span class="proc-flag-on">'.$on_label.'</span>'
        : '<span class="proc-flag-off">'.$off_label.'</span>';
}

$n_hosts = count($sdata['hosts'] ?? []);
$n_svcs  = count($sdata['services'] ?? []);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Process Information &mdash; CiMon</title>
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
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
			<span class="phd-page-title">Process Information</span>
		</div>
		<div class="phd-count"><?php echo $n_hosts; ?> hosts &middot; <?php echo $n_svcs; ?> services monitored</div>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<?php if (empty($program)): ?>
<div class="data-card"><div class="cell-state is-error">Could not read process status from status file.</div></div>
<?php else: ?>

<div class="proc-grid">

	<!-- Process status -->
	<div class="proc-section">
		<div class="proc-section-title">Process</div>
		<div class="proc-row" style="flex-direction:column;align-items:flex-start;gap:2px;padding-bottom:10px">
			<span class="proc-label">PID</span>
			<span class="proc-pid"><?php echo h($pid); ?></span>
		</div>
		<div class="proc-row" style="flex-direction:column;align-items:flex-start;gap:2px;padding-bottom:10px">
			<span class="proc-label">Uptime</span>
			<span class="proc-uptime"><?php echo fmt_uptime($uptime); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Started</span>
			<span class="proc-val"><?php echo $start ? date('Y-m-d H:i:s', $start) : '—'; ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Daemon mode</span>
			<span class="proc-val"><?php echo flag_cell($program['daemon_mode']??0, 'Yes', 'No'); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Last command check</span>
			<span class="proc-val"><?php
				$lcc = (int)($program['last_command_check'] ?? 0);
				echo $lcc > 0 ? date('H:i:s', $lcc) : '<span style="color:var(--text-lo)">N/A</span>';
			?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Last log rotation</span>
			<span class="proc-val"><?php
				$llr = (int)($program['last_log_rotation'] ?? 0);
				echo $llr > 0 ? date('Y-m-d H:i', $llr) : '<span style="color:var(--text-lo)">Never</span>';
			?></span>
		</div>
	</div>

	<!-- Notifications & Event Handlers -->
	<div class="proc-section">
		<div class="proc-section-title">Notifications &amp; Events</div>
		<div class="proc-row">
			<span class="proc-label">Notifications</span>
			<span class="proc-val"><?php echo flag_cell($program['enable_notifications']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Event handlers</span>
			<span class="proc-val"><?php echo flag_cell($program['enable_event_handlers']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Global host event handler</span>
			<span class="proc-val" style="font-size:0.65rem;color:var(--text-lo)">
				<?php $gh = trim($program['global_host_event_handler']??''); echo $gh !== '' ? h($gh) : '—'; ?>
			</span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Global service event handler</span>
			<span class="proc-val" style="font-size:0.65rem;color:var(--text-lo)">
				<?php $gs = trim($program['global_service_event_handler']??''); echo $gs !== '' ? h($gs) : '—'; ?>
			</span>
		</div>
	</div>

	<!-- Host Checks -->
	<div class="proc-section">
		<div class="proc-section-title">Host Checks</div>
		<div class="proc-row">
			<span class="proc-label">Active checks</span>
			<span class="proc-val"><?php echo flag_cell($program['active_host_checks_enabled']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Passive checks</span>
			<span class="proc-val"><?php echo flag_cell($program['passive_host_checks_enabled']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Check freshness</span>
			<span class="proc-val"><?php echo flag_cell($program['check_host_freshness']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Obsess over hosts</span>
			<span class="proc-val"><?php echo flag_cell($program['obsess_over_hosts']??0); ?></span>
		</div>
	</div>

	<!-- Service Checks -->
	<div class="proc-section">
		<div class="proc-section-title">Service Checks</div>
		<div class="proc-row">
			<span class="proc-label">Active checks</span>
			<span class="proc-val"><?php echo flag_cell($program['active_service_checks_enabled']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Passive checks</span>
			<span class="proc-val"><?php echo flag_cell($program['passive_service_checks_enabled']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Check freshness</span>
			<span class="proc-val"><?php echo flag_cell($program['check_service_freshness']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Obsess over services</span>
			<span class="proc-val"><?php echo flag_cell($program['obsess_over_services']??0); ?></span>
		</div>
	</div>

	<!-- Detection & Performance -->
	<div class="proc-section">
		<div class="proc-section-title">Detection &amp; Performance</div>
		<div class="proc-row">
			<span class="proc-label">Flap detection</span>
			<span class="proc-val"><?php echo flag_cell($program['enable_flap_detection']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Process performance data</span>
			<span class="proc-val"><?php echo flag_cell($program['process_performance_data']??0); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Next comment ID</span>
			<span class="proc-val"><?php echo h($program['next_comment_id'] ?? '—'); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Next downtime ID</span>
			<span class="proc-val"><?php echo h($program['next_downtime_id'] ?? '—'); ?></span>
		</div>
		<div class="proc-row">
			<span class="proc-label">Next event ID</span>
			<span class="proc-val"><?php echo h($program['next_event_id'] ?? '—'); ?></span>
		</div>
	</div>

</div><!-- /.proc-grid -->

<?php endif; ?>
</body>
</html>
