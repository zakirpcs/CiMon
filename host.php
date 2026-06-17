<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$mcf = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$now = time();

/* ── Resolve hostname ── */
$hn_raw = trim($_GET['host'] ?? '');
if ($hn_raw === '') {
    header('Location: hosts.php'); exit;
}

/* ── Parse status ── */
$sdata  = nagios_parse_status($sf);
$hblk   = null;
foreach ($sdata['hosts'] as $h) {
    if (($h['host_name'] ?? '') === $hn_raw) { $hblk = $h; break; }
}

/* ── Services for this host ── */
$svc_list = [];
foreach ($sdata['services'] as $s) {
    if (($s['host_name'] ?? '') === $hn_raw) $svc_list[] = $s;
}
usort($svc_list, function($a,$b){ return svc_state_info($a)['ord'] <=> svc_state_info($b)['ord']; });
$n_svcs = count($svc_list);
$svc_ok = $svc_warn = $svc_crit = $svc_unkn = 0;
foreach ($svc_list as $s) {
    $cs = (int)($s['current_state'] ?? 0);
    if ($cs===0) $svc_ok++; elseif($cs===1) $svc_warn++; elseif($cs===2) $svc_crit++; else $svc_unkn++;
}

/* ── Host config from objects.cache ── */
$cache   = nagios_find_objects_cache($mcf);
$cfg_res = nagios_parse_objects_config($cache);
$hcfg    = [];
foreach ($cfg_res['data']['hosts'] as $hc) {
    if (($hc['host_name'] ?? '') === $hn_raw) { $hcfg = $hc; break; }
}

/* ── Host group membership ── */
$grp_res    = nagios_parse_groups($cache);
$host_groups = [];
foreach ($grp_res['hostgroups'] as $g) {
    $members = array_map('trim', explode(',', $g['members'] ?? ''));
    if (in_array($hn_raw, $members)) $host_groups[] = $g['hostgroup_name'];
}

/* ── Comments + downtime ── */
$cdr            = nagios_parse_comments_downtime($sf);
$host_comments  = array_values(array_filter($cdr['comments'],  function($c) use($hn_raw){ return ($c['host_name']??'') === $hn_raw && ($c['kind']==='hostcomment'); }));
$host_downtimes = array_values(array_filter($cdr['downtimes'], function($d) use($hn_raw){ return ($d['host_name']??'') === $hn_raw; }));

/* ── Action URL ── */
$aurl_map   = nagios_load_action_urls($cache);
$action_url = get_action_url($hblk ?? [], $aurl_map, $hn_raw, '');

/* ── Performance data parser ── */
function parse_perf($raw) {
    $metrics = [];
    if (!$raw) return $metrics;
    preg_match_all("/('[^']+'|[^=\s]+)=([^;\s]+)(?:;([^;\s]*))?(?:;([^;\s]*))?(?:;([^;\s]*))?(?:;([^;\s]*))?/",
        trim($raw), $m, PREG_SET_ORDER);
    foreach ($m as $r) {
        $val  = trim($r[2]);
        $unit = preg_replace('/[\d.\-+eE]+/', '', $val, 1);
        $num  = (float)preg_replace('/[^\d.\-+eE]/', '', $val);
        $metrics[] = [
            'label' => trim($r[1], "'"),
            'val'   => $val, 'num' => $num, 'unit' => $unit,
            'warn'  => trim($r[3] ?? ''), 'crit' => trim($r[4] ?? ''),
            'min'   => trim($r[5] ?? ''), 'max'  => trim($r[6] ?? ''),
        ];
    }
    return $metrics;
}

/* ── State derivation ── */
if (!$hblk) {
    $st = ['text'=>'UNKNOWN','cls'=>'badge-unkn','row'=>'','ord'=>5];
} else {
    $st = host_state_info($hblk);
}

/* ── Hero accent colors by state ── */
$hero_colors = [
    'UP'          => ['hac'=>'#4ade80', 'hbg'=>'rgba(74,222,128,0.05)',  'sbadge'=>'sbadge-up'],
    'DOWN'        => ['hac'=>'#ef4444', 'hbg'=>'rgba(239,68,68,0.07)',   'sbadge'=>'sbadge-down'],
    'UNREACHABLE' => ['hac'=>'#a855f7', 'hbg'=>'rgba(168,85,247,0.06)', 'sbadge'=>'sbadge-unreachable'],
    'PENDING'     => ['hac'=>'#94a3b8', 'hbg'=>'rgba(148,163,184,0.04)','sbadge'=>'sbadge-pending'],
    'UNKNOWN'     => ['hac'=>'#f59e0b', 'hbg'=>'rgba(245,158,11,0.05)', 'sbadge'=>'sbadge-unknown'],
];
$hc = $hero_colors[$st['text']] ?? $hero_colors['UNKNOWN'];

