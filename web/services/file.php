<?php

// WEBSERVICE for user file access


header('Content-Encoding: none;');
session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");


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


if (in_array($login, $conf_admin_users) && isset($_GET['user']))
	$username = escape_filename($_GET['user']);
else
	$username = $login;


$path = str_replace("/../", "/", $_GET['path']);
while (strlen($path) > 3 && substr($path,0,3) == "../") $path = substr($path,3);

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
		echo date($_REQUEST['format'], `sudo $conf_base_path/bin/wsaccess $username filemtime "$path"`);
	else
		passthru("sudo $conf_base_path/bin/wsaccess $username filemtime \"$path\"");
}


?>
