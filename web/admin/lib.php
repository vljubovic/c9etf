<?php

// ADMIN/LIB.PHP - library of common function for admin web interface


// Write message to admin log
function admin_log($msg) {
	global $login, $conf_base_path;
	$msg = date("Y-m-d H:i:s") . " - $login - $msg\n";
	file_put_contents("$conf_base_path/log/admin.php.log", $msg, FILE_APPEND);
}

// Check if user has permissions to access admin UI
function admin_session() {
	global $login, $conf_admin_users;

	session_start();
	$logged_in = false;
	if (isset($_SESSION['login'])) {
		$login = $_SESSION['login'];
		$session_id = $_SESSION['server_session'];
		if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
	}

	if (!$logged_in || !in_array($login, $conf_admin_users)) return false;
	return true;
}

// Standard HTTP headers for admin
function admin_set_headers() {
	ini_set('default_charset', 'UTF-8');
	header('Content-Type: text/html; charset=UTF-8');
}

// List of permissions for a given user
function admin_permissions($login) {
	global $conf_sysadmins, $conf_data_path;
	$perms = array();
	
	// Sysadmins can see all courses, other just those they are teachers for
	if (!in_array($login, $conf_sysadmins)) {
		$perms_path = $conf_data_path . "/permissions.json";
		if (file_exists($perms_path)) {
			$all_perms = json_decode(file_get_contents($perms_path), true);
			if (array_key_exists($login, $all_perms)) $perms = $all_perms[$login];
		}
	}
	return $perms;
}

// Check if user has permission for admin access to a specific course
function admin_check_permissions($course, $year, $external) {
	global $login, $conf_sysadmins;
	
	if (in_array($login, $conf_sysadmins)) return;
	
	$perms = admin_permissions($login, $year);
	$cid = "$course" . "_$year";
	if ($external) $cid = "X$cid";
	foreach($perms as $perm) {
		if ($perm == $cid) return;
	}
	?>
	<p style="color:red; weight: bold">You don't have permission to access this page.</p>
	<?php
	exit(0);
}


// Get list of all courses
function admin_courses() {
	global $conf_data_path;
	
	$courses_path = $conf_data_path . "/courses.json";
	if (file_exists($courses_path))
		return json_decode(file_get_contents($courses_path), true);
	return array();
}

// Get data on specific course
function admin_courses_get($course, $external) {
	$courses = admin_courses();
	foreach ($courses as $c) {
		if ($c['id'] == $course && $external==1 && $c['type'] == 'external')
			return $c;
		if ($c['id'] == $course && $external==0 && $c['type'] != 'external')
			return $c;
	}
	return false;
}

function admin_course_path($course, $year, $external) {
	global $conf_data_path;
	
	$course_path_part = "$course" . "_$year";
	if ($external) $course_path_part = "X$course_path_part";
	return $conf_data_path . "/$course_path_part";
}

?>