$hn_h    = h($hn_raw);
$alias   = h($hcfg['alias']   ?? ($hblk['host_name'] ?? $hn_raw));
$address = h($hcfg['address'] ?? ($hblk['host_address'] ?? ''));

/* ── Key fields from hoststatus ── */
$cur_state  = (int)($hblk['current_state']          ?? -1);
$state_type = (int)($hblk['state_type']              ?? 1);
$cur_att    = (int)($hblk['current_attempt']          ?? 0);
$max_att    = (int)($hblk['max_attempts']             ?? ($hcfg['max_check_attempts'] ?? 0));
$lsc        = (int)($hblk['last_state_change']        ?? 0);
$last_chk   = (int)($hblk['last_check']               ?? 0);
$next_chk   = (int)($hblk['next_check']               ?? 0);
$exec_time  = (float)($hblk['check_execution_time']   ?? 0);
$latency    = (float)($hblk['check_latency']          ?? 0);
$chk_type   = (int)($hblk['check_type']               ?? 0);
$ack        = ($hblk['problem_has_been_acknowledged']  ?? '0') === '1';
$in_dt      = ((int)($hblk['scheduled_downtime_depth'] ?? 0)) > 0;
$flapping   = ($hblk['is_flapping']                   ?? '0') === '1';
$pct_flap   = (float)($hblk['percent_state_change']   ?? 0);
$plugin_out = $hblk['plugin_output']      ?? '';
$long_out   = $hblk['long_plugin_output'] ?? '';
$perf_raw   = $hblk['performance_data']   ?? '';
$perf_metrics = parse_perf($perf_raw);

$notif_en  = ($hblk['notifications_enabled']   ?? '1') === '1';
$act_en    = ($hblk['active_checks_enabled']    ?? '1') === '1';
$pas_en    = ($hblk['passive_checks_enabled']   ?? '1') === '1';
$evth_en   = ($hblk['event_handler_enabled']    ?? '1') === '1';
$flap_en   = ($hblk['flap_detection_enabled']   ?? '1') === '1';

$dur_str      = $lsc ? fmt_dur($now - $lsc) : '—';
$last_chk_str = $last_chk ? fmt_ago($last_chk)          : '—';
$last_chk_abs = $last_chk ? date('Y-m-d H:i:s', $last_chk) : '—';
$next_diff    = $next_chk ? ($next_chk - $now) : 0;
$next_str     = ($next_chk > 0) ? (($next_diff >= 0 ? 'in ' : '−') . fmt_dur(abs($next_diff))) : '—';
$next_abs     = $next_chk ? date('H:i:s', $next_chk) : '—';

/* ── Perf value state vs thresholds ── */
function perf_cls($num, $warn, $crit) {
    if ($crit !== '' && is_numeric($crit) && $num >= (float)$crit) return 'hperf-crit';
    if ($warn !== '' && is_numeric($warn) && $num >= (float)$warn) return 'hperf-warn';
    return '';
}

/* ── Perf bar: 0–100% of ceiling, or -1 if can't compute ── */
function perf_bar_pct($num, $warn, $crit, $maxv) {
    if ($maxv !== '' && is_numeric($maxv) && (float)$maxv > 0) $ceil = (float)$maxv;
    elseif ($crit !== '' && is_numeric($crit) && (float)$crit > 0) $ceil = (float)$crit * 1.25;
    elseif ($warn !== '' && is_numeric($warn) && (float)$warn > 0) $ceil = (float)$warn * 1.5;
    else return -1;
    return min(100, max(0, round(($num / $ceil) * 100)));
}

/* ── Comment entry_type labels ── */
$etype_labels = [1=>'Comment', 2=>'Downtime', 3=>'Flapping', 4=>'Ack'];
$etype_cls    = [1=>'etype-user-cmt', 2=>'etype-downtime-cmt', 3=>'etype-flap-cmt', 4=>'etype-ack'];

