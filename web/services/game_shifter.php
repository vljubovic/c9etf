<?php
require_once("./../classes/User.php");
require_once("./../classes/Course.php");
require_once("./helpers/common.php");

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
	jsonResponse(false, 400, array("message" => "No body"));
}

validateRequired(["key","username","assignment_id","oldTask_id","newTask_id","redo"],$input);

$key = $input["key"];
$username = $input["username"];
$assignmentId = intval($input["assignment_id"]);
$oldTaskId = intval($input["oldTask_id"]);
$newTaskId = intval($input["newTask_id"]);
$redo = boolval($input["redo"]);

// Well...
$course = null;
try {
	$course = Course::find(1, true);
} catch (Exception $exception) {
	jsonResponse(false, 500, array("message" => $exception->getMessage()));
}

$serverKey = file_get_contents("/usr/local/webide/data/__gameServerKey");

if ($serverKey === false || $serverKey === "-" || $serverKey === "" || $serverKey !== $key) {
	jsonResponse(false, 400, array("message" => "Server key is not valid"));
}

if ($redo === false) {
	if ($oldTaskId < 0) {
		// just add the files from game to student
	} else {
		// save student files to history and add files from game to student
	}
}else{
	if ($oldTaskId < 0) {
		// just add the files from history to student
	} else {
		// save student files to history and add files from history to student
	}
}

jsonResponse(false, 500, array("message"=>"Not yet implemented"));
