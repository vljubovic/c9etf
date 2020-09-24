<?php

require_once "common.php";



function check_filename($filename)
{
	$ref = null;
	$matches = preg_match('/[^\/?*:{};]+/', $filename, $ref);
	$contains_backslash = boolval(strpos($filename, "\\"));
	if ($filename !== "" && $filename !== "." && $filename !== ".." && $matches && $ref !== null && $ref[0] == $filename && !$contains_backslash) {
		return true;
	} else {
		return false;
	}
}

function json($data)
{
	if (defined("JSON_PRETTY_PRINT"))
		print json_encode($data, JSON_PRETTY_PRINT);
	else
		print json_encode($data);
	exit();
}
