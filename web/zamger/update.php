<?php

// TODO: port this code to classes

function zamger_update_teacher_courses($login, $force) {
	global $conf_data_path, $conf_current_year, $conf_zamger_update_interval;
	
	$user_courses = array();
	
	$user_courses_path = $conf_data_path . "/user_courses";
	if (!file_exists($user_courses_path)) mkdir($user_courses_path);
	$user_courses_path .= "/$login.json";
	$permissions_path = $conf_data_path . "/permissions.json";
	
	$user_courses = [];
	if (file_exists($user_courses_path))
		$user_courses = json_decode(file_get_contents($user_courses_path), true);
	$permissions = [];
	if (file_exists($permissions_path))
		$permissions = json_decode(file_get_contents($permissions_path), true);
	
	if (!$force) {
		if ($conf_zamger_update_interval == 0)
			json(ok("Courses not updated for teacher"));
		
		$last_update = 0;
		if (array_key_exists("last_update", $user_courses)) 
			$last_update = $user_courses['last_update'];
		$time_since_update = time() - $last_update;
		// Convert to hours
		$time_since_update /= 3600;
		if ($time_since_update < $conf_zamger_update_interval)
			json(ok("Courses not updated for teacher"));
	}
	
	// We will also update courses.json
	$courses_path = $conf_data_path . "/courses.json";
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	else
		$courses = array();
	
	$user_courses['last_update'] = time();
	
	require_once(__DIR__."/courses.php");
	$data = ok("Courses updated for teacher");
	$teacher_courses = teacher_courses($conf_current_year);
	$data['c'] = $teacher_courses;
	foreach($teacher_courses as $tc) {
		// Update courses.json
		$found = false;
		foreach($courses as $c) {
			if ($c['id'] == $tc['CourseUnit']['id'] && $c['type'] == "external") {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$new_c = array();
			$new_c['id'] = $tc['CourseUnit']['id'];
			$new_c['name'] = $tc['CourseUnit']['name'];
			$new_c['abbrev'] = $tc['CourseUnit']['abbrev'];
			$new_c['type'] = "external";
			$courses[] = $new_c;
		}
		
		// Update user_courses
		$course_id = "X" . $tc['CourseUnit']['id'] . "_" . $conf_current_year;
		if (!array_key_exists('teacher', $user_courses)) 
			$user_courses['teacher'] = array();
		if (!in_array($course_id, $user_courses['teacher']))
			$user_courses['teacher'][] = $course_id;
		if (!array_key_exists($login, $permissions))
			$permissions[$login] = array( $course_id );
		else if (!in_array($course_id, $permissions[$login]))
			$permissions[$login][] = $course_id;
	}
	file_put_contents($courses_path, json_encode($courses, JSON_PRETTY_PRINT));
	file_put_contents($user_courses_path, json_encode($user_courses, JSON_PRETTY_PRINT));
	file_put_contents($permissions_path, json_encode($permissions, JSON_PRETTY_PRINT));
	json($data);
}


function zamger_update_groups($course_id, $force) {
	global $conf_data_path, $conf_current_year, $conf_zamger_update_interval;

	// Get course
	$courses_path = $conf_data_path . "/courses.json";
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	else
		$courses = array();
	
	$course = array();
	foreach($courses as $c) {
		if ($c['type'] != "external") continue;
		$c_course_id = "X" . $c['id'] . "_";
		if (starts_with($course_id, $c_course_id)) {
			$course = $c;
			$academic_year = substr($course_id, strlen($c_course_id));
			
		}
	}
	
	if (empty($course))
		json(error("ERR001", "Course id $course_id not found"));
	
	// Get groups file
	$groups = array();
	$groups_path = $conf_data_path . "/" . $course_id . "/groups";
	
	// Course is not configured on c9
	if (!file_exists($conf_data_path . "/" . $course_id))
		json(ok("Groups not updated for course $course_id"));
		
	if (file_exists($groups_path))
		$groups = json_decode(file_get_contents($groups_path), true);
	
	// Check update interval
	if (!$force) {
		if ($conf_zamger_update_interval == 0)
			json(ok("Groups not updated for course $course_id"));
		
		$last_update = 0;
		if (array_key_exists("last_update", $groups)) 
			$last_update = $groups['last_update'];
		$time_since_update = time() - $last_update;
		// Convert to hours
		$time_since_update /= 3600;
		if ($time_since_update < $conf_zamger_update_interval)
			json(ok("Groups not updated for course $course_id"));
	}
	
	require_once(__DIR__."/groups.php");
	$api_groups = zamger_group_list($course['id'], $academic_year);
	if ($api_groups === false)
		json(error("ERR004", "Failed to retrieve groups for course $course_id"));		

	foreach($api_groups as $grp) {
		if ($grp['virtual'] == true) $grp['name'] = "(All students)";
		$groups[$grp['id']] = $grp['name'];
	}
	$groups[$course_id] = "Members without group";
	$groups['last_update'] = time();
	
	file_put_contents($groups_path, json_encode($groups, JSON_PRETTY_PRINT));
	json(ok("Groups updated for course $course_id"));
}


// Update the "all students" group
function zamger_update_allstudents($course_id, $force) {
	global $conf_data_path, $conf_current_year, $conf_zamger_update_interval, $conf_base_path;

	// Get course
	$courses_path = $conf_data_path . "/courses.json";
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	else
		$courses = array();
	
	$course = array();
	foreach($courses as $c) {
		if ($c['type'] != "external") continue;
		$c_course_id = "X" . $c['id'] . "_";
		if (starts_with($course_id, $c_course_id)) {
			$course = $c;
			$academic_year = substr($course_id, strlen($c_course_id));
			
		}
	}
	
	if (empty($course))
		json(error("ERR001", "Course id $course_id not found"));

	// Find "all students" group id
	$groups_path = $conf_data_path . "/" . $course_id . "/groups";
	if (!file_exists($groups_path)) 
		zamger_update_groups($course_id, true); // Force update
	$groups = json_decode(file_get_contents($groups_path), true);
	
	$all_students_id = false;
	foreach($groups as $id => $name) 
		if ($name == "(All students)")
			$all_students_id = $id;
	if ($all_students_id === false)
		json(error("ERR002", "No (All students) group on course $course_id"));
	
	// Check update interval
	if (!file_exists($conf_data_path . "/groups/")) mkdir($conf_data_path . "/groups/");
	$as_group_file = $conf_data_path . "/groups/" . $all_students_id;
	$asgroup = array();
	if (file_exists($as_group_file)) 
		$asgroup = json_decode(file_get_contents($as_group_file), true);
	if (!$force) {
		if ($conf_zamger_update_interval == 0)
			json(ok("All students not updated for course $course_id"));
		
		$last_update = 0;
		if (array_key_exists("all_students_update", $asgroup)) 
			$last_update = $groups['all_students_update'];
		$time_since_update = time() - $last_update;
		// Convert to hours
		$time_since_update /= 3600;
		if ($time_since_update < $conf_zamger_update_interval)
			json(ok("All students not updated for course $course_id $time_since_update $all_students_id"));
	}
	
	// Update users file
	eval(file_get_contents("$conf_base_path/users"));
	
	// Get all students
	require_once(__DIR__."/groups.php");
	$allStudents = zamger_all_students($course['id'], $academic_year);
	if ($allStudents === false)
		json(error("ERR004", "Failed to retrieve all students for course $course_id"));		

	if ($allStudents['id'] != $all_students_id)
		json(error("ERR003", "Mismatch id for (All students) group on course $course_id"));
	
	$asgroup = array(
		"id" => $allStudents['id'], 
		"name" => "(All students)", 
		"course" => $course_id, 
		"year" => $academic_year, 
		"course_type" => "external", 
		"members" => array(),
		"last_update" => time()
	);
	$mwggroup = array(
		"id" => $course_id, 
		"name" => "Members without group", 
		"course" => $course_id, 
		"year" => $academic_year, 
		"course_type" => "external", 
		"members" => array(),
		"last_update" => time()
	);
	
	$user_courses_path = $conf_data_path . "/user_courses/";
	
	foreach ($allStudents['members'] as $member) {
		$id = $member['student']['login'];
		$name = $member['student']['name'] . " " . $member['student']['surname'];
		
		$asgroup['members'][$id] = $name;
		
		if (!array_key_exists('Group', $member) || empty($member['Group']))
			$mwggroup['members'][$id] = $name;
		
		// Update user courses
		$user_courses = array();
		$user_courses_path_current = $user_courses_path . "/" . $member['student']['login'] . ".json";
		if (file_exists($user_courses_path_current))
			$user_courses = json_decode(file_get_contents($user_courses_path_current), true);
		
		if (!array_key_exists('student', $user_courses)) 
			$user_courses['student'] = array();
		if (!in_array($course_id, $user_courses['student']))
			$user_courses['student'][] = $course_id;
		
		file_put_contents($user_courses_path_current, json_encode($user_courses, JSON_PRETTY_PRINT));
		
		if (array_key_exists($id, $users)) {
			$users[$id]['realname'] = $name;
			if (!array_key_exists('email', $users[$id]) || empty($users[$id]['email']))
				$users[$id]['email'] = $id . '@' . $conf_zamger_email_domain;
		}
	}
	
	// Write users file
	file_put_contents( "$conf_base_path/users", "\$users = ". var_export($users, true) . ";" );
	
	// Write group files
	file_put_contents($as_group_file, json_encode($asgroup, JSON_PRETTY_PRINT));
	
	$mwg_group_file = $conf_data_path . "/groups/" . $course_id;
	file_put_contents($mwg_group_file, json_encode($mwggroup, JSON_PRETTY_PRINT));
	json(ok("All students updated for course $course_id"));
}



// Update members of one group
function zamger_update_group($group_id, $force) {
	global $conf_data_path, $conf_current_year, $conf_zamger_update_interval;
	
	// Check update interval
	if (!file_exists($conf_data_path . "/groups/")) mkdir($conf_data_path . "/groups/");
	$group_file = $conf_data_path . "/groups/" . $group_id;
	$group = array();
	if (file_exists($group_file)) 
		$group = json_decode(file_get_contents($group_file), true);
	
	if (!$force) {
		if ($conf_zamger_update_interval == 0)
			json(ok("Group $group_id not updated"));
		
		$last_update = 0;
		if (array_key_exists("last_update", $group)) 
			$last_update = $group['last_update'];
		$time_since_update = time() - $last_update;
		// Convert to hours
		$time_since_update /= 3600;
		if ($time_since_update < $conf_zamger_update_interval)
			json(ok("Group $group_id not updated"));
	}
	
	// Get all students
	require_once(__DIR__."/groups.php");
	$group_data = zamger_group_members($group_id, $conf_current_year);
	if ($group_data === false)
		json(error("ERR004", "Failed to retrieve members for group $group_id"));		
	
	$course_id = "X" . $group_data['CourseUnit']['id'] . "_" . $group_data['AcademicYear']['id'];
	
	$group = array(
		"id" => $group_data['id'], 
		"name" => $group_data['name'],
		"course" => $course_id, 
		"year" => $group_data['AcademicYear']['id'], 
		"course_type" => "external", 
		"members" => array(),
		"last_update" => time()
	);
	
	foreach ($group_data['members'] as $member) {
		$id = $member['Person']['login'];
		$name = $member['Person']['name'] . " " . $member['Person']['surname'];
		
		$group['members'][$id] = $name;
	}
	
	// Write group files
	file_put_contents($group_file, json_encode($group, JSON_PRETTY_PRINT));
	json(ok("Group $group_id updated"));
}



function zamger_update_all($login) {
	global $conf_data_path, $conf_current_year;

	$courses_path = $conf_data_path . "/courses.json";
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	else
		$courses = array();
	
	require_once(__DIR__."/courses.php");
	require_once(__DIR__."/groups.php");
	$teacher_courses = teacher_courses($conf_current_year);
	
	foreach($teacher_courses as $tc) {
		$found = false;
		foreach($courses as $c) {
			if ($c['id'] == $tc['id'] && $c['type'] == "external") {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$new_c = array();
			$new_c['id'] = $tc['id'];
			$new_c['name'] = $tc['naziv'];
			$new_c['abbrev'] = $tc['kratki_naziv'];
			$new_c['type'] = "external";
			$courses[] = $new_c;
		}
		
		// Update groups for course
		$course_id = "X" . $tc['id'] . "_" . $conf_current_year;
		$course_path = $conf_data_path . "/$course_id";
		if (!file_exists($course_path)) mkdir($course_path);
		
		$group_path = $conf_data_path . "/groups";
		if (!file_exists($group_path)) mkdir($group_path);
		
		$groups = zamger_group_list($tc['id'], $conf_current_year);
		$all_student_id = 0;
		foreach($groups as $id => &$name) {
			if ($name == "(Svi studenti)") {
				$name = "(All students)";
				$all_student_id = $id;
			}
		}
		$all_students = zamger_group_members($all_student_id);
		
		foreach($groups as $id => $name) {
			$gr = array();
			$gr['id'] = $id;
			$gr['name'] = $name;
			$gr['members'] = zamger_group_members($id);
			$gr['course'] = $course_id;
			$gr['year'] = $conf_current_year;
			$gr['course_type'] = "external";
			foreach ($gr['members'] as $login => $fullname) {
				unset($all_students[$login]);
			}
			$group_file = $group_path . "/$id";
			file_put_contents($group_file, json_encode($gr, JSON_PRETTY_PRINT));
		}
		
		$group_file = $group_path . "/$course_id";
		$gr = array();
		$gr['id'] = "$course_id";
		$gr['name'] = "Members without group";
		$gr['members'] = $all_students;
		$gr['course'] = $course;
		$gr['year'] = $year;
		$gr['course_type'] = "external";
		file_put_contents($group_file, json_encode($gr, JSON_PRETTY_PRINT));
		
		$groups[$course_id] = "Members without group";
		$group_list_file = $course_path . "/groups";
		file_put_contents($group_list_file, json_encode($groups, JSON_PRETTY_PRINT));
	}
	//file_put_contents($courses_path, json_encode($courses, JSON_PRETTY_PRINT));
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

$force = false;
if (isset($_REQUEST['force']) && $_REQUEST['force'] != "false") $force = true;


// Actions
if ($_REQUEST['action'] == "teacher_courses")
	zamger_update_teacher_courses($login, $force);
else if ($_REQUEST['action'] == "groups")
	zamger_update_groups($_REQUEST['course_id'], $force);
else if ($_REQUEST['action'] == "all_students")
	zamger_update_allstudents($_REQUEST['course_id'], $force);
else if ($_REQUEST['action'] == "group")
	zamger_update_group($_REQUEST['group_id'], $force);

else
	json(error("ERR999", "Unknown action"));


?>
