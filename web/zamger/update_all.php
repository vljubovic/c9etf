<?php

function zamger_update_all($login) {
	global $conf_data_path, $conf_current_year;

	$courses_path = $conf_data_path . "/courses.json";
	if (file_exists($courses_path))
		$courses = json_decode(file_get_contents($courses_path), true);
	else
		$courses = array();
	
	require_once(__DIR__."/courses.php");
	require_once(__DIR__."/groups.php");
	$teacher_courses = teacher_courses($conf_current_year);
	
	foreach($teacher_courses as $tc) {
		$found = false;
		foreach($courses as $c) {
			if ($c['id'] == $tc['id'] && $c['type'] == "external") {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$new_c = array();
			$new_c['id'] = $tc['id'];
			$new_c['name'] = $tc['naziv'];
			$new_c['abbrev'] = $tc['kratki_naziv'];
			$new_c['type'] = "external";
			$courses[] = $new_c;
		}
		
		// Update groups for course
		$course_id = "X" . $tc['id'] . "_" . $conf_current_year;
		$course_path = $conf_data_path . "/$course_id";
		if (!file_exists($course_path)) mkdir($course_path);
		
		$group_path = $conf_data_path . "/groups";
		if (!file_exists($group_path)) mkdir($group_path);
		
		$groups = zamger_group_list($tc['id'], $conf_current_year);
		$all_student_id = 0;
		foreach($groups as $id => &$name) {
			if ($name == "(Svi studenti)") {
				$name = "(All students)";
				$all_student_id = $id;
			}
		}
		$all_students = zamger_group_members($all_student_id);
		
		foreach($groups as $id => $name) {
			$gr = array();
			$gr['id'] = $id;
			$gr['name'] = $name;
			$gr['members'] = zamger_group_members($id);
			$gr['course'] = $course;
			$gr['year'] = $year;
			$gr['course_type'] = "external";
			foreach ($gr['members'] as $login => $fullname) {
				unset($all_students[$login]);
			}
			$group_file = $group_path . "/$id";
			file_put_contents($group_file, json_encode($gr, JSON_PRETTY_PRINT));
		}
		
		$group_file = $group_path . "/$course_id";
		$gr = array();
		$gr['id'] = "$course_id";
		$gr['name'] = "Members without group";
		$gr['members'] = $all_students;
		$gr['course'] = $course;
		$gr['year'] = $year;
		$gr['course_type'] = "external";
		file_put_contents($group_file, json_encode($gr, JSON_PRETTY_PRINT));
		
		$groups[$course_id] = "Members without group";
		$group_list_file = $course_path . "/groups";
		file_put_contents($group_list_file, json_encode($groups, JSON_PRETTY_PRINT));
	}
	file_put_contents($courses_path, json_encode($courses, JSON_PRETTY_PRINT));
}

?>