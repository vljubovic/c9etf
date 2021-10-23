<?php

function assignment_get($id) {
	global $assignments;
	
	foreach($assignments as $a) {
		if ($a['id'] == $id) return $a;
		if (array_key_exists('items', $a)) {
			$result = assignment_find_by_id($a['items'], $id);
			if ($result) return $result;
		}
	}
	return false;
}

function assignment_update($asgn) {
	global $assignments, $asgn_file_path;
	
	foreach($assignments as &$a) {
		if ($a['id'] == $asgn['id']) $a = $asgn;
	}
	file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));
}

// Initialize global variables from request params
function assignment_global_init() {
	global $conf_data_path, $course, $year, $external, $course_path, $course_link, $asgn_file_path, $assignments;

	$course = intval($_REQUEST['course']);
	$year = intval($_REQUEST['year']);
	$external = 0;
	if (isset($_REQUEST['external'])) $external = $_REQUEST['external'];
	if (isset($_REQUEST['X'])) $external = 1;

	if ($external) {
		$course_path = $conf_data_path . "/X$course" . "_$year";
		$course_link = "../admin.php?course=$course&amp;year=$year&amp;X";
	} else {
		$course_path = $conf_data_path . "/$course" . "_$year";
		$course_link = "../admin.php?course=$course&amp;year=$year";
	}
	if (!file_exists($course_path)) mkdir($course_path);

	$asgn_file_path = $course_path . "/assignments";
	$assignments = array();
	if (file_exists($asgn_file_path))
		$assignments = json_decode(file_get_contents($asgn_file_path), true);
}

function assignment_edit_link($asgn_id) {
	global $course, $year, $external;
	return "edit.php?action=edit&amp;course=$course&amp;year=$year&amp;external=$external&amp;assignment=$asgn_id";
}

function assignment_get_path($asgn) {
	global $course_path;
	
	$asgn_files_path = $course_path . "/assignment_files";
	if (!file_exists($asgn_files_path)) mkdir($asgn_files_path);
	$asgn_files_path = $asgn_files_path . "/". $asgn['path'];
	if (!file_exists($asgn_files_path)) mkdir($asgn_files_path);
	return $asgn_files_path;
}

function assignment_get_task_path($asgn, $task) {
	$asgn_files_path = assignment_get_path($asgn);
	$task_path = $asgn_files_path . "/Z$task";
	if (!file_exists($task_path)) mkdir($task_path);
	return $task_path;
}

function assignment_create_zadaca(&$asgn, $task) {
	if ($asgn['type'] != "homework") return;
	
	if (array_key_exists($task, $asgn['task_files'])) {
		if (in_array(".zadaca", $asgn['task_files'][$task])) {
			niceerror("File .zadaca already exists");
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
	} else {
		$asgn['task_files'][$task] = array();
	}
	
	$task_name = $asgn['name'] . ", Zadatak $task";
	$zadaca = array( 'id' => $asgn['homework_id'], 'zadatak' => $task, 'naziv' => $task_name );
	
	$task_path = assignment_get_task_path($asgn, $task);
	$zadaca_path = $task_path . "/.zadaca";
	file_put_contents($zadaca_path, json_encode($zadaca, JSON_PRETTY_PRINT));

	$asgn['task_files'][$task][] = ".zadaca";
	
	return $asgn;
}


function assignment_from_request() {
	$homework_id='0';
	$type = $_REQUEST['type'];
	if ($type == "other") {
		$type = trim($_REQUEST['type_other']);
		if (!preg_match("/\w/", $type)) {
			niceerror("Invalid assignment type");
			print "<p><a href=\"$course_link\">Go back</a></p>\n";
			return;
		}
	}
	else if ($type == "homework") $homework_id = intval($_REQUEST['homework_id']);
	else if ($type != "tutorial" && $type != "exam" && $type != "independent") {
		niceerror("Invalid assignment type");
		print "<p><a href=\"$course_link\">Go back</a></p>\n";
		return;
	}
	
	$tasks = intval($_REQUEST['tasks']);
	if ($tasks < 1 || $tasks > 100) {
		niceerror("Invalid number of tasks ".htmlentities($_REQUEST['nr_tasks']));
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	
	$name = trim($_REQUEST['name']);
	if (empty($name)) {
		niceerror("Invalid name");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	$path = basename(trim($_REQUEST['path']));
	if (empty($path)) {
		niceerror("Invalid path");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	if (isset($_REQUEST['hidden'])) $hidden = "true"; else $hidden = "false";

	$asgn = array();
	$asgn['type'] = $type;
	$asgn['tasks'] = $tasks;
	
	// Localized name
	if ($type == "homework") {
		$asgn['name'] = "Zadaća $number";
		$asgn['path'] = "Z$number";
		$asgn['homework_id'] = $homework_id;
	} else if ($type == "tutorial") {
		$asgn['name'] = "Tutorijal $number";
		$asgn['path'] = "T$number";
	} else if ($type == "exam") {
		$asgn['name'] = "Ispit $number";
		$asgn['path'] = "Ispit$number";
	} else if ($type == "independent") {
		$asgn['name'] = "ZSR $number";
		$asgn['path'] = "ZSR$number";
	} else {
		$asgn['name'] = "$type $number";
		$asgn['path'] = "";
		for ($i=0; $i<strlen($type)-1; $i++) {
			if ($type[$i] == " ") $asgn['path'] .= strtoupper($type[$i+1]);
			else if ($i==0) $asgn['path'] .= strtoupper($type[$i]);
		}
		$asgn['path'] .= "$number";
	}
}


?>
