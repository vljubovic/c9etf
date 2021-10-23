<?php

// WEBSERVICE for c9 module etf.zadaci


// Web service
function ws_from_path() {
	$path = $_REQUEST['task_path'];
	
	// Split path
	$startpos = strpos($path, "/");
	if (!$startpos) 
		json(error("ERR002", "Unknown course"));
		
    // Remove filename
    $endpos = strrpos($path, "/");
    $path = substr($path, 0, $endpos);
	
	// Find assignment
	$asgn = Assignment::fromWorkspacePath($path);
	if (!$asgn)
		json(error("ERR004", "Unknown assignment"));
	
	// Find course
	$course = $asgn->getCourse();
	
	$a = array();
	$a['course'] = $course->id;
	$a['year'] = $course->year;
	if ($course->external) $a['external'] = 1; else $a['external'] = 0;
	$a['assignment'] = $asgn->parent->id;
	$a['task'] = $asgn->id;
	
	$result = ok("");
	$result['data'] = $a;
	json($result);
}



// List of courses
function ws_courses() {
	global $conf_current_year, $login, $conf_admin_users;

	$year = $conf_current_year;
	if (isset($_REQUEST['year']))
		$year = intval($_REQUEST['year']);
	
	$student = $login;
	if (isset($_REQUEST['user']) && in_array($login, $conf_admin_users))
		$student = $_REQUEST['user'];
	
	$result = ok("");
	$result['data'] = Course::forStudent($student, $year);
	json($result);
}


// Helper recursive function for ws_assignments
function assignments_process(&$assignments, $parentPath, $courseFiles) {
	global $login, $conf_admin_users;
	
	foreach($assignments as $key => $value) {
		if (array_key_exists('hidden', $value) && $value['hidden'] == "true" && !in_array($login, $conf_admin_users) && $login != "test") {
			unset($assignments[$key]);
			continue;
		}
		if (array_key_exists('path', $value) && !empty($value['path']))
			$path = $assignments[$key]['path'] = $parentPath . "/" . $assignments[$key]['path'];
		else
			$path = $assignments[$key]['path'] = $parentPath;
		
		if (!array_key_exists('files', $value)) $assignments[$key]['files'] = array();
		foreach ($courseFiles as $cfile) {
			$found = false;
			$ccfile = $cfile;
			if (is_array($cfile) && array_key_exists('filename', $cfile))
				$ccfile = $cfile['filename'];
			foreach ($assignments[$key]['files'] as $file) {
				$ffile = $file;
				if (is_array($file) && array_key_exists('filename', $file))
					$ffile = $file['filename'];
				if ($ccfile == $ffile)
					$found = true;
			}
			if (!$found)
				$assignments[$key]['files'][] = $cfile;
		}
			
		if (array_key_exists('items', $value))
			assignments_process($assignments[$key]['items'], $path, $courseFiles);
	}
	$assignments = array_values($assignments);
}

function ws_assignments() {
	global $login, $conf_admin_users;

	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		json(error("ERR002", "Unknown course"));
	}
	
	if (!$course->isAdmin($login) && !$course->isStudent($login))
		json(error("ERR007", "Permission denied"));
	
	$root = $course->getAssignments();
	$root->getItems(); // Parse legacy data
	$assignments = $root->getData();
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	// Sort assigments by type, then by name (natural)
	function cmp($a, $b) { 
		if (array_key_exists('type', $a) && $a['type'] == $b['type']) return strnatcmp($a['name'], $b['name']); 
		if (array_key_exists('type', $a) && $a['type'] == "tutorial") return -1;
		if (array_key_exists('type', $b) && $b['type'] == "tutorial") return 1;
		if (array_key_exists('type', $a) && $a['type'] == "homework") return -1;
		if (array_key_exists('type', $b) && $b['type'] == "homework") return 1;
		// Other types are considered equal
		if (array_key_exists('name', $a))
			return strnatcmp($a['name'], $b['name']); 
		return -1;
	}
	assignments_process($assignments, $course->abbrev, $course->getFiles());
	usort($assignments, "cmp");
		
	$result = ok("");
	$result['data'] = $assignments;
	json($result);
}


// Get assignments in Object form

