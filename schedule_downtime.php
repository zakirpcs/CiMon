<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';
$now = time();

/* ── Parameters ── */
$hn_raw = trim($_GET['host']    ?? '');
$sn_raw = trim($_GET['service'] ?? '');
$is_svc = $sn_raw !== '';
$cmd_typ = $is_svc ? 56 : 55;

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

$last_chk   = (int)(($target ?? [])['last_check']     ?? 0);
$plugin_out = h(($target ?? [])['plugin_output']       ?? '');
$in_dt      = ((int)(($target ?? [])['scheduled_downtime_depth'] ?? 0)) > 0;

$last_str = $last_chk ? fmt_ago($last_chk) . ' (' . date('H:i:s', $last_chk) . ')' : '—';

/* ── Logged-in author ── */
$author_default = h($_SERVER['PHP_AUTH_USER'] ?? 'nagiosadmin');

/* ── Back URL ── */
$back_url = $is_svc
    ? h('service.php?host='.urlencode($hn_raw).'&service='.urlencode($sn_raw))
    : h('host.php?host='.urlencode($hn_raw));

/* ── cmd.cgi endpoint ── */
$cmd_action = h($cgi . '/cmd.cgi');

/* ── Pre-filled datetimes ── */
$default_start = date('Y-m-d\TH:i', $now);
$default_end   = date('Y-m-d\TH:i', $now + 7200); // +2 hours default
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Schedule Downtime &mdash; <?php echo $is_svc ? $sn_h.' on ' : ''; ?><?php echo $hn_h; ?></title>
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
.sd-wrap {
    max-width: 640px;
    margin: 0 auto;
    padding: 20px 16px 48px;
}

