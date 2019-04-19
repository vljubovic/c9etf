<?php

// =========================================
// SYNCSVN-FANOTIFY.PHP
// C9@ETF project (c) 2015-2018
//
// Commit all changed files to user repository (fanotify version)
// =========================================



# Run as username

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore", ".git", ".tmux.lock", "core", ".core", ".valgrind.out.core", ".nfs", "last/");
$file_size_limit = 100000; // 100kB
$file_limit_delete = 100000000; // 100MB

$logfile = $conf_base_path . "/log/syncsvn.log";
$data_path = $conf_base_path . "/data";

$cli_users = array();
if ($argc > 1) $cli_users = explode(",",$argv[1]);

/*if ($argc == 1) die("ERROR: username is required\n");
$username = $argv[1];


$userdata = setup_paths($username);
$lastfile = $conf_base_path . "/bin/webidectl last-update " . $userdata['esa'];
$fifo_file = $userdata['workspace'] . "/.svn.fifo";
$inotify_pid_file = $userdata['workspace'] . "/.inotify_pid";

if (!file_exists($userdata['workspace'])) die("ERROR: $username doesn't exist\n");*/

	$fifo_file = "/tmp/svn-fanotify.fifo";
	$fatrace_pid_file = $data_path . "/.fatrace_pid";
	
	$big_paths = array("OR","TP","ASP","OR2016","TP2016","OR2015","TP2015","NA");
	
	$users_file = $conf_base_path . "/users";
	$usersmtime = filemtime($users_file);
	$users = array();
	eval(file_get_contents($users_file));

// Avoid multiple instances
/*if (file_exists($userdata['svn_watch'])) {
	$pid = trim(file_get_contents($userdata['svn_watch']));
	if (file_exists("/proc/$pid"))
		die("ERROR: syncsvn already running $pid");
}*/

// REALLY avoid multiple instances :)
/*$mypid = getmypid();
foreach(ps_ax("localhost") as $process) {
	if (strstr($process['cmd'], "syncsvn.php $username") && $process['pid'] != $mypid)
		exec("kill " . $process['pid']);
}

exec("echo $mypid > " . $userdata['svn_watch']);*/



// SVN cleanup - not really neccessary because of fixsvn, but not a bad precaution
//exec("cd " . $userdata['workspace'] . "; svn add *; svn add .login; svn add .logout; svn ci -m syncsvn_starting .");

if (file_exists($fifo_file)) unlink($fifo_file);

$success = posix_mkfifo($fifo_file, 0755);
if( ! $success)
	die('Error: Could not create a named pipe: '. posix_strerror(posix_errno()) . "\n");
$date = date("d.m.Y");

$known_paths = $known_files = array();
  
