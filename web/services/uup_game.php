<?php
$game_server_url = "http://localhost:8183";

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
require_once("./../classes/GameNode.php");
require_once("./../classes/RequestBuilder.php");
require_once("./helpers/uup_game.php");

eval(file_get_contents("../../users"));

list($login, $logged_in, $session_id) = verifySession();

if (!$logged_in) {
	$result = array('success' => "false", "message" => "You're not logged in");
	print json_encode($result);
	return 0;
}

session_write_close();

$error = "";

if (!isset($_REQUEST["course_id"])) {
	error(400, "You need to specify course_id request parameter");
}

$course = extractCourseFromRequest();

if (!$course->isAdmin($login) && !$course->isStudent($login)) {
	error(403, "You are neither a student nor an admin on this course");
}

global $conf_sysadmins;
$action = $_REQUEST["action"];

$canInitialize = in_array($login, $conf_sysadmins) && $action === "initialize";
$resourcesExist = file_exists($course->getPath() . '/game.json')
						&& file_exists($course->getPath() . '/game_files');

if (!$canInitialize && !$resourcesExist) {
	error("500", "Game not established");
}

if ($action == "getAssignments") {
	if ($course->isStudent($login)) {
		getStudentAssignments();
	} elseif ($course->isAdmin($login)) {
		getAdminAssignments($course);
	}
} else if ($action == "createAssignment") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	createAssignment($course);
} else if ($action == "editAssignment") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	editAssignment($course);
} else if ($action == "createTask") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	createTask($course);
} else if ($action == "editTask") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	editTask($course);
} else if ($action == "editTask") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	editTask($course);
} else if ($action == "deleteTask") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	deleteTask($course);
} else if ($action == "createTaskFile") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	createTaskFile($course);
} else if ($action == "editTaskFile") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	editTaskFile($course);
} else if ($action == "deleteTaskFile") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	deleteTaskFile($course);
} else if ($action === "getTasksForAssignment") {
	if ($course->isAdmin($login)) {
		$assignment_id = null;
		if (isset($_REQUEST['assignment_id'])) {
			$assignment_id = $_REQUEST['assignment_id'];
		} else {
			error("400", "Assignment id is not set in query");
		}
		$request = curl_init("$game_server_url/uup-game/assignments/$assignment_id/tasks");
		curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($request);
		curl_close($request);
		if (curl_errno($request) !== 0) {
			error("500", "Failed get tasks for assignment");
		}
		message_and_data("TasksForAssignment", json_decode($response, true));
	}
} else if ($action == "getTaskCategories") {
	getTaskCategories();
} else if ($action == "getPowerUpTypes") {
	$request = curl_init("$game_server_url/uup-game/powerups/types");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($request);
	curl_close($request);
	message_and_data("getPowerUpTypes endpoint", json_decode($response, true));
} else if ($action == "getChallengeConfig") {
	$request = curl_init("$game_server_url/uup-game/challenge/config");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($request);
	curl_close($request);
	message_and_data("getChallengeConfig endpoint", json_decode($response, true));
} else if ($action == "getStudentData") {
	$request = curl_init("$game_server_url/uup-game/$login");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($request);
	curl_close($request);
	message_and_data("getStudentData endpoint", json_decode($response, true));
} else if ($action == "buyPowerUp") {
	$powerUpType = $_REQUEST["type_id"];
	if ($powerUpType === null) {
		error(400, "Set the powerUptType field");
	}
	$request = curl_init("http://localhost:8183/uup-game/powerups/buy/$login/$powerUpType");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($request, CURLOPT_POST, true);
	
	$response = json_decode(curl_exec($request), true);
	if (curl_errno($request) !== 0) {
		error(500, "Internal Error");
	}
	curl_close($request);
	message_and_data("OK", $response);
} else if ($action == "startAssignment") {
	$assignmentId = $_REQUEST["assignment_id"];
	if ($assignmentId === null) {
		error(400, "Set the assignment_id field");
	}
	$request = curl_init("http://localhost:8183/uup-game/assignments/$assignmentId/$login/start");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($request, CURLOPT_POST, true);
	
	$response = json_decode(curl_exec($request), true);
	if (curl_errno($request) !== 0) {
		error(500, "Internal Error");
	}
	curl_close($request);
	message_and_data("OK", $response);
} else if ($action == "turnTaskIn") {
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$assignmentId = $_REQUEST['assignment_id'];
		$request = curl_init("http://localhost:8183/uup-game/tasks/turn_in/$login/$assignmentId");
		curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($request, CURLOPT_POST, true);
		curl_setopt($request, CURLINFO_HEADER_OUT, true);
		curl_setopt($request, CURLOPT_POSTFIELDS, $input);
		
		curl_setopt($request, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($input))
		);
		
		$response = json_decode(curl_exec($request), true);
		if (curl_errno($request) !== 0) {
			error(500, "Internal Error");
		}
		curl_close($request);
		
		message_and_data("Task turned in", $response);
	}
} else if ($action == "swapTask") {
	$assignmentId = $_REQUEST['assignment_id'];
	$request = curl_init("http://localhost:8183/uup-game/tasks/swap/$login/$assignmentId");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($request, CURLOPT_POST, true);
	
	$response = json_decode(curl_exec($request), true);
	if (curl_errno($request) !== 0) {
		error(500, "Internal Error");
	}
	curl_close($request);
	
	message_and_data("Task swapped", $response);
} else if ($action == "hint") {
	$assignmentId = $_REQUEST['assignment_id'];
	$request = curl_init("http://localhost:8183/uup-game/tasks/hint/$login/$assignmentId");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($request, CURLOPT_POST, true);
	
	$response = json_decode(curl_exec($request), true);
	if (curl_errno($request) !== 0) {
		error(500, "Internal Error");
	}
	curl_close($request);
	
	message_and_data("Hint retrieved", $response);
} else if ($action == "getAvailableTasks") {
	$assignmentId = $_REQUEST['assignment_id'];
	$typeId = $_REQUEST['type_id'];
	$request = curl_init("$game_server_url/uup-game/tasks/turned_id/$login/$assignmentId/$typeId");
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($request);
	curl_close($request);
	message_and_data("getAvailableTasks endpoint", json_decode($response, true));
} else if ($action == "secondChance") {
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$assignmentId = $_REQUEST['assignment_id'];
		$request = curl_init("http://localhost:8183/uup-game/tasks/second_chance/$login/$assignmentId");
		curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($request, CURLOPT_PUT, true);
		curl_setopt($request, CURLINFO_HEADER_OUT, true);
		curl_setopt($request, CURLOPT_POSTFIELDS, $input);
		
		curl_setopt($request, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($input))
		);
		
		$response = json_decode(curl_exec($request), true);
		if (curl_errno($request) !== 0) {
			error(500, "Internal Error");
		}
		curl_close($request);
		
		message_and_data("Second chance", $response);
	}
} elseif ($action == "isGameCourse") {
	if ($course->isAdmin($login)) {
		if ($course->abbrev === "UUP" || $course->abbrev === "OR") {
			message("Yes");
		} else {
			error(503, "No");
		}
	}
} else if ($action === "initialize") {
	initializeGame($course);
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
// /uup-game/tasks/update/:id - Update task - Admin - put
// /uup-game/tasks/:id - Delete task - Admin - delete
//