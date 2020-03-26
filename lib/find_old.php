<?php

// =========================================
// FIND_OLD.PHP
// C9@ETF project (c) 2015-2020
// 
// List users that havent logged in for a very long time
// =========================================


require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


$limit_disk_usage = 10000;

$check_path = "";
if ($argc == 2 && $argv[1] == "lhome") { 
	$check_path = "/lhome";
}

$usage_file = file($conf_home_path . "/webide/usage_stats.txt");
$usages = [];
foreach($usage_file as $usage) {
	$usage = preg_replace("/\s+/", " ", $usage);
	$stuff = explode(" ", $usage);
	if (count($stuff)<2) continue;
	if (!is_numeric($stuff[1])) continue;
	$usages[$stuff[0]] = $stuff[1];
}


$files = array_reverse(explode("\n", `ls -t $conf_home_path/last`));
foreach($files as $file) {
	$username = substr($file,0,strlen($file)-5);
	$path = $conf_home_path . "/" . $username[0] . "/$username";
	if ($check_path != "")
		$path = $check_path . "/" . $username[0] . "/$username";
	if (!$username) continue;
	if (!file_exists($path))
		print "Doesn't exist $username\n";
	else {
		if ($check_path != "") print "Exists $username\n";
		else
		if (array_key_exists($username, $usages)) {
			if ($usages[$username] > $limit_disk_usage) {
				$stuff = preg_split("/\s+/", `ls -lh $conf_home_path/last/$username.last`);
				$date = "$stuff[4] $stuff[5] $stuff[6] $stuff[7]";
				print "User: '$username' usage ".$usages[$username]." ($date)\n";
			}
		}
		else {
		//	$usage = explode("\t", `du -s $path`);
		//	if ($usage[0] > $limit_disk_usage)
		//		print "Unknown usage $username ($usage[0])\n";
		}
	}
}

?>
