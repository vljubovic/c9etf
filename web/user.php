<?php

session_start();
$logged_in = false;

if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
}

if (!$logged_in) {
	header("Location: index.php");
	return;
}

session_write_close();

require_once("../lib/config.php");

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$userdata = array( "id" => $login, "fullname" => "", "email" => "" );
if (array_key_exists("realname", $users[$login]))
	$userdata['fullname'] = $users[$login]['realname'];
if (array_key_exists("email", $users[$login]))
	$userdata['email'] = $users[$login]['email'];

print json_encode($userdata, JSON_PRETTY_PRINT);

?>
