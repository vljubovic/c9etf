<?php

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");


// Prijava na Zamger putem web servisa

function zamger_login($username, $password) {
	// Globalne promjenljive potrebne za json_login funkciju...
	global $conf_json_user, $conf_json_pass, $session_id;

	$old_login = $_COOKIE['old_login'];
	//if (!empty($old_login) && $old_login != $username && $_SERVER['REMOTE_ADDR'] != "80.65.65.76") return "Pogrešni pristupni podaci";
	
	$conf_json_user = $username;
	$conf_json_pass = $password;
	$result = json_login();
	
	if ($result == -5) {
		return "Pogrešni pristupni podaci.";
	}
	
	$session_id = $result['sid'];
	$zamger_userid = $result['userid'];

	//session_regenerate_id(); // prevent session fixation
	setcookie('old_login', $username);
	$_SESSION['login'] = $username;
	$_SESSION['password'] = $password;
	$_SESSION['server_session'] = $session_id;
	$_SESSION['userid'] = $zamger_userid;
	$_SESSION['user_type'] = "zamger";
	session_write_close();
	
	return "";
}


function zamger_logout() {
	// Nema potrebe da obavještavamo Zamger
	return "";
}


?>