<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi          = $cfg['cgi_base_url'];
$sf           = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$mcf          = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$this_version = '4.5.11';
$refresh      = 90;
$now          = time();

/* ── Service Problems (server-side) ── */
$data     = nagios_parse_status($sf);
$ocf      = nagios_find_objects_cache($mcf);
$aurl_map = nagios_load_action_urls($ocf);

$host_state = [];
foreach ($data['hosts'] as $h) {
    $host_state[$h['host_name'] ?? ''] = host_state_info($h);
}

$pcounts  = ['crit'=>0,'warn'=>0,'unkn'=>0,'acked'=>0];
$problems = [];
foreach ($data['services'] as $s) {
    $st = svc_state_info($s);
    if (!in_array($st['text'], ['CRITICAL','WARNING','UNKNOWN'])) continue;
    $problems[] = $s;
    if ($st['text']==='CRITICAL') $pcounts['crit']++;
    if ($st['text']==='WARNING')  $pcounts['warn']++;
    if ($st['text']==='UNKNOWN')  $pcounts['unkn']++;
    if (($s['problem_has_been_acknowledged']??'0')==='1') $pcounts['acked']++;
}
$total_prob = count($problems);
$unacked    = $total_prob - $pcounts['acked'];

$grouped = [];
foreach ($problems as $s) { $grouped[$s['host_name']??''][] = $s; }
foreach ($grouped as $hn => &$svcs) {
    usort($svcs, function($a,$b){ return svc_state_info($a)['ord'] <=> svc_state_info($b)['ord']; });
}
unset($svcs);
uksort($grouped, function($a,$b) use ($grouped) {
    $w = function($x) use ($grouped) {
        $m = 99; foreach ($grouped[$x] as $s) { $o=svc_state_info($s)['ord']; if($o<$m)$m=$o; } return $m;
    };
    $wa=$w($a); $wb=$w($b);
    return $wa!==$wb ? $wa<=>$wb : strcmp($a,$b);
});
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<meta http-equiv="refresh" content="<?php echo $refresh; ?>">
<title>Tactical Overview &mdash; CiMon</title>
<link rel="stylesheet" type="text/css" href="stylesheets/common.css?<?php echo $this_version; ?>">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<style>
@font-face {
	font-family: 'Geist';
	src: url('fonts/Geist[wght].woff2') format('woff2');
	font-weight: 100 900; font-style: normal; font-display: swap;
}
:root {
	--pg-bg:       #F4F8FF;
	--pg-card:     #FFFFFF;
	--pg-card-bdr: rgba(0,0,0,0.08);
	--pg-thead:    #EEF2FA;
	--teal:        #0891B2;
	--teal-light:  #06B6D4;
	--teal-bg:     rgba(8,145,178,0.07);
	--teal-bdr:    rgba(8,145,178,0.20);
	--text-hi:     #111827;
	--text-mid:    #6B7280;
	--text-lo:     #9CA3AF;
}
body {
	font-family: 'Geist', system-ui, -apple-system, sans-serif;
	background: var(--pg-bg);
	color: var(--text-hi);
	font-size: 14px; line-height: 1.5;
	margin: 0; padding: 0;
}
a { color: var(--teal); text-decoration: none; }
a:hover { color: var(--teal-light); }

