<?php

// RECONSTRUCT.PHP - a handy script to recreate older versions of any file from
// .stats file, if svn or git versions are not available for some reason

// Run as root

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");
 
 
// Parameters
if ($argc != 4 && $argc != 1) { 
	print "ERROR: reconstruct.php expects exactly three parameters\n\n";
}
if ($argc != 4) {
	print "reconstruct.php - a handy script to recreate older versions of any file from\nstats file, if svn or git versions are not available for some reason.\nRun userstats before using reconstruct!\n\n";

	die("Usage:\n\tphp reconstruct.php USERNAME FILENAME TIMESTAMP\n\n");
}

$username = $argv[1];
$filename = $argv[2];
$timestamp = $argv[3];

if (intval($timestamp) < 100) $timestamp = strtotime($timestamp);

read_stats($username);
reconstruct_file($username, $filename, $timestamp);

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
			eval(file_get_contents($goto_path));
			foreach($stats_goto as $ks => $vs)
				$stats[$ks] = $vs;
			$stats_goto = null;
		}
	}
}


function reconstruct_file($username, $filename, $timestamp) {
	global $stats, $base_path;
	
	$userdata = setup_paths($username);
	
	if (!array_key_exists($filename, $stats))
		die("ERROR: File doesn't exist in stats log");
		
	$file_workspace_path = $userdata['workspace'] . "/$filename";
	if (!file_exists($file_workspace_path)) {
		print "File doesn't exist in workspace...\nCreating from blank (possibly with errors!)\n\n";
		$work_file = array();
	} else
		$work_file = file($file_workspace_path);
	
	$file_log = $stats[$filename]['events'];
	$evtcount = count($file_log);
	
	// We reconstruct the file backwards from its current state
	for ($i=$evtcount-1; $i>=0; $i--) {
		//print "$i,";
		if ($file_log[$i]['time'] < $timestamp) break;
		if ($i < -$timestamp) break;
		if ($file_log[$i]['text'] != "modified") continue;
		
		if (array_key_exists("change", $file_log[$i]['diff']))
			foreach($file_log[$i]['diff']['change'] as $lineno => $text) {
				// Editing last line - special case!
				if ($lineno-1 == count($work_file)) $lineno--;
				// Since php arrays are associative, we must initialize missing members in correct order
				if ($lineno-1 > count($work_file)) {
					if ($lineno == 2) $lineno=1;
					else {
						for ($j=count($work_file); $j<$lineno; $j++)
							$work_file[$j] = "\n";
					}
				}
				$work_file[$lineno-1] = $text . "\n";
			}
		if (array_key_exists("add_lines", $file_log[$i]['diff'])) {
			$offset=1;
			foreach($file_log[$i]['diff']['add_lines'] as $lineno => $text) {
				if ($offset == 0 && $lineno == 0) $offset=1;
				if ($lineno-$offset > count($work_file))
					for ($j=count($work_file); $j<$lineno-$offset+1; $j++)
						$work_file[$j] = "\n";
				array_splice($work_file, $lineno-$offset, 1);
				$offset++;
			}
		}
		if (array_key_exists("remove_lines", $file_log[$i]['diff'])) {
			$offset=-1;
			foreach($file_log[$i]['diff']['remove_lines'] as $lineno => $text) {
				if ($lineno+$offset > count($work_file))
					for ($j=count($work_file); $j<$lineno+$offset+1; $j++)
						$work_file[$j] = "\n";
				if ($text == "false" || $text === false) $text = "";
				array_splice($work_file, $lineno+$offset, 0, $text . "\n");
			}
		}
	}
	
	$output_path = "/tmp/reconstruct";
	if (!file_exists($output_path)) mkdir($output_path);
	$output_path .= "/" . basename($filename);
	
	file_put_contents($output_path, join("", $work_file));
	print "Written file $output_path, merged " . ($evtcount-$i) . " changes.\n";
}


?>
