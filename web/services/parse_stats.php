<?php


// Parse stats web-service is allowed only to admin

header('Content-Encoding: none;');
session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/parse_stats.php");


ini_set('default_charset', 'UTF-8');
header('Content-Type: text/json; charset=UTF-8');


if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
} else {
	json(error("ERR001", "Not logged in"));
}

// Check if user is admin
if (!in_array($login, $conf_admin_users))
	json(error("ERR007", "Insufficient privileges"));

if (!isset($_REQUEST['user']))
	json(error("ERR999", "Missing user"));
$user = $_REQUEST['user'];

$assignments = $stats = $last_access = array();
parse_stats($user, $assignments, $stats, $last_access, $_REQUEST['path']);

$result = array();
$result['success'] = "true";
$result['message'] = $msg;
$result['data'] = array();
$result['data']['assignments'] = $assignments;
$result['data']['stats'] = $stats;
$result['data']['last_access'] = $last_access;
if (isset($_REQUEST['date_format'])) {
	$result['data']['last_access'][$user] = date($_REQUEST['date_format'], $last_access[$user]);
	if ($last_access[$user] == 0 || $last_access[$user] == false) $result['data']['last_access'][$user] = 0;
}

if (defined("JSON_PRETTY_PRINT"))
	print json_encode($result, JSON_PRETTY_PRINT);
else
	print json_encode($result);


?>
