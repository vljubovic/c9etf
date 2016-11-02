<?php

// Login locally via auth files
function local_login($login, $pass) {
	global $conf_base_path;
	
	if (!file_exists("$conf_base_path/localusers/$login") && $login != "test")
		return "Nepostojeći korisnik";

	$login_esa = escapeshellarg($login);
	$login_efn = escape_filename($login);
	$pass_esa = escapeshellarg($pass);
	
	//print "echo $pass | htpasswd -vi /usr/local/webide/localusers/$login $login";
	$result = `echo $pass_esa | htpasswd -vi $conf_base_path/localusers/$login_efn $login_esa 2>&1`;
	
	/// Same thing via pipes (password not visible in process list...)
	/*$descriptorspec = array(
		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
	);
	$output = array();
	$process = proc_open("htpasswd -vi /usr/local/webide/localusers/$login $login", $descriptorspec, $pipes);
	if (is_resource($process)) {
		fwrite($pipes[0], $pass);
		fclose($pipes[0]);
		$rez = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		proc_close($process);
	}*/
	
	if (strstr($result, "correct") || ($login == "test" && $pass == "test")) { // FIXME
		$_SESSION['login'] = $login;
		$_SESSION['server_session'] = "";
		$_SESSION['userid'] = "";
		$_SESSION['user_type'] = "local";
		session_write_close();
		return "";
	}
	
	return "error";
}

function login($login, $pass) {
	$result = local_login($login, $pass);
	
	// Other logon modules
	if ($result != "" && $conf_zamger) {
		require_once("zamger/login.php");
		return zamger_login($login, $pass);
	}
}

function logout() {
	if ($conf_zamger) zamger_logout();
}

?>