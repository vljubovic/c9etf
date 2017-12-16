<?php

// WEBSERVICE for user file access


session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");


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


// If user is not admin, they can only access their own files
if (in_array($login, $conf_admin_users) && isset($_GET['user']))
	$username = escape_filename($_GET['user']);
else
	$username = $login;


$path = str_replace("/../", "/", $_GET['path']);
while (strlen($path) > 3 && substr($path,0,3) == "../") $path = substr($path,3);


// Check "course" part of path for users that are just admins (but not sysadmins)
if (in_array($login, $conf_admin_users) && !in_array($login, $conf_sysadmins)) {
	$perms = admin_permissions($login);
	
	// Tree root request
	$tree_root = false;
	if (isset($_REQUEST['type']) && $_REQUEST['type'] == "tree" && $path == "/")
		$tree_root = true;
	
	if (!$tree_root && !empty($perms)) {
		$found = false;
		foreach($perms as $course) {
			// Convert course string into numbers
			$external = 0;
			if ($course[0] == "X") {
				$external = 1;
				$course = substr($course, 1);
			}
			
			$cyear = intval(substr($course, strpos($course,"_")+1));
			$course = intval($course); // drop year part
			
			// Get "abbrev" from course data to form path
			$data = admin_courses_get($course, $external);
			$cpath = $data['abbrev'];
			if ($cyear != $conf_current_year) $cpath .= (2004 + $cyear);
			
			//print "course $course external $external cyear $cyear cpath $cpath\n";
			if ($path == $cpath || starts_with($path, $cpath . "/") || starts_with($path, "/" . $cpath . "/")) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$result = array ('success' => "false", "message" => "Permission denied");
			print json_encode($result);
			return 0;
		}
	}
}

if (!isset($_REQUEST['type']) || $_REQUEST['type'] == "file")
	passthru("sudo $conf_base_path/bin/wsaccess $username read \"$path\"");
else if ($_REQUEST['type'] == "git") {
	$rev = escapeshellarg($_REQUEST['rev']);
	passthru("sudo $conf_base_path/bin/wsaccess $username git-show \"$path\" $rev");
}
else if ($_REQUEST['type'] == "svn") {
	$svn_user_path = setup_paths($username)['svn'];
	$rev = intval($_REQUEST['rev']);
	$svn_file_path = "file://" . $svn_user_path . "/$path";
	echo svn_cat($svn_file_path, $rev);
}
else if ($_REQUEST['type'] == "tree") {
	passthru("sudo $conf_base_path/bin/wsaccess $username list \"$path\"");
}
else if ($_REQUEST['type'] == "exists") {
	passthru("sudo $conf_base_path/bin/wsaccess $username exists \"$path\"");
}
else if ($_REQUEST['type'] == "mtime") {
	if (isset($_REQUEST['format']))
		echo date($_REQUEST['format'], chop(`sudo $conf_base_path/bin/wsaccess $username filemtime "$path"`));
	else
		passthru("sudo $conf_base_path/bin/wsaccess $username filemtime \"$path\"");
}
else {
	$result = array ('success' => "false", "message" => "Unknown request type");
	print json_encode($result);
	return 0;
}

if (!isset($_REQUEST['type']))
	admin_log("file.php - file - $username - $path");
else
	admin_log("file.php - ".$_REQUEST['type']." - $username - $path");

?>
