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
	jsonResponse(
		false,
		400,
		array("message" => "You're not logged in")
	);
}

session_write_close();

$error = "";
//
//if (!isset($_REQUEST["course_id"])) {
//	error(400, "You need to specify course_id request parameter");
//}
// 2234
//$course = extractCourseFromRequest();

$course = Course::find(1, true);

if (!$course->isAdmin($login) && !$course->isStudent($login)) {
	jsonResponse(false, 403, array('message' => "You are neither a student nor an admin on this course"));
}

global $conf_sysadmins;
$action = $_REQUEST["action"];

$canInitialize = in_array($login, $conf_sysadmins) && $action === "initialize";
$resourcesExist = file_exists($course->getPath() . '/game.json')
	&& file_exists($course->getPath() . '/game_files');

if (!$canInitialize && !$resourcesExist) {
	jsonResponse(false, 500, array('message' => "Game not established"));
}

if ($action == "getAssignments") {
	if ($course->isStudent($login)) {
		getStudentAssignments();
	} elseif ($course->isAdmin($login)) {
		getAdminAssignments($course);
	}
} else if ($action == "createAssignment") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	createAssignment($course);
} else if ($action == "editAssignment") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	editAssignment($course);
} else if ($action == "createTask") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	createTask($course);
} else if ($action == "editTask") {
	if (!$course->isAdmin($login)) {
		error(403, "Permišn dinajd");
	}
	editTask($course);
} else if ($action == "deleteTask") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	deleteTask($course);
} else if ($action == "getTaskFileContent") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	getFileContent($course);
} else if ($action == "createTaskFile") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	createTaskFile($course);
} else if ($action == "editTaskFile") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	editTaskFile($course);
} else if ($action == "deleteTaskFile") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	deleteTaskFile($course);
} else if ($action === "getTasksForAssignment") {
	if (!$course->isAdmin($login)) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	getTasksForAssignment();
} else if ($action == "getTaskCategories") {
	getTaskCategories();
} else if ($action == "getPowerUpTypes") {
	getPowerUpTypes();
} else if ($action == "getChallengeConfig") {
	getChallengeConfig();
} else if ($action == "getStudentData") {
	getStudentData($login);
} else if ($action == "buyPowerUp") {
	buyPowerUp($login);
} else if ($action == "startAssignment") {
	startAssignment($login);
} else if ($action == "turnTaskIn") {
	turnTaskIn($login);
} else if ($action == "swapTask") {
	swapTask($login);
} else if ($action == "hint") {
	hint($login);
} else if ($action == "getAvailableTasks") {
	getAvailableTasks($login);
} else if ($action == "secondChance") {
	secondChance($login);
} else if ($action == "getUsedHint") {
	getUsedHint($login);
} else if ($action == "getTaskPreviousPoints") {
	getTaskPreviousPoints($login);
} else if ($action === "initialize") {
	initializeGame($course);
} else if ($action === "check") {
	if ($course->isAdmin($login)) {
		jsonResponse(true, 200, array("message" => "Ok"));
	} else {
		jsonResponse(false, 400, array("message" => "Not ok"));
	}
} else {
	jsonResponse(false, 422, array("message" => "Invalid action"));
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
