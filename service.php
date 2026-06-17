<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$mcf = $cfg['main_config_file'] ?? '/usr/local/nagios/etc/nagios.cfg';
$now = time();

/* ── Parameters ── */
$hn_raw  = trim($_GET['host']    ?? '');
$sn_raw  = trim($_GET['service'] ?? '');
if ($hn_raw === '' || $sn_raw === '') {
    header('Location: services.php'); exit;
}

/* ── Parse status ── */
$sdata = nagios_parse_status($sf);
$sblk  = null;
foreach ($sdata['services'] as $s) {
    if (($s['host_name'] ?? '') === $hn_raw && ($s['service_description'] ?? '') === $sn_raw) {
        $sblk = $s; break;
    }
}
$hblk = null;
foreach ($sdata['hosts'] as $h) {
    if (($h['host_name'] ?? '') === $hn_raw) { $hblk = $h; break; }
}

/* ── Service config from objects.cache ── */
$cache   = nagios_find_objects_cache($mcf);
$cfg_res = nagios_parse_objects_config($cache);
$scfg    = [];
foreach ($cfg_res['data']['services'] as $sc) {
    if (($sc['host_name'] ?? '') === $hn_raw && ($sc['service_description'] ?? '') === $sn_raw) {
        $scfg = $sc; break;
    }
}

/* ── Service group membership ── */
$grp_res    = nagios_parse_groups($cache);
$svc_groups = [];
foreach ($grp_res['servicegroups'] ?? [] as $g) {
    $members = array_map('trim', explode(',', $g['members'] ?? ''));
    for ($i = 0; $i + 1 < count($members); $i += 2) {
        if ($members[$i] === $hn_raw && $members[$i+1] === $sn_raw) {
            $svc_groups[] = $g['servicegroup_name'];
            break;
        }
    }
}

/* ── Comments + downtime for this service ── */
$cdr       = nagios_parse_comments_downtime($sf);
$svc_comments  = array_values(array_filter($cdr['comments'],  function($c) use($hn_raw,$sn_raw){
    return ($c['host_name']??'') === $hn_raw && ($c['service_description']??'') === $sn_raw;
}));
$svc_downtimes = array_values(array_filter($cdr['downtimes'], function($d) use($hn_raw,$sn_raw){
    return ($d['host_name']??'') === $hn_raw && ($d['service_description']??'') === $sn_raw;
}));

/* ── Action URL ── */
$aurl_map   = nagios_load_action_urls($cache);
$action_url = get_action_url($sblk ?? [], $aurl_map, $hn_raw, $sn_raw);

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

function svc_perf_bar_pct($num, $warn, $crit, $maxv) {
    if ($maxv !== '' && is_numeric($maxv) && (float)$maxv > 0) $ceil = (float)$maxv;
    elseif ($crit !== '' && is_numeric($crit) && (float)$crit > 0) $ceil = (float)$crit * 1.25;
    elseif ($warn !== '' && is_numeric($warn) && (float)$warn > 0) $ceil = (float)$warn * 1.5;
    else return -1;
    return min(100, max(0, round(($num / $ceil) * 100)));
}

/* ── State derivation ── */
if (!$sblk) {
    $st = ['text'=>'UNKNOWN','cls'=>'badge-unkn','row'=>'','ord'=>3];
} else {
    $st = svc_state_info($sblk);
}

/* ── Hero accent colors by service state ── */
$hero_colors = [
    'OK'       => ['hac'=>'#4ade80', 'hbg'=>'rgba(74,222,128,0.05)',  'sbadge'=>'sbadge-up'],
    'WARNING'  => ['hac'=>'#fbbf24', 'hbg'=>'rgba(251,191,36,0.06)',  'sbadge'=>'sbadge-warn'],
    'CRITICAL' => ['hac'=>'#ef4444', 'hbg'=>'rgba(239,68,68,0.07)',   'sbadge'=>'sbadge-down'],
    'UNKNOWN'  => ['hac'=>'#a855f7', 'hbg'=>'rgba(168,85,247,0.06)', 'sbadge'=>'sbadge-unknown'],
    'PENDING'  => ['hac'=>'#94a3b8', 'hbg'=>'rgba(148,163,184,0.04)','sbadge'=>'sbadge-pending'],
];
$hc = $hero_colors[$st['text']] ?? $hero_colors['UNKNOWN'];

$hn_h   = h($hn_raw);
$sn_h   = h($sn_raw);

/* ── Key fields from servicestatus block ── */
$cur_state   = (int)($sblk['current_state']            ?? -1);
$state_type  = (int)($sblk['state_type']               ?? 1);
$cur_att     = (int)($sblk['current_attempt']           ?? 0);
$max_att     = (int)($sblk['max_attempts']              ?? ($scfg['max_check_attempts'] ?? 0));
$lsc         = (int)($sblk['last_state_change']         ?? 0);
$last_chk    = (int)($sblk['last_check']                ?? 0);
$next_chk    = (int)($sblk['next_check']                ?? 0);
$exec_time   = (float)($sblk['check_execution_time']    ?? 0);
$latency     = (float)($sblk['check_latency']           ?? 0);
$chk_type    = (int)($sblk['check_type']                ?? 0);
$ack         = ($sblk['problem_has_been_acknowledged']   ?? '0') === '1';
$in_dt       = ((int)($sblk['scheduled_downtime_depth'] ?? 0)) > 0;
$flapping    = ($sblk['is_flapping']                    ?? '0') === '1';
$pct_flap    = (float)($sblk['percent_state_change']    ?? 0);
$plugin_out  = $sblk['plugin_output']       ?? '';
$long_out    = $sblk['long_plugin_output']  ?? '';
$perf_raw    = $sblk['performance_data']    ?? '';
$perf_metrics = parse_perf($perf_raw);

