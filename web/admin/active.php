<?php

session_start();

require_once("../../lib/config.php");


if (!isset($_SESSION['login'])) {
	print "Not logged in";
	return 0;
}

$login = $_SESSION['login'];
if (!in_array($login, $conf_admin_users)) {
	print "Not allowed";
	return 0;
}

$lines = 100;

$result=array();

$result['its_now'] = time();
$result['loadavg'] = `cat /proc/loadavg`;
$lasttime = 0;

foreach(explode("\n", `tail -$lines $conf_syncsvn_log`) as $line) { // $conf_syncsvn_log => config.php
	$matches=array();
	if (preg_match("/^PID: (\w+) \d+$/", $line, $matches)) {
		$username = $matches[1];
		$parsed_line = array();
		$parsed_line['datum'] = date("d.m.Y H:i:s", $lasttime);
		$parsed_line['timestamp'] = $lasttime;
		$parsed_line['path'] = "/";
		$parsed_line['file'] = ".login";
		$result[$username]=$parsed_line;
	}
	if (preg_match("/^(\d+\.\d+\.\d+ \d+\:\d+\:\d+) \((\w+)\) - (.*?) - (.*?) - .*?$/", $line, $matches)) {
		$username = $matches[2];
		$parsed_line = array();
		$parsed_line['datum'] = $matches[1];
		$parsed_line['timestamp'] = strtotime($matches[1]);
		$lasttime = strtotime($matches[1]);
		$parsed_line['path'] = $matches[3];
		$parsed_line['file'] = $matches[4];
		$result[$username]=$parsed_line;
	}
}

if (defined("JSON_PRETTY_PRINT"))
	print json_encode($result, JSON_PRETTY_PRINT);
else
	print json_encode($result);

?>