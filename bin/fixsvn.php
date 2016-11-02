<?php

// A tool that fixes all of the typical problems that arise with SVN repository
// To be run automatically some random time after logout

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


$debug = true;

// Args
if ($argc == 1) die("ERROR: username is required\n");
$username = $argv[1];

$sleep = 60;
if ($argc > 2) $sleep = $argv[2];


//$banned_users = array("egradanin1", "bdzanko1", "test");
$banned_users = array("test");
if (in_array($username, $banned_users)) 
	die("ERROR: username banned\n");

sleep($sleep);


$userdata = setup_paths($username);

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));
if ($users[$username]['status'] == 'active') {
	print "ERROR: User is logged in\n"; 
	exit(0); 
}


// Perform the following actions
fixsvn("find . -name .valgrind.out.core.* -print0 | xargs -0 --no-run-if-empty svn remove --force");
fixsvn("svn ci -m fixsvn .");
fixsvn("svn add *");
fixsvn("svn add */*");
fixsvn("svn add */*/*");
fixsvn("svn add */*/*/*");
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
		
		if (strstr($line, "Committed revision")) {
			$ok = true;
			break;
		}
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
		else if (preg_match("/File '(.*?)' is out of date/", $line, $matches) ||
			preg_match("/Base checksum mismatch on '(.*?)'/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			unlink("/tmp/$filename");
			fixsvn("cp \"".$matches[1]."\" \"/tmp/$filename\"");
			unlink($matches[1]);
			fixsvn("svn update --accept mine-full \"$wsname\"");
			fixsvn("cp \"/tmp/$filename\" \"$wsname\"");
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
		preg_match("/Tree conflict can only be resolved to 'working' state: '(.*?)' not resolved/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			fixsvn("svn resolve --accept mine-full \"$wsname\"");
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (preg_match("/Can't change perms of file '(.*?)': No such file or directory/", $line, $matches) ||
		preg_match("/'(.*?)' is scheduled for addition, but is missing/", $line, $matches)) {
			$filename = basename($matches[1]);
			$wsname = substr($matches[1], strlen($userdata['workspace'])+1);
			run_as($username, "cd " . $userdata['workspace'] . "; touch \"$wsname\"");
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
					run_as($username, "mv \"".$matches[1]."\" /tmp/$filename");
					run_as($username, "cd " . $userdata['workspace'] . "; touch \"$wsname\"");
					fixsvn("svn ci -m fixsvn \"$wsname\"");
					fixsvn("svn remove \"$wsname\"");
					fixsvn("svn ci -m fixsvn \"$wsname\"");
					run_as($username, "mv /tmp/$filename \"".$matches[1]."\"");
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
		else if (preg_match("/Can't move '.*?' to '(.*?)': Permission denied/", $line, $matches)) {
			$path = dirname($matches[1]);
			exec("chown -R $username:$conf_c9_group ".escapeshellarg($path));
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (strstr($line, "Directory '/' is out of date")) {
			fixsvn("svn update .");
			fixsvn($command);
			$ok = true;
			break;
		}
		else if (strstr($line, "Could not add all targets because some targets are already versioned") ||
		strstr($line, "Could not add all targets because some targets don't exist")) {
			$ok = true;
		}
		// Something is being fixed, go on
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
		$sleep += 5;
		exec("php $conf_base_path/bin/fixsvn.php " . $userdata['esa'] . " $sleep &");
		exit(1);
	}
}

?>