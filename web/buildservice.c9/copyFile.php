<?php

// COPYFILE.PHP
// Web service that copies the .at_result file into user workspace

function error($code, $msg) {
	print json_encode(get_error($code, $msg));
	exit(0);
}

function get_error($code, $msg) {
	$result = array();
	$result['success'] = "false";
	$result['code'] = $code;
	$result['message'] = $msg;
	return $result;
}

// Separate function to avoid polluting configuration space
function extract_push_url() {
	require_once("client/classes/Config.php");
	return $conf_push_url;
}

require_once("client/clientlib.php");
require_once("../../lib/config.php");
$conf_push_url = extract_push_url();

session_start();

// This script is only used from admin, so check admin session

if (!isset($_SESSION['login']))
	error("ERR010", "Not logged in");

$login = $_SESSION['login'];
if (!preg_match("/[a-zA-Z0-9]/",$login) || !in_array($login, $conf_admin_users))
	error("ERR010", "Access denied");


$program = intval($_REQUEST['program']);
$username = $_REQUEST['username'];
$path = $_REQUEST['filename']; // Misnamed parameter

eval(file_get_contents("../../users"));
if (!array_key_exists($username, $users))
	error("ERR020", "Unknown user $username");

if (`sudo $conf_base_path/bin/wsaccess $username exists "$path"` != 1)
	error("ERR030", "Path $path doesn't exist");

// If program doesn't exist, json_query will fail (and unfortunately, not return a valid JSON)
$result = json_query("getResult", array("id" => $program), "POST" );
$tmpFile = tempnam("/tmp", "TESTRESULT");
file_put_contents($tmpFile, json_encode($result, JSON_PRETTY_PRINT));
$filename = "$path/.at_result";

`sudo $conf_base_path/bin/wsaccess $username deploy "$filename" $tmpFile`;
//unlink($tmpFile);

$result = array();
$result['success'] = "true";
$result['message'] = "File copied";
print json_encode($result);
