<?php

function jsonResponse(bool $success, int $code, array $fields)
{
	header('Content-type:application/json;charset=utf-8');
	$response = array("success" => $success, "code" => $code);
	$response = array_merge($response, $fields);
	print json_encode($response);
	exit();
}

function error($code, $message)
{
	header('Content-type:application/json;charset=utf-8');
	print json_encode(array('success' => false, 'code' => $code, 'message' => $message));
	exit();
}

function message_and_data($message, $data)
{
	header('Content-type:application/json;charset=utf-8');
	print json_encode(array('success' => true, 'message' => $message, 'data' => $data),JSON_UNESCAPED_UNICODE);
	exit();
}

function message($message)
{
	header('Content-type:application/json;charset=utf-8');
	print json_encode(array('success' => true, 'message' => $message));
	exit();
}

function isSubPath($path1, $path2)
{
	$one = explode('/', $path1);
	$two = explode('/', $path2);
	$result = true;
	if (count($one) > count($two)) {
		for ($i = 0; $i < count($two); $i++) {
			if ($one[$i] !== $two[$i]) {
				$result = false;
			}
		}
	} else {
		for ($i = 0; $i < count($one); $i++) {
			if ($one[$i] !== $two[$i]) {
				$result = false;
			}
		}
	}
	return $result;
}


/**
 * @return array
 */
function verifySession(): array
{
	$login = '';
	// Verify session and permissions, set headers
	$logged_in = false;
	$session_id = '';
	if (isset($_SESSION['login'])) {
		$login = $_SESSION['login'];
		$session_id = $_SESSION['server_session'];
		if (preg_match("/[a-zA-Z0-9]/", $login)) $logged_in = true;
	}
	return array($login, $logged_in, $session_id);
}

/**
 * @return Course
 */
function extractCourseFromRequest(): Course
{
	$external = false;
	if (isset($_REQUEST["X"])) {
		$external = true;
	}
	global $conf_current_year;
	$year = $conf_current_year;
	if (isset($_REQUEST["year"])) {
		$year = intval($_REQUEST["year"]);
	}
	
	try {
		$course = Course::find($_REQUEST["course_id"], $external);
		$course->year = $year;
	} catch (Exception $e) {
		error("500", $e->getMessage());
	}
	return $course;
}

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