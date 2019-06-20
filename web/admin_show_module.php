<?php

// External script to load module for admin

session_start();
require_once("../lib/config.php");
require_once("../lib/webidelib.php");
require_once("login.php");

// Currently supported modules are
require_once("admin/activity_log.php");
require_once("admin/svn_log.php");
require_once("admin/git_log.php");


// Verify session and permissions
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
	return 0;
}

// Start module
$module = $_REQUEST['module'];
$user = basename($_REQUEST['user']);
$path = $_REQUEST['path'];

if ($module == "activity")
	admin_activity_log($user, $path);
if ($module == "svn")
	admin_svn_log($user, $path);
if ($module == "git")
	admin_git_log($user, $path);
if ($module == "deleted")
	//admin_deleted_files($user, $path);
	print "Not implemented yet";

?>
