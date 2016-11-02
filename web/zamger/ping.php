<?php

// Periodično pozvati ovu skriptu za oživljavanje sesije

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");
$webide_path = "/usr/local/webide";

session_start();
if (!isset($_SESSION['server_session'])) {
	print "ERROR";
} else {
	$parameters = array();
	$parameters[session_name()] = $_SESSION['server_session'];
	$username_esa = escapeshellarg($_SESSION['login']);
	
	//$result = json_request_retry($conf_json_base_url . "ping.php", $parameters);
	

	//if ($result['success'] == "true")
		if (file_exists($webide_path . "/broadcast.txt"))
			print "BROADCAST: " .file_get_contents($webide_path . "/broadcast.txt");
		else //print join("\n", $result['data']);
			print "OK";
	/*else
		print "ERROR";*/
	
	proc_close(proc_open("sudo $webide_path/bin/webidectl last-update $username_esa &", array(), $foo));
}

?>