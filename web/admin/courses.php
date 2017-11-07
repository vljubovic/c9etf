<?php

// ADMIN/COURSES.PHP - library of functions for working with a list of courses


// Get all courses
function admin_courses() {
	global $conf_data_path;
	
	$courses_path = $conf_data_path . "/courses.json";
	if (file_exists($courses_path))
		return json_decode(file_get_contents($courses_path), true);
	return array();
}

// Get specific course
function admin_courses_get($course, $external) {
	$courses = admin_courses();
	foreach ($courses as $c) {
		if ($c['id'] == $course && $external==1 && $c['type'] == 'external')
			return $c;
		if ($c['id'] == $course && $external==0 && $c['type'] != 'external')
			return $c;
	}
	return false;
}

?>
