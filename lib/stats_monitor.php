<?php

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");

$min_idle = 20;

$vmstat = `ps aux | grep "vmstat 1" | grep -v grep`;
if (!$vmstat)
	proc_close(proc_open("vmstat 1 > vmstat.pipe &", array(), $foo));

$counter=5;
$block=0;

$file = fopen("vmstat.pipe", "r");
while ($line = fgets($file)) {
	$stats = preg_split("/\s+/", trim($line));
	if (count($stats) < 15) continue;
	$cpuidle = $stats[14];
	
	if ($cpuidle == "" || $cpuidle == "id ") continue;
	if ($cpuidle < $min_idle) $block++; else $block=0;
	
	$loadavg = file_get_contents("/proc/loadavg");
	$loadavg = substr($loadavg, 0, strpos($loadavg, " "));

	foreach(file("/proc/meminfo") as $memdata) {
		if (strpos($memdata, "MemTotal") === 0)
			$memtotal = substr($memdata, 17, 8);
		if (strpos($memdata, "MemFree") === 0)
			$memfree = substr($memdata, 17, 8);
		if (strpos($memdata, "Buffers") === 0)
			$membuf = substr($memdata, 17, 8);
		if (strpos($memdata, "Cached") === 0)
			$memcach = substr($memdata, 17, 8);
		if (strpos($memdata, "SwapTotal") === 0)
			$memswaptotal = substr($memdata, 17, 8);
		if (strpos($memdata, "SwapFree") === 0)
			$memswapfree = substr($memdata, 17, 8);
	}
	
	$memreal = $memtotal - $memfree - $membuf - $memcach + $memswaptotal - $memswapfree;

	// Do these every 5 seconds
	if ($counter == 5) {
		$counter = 0;
	
		eval(file_get_contents($conf_base_path . "/users"));
		$count_users = 0;
		foreach($users as $user) 
			if ($user["status"] == "active") $count_users++;

		// stats[3] : number of really active users

		$processes = 0;
		foreach (ps_ax("localhost") as $process) {
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
