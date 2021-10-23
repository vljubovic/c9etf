<?php

// =========================================
// NEW_YEAR.PHP
// C9@ETF project (c) 2015-2020
// 
// Rename folders for last year data so that new year starts clean
// =========================================


require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");

$courses = array("OR", "TP", "ASP", "NA", "UUP", "DM", "RA", "NASP", "RG");
$old_year = 2020;

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$no = 0;
$total = count($users);

foreach($users as $username => $options) {
	$no++;
	print "$username ($no/$total):\n";
	if ($options['status'] == "active") {
		print "  - User is online!\n";
		continue;
	}
	$workspace = setup_paths($username)['workspace'];
	if (array_key_exists('volatile-remote', $options)) {
		print " - volatile\n";
		$inuse = "/lhome/" . substr($username,0,1) . "/" . $username . "/.in_use";
		if (file_exists($inuse)) {
				print "Deleting\n";
				$home = "/home/" . substr($username,0,1) . "/" . $username;
				$svn = "/home/c9/svn/" . $username;
				`rm -rf $home`;
				`rm -rf $svn`;
				unlink($inuse);
		} else {
				print "Not in use\n";
		}
		print "  - sync-local\n";
		exec("/usr/local/webide/bin/webidectl sync-local $username");
		print "gotov sync-local\n";
		if (!file_exists($workspace)) {
			print "  - sync failed!\n";
			continue;
		}
	}
	
	run_as($username, "cd $workspace; svn update .");
	$files = scandir($workspace);
	foreach($files as $file) {
		foreach($courses as $course) {
			if ($file == $course)
				run_as($username, "cd $workspace; svn update $file; svn rename $file $file$old_year");
		}
	}
	run_as($username, "cd $workspace; svn commit -m \"new year\" .");
	print "  - userstats\n";
	exec("$conf_base_path/bin/userstats $username");
	foreach($courses as $course) {
		exec("php $conf_base_path/lib/rename_folder.php $username $course $course$old_year");
	}
	
	if (array_key_exists('volatile-remote', $options)) {
		print "  - sync-remote\n";
		exec("/usr/local/webide/bin/webidectl sync-remote $username");
		$home = setup_paths($username)['home'];
		$svn = setup_paths($username)['svn'];
		exec("rm -fr $home");
		exec("rm -fr $svn");
	}
}

?>
