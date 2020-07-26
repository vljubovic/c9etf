<?php

// WEBSERVICE auth


session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");

if (isset($_POST['login'])) {
	$login = $_POST['login'];
	$pass = $_POST['password'];
	$admin_login = $_POST['admin_login'];
} else {
	$input = json_decode(file_get_contents('php://input'),true);
	if ($input) {
		$login = $input['login'];
		$pass = $input['password'];
		$admin_login = $input['admin_login'];
	}
}

$error = login($login, $_POST['password']);

if($admin_login) {
	if (!in_array($login,$conf_admin_users)) {
		$error = "You are not an administrator!";
	}
}

ini_set('default_charset', 'UTF-8');
header('Content-Type: application/json; charset=UTF-8');

$result = array();

if ($error == "") {
	$result['success'] = true;
	$result['sid'] = session_id();
	$result['message'] = "Welcome to c9";
} else {
	$result['success'] = false;
	$result['message'] = $error;
}

print json_encode($result);


