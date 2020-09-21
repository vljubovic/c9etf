<?php

session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");
require_once("../classes/Course.php");
require_once("../classes/Cache.php");
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

function validateRequired($keys, $array)
{
	foreach ($keys as $key) {
		if (!array_key_exists($key, $array)) {
			error("400", "Required field $key not present in body!");
		}
	}
}

/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function create_file($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(['folderPath', 'name'], $input);
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
		
		$fsNode = FSNode::constructTreeForCourse($course, $contentFolder, $descriptionFile);
		$folder = $fsNode->getNodeByPath($path);
		if ($folder == null) {
			error("400", "Invalid path to folder");
		}
		if ($name && check_filename($name)) {
			try {
				$folder->addFile(['name' => $name, 'show' => $show, 'binary' => $binary], $content);
				file_put_contents($course->getPath() . '/assignments.json', $fsNode->getJson());
				message("Successfully created file $name");
			} catch (Exception $exception) {
				error("400", $exception->getMessage());
			}
		}
	} else {
		error("400", "Missing body!");
	}
}

/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function edit_file($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(['path'], $input);
		
		$path = $input["path"];
		if (!array_key_exists('content', $input)) {
			$content = null;
		} else {
			$content = $input["content"];
		}
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
		
		$fsNode = FSNode::constructTreeForCourse($course, $contentFolder, $descriptionFile);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("404", "File not found");
		}
		if ($node->isDirectory) {
			error("400", "This is a folder, not a file");
		}
		$message = "";
		if ($node->isTemplateFile()) {
			$message = "You edited a template file. That means that you created a file in this folder and it is no longer part of the template.";
		}
		$node->editFile($content, $show, $binary);
		file_put_contents($course->getPath() . '/' . $descriptionFile, $fsNode->getJson());
		message("File $node->name edited. " . $message);
	} else {
		error("400", "Missing body!");
	}
}

/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function delete_file($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(['path'], $input);
		
		$path = $input['path'];
		$path = str_replace('/../', '/', $path);
		$path = str_replace('../', '/', $path);
		$fsNode = FSNode::constructTreeForCourse($course, $contentFolder, $descriptionFile);
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
		file_put_contents($course->getPath() . '/' . $descriptionFile, $content);
		message("Successfully deleted file $node->path");
	} else {
		error("400", "Missing body!");
	}
}

/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function get_file_content($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(['path'], $input);
		$path = $input["path"];
		$path = str_replace('/../', '/', $path);
		$path = str_replace('../', '/', $path);
		$fsNode = FSNode::constructTreeForCourse($course, $contentFolder, $descriptionFile);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("404", "File not found");
		}
		try {
			$content = $node->getFileContent();
			message_and_data("Content of file: $node->name", array('content' => $content, 'isFromGlobalTemplate' => $node->isTemplateFile()));
		} catch (Exception $exception) {
			error("500", $exception->getMessage());
		}
	} else {
		error("400", "Missing body!");
	}
}


function convert_to_new_format(Course $course)
{
	$fsNode = FSNode::constructTreeForCourseFromOldTree($course);
	$content = $fsNode->getJson();
	if ($content == null) {
		error("500", "Json is null. Check if everything is ok with the old file.");
	}
	$result = file_put_contents($course->getPath() . '/assignments.json', $content);
	if ($result == false) {
		error("500", "Could not write to " . $course->getPath() . '/assignments.json');
	}
	message("Successfully converted to new format");
}

/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function get_assignments($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$fsNode = FSNode::constructTreeForCourse($course, $contentFolder, $descriptionFile);
	message_and_data("AssignmentRoot", json_decode($fsNode->getJson()));
}


