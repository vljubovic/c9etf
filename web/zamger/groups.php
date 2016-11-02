<?php

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");

function zamger_group_list($course, $year) {
	global $session_id, $conf_json_base_url;
	$parameters[session_name()] = $session_id;
	$parameters["sta"] = "ws/labgrupa";
	$parameters["predmet"] = $course;
	$parameters["ag"] = $year;
	$result = json_request_retry($conf_json_base_url, $parameters, "GET");
	if ($result == -1 || $result['success'] != "true") return false;
	return $result['data'];
}

function zamger_group_members($group) {
	global $session_id, $conf_json_base_url;
	$parameters[session_name()] = $session_id;
	$parameters["sta"] = "ws/labgrupa";
	$parameters["id"] = $group;
	$result = json_request_retry($conf_json_base_url, $parameters, "GET");
	if ($result == -1 || $result['success'] != "true") return false;
	$members = array();
	foreach ($result['data']['studenti'] as $zs) {
		$username = $zs['login'];
		$fullname = $zs['ime'] . " " . $zs['prezime'];
		$members[$username] = $fullname;
	}
	return $members;
}


function zamger_without_group($course, $year) {
	// To get users without group, first we take all users enlisted on course, 
	// then remove those that have a group from list
	$groups = zamger_group_list($course, $year);
	$id_ss = 0;
	foreach ($groups['data'] as $id => $name) {
		if ($name == "(Svi studenti)") $id_ss  = $id;
	}
	
	$result = zamger_group_members($id_ss);
	$all_students = $result['data']['studenti'];
	
	foreach ($grupe['data'] as $id => $name) {
		if ($name == "(Svi studenti)") continue;
		$group = zamger_group_members($id);
		foreach ($group['data']['studenti'] as $login => $fullname) {
			if (array_key_exists($login, $all_students))
				unset($all_students[$login]);
		}
	}
	return $all_students;
}

?>