/* ── CGI command URLs ── */
$cmd_base    = $cgi . '/cmd.cgi';
$url_ack     = h($cmd_base.'?cmd_typ=33&host='.urlencode($hn_raw));
$url_dt      = h('schedule_downtime.php?host='.urlencode($hn_raw));
$url_cmt     = h('add_comment.php?host='.urlencode($hn_raw));
$url_recheck = h('reschedule.php?host='.urlencode($hn_raw));
$url_svc_all = h('services.php?host='.urlencode($hn_raw));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo $hn_h; ?> &mdash; Host Detail &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<style>
/* ── Compact body ── */
body     { padding: 10px 14px; }
.page-hd { margin: -10px -14px 10px; }
.data-card { margin-bottom: 8px; }

/* ── Compact hero banner ── */
.host-hero      { padding: 11px 16px 10px; border-left-width: 4px; }
.host-hero-top  { gap: 12px; margin-bottom: 8px; align-items: center; }
.hh-state       { padding-top: 0; }
.hh-state-dur   { font-size: 0.61rem; margin-top: 5px; }
.hh-state-since { font-size: 0.56rem; margin-top: 1px; }
.hh-name        { font-size: 1.05rem; letter-spacing: -0.02em; }
.hh-addr        { font-size: 0.70rem; margin-top: 2px; }
.hh-alias       { font-size: 0.62rem; margin-top: 1px; }
.host-hero-tags { margin-top: 5px; gap: 3px; }
.host-hero-tag  { font-size: 0.54rem; padding: 1px 5px; }
.hh-svc-summary { gap: 3px; min-width: 80px; }
.hh-svc-chip    { font-size: 0.60rem; padding: 2px 7px; }
.host-actions   { margin-top: 8px; padding-top: 8px; gap: 4px; }
.hact           { padding: 5px 9px; min-height: 26px; font-size: 0.61rem; gap: 4px; }
.hact svg       { width: 11px; height: 11px; }

/* ── Metrics: 6-col single strip (was 3×2 grid) ── */
.host-metrics             { grid-template-columns: repeat(6,1fr); border-bottom: none; }
.host-metric              { padding: 10px 12px; border-right: 1px solid rgba(0,0,0,0.05); }
.host-metric:nth-child(3) { border-right: 1px solid rgba(0,0,0,0.05); }
.host-metric:nth-child(4),
.host-metric:nth-child(5),
.host-metric:nth-child(6) { border-top: none; }
.host-metric:nth-child(6) { border-right: none; }
.hm-label { font-size: 0.53rem; margin-bottom: 4px; }
.hm-val   { font-size: 0.85rem; margin-bottom: 2px; }
.hm-sub   { font-size: 0.59rem; line-height: 1.45; }
.hm-chip  { font-size: 0.55rem; padding: 1px 5px; margin-top: 4px; }

/* ── Compact output & perf ── */
.host-output     { padding: 10px 16px 9px; }
.host-perf       { padding: 10px 16px 11px; }
.host-section-hd { margin-bottom: 5px; }
.host-out-text   { font-size: 0.74rem; padding-left: 8px; }
.host-out-long   { font-size: 0.65rem; padding: 7px 10px; margin-top: 6px; max-height: 110px; }
.host-perf-pills { gap: 6px; }
.hperf-pill      { padding: 6px 10px; min-width: 78px; }
.hperf-key       { font-size: 0.56rem; margin-bottom: 2px; }
.hperf-val       { font-size: 0.84rem; }

/* ── Compact split panel ── */
.host-svcs-hd            { padding: 9px 14px 8px; }
.host-info-panel         { padding: 10px 14px; }
.host-info-row           { padding: 3px 0; font-size: 0.67rem; }
.host-info-key           { min-width: 90px; }
.svc-compact-tbl thead th { padding: 6px 12px; }
.svc-compact-tbl tbody td { padding: 5px 12px; }

/* ── Compact comments / downtime ── */
.host-cdt-section { padding: 10px 14px 12px; }
.host-cdt-item    { padding: 8px 10px; margin-bottom: 6px; }
.host-cdt-header  { margin-bottom: 3px; }
</style>
</head>
<body>

