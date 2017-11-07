<?php


function assignment_copy($course, $year, $external, $asgn_id) {
	global $assignments, $asgn_file_path, $prev_assignments, $prev_asgn_file_path, $course_path, $prev_course_path, $backlink;
	
	$asgn = false;
	foreach($prev_assignments as $pa) {
		if ($pa['id'] == $asgn_id) $asgn = $pa;
	}
	if ($asgn === false) {
		niceerror("Assignment not found");
		return;
	}
	
	if (isset($_REQUEST['hidden'])) $asgn['hidden'] = "true";
	
	$name_exists = $path_exists = false;
	foreach($assignments as $a) {
		if ($a['name'] == $asgn['name']) $name_exists = true;
		if ($a['path'] == $asgn['path']) $path_exists = true;
	}
	if ($name_exists) {
		niceerror("Assignment with this name already exists");
		return;
	}
	if ($path_exists) {
		niceerror("Assignment with this folder name (path) already exists");
		return;
	}
	
	
	// Copy data files
	$asgn_files_path = $course_path . "/assignment_files";
	if (!file_exists($asgn_files_path)) mkdir($asgn_files_path);
	$asgn_files_path = $asgn_files_path . "/". $asgn['path'];
	if (!file_exists($asgn_files_path)) mkdir($asgn_files_path);
	
	$prev_asgn_files_path = $prev_course_path . "/assignment_files";
	$prev_asgn_files_path = $prev_asgn_files_path . "/". $asgn['path'];
	
	for ($i=1; $i<=$asgn['tasks']; $i++) {
		$task_path = $asgn_files_path . "/Z$i";
		if (!file_exists($task_path)) mkdir($task_path);
		$prev_task_path = $prev_asgn_files_path . "/Z$i";
		
		foreach($asgn['task_files'][$i] as $filename) {
			copy ($prev_task_path . "/" . $filename, $task_path . "/" . $filename);
		}
	}
	
	
	// Add new assignment to list of assignments
	$max_id = 0;
	foreach ($assignments as $a)
		if ($a['id'] > $max_id) $max_id = $a['id'];
	$asgn['id'] = $max_id + 1;
	
	$assignments[] = $asgn;
	file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));
	
	
	nicemessage("Assignment " . $asgn['name'] . " ($asgn_id) successfully copied from last year");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
}



require_once("../../lib/config.php"); // Webide config
require_once("../../lib/webidelib.php"); // Webide library
require_once("../login.php"); // Login
require_once("../admin/lib.php"); // Admin library
require_once("lib.php"); // Assignment library


// Verify session and permissions, set headers
admin_check_permissions($_REQUEST['course'], $_REQUEST['year']);
admin_set_headers();


// Set vars
assignment_global_init();


// HTML

?>
<!DOCTYPE html>
<html>
<head>
	<title>Assignments - copy from last year</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
</head>
<body>
<h1>Copy assignments from previous year</h1>
	<?php


// Initialize various variables related to course/assignment data for the
// previous year of same course
if ($external) {
	$prev_course_path = $conf_data_path . "/X$course" . "_" . ($year-1);
} else {
	$prev_course_path = $conf_data_path . "/$course" . "_" . ($year-1);
}

$prev_asgn_file_path = $prev_course_path . "/assignments";

if (!file_exists($prev_course_path) || !file_exists($prev_asgn_file_path)) {
	niceerror("No data from previous year found");
	return;
}

$prev_assignments = json_decode(file_get_contents($prev_asgn_file_path), true);
	
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
usort($prev_assignments, "cmp");

if (isset($_REQUEST['action']) && $_REQUEST['action'] == "create") {
	$asgn = intval($_REQUEST['asgn']);
	assignment_copy($course, $year, $external, $asgn);
}

else {
	?>
	<form action="copy.php" method="POST">
	<input type="hidden" name="action" value="create">
	<input type="hidden" name="course" value="<?=$course?>">
	<input type="hidden" name="year" value="<?=$year?>">
	<input type="hidden" name="external" value="<?=($external)?"1":"0"?>">
	<p>Select assignment: <select name="asgn">
	<?php

	foreach($prev_assignments as $pa) {
		$exists = false;
		foreach($assignments as $a) {
			if ($a['name'] == $pa['name']) $exists = true;
		}
		
		$name = $pa['name'];
		if ($exists) $name .= " - EXISTS!";
		?>
		<option value="<?=$pa['id']?>"><?=$name?></option>
		<?php
	}
	?>
	</select></p>
	<p><input type="checkbox" name="hidden"> Make hidden</p>
	<input type="submit" value=" Copy "></form>
	<?php
}

?>
</body>
</html>