$notif_en   = ($sblk['notifications_enabled']    ?? '1') === '1';
$act_en     = ($sblk['active_checks_enabled']     ?? '1') === '1';
$pas_en     = ($sblk['passive_checks_enabled']    ?? '1') === '1';
$evth_en    = ($sblk['event_handler_enabled']     ?? '1') === '1';
$flap_en    = ($sblk['flap_detection_enabled']    ?? '1') === '1';
$perf_en    = ($sblk['process_performance_data']  ?? '1') === '1';

$dur_str      = $lsc ? fmt_dur($now - $lsc) : '—';
$last_chk_str = $last_chk ? fmt_ago($last_chk)             : '—';
$last_chk_abs = $last_chk ? date('Y-m-d H:i:s', $last_chk) : '—';
$next_diff    = $next_chk ? ($next_chk - $now) : 0;
$next_str     = ($next_chk > 0) ? (($next_diff >= 0 ? 'in ' : '−') . fmt_dur(abs($next_diff))) : '—';
$next_abs     = $next_chk ? date('H:i:s', $next_chk) : '—';

/* ── Host state for banner ── */
$hst = $hblk ? host_state_info($hblk) : ['text'=>'UNKNOWN','cls'=>'badge-pending','row'=>'','ord'=>2];

/* ── Comment entry_type labels ── */
$etype_labels = [1=>'Comment', 2=>'Downtime', 3=>'Flapping', 4=>'Ack'];
$etype_cls    = [1=>'etype-user-cmt', 2=>'etype-downtime-cmt', 3=>'etype-flap-cmt', 4=>'etype-ack'];

/* ── CGI command URLs ── */
$cmd_base   = $cgi . '/cmd.cgi';
$qs_host    = '&host='    . urlencode($hn_raw);
$qs_svc     = '&service=' . urlencode($sn_raw);
$url_ack    = h($cmd_base.'?cmd_typ=34'.$qs_host.$qs_svc);
$url_unack  = h($cmd_base.'?cmd_typ=52'.$qs_host.$qs_svc);
$url_dt     = h('schedule_downtime.php?host='.urlencode($hn_raw).'&service='.urlencode($sn_raw));
$url_cmt    = h('add_comment.php?host='.urlencode($hn_raw).'&service='.urlencode($sn_raw));
$url_recheck= h('reschedule.php?host='.urlencode($hn_raw).'&service='.urlencode($sn_raw));
$url_notif_en  = h($cmd_base.'?cmd_typ=22'.$qs_host.$qs_svc);
$url_notif_dis = h($cmd_base.'?cmd_typ=23'.$qs_host.$qs_svc);
$url_chk_en    = h($cmd_base.'?cmd_typ=5' .$qs_host.$qs_svc);
$url_chk_dis   = h($cmd_base.'?cmd_typ=6' .$qs_host.$qs_svc);
$url_pchk_en   = h($cmd_base.'?cmd_typ=40'.$qs_host.$qs_svc);
$url_pchk_dis  = h($cmd_base.'?cmd_typ=41'.$qs_host.$qs_svc);
$url_evth_en   = h($cmd_base.'?cmd_typ=35'.$qs_host.$qs_svc);
$url_evth_dis  = h($cmd_base.'?cmd_typ=36'.$qs_host.$qs_svc);
$url_flap_en   = h($cmd_base.'?cmd_typ=38'.$qs_host.$qs_svc);
$url_flap_dis  = h($cmd_base.'?cmd_typ=39'.$qs_host.$qs_svc);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo $sn_h; ?> &mdash; <?php echo $hn_h; ?> &mdash; CiMon</title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<style>
/* ── Compact page spacing ── */
body     { padding: 10px 14px; }
.page-hd { margin: -10px -14px 10px; }
.data-card { margin-bottom: 8px; }
.host-section-hd { margin-bottom: 5px; }

/* ── Back button ── */
.phd-back-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px 5px 9px; border-radius: 7px; text-decoration: none;
    background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.09);
    transition: background 150ms ease, border-color 150ms ease;
    white-space: nowrap; color: inherit;
}
.phd-back-btn:hover  { background: rgba(0,0,0,0.08); border-color: rgba(0,0,0,0.18); }
.phd-back-btn svg    { width: 13px; height: 13px; flex-shrink: 0; color: var(--text-lo); stroke: currentColor; }
.phd-back-btn .sbth-label { font-size: 0.58rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.07em; color: var(--text-lo); }
.phd-back-btn .sbth-name  { font-size: 0.70rem; font-weight: 700; color: var(--text-hi); }

/* ── Host context bar ── */
.svc-host-ctx { display: flex; align-items: center; gap: 10px; padding: 6px 14px;
    background: rgba(0,0,0,0.03); border-bottom: 1px solid rgba(0,0,0,0.06);
    font-size: 0.66rem; }
