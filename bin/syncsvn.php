<?php

# Run as username

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore", ".git", ".tmux.lock", "core", ".core", ".valgrind.out.core", ".nfs");
$file_size_limit = 100000; // 100kB
$file_limit_delete = 100000000; // 100MB

$logfile = $conf_base_path . "/log/syncsvn.log";

if ($argc == 1) die("ERROR: username is required\n");
$username = $argv[1];


$userdata = setup_paths($username);
$lastfile = $conf_base_path . "/bin/webidectl last-update " . $userdata['esa'];
$fifo_file = $userdata['workspace'] . "/.svn.fifo";
$inotify_pid_file = $userdata['workspace'] . "/.inotify_pid";

if (!file_exists($userdata['workspace'])) die("ERROR: $username doesn't exist\n");

// Avoid multiple instances
if (file_exists($userdata['svn_watch'])) {
	$pid = trim(file_get_contents($userdata['svn_watch']));
	if (file_exists("/proc/$pid"))
		die("ERROR: syncsvn already running $pid");
}

// REALLY avoid multiple instances :)
$mypid = getmypid();
foreach(ps_ax("localhost") as $process) {
	if (strstr($process['cmd'], "syncsvn.php $username") && $process['pid'] != $mypid)
		exec("kill " . $process['pid']);
}

exec("echo $mypid > " . $userdata['svn_watch']);



// SVN cleanup - not really neccessary because of fixsvn, but not a bad precaution
exec("cd " . $userdata['workspace'] . "; svn add *; svn add .login; svn add .logout; svn ci -m syncsvn_starting .");

if (file_exists($fifo_file)) unlink($fifo_file);

$success = posix_mkfifo($fifo_file, 0755);
if( ! $success)
	die('Error: Could not create a named pipe: '. posix_strerror(posix_errno()) . "\n");
  
