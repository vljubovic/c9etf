<?php


require_once("../../lib/config.php"); // Webide config
require_once("../../lib/webidelib.php"); // Webide library
require_once("../login.php"); // Login
require_once("../admin/lib.php"); // Admin library
require_once("../admin/courses.php"); // Courses
require_once("../assignment/lib.php"); // Assignment library




// Verify session and permissions, set headers
admin_session();
admin_set_headers();


// Set vars
assignment_global_init();
admin_check_permissions($course, $year, $external);


// Find assignment
$asgn_id = intval($_REQUEST['assignment']);
$task = intval($_REQUEST['task']);

$asgn = assignment_get($asgn_id);
if ($asgn == false) {
	niceerror("Assignment not found");
	print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
	return;
}
$asgn_edit_link = "../assignment/" . assignment_edit_link($asgn_id);



// Find course 
$course_data = admin_courses_get($course, $external);

if ($course_data === false) {
	niceerror("Course not found");
	print "<p><a href=\"admin.php\">Go back</a></p>\n";
	return;
}
if (!array_key_exists("language", $course_data)) {
	niceerror("Required data not found");
	print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
	print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";
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
$files_path = assignment_get_task_path($asgn, $task);

$destination_path = $files_path . "/.autotest";
if (file_exists($destination_path)) {
	niceerror("Autotest file already exists");
	print "<p><a href=\"$backlink\">Go back</a></p>\n";
	return;
}

file_put_contents($destination_path, json_encode($autotest, JSON_PRETTY_PRINT));

// Update assignments
$asgn['task_files'][$task][] = ".autotest";
assignment_update($asgn);

nicemessage("Autotest file created!");
print "<p><a href=\"$course_link\">Go back to course</a></p>\n";
print "<p><a href=\"$asgn_edit_link\">Edit assignment</a></p>\n";



?>
