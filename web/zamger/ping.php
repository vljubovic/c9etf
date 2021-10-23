<?php

// Periodično pozvati ovu skriptu za oživljavanje sesije

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");
require_once(__DIR__."/../../lib/config.php");
require_once($conf_base_path . "/lib/webidelib.php");

session_start();
if (!isset($_SESSION['server_session'])) {
	print "OK session_expired";
} else {
	$parameters = array();
	$parameters[session_name()] = $_SESSION['server_session'];
	$login = $_SESSION['login'];
	$username_esa = escapeshellarg($login);
	$username_efn = escape_filename($login);
		
	//$result = json_request_retry($conf_json_base_url . "ping.php", $parameters);
	
	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));

	if ($users[$login]['status'] == "active")
		if (file_exists($conf_base_path . "/broadcast.txt"))
			print "BROADCAST: " .file_get_contents($conf_base_path . "/broadcast.txt");
		else //print join("\n", $result['data']);
			print "OK " . $_SESSION['login'];
	else
		print "ERROR";
	
	//proc_close(proc_open("sudo $conf_base_path/bin/webidectl last-update $username_esa &", array(), $foo));
	$lastfile = $conf_home_path . "/last/$username_efn.last";
	file_put_contents($lastfile, time());
}

?>
