<?php


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
} catch (Exception $e) {
	niceerror($e->getMessage());
	exit(0);
}

if (!$course->isAdmin($login)) {
	niceerror("You don't have permission to access this page.");
	exit(0);
}

$course_link = "<p><a href=\"admin.php?" . $course->urlPart() . "\">Go back to course</a></p>\n";


// Find assignment
$root = $course->getAssignments();
$root->getItems();
print "<pre>";
print_r($root);
exit(0);
$task = $root->findById( intval($_REQUEST['task']) );
if ($task === false) {
	niceerror("Assignment not found");
	print $course_link;
	return;
}
$asgn_edit_link = "<p><a href=\"../assignment/edit.php?action=edit&amp;" . $course->urlPart() . "\">Edit assignment</a></p>\n";



if (!array_key_exists("language", $course->data)) {
	niceerror("Required data not found");
	print $course_link;
	print $asgn_edit_link;
	return;
}

foreach(Cache::getFile("years.json") as $year)
	if ($year['id'] == $conf_current_year)
		$year_name = $year['name'];
$course_data = $course->data;

// Creating datastructure for .autotest file 
$autotest = array();
$autotest['id'] = intval(file_get_contents($conf_data_path . "/autotest_last_id.txt")) + 1;
file_put_contents($conf_data_path . "/autotest_last_id.txt", $autotest['id']);
$autotest['name'] = $course->name . " ($year_name), " . $task->parent->name . ", " . $task->name;
$autotest['language'] = $course_data['language'];
$autotest['required_compiler'] = $autotest['preferred_compiler'] = $course_data['compiler'];
$autotest['compiler_features'] = $course_data['compiler_features'];
$autotest['compiler_options'] = $course_data['compiler_options'];
$autotest['compiler_options_debug'] = $course_data['compiler_options_debug'];
$autotest['compile'] = $autotest['test'] = $autotest['debug'] = $autotest['profile'] = "true";
$autotest['run'] = "false";
$autotest['test_specifications'] = array();

// Path for file
$files_path = $task->filesPath();

$destination_path = $files_path . "/.autotest";
if (file_exists($destination_path)) {
	niceerror("Autotest file already exists");
	print $course_link;
	print $asgn_edit_link;
	return;
}

file_put_contents($destination_path, json_encode($autotest, JSON_PRETTY_PRINT));

// Update assignments
$task->files[] = ".autotest";
$task->update();

nicemessage("Autotest file created!");
print $course_link;
print $asgn_edit_link;



?>
