<?php

function admin_permissions($login, $year) {
	global $conf_sysadmins, $conf_data_path;
	$perms = array();
	if (!in_array($login, $conf_sysadmins)) {
		$perms_path = $conf_data_path . "/permissions.json";
		if (file_exists($perms_path)) {
			$all_perms = json_decode(file_get_contents($perms_path), true);
			$perms = $all_perms[$login];
		}
		if ($conf_zamger) {
			// Sysadmins can see all courses, other just those they are teachers for
			require_once("zamger/courses.php");
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
	return $perms;
}

?>