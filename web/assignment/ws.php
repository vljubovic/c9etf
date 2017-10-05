<?php

// WEBSERVICE for c9 module etf.zadaci


// Helper function that determines all parameters from path
function from_path($path, &$course_id, &$year_id, &$external, &$asgn_id, &$task_id) {
	global $conf_data_path, $conf_current_year, $conf_zamger;
	
	// Split path
	$startpos = strpos($path, "/");
	if ($startpos) {
		$course_name = substr($path, 0, $startpos);
		$path = substr($path, $startpos+1);
		$startpos = strpos($path, "/");
		if ($startpos) {
			$asgn_name = substr($path, 0, $startpos);
			$path = substr($path, $startpos+1);
			$startpos = strpos($path, "/");
			if ($startpos) {
				$task_name = substr($path, 0, $startpos);
			} else {
				$task_name = $path;
			}
		} else json(error("ERR002", "Unknown course"));
	} else json(error("ERR002", "Unknown course"));
	
	// Find course
	$courses_path = $conf_data_path . "/courses.json";
	$courses = array();
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	
	$course_id = 0;
	foreach($courses as $course) {
		if ($course['abbrev'] == $course_name) {
			$course_id = $course['id'];
			$year_id = $conf_current_year;
			if ($course['type'] == "external") $external=1; else $external=0;
			break;
		}
	}
	if ($course_id == 0) json(error("ERR002", "Unknown course"));
	
	// Find assignment
	if ($external) {
		$course_path = $conf_data_path . "/X" .$course_id . "_" . $year_id;
	} else {
		$course_path = $conf_data_path . "/" . $course_id . "_" . $year_id;
	}
	
	$asgn_file_path = $course_path . "/assignments";
	$assignments = array();
	if (file_exists($asgn_file_path))
		$assignments = json_decode(file_get_contents($asgn_file_path), true);

	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));

	$asgn = false;
	foreach ($assignments as $a)
		if ($a['path'] == $asgn_name) $asgn = $a;
	if ($asgn == false) json(error("ERR004", "Unknown assignment"));
	$asgn_id = $asgn['id'];
	
	// Find task
	if (!preg_match("/^Z(\d)$/", $task_name, $matches))
		json(error("ERR005", "Unknown task"));
	$task_id = $matches[1];
	if ($task_id < 1 || $task_id > $asgn['tasks'])
		json(error("ERR005", "Unknown task"));
}


// Web service
function ws_from_path() {
	$path = $_REQUEST['task_path'];
	$result = ok("");
	$a = array();
	from_path($path, $a['course'], $a['year'], $a['external'], $a['assignment'], $a['task']);
	$result['data'] = $a;
	json($result);
}



// List of courses
function ws_courses() {
	global $conf_data_path, $conf_current_year, $conf_zamger;

// Forsiram svima OR i ASP
//	if (isset($_SESSION['login']) && $_SESSION['login'] == "test") {
		$result = ok("");
		$result['data'][] = array(
			"id" => 1,
			"year" => 12,
			"type" => "external",
			"name" => "Osnove računarstva",
			"abbrev" => "OR"
		);
		$result['data'][] = array(
			"id" => 42,
			"year" => 12,
			"type" => "external",
			"name" => "Algoritmi i strukture podataka",
			"abbrev" => "ASP"
		);
		json($result);
//	}

	// Read files
	$courses_path = $conf_data_path . "/courses.json";
	$courses = array();
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	
	$result = ok("");
	if ($conf_zamger) {
		// Check zamger to see which courses is student enrolled in (FIXME)
		require_once("../zamger/courses.php");
		$zamger_courses = student_courses($conf_current_year);
		for ($i=0; $i<count($courses); $i++) {
			$found = false;
			foreach ($zamger_courses as $tmpcourse) {
				$course = array();
				$course['id'] = $tmpcourse['id'];
				$course['name'] = $tmpcourse['naziv'];
				$course['year'] = $conf_current_year;
				$course['type'] = "external";
				$course['abbrev'] = $tmpcourse['kratki_naziv'];
				$result['data'][] = $course;
			}
		}
	} else {
		foreach($courses as &$course) {
			$course['year'] = $conf_current_year;
		}
		$result['data'] = $courses;
	}
	json($result);
}


