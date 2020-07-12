<?php

session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");
require_once("../classes/Course.php");

eval(file_get_contents("../../users"));
require_once("../phpwebide/phpwebide.php");

// Verify session and permissions, set headers

$logged_in = false;
if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/", $login)) $logged_in = true;
}

if (!$logged_in) {
	$result = array('success' => "false", "message" => "You're not logged in");
	print json_encode($result);
	return 0;
}

session_write_close();

// If user is not admin, they can only access their own files
if (in_array($login, $conf_admin_users) && isset($_GET['user']))
	$username = escape_filename($_GET['user']);
else
	$username = $login;

if (isset($_REQUEST['course_id']) || isset($_REQUEST['course'])) {
	if (isset($_REQUEST['course_id'])) {
		$course_id = intval($_REQUEST['course_id']);
	} else {
		$course_id = intval($_REQUEST['course']);
	}
	$external = true;
	if (isset($_REQUEST['external'])) {
		$external = $_REQUEST['external'] === 'true';
	}
	try {
		$course = Course::find($course_id, $external);
		if (!$course->isAdmin($login)) {
			throw new Exception("course " . $course->toString() . " access denied");
		}
		$response_data = array();
		if (isset($_REQUEST['groups']) || isset($_REQUEST['group_id'])) {
			try {
				$groups = $course->getGroups();
				foreach ($groups as $group) {
					unset($group->course);
				}
				if (isset($_REQUEST['groups'])) {
					$response_data['groups'] = $groups;
				} else {
					// get specific group
					$response_group = null;
					$group_id = $_REQUEST['group_id'];
					foreach ($groups as $group) {
						if ($group->id == $group_id) {
							$response_group = $group;
							break;
						}
					}
					if (isset($_REQUEST['members'])) {
						// get students for specific group
						$response_data['members'] = $response_group->getMembers();
						
					}
					$response_data['group'] = $response_group;
				}
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
		}
		$response_data['course'] = $course;
	} catch (Exception $e) {
		$error = $e->getMessage();
	}
} else {
	// Get all courses
	if (isset($_REQUEST['year'])) {
		$year = intval($_REQUEST['year']);
	} else {
		$year = $conf_current_year;
	}
	try {
		$response_data = Course::forAdmin($login, $year);
	} catch (Exception $e) {
		$error = $e->getMessage();
	}
	function course_cmp($a, $b)
	{
		return $a->name > $b->name;
	}
	
	usort($response_data, "course_cmp");
}

ini_set('default_charset', 'UTF-8');
header('Content-Type: application/json; charset=UTF-8');
$result = array();

if ($error == "") {
	$result['success'] = true;
	$result['message'] = 'OK';
	$result['data'] = $response_data;
} else {
	$result['success'] = false;
	$result['message'] = $error;
}
print json_encode($result);