function ws_assignments2() {
	global $login, $conf_admin_users;

	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		json(error("ERR002", "Unknown course"));
	}
	
	if (!$course->isAdmin($login) && !$course->isStudent($login))
		json(error("ERR007", "Permission denied"));
	
	$root = $course->getAssignments();
	if (isset($_REQUEST['assignment'])) {
		$assignments = $root->findById( intval($_REQUEST['assignment']) )->getItems();
	}
	else
		$assignments = $root->getItems();
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	// Sort assigments by type, then by name (natural)
	function cmp($a, $b) {
		if ($a->type == $b->type) return strnatcmp($a->name, $b->name); 
		if ($a->type == "tutorial") return -1;
		if ($b->type == "tutorial") return 1;
		if ($a->type == "homework") return -1;
		if ($b->type == "homework") return 1;
		// Other types are considered equal
		return strnatcmp($a->name, $b->name); 
	}
	foreach($assignments as $key => $value) {
		//if ($value['type'] == "exam" && $login != "test" && $login != "epajic1" && $login != "ec15261") unset($assignments[$key]);
		if ($value->hidden == "true" && !in_array($login, $conf_admin_users) && $login != "test") 
			unset($assignments[$key]);
	}
	usort($assignments, "cmp");
		
	$result = ok("");
	$result['data'] = $assignments;
	json($result);
}


function ws_files() {
	global $login;
	
	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		json(error("ERR002", "Unknown course"));
	}
	
	if (!$course->isAdmin($login) && !$course->isStudent($login))
		json(error("ERR007", "Permission denied"));
	
	$root = $course->getAssignments();
	$assignments = $root->getData();
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	$task = false;
	if (isset($_REQUEST['task_direct']))
		$task = $root->findById( intval($_REQUEST['task_direct']) );
	else if (isset($_REQUEST['task']))
		$task = $root->findById( intval($_REQUEST['task']) );
	if ($task === false)
		json(error("ERR005", "Unknown task"));
	
	$files = array_unique(array_merge( $course->getFiles(), array_values($task->files) ), SORT_REGULAR);
	
	$result = ok("");
	$result['data'] = $files;
	json($result);
}


// Helper function that makes all the keyword replacements in a text file
function assignment_replace($code, $course, $task) {
	$title = $task->parent->name . ", " . $task->name;
	$code = str_replace("===TITLE===", $title, $code);
	$code = str_replace("===COURSE===", $course->name, $code);
	
	foreach(Cache::getFile("years.json") as $year)
		if ($year['id'] == $course->year)
			$year_name = $year['name'];
	$code = str_replace("===YEAR===", $year_name, $code);
	
	if (!empty($task->author))
		$code = str_replace("===AUTHOR===", $task->author, $code);

	return $code;
}



function ws_getfile() {
	global $login, $conf_base_path;

	// Validate input variables
	$file = basename($_REQUEST['file']);
	
	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		json(error("ERR002", "Unknown course"));
	}
	
	if (!$course->isAdmin($login) && !$course->isStudent($login))
		json(error("ERR007", "Permission denied"));
	
	$root = $course->getAssignments();
	$assignments = $root->getData();
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	if (isset($_REQUEST['task_direct']))
		$taskId = intval($_REQUEST['task_direct']);
	else if (isset($_REQUEST['task']))
		$taskId = intval($_REQUEST['task']);
	else
		json(error("ERR005", "Unknown task"));
	$task = $root->findById( $taskId );
	if ($task === false)
		json(error("ERR005", "Unknown task"));
	
	// Look for task file
	if (empty($file))
		json(error("ERR006", "File not found - $file"));
	
	$found_file_path = $task->filesPath() . "/$file";
	if (!file_exists($found_file_path))
		$found_file_path = $course->getPath() . "/files/$file";
	if (!file_exists($found_file_path))
		json(error("ERR006", "File not found - $file"));
	
	$destination_path = $task->filesPath(true) . "/$file";
		
	// Test if file is binary
	$binary = false;
	foreach($task->files as $fileData) {
		if (array_key_exists('filename', $fileData) && $fileData['filename'] == $file && $fileData['binary'])
			$binary = true;
	}
	
	if ($binary && !isset($_REQUEST['view'])) {
		if (empty($login)) json(error("ERR007", "Session expired, please login again"));
		
		$ok = ok("Copying on server $destination_path");
		$ok['code'] = "STA001";
		$ok['data'] = "sudo $conf_base_path/bin/wsaccess $login deploy \"$destination_path\" \"$found_file_path\" &";
		// Sadly downloading file from service doesn't work as apparently ws.writeFile doesn't support binary files
		proc_close(proc_open("sudo $conf_base_path/bin/wsaccess $login deploy \"$destination_path\" \"$found_file_path\" &", array(), $foo));
		json($ok);

// 		$type = `file -bi '$found_file_path'`;
// 		header("Content-Type: $type");
	} else
		header("Content-Type: text/plain");
	
	header('Content-Disposition: attachment; filename="'.$file.'"', false);
	header("Pragma: dummy=bogus"); 
	header("Cache-Control: private");
	
	if ($binary) {
		header("Content-Length: ".(string)(filesize($found_file_path)));
		$k = readfile($found_file_path,false);
	} else {
		$code = file_get_contents($found_file_path);
		
		if (isset($_REQUEST['replace']))
			$code = assignment_replace($code, $course, $task);
		
		if ($course->id == 2234 && $file == ".zadaca") {
			if (preg_match("/\"id\": (\d+)/", $code, $matches)) {
				$newid = $matches[1]+1;
				$code = preg_replace("/\"id\": (\d+)/", "\"id\": $newid", $code);
			}
		}
		header("Content-Length: ".(string)(strlen($code)));
		print $code;
	}
	exit();
}



