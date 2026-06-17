<?php
/**
 * Shared data-reading helpers for custom Nagios pages.
 * Reads status.dat and nagios.log directly — no CGI calls needed.
 */
require_once(dirname(__FILE__).'/../config.inc.php');

/* ── status.dat parser ──────────────────────────────────────────────── */
function nagios_parse_status($file) {
    if (!$file || !file_exists($file) || !is_readable($file)) {
        return ['ok' => false, 'error' => 'Status file not readable: ' . $file,
                'hosts' => [], 'services' => [], 'program' => []];
    }

    $hosts = []; $services = []; $program = [];
    $block_type = null;
    $block = [];

    $fh = fopen($file, 'r');
    while (($line = fgets($fh)) !== false) {
        $t = rtrim($line);
        $t = ltrim($t);
        if ($t === '' || $t[0] === '#') continue;

        if (preg_match('/^(\w+)\s*\{/', $t, $m)) {
            $block_type = $m[1];
            $block = [];
            continue;
        }

        if ($t === '}') {
            if ($block_type === 'hoststatus'   && isset($block['host_name'])) $hosts[]    = $block;
            if ($block_type === 'servicestatus' && isset($block['host_name'])) $services[] = $block;
            if ($block_type === 'programstatus')                                $program    = $block;
            $block_type = null;
            continue;
        }

        if ($block_type) {
            $eq = strpos($t, '=');
            if ($eq !== false)
                $block[substr($t, 0, $eq)] = substr($t, $eq + 1);
        }
    }
    fclose($fh);

    return ['ok' => true, 'hosts' => $hosts, 'services' => $services, 'program' => $program];
}

/* ── nagios.log parser ──────────────────────────────────────────────── */
function nagios_find_log($main_cfg) {
    if ($main_cfg && file_exists($main_cfg)) {
        $fh = fopen($main_cfg, 'r');
        while (($line = fgets($fh)) !== false) {
            $line = ltrim(rtrim($line));
            if (strncmp($line, 'log_file=', 9) === 0)
                return substr($line, 9);
        }
        fclose($fh);
    }
    return '/usr/local/nagios/var/nagios.log';
}

function nagios_parse_log($file, $type = 'alerts', $limit = 500) {
    if (!$file || !file_exists($file) || !is_readable($file)) {
        return ['ok' => false, 'error' => 'Log file not readable: ' . $file, 'entries' => []];
    }

    $entries = [];

    /* Read from the end so we get newest first without loading the whole file. */
    $fh = fopen($file, 'r');
    fseek($fh, 0, SEEK_END);
    $pos  = ftell($fh);
    $buf  = '';
    $done = false;

    while (!$done && count($entries) < $limit) {
        $chunk = min($pos, 65536);
        if ($chunk === 0) { $done = true; break; }
        $pos -= $chunk;
        fseek($fh, $pos);
        $buf = fread($fh, $chunk) . $buf;

        $lines = explode("\n", $buf);

        /* Keep the first element — it may be a partial line. */
        $buf = array_shift($lines);

        /* Process complete lines newest-first. */
        foreach (array_reverse($lines) as $line) {
            if (count($entries) >= $limit) { $done = true; break; }
            $entry = _parse_log_line($line, $type);
            if ($entry) $entries[] = $entry;
        }
    }

    /* Handle remaining buffer (first line of file). */
    if (!$done && $buf !== '') {
        $entry = _parse_log_line($buf, $type);
        if ($entry) $entries[] = $entry;
    }

    fclose($fh);
    return ['ok' => true, 'entries' => $entries];
}