/* ── Topbar ── */
.topbar {
	background: #fff;
	border-bottom: 1px solid #e1e1e1;
	padding: 0 16px; height: 54px;
	display: flex; align-items: center; justify-content: space-between;
}
.tb-left { display: flex; align-items: center; gap: 8px; }
.tb-icon svg { width: 15px; height: 15px; stroke: #0891B2; opacity: 0.85; flex-shrink: 0; }
.tb-page-title { font-size: 0.82rem; font-weight: 700; color: var(--text-hi); letter-spacing: -0.01em; white-space: nowrap; }
.tb-right { display: flex; align-items: center; gap: 8px; }

/* ── Countdown ring ── */
.cdown-wrap {
	position: relative; width: 36px; height: 36px; flex-shrink: 0;
	display: flex; align-items: center; justify-content: center;
}
.cdown-svg {
	position: absolute; top: 0; left: 0; width: 36px; height: 36px;
	transform: rotate(-90deg);
}
.cdown-track { fill: none; stroke: rgba(8,145,178,0.15); stroke-width: 2.5; }
.cdown-arc {
	fill: none; stroke: #0891B2; stroke-width: 2.5; stroke-linecap: round;
	stroke-dasharray: 75.4; stroke-dashoffset: 0;
	transition: stroke-dashoffset 1s linear;
}
.cdown-num {
	position: relative; z-index: 1;
	font-size: 0.55rem; font-weight: 800; line-height: 1;
	color: #0891B2; font-variant-numeric: tabular-nums; letter-spacing: -0.03em;
}
@keyframes dpulse { 0%,100%{opacity:1} 50%{opacity:0.35} }

/* ── Alert banner ── */
.alert-banner {
	display: flex; align-items: center; gap: 8px;
	background: rgba(220,38,38,0.05); border: 1px solid rgba(220,38,38,0.20);
	border-left: 3px solid #DC2626;
	margin: 10px 14px 0; padding: 8px 12px; border-radius: 8px;
	font-size: 0.70rem; font-weight: 600; color: #DC2626;
}
.alert-banner svg { width: 13px; height: 13px; stroke: #DC2626; flex-shrink: 0; }
.alert-banner-text { flex: 1; }
.alert-dismiss {
	width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;
	color: var(--text-lo); cursor: pointer; border-radius: 4px; font-size: 0.9rem;
	transition: color 0.15s, background 0.15s;
}
.alert-dismiss:hover { color: var(--text-mid); background: rgba(0,0,0,0.06); }

/* ── Main layout ── */
.dash-content { padding: 10px 14px 14px; }

/* 2-column equal-height grid */
.dash-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
	align-items: stretch;
	margin-bottom: 10px;
}
.status-col { display: flex; flex-direction: column; gap: 10px; }
.status-col .dash-card { flex: 1; }   /* host + service each take half the column */
.sys-col   { display: flex; flex-direction: column; }
.sys-col .dash-card { flex: 1; }      /* system card fills entire right column */

/* ── Card shell ── */
.dash-card {
	background: var(--pg-card);
	border: 1px solid var(--pg-card-bdr);
	border-radius: 12px; overflow: hidden;
	box-shadow: 0 1px 6px rgba(0,0,0,0.04);
	transition: border-color 0.35s, box-shadow 0.35s;
}
.dash-card.has-problems { border-color: rgba(220,38,38,0.28); box-shadow: 0 2px 14px rgba(220,38,38,0.07); }
.dash-card.has-warnings { border-color: rgba(217,119,6,0.25);  box-shadow: 0 2px 14px rgba(217,119,6,0.06); }

/* Card header */
.dash-card-header {
	display: flex; align-items: center; gap: 8px;
	background: var(--pg-thead); border-bottom: 1px solid var(--teal-bdr);
	padding: 9px 13px;
}
.dash-card-header svg { width: 12px; height: 12px; stroke: var(--teal); flex-shrink: 0; }
.dash-card-header-title {
	font-size: 0.63rem; font-weight: 700; letter-spacing: 0.09em;
	text-transform: uppercase; color: var(--text-mid); flex: 1;
}
.live-badge {
	font-size: 0.58rem; font-weight: 700; letter-spacing: 0.07em;
	color: #15803D; background: rgba(22,163,74,0.09);
	border: 1px solid rgba(22,163,74,0.22); padding: 2px 6px; border-radius: 4px;
	display: flex; align-items: center; gap: 4px;
}
.live-dot { width: 4px; height: 4px; border-radius: 50%; background: #16a34a; animation: dpulse 2s ease-in-out infinite; }
.dash-card-body { padding: 11px; }

/* ── Status tiles — light pastel ── */
.tiles-row { display: flex; gap: 6px; flex-wrap: wrap; }
.dash-tile {
	display: block; flex: 1; min-width: 60px;
	border-radius: 10px; padding: 14px 8px 11px;
	text-align: center; text-decoration: none; cursor: pointer;
	border: 1px solid transparent;
	touch-action: manipulation;
	transition: transform 0.13s ease, box-shadow 0.13s ease;
}
.dash-tile:hover    { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.09); }
.dash-tile:focus-visible { outline: 2px solid rgba(8,145,178,0.5); outline-offset: 3px; }
.tile-count {
	display: block; font-size: 1.85rem; font-weight: 800;
	letter-spacing: -0.05em; line-height: 1;
	margin-bottom: 5px; font-variant-numeric: tabular-nums;
}
.tile-label {
	display: block; font-size: 0.60rem; font-weight: 700;
	text-transform: uppercase; letter-spacing: 0.07em;
}

/* Pastel palette — light theme friendly */
.t-up     { background: #f0fdf4; border-color: #bbf7d0; }
.t-up     .tile-count { color: #15803d; }  .t-up     .tile-label { color: #16a34a; }

.t-down   { background: #fef2f2; border-color: #fecaca; }
.t-down   .tile-count { color: #b91c1c; }  .t-down   .tile-label { color: #dc2626; }

.t-unreach{ background: #faf5ff; border-color: #e9d5ff; }
.t-unreach.tile-count { color: #7c3aed; }  .t-unreach .tile-label { color: #8b5cf6; }
.t-unreach .tile-count { color: #7c3aed; }

.t-ok     { background: #f0fdf4; border-color: #bbf7d0; }
.t-ok     .tile-count { color: #15803d; }  .t-ok     .tile-label { color: #16a34a; }

.t-warn   { background: #fffbeb; border-color: #fde68a; }
.t-warn   .tile-count { color: #b45309; }  .t-warn   .tile-label { color: #d97706; }

.t-crit   { background: #fef2f2; border-color: #fecaca; }
.t-crit   .tile-count { color: #b91c1c; }  .t-crit   .tile-label { color: #dc2626; }

.t-unkn   { background: #faf5ff; border-color: #e9d5ff; }
.t-unkn   .tile-count { color: #7c3aed; }  .t-unkn   .tile-label { color: #8b5cf6; }

.t-pending{ background: #f8fafc; border-color: #e2e8f0; }
.t-pending.tile-count { color: #475569; }  .t-pending .tile-label { color: #64748b; }
.t-pending .tile-count { color: #475569; }

/* Alert state: stronger border */
.t-down.is-alert,   .t-crit.is-alert { border-color: #f87171; box-shadow: 0 0 0 1px #f87171; }
.t-warn.is-alert  { border-color: #fbbf24; box-shadow: 0 0 0 1px #fbbf24; }
.t-unreach.is-alert { border-color: #c084fc; box-shadow: 0 0 0 1px #c084fc; }

/* ── Skeleton loader ── */
.tile-skeleton {
	flex: 1; min-width: 60px; height: 70px; border-radius: 10px;
	background: linear-gradient(90deg,#EEF2FA 25%,#E2E8F8 50%,#EEF2FA 75%);
	background-size: 200% 100%; animation: shimmer 1.6s ease-in-out infinite;
}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* ── System info rows ── */
.sysinfo-row {
	display: flex; align-items: center; gap: 8px;
	padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.05);
}
.sysinfo-row:last-child { border-bottom: none; padding-bottom: 0; }
.sysinfo-row > svg { width: 11px; height: 11px; stroke: var(--text-lo); flex-shrink: 0; }
.sysinfo-key { font-size: 0.63rem; color: var(--text-lo); min-width: 78px; }
.sysinfo-val { font-size: 0.70rem; font-weight: 600; color: var(--text-hi); }
.sysinfo-val.ok  { color: #15803d; }
.sysinfo-val.err { color: #b91c1c; }
.sysinfo-val.warn { color: #b45309; }

/* ── Service Problems section ── */
.prob-section-hdr {
	display: flex; align-items: center; gap: 10px;
	background: var(--pg-thead); border: 1px solid var(--pg-card-bdr);
	border-bottom: 1px solid var(--teal-bdr);
	border-radius: 12px 12px 0 0;
	padding: 10px 14px;
}
.prob-section-icon svg { width: 13px; height: 13px; stroke: var(--teal); }
.prob-section-title {
	font-size: 0.65rem; font-weight: 700; letter-spacing: 0.09em;
	text-transform: uppercase; color: var(--text-mid); flex: 1;
}
.prob-chips { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.prob-chip {
	font-size: 0.63rem; font-weight: 700; padding: 2px 8px; border-radius: 5px; border: 1px solid;
}
.prob-chip.pc-crit { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.prob-chip.pc-warn { color: #b45309; background: #fffbeb; border-color: #fde68a; }
.prob-chip.pc-unkn { color: #7c3aed; background: #faf5ff; border-color: #e9d5ff; }
.prob-chip.pc-ok   { color: #15803d; background: #f0fdf4; border-color: #bbf7d0; }
.prob-section-actions { display: flex; align-items: center; gap: 6px; }
.prob-section-link {
	font-size: 0.67rem; font-weight: 600; color: var(--teal);
	padding: 3px 9px; border-radius: 6px; border: 1px solid var(--teal-bdr);
	background: var(--teal-bg); transition: background 0.13s, border-color 0.13s;
}
.prob-section-link:hover { background: rgba(8,145,178,0.13); border-color: rgba(8,145,178,0.35); }
.prob-table-wrap {
	background: var(--pg-card);
	border: 1px solid var(--pg-card-bdr); border-top: none;
	border-radius: 0 0 12px 12px;
	overflow: hidden;
}

/* ── All-OK empty state for problems section ── */
.prob-ok-state {
	display: flex; flex-direction: column; align-items: center; justify-content: center;
	padding: 32px 20px; gap: 10px; color: var(--text-mid);
}
.prob-ok-state svg { width: 36px; height: 36px; color: #4ade80; }
.prob-ok-state strong { color: #15803d; }

/* ── Active filter chip indicator ── */
.prob-chip.prob-fbtn { cursor: pointer; transition: box-shadow 0.13s; }
.prob-chip.prob-fbtn.active { box-shadow: 0 0 0 2px rgba(8,145,178,0.40); }
#unacked-toggle.active      { box-shadow: 0 0 0 2px rgba(217,119,6,0.45); }

/* ── Tile error state ── */
.dash-error { font-size: 0.72rem; color: #b91c1c; padding: 12px 0; text-align: center; }

/* ── Reduced motion ── */
@media (prefers-reduced-motion: reduce) {
	@keyframes dpulse  { 0%,100%{opacity:1} }
	@keyframes shimmer { 0%,100%{background-position:0 0} }
	.live-dot { animation: none; }
	.cdown-arc { transition: none; }
	.tile-skeleton { animation: none; background: #EEF2FA; }
	.dash-tile { transition: none !important; }
	.dash-card { transition: none !important; }
}
</style>
<script src="js/jquery-3.7.1.min.js"></script>
<script src="js/nag_funcs.js"></script>
<script>
var C = '<?php echo addslashes($cgi); ?>';
var _hostData = null, _svcData = null;
var REFRESH = <?php echo $refresh; ?>, _elapsed = 0;

$(function() {
	renderSkel('host-body', 4);
	renderSkel('svc-body', 5);
	fetchAll();
	setInterval(fetchAll, REFRESH * 1000);
	setInterval(function() {
		_elapsed++;
		if (_elapsed >= REFRESH) _elapsed = 0;
		var pct = _elapsed / REFRESH;
		$('#refresh-countdown').text(REFRESH - _elapsed);
		var arc = document.getElementById('cdown-arc');
		if (arc) arc.style.strokeDashoffset = (75.4 * pct).toFixed(2);
	}, 1000);
});

function fetchAll() {
	_elapsed = 0;
	$('#refresh-countdown').text(REFRESH);
	var arc = document.getElementById('cdown-arc');
	if (arc) arc.style.strokeDashoffset = '0';
	fetchHosts(); fetchServices(); fetchDaemon();
}
function renderSkel(id, n) {
	var h = '<div class="tiles-row">';
	for (var i=0;i<n;i++) h+='<div class="tile-skeleton"></div>';
	$('#'+id).html(h+'</div>');
}

/* ── Hosts ── */
function fetchHosts() {
	$.ajax({ url:C+'/statusjson.cgi?query=hostcount', dataType:'json', timeout:15000,
		xhrFields:{withCredentials:true},
		success:function(d){
			var c=(d&&d.data&&d.data.count)||{};
			_hostData={up:c.up||0,down:c.down||0,unreach:c.unreachable||0,pend:c.pending||0};
			renderHostTiles(_hostData); checkProblems();
		},
		error:function(){ $('#host-body').html('<div class="dash-error">Cannot reach Nagios API &mdash; check daemon</div>'); }
	});
}
function renderHostTiles(c) {
	$('#host-body').html(
		'<div class="tiles-row">'
		+T('t-up',     c.up,     'Up',          'hosts.php',        c.up>0?'':'')
		+T('t-down',   c.down,   'Down',        'hostproblems.php', c.down>0?' is-alert':'')
		+T('t-unreach',c.unreach,'Unreachable', 'hostproblems.php', c.unreach>0?' is-alert':'')
		+T('t-pending',c.pend,   'Pending',     'hosts.php',        '')
		+'</div>'
	);
	$('#host-body').closest('.dash-card')
		.toggleClass('has-problems', c.down>0)
		.toggleClass('has-warnings', c.unreach>0 && c.down===0);
}

/* ── Services ── */
function fetchServices() {
	$.ajax({ url:C+'/statusjson.cgi?query=servicecount', dataType:'json', timeout:15000,
		xhrFields:{withCredentials:true},
		success:function(d){
			var c=(d&&d.data&&d.data.count)||{};
			_svcData={ok:c.ok||0,warn:c.warning||0,crit:c.critical||0,unkn:c.unknown||0,pend:c.pending||0};
			renderSvcTiles(_svcData); checkProblems();
		},
		error:function(){ $('#svc-body').html('<div class="dash-error">Cannot reach Nagios API &mdash; check daemon</div>'); }
	});
}
function renderSvcTiles(c) {
	$('#svc-body').html(
		'<div class="tiles-row">'
		+T('t-ok',     c.ok,   'OK',       'services.php',        '')
		+T('t-warn',   c.warn, 'Warning',  'serviceproblems.php', c.warn>0?' is-alert':'')
		+T('t-crit',   c.crit, 'Critical', 'serviceproblems.php', c.crit>0?' is-alert':'')
		+T('t-unkn',   c.unkn, 'Unknown',  'services.php',        c.unkn>0?' is-alert':'')
		+T('t-pending',c.pend, 'Pending',  'services.php',        '')
		+'</div>'
	);
	$('#svc-body').closest('.dash-card')
		.toggleClass('has-problems', c.crit>0)
		.toggleClass('has-warnings', c.warn>0 && c.crit===0);
}

/* ── Problems banner ── */
function checkProblems() {
	if (!_hostData||!_svcData) return;
	var p=[];
	if(_hostData.down>0)  p.push(_hostData.down+' host'+(_hostData.down>1?'s':'')+' DOWN');
	if(_hostData.unreach>0) p.push(_hostData.unreach+' UNREACHABLE');
	if(_svcData.crit>0)   p.push(_svcData.crit+' service'+(_svcData.crit>1?'s':'')+' CRITICAL');
	if(_svcData.warn>0)   p.push(_svcData.warn+' service'+(_svcData.warn>1?'s':'')+' WARNING');
	if(p.length){ $('#problems-text').text(p.join('  ·  ')); $('#problems-banner').show(); }
	else { $('#problems-banner').hide(); }
}

/* ── Daemon ── */
function fetchDaemon() {
	$.ajax({ url:C+'/statusjson.cgi?query=programstatus', dataType:'json', timeout:10000,
		xhrFields:{withCredentials:true},
		success:function(d){
			d=(d&&d.data&&d.data.programstatus)||false;
			if(d&&d.nagios_pid){
				var mode=d.daemon_mode?'Daemon':'Process';
				var bool=function(v){return v==1||v===true;};
				var on='<span class="sysinfo-val ok">Enabled</span>';
				var off='<span class="sysinfo-val err">Disabled</span>';
				setProc('enabled', mode+' · PID '+d.nagios_pid);
				$('#proc-label-sys').text(mode);
				$('#si-pid').text('PID '+d.nagios_pid);
				$('#si-notif').html(bool(d.enable_notifications)?on:off);
				$('#si-checks').html(bool(d.execute_service_checks)
					?'<span class="sysinfo-val ok">Active</span>'
					:'<span class="sysinfo-val warn">Passive only</span>');
				$('#si-flap').html(bool(d.enable_flap_detection)?on:off);
				$('#si-evth').html(bool(d.enable_event_handlers)?on:off);
				var started='—';
				if(d.program_start){
					// Fix: Properly convert UTC timestamp to local timezone
					var dt = new Date(d.program_start * 1000);
					var userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
					started = dt.toLocaleDateString([], { 
						month: 'short', 
						day: 'numeric',
						timeZone: userTimezone
					}) + ' ' + dt.toLocaleTimeString([], { 
						hour: '2-digit', 
						minute: '2-digit',
						timeZone: userTimezone
					});
				}
				$('#si-started').text(started);
			} else {
				setProc('disabled','Not running');
				$('#proc-label-sys').text('Not running');
			}
		},
		error:function(){
			setProc('disabled','Status unavailable');
			$('#proc-label-sys').text('Unavailable');
		}
	});
}
function setProc(cls,txt){
	$('#proc-dot').attr('class','proc-dot '+cls); $('#proc-label').text(txt);
}

/* ── Tile factory ── */
function T(cls,n,label,href,extra){
	return '<a href="'+href+'" class="dash-tile '+cls+(extra||'')+'" target="main">'
		+'<span class="tile-count">'+n+'</span>'
		+'<span class="tile-label">'+label+'</span>'
		+'</a>';
}

/* ── Problems table toggle (server-side rendered) ── */
var _stateFilter='all', _unackedOnly=false;
function applyAll(){
	document.querySelectorAll('tr.data-row').forEach(function(r){
		if(r.dataset.collapsed==='1'){r.style.display='none';return;}
		var stOk=(_stateFilter==='all'||r.dataset.state===_stateFilter);
		var akOk=(!_unackedOnly||r.dataset.acked==='0');
		r.style.display=(stOk&&akOk)?'':'none';
	});
	document.querySelectorAll('tr.host-grp-hdr').forEach(function(hdr){
		var any=false;
		document.querySelectorAll('tr.data-row[data-host="'+hdr.dataset.host+'"]').forEach(function(r){
			if(r.style.display!=='none') any=true;
		});
		hdr.style.display=any?'':'none';
	});
}
function toggleGroup(hdr){
	var h=hdr.dataset.host, open=hdr.dataset.open==='1';
	hdr.dataset.open=open?'0':'1';
	hdr.querySelector('.chevron-icon').style.transform=open?'rotate(-90deg)':'';
	document.querySelectorAll('tr.data-row[data-host="'+h+'"]').forEach(function(r){
		r.dataset.collapsed=open?'1':'0'; r.style.display=open?'none':'';
	});
}
function filterRows(btn,state){
	document.querySelectorAll('.prob-fbtn').forEach(function(b){b.classList.remove('active');});
	btn.classList.add('active'); _stateFilter=state; applyAll();
}
function toggleUnacked(btn){
	_unackedOnly=!_unackedOnly; btn.classList.toggle('active',_unackedOnly); applyAll();
}
</script>
</head>
<body>

<!-- ── Topbar ── -->
<div class="topbar">
	<div class="tb-left">
		<span class="tb-icon">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
		</span>
		<span class="tb-page-title">Tactical Overview</span>
	</div>
	<div class="tb-right">
		<div class="cdown-wrap">
			<svg class="cdown-svg" viewBox="0 0 36 36">
				<circle class="cdown-track" cx="18" cy="18" r="12"/>
				<circle class="cdown-arc" id="cdown-arc" cx="18" cy="18" r="12"/>
			</svg>
			<span class="cdown-num" id="refresh-countdown"><?php echo $refresh; ?></span>
		</div>
	</div>
</div>

<!-- ── Alert banner ── -->
<div id="problems-banner" style="display:none">
	<div class="alert-banner">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
		<span class="alert-banner-text" id="problems-text"></span>
		<div class="alert-dismiss" onclick="$('#problems-banner').hide()" title="Dismiss">&times;</div>
	</div>
</div>

<!-- ── Dashboard ── -->
<div class="dash-content">

	<!-- Row 1: 2 equal-height columns -->
	<div class="dash-grid">

		<!-- Left: Host Status + Service Status stacked -->
		<div class="status-col">
			<div class="dash-card">
				<div class="dash-card-header">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
					<span class="dash-card-header-title">Host Status</span>
					<div class="live-badge"><div class="live-dot"></div>LIVE</div>
				</div>
				<div class="dash-card-body" id="host-body"></div>
			</div>

			<div class="dash-card">
				<div class="dash-card-header">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
					<span class="dash-card-header-title">Service Status</span>
					<div class="live-badge"><div class="live-dot"></div>LIVE</div>
				</div>
				<div class="dash-card-body" id="svc-body"></div>
			</div>
		</div><!-- /.status-col -->

		<!-- Right: System Status (stretches full height) -->
		<div class="sys-col">
			<div class="dash-card">
				<div class="dash-card-header">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
					<span class="dash-card-header-title">System Status</span>
				</div>
				<div class="dash-card-body">
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
						<span class="sysinfo-key">Process</span>
						<span class="sysinfo-val" id="proc-label-sys">Checking&hellip;</span>
					</div>
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
						<span class="sysinfo-key">Daemon PID</span>
						<span class="sysinfo-val" id="si-pid">—</span>
					</div>
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
						<span class="sysinfo-key">Notifications</span>
						<span id="si-notif" class="sysinfo-val">—</span>
					</div>
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
						<span class="sysinfo-key">Check mode</span>
						<span id="si-checks" class="sysinfo-val">—</span>
					</div>
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
						<span class="sysinfo-key">Flap detection</span>
						<span id="si-flap" class="sysinfo-val">—</span>
					</div>
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
						<span class="sysinfo-key">Event handlers</span>
						<span id="si-evth" class="sysinfo-val">—</span>
					</div>
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						<span class="sysinfo-key">Started</span>
						<span id="si-started" class="sysinfo-val">—</span>
					</div>
					<div class="sysinfo-row">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
						<span class="sysinfo-key">Version</span>
						<span class="sysinfo-val"><?php echo $this_version; ?></span>
					</div>
					<div class="sysinfo-row" style="border-bottom:none">
						<span class="refresh-dot"></span>
						<span style="font-size:0.62rem;font-weight:600;color:#6B7280;letter-spacing:0.05em;text-transform:uppercase">Auto-refresh <?php echo $refresh; ?>s</span>
					</div>
				</div>
			</div>
		</div><!-- /.sys-col -->

	</div><!-- /.dash-grid -->

	<!-- Row 2: Service Problems (full width) -->
	<div>
		<!-- Header tile -->
		<div class="prob-section-hdr">
			<span class="prob-section-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			</span>
			<span class="prob-section-title">Service Problems</span>

			<div class="prob-chips">
				<?php if ($pcounts['crit']): ?>
				<button class="prob-chip pc-crit prob-fbtn active" onclick="filterRows(this,'critical')"><?php echo $pcounts['crit']; ?> Critical</button>
				<?php endif; ?>
				<?php if ($pcounts['warn']): ?>
				<button class="prob-chip pc-warn prob-fbtn <?php echo !$pcounts['crit']?'active':''; ?>" onclick="filterRows(this,'warning')"><?php echo $pcounts['warn']; ?> Warning</button>
				<?php endif; ?>
				<?php if ($pcounts['unkn']): ?>
				<button class="prob-chip pc-unkn prob-fbtn" onclick="filterRows(this,'unknown')"><?php echo $pcounts['unkn']; ?> Unknown</button>
				<?php endif; ?>
				<?php if ($total_prob > 0): ?>
				<button class="prob-chip pc-ok prob-fbtn" onclick="filterRows(this,'all')"><?php echo $total_prob; ?> Total</button>
				<?php else: ?>
				<span class="prob-chip pc-ok"><?php echo $total_prob; ?> Total</span>
				<?php endif; ?>
			</div>

			<div class="prob-section-actions">
				<?php if ($total_prob > 0 && $unacked > 0): ?>
				<button class="prob-chip pc-warn" style="cursor:pointer" id="unacked-toggle" onclick="toggleUnacked(this)">Unacked (<?php echo $unacked; ?>)</button>
				<?php endif; ?>
				<a href="serviceproblems.php" class="prob-section-link" target="main">View Full Page &rarr;</a>
			</div>
		</div>

		<!-- Table -->
		<div class="prob-table-wrap">
		<?php if (!$data['ok']): ?>
			<div style="padding:12px 14px">
				<div class="cell-state is-error">Error reading status file: <?php echo h($data['error']); ?></div>
			</div>
		<?php elseif (empty($problems)): ?>
			<div class="prob-ok-state">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
				<div>All services are <strong>OK</strong> &mdash; no problems detected.</div>
			</div>
		<?php else: ?>
		<table class="dtbl">
		<thead><tr>
			<th style="width:90px">Status</th>
			<th>Service</th>
			<th style="width:100px">Duration</th>
			<th style="width:90px">Last Check</th>
			<th>Status Info</th>
			<th style="width:100px">Actions</th>
		</tr></thead>
		<tbody>
		<?php foreach ($grouped as $hn => $svcs):
		    $hst   = $host_state[$hn] ?? ['text'=>'UNKNOWN','cls'=>'badge-pending','row'=>'','ord'=>2];
		    $heurl = h('host.php?host='.urlencode($hn));
		    $haurl = get_action_url(['action_url'=>''], $aurl_map, $hn, '');
		    $hw    = 99; foreach ($svcs as $s) { $o=svc_state_info($s)['ord']; if($o<$hw) $hw=$o; }
		    $h_acked=0; foreach ($svcs as $s) { if(($s['problem_has_been_acknowledged']??'0')==='1') $h_acked++; }
		    $h_all_acked = ($h_acked===count($svcs)) ? '1' : '0';
		?>
		<tr class="host-grp-hdr" data-host="<?php echo h($hn); ?>" data-open="1"
		    data-hacked="<?php echo $h_all_acked; ?>"
		    onclick="toggleGroup(this)">
			<td colspan="6">
				<span class="hgrp-chevron">
					<svg class="chevron-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
				</span>
				<span class="hgrp-name">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;opacity:.6"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
					<a href="<?php echo $heurl; ?>" target="main" onclick="event.stopPropagation()"><?php echo h($hn); ?></a>
				</span>
				<?php echo state_badge($hst); ?>
				<span class="hgrp-count"><?php echo count($svcs); ?> problem<?php echo count($svcs)!==1?'s':''; ?></span>
				<span class="hgrp-actions" onclick="event.stopPropagation()">
					<?php if ($haurl): ?><a href="<?php echo h($haurl); ?>" target="_blank" title="Graph">
						<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></a>
					<?php endif; ?>
					<a href="<?php echo $heurl; ?>" target="main" class="detail-lnk" title="Host detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
				</span>
			</td>
		</tr>
		<?php foreach ($svcs as $s):
		    $st   = svc_state_info($s);
		    $dur  = (!empty($s['last_state_change'])&&$s['last_state_change']!=='0') ? fmt_dur($now-(int)$s['last_state_change']) : '-';
		    $lc   = (!empty($s['last_check'])&&$s['last_check']!=='0') ? fmt_ago((int)$s['last_check']) : '-';
		    $ack  = ($s['problem_has_been_acknowledged']??'0')==='1';
		    $dt   = ((int)($s['scheduled_downtime_depth']??0))>0;
		    $sn   = h($s['service_description']??'');
		    $out  = h($s['plugin_output']??'');
		    $durl = h('service.php?host='.urlencode($hn).'&service='.urlencode($s['service_description']??''));
		    $aurl = get_action_url($s, $aurl_map, $hn, $s['service_description']??'');
		    $stl  = strtolower($st['text']);
		?>
		<tr class="data-row <?php echo $st['row']; ?>"
		    data-host="<?php echo h($hn); ?>"
		    data-state="<?php echo $stl; ?>"
		    data-acked="<?php echo $ack?'1':'0'; ?>">
			<td><?php echo state_badge($st); ?></td>
			<td class="c-svc">
				<a href="<?php echo $durl; ?>" target="main"><?php echo $sn; ?></a>
				<?php if($ack) echo '<span class="tag-ack">ACK</span>'; ?>
				<?php if($dt)  echo '<span class="tag-dt">DOWNTIME</span>'; ?>
			</td>
			<td class="c-dur"><?php echo $dur; ?></td>
			<td class="c-lc"><?php echo $lc; ?></td>
			<td class="c-out" title="<?php echo $out; ?>"><?php echo $out; ?></td>
			<td style="white-space:nowrap">
				<?php if($aurl): ?>
				<a href="<?php echo h($aurl); ?>" target="_blank" class="action-graph" title="Graph">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
							</a>
				<?php endif; ?>
				<a href="<?php echo $durl; ?>" target="main" class="action-detail" title="Detail"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
			</td>
		</tr>
		<?php endforeach; ?>
		<?php endforeach; ?>
		</tbody>
		</table>
		<?php endif; ?>
		</div><!-- /.prob-table-wrap -->

	</div><!-- /problems section -->

</div><!-- /.dash-content -->

<script>
/* Sync system card "Process" row with daemon pill */
(function() {
	var orig = window.setProc;
	window.setProc = function(cls, txt) {
		orig(cls, txt);
		$('#proc-label-sys').text(txt)
			.removeClass('ok err warn')
			.addClass(cls === 'enabled' ? 'ok' : cls === 'disabled' ? 'err' : '');
	};
})();
/* Default filter: show critical first if any, else all */
<?php if ($pcounts['crit'] > 0): ?>
window.addEventListener('DOMContentLoaded', function(){ _stateFilter='critical'; applyAll(); });
<?php elseif ($pcounts['warn'] > 0 && !$pcounts['crit']): ?>
window.addEventListener('DOMContentLoaded', function(){ _stateFilter='warning'; applyAll(); });
<?php endif; ?>
</script>

</body>
</html>