function ws_addfile() {
	global $login, $conf_base_path;

	// Validate input variables
	$filename = basename($_FILES['add']['name']);
	
	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		json(error("ERR002", "Unknown course"));
	}
	
	if (!$course->isAdmin($login))
		json(error("ERR007", "Permission denied"));
	
	$root = $course->getAssignments();
	
	$task = false;
	if (isset($_REQUEST['task_direct'])) {
		$task = $root->findById( intval($_REQUEST['task_direct']) );
		if ($task === false)
			json(error("ERR005", "Unknown task"));
	}
	
	$file = array("filename" => $filename, "binary" => false, "show" => false);
	if (isset($_REQUEST['binary']) && $_REQUEST['binary']) $file['binary'] = true;
	if (isset($_REQUEST['show']) && $_REQUEST['show']) $file['show'] = true;
	
	$temporary = $_FILES['add']['tmp_name'];
	if (!$task) {
		$course->addFile($filename, $temporary);
	} else {
		$blah = $task->addFile($file, $temporary);
	}
	json(ok("filename $filename task ".$task->id." blah ".$blah));
}



function ws_generatefile() {
	global $login, $conf_data_path;

	// Validate input variables
	$filename = basename($_REQUEST['file']);
	
	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		json(error("ERR002", "Unknown course"));
	}
	
	if (!$course->isAdmin($login))
		json(error("ERR007", "Permission denied"));
	
	$root = $course->getAssignments();
	
	$task = false;
	if (isset($_REQUEST['task_direct'])) {
		$task = $root->findById( intval($_REQUEST['task_direct']) );
	}
	if ($task === false)
		json(error("ERR005", "Unknown task"));
	
	$file = array("filename" => $filename, "binary" => false, "show" => false);
	$temporary = tempnam("/tmp", "GEN");
	
	if ($filename == ".zadaca") {
		$zadatak = intval(substr($task->name, strrpos($task->name, " ")));
		
		$zadaca = array("id" => $task->parent->homework_id, "zadatak" => $zadatak, "naziv" => $task->parent->name . ", " . $task->name);
		file_put_contents($temporary, json_encode($zadaca, JSON_PRETTY_PRINT));
	}
	
	if ($filename == ".autotest"|| $filename == ".autotest2") {
		foreach(Cache::getFile("years.json") as $year)
			if ($year['id'] == $course->year)
				$year_name = $year['name'];
		$course_data = $course->data;
		
		$autotest = array();
		$autotest['id'] = intval(file_get_contents($conf_data_path . "/autotest_last_id.txt")) + 1;
		file_put_contents($conf_data_path . "/autotest_last_id.txt", $autotest['id']);
		$autotest['name'] = $course->name . " ($year_name), " . $task->parent->name . ", " . $task->name;
		$autotest['language'] = $course_data['language'];
		$autotest['required_compiler'] = $autotest['preferred_compiler'] = $course_data['compiler'];
		$autotest['compiler_features'] = $course_data['compiler_features'];
		$autotest['compiler_options'] = $course_data['compiler_options'];
		$autotest['compiler_options_debug'] = $course_data['compiler_options_debug'];
		$autotest['compile'] = $autotest['test'] = $autotest['debug'] = $autotest['profile'] = "true";
		$autotest['run'] = "false";
		$autotest['test_specifications'] = array();
		
		file_put_contents($temporary, json_encode($autotest, JSON_PRETTY_PRINT));
	}
	
	$task->addFile($file, $temporary);
	json(ok(""));
}


