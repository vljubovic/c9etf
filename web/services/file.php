<?php

// WEBSERVICE for user file access


session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");
require_once("../classes/Course.php");


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

session_write_close();

// If user is not admin, they can only access their own files
if (in_array($login, $conf_admin_users) && isset($_GET['user']))
	$username = escape_filename($_GET['user']);
else
	$username = $login;


$path = "";
if (isset($_GET['path'])) $path = str_replace("/../", "/", $_GET['path']);
while (strlen($path) > 3 && substr($path,0,3) == "../") $path = substr($path,3);


// Check "course" part of path for users that are just admins (but not sysadmins)
if (in_array($login, $conf_admin_users) && !in_array($login, $conf_sysadmins)) {
	$user = new User($login);
	$perms = $user->permissions();
	
	// Tree root request
	$tree_root = false;
	if (isset($_REQUEST['type']) && $_REQUEST['type'] == "tree" && $path == "/")
		$tree_root = true;
	
	if (!$tree_root && !empty($perms)) {
		$found = false;
		// FIXME: Use Assignment::fromWorkspacePath
		foreach($perms as $course_string) {
			// Convert course string into Course
			$course = Course::fromString($course_string);
			$cpath = $course->abbrev;
			if ($course->year != $conf_current_year) $cpath .= (2004 + $course->year);
			
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


if (!isset($_REQUEST['type']) || $_REQUEST['type'] == "file") {
	passthru("sudo $conf_base_path/bin/wsaccess $username read \"$path\"");
} else if ($_REQUEST['type'] == "git") {
	$rev = escapeshellarg($_REQUEST['rev']);
	passthru("sudo $conf_base_path/bin/wsaccess $username git-show \"$path\" $rev");
}
else if ($_REQUEST['type'] == "svn") {
	$svn_user_path = setup_paths($username)['svn'];
	$rev = intval($_REQUEST['rev']);
	$svn_file_path = "file://" . $svn_user_path . "/$path";
	passthru("svn cat -r$rev file://" . $svn_user_path . "/$path");
}
else if ($_REQUEST['type'] == "tree") {
//	echo "sudo $conf_base_path/bin/wsaccess $username list \"$path\"";
	passthru("sudo $conf_base_path/bin/wsaccess $username list \"$path\"");
}
else if ($_REQUEST['type'] == "exists") {
	passthru("sudo $conf_base_path/bin/wsaccess $username exists \"$path\"");
}
else if ($_REQUEST['type'] == "or_game") {
	$code = $_REQUEST['code'];
	$path = "or_game";
	passthru("sudo $conf_base_path/bin/wsaccess $username mkdir \"$path\"");
	$path .= "/main.c";

	$descriptorspec = array(
		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
	);
	$output = array();
	$process = proc_open("sudo $conf_base_path/bin/wsaccess $username write \"$path\"", $descriptorspec, $pipes);
	if (is_resource($process)) {
		fwrite($pipes[0], $code);
		fclose($pipes[0]);
		$rez = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		proc_close($process);
	}
}
else if ($_REQUEST['type'] == "mtime") {
	$mtime = `sudo $conf_base_path/bin/wsaccess $username filemtime "$path"`;
	if (strstr($mtime, "ERROR"))
		echo "";
	else if (isset($_REQUEST['format']))
		echo date($_REQUEST['format'], chop($mtime));
	else
		echo $mtime;
}
else {
	$result = array ('success' => "false", "message" => "Unknown request type");
	print json_encode($result);
	return 0;
}

if (!isset($_REQUEST['type']))
	admin_log("file.php - file - $username - $path");
else if ($_REQUEST['type'] != "or_game")
	admin_log("file.php - ".$_REQUEST['type']." - $username - $path");

?>
