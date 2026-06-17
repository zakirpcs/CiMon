<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file']      ?? '/usr/local/nagios/var/status.dat';
$now = time();

/* ── Parameters ── */
$hn_raw = trim($_GET['host']    ?? '');
$sn_raw = trim($_GET['service'] ?? '');
$is_svc = $sn_raw !== '';
$cmd_typ = $is_svc ? 7 : 96;

if ($hn_raw === '') { header('Location: hosts.php'); exit; }

/* ── Parse status ── */
$sdata = nagios_parse_status($sf);

$hblk = null;
foreach ($sdata['hosts'] as $h) {
    if (($h['host_name'] ?? '') === $hn_raw) { $hblk = $h; break; }
}
$sblk = null;
if ($is_svc) {
    foreach ($sdata['services'] as $s) {
        if (($s['host_name'] ?? '') === $hn_raw && ($s['service_description'] ?? '') === $sn_raw) {
            $sblk = $s; break;
        }
    }
}

$target = $is_svc ? $sblk : $hblk;
$st = $is_svc
    ? ($sblk ? svc_state_info($sblk) : ['text'=>'UNKNOWN','cls'=>'badge-unkn','row'=>'','ord'=>3])
    : ($hblk ? host_state_info($hblk) : ['text'=>'UNKNOWN','cls'=>'badge-pending','row'=>'','ord'=>2]);

/* ── State accent tokens ── */
$sac = [
    'UP'          => ['c'=>'#16a34a','bg'=>'rgba(22,163,74,0.06)'],
    'OK'          => ['c'=>'#16a34a','bg'=>'rgba(22,163,74,0.06)'],
    'DOWN'        => ['c'=>'#dc2626','bg'=>'rgba(239,68,68,0.07)'],
    'CRITICAL'    => ['c'=>'#dc2626','bg'=>'rgba(239,68,68,0.07)'],
    'WARNING'     => ['c'=>'#d97706','bg'=>'rgba(217,119,6,0.07)'],
    'UNKNOWN'     => ['c'=>'#9333ea','bg'=>'rgba(147,51,234,0.06)'],
    'UNREACHABLE' => ['c'=>'#9333ea','bg'=>'rgba(147,51,234,0.06)'],
    'PENDING'     => ['c'=>'#64748b','bg'=>'rgba(100,116,139,0.04)'],
];
$sc = $sac[$st['text']] ?? $sac['UNKNOWN'];

$hn_h = h($hn_raw);
$sn_h = h($sn_raw);

$last_chk  = (int)(($target ?? [])['last_check']     ?? 0);
$next_chk  = (int)(($target ?? [])['next_check']     ?? 0);
$plugin_out = h(($target ?? [])['plugin_output']      ?? '');
$chk_period = h(($target ?? [])['check_period']       ?? '');
$act_en     = (($target ?? [])['active_checks_enabled'] ?? '1') === '1';

$last_str = $last_chk ? fmt_ago($last_chk) . ' (' . date('H:i:s', $last_chk) . ')' : '—';
$next_str = $next_chk ? fmt_ago($next_chk) . ' (' . date('H:i:s', $next_chk) . ')' : '—';

/* ── Back URL ── */
$back_url = $is_svc
    ? h('service.php?host='.urlencode($hn_raw).'&service='.urlencode($sn_raw))
    : h('host.php?host='.urlencode($hn_raw));

/* ── Breadcrumb title ── */
$page_title = $is_svc
    ? $sn_h . ' &rsaquo; ' . $hn_h
    : $hn_h;

/* ── cmd.cgi endpoint ── */
$cmd_action = h($cgi . '/cmd.cgi');

/* ── Pre-filled datetime (now + 1 min, datetime-local format) ── */
$default_dt = date('Y-m-d\TH:i', $now + 60);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Reschedule Check &mdash; <?php echo $is_svc ? $sn_h.' on ' : ''; ?><?php echo $hn_h; ?></title>
<link rel="stylesheet" href="stylesheets/common.css">
<link rel="stylesheet" href="stylesheets/pages.css?v=4">
<style>
/* ── Back button — light mode ── */
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

/* ── Page shell ── */
.rs-wrap {
    max-width: 620px;
    margin: 0 auto;
    padding: 20px 16px 48px;
}

