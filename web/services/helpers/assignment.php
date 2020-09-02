<?php

require_once "common.php";

/**
 * @param Course $course
 * @param string $login
 */
function check_admin_access($course, $login): void
{
	try {
		if (!$course->isAdmin($login)) {
			error("403", "You are not an admin on this course");
		}
	} catch (Exception $e) {
		error("500", $e->getMessage());
	}
}

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
