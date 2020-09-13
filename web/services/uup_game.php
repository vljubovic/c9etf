<?php
$game_server_url = "http://localhost:8183";
//$request = curl_init("http://localhost:8183/uup-game/assignments/all");
//curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
//$response = curl_exec($request);
//$request = curl_init("http://localhost:8183/uup-game/assignments/2/mmesihovic1/start");
//curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
//curl_setopt($request, CURLOPT_POST, 1);
//$response = curl_exec($request);
//
//
//
//header('Content-type:application/json;charset=utf-8');
//
//if ($response == false) {
//	$data = array('success'=> false, 'message'=> 'Ne radi');
//	print(json_encode($data, JSON_PRETTY_PRINT));
//} else {
//	$response = json_decode($response,true);
//	print(json_encode($response, JSON_PRETTY_PRINT));
//}
//return;



session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");
require_once("../classes/Course.php");
require_once("../classes/Cache.php");
require_once("../classes/User.php");
require_once("./helpers/assignment.php");
require_once("./helpers/common.php");
require_once("./../classes/FSNode.php");

eval(file_get_contents("../../users"));

$login = '';
// Verify session and permissions, set headers
$logged_in = false;
if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/", $login)) $logged_in = true;
}

if (!$logged_in) {
	$result = array('success' => "false", "message" => "You're not logged in");
	print json_encode($result);
	return 0;
}

session_write_close();



$error = "";

if (!isset($_REQUEST["course_id"])) {
	error("400", "You need to specify course_id request parameter");
}
$external = false;
if (isset($_REQUEST["X"])) {
	$external = true;
}
global $conf_current_year;
$year = $conf_current_year;
if (isset($_REQUEST["year"])) {
	$year = intval($_REQUEST["year"]);
}

try {
	$course = Course::find($_REQUEST["course_id"], $external);
	$course->year = $year;
	
	if (!$course->isAdmin($login) && !$course->isStudent($login)) {
		error("403", "You are neither a student nor an admin on this course");
	}
} catch (Exception $e) {
	error("500", $e->getMessage());
}
global $conf_sysadmins;


$action = $_REQUEST["action"];

if ($action == "getAssignments") {
	$request = curl_init("$game_server_url/uup-game/assignments/all");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($request);
	message_and_data("getAssignments endpoint",json_decode($response,true));
} else if($action == "getTasksForAssignment"){
	message("Not yet implemented");
} else {
	error("422", "Unknown action");
}

// /uup-game/assignments/all - All assignments - all - get
// /uup-game/assignments/:id/tasks - All tasks for assignment - Admin - get
// /uup-game/assignments/:id - Single assignment - Admin - get
// /uup-game/assignments/create - Single assignment - Admin - post - body: name,active,points,challenge_pts
// /uup-game/assignments/:id - Single assignment - Admin - put - body: name,active,points,challenge_pts
// /uup-game/tasks/categories/all - All categories for tasks - all - get
// /uup-game/tasks/categories/update/:id - update category - Admin - put - body: name, points_percent, tokens, tasks_per_category //not yet implemented
// /uup-game/tasks/:id - Get task - Admin - get
// /uup-game/tasks/create - Create task - Admin - post - body: task_name, assignment_id, category_id, hint
// /uup-game/tasks/update/:id - Delete task - Admin - put
// /uup-game/tasks/:id - Delete task - Admin - delete
//