// Function to deploy file to all users (admin only)
function ws_deploy() {
	global $login, $conf_admin_users, $conf_base_path, $conf_web_background;

	// Validate input variables
	$file = basename($_REQUEST['file']);

	if (isset($_REQUEST['user'])) $user = escapeshellarg($_REQUEST['user']); else $user = "all-users";

	// Check if user is admin
	if (!in_array($login, $conf_admin_users))
		json(error("ERR007", "Insufficient privileges"));
	
	// Input values & validation
	$course = Course::fromRequest();
	
	$root = $course->getAssignments();
	$assignments = $root->getData();
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	$task = false;
	if (isset($_REQUEST['task_direct']))
		$task = $root->findById( intval($_REQUEST['task_direct']) );
	else if (isset($_REQUEST['task']))
		$task = $root->findById( intval($_REQUEST['task']) );
	if ($task === false)
		json(error("ERR005", "Unknown task"));
	
	// Look for task file
	if (empty($file))
		json(error("ERR006", "File not found"));
	
	$found_file_path = $task->filesPath() . "/$file";
	if (!file_exists($found_file_path))
		$found_file_path = $course->getPath() . "/files/$file";
	if (!file_exists($found_file_path))
		json(error("ERR006", "File not found " . $task->filesPath() . "/$file"));
		
	$destination_path = $task->filesPath(true) . "/$file";
	
	// Find suitable output filename
	// This log is used so that admin user can get a nice progress bar
	if (!file_exists($conf_web_background)) mkdir($conf_web_background);
	do {
		$log_filename = generateRandomString(10);
		$log_file = $conf_web_background . "/" . $log_filename;
	} while(file_exists($log_file));
	
	proc_close(proc_open("sudo $conf_base_path/bin/wsaccess $user deploy \"$destination_path\" \"$found_file_path\" >$log_file &", array(), $foo));
	
	$msg = date("Y-m-d H:i:s") . " - $login - deploy $user $destination_path\n";
	file_put_contents("$conf_base_path/log/admin.php.log", $msg, FILE_APPEND);
	
	$result = ok("");
	$result['data'] = $log_filename;
	json($result);
}



// Deploy progress
function ws_deploy_status() {
	global $login, $conf_admin_users, $conf_base_path, $conf_web_background;

	// Check if user is admin
	if (!in_array($login, $conf_admin_users))
		json(error("ERR007", "Insufficient privileges"));

	// Find log file
	$log_filename = basename($_REQUEST['id']);
	if (empty($log_filename))
		json(error("ERR006", "File not found"));
		
	$log_file = $conf_web_background . "/" . $log_filename;
	if (!file_exists($log_file))
		json(error("ERR006", "File not found"));

	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));
	$total = count($users);
	$count = `cat $log_file | grep -v ERROR | wc -l`;
	
	$result = ok("");
	$result['data'] = array();
	$result['data']['done'] = intval($count);
	$result['data']['total'] = intval($total);
	json($result);
}



// Update all assignment data on server
function ws_update_assignments() {
	global $login, $conf_data_path, $conf_admin_users;

	// Check if user is admin
	if (!in_array($login, $conf_admin_users))
		json(error("ERR007", "Insufficient privileges"));
	
	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		json(error("ERR002", "Unknown course"));
	}
	
	if (!$course->isAdmin($login))
		json(error("ERR007", "Permission denied"));
	
	$path = $conf_data_path . "/" . $course->toString() . "/assignments";
	copy ($path, $path . ".bak");
	
	// This step beautifies JSON code
	$data = json_decode($_REQUEST['data'], true);
	$dataJson = json_encode($data, JSON_PRETTY_PRINT);
	
	file_put_contents($path, $dataJson);
	json(ok(""));
}



// Construct ok/error messages
function error($code, $msg) {
        $result = array( 'success' => "false", 'code' => $code, 'message' => $msg );
        return $result;
}

function ok($msg) {
        $result = array( 'success' => "true", 'message' => $msg, 'data' => array() );
        return $result;
}

function json($data) {
	if (defined("JSON_PRETTY_PRINT"))
		print json_encode($data, JSON_PRETTY_PRINT);
	else
		print json_encode($data);
	exit();
}


// Since this service is allowed to all users, we will accept any valid session

session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");

require("../classes/Course.php");


ini_set('default_charset', 'UTF-8');
header('Content-Type: text/json; charset=UTF-8');


if (!isset($_REQUEST['action']))
	json(error("ERR999", "Unknown action"));


if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
} else {
	json(error("ERR001", "Not logged in"));
}

session_write_close();


// Actions
if ($_REQUEST['action'] == "courses")
	ws_courses();
else if ($_REQUEST['action'] == "assignments")
	ws_assignments();
else if ($_REQUEST['action'] == "assignments2")
	ws_assignments2();
else if ($_REQUEST['action'] == "files")
	ws_files();
else if ($_REQUEST['action'] == "getFile")
	ws_getfile();
else if ($_REQUEST['action'] == "addFile")
	ws_addfile();
else if ($_REQUEST['action'] == "generateFile")
	ws_generatefile();
else if ($_REQUEST['action'] == "deploy")
	ws_deploy();
else if ($_REQUEST['action'] == "deploy_status")
	ws_deploy_status();
else if ($_REQUEST['action'] == "from_path")
	ws_from_path();
else if ($_REQUEST['action'] == "updateAssignments")
	ws_update_assignments();

else
	json(error("ERR999", "Unknown action"));



?>
