<?php

// ADMIN/LIB.PHP - library of common function for admin web interface


// Write message to admin log
function admin_log($msg) {
	global $login, $conf_base_path;
	$msg = date("Y-m-d H:i:s") . " - $login - $msg\n";
	file_put_contents("$conf_base_path/log/admin.php.log", $msg, FILE_APPEND);
}

// Check if user has permissions to access admin UI and specific course
function admin_check_permissions($course = 0, $year = 0) {
	global $conf_admin_users;

	session_start();
	$logged_in = false;
	if (isset($_SESSION['login'])) {
		$login = $_SESSION['login'];
		$session_id = $_SESSION['server_session'];
		if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
	}

	if (!$logged_in || !in_array($login, $conf_admin_users)) {
		?>
		<p style="color:red; weight: bold">Your session expired. Please log out then log in.</p>
		<?php
		exit(0);
	}
	
	if ($course != 0) {
		// TODO
	}
}

// Standard HTTP headers for admin
function admin_set_headers() {
	ini_set('default_charset', 'UTF-8');
	header('Content-Type: text/html; charset=UTF-8');
}

?>