function _parse_log_line($line, $type) {
    $line = trim($line);
    if ($line === '' || $line[0] !== '[') return null;

    $rbr = strpos($line, ']');
    if ($rbr === false) return null;

    $ts      = (int)substr($line, 1, $rbr - 1);
    $content = ltrim(substr($line, $rbr + 1));

    if ($type === 'alerts') {
        if (strncmp($content, 'HOST ALERT: ', 12) === 0) {
            $p = explode(';', substr($content, 12), 5);
            return [
                'ts'         => $ts,
                'kind'       => 'HOST',
                'host'       => $p[0] ?? '',
                'service'    => '',
                'state'      => $p[1] ?? '',
                'state_type' => $p[2] ?? '',
                'output'     => $p[4] ?? '',
            ];
        }
        if (strncmp($content, 'SERVICE ALERT: ', 15) === 0) {
            $p = explode(';', substr($content, 15), 6);
            return [
                'ts'         => $ts,
                'kind'       => 'SERVICE',
                'host'       => $p[0] ?? '',
                'service'    => $p[1] ?? '',
                'state'      => $p[2] ?? '',
                'state_type' => $p[3] ?? '',
                'output'     => $p[5] ?? '',
            ];
        }
    }

    if ($type === 'notifications') {
        if (strncmp($content, 'HOST NOTIFICATION: ', 19) === 0) {
            $p = explode(';', substr($content, 19), 5);
            return [
                'ts'      => $ts,
                'kind'    => 'HOST',
                'contact' => $p[0] ?? '',
                'host'    => $p[1] ?? '',
                'service' => '',
                'state'   => $p[2] ?? '',
                'method'  => $p[3] ?? '',
                'output'  => $p[4] ?? '',
            ];
        }
        if (strncmp($content, 'SERVICE NOTIFICATION: ', 22) === 0) {
            $p = explode(';', substr($content, 22), 6);
            return [
                'ts'      => $ts,
                'kind'    => 'SERVICE',
                'contact' => $p[0] ?? '',
                'host'    => $p[1] ?? '',
                'service' => $p[2] ?? '',
                'state'   => $p[3] ?? '',
                'method'  => $p[4] ?? '',
                'output'  => $p[5] ?? '',
            ];
        }
    }

    return null;
}

