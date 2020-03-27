<?php

// =========================================
// CHECK_GIT.PHP
// C9@ETF project (c) 2015-2018
// 
// Check git status
// =========================================



require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


// Get users from "users" file
$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$i = 0;
$total = count($users);

foreach($users as $username => $data) {
	$i++;
	print "$username ($i/$total): ";
	$home = setup_paths($username)['home'];
	if (!file_exists($home)) {
		print "moved.\n";
		continue;
	}
	$output = `cd $home/workspace; git status`;
	if (!strstr($output, "working tree clean"))
		print substr(str_replace("\n", "", $output), 0, 100) . "\n";
	else print "ok\n";
}

?>
