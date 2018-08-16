<?php

// =========================================
// CHECK_USERS.PHP
// C9@ETF project (c) 2015-2018
// 
// Check if all users have home and passwd entry
// =========================================



require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


// Get users from "users" file
$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));
$file_users = array_keys($users);
sort($file_users);

// Check /etc/passwd
$passwd_users = array();
foreach(file("/etc/passwd") as $pwdline) {
	$pwd = explode(":", $pwdline);
	if ($pwd[3] === "1002" && $pwd[2] !== "1002") {
		$passwd_users[] = $pwd[0];
	}
}
sort($passwd_users);

// Check rhome
$rhome_users = array();
foreach(scandir($conf_home_path) as $homedir) {
	$hpath = $conf_home_path . "/$homedir";
	if (strlen($homedir) == 1 && $homedir != "." && is_dir($hpath)) {
		foreach(scandir($hpath) as $userdir) {
			$upath = $hpath . "/$userdir";
			if ($userdir != "." && $userdir != ".." && is_dir($upath))
				$rhome_users[] = $userdir;
		}
	}
}
sort($rhome_users);

$i=$j=$k=0;
while($i<count($file_users) && $j<count($passwd_users) && $k<count($rhome_users)) {
	//print "Comparing $file_users[$i] $passwd_users[$j] $rhome_users[$k]\n";
	if ($file_users[$i] == $passwd_users[$j] && escape_filename($file_users[$i]) == $rhome_users[$k]) {
		$i++; $j++; $k++;
		continue;
	}
	if ($file_users[$i] == $passwd_users[$j] && escape_filename($file_users[$i]) < $rhome_users[$k]) {
		//print "User without home $file_users[$i]\n";
		$i++; $j++;
		continue;
	}
	if (escape_filename($file_users[$i]) == $rhome_users[$k] && $file_users[$i] < $passwd_users[$j]) {
		print "User without passwd $file_users[$i]\n";
		$i++; $k++;
		continue;
	}
	if ($file_users[$i] < $passwd_users[$j]) {
		print "User without passwd and home $file_users[$i]\n";
		$i++;
		continue;
	}
	if (escape_filename($passwd_users[$j]) == $rhome_users[$k] && $file_users[$i] > $passwd_users[$j]) {
		print "User has passwd and home but not in users file $passwd_users[$j]\n";
		$j++; $k++;
		continue;
	}
	if ($passwd_users[$j] < $file_users[$i] && escape_filename($passwd_users[$j]) < $rhome_users[$k]) {
		print "Unknown user in passwd file $passwd_users[$j]\n";
		$j++;
		continue;
	}
	print "Unknown home $rhome_users[$k]\n";
	$k++;
}

while ($i<count($file_users)) {
	print "User without passwd and home $file_users[$i]\n";
	$i++;
}
while ($j<count($passwd_users)) {
	print "Unknown user in passwd file $passwd_users[$j]\n";
	$j++;
}
while ($k<count($rhome_users)) {
	print "Unknown home $rhome_users[$k]\n";
	$k++;
}

?>