<!-- ── Page header ── -->
<div class="page-hd">
	<div class="phd-left">
		<div class="phd-page">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
			<a href="hosts.php" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.55">Hosts</a>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
			<span class="phd-page-title"><?php echo $hn_h; ?></span>
		</div>
		<?php if ($hblk): ?>
		<div class="phd-count">
			<?php echo $n_svcs; ?> service<?php echo $n_svcs===1?'':'s'; ?>
			<?php if ($svc_crit): ?> &middot; <span style="color:#f87171;font-weight:700"><?php echo $svc_crit; ?> critical</span><?php endif; ?>
			<?php if ($svc_warn): ?> &middot; <span style="color:#fbbf24;font-weight:700"><?php echo $svc_warn; ?> warning</span><?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<div class="phd-right">
		<form class="hd-search" action="<?php echo h($cgi.'/status.cgi'); ?>" method="get" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="search" class="hd-search-input" name="host" placeholder="Search host…" autocomplete="off">
			<input type="hidden" name="navbarsearch" value="1">
		</form>
	</div>
</div>

<?php if (!$hblk): ?>
<div class="data-card"><div class="cell-state is-error">
	Host <strong><?php echo $hn_h; ?></strong> not found in status file.
	<br><a href="hosts.php" style="color:var(--amber);font-size:0.72rem;margin-top:8px;display:inline-block">← Back to Hosts</a>
</div></div>
<?php else: ?>

<!-- ── Hero banner ── -->
<div class="data-card" style="padding:0;margin-bottom:0;border-radius:0;border-left:none;border-right:none;border-top:none">
<div class="host-hero" style="--hac:<?php echo $hc['hac']; ?>;--hbg:<?php echo $hc['hbg']; ?>">

	<div class="host-hero-top">

		<!-- Col 1: State badge + duration -->
		<div class="hh-state">
			<div class="state-badge-lg <?php echo $hc['sbadge']; ?>">
				<span class="sbadge-dot"></span>
				<span><?php echo h($st['text']); ?></span>
			</div>
			<?php if ($lsc): ?>
			<div class="hh-state-dur">for <?php echo $dur_str; ?></div>
			<div class="hh-state-since">since <?php echo date('m/d H:i', $lsc); ?></div>
			<?php endif; ?>
		</div>

		<!-- Col 2: Identity -->
		<div class="hh-identity">
			<div class="hh-name"><?php echo $hn_h; ?></div>
			<?php if ($address): ?>
			<div class="hh-addr"><?php echo $address; ?></div>
			<?php endif; ?>
			<?php if ($alias && $alias !== $hn_h): ?>
			<div class="hh-alias"><?php echo $alias; ?></div>
			<?php endif; ?>
			<div class="host-hero-tags">
				<?php if ($ack): ?>
				<span class="host-hero-tag host-hero-tag-ack">
					<svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
					Acknowledged
				</span>
				<?php endif; ?>
				<?php if ($in_dt): ?>
				<span class="host-hero-tag host-hero-tag-dt">
					<svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>
					In Downtime
				</span>
				<?php endif; ?>
				<?php if ($flapping): ?>
				<span class="host-hero-tag host-hero-tag-flap">Flapping <?php echo number_format($pct_flap,1); ?>%</span>
				<?php endif; ?>
				<?php foreach ($host_groups as $gn): ?>
				<span class="host-hero-tag host-hero-tag-grp"><?php echo h($gn); ?></span>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Col 3: Service count summary -->
		<?php if ($n_svcs > 0): ?>
		<div class="hh-svc-summary">
			<div class="hh-svc-title"><?php echo $n_svcs; ?> Services</div>
			<?php if ($svc_crit): ?><span class="hh-svc-chip hh-svc-crit"><?php echo $svc_crit; ?> Critical</span><?php endif; ?>
			<?php if ($svc_warn): ?><span class="hh-svc-chip hh-svc-warn"><?php echo $svc_warn; ?> Warning</span><?php endif; ?>
			<?php if ($svc_unkn): ?><span class="hh-svc-chip hh-svc-unkn"><?php echo $svc_unkn; ?> Unknown</span><?php endif; ?>
			<?php if ($svc_ok):   ?><span class="hh-svc-chip hh-svc-ok"><?php echo $svc_ok; ?> OK</span><?php endif; ?>
		</div>
		<?php endif; ?>

	</div><!-- /.host-hero-top -->

	<!-- Action buttons -->
	<div class="host-actions">
		<?php if ($action_url): ?>
		<a href="<?php echo h($action_url); ?>" target="_blank" class="hact hact-primary">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
			Performance Graph
		</a>
		<?php endif; ?>
		<a href="<?php echo $url_recheck; ?>" target="main" class="hact">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.6"/></svg>
			Re-check Now
		</a>
		<?php if ($st['text'] !== 'UP' && !$ack): ?>
		<a href="<?php echo $url_ack; ?>" target="main" class="hact hact-warn">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
			Acknowledge
		</a>
		<?php endif; ?>
		<a href="<?php echo $url_dt; ?>" target="main" class="hact">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>
			Schedule Downtime
		</a>
		<a href="<?php echo $url_cmt; ?>" target="main" class="hact">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
			Add Comment
		</a>
		<a href="<?php echo $url_svc_all; ?>" target="main" class="hact">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
			All Services
		</a>
	</div><!-- /.host-actions -->

