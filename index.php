<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>CiMon &mdash; Central Infrastructure Monitoring</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta http-equiv="Content-Language" content="en" />
	<meta name="robots" content="noindex, nofollow" />
	<link rel="shortcut icon" type="image/x-icon" href="images/favicon.ico" />
	<script type="text/javascript">
	window.addEventListener('load', function() {
		var allFrames = document.querySelectorAll('frame');
		for (var i = 0; i < allFrames.length; i++) {
			if (allFrames[i].name === 'main') {
				allFrames[i].addEventListener('load', function() {
					try { window.frames['top'].updateLastUpdated(); } catch(e) {}
				});
				break;
			}
		}
	});
	</script>
</head>
	<frameset rows="60,*" frameborder="0" framespacing="0">
		<frame src="top.php" name="top" />
		<frameset cols="200,*" frameborder="0" framespacing="0">
			<frame src="menu.php" name="side" target="main" noresize="noresize" />
			<frame src="main.php" name="main" noresize="noresize" />
		</frameset>
		<noframes>
			<body>
				<p>These pages require a browser which supports frames.</p>
			</body>
		</noframes>
	</frameset>
</html>
