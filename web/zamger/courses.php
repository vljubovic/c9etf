<?php

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");

function teacher_courses($year=0) {
	global $session_id, $conf_json_base_url;
	$parameters[session_name()] = $session_id;
	$parameters["sta"] = "ws/nastavnik_predmet";
	if ($year>0) $parameters["ag"] = $year;
	$result = json_request_retry($conf_json_base_url, $parameters, "GET");
	return $result['data']['predmeti'];
}

function student_courses($year=0) {
	global $session_id, $conf_json_base_url;
	$parameters[session_name()] = $session_id;
	$parameters["sta"] = "ws/student_predmet";
	$parameters["ag"] = $year;
	$result = json_request_retry($conf_json_base_url, $parameters, "GET");
	return $result['data']['predmeti'];
}

?>