</div><!-- /.host-hero -->

<!-- ── Metric cards: 3 × 2 grid ── -->
<div class="host-metrics">

	<div class="host-metric">
		<div class="hm-label">Current State</div>
		<div class="hm-val <?php echo $st['text']==='UP'?'hm-ok':($st['text']==='DOWN'?'hm-crit':'hm-warn'); ?>">
			<?php echo h($st['text']); ?>
		</div>
		<div class="hm-sub">for <?php echo $dur_str; ?></div>
		<div class="hm-sub">since <?php echo $lsc ? date('Y-m-d H:i', $lsc) : '—'; ?></div>
		<span class="hm-chip <?php echo $state_type?'hm-chip-hard':'hm-chip-soft'; ?>">
			<?php echo $state_type ? 'Hard' : 'Soft'; ?> (<?php echo $cur_att; ?>/<?php echo $max_att; ?>)
		</span>
	</div>

	<div class="host-metric">
		<div class="hm-label">Last Check</div>
		<div class="hm-val hm-accent"><?php echo $last_chk_str; ?></div>
		<div class="hm-sub"><?php echo $last_chk_abs; ?></div>
		<div class="hm-sub">Latency <?php echo number_format($latency,3); ?>s &middot; Exec <?php echo number_format($exec_time,3); ?>s</div>
	</div>

	<div class="host-metric">
		<div class="hm-label">Next Check</div>
		<?php if ($next_chk): ?>
		<div class="hm-val <?php echo ($next_diff<0)?'hm-crit':'hm-muted'; ?>"><?php echo h($next_str); ?></div>
		<div class="hm-sub">at <?php echo $next_abs; ?></div>
		<?php else: ?>
		<div class="hm-val hm-muted">—</div>
		<?php endif; ?>
		<div class="hm-sub"><?php echo $chk_type===0 ? 'Active check' : 'Passive check'; ?></div>
		<?php if (!$act_en && $chk_type===0): ?>
		<span class="hm-chip" style="color:#f87171;background:rgba(239,68,68,0.1)">Checks disabled</span>
		<?php endif; ?>
	</div>

	<div class="host-metric">
		<div class="hm-label">Notifications</div>
		<div class="hm-val <?php echo $notif_en?'hm-ok':'hm-crit'; ?>"><?php echo $notif_en?'Enabled':'Disabled'; ?></div>
		<div class="hm-sub">Period: <?php echo h($hblk['notification_period'] ?? '—'); ?></div>
		<?php $last_notif = (int)($hblk['last_notification']??0); ?>
		<?php if ($last_notif): ?>
		<div class="hm-sub">Last: <?php echo fmt_ago($last_notif); ?></div>
		<?php endif; ?>
	</div>

	<div class="host-metric">
		<div class="hm-label">Check Interval</div>
		<?php $ci = (float)($hblk['check_interval'] ?? 0); ?>
		<div class="hm-val hm-muted"><?php echo $ci ? number_format($ci,0).'min' : '—'; ?></div>
		<div class="hm-sub">Retry: <?php echo (float)($hblk['retry_interval']??0) ? number_format((float)$hblk['retry_interval'],0).'min' : '—'; ?></div>
		<div class="hm-sub">Period: <?php echo h($hblk['check_period'] ?? '—'); ?></div>
	</div>

	<div class="host-metric">
		<div class="hm-label">Flap Detection</div>
		<?php if ($flapping): ?>
		<div class="hm-val hm-warn">Flapping</div>
		<div class="hm-sub"><?php echo number_format($pct_flap,1); ?>% state change</div>
		<?php elseif ($flap_en): ?>
		<div class="hm-val hm-ok">Stable</div>
		<div class="hm-sub">Detection: on</div>
		<?php else: ?>
		<div class="hm-val hm-muted">Disabled</div>
		<?php endif; ?>
		<?php $ltu = (int)($hblk['last_time_up']??0); if($ltu): ?>
		<div class="hm-sub">Last UP: <?php echo fmt_ago($ltu); ?></div>
		<?php endif; ?>
	</div>

