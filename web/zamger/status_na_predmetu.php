<?php

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");

function status_na_predmetu($predmet, $ag) {
	global $session_id, $conf_json_base_url;
	$parameters[session_name()] = $session_id;
	$parameters["predmet"] = $predmet;
	$parameters["ag"] = $ag;
	$result = json_request_retry($conf_json_base_url . "ajah/statusNaPredmetu.php", $parameters, "GET");
	return $result;
}

?>