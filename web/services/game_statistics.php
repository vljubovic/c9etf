<?php
session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../login.php");
require_once("../admin/lib.php");
require_once("../classes/Course.php");
require_once("../classes/Cache.php");
require_once("../classes/User.php");
require_once("./helpers/assignment.php");
require_once("./helpers/common.php");
require_once("./../classes/FSNode.php");
require_once("./../classes/GameNode.php");
require_once("./../classes/RequestBuilder.php");
require_once("./helpers/uup_game.php");

eval(file_get_contents("../../users"));

list($login, $logged_in, $session_id) = verifySession();

if (!$logged_in) {
	jsonResponse(
		false,
		400,
		array("message" => "You're not logged in")
	);
}

session_write_close();
global $conf_game_url, $conf_game_spectators;

if (!in_array($login, $conf_game_spectators)) {
	jsonResponse(false, 403, array("message" => "Permission denied"));
}

if (!isset($_REQUEST['action'])) {
	jsonResponse(false, 400, array("message" => "No action provided"));
}

$action = $_REQUEST['action'];
if ($action === "leaderboard") {
	$response = (new RequestBuilder())
		->setUrl("$conf_game_url/uup-game/statistics/leaderboard")
		->send();
	
	$data = json_decode($response->data, true);
	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	
	foreach ($data as &$student) {
		try {
			$element = new User($student['student']);
			$student['realName'] = $element->realname;
		} catch (Exception $e) {
		}
	}
	jsonResponse(true, 200, array("message" => "Student leaderboard", "data" => $data));
} else if ($action === "general") {
	$response = (new RequestBuilder())
		->setUrl("$conf_game_url/uup-game/statistics/general")
		->send();
	
	$data = json_decode($response->data, true);
	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	$bestStudents = $data["bestStudents"];
	foreach ($bestStudents as &$student) {
		try {
			$element = new User($student['username']);
			$student['realName'] = $element->realname;
		} catch (Exception $e) {
		}
	}
	$data["bestStudents"] = $bestStudents;
	jsonResponse(true, 200, array("message" => "General statistics", "data" => $data));
} else if ($action === "studentInfo") {
	if (!isset($_REQUEST['student'])) {
		jsonResponse(false, 400, array("message" => "No student specified"));
	}
	$username = $_REQUEST['student'];
	try {
		$profile = new User($username);
		$response = (new RequestBuilder())
			->setUrl("$conf_game_url/uup-game/statistics/students/$username")
			->send();
		
		$data = json_decode($response->data, true);
		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}
		
		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}
		$totalPoints = 0;
		foreach ($data as $assignmentId => $tasks) {
			foreach ($tasks as $task) {
				$totalPoints += $task["points"];
			}
		}
		jsonResponse(true, 200, array("message" => "Student statistics", "student" => array("username" => $profile->login, "realName" => $profile->realname, "totalPoints" => $totalPoints), "data" => $data));
	} catch (Exception $e) {
		jsonResponse(false, 404, array("message" => $e->getMessage()));
	}
} else if ($action === "groups") {
	$courses = [];
	try {
		$uup = Course::find("1", true);
		$courses[] = $uup;
	} catch (Exception $e) {
	}
	
	try {
		$or = Course::find("2234", true);
		$courses[] = $or;
	} catch (Exception $e) {
	}
	$groups = [];
	foreach ($courses as $course) {
		$courseGroups = $course->getGroups();
		foreach ($courseGroups as $courseGroup) {
			$members = [];
			try {
				$groupMembers = $courseGroup->getMembers();
				foreach ($groupMembers as $groupMember) {
					$members[] = array(
						"login" => $groupMember->login,
						"realName" => $groupMember->realname,
						"online" => $groupMember->online
					);
				}
				$groups[] = array(
					"id" => $courseGroup->id,
					"name" => $courseGroup->name,
					"members" => $members
				);
			} catch (Exception $e) {
			}
		}
	}
	jsonResponse(true, 200, array("data" => $groups));
} else {
	jsonResponse(false, 400, array("message" => "Unknown action"));
}


//
///statistics/leaderboard GET
///statistics/general GET
///statistics/students/:student GET