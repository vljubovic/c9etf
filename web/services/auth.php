<?php

// WEBSERVICE auth


session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");

$login = $pass = "";

if (isset($_POST['login'])) {
	$login = $_POST['login'];
	$pass = $_POST['password'];
} else {
	$input = json_decode(file_get_contents('php://input'),true);
	if ($input) {
		$login = $input['login'];
		$pass = $input['password'];
	}
}

$error = login($login, $pass);


ini_set('default_charset', 'UTF-8');
header('Content-Type: application/json; charset=UTF-8');

$result = array();

if ($error == "") {
	$result['success'] = true;
	$result['sid'] = session_id();
	$result['message'] = "Welcome to c9 $login";
	$result['roles'] = [];
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
} else {
	$result['success'] = false;
	$result['message'] = $error;
}

print json_encode($result);


