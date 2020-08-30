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
		$filename = $input["filename"];
		$content = $input["content"];
		$relative_path = $input["path"];
		$show = boolval($input['show']);
		$binary = boolval($input['binary']);
		if ($filename && check_filename($filename)) {
			// filesPath is like /usr/local/webide/data/X1_1/assignment_files
			$path = $course->getAssignments()->filesPath() . $relative_path . "/" . $filename;
			if (file_exists($path)) {
				error("422", "File already exists");
				return;
			}
			touch($path);
			if ($content) {
				file_put_contents($path, $content);
			}
			$file = array('name' => $filename, 'show' => $show, 'binary' => $binary);
			addFileToTreeAndSaveToFile($course, $relative_path, $file);
			message("Successfully created file $relative_path/$filename");
		} else {
			error("400", "filename property not set");
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
		$filename = $input["filename"];
		$content = $input["content"];
		$relative_path = $input["path"];
		if ($filename && check_filename($filename)) {
			// filesPath is like /usr/local/webide/data/X1_1/assignment_files
			$path = $course->getAssignments()->filesPath() . $relative_path . "/" . $filename;
			if (!file_exists($path)) {
				error("422", "File does not exist");
				return;
			}
			if ($content) {
				file_put_contents($path, $content);
			}
			message("Successfully edited file $relative_path/$filename");
		} else {
			error("400", "filename property not set");
		}
	}
}

/**
 * @param Course $course
 */
function delete_file($course)
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$filename = $input["filename"];
		$relative_path = $input["path"];
		if ($filename && check_filename($filename)) {
			// filesPath is like /usr/local/webide/data/X1_1/assignment_files
			$path = $course->getAssignments()->filesPath() . $relative_path . "/" . $filename;
			if (!file_exists($path)) {
				error("422", "File does not exist");
				return;
			}
			unlink($path);
			$tree = json_decode(file_get_contents($course->getPath() . '/assignments.json'), true);
			deleteFromTree($tree, $path);
			if (defined("JSON_PRETTY_PRINT")) {
				file_put_contents($course->getPath() . '/assignments.json', json_encode($tree, JSON_PRETTY_PRINT));
			} else {
				file_put_contents($course->getPath() . '/assignments.json', json_encode($tree));
			}
			message("Successfully deleted file $relative_path/$filename");
		} else {
			error("400", "filename property not set");
		}
	}
}

/**
 * @param Course $course
 */
function get_file_content($course)
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$filename = $input["filename"];
		$relative_path = $input["path"];
		if ($filename && check_filename($filename)) {
			// filesPath is like /usr/local/webide/data/X1_1/assignment_files
			$path = $course->getPath() . '/assignment_files' . $relative_path . "/" . $filename;
			$task = Assignment::fromWorkspacePath($course->folderName() . $relative_path);
			if (!file_exists($path)) {
				$template_file_path = $course->getPath() . "/files/$filename";
				if (file_exists($template_file_path)) {
					$content = assignment_replace_template_parameters(file_get_contents($template_file_path), $course, $task);
					$data = array('content' => $content, 'isFromGlobalTemplate' => true);
					message_and_data("Content of file: $filename", $data);
				} else {
					error("422", "File does not exist");
					return;
				}
			}
			$content = file_get_contents($path);
			$data = array('content' => $content, 'isFromGlobalTemplate' => false);
			message_and_data("Content of file: $relative_path/$filename", $data);
		} else {
			error("400", "filename property not set");
		}
	}
}


function update_assignments(Course $course)
{
	$path = $course->getPath() . '/assignments.json';
	if (!file_exists($path)) {
		$tree = get_updated_assignments_from_old_format($course);
	} else {
		$tree = get_updated_assignments_json($course);
	}
	if (JSON_PRETTY_PRINT) {
		file_put_contents($path, json_encode($tree, JSON_PRETTY_PRINT));
	} else {
		file_put_contents($path, json_encode($tree));
	}
	return $tree;
}

/**
 * @param Course $course
 */
function get_assignments($course)
{
	if (isset($_REQUEST['oldTree']) || !file_exists($course->getPath() . '/assignments.json')) {
//		$root = $course->getAssignments();
//		$root->getItems(); // Parse legacy data
//		$assignments = $root->getData();
//		if (empty($assignments))
//			json(error("ERR003", "No assignments for this course"));
//
//		assignments_process($assignments, $course->abbrev, $course->getFiles());
//		usort($assignments, "compareAssignments");
		$tree = get_updated_assignments_from_old_format($course);
	} else {
		$tree = get_updated_assignments_json($course);
	}
	
	usort($tree['children'], "compare_assignments");
	message_and_data("Assignments", $tree['children']);
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


$action = $_REQUEST["action"];

if ($action == "createFile") {
	check_admin_access($course, $login);
	create_file($course);
} else if ($action == "editFile") {
	check_admin_access($course, $login);
	edit_file($course);
} else if ($action == "deleteFile") {
	check_admin_access($course, $login);
	delete_file($course);
} else if ($action == "getFileContent") {
	get_file_content($course);
} else if ($action == "getAssignments") {
	get_assignments($course);
} else if ($action == "updateAssignments") {
	check_admin_access($course, $login);
	update_assignments($course);
} else if ($action == "addAssignment") {
	check_admin_access($course, $login);
	add_assignment($course);
} else if ($action == "editAssignment") {
	check_admin_access($course, $login);
	edit_assignment($course);
} else if ($action == "deleteAssignment") {
	check_admin_access($course, $login);
	delete_assignment($course);
} else {
	error("422", "Unknown action");
}