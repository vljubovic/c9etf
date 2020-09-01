<?php

session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");
require_once("../classes/Course.php");
require_once("../classes/Assignment.php");
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

/**
 * @param Course $course
 */
function create_file($course)
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		if (
			!array_key_exists('folderPath', $input)
			|| !array_key_exists('name', $input)
		) {
			error("400", 'body must contain :folderPath: and :name: fields');
		}
		$path = $input["folderPath"];
		$name = $input['name'];
		if (!array_key_exists('show', $input)) {
			$show = true;
		} else {
			$show = boolval($input['show']);
		}
		if (!array_key_exists('binary', $input)) {
			$binary = false;
		} else {
			$binary = boolval($input['binary']);
		}
		if (!array_key_exists('content', $input)) {
			$content = "";
		} else {
			$content = $input["content"];
		}
		
		$fsNode = FSNode::constructTreeForCourse($course);
		$folder = $fsNode->getNodeByPath($path);
		if ($folder == null) {
			error("400", "Invalid path to folder");
		}
		if ($name && check_filename($name)) {
			try {
				$folder->addFile(['name' => $name, 'show' => $show, 'binary' => $binary], $content);
				message("Successfully created file $name");
			} catch (Exception $exception) {
				error("400", $exception->getMessage());
			}
		}
	}
	
}

/**
 * @param Course $course
 */
function edit_file($course)
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$content = $input["content"];
		$path = $input["path"];
		$show = boolval($input["show"]);
		$binary = boolval($input["binary"]);
		$fsNode = FSNode::constructTreeForCourse($course);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("404", "File not found");
		}
		if ($node->isDirectory) {
			error("400", "This is a folder, not a file");
		}
		$node->editFile($content, $show, $binary);
		file_put_contents($course->getPath() . '/assignments.json', $fsNode->getJson());
		message("File $node->name edited");
	}
}

/**
 * @param Course $course
 */
function delete_file($course)
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$path = $input['path'];
		$path = str_replace('/../', '/', $path);
		$path = str_replace('../', '/', $path);
		$fsNode = FSNode::constructTreeForCourse($course);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("422", "File does not exist");
		}
		if ($node->isDirectory) {
			error("400", "This is a folder...");
		}
		$node->deleteFile();
		$content = $fsNode->getJson();
		if ($content == false) {
			error("500", "Contact your administrator!");
		}
		file_put_contents($course->getPath() . '/assignments.json', $content);
		message("Successfully deleted file $node->path");
	}
}

/**
 * @param Course $course
 */
function get_file_content($course)
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$path = $input["path"];
		$path = str_replace('/../', '/', $path);
		$path = str_replace('../', '/', $path);
		$fsNode = FSNode::constructTreeForCourse($course);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("404", "File not found");
		}
		try {
			$content = $node->getFileContent();
			message_and_data("Content of file: $node->name", array('content' => $content, 'isFromGlobalTemplate' => false));
		} catch (Exception $exception) {
			error("500", $exception->getMessage());
		}
	}
}


function convert_to_new_format(Course $course)
{
	$fsNode = FSNode::constructTreeForCourseFromOldTree($course);
	$content = $fsNode->getJson();
	$result = file_put_contents($course->getPath() . '/assignments.json', $content);
	if ($result == false) {
		error("500", "Could not write to " . $course->getPath() . '/assignments.json');
	}
	message("Successfully converted to new format");
}

/**
 * @param Course $course
 */
function get_assignments($course)
{
	$fsNode = FSNode::constructTreeForCourse($course);
	message_and_data("AssignmentRoot", json_decode($fsNode->getJson()));
}


/**
 * @param Course $course
 */
function add_assignment($course)
{
	error("NYI", "Not yet implemented");
}

/**
 * @param Course $course
 */
function edit_assignment($course)
{
	error("NYI", "Not yet implemented");
}

/**
 * @param Course $course
 */
function delete_assignment($course)
{
	error("NYI", "Not yet implemented");
}


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

if ($action == "createFile") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	create_file($course);
} else if ($action == "editFile") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	edit_file($course);
} else if ($action == "deleteFile") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	delete_file($course);
} else if ($action == "getFileContent") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	get_file_content($course);
} else if ($action == "getAssignments") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	get_assignments($course);
} else if ($action == "updateAssignments") {
	if (!in_array($login, $conf_sysadmins)) {
		error("403", "You are not the system admin");
	}
	convert_to_new_format($course);
} else if ($action == "addAssignment") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	add_assignment($course);
} else if ($action == "editAssignment") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	edit_assignment($course);
} else if ($action == "deleteAssignment") {
	if (!file_exists($course->getPath() . '/assignments.json')) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	delete_assignment($course);
} else {
	error("422", "Unknown action");
}