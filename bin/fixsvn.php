<?php

// A tool that fixes all of the typical problems that arise with SVN repository
// To be run automatically some random time after logout

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


// Don't run if load average too big
$stats = explode(" ", trim(`tail -1 $conf_base_path/server_stats.log`));
if ($stats[2] > $conf_limit_loadavg_fixsvn) {
	print "Load too big, wait some more\n";
	exit(1);
}

$debug = true;

// Args
if ($argc == 1) die("ERROR: username is required\n");
$username = $argv[1];


$banned_users = array();
if (in_array($username, $banned_users)) {
	print "ERROR: User is banned\n"; 
	exit(0); 
}

$userdata = setup_paths($username);

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));
if (!array_key_exists($username, $users)) {
	print "ERROR: Unknown user\n"; 
	exit(0); 
}
if ($users[$username]['status'] == 'active') {
	print "ERROR: User is logged in\n"; 
	exit(0); 
}


// Perform the following actions
fixsvn("ls */*core* | xargs -I {} svn remove --force {}");
fixsvn("ls */*/*core* | xargs -I {} svn remove --force {}");
fixsvn("ls */*/*/*core* | xargs -I {} svn remove --force {}");
fixsvn("svn ci -m fixsvn .");
fixsvn("svn add *");
fixsvn("svn add */*");
fixsvn("svn add */*/*");
fixsvn("svn add */*/.*");
fixsvn("svn add */*/*/*");
fixsvn("svn add */*/*/.*");
fixsvn("svn ci -m fixsvn .");


