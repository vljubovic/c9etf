<?php

// ADMIN/FILES.PHP - various functions for handling course files



// This function provides a list of course files with some buttons
// Meant to be called from admin.php
function assignment_files($course, $year, $external) {
	global $conf_data_path, $conf_base_path;

	if ($external)
		$course_path = $conf_data_path . "/X$course" . "_$year";
	else
		$course_path = $conf_data_path . "/$course" . "_$year";
	if (!file_exists($course_path)) mkdir($course_path);
	
	$files_path = $course_path . "/files";
	if (!file_exists($files_path)) mkdir($files_path);
	
	$files = scandir($files_path); $count = count($files);
	for ($i=0; $i<$count; $i++) {
		if (is_dir($files_path . "/" . $files[$i]) || $files[$i] == "..") 
			unset($files[$i]);
	}
	
	if (count($files)==0) print "<p>No files are defined</p>\n";
	else print "<ul>\n";
	foreach($files as $file) {
		?>
		<form action="assignment/files.php" method="POST">
		<input type="hidden" name="files_action" value="change">
		<input type="hidden" name="course" value="<?=$course?>">
		<input type="hidden" name="year" value="<?=$year?>">
		<input type="hidden" name="external" value="<?=$external?>">
		<input type="hidden" name="file" value="<?=$file?>">
		<li><?=$file?> <input type="submit" name="action" value="View"><input type="submit" name="action" value="Delete"></li>
		</form>
		<?php
	}
	if (count($files)!=0) print "</ul>\n";
	
	?>
	<form action="assignment/files.php" method="POST"  enctype="multipart/form-data">
	<input type="hidden" name="files_action" value="change">
	<input type="hidden" name="course" value="<?=$course?>">
	<input type="hidden" name="year" value="<?=$year?>">
	<input type="hidden" name="external" value="<?=$external?>">
	<p>Add a file: 
	<input type="file" name="add"> <input type="submit" name="action" value="Add"></p>
	</form>
	<?php
}


// This function performs actions clicked by button
// Meant to be invoked directly
function assignment_files_change() {
	global $conf_data_path, $course, $year, $external, $course_path, $course_link, $asgn_file_path, $assignments, $login, $conf_admin_users, $conf_sysadmins;

	require_once("../../lib/config.php"); // Webide config
	require_once("../../lib/webidelib.php"); // Webide library
	require_once("../login.php"); // Login
	require_once("../admin/lib.php"); // Admin library
	require_once("lib.php"); // Assignment library

	// Verify session and permissions, set headers
	admin_session();
	admin_set_headers();
	
	// Set core variables
	assignment_global_init();
	admin_check_permissions($course, $year, $external);
	
	if (isset($_REQUEST['assignment'])) {
		$asgn_id = intval($_REQUEST['assignment']);
		$task = intval($_REQUEST['task']);
		$asgn_edit_link = assignment_edit_link($asgn_id);
		
		$asgn = assignment_get($asgn_id);
		if (!$asgn) {
			niceerror("Assignment not found");
			print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
			return;
		}
		
		if ($task < 1 || $task > $asgn['tasks']) {
			niceerror("Invalid task number");
			print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
			print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";
			return;
		}
		
		$files_path = assignment_get_task_path($asgn, $task);
	} else {
		$files_path = $course_path . "/files";
		if (!file_exists($files_path)) mkdir($files_path);
	}
	
	
	// HTML code	
	?>
<!DOCTYPE html>
<html>
<head>
	<title>Assignment files - actions</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
</head>
<body>
	<?php
	
	// Perform action
	if ($_REQUEST['action'] == "View" || $_REQUEST['action'] == "Delete") {
		$file_name = basename($_REQUEST['file']);
		$file_path = $files_path . "/$file_name";
		if (!file_exists($file_path)) {
			print "<p><b style=\"color:red\">File $file_path doesn't exist.</b></p>";
			if ($_REQUEST['action'] == "View") { 
				print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
				print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";
				exit(0);
			}
		}
	}
	
	if ($_REQUEST['action'] == "View") {
		$download_url = "ws.php?action=getFile&amp;course=$course&amp;year=$year&amp;assignment=$asgn_id&amp;task=$task&amp;file=$file_name";
		if ($external) $download_url .= "&amp;X";
		print "<h1>Viewing file: $file_name - <a href=\"$download_url\">Download</a></h1>\n";
		print "<pre>";
		print htmlentities(file_get_contents($file_path));
		print "</pre>\n";
		print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
		print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";
	}
	if ($_REQUEST['action'] == "Delete") {
		unlink($file_path);
		admin_log("delete $file_path");
		print "<p>File deleted</p>";
		print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
		print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";
		
		// Update assignment task_files
		if (isset($_REQUEST['assignment'])) {
			foreach ($asgn['task_files'][$task] as $i => $task_file) {
				if ($asgn['task_files'][$task][$i] == $file_name) 
					unset($asgn['task_files'][$task][$i]);
			}
			assignment_update($asgn);
		}
	}
	if ($_REQUEST['action'] == "Add") {
		$temporary = $_FILES['add']['tmp_name'];
		
		// Prevent XSS through filename
		$destination = strip_tags(basename($_FILES['add']['name']));
		$destination = str_replace("&", "", $destination);
		$destination = str_replace("\"", "", $destination);
		$destination_path = $files_path . "/$destination";
		
		if (file_exists($destination_path) || empty($destination) || strlen($destination)<2) {
			print "<p><b style=\"color:red\">File named ".$_FILES['add']['name'].". already exists.</b> Please choose another name and resend</p>";
			print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
			print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";
			exit(0);
		}
		
		rename($temporary, $destination_path);
		
		// Update assignment task_files
		if (isset($_REQUEST['assignment'])) {
			$asgn['task_files'][$task][] = $destination;
			assignment_update($asgn);
		}

		admin_log("upload $destination_path");
		print "<p>File uploaded.</p>\n";
		print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
		print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";
	}
	
	?>
</body>
</html>
	<?php
}

if (isset($_REQUEST['files_action']) && $_REQUEST['files_action'] == "change") assignment_files_change();


?>
