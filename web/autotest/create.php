<?php


session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/courses.php");


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
$asgn_id = intval($_REQUEST['assignment']);
$task = intval($_REQUEST['task']);

if ($external) {
	$course_path = $conf_data_path . "/X$course" . "_$year";
	$backlink = "../admin.php?course=$course&amp;year=$year&amp;X";
} else {
	$course_path = $conf_data_path . "/$course" . "_$year";
	$backlink = "../admin.php?course=$course&amp;year=$year";
}
if (!file_exists($course_path)) mkdir($course_path);


// Find assignment
$asgn_file_path = $course_path . "/assignments";
$assignments = array();
if (file_exists($asgn_file_path))
	$assignments = json_decode(file_get_contents($asgn_file_path), true);
else {
	niceerror("Assignment not found");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
	return;
}

$asgn = false;
foreach($assignments as $a) {
	if ($a['id'] == $asgn_id) $asgn = $a;
}
if ($asgn == false) {
	niceerror("Assignment not found");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
	return;
}


// Find course 
$courses = admin_courses();
$course_data = false;
foreach ($courses as $c) {
	if ($c['id'] == $course && $external==1 && $c['type'] == 'external')
		$course_data = $c;
	if ($c['id'] == $course && $external==0 && $c['type'] != 'external')
		$course_data = $c;
}

if ($course_data === false) {
	niceerror("Course not found");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
	return;
}
if (!array_key_exists("language", $course_data)) {
	niceerror("Required data not found");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
	return;
}

// FIXME hack
$year_name = (2004 + $conf_current_year) . "/" . (2005 + $conf_current_year);


// Creating datastructure for .autotest file 
$autotest = array();
$autotest['id'] = intval(file_get_contents($conf_data_path . "/autotest_last_id.txt")) + 1;
file_put_contents($conf_data_path . "/autotest_last_id.txt", $autotest['id']);
$autotest['name'] = $course_data['name'] . " ($year_name), " . $asgn['name'] . ", zadatak $task";
$autotest['language'] = $course_data['language'];
$autotest['required_compiler'] = $autotest['preferred_compiler'] = $course_data['compiler'];
$autotest['compiler_features'] = $course_data['compiler_features'];
$autotest['compiler_options'] = $course_data['compiler_options'];
$autotest['compiler_options_debug'] = $course_data['compiler_options_debug'];
$autotest['compile'] = $autotest['test'] = $autotest['debug'] = $autotest['profile'] = "true";
$autotest['run'] = "false";
$autotest['test_specifications'] = array();

// Path for file
$files_path = $course_path . "/assignment_files";
if (!file_exists($files_path)) mkdir($files_path);
$files_path .= "/" . $asgn['path'];
if (!file_exists($files_path)) mkdir($files_path);
$files_path .= "/Z$task";
if (!file_exists($files_path)) mkdir($files_path);

$destination_path = $files_path . "/.autotest";
if (file_exists($destination_path)) {
	niceerror("Autotest file already exists");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
	return;
}

file_put_contents($destination_path, json_encode($autotest, JSON_PRETTY_PRINT));

// Update assignments
$asgn['task_files'][$task][] = ".autotest";
foreach($assignments as &$a)
	if ($a['id'] == $asgn_id) $a=$asgn;
file_put_contents($asgn_file_path, json_encode($assignments, JSON_PRETTY_PRINT));

nicemessage("Autotest file created!");
print "<p><a href=\"$backlink\">Go back</a></p>\n";



?>