</div><!-- /.host-metrics -->

<!-- ── Plugin Output ── -->
<?php if ($plugin_out !== '' || $long_out !== ''): ?>
<div class="host-output">
	<div class="host-section-hd">Check Output</div>
	<?php if ($plugin_out !== ''): ?>
	<?php
	$out_state_cls = '';
	if ($st['text'] === 'UP')       $out_state_cls = 'host-out-ok';
	elseif ($st['text'] === 'DOWN') $out_state_cls = 'host-out-crit';
	?>
	<div class="host-out-text <?php echo $out_state_cls; ?>"><?php echo h($plugin_out); ?></div>
	<?php endif; ?>
	<?php if (trim($long_out) !== ''): ?>
	<div class="host-out-long"><?php echo h(rtrim($long_out, "\\n")); ?></div>
	<?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Performance Data ── -->
<?php if (!empty($perf_metrics)): ?>
<div class="host-perf">
	<div class="host-section-hd">Performance Data</div>
	<div class="host-perf-pills">
		<?php foreach ($perf_metrics as $p):
			$vcls    = perf_cls($p['num'], $p['warn'], $p['crit']);
			$bpct    = perf_bar_pct($p['num'], $p['warn'], $p['crit'], $p['max']);
			$bar_cls = $vcls === 'hperf-crit' ? 'hperf-bar-crit' : ($vcls === 'hperf-warn' ? 'hperf-bar-warn' : 'hperf-bar-ok');
		?>
		<div class="hperf-pill">
			<div class="hperf-key"><?php echo h($p['label']); ?></div>
			<div class="hperf-val <?php echo $vcls; ?>"><?php echo h($p['val']); ?></div>
			<?php if ($bpct >= 0): ?>
			<div class="hperf-bar-track">
				<div class="hperf-bar-fill <?php echo $bar_cls; ?>" style="width:<?php echo $bpct; ?>%"></div>
			</div>
			<?php endif; ?>
			<?php if ($p['warn'] !== '' || $p['crit'] !== ''): ?>
			<div class="hperf-thresholds">
				<?php if ($p['warn'] !== ''): ?><span class="hperf-t-warn">W:<?php echo h($p['warn'].$p['unit']); ?></span><?php endif; ?>
				<?php if ($p['crit'] !== ''): ?><span class="hperf-t-crit">C:<?php echo h($p['crit'].$p['unit']); ?></span><?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

</div><!-- /.data-card (hero + metrics + output + perf) -->

