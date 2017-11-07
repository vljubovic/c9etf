<?php


function assignment_change($course, $year, $external) {
	global $assignments, $asgn_file_path, $course_path, $course_link;
	$asgn_id = intval($_REQUEST['assignment']);
	
	$backlink = assignment_edit_link($asgn_id);
	
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
	if (isset($_REQUEST['hidden'])) $hidden = "true"; else $hidden = "false";
	
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
	$asgn['hidden'] = $hidden;
	assignment_update($asgn);
	
	nicemessage("Assignment $asgn_id successfully changed");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
}


function assignment_edit($course, $year, $external) {
	global $assignments, $asgn_file_path, $course_path, $course_link, $course_data;
	$asgn_id = intval($_REQUEST['assignment']);
	$asgn = assignment_get($asgn_id);
	if (!$asgn) {
		niceerror("Unknown assignment");
		print "<p><a href=\"$course_link\">Go back</a></p>\n";
		return;
	}
	
	if (array_key_exists('hidden', $asgn) && $asgn['hidden'] == "true") $hidden_html = "CHECKED"; else $hidden_html = "";
	
	?>
	<p><a href="<?=$course_link?>">Back to course details</a></p>
	
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
	<tr><td>Hidden:</td><td><input type="checkbox" name="hidden" <?=$hidden_html?>></td></tr>
	<tr><td colspan="2" align="right"><input type="submit" value="Change"></td></tr>
	</table>
	</form>
	
	<h2>Tasks</h2>
	<?php
	
	// Tasks editing
	for ($task=1; $task<=$asgn['tasks']; $task++) {
		$url_part = "course=$course&amp;year=$year&amp;external=$external&amp;assignment=$asgn_id&amp;task=$task&amp;";
		$file_change_link = "files.php?files_action=change&amp;$url_part";
		?>
		<h3>Task <?=$task?></h3>
		<?php
		
		$zadaca_exists = $autotest_exists = false;
		
		if (array_key_exists($task, $asgn['task_files'])) {
			print "<p>Files:</p>\n<ul>\n";
			foreach ($asgn['task_files'][$task] as $file) {
				$task_file_link = $file_change_link . "file=" . urlencode($file) . "&amp;";
				$view_link = $task_file_link . "action=View";
				$delete_link = $task_file_link . "action=Delete";
				print "<li><a href=\"$view_link\">$file</a> - (<a href=\"$delete_link\">delete</a>)</li>\n";
				if ($file == ".zadaca")
					$zadaca_exists = true;
				if ($file == ".autotest")
					$autotest_exists = true;
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
		
		if (!$autotest_exists && array_key_exists("language", $course_data)) {
			?>
			<p><a href="../autotest/create.php?<?=$url_part?>">Create .autotest file</a></p>
			<?php
		}
		
		if (!$zadaca_exists && $asgn['type']=="homework") {
			?>
			<p><a href="edit.php?action=create_zadaca&amp;<?=$url_part?>">Create .zadaca file</a></p>
			<?php
		}
	}
}


function assignment_edit_create_zadaca($course, $year, $external) {
	global $assignments, $asgn_file_path, $course_path, $course_link, $course_data;
	
	$asgn_id = intval($_REQUEST['assignment']);
	$task = intval($_REQUEST['task']);
	
	$backlink = assignment_edit_link($asgn_id);
	
	// Validation checking
	$asgn = assignment_get($asgn_id);
	if (!$asgn) {
		niceerror("Unknown assignment");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	if ($task > $asgn['tasks']) { 
		niceerror("Unknown task");
		print "<p><a href=\"$backlink\">Go back</a></p>\n";
		return;
	}
	
	assignment_create_zadaca($asgn, $task);
	assignment_update($asgn);
	
	nicemessage("File .zadaca successfully created");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
}


function assignment_create($course, $year, $external) {
	global $assignments, $asgn_file_path, $course_path, $course_link;
	
	// Input values & validation
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
	
	$number = intval($_REQUEST['assignment_number']);
	if ($number < 1 || $number > 100) {
		niceerror("Invalid assignment number ".htmlentities($_REQUEST['assignment_number']));
		print "<p><a href=\"$course_link\">Go back</a></p>\n";
		return;
	}
	
	$tasks = intval($_REQUEST['nr_tasks']);
	if ($tasks < 1 || $tasks > 100) {
		niceerror("Invalid number of tasks ".htmlentities($_REQUEST['nr_tasks']));
		print "<p><a href=\"$course_link\">Go back</a></p>\n";
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
			print "<p><a href=\"$course_link\">Go back</a></p>\n";
			return;
		}
		if ($a['path'] == $asgn['path']) {
			niceerror("Duplicate assignment path ".$asgn['path']);
			print "<p>Try using a different assignment number (currently: $number).</p>";
			print "<p><a href=\"$course_link\">Go back</a></p>\n";
			return;
		}
	}
	$asgn['task_files'] = array();
	
	for ($i=1; $i<=$tasks; $i++) {
		// This will create folders and add .zadaca file if type is homework
		assignment_create_zadaca($asgn, $i);
	}
	
	// Write assignments
	$assignments[] = $asgn;
	file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));
	
	nicemessage("Assignment successfully created");
	print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
	print "<p><a href=\"" . assignment_edit_link($asgn['id']) . "\">Edit assignment</a></p>\n";
}



require_once("../../lib/config.php"); // Webide config
require_once("../../lib/webidelib.php"); // Webide library
require_once("../login.php"); // Login
require_once("../admin/lib.php"); // Admin library
require_once("../admin/courses.php"); // Courses
require_once("lib.php"); // Assignment library


// Verify session and permissions, set headers
admin_check_permissions($_REQUEST['course'], $_REQUEST['year']);
admin_set_headers();


// Set vars
assignment_global_init();

// Find course 
$course_data = admin_courses_get($course, $external);


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
	if ($_REQUEST['action'] == "create_zadaca") assignment_edit_create_zadaca($course, $year, $external);
	if ($_REQUEST['action'] == "change") assignment_change($course, $year, $external);
}

?>
</body>
</html>