.svc-host-ctx-label { color: var(--text-lo); }
.svc-host-ctx-name  { font-weight: 700; color: var(--text-hi); }
.svc-host-ctx-name a { color: inherit; text-decoration: none; }
.svc-host-ctx-name a:hover { color: var(--amber); }
.svc-host-ctx-out   { color: var(--text-lo); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Compact hero ── */
.svc-det-hero { padding: 11px 16px 10px; border-left: 4px solid var(--hac, #4ade80);
    background: radial-gradient(ellipse 60% 80% at 0% 50%, var(--hbg, transparent), transparent); }
.svc-det-top  { display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: center; }
@media (max-width: 640px) { .svc-det-top { grid-template-columns: auto 1fr; } .svc-det-meta { display:none; } }

.svc-det-id   { min-width: 0; }
.svc-det-name { font-size: 1.05rem; font-weight: 800; color: var(--text-hi); line-height: 1.2; word-break: break-word; }
.svc-det-host { font-size: 0.70rem; color: var(--amber); font-weight: 600; margin-top: 3px; }
.svc-det-host a { color: inherit; text-decoration: none; }
.svc-det-host a:hover { text-decoration: underline; }
.svc-det-tags { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 5px; }

.svc-det-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; min-width: 80px; }
.svc-det-meta-row { font-size: 0.58rem; color: var(--text-lo); white-space: nowrap; }
.svc-det-meta-val { color: var(--text-md); font-weight: 600; }

/* ── Compact action buttons ── */
.svc-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; padding-top: 8px;
    border-top: 1px solid rgba(0,0,0,0.06); }
.svc-act-btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 9px;
    border-radius: 6px; font-size: 0.61rem; font-weight: 600; text-decoration: none;
    border: 1px solid rgba(0,0,0,0.09); color: var(--text-mid);
    background: rgba(0,0,0,0.03); transition: background .15s, border-color .15s; touch-action: manipulation; }