<!-- ── Services + Host Info split ── -->
<div class="data-card" style="padding:0">
<div class="host-split">

	<!-- Services table -->
	<div class="host-svcs">
		<div class="host-svcs-hd">
			<span class="host-section-hd" style="margin-bottom:0">Services</span>
			<span class="host-svc-count"><?php echo $n_svcs; ?></span>
			<?php if ($svc_crit): ?><span class="badge badge-crit" style="font-size:0.6rem"><?php echo $svc_crit; ?> crit</span><?php endif; ?>
			<?php if ($svc_warn): ?><span class="badge badge-warn" style="font-size:0.6rem"><?php echo $svc_warn; ?> warn</span><?php endif; ?>
		</div>
		<?php if (empty($svc_list)): ?>
		<div class="cell-state" style="padding:20px 16px;font-size:0.72rem">No services defined for this host.</div>
		<?php else: ?>
		<table class="svc-compact-tbl">
		<thead><tr>
			<th style="width:30px"></th>
			<th>Service</th>
			<th style="width:70px">Duration</th>
			<th>Output</th>
			<th style="width:28px"></th>
		</tr></thead>
		<tbody>
		<?php foreach ($svc_list as $s):
			$ss        = svc_state_info($s);
			$sn        = $s['service_description'] ?? '';
			$scs       = (int)($s['current_state'] ?? 0);
			$sstripes  = ['svc-stripe-ok','svc-stripe-warn','svc-stripe-crit','svc-stripe-unkn'];
			$sstripe   = $sstripes[$scs] ?? 'svc-stripe-unkn';
			$sdurl     = h('service.php?host='.urlencode($hn_raw).'&service='.urlencode($sn));
			$slsc      = (int)($s['last_state_change'] ?? 0);
			$sdur      = $slsc ? fmt_dur($now - $slsc) : '—';
			$sout      = h($s['plugin_output'] ?? '');
			$sack      = ($s['problem_has_been_acknowledged'] ?? '0') === '1';
			$sdt       = ((int)($s['scheduled_downtime_depth'] ?? 0)) > 0;
			$saurl     = get_action_url($s, $aurl_map, $hn_raw, $sn);
		?>
		<tr class="<?php echo $ss['row']; ?> <?php echo $sstripe; ?>">
			<td style="padding:7px 6px 7px 14px"><?php echo state_badge($ss); ?></td>
			<td class="td-svc">
				<a href="<?php echo $sdurl; ?>" target="main"><?php echo h($sn); ?></a>
				<?php if ($sack) echo '<span class="tag-ack" style="font-size:0.55rem">ACK</span>'; ?>
				<?php if ($sdt)  echo '<span class="tag-dt"  style="font-size:0.55rem">DT</span>'; ?>
			</td>
			<td class="td-dur"><?php echo $sdur; ?></td>
			<td class="td-out" title="<?php echo $sout; ?>"><?php echo $sout; ?></td>
			<td style="padding:7px 10px 7px 4px">
				<?php if ($saurl): ?>
				<a href="<?php echo h($saurl); ?>" target="_blank" class="action-graph" title="Graph" style="padding:2px">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
				</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
		</table>
		<?php endif; ?>
	</div><!-- /.host-svcs -->

	<!-- Host info panel -->
	<div class="host-info-panel">
		<div class="host-section-hd">Host Configuration</div>

		<?php if ($address): ?>
		<div class="host-info-row">
			<span class="host-info-key">Address</span>
			<span class="host-info-val"><?php echo $address; ?></span>
		</div>
		<?php endif; ?>
		<?php if ($alias && $alias !== $hn_h): ?>
		<div class="host-info-row">
			<span class="host-info-key">Alias</span>
			<span class="host-info-val"><?php echo $alias; ?></span>
		</div>
		<?php endif; ?>
		<?php
		$parents_str = $hcfg['parents'] ?? '';
		if ($parents_str !== '' && $parents_str !== null):
			$plist = array_filter(array_map('trim', explode(',', $parents_str)));
		?>
		<div class="host-info-row">
			<span class="host-info-key">Parents</span>
			<span class="host-info-val">
				<?php foreach ($plist as $p): ?>
				<a href="host.php?host=<?php echo urlencode($p); ?>" target="main" class="host-info-tag" style="text-decoration:none"><?php echo h($p); ?></a>
				<?php endforeach; ?>
			</span>
		</div>
		<?php endif; ?>
		<?php if (!empty($host_groups)): ?>
		<div class="host-info-row">
			<span class="host-info-key">Host Groups</span>
			<span class="host-info-val">
				<?php foreach ($host_groups as $g): ?>
				<span class="host-info-tag"><?php echo h($g); ?></span>
				<?php endforeach; ?>
			</span>
		</div>
		<?php endif; ?>
		<div class="host-info-row">
			<span class="host-info-key">Check Command</span>
			<span class="host-info-val" style="font-size:0.64rem;font-family:monospace;word-break:break-all"><?php echo h($hblk['check_command'] ?? '—'); ?></span>
		</div>
		<div class="host-info-row">
			<span class="host-info-key">Check Period</span>
			<span class="host-info-val"><?php echo h($hblk['check_period'] ?? '—'); ?></span>
		</div>
		<div class="host-info-row">
			<span class="host-info-key">Notif Period</span>
			<span class="host-info-val"><?php echo h($hblk['notification_period'] ?? '—'); ?></span>
		</div>
		<?php if (isset($hcfg['contact_groups']) && $hcfg['contact_groups'] !== ''): ?>
		<div class="host-info-row">
			<span class="host-info-key">Contact Groups</span>
			<span class="host-info-val">
				<?php foreach (array_filter(array_map('trim', explode(',', $hcfg['contact_groups']))) as $cg): ?>
				<span class="host-info-tag"><?php echo h($cg); ?></span>
				<?php endforeach; ?>
			</span>
		</div>
		<?php endif; ?>
		<div class="host-info-row">
			<span class="host-info-key">Active Checks</span>
			<span class="host-info-val <?php echo $act_en?'host-info-enabled':'host-info-disabled'; ?>"><?php echo $act_en?'Enabled':'Disabled'; ?></span>
		</div>
		<div class="host-info-row">
			<span class="host-info-key">Passive Checks</span>
			<span class="host-info-val <?php echo $pas_en?'host-info-enabled':'host-info-disabled'; ?>"><?php echo $pas_en?'Enabled':'Disabled'; ?></span>
		</div>
		<div class="host-info-row">
			<span class="host-info-key">Event Handler</span>
			<span class="host-info-val <?php echo $evth_en?'host-info-enabled':'host-info-disabled'; ?>"><?php echo $evth_en?'Enabled':'Disabled'; ?></span>
		</div>
		<div class="host-info-row">
			<span class="host-info-key">Perf Data</span>
			<span class="host-info-val <?php echo ($hblk['process_performance_data']??'1')==='1'?'host-info-enabled':'host-info-disabled'; ?>"><?php echo ($hblk['process_performance_data']??'1')==='1'?'Enabled':'Disabled'; ?></span>
		</div>
		<?php $last_update = (int)($hblk['last_update']??0); if ($last_update): ?>
		<div class="host-info-row">
			<span class="host-info-key">Status Updated</span>
			<span class="host-info-val"><?php echo fmt_ago($last_update); ?></span>
		</div>
		<?php endif; ?>
		<?php if (($hcfg['notes'] ?? '') !== ''): ?>
		<div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.05)">
			<div class="host-section-hd">Notes</div>
			<div style="font-size:0.70rem;color:var(--text-lo);line-height:1.5"><?php echo h($hcfg['notes']??''); ?></div>
		</div>
		<?php endif; ?>
	</div><!-- /.host-info-panel -->

