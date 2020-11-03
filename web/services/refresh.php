<?php


session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");


eval(file_get_contents("../../users"));
require_once("../phpwebide/phpwebide.php");

// Verify session and permissions, set headers

$logged_in = false;
if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/", $login)) $logged_in = true;
}
header('Content-type:application/json;charset=utf-8');
if (!$logged_in) {
	$result = array('success' => false, "message" => "You're not logged in");
	print json_encode($result);
	return 0;
}

session_write_close();
$error = "";
global $conf_sysadmins, $conf_admin_users;
$result['success'] = true;
$result['message'] = 'You are logged in';
$result['username'] = $login;
$result['sid'] = $session_id;
if (in_array($login,$conf_sysadmins)) {
	$result['roles'][] = 'sysadmin';
}
if (in_array($login,$conf_admin_users)) {
	$result['roles'][] = 'admin';
}
if (in_array($login, $conf_game_spectators)) {
	$result['roles'][] = 'game-spectator';
}
if (count($result['roles']) === 0) {
	$result['roles'] = 'student';
}

print json_encode($result);
