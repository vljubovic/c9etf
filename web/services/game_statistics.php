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


function getHistoryEntries($path)
{
	$entries = scandir("$path");
	if ($entries === false) {
		return [];
	}
	$result = array();
	foreach ($entries as $name) {
		if ($name === "." || $name === ".." || $name === "") {
			continue;
		}
		$isDirectory = is_dir($path . '/' . $name);
		$element = array(
			"name" => $name,
			"isDirectory" => $isDirectory
		);
		if ($isDirectory) {
			$element["children"] = getHistoryEntries($path . "/" . $name);
		}
		$result[] = $element;
	}
	return $result;
}

function getEntries($path, $username)
{
	global $conf_base_path;
	$entries = `sudo $conf_base_path/bin/wsaccess $username list "$path"`;
	if (strstr($entries, "ERROR")) {
		jsonResponse(false, 500, array("message" => "Something is wrong with the command"));
	}
	$entries = explode("\n", $entries);
	$result = array();
	foreach ($entries as $entry) {
		$name = str_replace("/", "", $entry);
		if ($name === "." || $name === ".." || $name === "") {
			continue;
		}
		$isDirectory = strpos($entry, "/");
		if (!($isDirectory === false)) {
			$isDirectory = true;
		}
		$element = array(
			"name" => $name,
			"isDirectory" => $isDirectory
		);
		if ($isDirectory) {
			$element["children"] = getEntries($path . "/" . $name, $username);
		}
		$result[] = $element;
	}
	return $result;
}


global $conf_game_url, $conf_game_spectators, $conf_base_path;

$courses = array();
try {
	$or = Course::find("1", true);
	$courses[] = $or;
} catch (Exception $e) {
}
try {
	$uup = Course::find("2234", true);
	$courses[] = $uup;
} catch (Exception $e) {
}
$hasPermission = false;
foreach ($courses as $course) {
	try {
		if ($course->isAdmin($login)) {
			$hasPermission = true;
		}
	} catch (Exception $e) {
	}
}
if (!in_array($login, $conf_game_spectators) && !$hasPermission) {
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
			$groups[] = array(
				"id" => $courseGroup->id,
				"name" => $courseGroup->name,
				"course"=> $course->name
			);
		}
	}
	jsonResponse(true, 200, array("data" => $groups));
} else if ($action === "groupMembers") {
	if (!isset($_REQUEST['groupId'])) {
		jsonResponse(false, 400, array("message" => "groupId is not set"));
	}
	$groupId = $_REQUEST['groupId'];
	$courses = [];
	try {
		$or = Course::find("1", true);
		$courses[] = $or;
	} catch (Exception $e) {
	}
	
	try {
		$uup = Course::find("2234", true);
		$courses[] = $uup;
	} catch (Exception $e) {
	}
	$group = null;
	foreach ($courses as $course) {
		$courseGroups = $course->getGroups();
		$found = false;
		foreach ($courseGroups as $courseGroup) {
			if ($courseGroup->id == $groupId) {
				try {
					$groupMembers = $courseGroup->getMembers();
					$members = [];
					foreach ($groupMembers as $key => $value) {
						try {
							$user = new User($key);
							$members[] = array(
								"login" => $user->login,
								"realName" => $user->realname,
								"online" => $user->online,
							);
						} catch (Exception $e) {
						}
					}
					$group = array(
						"id" => $courseGroup->id,
						"name" => $courseGroup->name,
						"members" => $members
					);
					$found = true;
					break;
				} catch (Exception $e) {
				}
			}
		}
		if ($found) {
			break;
		}
	}
	if ($group === null) {
		jsonResponse(false, 404, array("message" => "Group with id $groupId not found"));
	} else {
		jsonResponse(true, 200, array("data" => $group));
	}
} else if ($action === "studentWorkTree") {
	if (!isset($_REQUEST['student'])) {
		jsonResponse(false, 400, array("message" => "No student specified"));
	}
	$username = $_REQUEST['student'];
	try {
		$user = new User($username);
	} catch (Exception $e) {
		jsonResponse(false, 400, array("message" => "User not found"));
	}
	$path = "UUP_GAME";
	$entries = getEntries($path, $username);
	jsonResponse(true, 200, array("data" => $entries));
} else if ($action === "studentWorkRead") {
	if (!isset($_REQUEST['student'])) {
		jsonResponse(false, 400, array("message" => "No student specified"));
	}
	if (!isset($_REQUEST['path'])) {
		jsonResponse(false, 400, array("message" => "No path specified"));
	}
	$path = $_REQUEST['path'];
	$path = str_replace("/../", "/", $path);
	$username = $_REQUEST['student'];
	$content = `sudo $conf_base_path/bin/wsaccess $username read "UUP_GAME$path"`;
	if (strstr($content, "ERROR")) {
		jsonResponse(false, 500, array("message" => "Something is wrong with the command"));
	}
	jsonResponse(true, 200, array("data" => array("content" => $content)));
} else if ($action === "studentHistoryTree") {
	if (!isset($_REQUEST['student'])) {
		jsonResponse(false, 400, array("message" => "No student specified"));
	}
	$or = null;
	try {
		$or = Course::find("1", true);
	} catch (Exception $e) {
		jsonResponse(false, 500, array("message" => $e->getMessage()));
	}
	$course = $or->toString();
	$username = $_REQUEST['student'];
	$base_path = "$conf_base_path/data/$course/task_history/$username";
	$entries = getHistoryEntries($base_path);
	jsonResponse(true, 200, array("data" => $entries));
} else if ($action === "studentHistoryRead") {
	if (!isset($_REQUEST['student'])) {
		jsonResponse(false, 400, array("message" => "No student specified"));
	}
	if (!isset($_REQUEST['path'])) {
		jsonResponse(false, 400, array("message" => "No path specified"));
	}
	$username = $_REQUEST['student'];
	$path = $_REQUEST['path'];
	$or = null;
	try {
		$or = Course::find("1", true);
	} catch (Exception $e) {
		jsonResponse(false, 500, array("message" => $e->getMessage()));
	}
	$course = $or->toString();
	$content = file_get_contents("$conf_base_path/data/$course/task_history/$username$path");
	if ($content === false) {
		jsonResponse(false, 500, array("message" => "Content not available or not readable"));
	}
	jsonResponse(true, 200, array("data" => array("content" => $content)));
} else if ($action==="getAssignments") {
	if ($or !== null) {
		$root = GameNode::constructGameForCourse($or);
		$json = $root->getJson();
		$object = json_decode($json, true);
		if ($json === false) {
			jsonResponse(false,500, array("message" => "Assignments not available"));
		}
		jsonResponse(true,200, array("data"=>$object));
	}
} else {
	jsonResponse(false, 400, array("message" => "Unknown action"));
}


//
///statistics/leaderboard GET
///statistics/general GET
///statistics/students/:student GET