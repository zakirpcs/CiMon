<?php
include_once(dirname(__FILE__).'/includes/nagios_data.inc.php');
$cgi = $cfg['cgi_base_url'];
$sf  = $cfg['status_file'] ?? '/usr/local/nagios/var/status.dat';
$now = time();

/* ── Parameters ── */
$hn_raw = trim($_GET['host']    ?? '');
$sn_raw = trim($_GET['service'] ?? '');
$is_svc = $sn_raw !== '';
$cmd_typ = $is_svc ? 3 : 1;

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

$last_chk   = (int)(($target ?? [])['last_check']  ?? 0);
$plugin_out = h(($target ?? [])['plugin_output']    ?? '');

/* ── Existing comment count ── */
$cdr = nagios_parse_comments_downtime($sf);
if ($is_svc) {
    $existing = array_values(array_filter($cdr['comments'], function($c) use($hn_raw,$sn_raw){
        return ($c['host_name']??'') === $hn_raw && ($c['service_description']??'') === $sn_raw && ($c['kind']==='servicecomment');
    }));
} else {
    $existing = array_values(array_filter($cdr['comments'], function($c) use($hn_raw){
        return ($c['host_name']??'') === $hn_raw && ($c['kind']==='hostcomment');
    }));
}
$existing_count = count($existing);

$last_str = $last_chk ? fmt_ago($last_chk) . ' (' . date('H:i:s', $last_chk) . ')' : '—';

/* ── Logged-in author ── */
$author_default = h($_SERVER['PHP_AUTH_USER'] ?? 'nagiosadmin');

/* ── Back URL ── */
$back_url = $is_svc
    ? h('service.php?host='.urlencode($hn_raw).'&service='.urlencode($sn_raw))
    : h('host.php?host='.urlencode($hn_raw));

/* ── cmd.cgi endpoint ── */
$cmd_action = h($cgi . '/cmd.cgi');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Add Comment &mdash; <?php echo $is_svc ? $sn_h.' on ' : ''; ?><?php echo $hn_h; ?></title>
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
.ac-wrap {
    max-width: 580px;
    margin: 0 auto;
    padding: 20px 16px 48px;
}