/* ── Context card ── */
.sd-ctx {
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.08);
    border-left: 4px solid var(--sac, #16a34a);
    background: var(--sabg, rgba(22,163,74,0.04));
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.sd-ctx-body {
    padding: 16px 18px 14px;
    display: flex; align-items: flex-start; gap: 14px;
}
.sd-ctx-icon {
    width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
    background: rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.08);
    display: flex; align-items: center; justify-content: center;
}
.sd-ctx-icon svg { width: 17px; height: 17px; stroke: var(--sac, #16a34a); }
.sd-ctx-info { flex: 1; min-width: 0; }
.sd-ctx-name {
    font-size: 1.0rem; font-weight: 700; color: var(--text-hi);
    line-height: 1.25; margin-bottom: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sd-ctx-sub  { font-size: 0.66rem; color: var(--text-lo); margin-bottom: 6px; }
.sd-ctx-sub a { color: var(--amber); text-decoration: none; }
.sd-ctx-sub a:hover { text-decoration: underline; }
.sd-ctx-out {
    font-size: 0.67rem; color: var(--text-mid); font-family: 'Geist Mono', monospace;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    background: rgba(0,0,0,0.04); border-radius: 5px;
    padding: 5px 9px; margin-top: 6px;
}
.sd-ctx-meta {
    display: flex; gap: 20px; flex-wrap: wrap;
    padding: 10px 18px;
    border-top: 1px solid rgba(0,0,0,0.06);
    background: rgba(0,0,0,0.025);
}
.sd-ctx-meta-item { font-size: 0.62rem; }
.sd-ctx-meta-key  { color: var(--text-lo); display: block; margin-bottom: 1px; }
.sd-ctx-meta-val  { color: var(--text-mid); font-weight: 600; }
.sd-ctx-active {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 18px; font-size: 0.65rem; color: #1D4ED8;
    background: rgba(37,99,235,0.06); border-top: 1px solid rgba(37,99,235,0.14);
}
.sd-ctx-active svg { width: 13px; height: 13px; flex-shrink: 0; stroke: #1D4ED8; }

/* ── Form card ── */
.sd-card {
    background: #FFFFFF;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.sd-card-hd {
    padding: 16px 20px 14px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    display: flex; align-items: center; gap: 10px;
}
.sd-card-hd-icon {
    width: 32px; height: 32px; border-radius: 7px;
    background: rgba(180,83,9,0.08); border: 1px solid rgba(180,83,9,0.20);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sd-card-hd-icon svg { width: 15px; height: 15px; stroke: #B45309; }
.sd-card-hd-text { flex: 1; }
.sd-card-title { font-size: 0.85rem; font-weight: 700; color: var(--text-hi); }
.sd-card-desc  { font-size: 0.62rem; color: var(--text-lo); margin-top: 1px; }

.sd-form { padding: 20px; display: flex; flex-direction: column; gap: 20px; }

/* ── Field groups ── */
.sd-field { display: flex; flex-direction: column; gap: 6px; }
.sd-label {
    font-size: 0.67rem; font-weight: 600; color: var(--text-mid);
    display: flex; align-items: center; gap: 6px;
}
.sd-label-req { color: #DC2626; font-size: 0.6rem; }
.sd-label-opt { font-size: 0.57rem; color: var(--text-lo); font-weight: 400; margin-left: 2px; }

.sd-input-row { display: flex; gap: 8px; align-items: stretch; }
.sd-input {
    flex: 1; padding: 10px 13px;
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.12);
    border-radius: 8px; color: var(--text-hi);
    font-size: 0.78rem; font-family: 'Geist Mono', monospace;
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none; min-height: 44px;
    color-scheme: light;
}
.sd-input:focus {
    border-color: rgba(180,83,9,0.5);
    box-shadow: 0 0 0 3px rgba(180,83,9,0.10);
}
.sd-input:hover:not(:focus) { border-color: rgba(0,0,0,0.22); }
.sd-input.has-error {
    border-color: rgba(220,38,38,0.5);
    box-shadow: 0 0 0 3px rgba(220,38,38,0.10);
}

textarea.sd-input {
    resize: vertical; min-height: 80px; font-family: 'Geist', sans-serif;
    font-size: 0.75rem; line-height: 1.5;
}

.sd-quick-btns {
    display: flex; gap: 6px; flex-wrap: wrap;
}
.sd-quick-btn {
    padding: 5px 10px; border-radius: 6px;
    background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.10);
    color: var(--text-mid); font-size: 0.60rem; font-weight: 600;
    cursor: pointer;
    transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
}
.sd-quick-btn:hover { background: rgba(180,83,9,0.08); color: #B45309; border-color: rgba(180,83,9,0.3); }

.sd-helper {
    font-size: 0.60rem; color: var(--text-lo); line-height: 1.5;
    display: flex; align-items: flex-start; gap: 5px;
}
.sd-helper svg { width: 11px; height: 11px; flex-shrink: 0; stroke: var(--text-lo); margin-top: 1px; }

/* ── Two-column grid for date fields ── */
.sd-date-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 480px) { .sd-date-grid { grid-template-columns: 1fr; } }

/* ── Duration fields ── */
.sd-dur-row {
    display: flex; gap: 8px; align-items: stretch;
}
.sd-dur-unit {
    display: flex; align-items: center; gap: 6px;
    flex: 1;
}
.sd-dur-input {
    width: 100%; padding: 10px 13px;
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.12);
    border-radius: 8px; color: var(--text-hi);
    font-size: 0.78rem; font-family: 'Geist Mono', monospace;
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none; min-height: 44px;
    color-scheme: light; text-align: center;
}
.sd-dur-input:focus {
    border-color: rgba(180,83,9,0.5);
    box-shadow: 0 0 0 3px rgba(180,83,9,0.10);
}
.sd-dur-label { font-size: 0.63rem; color: var(--text-lo); white-space: nowrap; }

/* ── Fixed/Flexible type toggle ── */
.sd-type-toggle {
    display: flex; border: 1px solid rgba(0,0,0,0.10); border-radius: 9px;
    background: rgba(0,0,0,0.03); padding: 3px; gap: 3px; width: 100%;
}
.sd-type-btn {
    flex: 1; padding: 8px 14px; border-radius: 6px; border: none; cursor: pointer;
    font-size: 0.68rem; font-weight: 600; color: var(--text-mid);
    background: transparent;
    transition: background 150ms ease, color 150ms ease, box-shadow 150ms ease;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    min-height: 38px;
}
.sd-type-btn svg { width: 13px; height: 13px; stroke: currentColor; flex-shrink: 0; }
.sd-type-btn.active {
    background: rgba(180,83,9,0.10); color: #B45309;
    border: 1px solid rgba(180,83,9,0.25);
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.sd-type-btn.active svg { stroke: #B45309; }

/* ── Duration section (progressive disclosure) ── */
.sd-flex-fields {
    display: none; flex-direction: column; gap: 10px;
    padding: 14px 16px;
    background: rgba(0,0,0,0.025);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 8px;
    animation: fade-in 150ms ease;
}
.sd-flex-fields.is-shown { display: flex; }
@keyframes fade-in { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
.sd-flex-title {
    font-size: 0.62rem; font-weight: 600; color: var(--text-lo); margin-bottom: 2px;
}

/* ── Divider ── */
.sd-divider { border: none; border-top: 1px solid rgba(0,0,0,0.07); margin: 0; }

/* ── Actions ── */
.sd-actions {
    padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    border-top: 1px solid rgba(0,0,0,0.06);
    background: rgba(0,0,0,0.025);
}
.sd-btn-cancel {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 8px; font-size: 0.70rem; font-weight: 600;
    text-decoration: none; color: var(--text-mid);
    background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.09);
    transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
    cursor: pointer; min-height: 40px;
}
.sd-btn-cancel:hover { background: rgba(0,0,0,0.08); color: var(--text-hi); border-color: rgba(0,0,0,0.18); }
.sd-btn-cancel svg { width: 13px; height: 13px; stroke: currentColor; }

.sd-btn-submit {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 8px; font-size: 0.72rem; font-weight: 700;
    border: none; cursor: pointer; min-height: 42px; min-width: 190px;
    justify-content: center;
    background: #D97706; color: #FFFFFF;
    box-shadow: 0 2px 12px rgba(180,83,9,0.20);
    transition: background 150ms ease, box-shadow 150ms ease, transform 80ms ease;
    touch-action: manipulation;
}
.sd-btn-submit:hover { background: #B45309; box-shadow: 0 4px 18px rgba(180,83,9,0.28); }
.sd-btn-submit:active { transform: scale(0.98); }
.sd-btn-submit:disabled {
    background: rgba(180,83,9,0.35); color: rgba(255,255,255,0.6);
    box-shadow: none; cursor: not-allowed; transform: none;
}
.sd-btn-submit svg { width: 15px; height: 15px; stroke: currentColor; flex-shrink: 0; }
.sd-btn-submit .btn-spinner {
    width: 15px; height: 15px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #FFFFFF;
    animation: spin 0.7s linear infinite; display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }
.sd-btn-submit.is-loading .btn-icon { display: none; }
.sd-btn-submit.is-loading .btn-spinner { display: block; }
.sd-btn-submit.is-loading .btn-label { opacity: 0.7; }

/* ── Success overlay ── */
.sd-success {
    display: none; flex-direction: column; align-items: center; justify-content: center;
    padding: 48px 24px; text-align: center;
}
.sd-success.is-shown { display: flex; }
.sd-success-ring {
    width: 60px; height: 60px; border-radius: 50%;
    background: rgba(180,83,9,0.08); border: 2px solid rgba(180,83,9,0.3);
    display: flex; align-items: center; justify-content: center; margin-bottom: 18px;
    animation: ring-pop 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes ring-pop { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.sd-success-ring svg { width: 28px; height: 28px; stroke: #B45309; stroke-width: 2.5; }
.sd-success-title { font-size: 1.05rem; font-weight: 800; color: var(--text-hi); margin-bottom: 6px; }
.sd-success-sub   { font-size: 0.72rem; color: var(--text-mid); line-height: 1.6; max-width: 360px; }
.sd-success-actions { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; justify-content: center; }
.sd-success-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 0.68rem; font-weight: 600;
    text-decoration: none; border: 1px solid rgba(0,0,0,0.10);
    color: var(--text-mid); background: rgba(0,0,0,0.04);
    transition: background 150ms ease;
}
.sd-success-btn:hover { background: rgba(0,0,0,0.08); }
.sd-success-btn.primary { background: rgba(180,83,9,0.08); color: #B45309; border-color: rgba(180,83,9,0.22); }
.sd-success-btn.primary:hover { background: rgba(180,83,9,0.14); }
.sd-success-btn svg { width: 13px; height: 13px; stroke: currentColor; }

/* ── Error banner ── */
.sd-error {
    display: none; align-items: flex-start; gap: 10px;
    padding: 12px 16px; margin: 0 20px 16px;
    background: rgba(220,38,38,0.06); border: 1px solid rgba(220,38,38,0.22);
    border-radius: 8px; font-size: 0.67rem; color: #DC2626; line-height: 1.5;
}
.sd-error.is-shown { display: flex; }
.sd-error svg { width: 14px; height: 14px; stroke: #DC2626; flex-shrink: 0; margin-top: 1px; }

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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
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
            <span class="phd-page-title">Schedule Downtime</span>
        </div>
        <div class="phd-count">Suppress alerts during planned maintenance</div>
    </div>
    <div class="phd-right">
        <a href="<?php echo $back_url; ?>" class="phd-back-btn" target="main">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <span class="sbth-label"><?php echo $is_svc ? 'Service' : 'Host'; ?></span>
            <span class="sbth-name"><?php echo $is_svc ? $sn_h : $hn_h; ?></span>
        </a>
    </div>
</div>

<div class="sd-wrap">

<!-- ── Context card ── -->
<div class="sd-ctx" style="--sac:<?php echo $sc['c']; ?>;--sabg:<?php echo $sc['bg']; ?>">
    <div class="sd-ctx-body">
        <div class="sd-ctx-icon">
            <?php if ($is_svc): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
            <?php endif; ?>
        </div>
        <div class="sd-ctx-info">
            <div class="sd-ctx-name"><?php echo $is_svc ? $sn_h : $hn_h; ?></div>
            <div class="sd-ctx-sub">
                <?php if ($is_svc): ?>
                on <a href="host.php?host=<?php echo urlencode($hn_raw); ?>" target="main"><?php echo $hn_h; ?></a>
                &nbsp;&middot;&nbsp;
                <?php endif; ?>
                <?php echo state_badge($st); ?>
            </div>
            <?php if ($plugin_out): ?>
            <div class="sd-ctx-out"><?php echo $plugin_out; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="sd-ctx-meta">
        <div class="sd-ctx-meta-item">
            <span class="sd-ctx-meta-key">Last Check</span>
            <span class="sd-ctx-meta-val"><?php echo $last_str; ?></span>
        </div>
        <div class="sd-ctx-meta-item">
            <span class="sd-ctx-meta-key">Downtime Type</span>
            <span class="sd-ctx-meta-val"><?php echo $is_svc ? 'Service Downtime' : 'Host Downtime'; ?></span>
        </div>
        <div class="sd-ctx-meta-item">
            <span class="sd-ctx-meta-key">cmd_typ</span>
            <span class="sd-ctx-meta-val"><?php echo $cmd_typ; ?></span>
        </div>
    </div>
    <?php if ($in_dt): ?>
    <div class="sd-ctx-active">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        This <?php echo $is_svc ? 'service' : 'host'; ?> is <strong>already in a scheduled downtime</strong>. You can add another window if needed.
    </div>
    <?php endif; ?>
</div>

<!-- ── Form card ── -->
<div class="sd-card">
    <div class="sd-card-hd">
        <div class="sd-card-hd-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
        </div>
        <div class="sd-card-hd-text">
            <div class="sd-card-title">Schedule Maintenance Window</div>
            <div class="sd-card-desc">Alerts will be suppressed for this <?php echo $is_svc ? 'service' : 'host'; ?> during the specified window</div>
        </div>
    </div>

    <!-- Error banner -->
    <div class="sd-error" id="sd-error" role="alert" aria-live="polite">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="sd-error-text">Command failed. Please try again.</span>
    </div>

    <!-- Main form -->
    <div id="sd-form-area">
        <form id="sd-form" action="<?php echo $cmd_action; ?>" method="post" novalidate>
            <input type="hidden" name="cmd_typ"    value="<?php echo $cmd_typ; ?>">
            <input type="hidden" name="cmd_mod"    value="2">
            <input type="hidden" name="host"       value="<?php echo h($hn_raw); ?>">
            <?php if ($is_svc): ?>
            <input type="hidden" name="service"    value="<?php echo h($sn_raw); ?>">
            <?php endif; ?>
            <input type="hidden" name="trigger_id" value="0">
            <!-- Nagios-format datetimes, populated by JS -->
            <input type="hidden" name="start_time" id="start_time_hidden">
            <input type="hidden" name="end_time"   id="end_time_hidden">
            <!-- fixed: 1=fixed, 0=flexible -->
            <input type="hidden" name="fixed"      id="fixed_hidden" value="1">

            <div class="sd-form">

                <!-- ── Time window ── -->
                <div class="sd-date-grid">
                    <div class="sd-field">
                        <label class="sd-label" for="sd-start">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Start Time
                            <span class="sd-label-req" aria-label="required">*</span>
                        </label>
                        <input
                            type="datetime-local"
                            id="sd-start"
                            class="sd-input"
                            value="<?php echo $default_start; ?>"
                            required
                            aria-describedby="sd-time-help"
                        >
                    </div>
                    <div class="sd-field">
                        <label class="sd-label" for="sd-end">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            End Time
                            <span class="sd-label-req" aria-label="required">*</span>
                        </label>
                        <input
                            type="datetime-local"
                            id="sd-end"
                            class="sd-input"
                            value="<?php echo $default_end; ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Quick duration presets -->
                <div class="sd-field" style="margin-top:-10px">
                    <div class="sd-label">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
                        Quick Duration
                    </div>
                    <div class="sd-quick-btns">
                        <button type="button" class="sd-quick-btn" data-mins="30">30 min</button>
                        <button type="button" class="sd-quick-btn" data-mins="60">1 hour</button>
                        <button type="button" class="sd-quick-btn" data-mins="120" id="sd-q-2h">2 hours</button>
                        <button type="button" class="sd-quick-btn" data-mins="240">4 hours</button>
                        <button type="button" class="sd-quick-btn" data-mins="480">8 hours</button>
                        <button type="button" class="sd-quick-btn" data-mins="1440">1 day</button>
                    </div>
                    <div class="sd-helper" id="sd-time-help">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Quick buttons set the end time relative to the current start time.
                    </div>
                </div>

                <hr class="sd-divider">

                <!-- ── Fixed / Flexible toggle ── -->
                <div class="sd-field">
                    <label class="sd-label">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        Downtime Type
                    </label>
                    <div class="sd-type-toggle" role="group" aria-label="Downtime type">
                        <button type="button" class="sd-type-btn active" id="sd-fixed-btn" aria-pressed="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                            Fixed
                        </button>
                        <button type="button" class="sd-type-btn" id="sd-flex-btn" aria-pressed="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                            Flexible
                        </button>
                    </div>
                    <div class="sd-helper">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span id="sd-type-desc">Fixed: downtime lasts the entire start-to-end period regardless of host state.</span>
                    </div>

                    <!-- Flexible duration fields (progressive disclosure) -->
                    <div class="sd-flex-fields" id="sd-flex-fields">
                        <div class="sd-flex-title">Flexible Duration (starts when host enters problem state)</div>
                        <div class="sd-dur-row">
                            <div class="sd-dur-unit">
                                <input type="number" name="hours" id="sd-hours" class="sd-dur-input"
                                    value="2" min="0" max="999" aria-label="Hours">
                                <span class="sd-dur-label">hours</span>
                            </div>
                            <div class="sd-dur-unit">
                                <input type="number" name="minutes" id="sd-minutes" class="sd-dur-input"
                                    value="0" min="0" max="59" aria-label="Minutes">
                                <span class="sd-dur-label">minutes</span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="sd-divider">

                <!-- ── Comment ── -->
                <div class="sd-field">
                    <label class="sd-label" for="sd-comment">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        Comment
                        <span class="sd-label-req" aria-label="required">*</span>
                    </label>
                    <textarea
                        id="sd-comment"
                        name="com_data"
                        class="sd-input"
                        placeholder="Reason for downtime, ticket number, etc."
                        required
                        aria-describedby="sd-comment-help"
                    ></textarea>
                    <div class="sd-helper" id="sd-comment-help">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Displayed in the downtime list and notifications.
                    </div>
                </div>

                <!-- ── Author ── -->
                <div class="sd-field">
                    <label class="sd-label" for="sd-author">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Author
                        <span class="sd-label-opt">(auto-filled)</span>
                    </label>
                    <input
                        type="text"
                        id="sd-author"
                        name="com_author"
                        class="sd-input"
                        value="<?php echo $author_default; ?>"
                        placeholder="nagiosadmin"
                        style="font-family:'Geist',sans-serif;"
                    >
                </div>

            </div><!-- /.sd-form -->

            <div class="sd-actions">
                <a href="<?php echo $back_url; ?>" class="sd-btn-cancel" target="main">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Cancel
                </a>
                <button type="submit" class="sd-btn-submit" id="sd-submit">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                    <div class="btn-spinner"></div>
                    <span class="btn-label">Schedule Downtime</span>
                </button>
            </div>

        </form>
    </div><!-- /#sd-form-area -->

    <!-- Success state -->
    <div class="sd-success" id="sd-success" aria-live="polite">
        <div class="sd-success-ring">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="sd-success-title">Downtime Scheduled</div>
        <div class="sd-success-sub" id="sd-success-msg">
            Downtime has been scheduled for
            <strong><?php echo $is_svc ? $sn_h . ' on ' . $hn_h : $hn_h; ?></strong>.
            Alerts will be suppressed during the maintenance window.
        </div>
        <div class="sd-success-actions">
            <a href="<?php echo $back_url; ?>" class="sd-success-btn primary" target="main">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to <?php echo $is_svc ? 'Service' : 'Host'; ?>
            </a>
            <a href="hosts.php" class="sd-success-btn" target="main">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                All Hosts
            </a>
        </div>
    </div>

</div><!-- /.sd-card -->

</div><!-- /.sd-wrap -->

<script>
(function () {
    const startIn   = document.getElementById('sd-start');
    const endIn     = document.getElementById('sd-end');
    const startHid  = document.getElementById('start_time_hidden');
    const endHid    = document.getElementById('end_time_hidden');
    const fixedHid  = document.getElementById('fixed_hidden');
    const fixedBtn  = document.getElementById('sd-fixed-btn');
    const flexBtn   = document.getElementById('sd-flex-btn');
    const flexFlds  = document.getElementById('sd-flex-fields');
    const typeDesc  = document.getElementById('sd-type-desc');
    const form      = document.getElementById('sd-form');
    const submitBtn = document.getElementById('sd-submit');
    const errorBox  = document.getElementById('sd-error');
    const errorTxt  = document.getElementById('sd-error-text');
    const formArea  = document.getElementById('sd-form-area');
    const successEl = document.getElementById('sd-success');
    const commentEl = document.getElementById('sd-comment');

    /* ── Convert datetime-local value → MM/DD/YYYY HH:MM:SS ── */
    function toNagiosTime(val) {
        if (!val) return '';
        const d = new Date(val);
        if (isNaN(d)) return '';
        const p = n => String(n).padStart(2, '0');
        return `${p(d.getMonth()+1)}/${p(d.getDate())}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:00`;
    }

    /* ── Local datetime string (for setting input value) ── */
    function toLocalDT(ms) {
        const d = new Date(ms);
        const p = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
    }

    /* ── Quick duration presets ── */
    document.querySelectorAll('.sd-quick-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const mins = parseInt(btn.dataset.mins, 10);
            const startVal = startIn.value;
            if (!startVal) return;
            const startMs = new Date(startVal).getTime();
            if (isNaN(startMs)) return;
            endIn.value = toLocalDT(startMs + mins * 60000);
        });
    });

    /* ── Fixed / Flexible toggle ── */
    let isFixed = true;

    function setFixed() {
        isFixed = true;
        fixedHid.value = '1';
        fixedBtn.classList.add('active');
        flexBtn.classList.remove('active');
        fixedBtn.setAttribute('aria-pressed', 'true');
        flexBtn.setAttribute('aria-pressed', 'false');
        flexFlds.classList.remove('is-shown');
        typeDesc.textContent = 'Fixed: downtime lasts the entire start-to-end period regardless of host state.';
    }

    function setFlexible() {
        isFixed = false;
        fixedHid.value = '0';
        flexBtn.classList.add('active');
        fixedBtn.classList.remove('active');
        flexBtn.setAttribute('aria-pressed', 'true');
        fixedBtn.setAttribute('aria-pressed', 'false');
        flexFlds.classList.add('is-shown');
        typeDesc.textContent = 'Flexible: downtime starts when the host/service enters a problem state and lasts for the specified duration.';
    }

    fixedBtn.addEventListener('click', setFixed);
    flexBtn.addEventListener('click', setFlexible);

    /* ── Validation helpers ── */
    function markError(el) { el.classList.add('has-error'); }
    function clearError(el) { el.classList.remove('has-error'); }
    [startIn, endIn, commentEl].forEach(function(el) {
        el.addEventListener('input', function() { clearError(el); });
    });

    /* ── Form submit: AJAX → cmd.cgi ── */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        /* Client-side validation */
        let valid = true;
        if (!startIn.value) { markError(startIn); startIn.focus(); valid = false; }
        if (!endIn.value)   { markError(endIn);   if (valid) endIn.focus(); valid = false; }
        if (!commentEl.value.trim()) { markError(commentEl); if (valid) commentEl.focus(); valid = false; }
        if (!valid) return;

        /* Validate end > start */
        const startMs = new Date(startIn.value).getTime();
        const endMs   = new Date(endIn.value).getTime();
        if (endMs <= startMs) {
            markError(endIn);
            endIn.focus();
            errorTxt.textContent = 'End time must be after start time.';
            errorBox.classList.add('is-shown');
            return;
        }
        errorBox.classList.remove('is-shown');

        /* Populate hidden Nagios-format fields */
        startHid.value = toNagiosTime(startIn.value);
        endHid.value   = toNagiosTime(endIn.value);

        /* Loading state */
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');

        try {
            const fd = new FormData(form);
            const resp = await fetch(form.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });

            const html = await resp.text();
            const ok = html.includes('successfully submitted') ||
                       html.includes('Your command request was successfully') ||
                       html.includes('was successfully submitted');

            if (ok) {
                formArea.style.display = 'none';
                successEl.classList.add('is-shown');
            } else {
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