function ws_assignments() {
	global $conf_data_path, $login;

	$course = intval($_REQUEST['course']);
	$year = intval($_REQUEST['year']);
	if (isset($_REQUEST['external'])) $external = $_REQUEST['external']; else $external=0;
	if (isset($_REQUEST['X'])) $external=1;
	
	if ($external) {
		$course_path = $conf_data_path . "/X$course" . "_$year";
	} else {
		$course_path = $conf_data_path . "/$course" . "_$year";
	}
	if (!file_exists($course_path))
		json(error("ERR002", "Unknown course"));

	$asgn_file_path = $course_path . "/assignments";
	$assignments = array();
	if (file_exists($asgn_file_path))
		$assignments = json_decode(file_get_contents($asgn_file_path), true);
	
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	// Sort assigments by type, then by name (natural)
	function cmp($a, $b) { 
		if ($a['type'] == $b['type']) return strnatcmp($a['name'], $b['name']); 
		if ($a['type'] == "tutorial") return -1;
		if ($b['type'] == "tutorial") return 1;
		if ($a['type'] == "homework") return -1;
		if ($b['type'] == "homework") return 1;
		// Other types are considered equal
		return strnatcmp($a['name'], $b['name']); 
	}
	usort($assignments, "cmp");
		
	$result = ok("");
	$result['data'] = $assignments;
	json($result);
}



function ws_files() {
	global $conf_data_path;

	// Validate input variables
	if (isset($_REQUEST['course'])) $course = intval($_REQUEST['course']);
	if (isset($_REQUEST['year'])) $year = intval($_REQUEST['year']);
	if (isset($_REQUEST['external'])) $external = $_REQUEST['external']; else $external=0;
	if (isset($_REQUEST['X'])) $external=1;
	if (isset($_REQUEST['assignment'])) $asgn_id = intval($_REQUEST['assignment']);
	if (isset($_REQUEST['task'])) $task = intval($_REQUEST['task']);
	if (isset($_REQUEST['task_path'])) from_path($_REQUEST['task_path'], $course, $year, $external, $asgn_id, $task);
	
	if ($external) {
		$course_path = $conf_data_path . "/X$course" . "_$year";
	} else {
		$course_path = $conf_data_path . "/$course" . "_$year";
	}
	if (!file_exists($course_path))
		json(error("ERR002", "Unknown course"));

	// Read files
	$asgn_file_path = $course_path . "/assignments";
	$assignments = array();
	if (file_exists($asgn_file_path))
		$assignments = json_decode(file_get_contents($asgn_file_path), true);
	
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	$asgn = array();
	foreach ($assignments as $a)
		if ($a['id'] == $asgn_id) $asgn=$a;
	if (empty($asgn))
		json(error("ERR004", "Unknown assignment"));
	
	if ($task < 1 || $task > $asgn['tasks'])
		json(error("ERR005", "Unknown task"));
	
	// Get list of course files
	$files_path = $course_path . "/files";
	$files = array();
	if (file_exists($files_path)) {
		$files = scandir($files_path); $count = count($files);
		for ($i=0; $i<$count; $i++) {
			if (is_dir($files_path . "/" . $files[$i]) || $files[$i] == "..") 
				unset($files[$i]);
		}
	}
	
	// Get a list of task files
	if (isset($asgn['task_files'][$task])) {
		foreach($asgn['task_files'][$task] as $file)
			if (!in_array($file, $files))
				$files[] = $file;
	}
	
	$result = ok("");
	$result['data'] = array_values($files);
	json($result);
}



function ws_getfile() {
	global $conf_data_path;

	// Validate input variables
	$course = intval($_REQUEST['course']);
	$year = intval($_REQUEST['year']);
	$external = $_REQUEST['external'];
	if (isset($_REQUEST['external'])) $external = $_REQUEST['external']; else $external=0;
	if (isset($_REQUEST['X'])) $external=1;
	$asgn_id = intval($_REQUEST['assignment']);
	$task = intval($_REQUEST['task']);
	$file = basename($_REQUEST['file']);
	
	if (empty($file))
		json(error("ERR006", "File not found"));
	
	if ($external) {
		$course_path = $conf_data_path . "/X$course" . "_$year";
	} else {
		$course_path = $conf_data_path . "/$course" . "_$year";
	}
	if (!file_exists($course_path))
		json(error("ERR002", "Unknown course"));
		
	// Find assignment
	$asgn_file_path = $course_path . "/assignments";
	$assignments = array();
	if (file_exists($asgn_file_path))
		$assignments = json_decode(file_get_contents($asgn_file_path), true);
	
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	$asgn = array();
	foreach ($assignments as $a)
		if ($a['id'] == $asgn_id) $asgn=$a;
	if (empty($asgn))
		json(error("ERR004", "Unknown assignment"));
	
	// Look for task file
	$found_file_path = $course_path . "/assignment_files/" . $asgn['path'] . "/Z$task/$file";
	if (!file_exists($found_file_path))
		$found_file_path = $course_path . "/files/$file";
	if (!file_exists($found_file_path))
		json(error("ERR006", "File not found"));
	
	header("Content-Type: text/plain");
	header('Content-Disposition: attachment; filename="'.$file.'"', false);
	header("Pragma: dummy=bogus"); 
	header("Cache-Control: private");
	$code = file_get_contents($found_file_path);
	if (isset($_REQUEST['replace']))
		$code = str_replace("===TITLE===", $_REQUEST['replace'], $code);
	print $code;
	exit();
}



