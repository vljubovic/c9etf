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

try {
	$or = Course::find(1, true);
} catch (Exception $exception) {

}
try {
	$uup = Course::find(2234, true);
} catch (Exception $exception) {

}

$courses = [$or, $uup];
$isPartOfGame = false;
$isAdmin = false;
$isStudent = false;
foreach ($courses as $course) {
	if ($course === null) {
		continue;
	}
	if ($course->isAdmin($login) || $course->isStudent($login)) {
		$isPartOfGame = true;
	}
	if ($course->isAdmin($login)) {
		$isAdmin = true;
	}
	if ($course->isStudent($login)) {
		$isStudent = true;
	}
}

if(!$isPartOfGame) {
	jsonResponse(false, 403, array('message' => "You are neither a student nor an admin on this course"));
}

$course = $or;

global $conf_sysadmins;
$action = $_REQUEST["action"];

$canInitialize = in_array($login, $conf_sysadmins) && $action === "initialize";
$resourcesExist = file_exists($course->getPath() . '/game.json')
	&& file_exists($course->getPath() . '/game_files');

if (!$canInitialize && !$resourcesExist) {
	jsonResponse(false, 500, array('message' => "Game not established"));
}

$adminParam = false;
if (isset($_REQUEST["A"])) {
	$adminParam = true;
}

if ($action == "getAssignments") {
	if ($isAdmin && $adminParam) {
		getAdminAssignments($course);
	} elseif ($isStudent) {
		getStudentAssignments($course);
	}
} else if ($action == "createAssignment") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	createAssignment($course);
} else if ($action == "editAssignment") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	editAssignment($course);
} else if ($action == "createTask") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	createTask($course);
} else if ($action == "editTask") {
	if (!$isAdmin) {
		error(403, "Permišn dinajd");
	}
	editTask($course);
} else if ($action == "deleteTask") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	deleteTask($course);
} else if ($action == "getTaskFileContent") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	getFileContent($course);
} else if ($action == "createTaskFile") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	createTaskFile($course);
} else if ($action == "editTaskFile") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	editTaskFile($course);
} else if ($action == "deleteTaskFile") {
	if (!$isAdmin) {
		jsonResponse(false, 403, array('message' => "Permission denied"));
	}
	deleteTaskFile($course);
} else if ($action === "getTasksForAssignment") {
	if (!$isAdmin) {
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
} else if ($action == "resetRetard") {
	if (!$isAdmin) {
		error(403, "Permišn dinajd");
	}
	resetRetard($login);
} else if ($action == "setTokens") {
	if (!$isAdmin) {
		error(403, "Permišn dinajd");
	}
	setTokens($login);
} else if ($action === "check") {
	$roles = [];
	if ($isAdmin) {
		$roles[] = "admin";
	}
	if ($isStudent) {
		$roles[] = "student";
	}
	jsonResponse(true, 200, array("message" => "Ok", "roles" => $roles));
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