/* ── Context card ── */
.rs-ctx {
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.08);
    border-left: 4px solid var(--sac, #16a34a);
    background: var(--sabg, rgba(22,163,74,0.04));
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.rs-ctx-body {
    padding: 16px 18px 14px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.rs-ctx-icon {
    width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
    background: rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.08);
    display: flex; align-items: center; justify-content: center;
}
.rs-ctx-icon svg { width: 17px; height: 17px; stroke: var(--sac, #16a34a); }
.rs-ctx-info { flex: 1; min-width: 0; }
.rs-ctx-name {
    font-size: 1.0rem; font-weight: 700; color: var(--text-hi);
    line-height: 1.25; margin-bottom: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rs-ctx-sub {
    font-size: 0.66rem; color: var(--text-lo); margin-bottom: 6px;
}
.rs-ctx-sub a { color: var(--amber); text-decoration: none; }
.rs-ctx-sub a:hover { text-decoration: underline; }
.rs-ctx-out {
    font-size: 0.67rem; color: var(--text-mid); font-family: 'Geist Mono', monospace;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    background: rgba(0,0,0,0.04); border-radius: 5px;
    padding: 5px 9px; margin-top: 6px;
}
.rs-ctx-meta {
    display: flex; gap: 20px; flex-wrap: wrap;
    padding: 10px 18px;
    border-top: 1px solid rgba(0,0,0,0.06);
    background: rgba(0,0,0,0.025);
}
.rs-ctx-meta-item { font-size: 0.62rem; }
.rs-ctx-meta-key  { color: var(--text-lo); display: block; margin-bottom: 1px; }
.rs-ctx-meta-val  { color: var(--text-mid); font-weight: 600; }
.rs-ctx-warn {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 18px; font-size: 0.65rem; color: #92400E;
    background: rgba(180,83,9,0.06); border-top: 1px solid rgba(180,83,9,0.14);
}
.rs-ctx-warn svg { width: 13px; height: 13px; flex-shrink: 0; stroke: #92400E; }

/* ── Form card ── */
.rs-card {
    background: #FFFFFF;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.rs-card-hd {
    padding: 16px 20px 14px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    display: flex; align-items: center; gap: 10px;
}
.rs-card-hd-icon {
    width: 32px; height: 32px; border-radius: 7px;
    background: rgba(180,83,9,0.08); border: 1px solid rgba(180,83,9,0.20);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.rs-card-hd-icon svg { width: 15px; height: 15px; stroke: #B45309; }
.rs-card-hd-text { flex: 1; }
.rs-card-title { font-size: 0.85rem; font-weight: 700; color: var(--text-hi); }
.rs-card-desc  { font-size: 0.62rem; color: var(--text-lo); margin-top: 1px; }

.rs-form { padding: 20px; display: flex; flex-direction: column; gap: 20px; }

/* ── Field groups ── */
.rs-field { display: flex; flex-direction: column; gap: 6px; }
.rs-label {
    font-size: 0.67rem; font-weight: 600; color: var(--text-mid);
    display: flex; align-items: center; gap: 6px;
}
.rs-label-req { color: #DC2626; font-size: 0.6rem; }

.rs-input-row { display: flex; gap: 8px; align-items: stretch; }
.rs-input {
    flex: 1; padding: 10px 13px;
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.12);
    border-radius: 8px; color: var(--text-hi);
    font-size: 0.78rem; font-family: 'Geist Mono', monospace;
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none; min-height: 44px;
    color-scheme: light;
}
.rs-input:focus {
    border-color: rgba(180,83,9,0.5);
    box-shadow: 0 0 0 3px rgba(180,83,9,0.10);
}
.rs-input:hover:not(:focus) { border-color: rgba(0,0,0,0.22); }

.rs-now-btn {
    padding: 0 14px; border-radius: 8px; min-height: 44px;
    background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.10);
    color: var(--text-mid); font-size: 0.62rem; font-weight: 600;
    cursor: pointer; white-space: nowrap; flex-shrink: 0;
    transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
    display: flex; align-items: center; gap: 5px;
}
.rs-now-btn:hover { background: rgba(180,83,9,0.08); color: #B45309; border-color: rgba(180,83,9,0.3); }
.rs-now-btn svg   { width: 12px; height: 12px; flex-shrink: 0; stroke: currentColor; }

.rs-helper {
    font-size: 0.60rem; color: var(--text-lo); line-height: 1.5;
    display: flex; align-items: flex-start; gap: 5px;
}
.rs-helper svg { width: 11px; height: 11px; flex-shrink: 0; stroke: var(--text-lo); margin-top: 1px; }

/* ── Force check toggle ── */
.rs-toggle-row {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 14px 16px;
    background: rgba(0,0,0,0.025);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 8px; cursor: pointer;
    transition: background 150ms ease, border-color 150ms ease;
}
.rs-toggle-row:hover { background: rgba(0,0,0,0.045); border-color: rgba(0,0,0,0.14); }
.rs-toggle-row input[type="checkbox"] { display: none; }

.rs-toggle-switch {
    width: 36px; height: 20px; border-radius: 10px; flex-shrink: 0;
    background: rgba(0,0,0,0.12); border: 1px solid rgba(0,0,0,0.14);
    position: relative; transition: background 200ms ease, border-color 200ms ease;
    margin-top: 1px;
}
.rs-toggle-switch::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 12px; height: 12px; border-radius: 50%;
    background: rgba(0,0,0,0.3);
    transition: transform 200ms ease, background 200ms ease;
}
input[type="checkbox"]:checked ~ .rs-toggle-info .rs-toggle-switch,
.rs-toggle-row.is-on .rs-toggle-switch {
    background: rgba(180,83,9,0.18);
    border-color: rgba(180,83,9,0.35);
}
input[type="checkbox"]:checked ~ .rs-toggle-info .rs-toggle-switch::after,
.rs-toggle-row.is-on .rs-toggle-switch::after {
    transform: translateX(16px);
    background: #B45309;
}
.rs-toggle-info { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.rs-toggle-info-top { display: flex; align-items: center; gap: 10px; }
.rs-toggle-label { font-size: 0.73rem; font-weight: 600; color: var(--text-hi); }
.rs-toggle-badge {
    font-size: 0.54rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.06em;
    background: rgba(180,83,9,0.10); color: #B45309;
    border: 1px solid rgba(180,83,9,0.25);
    opacity: 0; transition: opacity 150ms ease;
}
.rs-toggle-row.is-on .rs-toggle-badge { opacity: 1; }
.rs-toggle-desc { font-size: 0.62rem; color: var(--text-lo); line-height: 1.45; }

/* ── Divider ── */
.rs-divider { border: none; border-top: 1px solid rgba(0,0,0,0.07); margin: 0; }

/* ── Actions ── */
.rs-actions {
    padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    border-top: 1px solid rgba(0,0,0,0.06);
    background: rgba(0,0,0,0.025);
}
.rs-btn-cancel {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 8px; font-size: 0.70rem; font-weight: 600;
    text-decoration: none; color: var(--text-mid);
    background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.09);
    transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
    cursor: pointer; min-height: 40px;
}
.rs-btn-cancel:hover { background: rgba(0,0,0,0.08); color: var(--text-hi); border-color: rgba(0,0,0,0.18); }
.rs-btn-cancel svg { width: 13px; height: 13px; stroke: currentColor; }

.rs-btn-submit {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 8px; font-size: 0.72rem; font-weight: 700;
    border: none; cursor: pointer; min-height: 42px; min-width: 180px;
    justify-content: center;
    background: #D97706; color: #FFFFFF;
    box-shadow: 0 2px 12px rgba(180,83,9,0.20);
    transition: background 150ms ease, box-shadow 150ms ease, transform 80ms ease;
    touch-action: manipulation;
}
.rs-btn-submit:hover { background: #B45309; box-shadow: 0 4px 18px rgba(180,83,9,0.28); }
.rs-btn-submit:active { transform: scale(0.98); }
.rs-btn-submit:disabled {
    background: rgba(180,83,9,0.35); color: rgba(255,255,255,0.6);
    box-shadow: none; cursor: not-allowed; transform: none;
}
.rs-btn-submit svg { width: 15px; height: 15px; stroke: currentColor; flex-shrink: 0; }
.rs-btn-submit .btn-spinner {
    width: 15px; height: 15px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #FFFFFF;
    animation: spin 0.7s linear infinite; display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }
.rs-btn-submit.is-loading .btn-icon { display: none; }
.rs-btn-submit.is-loading .btn-spinner { display: block; }
.rs-btn-submit.is-loading .btn-label { opacity: 0.7; }

/* ── Success overlay ── */
.rs-success {
    display: none; flex-direction: column; align-items: center; justify-content: center;
    padding: 48px 24px; text-align: center;
}
.rs-success.is-shown { display: flex; }
.rs-success-ring {
    width: 60px; height: 60px; border-radius: 50%;
    background: rgba(22,163,74,0.08); border: 2px solid rgba(22,163,74,0.30);
    display: flex; align-items: center; justify-content: center; margin-bottom: 18px;
    animation: ring-pop 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes ring-pop { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.rs-success-ring svg { width: 28px; height: 28px; stroke: #15803D; stroke-width: 2.5; }
.rs-success-title { font-size: 1.05rem; font-weight: 800; color: var(--text-hi); margin-bottom: 6px; }
.rs-success-sub   { font-size: 0.72rem; color: var(--text-mid); line-height: 1.6; max-width: 340px; }
.rs-success-actions { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; justify-content: center; }
.rs-success-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 0.68rem; font-weight: 600;
    text-decoration: none; border: 1px solid rgba(0,0,0,0.10);
    color: var(--text-mid); background: rgba(0,0,0,0.04);
    transition: background 150ms ease;
}
.rs-success-btn:hover { background: rgba(0,0,0,0.08); }
.rs-success-btn.primary { background: rgba(22,163,74,0.08); color: #15803D; border-color: rgba(22,163,74,0.22); }
.rs-success-btn.primary:hover { background: rgba(22,163,74,0.14); }
.rs-success-btn svg { width: 13px; height: 13px; stroke: currentColor; }

/* ── Error banner ── */
.rs-error {
    display: none; align-items: flex-start; gap: 10px;
    padding: 12px 16px; margin: 0 20px 16px;
    background: rgba(220,38,38,0.06); border: 1px solid rgba(220,38,38,0.22);
    border-radius: 8px; font-size: 0.67rem; color: #DC2626; line-height: 1.5;
}
.rs-error.is-shown { display: flex; }
.rs-error svg { width: 14px; height: 14px; stroke: #DC2626; flex-shrink: 0; margin-top: 1px; }

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
}
</style>
</head>
<body>

<!-- ── Page header ── -->
<div class="page-hd">
    <div class="phd-left">
        <div class="phd-page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
            <?php if ($is_svc): ?>
            <a href="hosts.php" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.4">Hosts</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
            <a href="host.php?host=<?php echo urlencode($hn_raw); ?>" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.5" target="main"><?php echo $hn_h; ?></a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
            <a href="service.php?host=<?php echo urlencode($hn_raw); ?>&service=<?php echo urlencode($sn_raw); ?>" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.7" target="main"><?php echo $sn_h; ?></a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
            <?php else: ?>
            <a href="hosts.php" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.5">Hosts</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
            <a href="host.php?host=<?php echo urlencode($hn_raw); ?>" class="phd-page-title" style="text-decoration:none;color:inherit;opacity:.7" target="main"><?php echo $hn_h; ?></a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;color:var(--text-lo)"><polyline points="9 18 15 12 9 6"/></svg>
            <?php endif; ?>
            <span class="phd-page-title">Reschedule Check</span>
        </div>
        <div class="phd-count">Force an immediate or timed re-evaluation</div>
    </div>
    <div class="phd-right">
        <a href="<?php echo $back_url; ?>" class="phd-back-btn" target="main">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <span class="sbth-label"><?php echo $is_svc ? 'Service' : 'Host'; ?></span>
            <span class="sbth-name"><?php echo $is_svc ? $sn_h : $hn_h; ?></span>
        </a>
    </div>
</div>

<div class="rs-wrap">

<!-- ── Context card ── -->
<div class="rs-ctx" style="--sac:<?php echo $sc['c']; ?>;--sabg:<?php echo $sc['bg']; ?>">
    <div class="rs-ctx-body">
        <div class="rs-ctx-icon">
            <?php if ($is_svc): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
            <?php endif; ?>
        </div>
        <div class="rs-ctx-info">
            <div class="rs-ctx-name"><?php echo $is_svc ? $sn_h : $hn_h; ?></div>
            <div class="rs-ctx-sub">
                <?php if ($is_svc): ?>
                on <a href="host.php?host=<?php echo urlencode($hn_raw); ?>" target="main"><?php echo $hn_h; ?></a>
                &nbsp;&middot;&nbsp;
                <?php endif; ?>
                <?php echo state_badge($st); ?>
                <?php if ($chk_period): ?>&nbsp;&middot;&nbsp;<span>Period: <?php echo $chk_period; ?></span><?php endif; ?>
            </div>
            <?php if ($plugin_out): ?>
            <div class="rs-ctx-out"><?php echo $plugin_out; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="rs-ctx-meta">
        <div class="rs-ctx-meta-item">
            <span class="rs-ctx-meta-key">Last Check</span>
            <span class="rs-ctx-meta-val"><?php echo $last_str; ?></span>
        </div>
        <div class="rs-ctx-meta-item">
            <span class="rs-ctx-meta-key">Next Scheduled</span>
            <span class="rs-ctx-meta-val"><?php echo $next_str; ?></span>
        </div>
    </div>
    <?php if (!$act_en): ?>
    <div class="rs-ctx-warn">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Active checks are currently <strong>disabled</strong>. Enable "Force Check" below to run this check regardless.
    </div>
    <?php endif; ?>
</div>

<!-- ── Form card ── -->
<div class="rs-card">
    <div class="rs-card-hd">
        <div class="rs-card-hd-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="rs-card-hd-text">
            <div class="rs-card-title">Schedule New Check</div>
            <div class="rs-card-desc">Override the next check time for this <?php echo $is_svc ? 'service' : 'host'; ?></div>
        </div>
    </div>

    <!-- Error banner (shown on failure) -->
    <div class="rs-error" id="rs-error" role="alert" aria-live="polite">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="rs-error-text">Command failed. Please try again.</span>
    </div>

    <!-- Main form -->
    <div id="rs-form-area">
        <form id="rs-form" action="<?php echo $cmd_action; ?>" method="post" novalidate>
            <input type="hidden" name="cmd_typ" value="<?php echo $cmd_typ; ?>">
            <input type="hidden" name="cmd_mod" value="2">
            <input type="hidden" name="host"    value="<?php echo h($hn_raw); ?>">
            <?php if ($is_svc): ?>
            <input type="hidden" name="service" value="<?php echo h($sn_raw); ?>">
            <?php endif; ?>
            <!-- Nagios-format datetime, populated by JS before submit -->
            <input type="hidden" name="start_time" id="start_time_hidden">

            <div class="rs-form">

                <!-- Schedule time -->
                <div class="rs-field">
                    <label class="rs-label" for="rs-dt">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Check Time
                        <span class="rs-label-req" aria-label="required">*</span>
                    </label>
                    <div class="rs-input-row">
                        <input
                            type="datetime-local"
                            id="rs-dt"
                            class="rs-input"
                            value="<?php echo $default_dt; ?>"
                            required
                            aria-describedby="rs-dt-help"
                        >
                        <button type="button" class="rs-now-btn" id="rs-now-btn" title="Set to now">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
                            Now
                        </button>
                    </div>
                    <div class="rs-helper" id="rs-dt-help">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Nagios will run the check at or after this time. Defaults to 1 minute from now.
                    </div>
                </div>

                <!-- Force check toggle -->
                <div class="rs-field">
                    <label class="rs-label">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        Options
                    </label>
                    <label class="rs-toggle-row" id="rs-force-label">
                        <input type="checkbox" name="force_check" id="rs-force" value="on" <?php echo !$act_en ? 'checked' : ''; ?>>
                        <div class="rs-toggle-info">
                            <div class="rs-toggle-info-top">
                                <div class="rs-toggle-switch"></div>
                                <span class="rs-toggle-label">Force Check</span>
                                <span class="rs-toggle-badge">ON</span>
                            </div>
                            <div class="rs-toggle-desc">
                                Run the check even if active checks are disabled for this <?php echo $is_svc ? 'service' : 'host'; ?>.
                                Useful for immediate verification without enabling global checks.
                            </div>
                        </div>
                    </label>
                </div>

            </div><!-- /.rs-form -->

            <div class="rs-actions">
                <a href="<?php echo $back_url; ?>" class="rs-btn-cancel" target="main">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Cancel
                </a>
                <button type="submit" class="rs-btn-submit" id="rs-submit">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
                    <div class="btn-spinner"></div>
                    <span class="btn-label">Reschedule Check</span>
                </button>
            </div>

        </form>
    </div><!-- /#rs-form-area -->

    <!-- Success state -->
    <div class="rs-success" id="rs-success" aria-live="polite">
        <div class="rs-success-ring">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="rs-success-title">Check Rescheduled</div>
        <div class="rs-success-sub">
            Nagios has queued a new check for
            <strong><?php echo $is_svc ? $sn_h . ' on ' . $hn_h : $hn_h; ?></strong>.
            Results will appear after the check runs.
        </div>
        <div class="rs-success-actions">
            <a href="<?php echo $back_url; ?>" class="rs-success-btn primary" target="main">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to <?php echo $is_svc ? 'Service' : 'Host'; ?>
            </a>
            <a href="hosts.php" class="rs-success-btn" target="main">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                All Hosts
            </a>
        </div>
    </div>

</div><!-- /.rs-card -->

</div><!-- /.rs-wrap -->

<script>
(function () {
    /* ── Elements ── */
    const form      = document.getElementById('rs-form');
    const dtInput   = document.getElementById('rs-dt');
    const hiddenDt  = document.getElementById('start_time_hidden');
    const submitBtn = document.getElementById('rs-submit');
    const nowBtn    = document.getElementById('rs-now-btn');
    const forceChk  = document.getElementById('rs-force');
    const forceRow  = document.getElementById('rs-force-label');
    const errorBox  = document.getElementById('rs-error');
    const errorTxt  = document.getElementById('rs-error-text');
    const formArea  = document.getElementById('rs-form-area');
    const successEl = document.getElementById('rs-success');

    /* ── Convert datetime-local → MM/DD/YYYY HH:MM:SS ── */
    function toNagiosTime(val) {
        if (!val) return '';
        const d = new Date(val);
        if (isNaN(d)) return '';
        const p = n => String(n).padStart(2, '0');
        return `${p(d.getMonth()+1)}/${p(d.getDate())}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    }

    /* ── "Now" quick-fill ── */
    function setNow() {
        const n = new Date(Date.now() + 5000); // 5 sec from now
        const pad = v => String(v).padStart(2, '0');
        dtInput.value = `${n.getFullYear()}-${pad(n.getMonth()+1)}-${pad(n.getDate())}T${pad(n.getHours())}:${pad(n.getMinutes())}`;
    }
    nowBtn.addEventListener('click', setNow);

    /* ── Force-check toggle visual ── */
    function syncToggle() {
        forceRow.classList.toggle('is-on', forceChk.checked);
    }
    forceChk.addEventListener('change', syncToggle);
    syncToggle(); // init
    forceRow.addEventListener('click', function(e) {
        // let label handle it naturally; just sync visuals
        requestAnimationFrame(syncToggle);
    });

    /* ── Form submit: AJAX → cmd.cgi ── */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        /* Validate time */
        if (!dtInput.value) {
            dtInput.focus();
            return;
        }

        /* Populate hidden Nagios-format field */
        hiddenDt.value = toNagiosTime(dtInput.value);

        /* Loading state */
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
        errorBox.classList.remove('is-shown');

        try {
            const fd = new FormData(form);
            const resp = await fetch(form.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });

            const html = await resp.text();

            /* Nagios success strings */
            const ok = html.includes('successfully submitted') ||
                       html.includes('Your command request was successfully') ||
                       html.includes('was successfully submitted');

            if (ok) {
                /* Show success state */
                formArea.style.display = 'none';
                successEl.classList.add('is-shown');
            } else {
                /* Try to extract Nagios error text */
                const match = html.match(/<div[^>]*class="[^"]*error[^"]*"[^>]*>([\s\S]*?)<\/div>/i)
                           || html.match(/<p[^>]*>(Error[^<]+)<\/p>/i);
                errorTxt.textContent = match
                    ? match[1].replace(/<[^>]+>/g, '').trim()
                    : 'Command failed. Check Nagios logs for details.';
                errorBox.classList.add('is-shown');
                submitBtn.disabled = false;
                submitBtn.classList.remove('is-loading');
            }
        } catch (err) {
            errorTxt.textContent = 'Network error: ' + err.message;
            errorBox.classList.add('is-shown');
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-loading');
        }
    });
})();
</script>
</body>
</html>
