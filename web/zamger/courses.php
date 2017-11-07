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

function zamger_permissions() {
		if ($conf_zamger) {
			$tcs = teacher_courses($year);
			if ($tcs == false) {
				admin_log("failed to retrieve courses");
				niceerror("Neuspješno preuzimanje spiska predmeta");
				print "<p>Konekcija na Zamger ne funkcioniše. Probajte logout pa login...</p>\n";
				print "</body></html>\n";
				return 0;
			}
			if (empty($tcs)) {
				niceerror("Izgleda da nemate status nastavnika niti na jednom predmetu.");
				return 0;
			}
			foreach($tcs as $tc) {
				$c9id = "X" . $tc['id'] . "_" . $year;
				if (!in_array($c9id, $perms)) $perms[] = $c9id;
			}
		}
}

?>
