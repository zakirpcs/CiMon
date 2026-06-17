<?php
$username = '';
if (!empty($_SERVER['PHP_AUTH_USER']))      $username = htmlspecialchars($_SERVER['PHP_AUTH_USER']);
elseif (!empty($_SERVER['REMOTE_USER']))    $username = htmlspecialchars($_SERVER['REMOTE_USER']);
else                                        $username = 'nagiosadmin';
?><!DOCTYPE html>
<html lang="en">
<head>
	<title>CiMon</title>
	<meta charset="UTF-8" />
	<meta name="robots" content="noindex, nofollow" />
	<style>
	@font-face {
		font-family: 'Geist';
		src: url('fonts/Geist[wght].woff2') format('woff2');
		font-weight: 100 900;
		font-style: normal;
		font-display: swap;
	}
	html, body {
		height: 60px;
		margin: 0; padding: 0;
		overflow: hidden;
		font-family: 'Geist', system-ui, -apple-system, sans-serif;
	}
	body {
		background: #FFFFFF;
		box-shadow: 0 1px 4px rgba(0,0,0,0.06);
	}

	/* ── Layout ── */
	.topbar {
		height: 58px;
		border-bottom: 2px solid #e1e1e1;
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 20px;
	}
	.tb-left  { display: flex; align-items: center; gap: 14px; }
	.tb-right { display: flex; align-items: center; gap: 10px; }

	/* ── Brand ── */
	.brand-link {
		display: flex;
		align-items: center;
		gap: 10px;
		text-decoration: none;
	}
	.brand-icon {
		width: 28px; height: 28px;
		background: linear-gradient(135deg, #0891B2, #06B6D4);
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 2px 8px rgba(8,145,178,0.30);
		flex-shrink: 0;
	}
	.brand-icon svg { width: 14px; height: 14px; stroke: #fff; }
	.brand-name {
		font-size: 1rem;
		font-weight: 700;
		letter-spacing: -0.02em;
		color: #111827;
		white-space: nowrap;
	}
	.brand-name span { color: #0891B2; }

	.brand-divider {
		width: 1px; height: 22px;
		background: linear-gradient(to bottom, transparent, rgba(8,145,178,0.22), transparent);
		flex-shrink: 0;
	}
	.brand-tag {
		font-size: 0.66rem;
		font-weight: 600;
		letter-spacing: 0.11em;
		text-transform: uppercase;
		color: #9CA3AF;
		white-space: nowrap;
	}

	/* ── Live clock chip ── */
	.lut-chip {
		display: flex;
		align-items: center;
		gap: 7px;
		padding: 4px 12px;
		background: rgba(0,0,0,0.04);
		border: 1px solid rgba(0,0,0,0.08);
		border-radius: 8px;
	}
	.lut-dot {
		width: 5px; height: 5px;
		border-radius: 50%;
		background: #16a34a;
		box-shadow: 0 0 6px rgba(22,163,74,0.6);
		flex-shrink: 0;
		animation: lut-pulse 2.4s ease-in-out infinite;
	}
	@keyframes lut-pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
	.lut-label {
		font-size: 0.66rem;
		font-weight: 700;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		color: #9CA3AF;
		white-space: nowrap;
	}
	.lut-value {
		font-size: 0.78rem;
		font-weight: 700;
		color: #374151;
		font-variant-numeric: tabular-nums;
		letter-spacing: 0.03em;
		white-space: nowrap;
	}

	/* ── Focus states ── */
	.brand-link:focus-visible {
		outline: 2px solid rgba(8,145,178,0.5);
		outline-offset: 4px;
		border-radius: 6px;
	}

	/* ── Prefers-reduced-motion ── */
	@media (prefers-reduced-motion: reduce) {
		@keyframes lut-pulse { 0%, 100% { opacity: 1; } }
		.lut-dot { animation: none; }
	}

	.meta-sep {
		width: 1px; height: 20px;
		background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.10), transparent);
		flex-shrink: 0;
	}

	/* ── User pill ── */
	.user-pill {
		display: flex;
		align-items: center;
		gap: 7px;
		padding: 5px 13px;
		background: rgba(8,145,178,0.07);
		border: 1px solid rgba(8,145,178,0.22);
		border-radius: 8px;
		cursor: default;
		transition: background 0.15s, border-color 0.15s;
	}
	.user-pill:hover {
		background: rgba(8,145,178,0.12);
		border-color: rgba(8,145,178,0.38);
	}
	.user-pill svg { width: 13px; height: 13px; stroke: #0891B2; opacity: 0.8; flex-shrink: 0; }
	.user-name {
		font-size: 0.73rem;
		font-weight: 600;
		color: #0891B2;
		letter-spacing: 0.02em;
		white-space: nowrap;
	}
	</style>
</head>
<body>
<div class="topbar">

	<!-- Left: Brand -->
	<div class="tb-left">
		<a href="/nagios/" class="brand-link">
			<div class="brand-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="2" y="3" width="20" height="7" rx="1.5"/>
					<circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/>
					<rect x="2" y="14" width="20" height="7" rx="1.5"/>
					<polyline points="5 17.5 7 17.5 8.5 14.5 10.5 20.5 12.5 14.5 14 17.5 19 17.5"/>
				</svg>
			</div>
			<span class="brand-name">Ci<span>Mon</span></span>
		</a>
		<div class="brand-divider" aria-hidden="true"></div>
		<span class="brand-tag">Central Infrastructure Monitoring</span>
	</div>

	<!-- Right: Live clock + User -->
	<div class="tb-right">

		<div class="lut-chip" aria-live="off">
			<div class="lut-dot" aria-hidden="true"></div>
			<span class="lut-label">Time</span>
			<time class="lut-value" id="lut-time" aria-label="Current time"></time>
		</div>

		<div class="meta-sep" aria-hidden="true"></div>

		<div class="user-pill">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
				<circle cx="12" cy="7" r="4"/>
			</svg>
			<span class="user-name"><?php echo $username; ?></span>
		</div>

	</div>
</div>
<script>
(function() {
	var el = document.getElementById('lut-time');
	function tick() {
		var n = new Date();
		var p = function(v){ return String(v).padStart(2,'0'); };
		var t = p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());
		el.textContent = t;
		el.setAttribute('datetime', n.toISOString());
	}
	tick();
	setInterval(tick, 1000);

	/* Called by index.html when main frame navigates — kept for compatibility */
	window.updateLastUpdated = tick;
})();
</script>
</body>
</html>
