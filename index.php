<?php

// To change these values, create a file called config.php and copy/paste them there.
$server_name = "Server";
$server_desc = "";
$color_bg = "#222";
$color_name = "#fff";
$color_text = "#ccc";
$custom_css = "";

if(is_file("config.php")) {
	include "config.php";
}

// Detect Windows systems
$windows = defined('PHP_WINDOWS_VERSION_MAJOR');

// Get system status
if($windows) {

	// Uptime parsing was a mess...
	$uptime = 'Error';

	// Assuming C: as the system drive
	$disk_stats = explode(' ',trim(preg_replace('/\s+/',' ',preg_replace('/[^0-9 ]+/','',`fsutil volume diskfree c:`))));
	$disk = round($disk_stats[0] / $disk_stats[1] * 100);

	$disk_total = '';
	$disk_used = '';

	// Memory checking is slow on Windows, will only set over AJAX to allow page to load faster
	$memory = 0;

} else {

	$initial_uptime = shell_exec("cut -d. -f1 /proc/uptime");
	$days = floor($initial_uptime / 60 / 60 / 24);
	$hours = $initial_uptime / 60 / 60 % 24;
	$mins = $initial_uptime / 60 % 60;
	$secs = $initial_uptime % 60;

	if($days > "0") {
		$uptime = $days . "d " . $hours . "h";
	} elseif ($days == "0" && $hours > "0") {
		$uptime = $hours . "h " . $mins . "m";
	} elseif ($hours == "0" && $mins > "0") {
		$uptime = $mins . "m " . $secs . "s";
	} elseif ($mins < "0") {
		$uptime = $secs . "s";
	} else {
		$uptime = "Error retreving uptime.";
	}

	// Check disk stats
	$disk_result = `df -k | grep /dev/[sv]da`;
	if(!trim($disk_result)) {
		$disk_result = `df -k | grep /dev/simfs`;
	}
	$disk_result = explode(" ", preg_replace("/\s+/", " ", $disk_result));

	$disk_total = $disk_result[1];
	$disk_used = $disk_result[2];
	$disk = intval(rtrim($disk_result[4], "%"));

	// Check current RAM usage
	$mem_result = `free -mo | grep Mem`;
	$mem_result = explode(" ", preg_replace("/\s+/", " ", $mem_result));
	$mem_total = intval($mem_result[1]);
	$mem_used = $mem_total - $mem_result[3];
	$memory = round($mem_used / $mem_total * 100);
}

