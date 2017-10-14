<?php

require_once("config.php");
require_once("webidelib.php");

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$courses = array("OR", "TP", "ASP");
$old_year = 2016;

foreach($users as $username => $options) {
	print "$username:\n";
	$workspace = setup_paths($username)['workspace'];
	run_as($username, "cd $workspace; svn update .");
	$files = scandir($workspace);
	foreach($files as $file) {
		foreach($courses as $course) {
			if ($file == $course)
				run_as($username, "cd $workspace; svn rename $file $file$old_year");
		}
	}
	run_as($username, "cd $workspace; svn commit -m \"new year\" .");
}

?>
