<?php

// This function provides a list of course files with some buttons
// Meant to be called from admin.php
function assignment_files($course, $year, $external) {
	global $conf_data_path;

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
	global $conf_admin_users, $conf_data_path;
	
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
	
	
	// Set core variables
	$course = intval($_REQUEST['course']);
	$year = intval($_REQUEST['year']);
	$external = $_REQUEST['external'];
	
	if ($external) {
		$course_path = $conf_data_path . "/X$course" . "_$year";
		$backlink = "../admin.php?course=$course&amp;year=$year&amp;X";
	} else {
		$course_path = $conf_data_path . "/$course" . "_$year";
		$backlink = "../admin.php?course=$course&amp;year=$year";
	}
	if (!file_exists($course_path)) mkdir($course_path);
	
	if (isset($_REQUEST['assignment'])) {
		$asgn_id = intval($_REQUEST['assignment']);
		$task = intval($_REQUEST['task']);
		
		$asgn_file_path = $course_path . "/assignments";
		$assignments = array();
		if (file_exists($asgn_file_path))
			$assignments = json_decode(file_get_contents($asgn_file_path), true);
			
		$asgn = array();
		foreach ($assignments as $a) 
			if ($a['id'] == $asgn_id) $asgn=$a;
		if (empty($asgn)) {
			niceerror("Assignment not found");
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
		
		if ($task < 1 || $task > $asgn['tasks']) {
			niceerror("Invalid task number");
			print "<p><a href=\"$backlink\">Go back</a></p>\n";
			return;
		}
		
		$files_path = $course_path . "/assignment_files/" . $asgn['path'] . "/Z$task";
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
			print "<p><b style=\"color:red\">File $file_name doesn't exist.</b></p>";
			print "<p><a href=\"$backlink\">Back</a></p>\n";
			exit(0);
		}
	}
	
	if ($_REQUEST['action'] == "View") {
		$download_url = "ws.php?action=getFile&amp;course=$course&amp;year=$year&amp;assignment=$asgn_id&amp;task=$task&amp;file=$file_name";
		if ($external) $download_url .= "&amp;X";
		print "<h1>Viewing file: $file_name - <a href=\"$download_url\">Download</a></h1>\n";
		print "<pre>";
		print htmlentities(file_get_contents($file_path));
		print "</pre>\n";
		print "<p><a href=\"$backlink\">Back</a></p>\n";
	}
	if ($_REQUEST['action'] == "Delete") {
		unlink($file_path);
		print "<p>File deleted</p>";
		print "<p><a href=\"$backlink\">Back</a></p>\n";
		
		// Update assignment task_files
		if (isset($_REQUEST['assignment'])) {
			for ($i=0; $i<count($asgn['task_files'][$task]); $i++) {
				if ($asgn['task_files'][$task][$i] == $file_name) 
					unset($asgn['task_files'][$task][$i]);
			}
			foreach($assignments as &$a)
				if ($a['id'] == $asgn_id) $a=$asgn;
			file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));
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
			print "<p><a href=\"$backlink\">Back</a></p>\n";
			exit(0);
		}
		
		rename($temporary, $destination_path);
		
		// Update assignment task_files
		if (isset($_REQUEST['assignment'])) {
			$asgn['task_files'][$task][] = $destination;
			foreach($assignments as &$a)
				if ($a['id'] == $asgn_id) $a=$asgn;
			file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));
		}

		print "<p>File uploaded.</p>\n";
		print "<p><a href=\"$backlink\">Back</a></p>\n";
	}
	
	?>
</body>
</html>
	<?php
}


if (isset($_REQUEST['files_action']) && $_REQUEST['files_action'] == "change") assignment_files_change();



?>