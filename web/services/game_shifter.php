<?php
require_once("./../classes/Course.php");
require_once("./../classes/GameNode.php");
require_once("./helpers/common.php");

if (!$_SERVER['REMOTE_ADDR'] === '127.0.0.1') {
	jsonResponse(false, 403, array("message"=>"Permission denied"));
}

function replaceKeys(array $pairs, $code)
{
	foreach ($pairs as $key => $value) {
		$code = str_replace($key, $value, $code);
	}
	return $code;
}

$input = json_decode(file_get_contents('php://input'), true);
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

$serverKey = file_get_contents("/usr/local/webide/data/__gameServerKey");

if ($serverKey === false || $serverKey === "-" || $serverKey === "" || $serverKey !== $key) {
	jsonResponse(false, 400, array("message" => "Server key is not valid"));
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
	proc_close(proc_open("sudo $conf_base_path/bin/game-deploy $username $action $courseString $assignment $assignmentName $task &", array(), $foo));
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
		//user="$1"
		//action="$2"
		//course="$3"
		//assignment="$4"
		//assignmentName="$5"
		//task="$6"
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
			"===COURSE===" => $taskNode->course->name,
			"===STUDENT-FULL-NAME===" => $user->realname,
			"===STUDENT-USERNAME===" => $user->login,
			"===ASSIGNMENT===" => $taskNode->parent->name,
			"===TASK===" => $taskNode->name
		);
		foreach ($taskNode->children as $fileNode) {
			if (!$fileNode->data['binary']) {
				$filePairs[$fileNode->path] = $fileNode->getFileContent();
				$replacedContent = replaceKeys($replacementPairs, $fileNode->getFileContent());
				file_put_contents($fileNode->getAbsolutePath(), $replacedContent);
			}
		}
		proc_close(proc_open("sudo $conf_base_path/bin/game-deploy $username $action $courseString $assignment $assignmentName $task &", array(), $foo));
		foreach ($taskNode->children as $fileNode) {
			if (!$fileNode->data['binary']) {
				$replacedContent = $filePairs[$fileNode->path];
				file_put_contents($fileNode->getAbsolutePath(), $replacedContent);
			}
		}
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
		proc_close(proc_open("sudo $conf_base_path/bin/game-deploy $username $action $courseString $assignment $assignmentName $task &", array(), $foo));
		$pathArray = explode('/', $taskNode->parent->path);
		$taskPath = $taskNode->getAbsolutePath();
		
		$action = "from-uup-to-student";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $taskNode->parent->name;
		
		$pathArray = explode('/', $taskNode->path);
		$task = end($pathArray);
		//user="$1"
		//action="$2"
		//course="$3"
		//assignment="$4"
		//assignmentName="$5"
		//task="$6"
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
			"===COURSE===" => $taskNode->course->name,
			"===STUDENT-FULL-NAME===" => $user->realname,
			"===STUDENT-USERNAME===" => $user->login,
			"===ASSIGNMENT===" => $taskNode->parent->name,
			"===TASK===" => $taskNode->name
		);
		foreach ($taskNode->children as $fileNode) {
			if (!$fileNode->data['binary']) {
				$filePairs[$fileNode->path] = $fileNode->getFileContent();
				$replacedContent = replaceKeys($replacementPairs, $fileNode->getFileContent());
				file_put_contents($fileNode->getAbsolutePath(), $replacedContent);
			}
		}
		proc_close(proc_open("sudo $conf_base_path/bin/game-deploy $username $action $courseString $assignment $assignmentName $task &", array(), $foo));
		foreach ($taskNode->children as $fileNode) {
			if (!$fileNode->data['binary']) {
				$replacedContent = $filePairs[$fileNode->path];
				file_put_contents($fileNode->getAbsolutePath(), $replacedContent);
			}
		}
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
		proc_close(proc_open("sudo $conf_base_path/bin/game-deploy $username $action $courseString $assignment $assignmentName $task &", array(), $foo));
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
		proc_close(proc_open("sudo $conf_base_path/bin/game-deploy $username $action $courseString $assignment $assignmentName $task &", array(), $foo));
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
		proc_close(proc_open("sudo $conf_base_path/bin/game-deploy $username $action $courseString $assignment $assignmentName $task &", array(), $foo));
		jsonResponse(true, 200, array("message" => "Deployed from student to history and from history to student"));
	}
}

jsonResponse(false, 500, array("message" => "Not yet implemented"));