</div><!-- /.host-split -->
</div><!-- /.data-card (split) -->

<!-- ── Comments ── -->
<?php if (!empty($host_comments)): ?>
<div class="data-card" style="padding:0">
<div class="host-cdt-section">
	<div class="host-section-hd">Comments (<?php echo count($host_comments); ?>)</div>
	<?php foreach ($host_comments as $c):
		$et   = (int)($c['entry_type'] ?? 1);
		$ecls = $etype_cls[$et] ?? 'etype-user-cmt';
		$elbl = $etype_labels[$et] ?? 'Comment';
	?>
	<div class="host-cdt-item">
		<div class="host-cdt-header">
			<span class="etype <?php echo $ecls; ?>"><?php echo h($elbl); ?></span>
			<span class="host-cdt-author"><?php echo h($c['author'] ?? ''); ?></span>
			<span class="host-cdt-time"><?php echo $c['entry_time'] ? date('Y-m-d H:i', (int)$c['entry_time']) : ''; ?></span>
		</div>
		<div class="host-cdt-body"><?php echo h($c['comment_data'] ?? ''); ?></div>
	</div>
	<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<!-- ── Scheduled Downtime ── -->
<?php if (!empty($host_downtimes)): ?>
<div class="data-card" style="padding:0">
<div class="host-cdt-section">
	<div class="host-section-hd">Scheduled Downtime</div>
	<?php foreach ($host_downtimes as $d):
		$active = (int)($d['is_in_effect']??0);
		$start  = (int)($d['start_time']??0);
		$end    = (int)($d['end_time']??0);
	?>
	<div class="host-cdt-item">
		<div class="host-cdt-header">
			<?php if ($active): ?>
			<span class="etype" style="background:rgba(22,163,74,0.14);color:#4ade80">Active</span>
			<?php else: ?>
			<span class="badge badge-pending" style="font-size:0.58rem">Pending</span>
			<?php endif; ?>
			<span class="host-cdt-author"><?php echo h($d['author'] ?? ''); ?></span>
			<span class="host-cdt-time"><?php echo $start ? date('Y-m-d H:i',$start).' → '.date('H:i',$end) : ''; ?></span>
		</div>
		<div class="host-cdt-body"><?php echo h($d['comment_data'] ?? ''); ?></div>
	</div>
	<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php endif; /* hblk found */ ?>
</body>
</html>