function fixsvn($command) {
	global $username, $userdata, $debug, $sleep, $conf_base_path, $conf_c9_group;

	if ($debug) print "Command: $command\n";
	
	$output = run_as($username, "cd " . $userdata['workspace'] . "; $command 2>&1");
	if ($debug) print "Result: \n";
	$ok = false;
	if (empty($output)) $ok = true;
	foreach (explode("\n", $output) as $line) {
		if ($debug) print "$line\n";
		$matches = array();
		
		// Messages that mean everything is ok
		if (strstr($line, "Committed revision")) {
			$ok = true;
			break;
		}
		
		// Root dir itself isn't updated
		else if (strstr($line, "Directory '/' is out of date")) {
			fixsvn("svn update .");
			fixsvn($command);
			$ok = true;
			break;
		}
		
		// Call svn cleanup
		else if (strstr($line, "Previous operation has not finished")) {
			fixsvn("svn cleanup");
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (strstr($line, "is already locked")) {
			fixsvn("svn cleanup");
			fixsvn($command);
			$ok = true;
			break;
		}
		
		// Resolve conflicts
		else if (preg_match("/File '(.*?)' is out of date/", $line, $matches) ||
			preg_match("/Base checksum mismatch on '(.*?)'/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			unlink("/tmp/$filename");
			run_as($username, "cp \"".$matches[1]."\" \"/tmp/$filename\"");
			unlink($matches[1]);
			fixsvn("svn update --accept mine-full \"$wsname\"");
			run_as($username, "cp \"/tmp/$filename\" \"$wsname\"");
			unlink("/tmp/$filename");
			fixsvn("svn ci -m fixsvn \"$wsname\"");
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (preg_match("/Directory '(.*?)' is out of date/", $line, $matches)) {
			// Let's hope there are no local changes to files...
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			fixsvn("svn update --accept mine-full \"$wsname\"");
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (preg_match("/Aborting commit: '(.*?)' remains in conflict/", $line, $matches) ||
		preg_match("/Tree conflict can only be resolved to .*? state: '(.*?)' not resolved/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			fixsvn("svn resolve --accept working \"$wsname\"");
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (preg_match("/Checksum mismatch for text base of '(.*?)'/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			unlink("/tmp/$filename");
			fixsvn("cp \"".$matches[1]."\" \"/tmp/$filename\"");
			unlink($matches[1]);
			fixsvn("svn rm --force \"$wsname\"");
			fixsvn("cp \"/tmp/$filename\" \"$wsname\"");
			unlink("/tmp/$filename");
			fixsvn("svn add \"$wsname\"");
			fixsvn("svn ci -m fixsvn \"$wsname\"");
			fixsvn($command);
			$ok = true;
			break;
		}
		
		// Missing files
		else if (preg_match("/Can't change perms of file '(.*?)': No such file or directory/", $line, $matches) ||
		preg_match("/'(.*?)' is scheduled for addition, but is missing/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			$response = run_as($username, "cd " . $userdata['workspace'] . "; touch \"$wsname\"  2>&1");
			if (strstr($response, "No such file or directory")) {
				$dir = dirname($wsname);
				run_as($username, "cd " . $userdata['workspace'] . "; mkdir -p \"$dir\"");
				run_as($username, "cd " . $userdata['workspace'] . "; touch \"$wsname\"");
			}
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (preg_match("/Node '(.*?)' has unexpectedly changed kind/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			if (is_dir($matches[1])) {
				$files = scandir($matches[1]);
				if (count($files) == 2) {
					// Directory empty, add an empty file instead
					rmdir($matches[1]);
					run_as($username, "cd " . $userdata['workspace'] . "; touch \"$wsname\"");
					fixsvn($command);
					$ok = true;
				} else {
					// Directory not empty
					// Copy to tmp, commit file, readd
					`rm -fr /tmp/$filename`;
					run_as($username, "mv \"".$matches[1]."\" \"/tmp/$filename\"");
					run_as($username, "cd " . $userdata['workspace'] . "; touch \"$wsname\"");
					fixsvn("svn ci -m fixsvn \"$wsname\"");
					fixsvn("svn remove \"$wsname\"");
					fixsvn("svn ci -m fixsvn \"$wsname\"");
					run_as($username, "mv \"/tmp/$filename\" \"".$matches[1]."\"");
					fixsvn("svn add \"$wsname\"");
					fixsvn("svn ci -m fixsvn \"$wsname\"");
				}
			} else {
				$contents = file_get_contents($matches[1]);
				unlink($matches[1]);
				run_as($username, "cd " . $userdata['workspace'] . "; mkdir \"$wsname\"");
				fixsvn($command);
				if (!empty($contents)) {
					// After $command directory may become file again!
					if (is_dir($matches[1])) {
						$newname = $matches[1] . "/" . $filename;
						file_put_contents($newname, $contents);
						exec("chown $username:$conf_c9_group \"$newname\"");
					} else {
						file_put_contents($matches[1], $contents);
						fixsvn("svn add \"$wsname\"");
						fixsvn("svn ci -m fixsvn \"$wsname\"");
					}
				}
				$ok = true;
			}
			break;
		}
		
		// Wrong permissions
		else if (preg_match("/Can't move '.*?' to '(.*?)': Permission denied/", $line, $matches) ||
			preg_match("/Can't open file '(.*?)': Permission denied/", $line, $matches)) {
			$path = dirname($matches[1]);
			exec("chown -R $username:$conf_c9_group ".escapeshellarg($path));
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (preg_match("/Can't check path '(.*?)': Permission denied/", $line, $matches)) {
			$path = dirname($matches[1]);
			exec("chmod 755 ".escapeshellarg($path));
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (preg_match("/Can't read directory '(.*?)': Partial results are valid/", $line, $matches)) {
			$path = $matches[1];
			exec("chmod 755 ".escapeshellarg($path));
			fixsvn($command);
			$ok = true;
			break;
		}
		
		// Illegal filenames
		else if (preg_match("/Invalid control character '.*?' in path '(.*?)'/", $line, $matches) ||
			preg_match("/Error converting entry in directory '(.*?)' to/", $line, $matches) ||
			preg_match("/'(.*?)': a peg revision is not allowed here/", $line, $matches)) {
			$basedir = $matches[1];
			if (strstr($line, "Invalid control") || strstr($line, "a peg revision")) $basedir = dirname($basedir);
			if (strstr($line, "a peg revision")) $basedir = $userdata['workspace'] . "/" . $basedir;
			
			$dh = opendir($basedir);
			while ($filename = readdir($dh)) {
				//print "Filename: $filename\n";
				if (preg_match('/[[:^print:]]/', $filename) || strstr($filename, "@")) {
					$new_filename = preg_replace('/[[:^print:]]/', '?', $filename);
					$new_filename = str_replace('@', '?', $new_filename);
					print "Renaming $filename to $new_filename\n";
					rename($basedir . "/" . $filename, $basedir . "/" . $new_filename);
				}
			}
			
			fixsvn($command);
			$ok = true;
			break;
		}
		
		// SVN DB corruption
		else if (preg_match("/Can't install '(.*?)' from pristine store, because no checksum is recorded for this file/", $line, $matches)) {
			$filename = basename($matches[1]);
			fixsvn("sqlite3 .svn/wc.db \"delete from work_queue\"");
			fixsvn($command);
			$ok = true;
			break;
		}
		
		else if (preg_match("/Pristine text '(.*?)' not present/", $line, $matches)) {
			$pristine = basename($matches[1]);
			file_put_contents("/tmp/fixsvn_script.sh", "cd " . $userdata['workspace'] . "; sqlite3 .svn/wc.db 'select local_relpath from nodes where checksum=\"\$sha1\$" . $pristine . "\";'");
			$filepath = trim(run_as($username, "/bin/sh /tmp/fixsvn_script.sh"));
			$filename = basename($filepath);
			
			file_put_contents("/tmp/fixsvn_script.sh", "cd " . $userdata['workspace'] . "; sqlite3 .svn/wc.db 'update nodes set presence=\"not-present\" where checksum=\"\$sha1\$" . $pristine . "\";'");

			run_as($username, "mv \"$filepath\" \"/tmp/$filename\"");
			run_as($username, "/bin/sh /tmp/fixsvn_script.sh");
			fixsvn("svn cleanup");
			fixsvn("svn update --force");
			run_as($username, "mv \"/tmp/$filename\" \"$filepath\"");
			fixsvn($command);
			$ok = true;
			break;
		}
		
		else if (strstr($line, "database disk image is malformed")) {
			fixsvn("sqlite3 .svn/wc.db \"reindex nodes\"");
			fixsvn("sqlite3 .svn/wc.db \"reindex pristine\"");
			
			fixsvn("svn cleanup");
			fixsvn($command);
			$ok = true;
			break;
		}
		
		// Something is being fixed, go on
		else if (strstr($line, "Could not add all targets because some targets are already versioned") ||
		strstr($line, "Could not add all targets because some targets don't exist")) {
			$ok = true;
		}
		else if (strstr($line, "Resolved conflicted state of")) {
			$ok = true;
		} 
		else if (strstr($line, "Updated to revision") || strstr($line, "At revision") || strstr($line, "Committed revision")) {
			$ok = true;
		}
		else if (strstr($line, "D      ") || strstr($line, "A      ") ) {
			$ok = true;
		}
	}
	
	if (!$ok) {
		print "Unkown error!\n\n";
		exit(1);
	}
}

?>
