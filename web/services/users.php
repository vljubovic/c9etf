<?php

// WEBSERVICE for querying information about users


session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");


// Verify session and permissions, set headers

$logged_in = false;
if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
}

if (!$logged_in) {
	$result = array ('success' => "false", "message" => "You're not logged in");
	print json_encode($result);
	return 0;
}

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$username = escape_filename($_GET['user']);

if (!array_key_exists($username, $users)) {
	$result = array ('success' => "false", "message" => "Unknown user");
	print json_encode($result);
	return 0;
}

$result = array('success' => "true");
$result['data']['username'] = $username;
$result['data']['realname'] = "";
if (array_key_exists('realname', $users[$username])) $result['data']['realname'] = $users[$username]['realname'];
$result['data']['status'] = $users[$username]['status'];

$result['data']['last'] = 0;
$last_file = $conf_base_path . "/last/$username.last";
if (file_exists($last_file)) $result['data']['last'] = file_get_contents($last_file);

print json_encode($result);

?>
