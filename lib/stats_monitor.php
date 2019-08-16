<?php

// =========================================
// STATS_MONITOR.PHP
// C9@ETF project (c) 2015-2018
//
// Background process that monitors various system statistics
// =========================================



require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");

$min_idle = 20;

$vmstat = `ps aux | grep "vmstat 1" | grep -v grep`;
if (!$vmstat)
	proc_close(proc_open("vmstat 1 > $conf_base_path/lib/vmstat.pipe &", array(), $foo));

$counter=5;
$block=0;

$file = fopen("$conf_base_path/lib/vmstat.pipe", "r");
while ($line = fgets($file)) {
	$stats = preg_split("/\s+/", trim($line));
	if (count($stats) < 15) continue;
	$cpuidle = $stats[14];
	
	if ($cpuidle == "" || $cpuidle == "id ") continue;
	if ($cpuidle < $min_idle) $block++; else $block=0;
	
	$loadavg = file_get_contents("/proc/loadavg");
	$loadavg = substr($loadavg, 0, strpos($loadavg, " "));

	$memtotal = $memfree = $memavail = $membuf = $memcach = $memswaptotal = $memswapfree = $memslab = 0;
	foreach(file("/proc/meminfo") as $memdata) {
		if (strpos($memdata, "MemTotal") === 0)
			$memtotal = intval(substr($memdata, 17, 8));
		if (strpos($memdata, "MemFree") === 0)
			$memfree = intval(substr($memdata, 17, 8));
		if (strpos($memdata, "MemAvailable") === 0)
			$memavail = intval(substr($memdata, 17, 8));
		if (strpos($memdata, "Buffers") === 0)
			$membuf = intval(substr($memdata, 17, 8));
		if (strpos($memdata, "Cached") === 0)
			$memcach = intval(substr($memdata, 17, 8));
		if (strpos($memdata, "Slab") === 0)
			$memslab = intval(substr($memdata, 17, 8));
		if (strpos($memdata, "SwapTotal") === 0)
			$memswaptotal = intval(substr($memdata, 17, 8));
		if (strpos($memdata, "SwapFree") === 0)
			$memswapfree = intval(substr($memdata, 17, 8));
	}
	
	$memreal = $memtotal - $memfree - $membuf - $memcach - $memslab + $memswaptotal - $memswapfree;

	// Do these every 5 seconds
	if ($counter == 5) {
		$counter = 0;
	
		eval(file_get_contents($conf_base_path . "/users"));
		$count_users = 0;
		foreach($users as $user) 
			if ($user["status"] == "active") $count_users++;

		// stats[3] : number of really active users

		$processes = 0;
		foreach (ps_ax("") as $process) {
			if (strstr($process['cmd'], "node server") || strstr($process['cmd'], "nodejs server"))
				$processes++;
		}
	
		$free_space = 0;
		foreach(explode("\n", shell_exec("df")) as $line) {
			$parts = preg_split("/\s+/", $line);
			if (count($parts) < 6) continue;
			if ($parts[5] == "/" || starts_with($parts[5], $conf_home_path) || starts_with($parts[5], $conf_base_path) || starts_with($parts[5], $conf_svn_path))
				if ($free_space == 0 || $parts[3] < $free_space)
					$free_space = $parts[3];
		}
		
		$free_inodes = 0;
		foreach(explode("\n", shell_exec("df -i")) as $line) {
			$parts = preg_split("/\s+/", $line);
			if (count($parts) < 6) continue;
			if ($parts[5] == "/" || starts_with($parts[5], $conf_home_path) || starts_with($parts[5], $conf_base_path) || starts_with($parts[5], $conf_svn_path))
				if ($free_inodes == 0 || $parts[3] < $free_inodes)
					$free_inodes = $parts[3];
		}
	}
	
	$counter++;
	
	print intval($cpuidle)." $block $loadavg $memreal $count_users $processes $free_space $free_inodes ".time()."\n";
}
fclose($file);

?>
