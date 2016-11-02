<?php


function parse_stats($login, &$assignments, &$stats_parsed, &$last_access, $path_log) {
	global $conf_base_path, $conf_stats_path;
	
	$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore", "global_events", "last_update_rev", ".gcc.out");
	
	$stats_parsed[$login] = array();
	$last_access[$login] = 0;
	
	// Read last data
	$last_path = $conf_base_path . "/last/$login.last";
	if (file_exists($last_path)) $last_access[$login] = intval(file_get_contents($last_path));
	
	// Loading stats file
	$stats=null;
	$stat_path = $conf_stats_path . "/$login.stats";
	if (!file_exists($stat_path)) {
		//print "File $stat_path doesn't exist<br>\n";
		return;
	}
	$contents = file_get_contents($stat_path);
	//print "File $stat_path exists, size ".strlen($contents)."<br>\n";
	eval($contents);
	$contents = null;
	if (!isset($stats)) return;

	// Handle goto entries
	foreach($stats as $path => $data) {
		//if (is_array($data) && array_key_exists("goto", $data))
			//print "Path $path Ima goto substr je ".substr($path_log,0,strlen($path))."<br>\n";
		if (is_array($data) && array_key_exists("goto", $data) && substr($path_log,0,strlen($path)) === $path) {
			$goto_path = $conf_stats_path . "/" . $data['goto'];
			if (!file_exists($goto_path)) {
				//print "File $goto_path doesn't exist<br>\n";
				continue;
			}
			//print "Reading $goto_path path $path<br>\n";
			//print "path $path čitam goto ".$goto_path."<br>\n";
			eval(file_get_contents($goto_path));
			foreach($stats_goto as $ks => $vs) {
				//print "Dodajem $ks<br>\n";
				$stats[$ks] = $vs;
			}
			$stats_goto = null;
		}
	}
	
	// Determine last access time from stats - commented out because it was slow
/*	foreach($stats as $path => $data) {
		if (is_array($data) && array_key_exists('events', $data)) {
			$lastevt = end($data['events']);
			if ($lastevt['time'] > $last_access[$login])
				$last_access[$login] = $lastevt['time'];
		}
		if ($path == 'global_events') {
			$lastglobal = end($data);
			if ($lastglobal['real_time'] > $last_access[$login])
				$last_access[$login] = $lastglobal['real_time'];
		}
	}*/

	// Ensure that $entries contains all entries under current path
	if ($path_log != "" && array_key_exists($path_log, $stats)) {
		$entries = $stats[$path_log]['entries'];
	} else {
		$entries = array();
		foreach($stats as $path => $data) {
			if (!strstr($path, "/") && !in_array($path, $svn_ignore)) array_push($entries, $path);
		}
	}
	
	foreach($stats as $path => $data) {
		if (!in_array($path, $entries)) continue;
		// Skip root folder creation event
		if ($path == "") continue;
		
		$file_name = $path;
		// Skip entries not under current path, if set
		if (isset($_REQUEST['path'])) {
			if (strlen($path) < strlen($path_log)) continue;
			if (substr($path, 0, strlen($path_log)) != $path_log) continue;
			// Remove path from file name
			$file_name = substr($path, strlen($path_log)+1);
		}
		
		//print "Trying path $path file name $file_name<br>\n";
		
		// Add new entry to the $assignments
		if (!array_key_exists($file_name, $assignments)) {
			$assgn = array();
			$assgn['path'] = $path;
			/*
			// Snoop filesystem to see if path is a folder and whether it is deleted
			if (file_exists("/home/c9/workspace/$login/$path")) {
				$assgn['obrisan'] = false;
				if (is_dir("/home/c9/workspace/$login/$path"))
					$assgn['folder'] = true;
				else
					$assgn['folder'] = false;
			} else {
				$assgn['obrisan'] = true;
				$assgn['folder'] = false;
			}*/
			
			$assignments[$file_name] = $assgn;
		}
		
		$stats_parsed[$login][$file_name]['time'] = $data['total_time'];
		if (array_key_exists('builds', $data))
			$stats_parsed[$login][$file_name]['builds'] = $data['builds'];
		if (array_key_exists('builds_succeeded', $data))
			$stats_parsed[$login][$file_name]['builds_succeeded'] = $data['builds_succeeded'];
		if (array_key_exists('last_test_results', $data) && !empty($data['last_test_results']))
			$stats_parsed[$login][$file_name]['test_results'] = $data['last_test_results'];
	}
}



?>