<?php

function admin_courses() {
	global $conf_data_path;
	$courses_path = $conf_data_path . "/courses.json";
	$courses = array();
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	return $courses;
}

?>