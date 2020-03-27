<?php

// =========================================
// CHECK_STATS.PHP
// C9@ETF project (c) 2015-2020
// 
// Check all .stats files for health issues
// =========================================



require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


// If parameter is passed, load stats file incrementally
if (count($argv) > 1) {
	load_incrementally($argv[1]);
	exit(0);
}


// Get users from "users" file
$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$total = count($users);
$i = 0;

foreach ($users as $username => $data) {
	$i++;
	print "$username ($i/$total): ";
	read_stats($username);
	print count($stats['global_events']) . " " . $stats['last_update_rev'] . "\n";
}

exit(0);



// Functions

// Read stats file
function read_stats($username) {
	global $stats, $conf_stats_path;

	$username_efn = escape_filename($username);
	$stat_file = $conf_stats_path . "/" . "$username_efn.stats";
	
	$stats = NULL;
	if (file_exists($stat_file))
		eval(file_get_contents($stat_file));
	if ($stats == NULL) {
		$stats = array(
			"global_events" => array(),
			"last_update_rev" => 0
		);
	}
	// Stats file can reference other files to be included
	foreach ($stats as $key => $value) {
		if (is_array($value) && array_key_exists("goto", $value)) {
			$goto_path = $conf_stats_path . "/" . $value['goto'];
			if (file_exists($goto_path)) {
				eval(file_get_contents($goto_path));
				foreach($stats_goto as $ks => $vs)
					$stats[$ks] = $vs;
			}
			$stats_goto = null;
		}
	}
}

function load_incrementally($username) {
	global $stats, $conf_stats_path, $was_goto;

	$username_efn = escape_filename($username);
	$stat_file = $conf_stats_path . "/" . "$username_efn.stats";
	
	$stats = NULL;
	$was_goto = [];
	load_incrementally_file($stat_file);
}

function load_incrementally_file($filename) {
	global $stats, $conf_stats_path, $was_goto;
	
	$in_file = $in_events = false;
	$code = $current_file = "";

	$fh = fopen($filename, "r");
	while ($line = fgets($fh, 4096)) {
		if (!$in_file && preg_match("/^\s+'?(.*?)'? =>(\s*)$/", $line, $matches)) {
			if (!empty($code)) {
				print "File: $current_file\n";
				eval($code);
			}
			$in_file = true;
			$current_file = $matches[1];
			$code = "\$stats['$current_file'] = " . $matches[2] . "\n";
		}
		else if ($in_file) {
			if ($line == "  ),\n") {
				$in_file = $in_events = false;
				$code .= ");\n";
			}
			else if (preg_match("/^\s+'goto' => '(.*?)',$/", $line, $matches)) {
				$goto_path = $conf_stats_path . "/" . $matches[1];
				if (!in_array($goto_path, $was_goto)) {
					print "Goto: $matches[1]\n";
					$was_goto[] = $goto_path;
					load_incrementally_file($goto_path);
				}
			}
			else $code .= $line;
		}
	}
	fclose($fh);
}

?>
