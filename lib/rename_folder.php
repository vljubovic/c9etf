<?php

// Rename a folder in stats file

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");

// Settings for read_stats/write_stats
$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore");
$split_folder = array("OR", "OR2015", "TP2015", "OR2016", "TP2016");


// Parameters
if ($argc != 4) { 
	die("ERROR: rename_folder.php expects exactly three parameters\n");
}
$username = $argv[1];
$oldname = $argv[2];
$newname = $argv[3];


read_stats($username);
rename_entry($username, $oldname, $newname, true);
write_stats($username);
print "Rename completed\n";

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

// Write stats file
function write_stats($username) {
	global $stats, $conf_stats_path, $split_folder, $conf_nginx_user;
	
	$username_efn = escape_filename($username);
	
	foreach ($split_folder as $folder) {
		if (!array_key_exists($folder, $stats)) continue;
		
		$goto_dir = $conf_stats_path . "/" . $folder;
		if (!file_exists($goto_dir)) mkdir($goto_dir);
		
		$goto_file_rel = $folder . "/$username_efn.stats";
		$goto_file = $conf_stats_path . "/" . $goto_file_rel;
		
		$stats_goto = $stats;
		$stats[$folder] = array ("goto" => $goto_file_rel);
		foreach ($stats as $key => &$value) {
			if ($key != $folder."/" && strlen($key) > strlen($folder)+1 && substr($key, 0, strlen($folder)+1) == $folder . "/") {
				$stats[$key] = null;
				unset($stats[$key]);
			}
		}
		foreach ($stats_goto as $key => &$value) {
			if ($key != $folder && !(strlen($key) > strlen($folder)+1 && substr($key, 0, strlen($folder)+1) == $folder . "/")) {
				$stats_goto[$key] = null;
				unset($stats_goto[$key]);
			}
		}
		
		ensure_write( $goto_file, "\$stats_goto = ". var_export($stats_goto, true) . ";" );
		chown($goto_file, $conf_nginx_user);
		chmod($goto_file, 0640);
	}
	$stats_file = $conf_stats_path . "/$username_efn.stats";
	ensure_write( $stats_file, "\$stats = " . var_export($stats, true) . ";" );
	chown($stats_file, $conf_nginx_user);
	chmod($stats_file, 0640);
}

function ensure_write($filename, $content) {
	$retry = 1;
	while(true) {
		if (file_put_contents($filename, $content)) return;
		print "Error writing $filename... retry in $retry seconds\n";
		sleep($retry);
	}
}


// Sortiranje svn loga po vremenu commita u rastućem redoslijedu
function svnsort($a, $b) {
	if ($a['unixtime'] == $b['unixtime']) return 0;
	return ($a['unixtime'] < $b['unixtime']) ? -1 : 1;
}


function evtsort($a, $b) {
	if ($a['time'] == $b['time']) return 0;
	return ($a['time'] < $b['time']) ? -1 : 1;
}

function rename_entry($username, $oldname, $newname, $prvi) {
	global $stats;

	if (!array_key_exists($oldname, $stats))
		die("GRESKA: nepoznat put $oldname\n");
		
	$olditem = &$stats[$oldname];

	if (!array_key_exists($newname, $stats))
		// Popunićemo polja praznom statistikom
		$stats[$newname] = array( 
			'total_time' => 0,
			'builds' => 0,
			'builds_succeeded' => 0,
			'testings' => 0,
			'last_test_results' => '',
			'events' => array(),
			'last_revision' => 0,
			'entries' => array()
		);
	$newitem = &$stats[$newname];
	
	// Rekurzivno radimo rename svih entry-ja u folderu
	foreach($olditem['entries'] as $entry) {
		$newentry = $newname . substr($entry, strlen($oldname));
		rename_entry($username, $entry, $newentry, false);
	}
	
	// Uklanjamo polje iz entries za starog roditelja i dodajemo za novog roditelja
	if ($oldparent = daj_roditelja($oldname)) {
		$fixed_entries = array();
		foreach ($stats[$oldparent]['entries'] as $entry)
			if ($entry != $oldname)
				array_push($fixed_entries, $entry);
		$stats[$oldparent]['entries'] = $fixed_entries;
	}
	if ($newparent = daj_roditelja($newname)) {
		if (!array_key_exists('entries', $stats[$newparent]))
			$stats[$newparent]['entries'] = [];
		if (!in_array($newname, $stats[$newparent]['entries']))
			array_push($stats[$newparent]['entries'], $newname);
	}
	
	// Sabiramo statistike starog foldera na novi folder
	if (!array_key_exists('total_time', $newitem))
		$newitem['total_time'] = $olditem['total_time'];
	else if ($newitem['total_time'] == 0)
		$newitem['total_time'] += $olditem['total_time'];
	else
		$newitem['total_time'] += $olditem['total_time'] - 10;
		
	// Dodajemo vrijeme stare stavke na novu stavku i sve njene roditelje
	if ($prvi) {
		while ($newparent) {
			print "Ažuriram roditelja $newparent\n";
			$stats[$newparent]['total_time'] += $olditem['total_time'];
			$newparent = daj_roditelja($newparent);
		}
	}

	if (!array_key_exists('builds', $newitem))
		$newitem['builds'] = $olditem['builds'];
	else 
		$newitem['builds'] += $olditem['builds'];
		
	
	if (!array_key_exists('builds_succeeded', $newitem))
		$newitem['builds_succeeded'] = $olditem['builds_succeeded'];
	else 
		$newitem['builds_succeeded'] += $olditem['builds_succeeded'];
		
	if (array_key_exists('testings', $olditem))
		if (!array_key_exists('testings', $newitem))
			$newitem['testings'] = $olditem['testings'];
		else 
			$newitem['testings'] += $olditem['testings'];
			
	if (array_key_exists('last_test_results', $olditem))
		$newitem['last_test_results'] = $olditem['last_test_results'];
		
	if (!array_key_exists('last_revision', $newitem) || $newitem['last_revision'] < $olditem['last_revision'])
		$newitem['last_revision'] = $olditem['last_revision'];
	
	// Dodajemo entry-je (ako ih prethodno nije dodala rekurzija, što bi se trebalo desiti...)
	if (!array_key_exists('entries', $newitem))
		$newitem['entries'] = [];
	foreach($olditem['entries'] as $entry) {
		$newentry = $newname . substr($entry, strlen($oldname));
		if (!in_array($newentry, $newitem['entries']))
			array_push($newitem['entries'], $newname);
	}
	
	// Dodajem evente sortirano po vremenu
	if (!array_key_exists('events', $newitem))
		$newitem['events'] = [];
	foreach($olditem['events'] as $event) {
		array_push($newitem['events'], $event);
	}
	usort($newitem['events'], 'evtsort');
	
	// Sada možemo obrisati stari item
	unset ($stats[$oldname]);
}

function daj_roditelja($entry) {
	global $stats;
	
	if (!strstr($entry, "/")) return false;
	$parent = substr($entry, 0, strrpos($entry, "/"));
	if (!array_key_exists($parent, $stats))
		die("GRESKA: roditelj od $entry ($parent) nije pronadjen\n");
	return $parent;
}


function debug_log($msg) {
	$time = date("d. m. Y. H:i:s");
	`echo $time $msg >> /tmp/rename.log`;
}

?>