/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function create_assignment($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(['path', 'name', 'displayName', 'type', 'hidden'], $input);
		$path = $input['path'];
		$name = $input['name'];
		$displayName = $input['displayName'];
		$type = $input['type'];
		$hidden = $input['hidden'];
		$homeworkId = null;
		if (array_key_exists('homeworkId', $input)) {
			$homeworkId = $input['homeworkId'];
		}
		$fsNode = FSNode::constructTreeForCourse($course,$contentFolder,$descriptionFile);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("400", "Invalid path. Path must point to the parent folder for the new assignment");
		}
		try {
			$id = null; // TODO: Get ID from game server if it's a game
//			if ($contentFolder === "game_files") {
//				$request = curl_init("$conf_game_url/uup-game/assignments/create");
//				curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
//				curl_setopt($request, CURLOPT_POST, true);
//				$body = array("name"=>$displayName, "active"=>true, "points"=>3,"challenge_pts"=>5);
//				curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($body));
//				curl_setopt($request, CURLOPT_HTTPHEADER, array(
//					'Content-Type: application/json'
//				));
//				$response = curl_exec($request);
//			}
			$node->addFolder($name, $displayName, $type, $hidden, $homeworkId, $id);
			$content = $fsNode->getJson();
			if ($content == null) {
				error("500", "Please send this to your administrator. Add assignments is not working properly.");
			}
			file_put_contents($course->getPath() . '/'.$descriptionFile, $content);
			message("Successfully added folder $name with display name $displayName to path: $path");
		} catch (Exception $exception) {
			error("400", $exception->getMessage());
		}
	} else {
		error("400", "Missing body!");
	}
}

/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function edit_assignment($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(['path'], $input);
		$path = $input['path'];
		$fsNode = FSNode::constructTreeForCourse($course,$contentFolder,$descriptionFile);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("400", "Invalid path");
		}
		if (!array_key_exists('displayName', $input)) {
			$displayName = null;
		} else {
			$displayName = $input['displayName'];
		}
		if (!array_key_exists('hidden', $input)) {
			$hidden = null;
		} else {
			$hidden = $input['hidden'];
		}
		if (!array_key_exists('type', $input)) {
			$type = null;
		} else {
			$type = $input['type'];
		}
		if (!array_key_exists('homeworkId', $input)) {
			$homeworkId = null;
		} else {
			$homeworkId = $input['homeworkId'];
		}
		$node->editFolder($displayName, $type, $hidden, $homeworkId);
		$content = $fsNode->getJson();
		if ($content == null) {
			error("500", "Contact your administrator. Edit assignment service endpoint problem");
		}
		file_put_contents($course->getPath() . '/'.$descriptionFile, $fsNode->getJson());
		message("Successfully updated assignment $path");
	} else {
		error("400", "Missing body!");
	}
}

/**
 * @param Course $course
 * @param string $contentFolder
 * @param string $descriptionFile
 */
function delete_assignment($course, $contentFolder = "assignment_files", $descriptionFile = "assignments.json")
{
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(['path'], $input);
		$path = $input['path'];
		$fsNode = FSNode::constructTreeForCourse($course,$contentFolder,$descriptionFile);
		$node = $fsNode->getNodeByPath($path);
		if ($node == null) {
			error("400", "Invalid path");
		}
		$node->deleteFolder();
		$content = $fsNode->getJson();
		if ($content == null) {
			error("500", "Contact your administrator. Delete assignment service endpoint problem");
		}
		file_put_contents($course->getPath() . '/'.$descriptionFile, $fsNode->getJson());
		message("Successfully deleted assignment $path");
	} else {
		error("400", "Missing body!");
	}
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

/**
 * THIS PART IS FOR THE GAME
 */
//if (isset($_REQUEST["game"])) {
//	$folder = "game_files";
//	$descriptor = "game.json";
//} else {
$folder = "assignment_files";
$descriptor = "assignments.json";
//}
/**
 * TILL HERE
 */

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
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	create_file($course,$folder,$descriptor);
} else if ($action == "editFile") {
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	edit_file($course,$folder,$descriptor);
} else if ($action == "deleteFile") {
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	delete_file($course,$folder,$descriptor);
} else if ($action == "getFileContent") {
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	get_file_content($course,$folder,$descriptor);
} else if ($action == "getAssignments") {
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	get_assignments($course,$folder,$descriptor);
} else if ($action == "updateAssignments") {
	if (!in_array($login, $conf_sysadmins)) {
		error("403", "You are not the system admin");
	}
	convert_to_new_format($course);
} else if ($action == "createAssignment") {
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	create_assignment($course,$folder,$descriptor);
} else if ($action == "editAssignment") {
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	edit_assignment($course,$folder,$descriptor);
} else if ($action == "deleteAssignment") {
	if (!file_exists($course->getPath() . '/'.$descriptor)) {
		error("404", "Assignments not configured. Contact your administrator!");
	}
	check_admin_access($course, $login);
	delete_assignment($course,$folder,$descriptor);
} else {
	error("422", "Unknown action");
}