.svc-act-btn:hover  { background: rgba(0,0,0,0.06); border-color: rgba(0,0,0,0.18); color: var(--text-hi); }
.svc-act-btn svg    { width: 11px; height: 11px; flex-shrink: 0; }
.svc-act-btn.s-recheck { border-color:rgba(8,145,178,0.3); color:var(--amber); background:rgba(8,145,178,0.06); }
.svc-act-btn.s-recheck:hover { background:rgba(8,145,178,0.12); }
.svc-act-btn.s-ack  { border-color:rgba(37,99,235,0.3); color:#1D4ED8; background:rgba(37,99,235,0.07); }
.svc-act-btn.s-ack:hover { background:rgba(37,99,235,0.13); }
.svc-act-btn.s-dt   { border-color:rgba(16,185,129,0.3); color:#047857; background:rgba(16,185,129,0.06); }
.svc-act-btn.s-dt:hover { background:rgba(16,185,129,0.12); }
.svc-act-btn.s-cmt  { border-color:rgba(0,0,0,0.09); }
.svc-act-btn.s-danger { border-color:rgba(239,68,68,0.3); color:#DC2626; background:rgba(239,68,68,0.06); }
.svc-act-btn.s-danger:hover { background:rgba(239,68,68,0.12); }

/* ── Metrics: 6-col strip (was 3×2 grid) ── */
.svc-metrics { display: grid; grid-template-columns: repeat(6, 1fr); }
@media (max-width: 700px) { .svc-metrics { grid-template-columns: repeat(3, 1fr); } }
.svc-metric          { padding: 9px 12px; border-right: 1px solid rgba(0,0,0,0.05); }
.svc-metric:nth-child(3)  { border-right: 1px solid rgba(0,0,0,0.05); }
.svc-metric:nth-child(n+4) { border-top: none; }
.svc-metric:nth-child(3n)  { border-right: 1px solid rgba(0,0,0,0.05); }
.svc-metric:nth-child(6)   { border-right: none; }
.svc-metric:hover { background: rgba(0,0,0,0.018); }
.svc-metric-key { font-size: 0.53rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.08em; color: var(--text-lo); margin-bottom: 3px; }
.svc-metric-val { font-size: 0.85rem; font-weight: 700; color: var(--text-hi); }
.svc-metric-sub { font-size: 0.57rem; color: var(--text-lo); margin-top: 1px; }
.svc-metric-ok   { color: #15803D; }
.svc-metric-warn { color: #D97706; }
.svc-metric-crit { color: #DC2626; }
.svc-metric-unkn { color: #7C3AED; }

/* ── Output block ── */
.svc-output { padding: 10px 16px 9px; border-bottom: 1px solid rgba(0,0,0,0.06); }
.svc-out-text { font-family: monospace; font-size: 0.74rem; line-height: 1.5;
    padding: 8px 11px; border-radius: 6px; border-left: 3px solid rgba(0,0,0,0.10);
    background: rgba(0,0,0,0.03); white-space: pre-wrap; word-break: break-word; color: var(--text-body); }
.svc-out-ok   { border-left-color: rgba(22,163,74,0.45); }
.svc-out-warn { border-left-color: rgba(217,119,6,0.45); }
.svc-out-crit { border-left-color: rgba(220,38,38,0.5); }
.svc-out-unkn { border-left-color: rgba(124,58,237,0.4); }
.svc-out-long { font-family: monospace; font-size: 0.67rem; line-height: 1.55; margin-top: 6px;
    padding: 7px 11px; border-radius: 5px; background: rgba(0,0,0,0.03);
    color: var(--text-mid); white-space: pre-wrap; word-break: break-word;
    max-height: 140px; overflow-y: auto; border: 1px solid rgba(0,0,0,0.07); }
.svc-out-long::-webkit-scrollbar { width: 4px; }
.svc-out-long::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 2px; }

/* ── Performance pills ── */
.svc-perf { padding: 10px 16px 11px; border-bottom: 1px solid rgba(0,0,0,0.06); }
.svc-perf-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.sperf-pill { padding: 6px 10px; border-radius: 7px; background: #F8FAFF;
    border: 1px solid rgba(0,0,0,0.08); min-width: 78px; transition: border-color 150ms ease; }
.sperf-pill:hover { border-color: rgba(0,0,0,0.15); }
.sperf-key  { font-size: 0.55rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; color: var(--text-lo); margin-bottom: 2px; }
.sperf-val  { font-size: 0.82rem; font-weight: 700; color: var(--text-hi); }
.sperf-crit { color: #DC2626; }
.sperf-warn { color: #D97706; }
.sperf-bar-track { height: 3px; border-radius: 2px; background: rgba(0,0,0,0.08); margin-top: 5px; overflow: hidden; }
.sperf-bar-fill  { height: 100%; border-radius: 2px; transition: width 0.3s ease; }
.sperf-bar-ok   { background: #16a34a; }
.sperf-bar-warn { background: #d97706; }
.sperf-bar-crit { background: #dc2626; }
.sperf-thresholds { display: flex; gap: 8px; margin-top: 3px; }
.sperf-t-warn { font-size: 0.53rem; color: #92400E; }
.sperf-t-crit { font-size: 0.53rem; color: #991B1B; }

/* ── Split layout ── */
.svc-body-split { display: grid; grid-template-columns: 1fr 250px; }
@media (max-width: 860px) { .svc-body-split { grid-template-columns: 1fr; } }

/* ── Info panel ── */
.svc-info-panel { padding: 10px 14px; border-right: 1px solid rgba(0,0,0,0.06); }
@media (max-width: 860px) { .svc-info-panel { border-right: none; border-bottom: 1px solid rgba(0,0,0,0.06); } }
.svc-info-row { display: flex; justify-content: space-between; align-items: baseline;
    gap: 8px; padding: 3px 0; border-bottom: 1px solid rgba(0,0,0,0.04); font-size: 0.67rem; }
.svc-info-row:last-child { border-bottom: none; }
.svc-info-key { color: var(--text-lo); white-space: nowrap; flex-shrink: 0; }
.svc-info-val { color: var(--text-body); text-align: right; word-break: break-all; }
.svc-info-tag { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 0.58rem;
    font-weight: 600; background: rgba(8,145,178,0.08); color: var(--amber);
    border: 1px solid rgba(8,145,178,0.22); margin: 1px 2px 1px 0; }
.svc-info-enabled  { color: #15803D; font-weight: 600; }
.svc-info-disabled { color: #DC2626; font-weight: 600; }
.svc-info-mono { font-family: monospace; font-size: 0.61rem; word-break: break-all; }

/* ── Commands panel ── */
.svc-cmd-panel { padding: 10px 14px; }
.svc-cmd-group { margin-bottom: 10px; }
.svc-cmd-group:last-child { margin-bottom: 0; }
.svc-cmd-group-title { font-size: 0.55rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.09em; color: var(--text-lo); margin-bottom: 5px; }
.svc-cmd-list { display: flex; flex-direction: column; gap: 3px; }
.svc-cmd-link { display: flex; align-items: center; gap: 6px; padding: 5px 9px;
    border-radius: 6px; font-size: 0.64rem; font-weight: 500; text-decoration: none;
    color: var(--text-mid); background: rgba(0,0,0,0.02);
    border: 1px solid rgba(0,0,0,0.08); transition: background .15s, border-color .15s; touch-action: manipulation; }
.svc-cmd-link:hover { background: rgba(0,0,0,0.05); border-color: rgba(0,0,0,0.16); color: var(--text-hi); }
.svc-cmd-link svg  { flex-shrink: 0; opacity: 0.5; }
.svc-cmd-link.cmd-ack     { color:#1D4ED8; border-color:rgba(37,99,235,0.25); background:rgba(37,99,235,0.05); }
.svc-cmd-link.cmd-ack:hover { background:rgba(37,99,235,0.10); }
.svc-cmd-link.cmd-dt      { color:#047857; border-color:rgba(16,185,129,0.25); background:rgba(16,185,129,0.05); }
.svc-cmd-link.cmd-dt:hover { background:rgba(16,185,129,0.10); }
.svc-cmd-link.cmd-recheck { color:var(--amber); border-color:rgba(8,145,178,0.25); background:rgba(8,145,178,0.05); }
.svc-cmd-link.cmd-recheck:hover { background:rgba(8,145,178,0.10); }
.svc-cmd-link.cmd-danger  { color:#DC2626; border-color:rgba(239,68,68,0.25); background:rgba(239,68,68,0.04); }
.svc-cmd-link.cmd-danger:hover { background:rgba(239,68,68,0.09); }

/* ── Comments / Downtime ── */
.svc-cdt-section { padding: 10px 14px 4px; }
.svc-cdt-item { padding: 7px 10px; margin-bottom: 6px; border-radius: 7px;
    background: #F8FAFF; border: 1px solid rgba(0,0,0,0.07); }
.svc-cdt-header { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; flex-wrap: wrap; }
.svc-cdt-author { font-size: 0.63rem; font-weight: 600; color: var(--text-body); }
.svc-cdt-time   { font-size: 0.59rem; color: var(--text-lo); }
.svc-cdt-body   { font-size: 0.66rem; color: var(--text-mid); line-height: 1.5; }
</style>
</head>
<body>

<!-- ── Page header ── -->
<div class="page-hd">
	<div class="phd-left">
		<div class="phd-page">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
			<a href="hosts.php" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.45">Hosts</a>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
			<a href="host.php?host=<?php echo urlencode($hn_raw); ?>" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.65" target="main"><?php echo $hn_h; ?></a>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
			<span class="phd-page-title"><?php echo $sn_h; ?></span>
		</div>
		<?php if ($sblk): ?>
		<div class="phd-count">
			<?php echo h($st['text']); ?> state
			<?php if ($lsc): ?> &middot; for <?php echo $dur_str; ?><?php endif; ?>
			<?php if ($last_chk): ?> &middot; checked <?php echo $last_chk_str; ?><?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<div class="phd-right">
		<a href="host.php?host=<?php echo urlencode($hn_raw); ?>" class="phd-back-btn" target="main">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
			<span class="sbth-label">Host</span>
			<span class="sbth-name"><?php echo $hn_h; ?></span>
		</a>
	</div>
</div>

<?php if (!$sblk): ?>
<div class="data-card"><div class="cell-state is-error">
	Service <strong><?php echo $sn_h; ?></strong> on host <strong><?php echo $hn_h; ?></strong> not found in status file.
	<br><a href="host.php?host=<?php echo urlencode($hn_raw); ?>" style="color:var(--amber);font-size:0.72rem;margin-top:8px;display:inline-block">← Back to <?php echo $hn_h; ?></a>
</div></div>
<?php else: ?>

<!-- ── Host context bar ── -->
<div class="data-card" style="padding:0;margin-bottom:0;border-radius:0;border-left:none;border-right:none;border-top:none">
<div class="svc-host-ctx">
	<span class="svc-host-ctx-label">Host</span>
	<?php echo state_badge($hst); ?>
	<span class="svc-host-ctx-name"><a href="host.php?host=<?php echo urlencode($hn_raw); ?>" target="main"><?php echo $hn_h; ?></a></span>
	<?php if ($hblk && ($hblk['plugin_output'] ?? '') !== ''): ?>
	<span class="svc-host-ctx-out"><?php echo h($hblk['plugin_output']); ?></span>
	<?php endif; ?>
</div>

<!-- ── Hero ── -->
<div class="svc-det-hero" style="--hac:<?php echo $hc['hac']; ?>;--hbg:<?php echo $hc['hbg']; ?>">
<div class="svc-det-top">

	<!-- Col 1: State -->
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
	<div class="svc-det-id">
		<div class="svc-det-name"><?php echo $sn_h; ?></div>
		<div class="svc-det-host">
			<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px;opacity:.7"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
			<a href="host.php?host=<?php echo urlencode($hn_raw); ?>" target="main"><?php echo $hn_h; ?></a>
		</div>
		<div class="svc-det-tags">
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
			<?php if ($state_type === 1 && $cur_state !== 0): ?>
			<span class="host-hero-tag" style="background:rgba(239,68,68,0.10);color:#f87171;border:1px solid rgba(239,68,68,0.25)">HARD</span>
			<?php elseif ($state_type === 0 && $cur_state !== 0): ?>
			<span class="host-hero-tag" style="background:rgba(251,191,36,0.10);color:#fbbf24;border:1px solid rgba(251,191,36,0.25)">SOFT</span>
			<?php endif; ?>
			<?php foreach ($svc_groups as $gn): ?>
			<span class="host-hero-tag host-hero-tag-grp"><?php echo h($gn); ?></span>
			<?php endforeach; ?>
		</div>

		<!-- Quick action buttons -->
		<div class="svc-actions">
			<a href="<?php echo $url_recheck; ?>" target="main" class="svc-act-btn s-recheck">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
				Reschedule Check
			</a>
			<?php if (!$ack && $cur_state !== 0): ?>
			<a href="<?php echo $url_ack; ?>" target="main" class="svc-act-btn s-ack">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				Acknowledge
			</a>
			<?php elseif ($ack): ?>
			<a href="<?php echo $url_unack; ?>" target="main" class="svc-act-btn s-danger">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
				Remove Ack
			</a>
			<?php endif; ?>
			<a href="<?php echo $url_dt; ?>" target="main" class="svc-act-btn s-dt">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
				Schedule Downtime
			</a>
			<a href="<?php echo $url_cmt; ?>" target="main" class="svc-act-btn s-cmt">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
				Add Comment
			</a>
			<?php if ($action_url): ?>
			<a href="<?php echo h($action_url); ?>" target="_blank" class="svc-act-btn">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
				View Graph
			</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Col 3: Quick meta -->
	<div class="svc-det-meta">
		<div class="svc-det-meta-row">Last check <span class="svc-det-meta-val"><?php echo $last_chk_str; ?></span></div>
		<div class="svc-det-meta-row">Next check <span class="svc-det-meta-val"><?php echo $next_str; ?></span></div>
		<?php if ($max_att > 0): ?>
		<div class="svc-det-meta-row">Attempt <span class="svc-det-meta-val"><?php echo $cur_att; ?>/<?php echo $max_att; ?></span></div>
		<?php endif; ?>
		<div class="svc-det-meta-row">Latency <span class="svc-det-meta-val"><?php echo number_format($latency,3); ?>s</span></div>
	</div>

</div><!-- /.svc-det-top -->
</div><!-- /.svc-det-hero -->
</div><!-- /.data-card (hero) -->

<!-- ── Metric cards ── -->
<div class="data-card" style="padding:0;margin-bottom:0;border-radius:0;border-left:none;border-right:none;border-top:none">
<div class="svc-metrics">
	<?php
	$st_cls = ['svc-metric-ok','svc-metric-warn','svc-metric-crit','svc-metric-unkn'];
	$val_cls = $st_cls[$cur_state] ?? 'svc-metric-unkn';
	?>
	<div class="svc-metric">
		<div class="svc-metric-key">State</div>
		<div class="svc-metric-val <?php echo $val_cls; ?>"><?php echo h($st['text']); ?></div>
		<div class="svc-metric-sub"><?php echo $state_type===1?'Hard':'Soft'; ?></div>
	</div>
	<div class="svc-metric">
		<div class="svc-metric-key">Duration</div>
		<div class="svc-metric-val"><?php echo $dur_str; ?></div>
		<?php if ($lsc): ?><div class="svc-metric-sub">since <?php echo date('Y-m-d', $lsc); ?></div><?php endif; ?>
	</div>
	<div class="svc-metric">
		<div class="svc-metric-key">Last Check</div>
		<div class="svc-metric-val"><?php echo $last_chk_str; ?></div>
		<div class="svc-metric-sub"><?php echo $last_chk_abs; ?></div>
	</div>
	<div class="svc-metric">
		<div class="svc-metric-key">Next Check</div>
		<div class="svc-metric-val"><?php echo $next_str; ?></div>
		<div class="svc-metric-sub"><?php echo $next_abs; ?></div>
	</div>
	<div class="svc-metric">
		<div class="svc-metric-key">Attempt</div>
		<div class="svc-metric-val"><?php echo $cur_att; ?><?php if ($max_att > 0): ?><span style="font-size:0.65rem;opacity:.5"> / <?php echo $max_att; ?></span><?php endif; ?></div>
		<div class="svc-metric-sub"><?php echo $chk_type===0?'Active':'Passive'; ?> check</div>
	</div>
	<div class="svc-metric">
		<div class="svc-metric-key">Exec / Latency</div>
		<div class="svc-metric-val"><?php echo number_format($exec_time,3); ?>s</div>
		<div class="svc-metric-sub">latency <?php echo number_format($latency,3); ?>s</div>
	</div>
</div>
</div><!-- /.data-card (metrics) -->

<!-- ── Plugin Output ── -->
<?php if ($plugin_out !== '' || $long_out !== ''): ?>
<div class="data-card" style="padding:0;margin-bottom:0;border-radius:0;border-left:none;border-right:none;border-top:none">
<div class="svc-output">
	<div class="host-section-hd">Plugin Output</div>
	<?php
	$out_cls_map = ['svc-out-ok','svc-out-warn','svc-out-crit','svc-out-unkn'];
	$out_cls = $out_cls_map[$cur_state] ?? 'svc-out-unkn';
	if ($plugin_out !== ''): ?>
	<div class="svc-out-text <?php echo $out_cls; ?>"><?php echo h($plugin_out); ?></div>
	<?php endif; ?>
	<?php if ($long_out !== ''): ?>
	<div class="svc-out-long"><?php echo h($long_out); ?></div>
	<?php endif; ?>
</div>
</div>
<?php endif; ?>

<!-- ── Performance data ── -->
<?php if (!empty($perf_metrics)): ?>
<div class="data-card" style="padding:0;margin-bottom:0;border-radius:0;border-left:none;border-right:none;border-top:none">
<div class="svc-perf">
	<div class="host-section-hd">Performance Data</div>
	<div class="svc-perf-pills">
	<?php foreach ($perf_metrics as $p):
		$bpct  = svc_perf_bar_pct($p['num'], $p['warn'], $p['crit'], $p['max']);
		$vcls  = '';
		if ($p['crit'] !== '' && is_numeric($p['crit']) && $p['num'] >= (float)$p['crit']) $vcls = 'sperf-crit';
		elseif ($p['warn'] !== '' && is_numeric($p['warn']) && $p['num'] >= (float)$p['warn']) $vcls = 'sperf-warn';
		$bar_cls = $vcls === 'sperf-crit' ? 'sperf-bar-crit' : ($vcls === 'sperf-warn' ? 'sperf-bar-warn' : 'sperf-bar-ok');
	?>
	<div class="sperf-pill">
		<div class="sperf-key"><?php echo h($p['label']); ?></div>
		<div class="sperf-val <?php echo $vcls; ?>"><?php echo h($p['val']); ?></div>
		<?php if ($bpct >= 0): ?>
		<div class="sperf-bar-track">
			<div class="sperf-bar-fill <?php echo $bar_cls; ?>" style="width:<?php echo $bpct; ?>%"></div>
		</div>
		<?php endif; ?>
		<?php if ($p['warn'] !== '' || $p['crit'] !== ''): ?>
		<div class="sperf-thresholds">
			<?php if ($p['warn'] !== ''): ?><span class="sperf-t-warn">W:<?php echo h($p['warn'].$p['unit']); ?></span><?php endif; ?>
			<?php if ($p['crit'] !== ''): ?><span class="sperf-t-crit">C:<?php echo h($p['crit'].$p['unit']); ?></span><?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
	</div>
</div>
</div>
<?php endif; ?>

<!-- ── Service Info + Commands split ── -->
<div class="data-card" style="padding:0">
<div class="svc-body-split">

	<!-- Service State Information -->
	<div class="svc-info-panel">
		<div class="host-section-hd">Service State Information</div>

		<div class="svc-info-row">
			<span class="svc-info-key">Current State</span>
			<span class="svc-info-val"><?php echo state_badge($st); ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">State Type</span>
			<span class="svc-info-val"><?php echo $state_type===1?'HARD':'SOFT'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">State Duration</span>
			<span class="svc-info-val"><?php echo $dur_str; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Last State Change</span>
			<span class="svc-info-val"><?php echo $lsc ? date('Y-m-d H:i:s', $lsc) : '—'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Last Check Time</span>
			<span class="svc-info-val"><?php echo $last_chk_abs; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Next Scheduled Check</span>
			<span class="svc-info-val"><?php echo $next_str; ?> <span style="opacity:.5;font-size:0.60rem">(<?php echo $next_abs; ?>)</span></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Check Attempt</span>
			<span class="svc-info-val"><?php echo $cur_att; ?> / <?php echo $max_att ?: '?'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Check Type</span>
			<span class="svc-info-val"><?php echo $chk_type===0?'Active':'Passive'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Check Latency</span>
			<span class="svc-info-val"><?php echo number_format($latency,3); ?> seconds</span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Check Execution Time</span>
			<span class="svc-info-val"><?php echo number_format($exec_time,3); ?> seconds</span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Acknowledged</span>
			<span class="svc-info-val <?php echo $ack?'svc-info-enabled':''; ?>"><?php echo $ack?'Yes':'No'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Scheduled Downtime</span>
			<span class="svc-info-val <?php echo $in_dt?'svc-info-enabled':''; ?>"><?php echo $in_dt?'Yes':'No'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Flap Detection</span>
			<span class="svc-info-val <?php echo $flapping?'svc-info-warn':''; ?>">
				<?php echo $flap_en?'Enabled':'Disabled'; ?>
				<?php if ($flapping): ?> <span style="color:#c084fc">(Flapping <?php echo number_format($pct_flap,1); ?>%)</span><?php endif; ?>
			</span>
		</div>

		<div class="host-section-hd" style="margin-top:8px">Service Configuration</div>

		<div class="svc-info-row">
			<span class="svc-info-key">Check Command</span>
			<span class="svc-info-val svc-info-mono"><?php echo h($sblk['check_command'] ?? $scfg['check_command'] ?? '—'); ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Check Period</span>
			<span class="svc-info-val"><?php echo h($sblk['check_period'] ?? $scfg['check_period'] ?? '—'); ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Notification Period</span>
			<span class="svc-info-val"><?php echo h($sblk['notification_period'] ?? $scfg['notification_period'] ?? '—'); ?></span>
		</div>
		<?php
		$contacts_raw = $scfg['contacts'] ?? $scfg['contact_groups'] ?? '';
		if ($contacts_raw !== ''):
			$contacts = array_filter(array_map('trim', explode(',', $contacts_raw)));
		?>
		<div class="svc-info-row">
			<span class="svc-info-key">Contacts / Groups</span>
			<span class="svc-info-val">
				<?php foreach ($contacts as $c): ?>
				<span class="svc-info-tag"><?php echo h($c); ?></span>
				<?php endforeach; ?>
			</span>
		</div>
		<?php endif; ?>
		<?php if (!empty($svc_groups)): ?>
		<div class="svc-info-row">
			<span class="svc-info-key">Service Groups</span>
			<span class="svc-info-val">
				<?php foreach ($svc_groups as $g): ?>
				<span class="svc-info-tag"><?php echo h($g); ?></span>
				<?php endforeach; ?>
			</span>
		</div>
		<?php endif; ?>

		<div class="host-section-hd" style="margin-top:8px">Feature Toggles</div>

		<div class="svc-info-row">
			<span class="svc-info-key">Active Checks</span>
			<span class="svc-info-val <?php echo $act_en?'svc-info-enabled':'svc-info-disabled'; ?>"><?php echo $act_en?'Enabled':'Disabled'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Passive Checks</span>
			<span class="svc-info-val <?php echo $pas_en?'svc-info-enabled':'svc-info-disabled'; ?>"><?php echo $pas_en?'Enabled':'Disabled'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Notifications</span>
			<span class="svc-info-val <?php echo $notif_en?'svc-info-enabled':'svc-info-disabled'; ?>"><?php echo $notif_en?'Enabled':'Disabled'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Event Handler</span>
			<span class="svc-info-val <?php echo $evth_en?'svc-info-enabled':'svc-info-disabled'; ?>"><?php echo $evth_en?'Enabled':'Disabled'; ?></span>
		</div>
		<div class="svc-info-row">
			<span class="svc-info-key">Performance Data</span>
			<span class="svc-info-val <?php echo $perf_en?'svc-info-enabled':'svc-info-disabled'; ?>"><?php echo $perf_en?'Enabled':'Disabled'; ?></span>
		</div>

		<?php if (($scfg['notes'] ?? '') !== ''): ?>
		<div class="host-section-hd" style="margin-top:8px">Notes</div>
		<div style="font-size:0.70rem;color:var(--text-lo);line-height:1.5;padding:4px 0"><?php echo h($scfg['notes']); ?></div>
		<?php endif; ?>
	</div><!-- /.svc-info-panel -->

	<!-- Commands panel -->
	<div class="svc-cmd-panel">
		<div class="svc-cmd-group">
			<div class="svc-cmd-group-title">Service Commands</div>
			<div class="svc-cmd-list">
				<a href="<?php echo $url_recheck; ?>" target="main" class="svc-cmd-link cmd-recheck">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
					Reschedule Next Check
				</a>
				<?php if (!$ack && $cur_state !== 0): ?>
				<a href="<?php echo $url_ack; ?>" target="main" class="svc-cmd-link cmd-ack">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
					Acknowledge Problem
				</a>
				<?php endif; ?>
				<?php if ($ack): ?>
				<a href="<?php echo $url_unack; ?>" target="main" class="svc-cmd-link cmd-danger">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					Remove Acknowledgement
				</a>
				<?php endif; ?>
				<a href="<?php echo $url_dt; ?>" target="main" class="svc-cmd-link cmd-dt">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					Schedule Downtime
				</a>
				<a href="<?php echo $url_cmt; ?>" target="main" class="svc-cmd-link">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
					Add Comment
				</a>
			</div>
		</div>

		<div class="svc-cmd-group">
			<div class="svc-cmd-group-title">Check Controls</div>
			<div class="svc-cmd-list">
				<?php if ($act_en): ?>
				<a href="<?php echo $url_chk_dis; ?>" target="main" class="svc-cmd-link">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					Disable Active Checks
				</a>
				<?php else: ?>
				<a href="<?php echo $url_chk_en; ?>" target="main" class="svc-cmd-link cmd-ack">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
					Enable Active Checks
				</a>
				<?php endif; ?>
				<?php if ($pas_en): ?>
				<a href="<?php echo $url_pchk_dis; ?>" target="main" class="svc-cmd-link">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					Disable Passive Checks
				</a>
				<?php else: ?>
				<a href="<?php echo $url_pchk_en; ?>" target="main" class="svc-cmd-link cmd-ack">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
					Enable Passive Checks
				</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="svc-cmd-group">
			<div class="svc-cmd-group-title">Notification Controls</div>
			<div class="svc-cmd-list">
				<?php if ($notif_en): ?>
				<a href="<?php echo $url_notif_dis; ?>" target="main" class="svc-cmd-link">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
					Disable Notifications
				</a>
				<?php else: ?>
				<a href="<?php echo $url_notif_en; ?>" target="main" class="svc-cmd-link cmd-ack">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
					Enable Notifications
				</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="svc-cmd-group">
			<div class="svc-cmd-group-title">Advanced</div>
			<div class="svc-cmd-list">
				<?php if ($evth_en): ?>
				<a href="<?php echo $url_evth_dis; ?>" target="main" class="svc-cmd-link">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
					Disable Event Handler
				</a>
				<?php else: ?>
				<a href="<?php echo $url_evth_en; ?>" target="main" class="svc-cmd-link cmd-ack">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
					Enable Event Handler
				</a>
				<?php endif; ?>
				<?php if ($flap_en): ?>
				<a href="<?php echo $url_flap_dis; ?>" target="main" class="svc-cmd-link">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
					Disable Flap Detection
				</a>
				<?php else: ?>
				<a href="<?php echo $url_flap_en; ?>" target="main" class="svc-cmd-link cmd-ack">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
					Enable Flap Detection
				</a>
				<?php endif; ?>
			</div>
		</div>
	</div><!-- /.svc-cmd-panel -->

</div><!-- /.svc-body-split -->
</div><!-- /.data-card (split) -->

<!-- ── Comments ── -->
<?php if (!empty($svc_comments)): ?>
<div class="data-card" style="padding:0">
<div class="svc-cdt-section">
	<div class="host-section-hd">Comments (<?php echo count($svc_comments); ?>)</div>
	<?php foreach ($svc_comments as $c):
		$et   = (int)($c['entry_type'] ?? 1);
		$ecls = $etype_cls[$et] ?? 'etype-user-cmt';
		$elbl = $etype_labels[$et] ?? 'Comment';
	?>
	<div class="svc-cdt-item">
		<div class="svc-cdt-header">
			<span class="etype <?php echo $ecls; ?>"><?php echo h($elbl); ?></span>
			<span class="svc-cdt-author"><?php echo h($c['author'] ?? ''); ?></span>
			<span class="svc-cdt-time"><?php echo $c['entry_time'] ? date('Y-m-d H:i', (int)$c['entry_time']) : ''; ?></span>
		</div>
		<div class="svc-cdt-body"><?php echo h($c['comment_data'] ?? ''); ?></div>
	</div>
	<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<!-- ── Active Downtimes ── -->
<?php if (!empty($svc_downtimes)): ?>
<div class="data-card" style="padding:0">
<div class="svc-cdt-section">
	<div class="host-section-hd">Active Downtimes (<?php echo count($svc_downtimes); ?>)</div>
	<?php foreach ($svc_downtimes as $d): ?>
	<div class="svc-cdt-item">
		<div class="svc-cdt-header">
			<span class="etype etype-downtime-cmt">Downtime</span>
			<span class="svc-cdt-author"><?php echo h($d['author'] ?? ''); ?></span>
			<span class="svc-cdt-time">
				<?php echo $d['start_time'] ? date('Y-m-d H:i', (int)$d['start_time']) : ''; ?>
				<?php if ($d['end_time']): ?> → <?php echo date('H:i', (int)$d['end_time']); ?><?php endif; ?>
			</span>
		</div>
		<div class="svc-cdt-body"><?php echo h($d['comment'] ?? $d['comment_data'] ?? ''); ?></div>
	</div>
	<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php endif; ?>
</body>
</html>
