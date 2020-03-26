<?php

// =========================================
// STATS_ANALYZE.PHP
// C9@ETF project (c) 2015-2020
// 
// Analyze .stats file without eval() (useful for too large files, syntax errors etc.)
// =========================================


$filename = $argv[1];
if (count($argv)>2) { 
	$fix_file = $argv[2]; 
	$find_rev = true; 
} else { 
	$fix_file = ""; 
	$find_rev = false; 
}
if (count($argv)>4 && $argv[3] == "nuke") {
	$do_output = true;
	$nuke_rev = $argv[4];
	$output_file = $argv[1] . ".new";
} else {
	$nuke_rev = false;
	$do_output = false;
}

$in_file = $in_events = false;
$size = 0;
$cur_event = -1; $event_size = 0;

$fh = fopen($filename, "r");
if ($do_output)
	$fh2 = fopen($output_file, "w");
	
while ($line = fgets($fh, 4096)) {
	if (!$in_file && preg_match("/^\s+'?(.*?)'? =>\s*$/", $line, $matches)) {
		if (empty($fix_file) || $fix_file == $matches[1]) {
			print "File: $matches[1]  ";
			$in_file = true;
			$size = strlen($line);
		}
	}
	else if ($in_file) $size += strlen($line);
	if ($in_file) {
		if ($find_rev && $line == "    'events' => \n") $in_events = true;
		if ($in_events && ($line == "    ),\n" || preg_match("/^      (\d+) => $/", $line, $matches))) {
			if ($cur_event>=0) print "Event $cur_event size " . nicesize($size-$event_size) . " ($cur_time)\n";
			if (count($argv)>4 && $do_output == false) print "Nuked!\n";
			$event_size = $size;
			if ($line == "    ),\n") { 
				$in_events = false; 
				if ($nuke_rev !== false) $do_output=true; 
			}
			else {
				$cur_event = $matches[1];
				if ($nuke_rev !== false) {
					if ($nuke_rev == $cur_event) $do_output=false; else $do_output=true;
				}
			}
		}
		if ($line == "  ),\n") {
			print "Size: " . nicesize($size) . "\n";
			$in_file = $in_events = false;
			if ($nuke_rev !== false) $do_output=true;
		}
		if (preg_match("/^        'time' => (\d+),/", $line, $matches)) {
			$cur_time = date ("d.m.Y H:i:s", $matches[1]);
		}
	}
	if ($do_output)
		fwrite($fh2, $line);
}
fclose($fh);
if ($do_output) {
	fclose($fh2);
	rename($filename, $filename . ".bak");
	rename($output_file, $filename);
}

function nicesize($size) {
	if ($size>1024*1024*1024) {
		return intval($size/(1024*1024*1024/10))/10 . " GB";
	} else if ($size>1024*1024*10) {
		return intval($size/(1024*1024)) . " MB !!!";
	} else if ($size>1024*1024) {
		return intval($size/(1024*1024/10))/10 . " MB !!!";
	} else if ($size>1024*10) {
		return intval($size/1024) . " kB";
	} else if ($size>1024) {
		return intval($size / (1024/10))/10 . " kB";
	} else {
		return $size . " B";
	}
}

?>
