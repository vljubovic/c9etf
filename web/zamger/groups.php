<?php

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");

function zamger_group_list($course, $year) {
	global $session_id, $conf_json_base_apiv5;
	$parameters["SESSION_ID"] = $session_id;
	$parameters["year"] = $year;
	$parameters["includeVirtual"] = "true";
	$url = $conf_json_base_apiv5 . "group/course/$course";
	$result = json_request_retry($url, $parameters, "GET");
	if ($result == -1 || (array_key_exists('success', $result) && $result['success'] != "true"))
		return false;
	return $result['results'];
}

function zamger_all_students($course, $year) {
	global $session_id, $conf_json_base_apiv5;
	$parameters["SESSION_ID"] = $session_id;
	$parameters["year"] = $year;
	$parameters["names"] = true;
	$parameters["resolve[]"] = "Person";
	$url = $conf_json_base_apiv5 . "group/course/$course/allStudents";
	$result = json_request_retry($url, $parameters, "GET");
	if ($result == -1 || (array_key_exists('success', $result) && $result['success'] != "true"))
		return false;
	return $result;
}
function zamger_group_members($group, $year) {
	global $session_id, $conf_json_base_apiv5;
	$parameters["SESSION_ID"] = $session_id;
	$parameters["year"] = $year;
	$parameters["names"] = true;
	$url = $conf_json_base_apiv5 . "group/$group";
	$result = json_request_retry($url, $parameters, "GET");
	if ($result == -1 || (array_key_exists('success', $result) && $result['success'] != "true"))
		return false;
	return $result;
}


function zamger_without_group($course, $year) {
	// To get users without group, first we take all users enlisted on course, 
	// then remove those that have a group from list
	$groups = zamger_group_list($course, $year);
	$id_ss = 0;
	foreach ($groups['data'] as $id => $name) {
		if ($name == "(Svi studenti)") $id_ss  = $id;
	}
	
	$result = zamger_group_members($id_ss, $year);
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