// Main loop ensures that inotifywait is always running
while(true) {
	// Is inotifywait already started? Kill it!
	if (file_exists($inotify_pid_file)) {
		$pid = intval(file_get_contents($inotify_pid_file));
		if ($pid > 0) exec("kill $pid");
	}

	// Start inotifywait and write pid to pidfile
	exec(sprintf("%s >> %s 2>&1 & echo $! > %s", "/usr/local/bin/inotifywait -o $fifo_file -m -c -q -e close_write,moved_from,moved_to,create,delete -r " . $userdata['workspace'] . " --exclude .svn", $conf_svn_problems_log, $inotify_pid_file));

	// Sometimes something is preventing inotifywait to start (e.g. bug, watches limit...)
	// This will keep it restarting every 5s and commit the changes
	usleep(500000); 
	$pid = trim(file_get_contents($inotify_pid_file));
	echo "PID: $username $pid\n";
	if (!file_exists("/proc/$pid")) { 
		usleep(5000000);
		echo "Loop $username...\n";
		exec("cd " . $userdata['workspace'] . "; svn ci -m loop_cleanup .");
		continue;
	}
	
	// Read from pipe
	$pipe_read = fopen($fifo_file, 'r');

	if( ! $pipe_read)
		die('Error: Could not open the named pipe: '. posix_strerror(posix_errno()) . "\n");
		
	$was_movedfrom = $was_was_movedfrom = $was_was_movedto = "";
	
	// Loop for reading from pipe
	while($f = fgets($pipe_read)){
		$openquotes = false;
		$parts = array();
		$parts[0] = $parts[1] = $parts[2] = "";
		$p = 0;
		for ($i=0; $i<strlen($f); $i++) {
			if ($f[$i] == '"') $openquotes = !$openquotes;
			else if ($f[$i] == ',' && !$openquotes) $p++;
			else $parts[$p] .= $f[$i];
		}
		$subpath = $parts[0];
		$change = $parts[1];
		$file = trim($parts[2]);
		
		// Should this event be ignored?
		$do_ignore = false;
		foreach ($svn_ignore as $ign) {
			if (substr($file, 0, strlen($ign)) == $ign) $do_ignore=true;
			
			$testpath = $userdata['workspace'] . "/" . $ign;
			if (substr($subpath, 0, strlen($testpath)) == $testpath)
				$do_ignore = true;
			if ($file == $ign) $do_ignore = true;
		}
		if ($do_ignore) { continue; }
		
		// Output :)
		$subsubpath = substr($subpath, strlen($userdata['workspace']));
		print date("d.m.Y H:i:s") . " ($username) - $subsubpath - $file - $change\n";

		$output = array();
		
		// Create new file
		if (strstr($parts[1], "CREATE")) {
			if ($was_was_movedfrom == $subpath.$file || $was_was_movedto == $subpath.$file) { print "skip A\n"; continue; }
			// Don't add too large files to SVN
			if (filesize($subpath.$file) > $file_size_limit) { print "skip large file $file\n"; continue; }
			exec("svn add '$subpath$file'", $output);
			
		// Delete file
		} elseif (strstr($parts[1], "DELETE")) {
			if ($file == "runme" || $file == ".at_result" || $file == ".runme") { print "skip X\n"; continue; }
			if ($was_was_movedfrom == $subpath.$file || $was_was_movedto == $subpath.$file) { print "skip B\n"; continue; }
			exec("svn delete '$subpath$file'", $output);
			// Here commit must be on parent folder
			file_put_contents($logfile, join("\n", $output), FILE_APPEND);
			
			// I dont understand why this is neccessary
			if (strstr($parts[1], "ISDIR")) {
				exec("svn update '$subpath'", $output);
				file_put_contents($logfile, join("\n", $output), FILE_APPEND);
			}
			
			exec("svn ci -m delete '$subpath'", $output);
			file_put_contents($logfile, join("\n", $output), FILE_APPEND);
			$was_movedfrom = "";
			continue;
			
		// Moved from
		} elseif (strstr($parts[1], "MOVED_FROM")) {
			if ($was_was_movedfrom == $subpath.$file || $was_was_movedto == $subpath.$file) { print "skip C\n"; continue; }
			if ($was_movedfrom == "") { 
				$was_movedfrom="$subpath$file";
				{ print "skip D\n"; continue; }
			}
			// Otherwise it's delete
			exec("svn delete '$subpath$file'", $output);
			
		// Moved to
		} elseif (strstr($parts[1], "MOVED_TO")) {
			if ($was_was_movedfrom == $subpath.$file) { print "skip E\n"; continue; }
			if ($was_movedfrom != "") {
				$was_was_movedfrom = $was_movedfrom;
				$was_was_movedto = $subpath.$file;
/*				exec("mv '$subpath$file' '$was_movedfrom'");
				exec("svn rename '$was_movedfrom' '$subpath$file'", $output);
				file_put_contents($logfile, join("\n", $output), FILE_APPEND);
				exec("svn ci -m move $student_workspace", $output);
				file_put_contents($logfile, join("\n", $output), FILE_APPEND);*/
				exec("svn remove '$was_movedfrom'");
				exec("svn add '$subpath$file'");
				exec("svn update '$subpath'");
				exec("svn ci -m move " . $userdata['workspace']);
				$was_movedfrom = "";
				continue;
			}
			// Don't add too large files to SVN
			if (filesize($subpath.$file) > $file_size_limit) { print "skip large file $file\n"; continue; }
			// Otherwise it's create
			print "svn add '$subpath$file'\n";
			exec("svn add '$subpath$file'", $output);
		}
		
		// Don't add too large files to SVN
		if (filesize($subpath.$file) > $file_size_limit) { print "skip large file $file\n"; continue; }
		if (filesize($subpath.$file) > $file_limit_delete) { 
			print "delete extra large file $file\n"; 
			unlink($subpath.$file);
			exec("svn delete '$subpath$file'", $output);
		}
		
		$was_movedfrom = $was_was_movedfrom = $was_was_movedto = "";
		file_put_contents($logfile, join("\n", $output), FILE_APPEND);
		exec("svn ci -m monitor '$subpath$file'", $output);
		file_put_contents($logfile, "svn ci -m monitor '$subpath$file'\n", FILE_APPEND);
		file_put_contents($logfile, join("\n", $output), FILE_APPEND);
		
		file_put_contents($lastfile, time());
		
		// Don't use too much CPU in case of very rapid changes (folder upload)
		usleep(500000);
		
		if (!file_exists("/proc/$pid")) break;
	}
}


?>