/* ── Context card ── */
.ac-ctx {
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.08);
    border-left: 4px solid var(--sac, #16a34a);
    background: var(--sabg, rgba(22,163,74,0.04));
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.ac-ctx-body {
    padding: 16px 18px 14px;
    display: flex; align-items: flex-start; gap: 14px;
}
.ac-ctx-icon {
    width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
    background: rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.08);
    display: flex; align-items: center; justify-content: center;
}
.ac-ctx-icon svg { width: 17px; height: 17px; stroke: var(--sac, #16a34a); }
.ac-ctx-info { flex: 1; min-width: 0; }
.ac-ctx-name {
    font-size: 1.0rem; font-weight: 700; color: var(--text-hi);
    line-height: 1.25; margin-bottom: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ac-ctx-sub  { font-size: 0.66rem; color: var(--text-lo); margin-bottom: 6px; }
.ac-ctx-sub a { color: var(--amber); text-decoration: none; }
.ac-ctx-sub a:hover { text-decoration: underline; }
.ac-ctx-out {
    font-size: 0.67rem; color: var(--text-mid); font-family: 'Geist Mono', monospace;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    background: rgba(0,0,0,0.04); border-radius: 5px;
    padding: 5px 9px; margin-top: 6px;
}
.ac-ctx-meta {
    display: flex; gap: 20px; flex-wrap: wrap;
    padding: 10px 18px;
    border-top: 1px solid rgba(0,0,0,0.06);
    background: rgba(0,0,0,0.025);
}
.ac-ctx-meta-item { font-size: 0.62rem; }
.ac-ctx-meta-key  { color: var(--text-lo); display: block; margin-bottom: 1px; }
.ac-ctx-meta-val  { color: var(--text-mid); font-weight: 600; }

/* Existing comments notice */
.ac-ctx-cmt-note {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 18px; font-size: 0.65rem; color: #1D4ED8;
    background: rgba(37,99,235,0.06); border-top: 1px solid rgba(37,99,235,0.14);
}
.ac-ctx-cmt-note svg { width: 13px; height: 13px; flex-shrink: 0; stroke: #1D4ED8; }

/* ── Form card ── */
.ac-card {
    background: #FFFFFF;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.ac-card-hd {
    padding: 16px 20px 14px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    display: flex; align-items: center; gap: 10px;
}
.ac-card-hd-icon {
    width: 32px; height: 32px; border-radius: 7px;
    background: rgba(180,83,9,0.08); border: 1px solid rgba(180,83,9,0.20);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ac-card-hd-icon svg { width: 15px; height: 15px; stroke: #B45309; }
.ac-card-hd-text { flex: 1; }
.ac-card-title { font-size: 0.85rem; font-weight: 700; color: var(--text-hi); }
.ac-card-desc  { font-size: 0.62rem; color: var(--text-lo); margin-top: 1px; }

.ac-form { padding: 20px; display: flex; flex-direction: column; gap: 20px; }

/* ── Fields ── */
.ac-field { display: flex; flex-direction: column; gap: 6px; }
.ac-label {
    font-size: 0.67rem; font-weight: 600; color: var(--text-mid);
    display: flex; align-items: center; gap: 6px;
}
.ac-label-req { color: #DC2626; font-size: 0.6rem; }
.ac-label-opt { font-size: 0.57rem; color: var(--text-lo); font-weight: 400; margin-left: 2px; }

.ac-input {
    width: 100%; padding: 10px 13px; box-sizing: border-box;
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.12);
    border-radius: 8px; color: var(--text-hi);
    font-size: 0.78rem; font-family: 'Geist', sans-serif;
    transition: border-color 150ms ease, box-shadow 150ms ease;
    outline: none; min-height: 44px;
}
.ac-input:focus {
    border-color: rgba(180,83,9,0.5);
    box-shadow: 0 0 0 3px rgba(180,83,9,0.10);
}
.ac-input:hover:not(:focus) { border-color: rgba(0,0,0,0.22); }
.ac-input.has-error {
    border-color: rgba(220,38,38,0.5);
    box-shadow: 0 0 0 3px rgba(220,38,38,0.10);
}

textarea.ac-input {
    resize: vertical; min-height: 100px;
    font-size: 0.75rem; line-height: 1.6;
}

/* Character counter */
.ac-char-count {
    font-size: 0.58rem; color: var(--text-lo); text-align: right;
    transition: color 150ms ease;
}
.ac-char-count.near-limit { color: #D97706; }
.ac-char-count.at-limit   { color: #DC2626; }

.ac-helper {
    font-size: 0.60rem; color: var(--text-lo); line-height: 1.5;
    display: flex; align-items: flex-start; gap: 5px;
}
.ac-helper svg { width: 11px; height: 11px; flex-shrink: 0; stroke: var(--text-lo); margin-top: 1px; }

/* ── Persistent toggle ── */
.ac-toggle-row {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 14px 16px;
    background: rgba(0,0,0,0.025);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 8px; cursor: pointer;
    transition: background 150ms ease, border-color 150ms ease;
}
.ac-toggle-row:hover { background: rgba(0,0,0,0.045); border-color: rgba(0,0,0,0.14); }
.ac-toggle-row input[type="checkbox"] { display: none; }

.ac-toggle-switch {
    width: 36px; height: 20px; border-radius: 10px; flex-shrink: 0;
    background: rgba(0,0,0,0.12); border: 1px solid rgba(0,0,0,0.14);
    position: relative; transition: background 200ms ease, border-color 200ms ease;
    margin-top: 1px;
}
.ac-toggle-switch::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 12px; height: 12px; border-radius: 50%;
    background: rgba(0,0,0,0.3);
    transition: transform 200ms ease, background 200ms ease;
}
.ac-toggle-row.is-on .ac-toggle-switch {
    background: rgba(180,83,9,0.18);
    border-color: rgba(180,83,9,0.35);
}
.ac-toggle-row.is-on .ac-toggle-switch::after {
    transform: translateX(16px);
    background: #B45309;
}
.ac-toggle-info { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.ac-toggle-info-top { display: flex; align-items: center; gap: 10px; }
.ac-toggle-label { font-size: 0.73rem; font-weight: 600; color: var(--text-hi); }
.ac-toggle-badge {
    font-size: 0.54rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.06em;
    background: rgba(180,83,9,0.10); color: #B45309;
    border: 1px solid rgba(180,83,9,0.25);
    opacity: 0; transition: opacity 150ms ease;
}
.ac-toggle-row.is-on .ac-toggle-badge { opacity: 1; }
.ac-toggle-desc { font-size: 0.62rem; color: var(--text-lo); line-height: 1.45; }

/* ── Divider ── */
.ac-divider { border: none; border-top: 1px solid rgba(0,0,0,0.07); margin: 0; }

/* ── Actions ── */
.ac-actions {
    padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    border-top: 1px solid rgba(0,0,0,0.06);
    background: rgba(0,0,0,0.025);
}
.ac-btn-cancel {
    display: inline-flex; align-items: center; gap: 6px;
    height: 36px; padding: 0 16px; box-sizing: border-box;
    border-radius: 8px; font-size: 0.70rem; font-weight: 600; line-height: 1;
    text-decoration: none; color: var(--text-mid);
    background: rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.09);
    transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
    cursor: pointer;
}
.ac-btn-cancel:hover { background: rgba(0,0,0,0.08); color: var(--text-hi); border-color: rgba(0,0,0,0.18); }
.ac-btn-cancel svg { width: 13px; height: 13px; stroke: currentColor; }

.ac-btn-submit {
    display: inline-flex; align-items: center; gap: 8px;
    height: 36px; padding: 0 18px; box-sizing: border-box;
    border-radius: 8px; font-size: 0.70rem; font-weight: 700; line-height: 1;
    border: none; cursor: pointer; justify-content: center;
    background: #D97706; color: #FFFFFF;
    box-shadow: 0 2px 12px rgba(180,83,9,0.20);
    transition: background 150ms ease, box-shadow 150ms ease, transform 80ms ease;
    touch-action: manipulation;
}
.ac-btn-submit:hover { background: #B45309; box-shadow: 0 4px 18px rgba(180,83,9,0.28); }
.ac-btn-submit:active { transform: scale(0.98); }
.ac-btn-submit:disabled {
    background: rgba(180,83,9,0.35); color: rgba(255,255,255,0.6);
    box-shadow: none; cursor: not-allowed; transform: none;
}
.ac-btn-submit .btn-spinner {
    width: 15px; height: 15px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #FFFFFF;
    animation: spin 0.7s linear infinite; display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }
.ac-btn-submit.is-loading .btn-icon    { display: none; }
.ac-btn-submit.is-loading .btn-spinner { display: block; }
.ac-btn-submit.is-loading .btn-label   { opacity: 0.7; }

/* ── Success overlay ── */
.ac-success {
    display: none; flex-direction: column; align-items: center; justify-content: center;
    padding: 48px 24px; text-align: center;
}
.ac-success.is-shown { display: flex; }
.ac-success-ring {
    width: 60px; height: 60px; border-radius: 50%;
    background: rgba(22,163,74,0.08); border: 2px solid rgba(22,163,74,0.30);
    display: flex; align-items: center; justify-content: center; margin-bottom: 18px;
    animation: ring-pop 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes ring-pop { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.ac-success-ring svg { width: 28px; height: 28px; stroke: #15803D; stroke-width: 2.5; }
.ac-success-title { font-size: 1.05rem; font-weight: 800; color: var(--text-hi); margin-bottom: 6px; }
.ac-success-sub   { font-size: 0.72rem; color: var(--text-mid); line-height: 1.6; max-width: 340px; }
.ac-success-preview {
    margin-top: 16px; padding: 12px 18px; max-width: 380px; width: 100%;
    background: rgba(0,0,0,0.025); border: 1px solid rgba(0,0,0,0.08);
    border-radius: 8px; text-align: left;
}
.ac-success-preview-label { font-size: 0.57rem; font-weight: 700; color: var(--text-lo); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 6px; }
.ac-success-preview-text  { font-size: 0.72rem; color: var(--text-body); line-height: 1.5; word-break: break-word; }
.ac-success-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; justify-content: center; }
.ac-success-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 0.68rem; font-weight: 600;
    text-decoration: none; border: 1px solid rgba(0,0,0,0.10);
    color: var(--text-mid); background: rgba(0,0,0,0.04);
    transition: background 150ms ease;
}
.ac-success-btn:hover { background: rgba(0,0,0,0.08); }
.ac-success-btn.primary { background: rgba(22,163,74,0.08); color: #15803D; border-color: rgba(22,163,74,0.22); }
.ac-success-btn.primary:hover { background: rgba(22,163,74,0.14); }
.ac-success-btn svg { width: 13px; height: 13px; stroke: currentColor; }

/* ── Error banner ── */
.ac-error {
    display: none; align-items: flex-start; gap: 10px;
    padding: 12px 16px; margin: 0 20px 16px;
    background: rgba(220,38,38,0.06); border: 1px solid rgba(220,38,38,0.22);
    border-radius: 8px; font-size: 0.67rem; color: #DC2626; line-height: 1.5;
}
.ac-error.is-shown { display: flex; }
.ac-error svg { width: 14px; height: 14px; stroke: #DC2626; flex-shrink: 0; margin-top: 1px; }

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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
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
            <span class="phd-page-title">Add Comment</span>
        </div>
        <div class="phd-count">Attach a note to this <?php echo $is_svc ? 'service' : 'host'; ?> visible in the Nagios interface</div>
    </div>
    <div class="phd-right">
        <a href="<?php echo $back_url; ?>" class="phd-back-btn" target="main">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <span class="sbth-label"><?php echo $is_svc ? 'Service' : 'Host'; ?></span>
            <span class="sbth-name"><?php echo $is_svc ? $sn_h : $hn_h; ?></span>
        </a>
    </div>
</div>

<div class="ac-wrap">

<!-- ── Context card ── -->
<div class="ac-ctx" style="--sac:<?php echo $sc['c']; ?>;--sabg:<?php echo $sc['bg']; ?>">
    <div class="ac-ctx-body">
        <div class="ac-ctx-icon">
            <?php if ($is_svc): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
            <?php endif; ?>
        </div>
        <div class="ac-ctx-info">
            <div class="ac-ctx-name"><?php echo $is_svc ? $sn_h : $hn_h; ?></div>
            <div class="ac-ctx-sub">
                <?php if ($is_svc): ?>
                on <a href="host.php?host=<?php echo urlencode($hn_raw); ?>" target="main"><?php echo $hn_h; ?></a>
                &nbsp;&middot;&nbsp;
                <?php endif; ?>
                <?php echo state_badge($st); ?>
            </div>
            <?php if ($plugin_out): ?>
            <div class="ac-ctx-out"><?php echo $plugin_out; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="ac-ctx-meta">
        <div class="ac-ctx-meta-item">
            <span class="ac-ctx-meta-key">Last Check</span>
            <span class="ac-ctx-meta-val"><?php echo $last_str; ?></span>
        </div>
        <div class="ac-ctx-meta-item">
            <span class="ac-ctx-meta-key">Existing Comments</span>
            <span class="ac-ctx-meta-val"><?php echo $existing_count > 0 ? $existing_count : 'None'; ?></span>
        </div>
    </div>
    <?php if ($existing_count > 0): ?>
    <div class="ac-ctx-cmt-note">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        This <?php echo $is_svc ? 'service' : 'host'; ?> already has <strong><?php echo $existing_count; ?> comment<?php echo $existing_count !== 1 ? 's' : ''; ?></strong>. This will add another.
    </div>
    <?php endif; ?>
</div>

<!-- ── Form card ── -->
<div class="ac-card">
    <div class="ac-card-hd">
        <div class="ac-card-hd-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div class="ac-card-hd-text">
            <div class="ac-card-title">New Comment</div>
            <div class="ac-card-desc">Add a note to <?php echo $is_svc ? h($sn_raw) : $hn_h; ?> — visible to all Nagios users</div>
        </div>
    </div>

    <!-- Error banner -->
    <div class="ac-error" id="ac-error" role="alert" aria-live="polite">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="ac-error-text">Command failed. Please try again.</span>
    </div>

    <!-- Main form -->
    <div id="ac-form-area">
        <form id="ac-form" action="<?php echo $cmd_action; ?>" method="post" novalidate>
            <input type="hidden" name="cmd_typ" value="<?php echo $cmd_typ; ?>">
            <input type="hidden" name="cmd_mod" value="2">
            <input type="hidden" name="host"    value="<?php echo h($hn_raw); ?>">
            <?php if ($is_svc): ?>
            <input type="hidden" name="service" value="<?php echo h($sn_raw); ?>">
            <?php endif; ?>

            <div class="ac-form">

                <!-- ── Comment ── -->
                <div class="ac-field">
                    <label class="ac-label" for="ac-comment">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        Comment
                        <span class="ac-label-req" aria-label="required">*</span>
                    </label>
                    <textarea
                        id="ac-comment"
                        name="com_data"
                        class="ac-input"
                        placeholder="Enter your note, ticket reference, or observation…"
                        required
                        maxlength="4000"
                        aria-describedby="ac-comment-help ac-char-count"
                    ></textarea>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                        <div class="ac-helper" id="ac-comment-help">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            Visible to all users in the host/service comments list.
                        </div>
                        <div class="ac-char-count" id="ac-char-count" aria-live="polite">0 / 4000</div>
                    </div>
                </div>

                <hr class="ac-divider">

                <!-- ── Author ── -->
                <div class="ac-field">
                    <label class="ac-label" for="ac-author">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Author
                        <span class="ac-label-opt">(auto-filled)</span>
                    </label>
                    <input
                        type="text"
                        id="ac-author"
                        name="com_author"
                        class="ac-input"
                        value="<?php echo $author_default; ?>"
                        placeholder="nagiosadmin"
                    >
                </div>

                <!-- ── Persistent toggle ── -->
                <div class="ac-field">
                    <label class="ac-label">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
                        Options
                    </label>
                    <label class="ac-toggle-row" id="ac-persist-label">
                        <input type="checkbox" name="persistent" id="ac-persist" value="1" checked>
                        <div class="ac-toggle-info">
                            <div class="ac-toggle-info-top">
                                <div class="ac-toggle-switch"></div>
                                <span class="ac-toggle-label">Persistent Comment</span>
                                <span class="ac-toggle-badge">ON</span>
                            </div>
                            <div class="ac-toggle-desc">
                                Keep this comment after the <?php echo $is_svc ? 'service' : 'host'; ?> recovers or is acknowledged.
                                Disable to auto-delete on next state change.
                            </div>
                        </div>
                    </label>
                </div>

            </div><!-- /.ac-form -->

            <div class="ac-actions">
                <a href="<?php echo $back_url; ?>" class="ac-btn-cancel" target="main">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Cancel
                </a>
                <button type="submit" class="ac-btn-submit" id="ac-submit">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <div class="btn-spinner"></div>
                    <span class="btn-label">Add Comment</span>
                </button>
            </div>

        </form>
    </div><!-- /#ac-form-area -->

    <!-- Success state -->
    <div class="ac-success" id="ac-success" aria-live="polite">
        <div class="ac-success-ring">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="ac-success-title">Comment Added</div>
        <div class="ac-success-sub">
            Your comment has been posted to
            <strong><?php echo $is_svc ? $sn_h . ' on ' . $hn_h : $hn_h; ?></strong>.
        </div>
        <div class="ac-success-preview">
            <div class="ac-success-preview-label">Your comment</div>
            <div class="ac-success-preview-text" id="ac-success-preview-text"></div>
        </div>
        <div class="ac-success-actions">
            <a href="<?php echo $back_url; ?>" class="ac-success-btn primary" target="main">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to <?php echo $is_svc ? 'Service' : 'Host'; ?>
            </a>
            <a href="hosts.php" class="ac-success-btn" target="main">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                All Hosts
            </a>
        </div>
    </div>

</div><!-- /.ac-card -->

</div><!-- /.ac-wrap -->

<script>
(function () {
    const form         = document.getElementById('ac-form');
    const commentEl    = document.getElementById('ac-comment');
    const charCountEl  = document.getElementById('ac-char-count');
    const submitBtn    = document.getElementById('ac-submit');
    const persistChk   = document.getElementById('ac-persist');
    const persistRow   = document.getElementById('ac-persist-label');
    const errorBox     = document.getElementById('ac-error');
    const errorTxt     = document.getElementById('ac-error-text');
    const formArea     = document.getElementById('ac-form-area');
    const successEl    = document.getElementById('ac-success');
    const previewTxt   = document.getElementById('ac-success-preview-text');
    const MAX          = 4000;

    /* ── Character counter ── */
    function updateCount() {
        const len = commentEl.value.length;
        charCountEl.textContent = len + ' / ' + MAX;
        charCountEl.classList.toggle('near-limit', len > MAX * 0.85 && len < MAX);
        charCountEl.classList.toggle('at-limit',   len >= MAX);
    }
    commentEl.addEventListener('input', function() {
        clearError(commentEl);
        updateCount();
    });
    updateCount();

    /* ── Persistent toggle visual ── */
    function syncToggle() {
        persistRow.classList.toggle('is-on', persistChk.checked);
    }
    persistChk.addEventListener('change', syncToggle);
    persistRow.addEventListener('click', function() { requestAnimationFrame(syncToggle); });
    syncToggle();

    /* ── Validation ── */
    function markError(el) { el.classList.add('has-error'); }
    function clearError(el) { el.classList.remove('has-error'); }
    commentEl.addEventListener('blur', function() {
        if (!commentEl.value.trim()) markError(commentEl);
    });

    /* ── Form submit: AJAX → cmd.cgi ── */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!commentEl.value.trim()) {
            markError(commentEl);
            commentEl.focus();
            return;
        }
        errorBox.classList.remove('is-shown');

        const commentText = commentEl.value.trim();

        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');

        try {
            const fd = new FormData(form);
            /* Ensure persistent checkbox value is correct when unchecked */
            if (!persistChk.checked) {
                fd.set('persistent', '0');
            }
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
                previewTxt.textContent = commentText;
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