/* ── Shared formatting helpers ───────────────────────────────────────── */
function h($s)            { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_ago($ts)     {
    $d = time() - (int)$ts;
    if ($d < 60)    return $d . 's ago';
    if ($d < 3600)  return (int)($d/60) . 'm ago';
    if ($d < 86400) return (int)($d/3600) . 'h ago';
    return (int)($d/86400) . 'd ago';
}
function fmt_dur($secs) {
    $secs = max(0, (int)$secs);
    $d = (int)($secs/86400); $h = (int)(($secs%86400)/3600); $m = (int)(($secs%3600)/60);
    $p = [];
    if ($d) $p[] = $d.'d';
    if ($h) $p[] = $h.'h';
    $p[] = $m.'m';
    return implode(' ', $p);
}
function fmt_ts($ts) {
    if (!$ts) return '-';
    return date('Y-m-d H:i', (int)$ts);
}

/* Host state mapping (current_state from status.dat hoststatus) */
function host_state_info($block) {
    if (($block['has_been_checked'] ?? '0') === '0')
        return ['text'=>'PENDING', 'cls'=>'badge-pending', 'row'=>'', 'ord'=>3];
    switch ($block['current_state'] ?? '0') {
        case '0': return ['text'=>'UP',          'cls'=>'badge-up',      'row'=>'',          'ord'=>4];
        case '1': return ['text'=>'DOWN',        'cls'=>'badge-down',    'row'=>'r-down',    'ord'=>0];
        case '2': return ['text'=>'UNREACHABLE', 'cls'=>'badge-unreach', 'row'=>'r-unreach', 'ord'=>1];
    }
    return ['text'=>'UNKNOWN','cls'=>'badge-pending','row'=>'','ord'=>2];
}

/* Service state mapping */
function svc_state_info($block) {
    if (($block['has_been_checked'] ?? '0') === '0')
        return ['text'=>'PENDING', 'cls'=>'badge-pending', 'row'=>'', 'ord'=>4];
    switch ($block['current_state'] ?? '0') {
        case '0': return ['text'=>'OK',       'cls'=>'badge-ok',   'row'=>'',       'ord'=>5];
        case '1': return ['text'=>'WARNING',  'cls'=>'badge-warn', 'row'=>'r-warn', 'ord'=>1];
        case '2': return ['text'=>'CRITICAL', 'cls'=>'badge-crit', 'row'=>'r-crit', 'ord'=>0];
        case '3': return ['text'=>'UNKNOWN',  'cls'=>'badge-unkn', 'row'=>'r-unkn', 'ord'=>2];
    }
    return ['text'=>'UNKNOWN','cls'=>'badge-unkn','row'=>'r-unkn','ord'=>3];
}

/* State badge HTML */
function state_badge($info) {
    return '<span class="badge ' . $info['cls'] . '">' . $info['text'] . '</span>';
}

/* Expand Nagios macros in action_url / notes_url.
   Handles the macros that typically appear in graph tool URLs. */
function expand_url($url, $hostname, $service_desc = '') {
    if (!$url) return '';
    $url = str_replace('$HOSTNAME$',    rawurlencode($hostname),     $url);
    $url = str_replace('$SERVICEDESC$', rawurlencode($service_desc), $url);
    $url = str_replace('$HOSTADDRESS$', rawurlencode($hostname),     $url);
    return $url;
}

/* ── Raw log reader (all entry types) ───────────────────────────────── */
function nagios_parse_log_all($file, $limit = 1000) {
    if (!$file || !file_exists($file) || !is_readable($file)) {
        return ['ok'=>false, 'error'=>'Log file not readable: '.$file, 'entries'=>[]];
    }
    $entries = []; $fh = fopen($file, 'r');
    fseek($fh, 0, SEEK_END); $pos = ftell($fh); $buf = ''; $done = false;
    while (!$done && count($entries) < $limit) {
        $chunk = min($pos, 65536); if ($chunk === 0) { $done = true; break; }
        $pos -= $chunk; fseek($fh, $pos);
        $buf = fread($fh, $chunk) . $buf;
        $lines = explode("\n", $buf); $buf = array_shift($lines);
        foreach (array_reverse($lines) as $line) {
            if (count($entries) >= $limit) { $done = true; break; }
            $e = _classify_log_line(trim($line)); if ($e) $entries[] = $e;
        }
    }
    if (!$done && $buf !== '') { $e = _classify_log_line(trim($buf)); if ($e) $entries[] = $e; }
    fclose($fh);
    return ['ok'=>true, 'entries'=>$entries];
}
function _classify_log_line($line) {
    if ($line === '' || $line[0] !== '[') return null;
    $rbr = strpos($line, ']'); if ($rbr === false) return null;
    $ts  = (int)substr($line, 1, $rbr - 1);
    $msg = ltrim(substr($line, $rbr + 1)); if ($msg === '') return null;
    if      (strncmp($msg, 'HOST ALERT:', 11) === 0)             $type = 'host_alert';
    elseif  (strncmp($msg, 'SERVICE ALERT:', 14) === 0)           $type = 'svc_alert';
    elseif  (strncmp($msg, 'HOST NOTIFICATION:', 18) === 0)       $type = 'host_notif';
    elseif  (strncmp($msg, 'SERVICE NOTIFICATION:', 21) === 0)    $type = 'svc_notif';
    elseif  (strncmp($msg, 'HOST DOWNTIME ALERT:', 20) === 0 ||
             strncmp($msg, 'SERVICE DOWNTIME ALERT:', 23) === 0)  $type = 'downtime';
    elseif  (strncmp($msg, 'EXTERNAL COMMAND:', 17) === 0)        $type = 'external';
    elseif  (strpos($msg, 'starting') !== false || strpos($msg, 'shutting down') !== false ||
             strpos($msg, 'restarting') !== false ||
             (strpos($msg, 'Nagios') !== false && strpos($msg, 'Copyright') !== false)) $type = 'process';
    else    $type = 'other';
    return ['ts'=>$ts, 'type'=>$type, 'message'=>$msg];
}

/* ── Availability segment calculator ────────────────────────────────── */
/*
 * Takes a chronologically-sorted events array [{ts, state}, ...],
 * a window (window_start..now), and a fallback initial state.
 * Returns ['segs'=>[{state,from,to,secs}], 'pct'=>[state=>%], 'total'=>secs].
 */
function compute_avail_segs($events, $window_start, $now, $fallback = 'UNKNOWN') {
    $norm = function($s) {
        $s = strtoupper(trim($s));
        return ($s === 'RECOVERY') ? 'UP' : $s;
    };
    /* Find state just before the window */
    $initial = $fallback;
    foreach ($events as $ev) {
        if ($ev['ts'] <= $window_start) $initial = $norm($ev['state']);
    }
    $cur = $norm($initial); $cur_ts = $window_start; $segs = [];
    foreach ($events as $ev) {
        if ($ev['ts'] <= $window_start || $ev['ts'] > $now) continue;
        $s = $norm($ev['state']);
        if ($ev['ts'] > $cur_ts) $segs[] = ['state'=>$cur,'from'=>$cur_ts,'to'=>$ev['ts'],'secs'=>$ev['ts']-$cur_ts];
        $cur = $s; $cur_ts = $ev['ts'];
    }
    if ($now > $cur_ts) $segs[] = ['state'=>$cur,'from'=>$cur_ts,'to'=>$now,'secs'=>$now-$cur_ts];
    $total = max($now - $window_start, 1);
    $pct   = [];
    foreach ($segs as $sg) $pct[$sg['state']] = ($pct[$sg['state']] ?? 0) + $sg['secs'];
    foreach ($pct as $k => $v) $pct[$k] = round($v / $total * 100, 2);
    return ['segs'=>$segs, 'pct'=>$pct, 'total'=>$total];
}

/* ── objects.cache parser ────────────────────────────────────────────── */

/* Find the objects.cache file path from nagios.cfg. */
function nagios_find_objects_cache($main_cfg) {
    if ($main_cfg && file_exists($main_cfg)) {
        $fh = fopen($main_cfg, 'r');
        while (($line = fgets($fh)) !== false) {
            $line = ltrim(rtrim($line));
            if (strncmp($line, 'object_cache_file=', 18) === 0) {
                fclose($fh);
                return trim(substr($line, 18));
            }
        }
        fclose($fh);
    }
    return '/usr/local/nagios/var/objects.cache';
}

/*
 * Parse objects.cache and return a lookup map keyed by "hostname\0servicedesc"
 * (servicedesc is empty string for host-level action_url).
 * objects.cache uses whitespace (tab) as key–value separator inside define blocks.
 */
function nagios_load_action_urls($cache_file) {
    $map = [];
    if (!$cache_file || !file_exists($cache_file) || !is_readable($cache_file)) return $map;

    $btype = null;
    $block = [];

    $fh = fopen($cache_file, 'r');
    while (($line = fgets($fh)) !== false) {
        $t = ltrim(rtrim($line));
        if ($t === '' || $t[0] === '#') continue;

        if (preg_match('/^define\s+(\w+)\s*\{/', $t, $m)) {
            $btype = $m[1];
            $block = [];
            continue;
        }

        if ($t === '}') {
            if (!empty($block['action_url'])) {
                if ($btype === 'service') {
                    foreach (array_map('trim', explode(',', $block['host_name'] ?? '')) as $hn) {
                        if ($hn !== '')
                            $map[$hn . "\0" . ($block['service_description'] ?? '')] = $block['action_url'];
                    }
                } elseif ($btype === 'host') {
                    $map[($block['host_name'] ?? '') . "\0"] = $block['action_url'];
                }
            }
            $btype = null;
            $block = [];
            continue;
        }

        if ($btype === 'service' || $btype === 'host') {
            if (preg_match('/^(\S+)\s+(.+)$/', $t, $m))
                $block[trim($m[1])] = trim($m[2]);
        }
    }
    fclose($fh);
    return $map;
}

/**
 * Parse hostgroup and servicegroup definitions from objects.cache.
 * Returns ['ok'=>bool, 'hostgroups'=>[], 'servicegroups'=>[]]
 * servicegroup members are stored as alternating pairs: host,svc,host,svc,...
 */
function nagios_parse_groups($cache_file) {
    $hostgroups = [];
    $servicegroups = [];
    if (!$cache_file || !file_exists($cache_file) || !is_readable($cache_file)) {
        return ['ok'=>false, 'error'=>'Objects cache not readable: '.($cache_file ?: '(not configured)'),
                'hostgroups'=>[], 'servicegroups'=>[]];
    }
    $btype = null;
    $block = [];
    $fh = fopen($cache_file, 'r');
    while (($line = fgets($fh)) !== false) {
        $t = ltrim(rtrim($line));
        if ($t === '' || $t[0] === '#') continue;
        if (preg_match('/^define\s+(\w+)\s*\{/', $t, $m)) {
            $btype = $m[1]; $block = []; continue;
        }
        if ($t === '}') {
            if ($btype === 'hostgroup'    && !empty($block['hostgroup_name']))    $hostgroups[]    = $block;
            if ($btype === 'servicegroup' && !empty($block['servicegroup_name'])) $servicegroups[] = $block;
            $btype = null; $block = []; continue;
        }
        if ($btype === 'hostgroup' || $btype === 'servicegroup') {
            if (preg_match('/^(\S+)\s+(.+)$/', $t, $m))
                $block[trim($m[1])] = trim($m[2]);
        }
    }
    fclose($fh);
    return ['ok'=>true, 'hostgroups'=>$hostgroups, 'servicegroups'=>$servicegroups];
}

/**
 * Parse host definitions from objects.cache.
 * Returns ['ok'=>bool, 'hosts'=>[hostname => ['alias','address','parents','action_url']]]
 * 'parents' is an array of parent hostnames (empty array = root host).
 */
function nagios_parse_host_objects($cache_file) {
    $hosts = [];
    if (!$cache_file || !file_exists($cache_file) || !is_readable($cache_file)) {
        return ['ok'=>false, 'error'=>'Objects cache not readable: '.($cache_file ?: '(not configured)'), 'hosts'=>[]];
    }
    $btype = null; $block = [];
    $fh = fopen($cache_file, 'r');
    while (($line = fgets($fh)) !== false) {
        $t = ltrim(rtrim($line));
        if ($t === '' || $t[0] === '#') continue;
        if (preg_match('/^define\s+(\w+)\s*\{/', $t, $m)) { $btype = $m[1]; $block = []; continue; }
        if ($t === '}') {
            if ($btype === 'host' && !empty($block['host_name'])) {
                $praw = isset($block['parents']) ? array_map('trim', explode(',', $block['parents'])) : [];
                $hosts[$block['host_name']] = [
                    'alias'      => $block['alias']      ?? $block['host_name'],
                    'address'    => $block['address']    ?? '',
                    'parents'    => array_values(array_filter($praw)),
                    'action_url' => $block['action_url'] ?? '',
                ];
            }
            $btype = null; $block = []; continue;
        }
        if ($btype === 'host') {
            if (preg_match('/^(\S+)\s+(.+)$/', $t, $m))
                $block[trim($m[1])] = trim($m[2]);
        }
    }
    fclose($fh);
    return ['ok'=>true, 'hosts'=>$hosts];
}

/**
 * Parse hostcomment, servicecomment, hostdowntime, servicedowntime blocks
 * from status.dat (or retention.dat). Returns:
 *   ['ok'=>bool, 'comments'=>[...], 'downtimes'=>[...]]
 * Each item has 'kind' = the block type name.
 */
function nagios_parse_comments_downtime($file) {
    $comments = []; $downtimes = [];
    if (!$file || !file_exists($file) || !is_readable($file)) {
        return ['ok'=>false, 'error'=>'Status file not readable: '.($file ?: '(not configured)'),
                'comments'=>[], 'downtimes'=>[]];
    }
    $btype = null; $block = [];
    $fh = fopen($file, 'r');
    while (($line = fgets($fh)) !== false) {
        $t = ltrim(rtrim($line));
        if ($t === '' || $t[0] === '#') continue;
        if (preg_match('/^(\w+)\s*\{/', $t, $m)) { $btype = $m[1]; $block = []; continue; }
        if ($t === '}') {
            if ($btype === 'hostcomment' || $btype === 'servicecomment')
                $comments[]  = $block + ['kind'=>$btype];
            if ($btype === 'hostdowntime' || $btype === 'servicedowntime')
                $downtimes[] = $block + ['kind'=>$btype];
            $btype = null; $block = []; continue;
        }
        if ($btype) {
            $eq = strpos($t, '=');
            if ($eq !== false) $block[substr($t,0,$eq)] = substr($t,$eq+1);
        }
    }
    fclose($fh);
    return ['ok'=>true, 'comments'=>$comments, 'downtimes'=>$downtimes];
}

/**
 * Parse all define blocks from objects.cache.
 * Returns ['ok'=>bool, 'data'=>['hosts'=>[], 'services'=>[], 'contacts'=>[],
 *          'contactgroups'=>[], 'hostgroups'=>[], 'servicegroups'=>[],
 *          'timeperiods'=>[], 'commands'=>[]]]
 */
function nagios_parse_objects_config($cache_file) {
    $result = [
        'hosts'=>[], 'services'=>[], 'hostgroups'=>[], 'servicegroups'=>[],
        'contacts'=>[], 'contactgroups'=>[], 'timeperiods'=>[], 'commands'=>[]
    ];
    if (!$cache_file || !file_exists($cache_file) || !is_readable($cache_file)) {
        return ['ok'=>false, 'error'=>'Objects cache not readable: '.($cache_file ?: '(not configured)'), 'data'=>$result];
    }
    $btype = null; $block = [];
    $fh = fopen($cache_file, 'r');
    while (($line = fgets($fh)) !== false) {
        $t = ltrim(rtrim($line));
        if ($t === '' || $t[0] === '#') continue;
        if (preg_match('/^define\s+(\w+)\s*\{/', $t, $m)) { $btype = $m[1]; $block = []; continue; }
        if ($t === '}') {
            switch ($btype) {
                case 'host':         if (!empty($block['host_name']))           $result['hosts'][]         = $block; break;
                case 'service':      if (!empty($block['service_description'])) $result['services'][]      = $block; break;
                case 'hostgroup':    if (!empty($block['hostgroup_name']))      $result['hostgroups'][]    = $block; break;
                case 'servicegroup': if (!empty($block['servicegroup_name']))   $result['servicegroups'][] = $block; break;
                case 'contact':      if (!empty($block['contact_name']))        $result['contacts'][]      = $block; break;
                case 'contactgroup': if (!empty($block['contactgroup_name']))   $result['contactgroups'][] = $block; break;
                case 'timeperiod':   if (!empty($block['timeperiod_name']))     $result['timeperiods'][]   = $block; break;
                case 'command':      if (!empty($block['command_name']))        $result['commands'][]      = $block; break;
            }
            $btype = null; $block = []; continue;
        }
        if ($btype) {
            if (preg_match('/^(\S+)\s+(.*)$/', $t, $m))
                $block[trim($m[1])] = trim($m[2]);
        }
    }
    fclose($fh);
    usort($result['hosts'],    function($a,$b){ return strcmp($a['host_name']??'',$b['host_name']??''); });
    usort($result['services'], function($a,$b){
        $c = strcmp($a['host_name']??'',$b['host_name']??'');
        return $c ?: strcmp($a['service_description']??'',$b['service_description']??'');
    });
    usort($result['contacts'], function($a,$b){ return strcmp($a['contact_name']??'',$b['contact_name']??''); });
    usort($result['commands'], function($a,$b){ return strcmp($a['command_name']??'',$b['command_name']??''); });
    return ['ok'=>true, 'data'=>$result];
}

/* Look up action_url for a service/host: tries status.dat value first,
   falls back to objects.cache map. */
function get_action_url($block, $aurl_map, $hostname, $service_desc = '') {
    $raw = trim($block['action_url'] ?? '');
    if ($raw === '' && $aurl_map !== null) {
        $key = $hostname . "\0" . $service_desc;
        $raw = $aurl_map[$key] ?? '';
    }
    return expand_url($raw, $hostname, $service_desc);
}