if(!empty($_GET['json'])) {

	// Determine number of CPUs
	$num_cpus = 1;
	if (is_file('/proc/cpuinfo')) {
		$cpuinfo = file_get_contents('/proc/cpuinfo');
		preg_match_all('/^processor/m', $cpuinfo, $matches);
		$num_cpus = count($matches[0]);
	} else if ('WIN' == strtoupper(substr(PHP_OS, 0, 3))) {
		$process = @popen('wmic cpu get NumberOfCores', 'rb');
		if (false !== $process) {
			fgets($process);
			$num_cpus = intval(fgets($process));
			pclose($process);
		}
	} else {
		$process = @popen('sysctl -a', 'rb');
		if (false !== $process) {
			$output = stream_get_contents($process);
			preg_match('/hw.ncpu: (\d+)/', $output, $matches);
			if ($matches) {
				$num_cpus = intval($matches[1][0]);
			}
			pclose($process);
		}
	}

	if($windows) {

		// Get stats for Windows
		$cpu = intval(trim(preg_replace('/[^0-9]+/','',`wmic cpu get loadpercentage`)));
		$memory_stats = explode(' ',trim(preg_replace('/\s+/',' ',preg_replace('/[^0-9 ]+/','',`systeminfo | findstr Memory`))));
		$memory = round($memory_stats[4] / $memory_stats[0] * 100);

	} else {

		// Get stats for linux using simplest possible methods
		if(function_exists("sys_getloadavg")) {
			$load = sys_getloadavg();
			$cpu = $load[0] * 100 / $num_cpus;
		} elseif(`which uptime`) {
			$str = substr(strrchr(`uptime`,":"),1);
			$avs = array_map("trim",explode(",",$str));
			$cpu = $avs[0] * 100 / $num_cpus;
		} elseif(`which mpstat`) {
			$cpu = 100 - round(`mpstat 1 2 | tail -n 1 | sed 's/.*\([0-9\.+]\{5\}\)$/\\1/'`);
		} elseif(is_file('/proc/loadavg')) {
			$cpu = 0;
			$output = `cat /proc/loadavg`;
			$cpu = substr($output,0,strpos($output," "));
		} else {
			$cpu = 0;
		}

	}

	header("Content-type: application/json");
	exit(json_encode(array(
		'uptime' => $uptime,
		'disk' => $disk,
		'disk_total' => $disk_total,
		'disk_used' => $disk_used,
		'cpu' => $cpu,
		'num_cpus' => $num_cpus,
		'memory' => $memory,
		'memory_total' => $mem_total,
		'memory_used' => $mem_used,
	)));
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo $server_name; ?></title>
<style type="text/css">
html {
	height: 100%;
}
html, body {
	margin: 0;
	padding: 0;
}
body {
	position: absolute;
	top: 50%;
	width: 100%;
	margin: -4em 0 0 0;
	font-family: "Segoe UI Light",'HelveticaNeue-UltraLight','Helvetica Neue UltraLight','Helvetica Neue',"Open Sans","Segoe UI","Tahoma","Verdana","Arial",sans-serif;
	font-weight: 300;
	background: <?php echo $color_bg; ?>
}
h1, p {
	padding-left: 15%;
}
h1 {
	color: <?php echo $color_name; ?>;
	font-weight: 100;
	font-size: 4em;
	margin: 0;
}
p {
	color: <?php echo $color_text; ?>;
	font-size: 2em;
	margin: 0;
}
footer {
	font-family: "Segoe UI",'Helvetica Neue',"Open Sans","Tahoma","Verdana","Arial",sans-serif;
	position: absolute;
	position: fixed;
	line-height: 40px;
	bottom: 2em;
	left: 15%;
	color: <?php echo $color_text; ?>;
}

/* Yes, this is a hack. */
footer canvas {
	vertical-align: middle;
}
footer canvas + input {
	margin-top: 16px !important;
	font-size: 12px !important;
}

/* Begin: Custom CSS */
<?php echo $custom_css; ?>
/* End: Custom CSS */
</style>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="jqknob.js"></script>
<script>
function update() {
	$.post('<?php echo basename(__FILE__); ?>?json=1', function(data) {

		$('#uptime').text(data.uptime);
		$('#k-cpu').val(data.cpu).trigger("change");
		$('#k-memory').val(data.memory).trigger("change");

		window.setTimeout(update, 1000);

	},'json');
}
$(document).ready(function() {
	update();
	$("#k-disk, #k-memory, #k-cpu").knob({
		readOnly: true,
		width: 40,
		height: 40,
		thickness: 0.2,
		fontWeight: 'normal',
		bgColor: 'rgba(127,127,127,0.15)', // 50% grey with a low opacity, should work with most backgrounds
		fgColor: '<?php echo $color_text; ?>'
	});
});
</script>
</head>
<body>
<h1><?php echo $server_name; ?></h1>
<p><?php echo $server_desc; ?></p>
<footer>
	<?php if(!$windows && !empty($uptime)) { ?>
		Uptime: <span id="uptime"><?php echo $uptime; ?></span>&emsp;
	<?php } ?>
	Disk usage: <input id="k-disk" value="<?php echo $disk; ?>">&emsp;
	Memory: <input id="k-memory" value="<?php echo $memory; ?>">&emsp;
	CPU: <input id="k-cpu" value="0">
</footer>
</body>
</html>