// Main loop ensures that fatrace is always running
while(true) {
	// Is fatrace already started? Kill it!
	if (file_exists($fatrace_pid_file)) {
		$pid = intval(file_get_contents($fatrace_pid_file));
		if ($pid > 0) exec("kill $pid");
	}

	// Start fatrace and write pid to pidfile
	chdir("/home");
	exec(sprintf("%s >> %s 2>&1 & echo $! > %s", "/usr/local/bin/fatrace -o $fifo_file -c -C nfsd -f W -t", $conf_svn_problems_log, $fatrace_pid_file));

	// Sometimes something is preventing inotifywait to start (e.g. bug, watches limit...)
	// This will keep it restarting every 5s and commit the changes
	usleep(500000); 
	$pid = trim(file_get_contents($fatrace_pid_file));
	echo "PID: $pid\n";
	if (!file_exists("/proc/$pid")) { 
		usleep(5000000);
		echo "Loop ...\n";
		exec("cd " . $userdata['workspace'] . "; svn ci -m loop_cleanup .");
		continue;
	}
	
	// Read from pipe
	$pipe_read = fopen($fifo_file, 'r');

	if( ! $pipe_read)
		die('Error: Could not open the named pipe: '. posix_strerror(posix_errno()) . "\n");
		
	$was_movedfrom = $was_was_movedfrom = $was_was_movedto = "";
	$oldtime = "";
	
	// Loop for reading from pipe
	while ($f = fgets($pipe_read)) {
		$time = $process = $mode = $filepath = "";
		
		// Parse fatrace line
		$pdot = strpos($f, ".");
		$time = substr($f, 0, $pdot);
		$psp = strpos($f, " ");
		$p = strpos($f, ": ", $psp+1);
		$process = substr($f, $psp, $p-$psp);
		$mode = substr($f, $p+2, 1);
		$filepath = trim(substr($f, $p+4));
		
		// Paths to ignore
		$ignore = false;
		foreach($svn_ignore as $ign)
			if (strstr($filepath, "/" . $ign)) $ignore=true;
		if ($ignore == true) continue;
		
		// Skip deleted files
		$filepath = str_replace(" (deleted)", "", $filepath);
		if (!file_exists($filepath)) continue;
		
		// Not in home (shouldn't happen)
		if (substr($filepath, 0, 6) != "/home/") continue;
		
		// Get username
		$endusr = strpos($filepath, "/", 9);
		$user = substr($filepath, 8, $endusr-8);
		
		// Skip username if list of allowed users is set
		if (!empty($cli_users) && !in_array($user, $cli_users)) continue;
		
		// Skip user if not logged in
		$tmpmtime = filemtime($users_file);
		if ($usersmtime < $tmpmtime || $count_active == 0) { 
			print "Reread users file\n";
			$usersmtime = $tmpmtime;
			$users = array();
			$in_user = false;
			$count_active = 0;
			foreach(file($users_file) as $line) {
				if (preg_match("/^\s+\'(.*?)\' => $/", $line, $matches) && !$in_user) {
					$m_user = $matches[1];
					//print "User $user\n";
					$users[$m_user] = array();
					$in_user = true;
				}
				if (preg_match("/^\s+\),$/", $line) && $in_user)
					$in_user = false;
				if (strpos($line, "'status' => 'inactive'") && $in_user)
					$users[$m_user]['status'] = 'inactive';
				if (strpos($line, "'status' => 'active'") && $in_user) {
					$users[$m_user]['status'] = 'active';
					$count_active++;
				}
			}
		}
		if (!array_key_exists($user, $users)) { 
			print "Skipping unknown user $user (line: $f)\n"; 
			continue; 
		}
		
		// Skip if not in workspace (configuration and stuff)
		$ws = substr($filepath, 9+strlen($user), 10);
		if ($ws != "workspace/") continue;
		$user_ws = substr($filepath, 0, 19+strlen($user));
		
		// Split into folder and file
		$endpath = strrpos($filepath, "/");
		$path = substr($filepath, 19+strlen($user), $endpath-18-strlen($user));
		$file = substr($filepath, $endpath+1);
		
		// Skip users who are not logged in (shouldn't really happen)
		if ($users[$user]["status"] === "inactive" && $file != ".login" && $file != ".logout") { 
			print "Skipping inactive $user file $file"; 
			continue; 
		}
		
		// Detect date change
		if (intval($oldtime) > intval($time))
			$date = date("d.m.Y");
		$oldtime = $time;
		
		// Check for folder create
		if (!array_key_exists($user, $known_paths))
			$known_paths[$user] = array();
		if ($path != "" && !in_array($path, $known_paths[$user])) {
			// Wait for all the files in folder to become created
			//usleep(500000);
			$oldp = 0;
			$first_path = "";
			do {
				$p = strpos($path, "/", $oldp+1);
				if ($p == 0) $path_part = $path;
				else $path_part = substr($path, 0, $p);
				$oldp = $p;
				
				$known_paths[$user][] = $path_part;
				
				//if (in_array($path_part, $big_paths)) continue;
				print "-- Adding path $path_part\n";
				run_as($user, "cd $user_ws; svn add \"$path_part\"; svn ci -m monitor \"$path_part\"");
				if ($first_path == "") $first_path = $path_part;
			} while ($p != 0);
			
			run_as($user, "cd $user_ws; svn ci -m monitor \"$path\"");
		}
		
		// Check for file create
		$pfile = $path . "/" . $file;
		if ($path == "") $pfile = $file;
		if (!array_key_exists($user, $known_files))
			$known_files[$user] = array();
		if (!in_array($pfile, $known_files[$user])) {
			if (filesize($filepath) > $file_size_limit) { 
				print "skip adding large file $pfile\n"; 
				continue; 
			}
			run_as($user, "cd $user_ws; svn add \"$pfile\"");
			$known_files[$user][] = $pfile;
		}
		
		// Skip & delete large files
		if (filesize($filepath) > $file_limit_delete) { 
			print "delete extra large file $pfile\n"; 
			unlink($filepath);
			run_as($user, "cd $user_ws; svn delete \"$pfile\"; svn ci -m monitor \"$pfile\"");
		}
		if (filesize($filepath) > $file_size_limit) { 
			print "skip large file $pfile\n"; 
			continue; 
		}
		
		// Commit change
		run_as($user, "cd $user_ws; svn ci -m monitor \"$pfile\"");
		
		// Write monitor.out
		print "$date $time ($user) - /$path - $file - WRITE\n";
		file_put_contents($conf_syncsvn_log, "$date $time ($user) - /$path - $file - WRITE\n", FILE_APPEND);
		
		
		if (!file_exists("/proc/$pid")) break; // fatrace dieded
	}
}


?>
