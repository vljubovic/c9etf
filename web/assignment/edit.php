<?php


function assignment_change($course, $year, $external) {
	global $assignments, $asgn_file_path, $course_path, $backlink;
	$asgn_id = intval($_REQUEST['assignment']);
	
	// Check parameter validity
	$type = trim($_REQUEST['type']);
	if (!preg_match("/\w/", $type)) {
		niceerror("Invalid assignment type");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	$homework_id='0';
	if ($type == "homework") $homework_id = intval($_REQUEST['homework_id']);
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
	
	// Check for duplicates
	$asgn = array();
	foreach ($assignments as $a) {
		if ($a['id'] == $asgn_id) { $asgn=$a; continue; }
		if ($a['name'] == $name) {
			niceerror("Duplicate assignment name &quot;".$a['name']."&quot;");
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
		if ($a['path'] == $path) {
			niceerror("Duplicate assignment path &quot;".$a['path']."&quot;");
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
	}
	
	if (empty($asgn)) {
		niceerror("Unknown assignment");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	
	$old_asgn_path = $course_path . "/assignment_files/" . $asgn['path'];
	
	// Check for reduction in number of tasks
	if ($tasks < $asgn['tasks']) {
		$deletes = false;
		for ($i=$tasks+1; $i<=$asgn['tasks']; $i++) {
			if (array_key_exists($task, $asgn['task_files']) && !empty($asgn['task_files'][$i]))
				$deletes = true;
		}
		
		if ($deletes) {
			niceerror("Can't change number of tasks because you have files in removed tasks");
			print "<p>Please manually delete the following files:</p>\n<ul>\n";
			for ($i=$tasks+1; $i<=$asgn['tasks']; $i++) {
				if (array_key_exists($task, $asgn['task_files']) && !empty($asgn['task_files'][$i]))
					foreach ($asgn['task_files'][$i] as $file)
						print "<li>Task $i, File $file</li>\n";
			}
			print "</ul>\n";
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		
		} else {
			// Start deleting folders
			for ($i=$tasks+1; $i<=$asgn['tasks']; $i++) {
				if (file_exists($old_asgn_path . "/Z$i"))
					rmdir($old_asgn_path . "/Z$i");
			}
		}
	}
	
	// Move stuff is path is changed
	$new_asgn_path = $old_asgn_path;
	if ($path != $asgn['path']) {
		$new_asgn_path = $course_path . "/assignment_files/" . $path;
		if (!file_exists($new_asgn_path)) mkdir($new_asgn_path);
		for ($i=1; $i<=$tasks; $i++) {
			if (file_exists($old_asgn_path . "/Z$i"))
				rename($old_asgn_path . "/Z$i", $new_asgn_path . "/Z$i");
		}
		unlink($old_asgn_path);
	}
	
	// Update assignment file
	$asgn['name'] = $name;
	$asgn['type'] = $type;
	$asgn['path'] = $path;
	$asgn['homework_id'] = $homework_id;
	$asgn['tasks'] = $tasks;
	foreach ($assignments as &$a)
		if ($a['id'] == $asgn_id) $a=$asgn;
	
	file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));
	
	nicemessage("Assignment $asgn_id successfully changed");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
}


function assignment_edit($course, $year, $external) {
	global $assignments, $asgn_file_path, $course_path, $backlink;
	$asgn_id = intval($_REQUEST['assignment']);
	
	$asgn = array();
	foreach ($assignments as $a)
		if ($a['id'] == $asgn_id) $asgn=$a;
	if (empty($asgn)) {
		niceerror("Unknown assignment");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	
	?>
	<p><a href="<?=$backlink?>">Back to course details</a></p>
	
	<h2>Edit assignment</h2>
	<form action="edit.php" method="POST">
	<input type="hidden" name="action" value="change">
	<input type="hidden" name="course" value="<?=$course?>">
	<input type="hidden" name="year" value="<?=$year?>">
	<input type="hidden" name="external" value="<?=$external?>">
	<input type="hidden" name="assignment" value="<?=$asgn_id?>">
	<table border="0">
	<tr><td>ID:</td><td><?=$asgn['id']?></td></tr>
	<tr><td>Name:</td><td><input type="text" name="name" value="<?=$asgn['name']?>"></td></tr>
	<tr><td>Type:</td><td><input type="text" name="type" value="<?=$asgn['type']?>"></td></tr>
	<?php
	if ($asgn['type']=="homework") {
		?>
		<tr><td>Homework ID:</td><td><input type="text" name="homework_id" value="<?=$asgn['homework_id']?>"></td></tr>
		<?php
	}
	?>
	<tr><td>Path:</td><td><input type="text" name="path" value="<?=$asgn['path']?>"></td></tr>
	<tr><td>No. tasks:</td><td><input type="text" name="tasks" value="<?=$asgn['tasks']?>"></td></tr>
	<tr><td colspan="2" align="right"><input type="submit" value="Change"></td></tr>
	</table>
	</form>
	
	<h2>Tasks</h2>
	<?php
	
	// Tasks editing
	for ($task=1; $task<=$asgn['tasks']; $task++) {
		$file_change_link = "files.php?files_action=change&amp;course=$course&amp;year=$year&amp;external=$external&amp;assignment=$asgn_id&amp;task=$task&amp;";
		?>
		<h3>Task <?=$task?></h3>
		<?php
		if (array_key_exists($task, $asgn['task_files'])) {
			print "<p>Files:</p>\n<ul>\n";
			foreach ($asgn['task_files'][$task] as $file) {
				$task_file_link = $file_change_link . "file=" . urlencode($file) . "&amp;";
				$view_link = $task_file_link . "action=View";
				$delete_link = $task_file_link . "action=Delete";
				print "<li><a href=\"$view_link\">$file</a> - (<a href=\"$delete_link\">delete</a>)</li>\n";
			}
			print "</ul>\n";
		}
		?>
		<form action="files.php" method="POST"  enctype="multipart/form-data">
		<input type="hidden" name="files_action" value="change">
		<input type="hidden" name="action" value="Add">
		<input type="hidden" name="course" value="<?=$course?>">
		<input type="hidden" name="year" value="<?=$year?>">
		<input type="hidden" name="external" value="<?=$external?>">
		<input type="hidden" name="assignment" value="<?=$asgn_id?>">
		<input type="hidden" name="task" value="<?=$task?>">
		<p>Upload file:
		<input type="file" name="add"> <input type="submit" name="action" value="Add"></p>
		</form>
		<?php
	}
}


function assignment_create($course, $year, $external) {
	global $assignments, $asgn_file_path, $course_path, $backlink;
	
	// Input values & validation
	$type = $_REQUEST['type'];
	if ($type == "other") {
		$type = trim($_REQUEST['type_other']);
		if (!preg_match("/\w/", $type)) {
			niceerror("Invalid assignment type");
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
	}
	else if ($type == "homework") $homework_id = intval($_REQUEST['homework_id']);
	else if ($type != "tutorial" && $type != "exam" && $type != "independent") {
		niceerror("Invalid assignment type");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	
	$number = intval($_REQUEST['assignment_number']);
	if ($number < 1 || $number > 100) {
		niceerror("Invalid assignment number ".htmlentities($_REQUEST['assignment_number']));
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	
	$tasks = intval($_REQUEST['nr_tasks']);
	if ($tasks < 1 || $tasks > 100) {
		niceerror("Invalid number of tasks ".htmlentities($_REQUEST['nr_tasks']));
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	
	$asgn = array();
	$max_id = 0;
	foreach ($assignments as $a)
		if ($a['id'] > $max_id) $max_id = $a['id'];
	$asgn['id'] = $max_id + 1;
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
	
	// Find duplicates
	foreach($assignments as $a) {
		if ($a['name'] == $asgn['name']) {
			niceerror("Duplicate assignment name ".$asgn['name']);
			print "<p>Try using a different assignment number (currently: $number).</p>";
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
		if ($a['path'] == $asgn['path']) {
			niceerror("Duplicate assignment path ".$asgn['path']);
			print "<p>Try using a different assignment number (currently: $number).</p>";
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
	}
	$asgn['task_files'] = array();
	
	// Create folders
	$asgn_files_path = $course_path . "/assignment_files";
	if (!file_exists($asgn_files_path)) mkdir($asgn_files_path);
	$asgn_files_path = $asgn_files_path . "/". $asgn['path'];
	if (!file_exists($asgn_files_path)) mkdir($asgn_files_path);
	
	for ($i=1; $i<=$tasks; $i++) {
		$task_path = $asgn_files_path . "/Z$i";
		if (!file_exists($task_path)) mkdir($task_path);
		
		$task_name = $asgn['name'] . ", Zadatak $i";
		
		// Create .zadaca file 
		if ($type == "homework") {
			$zadaca = array( 'id' => $homework_id, 'zadatak' => $i, 'naziv' => $task_name );
			$zadaca_path = $task_path . "/.zadaca";
			file_put_contents($zadaca_path, json_encode($zadaca, JSON_PRETTY_PRINT));
			$asgn['task_files'][$i] = array(".zadaca");
		}
	}
	
	// Write assignments
	$assignments[] = $asgn;
	file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));
	
	nicemessage("Assignment successfully created");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
}



header('Content-Encoding: none;');
session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");


// Verify session and permissions, set headers

$logged_in = false;
if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
}

if (!$logged_in || !in_array($login, $conf_admin_users)) {
	?>
	<p style="color:red; weight: bold">Your session expired. Please log out then log in.</p>
	<?php
	return 0;
}

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');


// Set vars
$course = intval($_REQUEST['course']);
$year = intval($_REQUEST['year']);
$external = $_REQUEST['external'];
if (isset($_REQUEST['X'])) $external=1;

if ($external) {
	$course_path = $conf_data_path . "/X$course" . "_$year";
	$backlink = "../admin.php?course=$course&amp;year=$year&amp;X";
} else {
	$course_path = $conf_data_path . "/$course" . "_$year";
	$backlink = "../admin.php?course=$course&amp;year=$year";
}
if (!file_exists($course_path)) mkdir($course_path);

$asgn_file_path = $course_path . "/assignments";
$assignments = array();
if (file_exists($asgn_file_path))
	$assignments = json_decode(file_get_contents($asgn_file_path), true);

	
// HTML

	?>
<!DOCTYPE html>
<html>
<head>
	<title>Assignments - actions</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
</head>
<body>
	<?php

// Actions
if (isset($_REQUEST['action'])) {
	if ($_REQUEST['action'] == "create") assignment_create($course, $year, $external);
	if ($_REQUEST['action'] == "edit") assignment_edit($course, $year, $external);
	if ($_REQUEST['action'] == "change") assignment_change($course, $year, $external);
}

?>
</body>
</html>