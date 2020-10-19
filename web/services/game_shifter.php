<?php
require_once("./../../lib/config.php");
require_once("./../classes/Course.php");
require_once("./../classes/GameNode.php");
require_once("./helpers/common.php");

$url = "/usr/local/webide/data/log";

if (!($_SERVER['REMOTE_ADDR'] === '127.0.0.1')) {
	jsonResponse(false, 403, array("message"=>"Nisi localhost, jarane!"));
}

function replaceKeys(array $pairs, $code)
{
	foreach ($pairs as $key => $value) {
		$code = str_replace($key, $value, $code);
	}
	return $code;
}

$json = file_get_contents('php://input');
$input = json_decode($json, true);
if (!$input) {
	jsonResponse(false, 400, array("message" => "No body"));
}

validateRequired(["username", "assignment_id", "oldTask_id", "newTask_id", "redo"], $input);

$username = $input["username"];
$assignmentId = intval($input["assignment_id"]);
$oldTaskId = intval($input["oldTask_id"]);
$newTaskId = intval($input["newTask_id"]);
$redo = boolval($input["redo"]);

// Well...
$course = null;
try {
	$course = Course::find(1, true);
} catch (Exception $exception) {
	jsonResponse(false, 500, array("message" => $exception->getMessage()));
}
if ($newTaskId < 0 && $oldTaskId > 0) {
	$oldTaskNode = GameNode::findTaskById($oldTaskId, $course);
	
	if ($oldTaskNode === null) {
		jsonResponse(false, 500, array("message" => "Task does not exist"));
	}
	
	$pathArray = explode('/', $oldTaskNode->parent->path);
	$oldTaskPath = $oldTaskNode->getAbsolutePath();
	
	$action = "from-student-to-history";
	$courseString = $course->toString();
	$assignment = end($pathArray);
	$assignmentName = $oldTaskNode->parent->name;
	
	$pathArray = explode('/', $oldTaskNode->path);
	$task = end($pathArray);
	
	if ($task === false || $assignment === false) {
		$taskString = "Task found: " . ($task ? "True." : "False.");
		$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
		jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
	}
	proc_close(proc_open("sudo $conf_base_path/bin/game-deploy \"$username\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" &", array(), $foo));
	jsonResponse(true, 200, array("message" => "Assignment turned in"));
}
if ($redo === false) {
	if ($oldTaskId < 0) {
		// just add the files from game to student
		$taskNode = GameNode::findTaskById($newTaskId, $course);
		if ($taskNode === null) {
			jsonResponse(false, 500, array("message" => "Task does not exist"));
		}
		$pathArray = explode('/', $taskNode->parent->path);
		$taskPath = $taskNode->getAbsolutePath();
		
		$action = "from-uup-to-student";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $taskNode->parent->name;
		
		$pathArray = explode('/', $taskNode->path);
		$task = end($pathArray);
		
		file_put_contents($url, "Line 99 $task $assignment\n", FILE_APPEND);
		if ($task === false || $assignment === false) {
			$taskString = "Task found: " . ($task ? "True." : "False.");
			$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
			jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
		}
		$filePairs = array();
		$user = null;
		try {
			$user = new User($username);
		} catch (Exception $e) {
			jsonResponse(false, 500, array("message" => $e->getMessage()));
		}
		$replacementPairs = array(
			"===TITLE===" => $taskNode->parent->name . ", " . $taskNode->name ,
			"===COURSE===" => $taskNode->course->name,
			"===STUDENT-FULL-NAME===" => $user->realname,
			"===STUDENT-USERNAME===" => $user->login,
			"===ASSIGNMENT===" => $taskNode->parent->name,
			"===TASK===" => $taskNode->name
		);
		$sedovi = "$conf_base_path/data/sedovi";
		file_put_contents($sedovi, "");
		foreach ($replacementPairs as $key => $value) {
			file_put_contents("$conf_base_path/data/sedovi", "sed -i 's/$key/$value/g'\n", FILE_APPEND);
		}

		$cmd = "sudo $conf_base_path/bin/game-deploy \"$username\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" &";
		proc_close(proc_open($cmd, array(), $foo));

		jsonResponse(true, 200, array("message" => "Deployed from game to student"));
	} else {
		// save student files to history and add files from game to student
		$oldTaskNode = GameNode::findTaskById($oldTaskId, $course);
		
		$taskNode = GameNode::findTaskById($newTaskId, $course);
		if ($taskNode === null || $oldTaskNode === null) {
			jsonResponse(false, 500, array("message" => "Task does not exist"));
		}
		
		$pathArray = explode('/', $oldTaskNode->parent->path);
		$oldTaskPath = $oldTaskNode->getAbsolutePath();
		
		$action = "from-student-to-history";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $oldTaskNode->parent->name;
		
		$pathArray = explode('/', $oldTaskNode->path);
		$task = end($pathArray);
		
		if ($task === false || $assignment === false) {
			$taskString = "Task found: " . ($task ? "True." : "False.");
			$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
			jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
		}
		$cmd = "sudo $conf_base_path/bin/game-deploy \"$username\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" ";
//		proc_close(proc_open($cmd, array(), $foo));
		$commandOne = $cmd;
		$pathArray = explode('/', $taskNode->parent->path);
		$taskPath = $taskNode->getAbsolutePath();
		
		$action = "from-uup-to-student";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $taskNode->parent->name;
		
		$pathArray = explode('/', $taskNode->path);
		$task = end($pathArray);
		
		if ($task === false || $assignment === false) {
			$taskString = "Task found: " . ($task ? "True." : "False.");
			$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
			jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
		}
		$filePairs = array();
		$user = null;
		try {
			$user = new User($username);
		} catch (Exception $e) {
			jsonResponse(false, 500, array("message" => $e->getMessage()));
		}
		$replacementPairs = array(
			"===TITLE===" => $taskNode->parent->name . ", " . $taskNode->name ,
			"===COURSE===" => $taskNode->course->name,
			"===STUDENT-FULL-NAME===" => $user->realname,
			"===STUDENT-USERNAME===" => $user->login,
			"===ASSIGNMENT===" => $taskNode->parent->name,
			"===TASK===" => $taskNode->name
		);
		$sedovi = "$conf_base_path/data/sedovi";
		file_put_contents($sedovi, "");
		foreach ($replacementPairs as $key => $value) {
			file_put_contents("$conf_base_path/data/sedovi", "sed -i 's/$key/$value/g'\n", FILE_APPEND);
		}
		$cmd = "$commandOne && sudo $conf_base_path/bin/game-deploy \"$username\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" &";
		proc_close(proc_open($cmd, array(), $foo));
		jsonResponse(true, 200, array("message" => "Deployed from student to history and from game to student"));
	}
} else {
	if ($oldTaskId < 0) {
		// just add the files from history to student
		$taskNode = GameNode::findTaskById($newTaskId, $course);
		if ($taskNode === null) {
			jsonResponse(false, 500, array("message" => "Task does not exist"));
		}
		$pathArray = explode('/', $taskNode->parent->path);
		$taskPath = $taskNode->getAbsolutePath();
		
		$action = "from-history-to-student";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $taskNode->parent->name;
		
		$pathArray = explode('/', $taskNode->path);
		$task = end($pathArray);
		
		if ($task === false || $assignment === false) {
			$taskString = "Task found: " . ($task ? "True." : "False.");
			$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
			jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
		}
		$cmd = "sudo $conf_base_path/bin/game-deploy \"$username\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" &";
		proc_close(proc_open($cmd, array(), $foo));
		jsonResponse(true, 200, array("message" => "Deployed from history to student"));
	} else {
		// save student files to history and add files from history to student
		$oldTaskNode = GameNode::findTaskById($oldTaskId, $course);
		
		$taskNode = GameNode::findTaskById($newTaskId, $course);
		if ($taskNode === null || $oldTaskNode === null) {
			jsonResponse(false, 500, array("message" => "Task does not exist"));
		}
		
		$pathArray = explode('/', $oldTaskNode->parent->path);
		$oldTaskPath = $oldTaskNode->getAbsolutePath();
		
		$action = "from-student-to-history";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $oldTaskNode->parent->name;
		
		$pathArray = explode('/', $oldTaskNode->path);
		$task = end($pathArray);
		
		if ($task === false || $assignment === false) {
			$taskString = "Task found: " . ($task ? "True." : "False.");
			$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
			jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
		}
		$cmd = "sudo $conf_base_path/bin/game-deploy \"$username\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" ";
//		proc_close(proc_open($cmd, array(), $foo));
		$commandOne = $cmd;
		$pathArray = explode('/', $taskNode->parent->path);
		$oldTaskPath = $oldTaskNode->getAbsolutePath();
		
		$action = "from-history-to-student";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $taskNode->parent->name;
		
		$pathArray = explode('/', $taskNode->path);
		$task = end($pathArray);
		
		if ($task === false || $assignment === false) {
			$taskString = "Task found: " . ($task ? "True." : "False.");
			$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
			jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
		}
		$cmd = "$commandOne && sudo $conf_base_path/bin/game-deploy \"$username\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" &";
		proc_close(proc_open($cmd, array(), $foo));
		jsonResponse(true, 200, array("message" => "Deployed from student to history and from history to student"));
	}
}

jsonResponse(false, 500, array("message" => "Not yet implemented"));
