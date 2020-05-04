<?php

// ADMIN/FILES.PHP - various functions for handling course files



// This function provides a list of course files with some buttons
// Meant to be called from admin.php
function assignment_files($course) {
	$files = $course->getFiles();
	
	if (count($files)==0) print "<p>No files are defined</p>\n";
	else print "<ul>\n";
	
	foreach($files as $file) {
		?>
		<form action="assignment/files.php" method="POST">
		<input type="hidden" name="files_action" value="change">
		<?php
		print $course->htmlForm();
		?>
		<input type="hidden" name="file" value="<?=$file?>">
		<li><?=$file?> <input type="submit" name="action" value="View"><input type="submit" name="action" value="Delete"></li>
		</form>
		<?php
	}
	if (count($files)!=0) print "</ul>\n";
	
	?>
	<form action="assignment/files.php" method="POST"  enctype="multipart/form-data">
	<input type="hidden" name="files_action" value="change">
	<?php
	print $course->htmlForm();
	?>
	<p>Add a file: 
	<input type="file" name="add"> <input type="submit" name="action" value="Add"></p>
	</form>
	<?php
}


// This function performs actions clicked by button
// Meant to be invoked directly
function assignment_files_change() {
	global $conf_admin_users, $conf_data_path, $conf_base_path, $login, $_REQUEST;
	
	require_once("../../lib/config.php"); // Webide config
	require_once("../../lib/webidelib.php"); // Webide library
	require_once("../login.php"); // Login
	require_once("../admin/lib.php"); // Admin library
	require_once("../classes/Course.php");

	// Verify session and permissions, set headers
	admin_set_headers();
	if (!admin_session()) {
		niceerror("Your session expired. Please log out then log in.");
		exit(0);
	}

	try {
		$course = Course::fromRequest();
	} catch(Exception $e) {
		niceerror("Unknown course.");
		exit(0);
	}
	if (!$course->isAdmin($login)) {
		niceerror("Permission denied.");
		exit(0);
	}
	
	$course_link = "../admin.php?" . $course->urlPart();
	$asgn_edit_link = "edit.php?action=edit&amp;" . $course->urlPart();
	
	if (isset($_REQUEST['assignment'])) {
		$asgn_id = intval($_REQUEST['assignment']);
		
		$asgn = $course->getAssignments()->findById($asgn_id);
		if ($asgn === false) {
			niceerror("Assignment not found");
			print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
			return;
		}
		$files_path = $asgn->filesPath();
	} else {
		$files_path = $course->getPath() . "/files";
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
		$download_url = "ws.php?action=getFile&amp;" . $course->urlPart() . "&amp;task_direct=" . $asgn->id . "&amp;file=$file_name";
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
