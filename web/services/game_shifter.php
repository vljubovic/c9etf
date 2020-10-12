<?php
require_once("./../classes/User.php");
require_once("./../classes/Course.php");
require_once("./helpers/common.php");


if (!isset($_REQUEST["key"])) {
	jsonResponse(false, 400, array("message" => "Key not provided"));
}

$key = $_REQUEST["key"];

// Well...
$course = null;
try {
	$course = Course::find(1, true);
} catch (Exception $exception) {
	jsonResponse(false, 500, array("message" => $exception->getMessage()));
}

$serverKey = file_get_contents("/usr/local/webide/data/__gameServerKey");

if ($serverKey === false || $serverKey === "-" || $serverKey === "") {
	jsonResponse(false, 400, array("message" => "Server key is not valid"));
}

if ($serverKey !== $key) {
	jsonResponse(false, 400, array("message" => "Server key is not valid"));
}


jsonResponse(false, 500, array("message"=>"Not yet implemented"));
