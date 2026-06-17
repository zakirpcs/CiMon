<?php
include_once(dirname(__FILE__).'/config.inc.php');
$link_target = 'main';

function ico($d) {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>Navigation</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="robots" content="noindex, nofollow" />
	<link rel="stylesheet" type="text/css" href="stylesheets/interface/menu.css" />
</head>
<body>
<div id="menu">
	<div class="nav-scroll">

		<h2>Monitoring</h2>
		<ul>
			<li><a href="main.php" target="<?php echo $link_target;?>"><?php echo ico('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'); ?>Tactical Overview</a></li>
			<li><a href="hosts.php" target="<?php echo $link_target;?>"><?php echo ico('<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>'); ?>Hosts</a></li>
			<li><a href="services.php" target="<?php echo $link_target;?>"><?php echo ico('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'); ?>Services</a></li>
			<li><a href="hostgroups.php" target="<?php echo $link_target;?>"><?php echo ico('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'); ?>Host Groups</a></li>
			<li><a href="servicegroups.php" target="<?php echo $link_target;?>"><?php echo ico('<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>'); ?>Service Groups</a></li>
			<li><a href="hostproblems.php" target="<?php echo $link_target;?>"><?php echo ico('<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'); ?>Host Problems</a></li>
			<li><a href="serviceproblems.php" target="<?php echo $link_target;?>"><?php echo ico('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'); ?>Service Problems</a></li>
			<li><a href="map.php" target="<?php echo $link_target;?>"><?php echo ico('<circle cx="12" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><line x1="12" y1="8" x2="5" y2="16"/><line x1="12" y1="8" x2="19" y2="16"/>'); ?>Map</a></li>
			<li><a href="networkoutages.php" target="<?php echo $link_target;?>"><?php echo ico('<line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.56 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>'); ?>Network Outages</a></li>
		</ul>

		<h2>Reports</h2>
		<ul>
			<li><a href="availability.php" target="<?php echo $link_target;?>"><?php echo ico('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'); ?>Availability</a></li>
			<li><a href="trends.php" target="<?php echo $link_target;?>"><?php echo ico('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'); ?>Trends</a></li>
			<li><a href="history.php?host=all" target="<?php echo $link_target;?>"><?php echo ico('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'); ?>Alert History</a></li>
			<li><a href="alertsummary.php" target="<?php echo $link_target;?>"><?php echo ico('<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>'); ?>Alert Summary</a></li>
			<li><a href="alerthistogram.php" target="<?php echo $link_target;?>"><?php echo ico('<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'); ?>Alert Histogram</a></li>
			<li><a href="notifications.php?host=all" target="<?php echo $link_target;?>"><?php echo ico('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'); ?>Notifications</a></li>
			<li><a href="eventlog.php" target="<?php echo $link_target;?>"><?php echo ico('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'); ?>Event Log</a></li>
		</ul>

		<h2>System</h2>
		<ul>
			<li><a href="comments.php" target="<?php echo $link_target;?>"><?php echo ico('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'); ?>Comments</a></li>
			<li><a href="downtime.php" target="<?php echo $link_target;?>"><?php echo ico('<circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/>'); ?>Downtime</a></li>
			<li><a href="processinfo.php" target="<?php echo $link_target;?>"><?php echo ico('<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>'); ?>Process Info</a></li>
			<li><a href="performanceinfo.php" target="<?php echo $link_target;?>"><?php echo ico('<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'); ?>Performance Info</a></li>
			<li><a href="schedulingqueue.php" target="<?php echo $link_target;?>"><?php echo ico('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'); ?>Scheduling Queue</a></li>
			<li><a href="configuration.php" target="<?php echo $link_target;?>"><?php echo ico('<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>'); ?>Configuration</a></li>
		</ul>

	</div><!-- /.nav-scroll -->

	<div class="menu-footer">
		<div class="menu-footer-dot"></div>
		<span class="menu-footer-ver">CiMon v4.5.11</span>
	</div>
</div><!-- /#menu -->

<script type="text/javascript">
(function() {
	/* ── Section collapse with chevron ── */
	var heads = document.querySelectorAll('#menu h2');
	for (var i = 0; i < heads.length; i++) {
		heads[i].addEventListener('click', (function(h) {
			return function() {
				var ul = h.nextElementSibling;
				if (!ul || ul.tagName !== 'UL') return;
				var open = ul.style.display !== 'none';
				ul.style.display = open ? 'none' : '';
				h.classList.toggle('collapsed', open);
			};
		})(heads[i]));
	}

	/* ── Active link detection on load ── */
	var allLinks = document.querySelectorAll('#menu ul li a');
	function setActive(href) {
		for (var i = 0; i < allLinks.length; i++) {
			var rel = allLinks[i].getAttribute('href');
			allLinks[i].classList.toggle('nav-active', !!(rel && href.indexOf(rel.split('?')[0]) !== -1));
		}
	}
	try {
		setActive(window.parent.frames['main'].location.href);
	} catch(e) {
		/* cross-origin or not yet loaded — default to serviceproblems */
		setActive('serviceproblems.php');
	}

	/* ── Update active on click ── */
	for (var k = 0; k < allLinks.length; k++) {
		allLinks[k].addEventListener('click', (function(link) {
			return function() {
				for (var l = 0; l < allLinks.length; l++) {
					allLinks[l].classList.remove('nav-active');
				}
				link.classList.add('nav-active');
			};
		})(allLinks[k]));
	}
})();
</script>
</body>
</html>