// Function to deploy file to all users (admin only)
function ws_deploy() {
	global $login, $conf_admin_users, $conf_base_path, $conf_web_background, $conf_data_path;

	if (isset($_REQUEST['user'])) $user = escapeshellarg($_REQUEST['user']); else $user = "all-users";

	// Check if user is admin
	if (!in_array($login, $conf_admin_users))
		json(error("ERR007", "Insufficient privileges"));
	
	// Input values & validation
	$course = intval($_REQUEST['course']);
	$year = intval($_REQUEST['year']);
	$external = $_REQUEST['external'];
	if (isset($_REQUEST['X'])) $external=1;
	$asgn_id = intval($_REQUEST['assignment']);
	$task = intval($_REQUEST['task']);
	$file = basename($_REQUEST['file']);
		
	if (empty($file))
		json(error("ERR006", "File not found"));
	
	if ($external) {
		$course_path = $conf_data_path . "/X$course" . "_$year";
	} else {
		$course_path = $conf_data_path . "/$course" . "_$year";
	}
	if (!file_exists($course_path))
		json(error("ERR002", "Unknown course"));

	// Find course abbreviation (for path part)
	$courses_path = $conf_data_path . "/courses.json";
	$courses = array();
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	
	$course_data = array();
	foreach($courses as $c) {
		if ($external && $c['type'] != "external") continue;
		if (!$external && $c['type'] == "external") continue;
		if ($c['id'] == $course) $course_data = $c;
	}
	
	// Find assignment
	$asgn_file_path = $course_path . "/assignments";
	$assignments = array();
	if (file_exists($asgn_file_path))
		$assignments = json_decode(file_get_contents($asgn_file_path), true);
	
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	$asgn = array();
	foreach ($assignments as $a)
		if ($a['id'] == $asgn_id) $asgn=$a;
	if (empty($asgn))
		json(error("ERR004", "Unknown assignment"));
	
	// Look for task file
	$found_file_path = $course_path . "/assignment_files/" . $asgn['path'] . "/Z$task/$file";
	if (!file_exists($found_file_path))
		$found_file_path = $course_path . "/files/$file";
	if (!file_exists($found_file_path))
		json(error("ERR006", "File not found"));
		
	$destination_path = $course_data['abbrev'] . "/" . $asgn['path'] . "/Z$task/$file";
	
	// Find suitable output filename
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

	// This is wrong due to zombie users
	//$users_file = $conf_base_path . "/users";
	//eval(file_get_contents($users_file));
	//$total = count($users);
	$total = `grep 1002 /etc/passwd | wc -l`;
	$count = `cat $log_file | grep -v ERROR | wc -l`;
	
	$result = ok("");
	$result['data'] = array();
	$result['data']['done'] = intval($count);
	$result['data']['total'] = intval($total);
	json($result);
}



// Construct ok/error messages
function error($code, $msg) {
        $result = array();
        $result['success'] = "false";
        $result['code'] = $code;
        $result['message'] = $msg;
        return $result;
}

function ok($msg) {
        $result = array();
        $result['success'] = "true";
        $result['message'] = $msg;
        $result['data'] = array();
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


// Actions
if ($_REQUEST['action'] == "courses")
	ws_courses();
else if ($_REQUEST['action'] == "assignments")
	ws_assignments();
else if ($_REQUEST['action'] == "files")
	ws_files();
else if ($_REQUEST['action'] == "getFile")
	ws_getfile();
else if ($_REQUEST['action'] == "deploy")
	ws_deploy();
else if ($_REQUEST['action'] == "deploy_status")
	ws_deploy_status();
else if ($_REQUEST['action'] == "from_path")
	ws_from_path();

else
	json(error("ERR999", "Unknown action"));



?>
