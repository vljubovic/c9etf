<?php


// GAME_SHIFTER.PHP - localhost service that copies UUP Game related files to/from user workspace

require_once("./../../lib/config.php");
require_once("./../classes/Course.php");
require_once("./../classes/GameNode.php");
require_once("./helpers/common.php");


// This service is not publicly available
if (!($_SERVER['REMOTE_ADDR'] === '127.0.0.1')) {
	jsonResponse(false, 403, array("message"=>"Access denied!"));
}

// Log function
function log_this($text) {
	global $username, $conf_base_path;
	file_put_contents($conf_base_path . "/log/game_shifter.log", "$username - [". date("d.m.Y H:i:s")."] - $text\n", FILE_APPEND);
}

// Quick replace function
function replaceKeys(array $pairs, $code)
{
	foreach ($pairs as $key => $value) {
		$code = str_replace($key, $value, $code);
	}
	return $code;
}

// Run wsaccess command for user and log error
function wsaccess(User $user, $cmd) {
	global $conf_base_path;
	$username = escapeshellarg($user->login);
	$output = exec("sudo $conf_base_path/bin/wsaccess $username $cmd");
	if (strstr($output, "ERROR"))
		log_this("wsaccess: $output");
	return $output;
}


// Copy task from student workspace to task history and delete from workspace
function from_student_to_history(User $user, Course $course, $oldTaskId) {
	global $conf_base_path;
	
	$oldTaskNode = GameNode::findTaskById($oldTaskId, $course);
	if ($oldTaskNode === null) {
		jsonResponse(false, 500, array("message" => "Task $oldTaskId does not exist"));
		log_this("Task does not exist");
		return;
	}
	
	$taskString = basename($oldTaskNode->path);
	$assignmentString = basename($oldTaskNode->parent->path);
	$username = $user->login;
	$courseString = $course->toString();
	
	$history = "$conf_base_path/data/$courseString/task_history";
	$src = "UUP_GAME/$assignmentString";
    $dest = "$history/$username/$assignmentString/$taskString";
	
	$files = wsaccess($user, "list \"$src\"");
	if (strstr($files, "ERROR")) {
		jsonResponse(false, 500, array("message" => "Source directory $src does not exist"));
		return;
	}
	
	foreach (explode("\n", $files) as $file) {
		$lastChr = substr( $file, strlen($file) - 1 );
		if ($lastChr == "/" || $lastChr == "=" || $lastChr == ">" || $lastChr == "@" || $lastChr == "|") continue;
		if ($lastChr == "*") $file = substr($file, 0, strlen($file) - 1);
		
		wsaccess($user, "undeploy \"$src/$file\" \"$dest\"");
	}
	wsaccess($user, "delete \"$src\"");
	log_this("from_student_to_history - $oldTaskId");
}


// Generate new game files for student and copy them to student workspace
function from_uup_to_student(User $user, Course $course, $newTaskId) {
	global $conf_base_path;
	
	$taskNode = GameNode::findTaskById($newTaskId, $course);
	if ($taskNode === null) {
		jsonResponse(false, 500, array("message" => "Task $newTaskId does not exist"));
		log_this("Task does not exist");
	}
	
	$taskString = basename($taskNode->path);
	$assignmentString = basename($taskNode->parent->path);
	$username = $user->login;
	$courseString = $course->toString();
	
	$game_files = "$conf_base_path/data/$courseString/game_files/$assignmentString/$taskString";
	$dest = "UUP_GAME/$assignmentString";
	
	$replacementPairs = array(
		"===TITLE===" => $taskNode->parent->name . ", " . $taskNode->name,
		"===COURSE===" => $taskNode->course->name,
		"===STUDENT-FULL-NAME===" => $user->realname,
		"===STUDENT-USERNAME===" => $user->login,
		"===ASSIGNMENT===" => $taskNode->parent->name,
		"===TASK===" => $taskNode->name
	);
	
	wsaccess($user, "delete \"$dest\"");
	wsaccess($user, "mkdir \"$dest\"");
	
	foreach(scandir($game_files) as $file) {
		if ($file == "." || $file == "..") continue;
		$fileContent = replaceKeys($replacementPairs, file_get_contents("$game_files/$file"));
		
		// We will use wsaccess "write" so no temporary files are created, as that can always cause race conditions
		// However, "write" requires that we redirect stdin
		$descriptorspec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("file", "/tmp/error-output.txt", "a")
		);
		$process = proc_open("sudo $conf_base_path/bin/wsaccess $username write \"$dest/$file\"", $descriptorspec, $pipes);
		if (is_resource($process)) {
			fwrite($pipes[0], $fileContent);
			fclose($pipes[0]);
			$rez = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			proc_close($process);
		}
		if (strstr($rez, "ERROR"))
			log_this("wsaccess: $rez");
		if ($file == ".autotest2")
			wsaccess($user, "own \"$dest/$file\"");
	}
	log_this("from_uup_to_student - $newTaskId");
}


// Revert files from history into student workspace
function from_history_to_student(User $user, Course $course, $newTaskId) {
	global $conf_base_path;
	
	$taskNode = GameNode::findTaskById($newTaskId, $course);
	if ($taskNode === null) {
		jsonResponse(false, 500, array("message" => "Task does not exist"));
		log_this("Task does not exist");
	}
	
	$taskString = basename($taskNode->path);
	$assignmentString = basename($taskNode->parent->path);
	$username = $user->login;
	$courseString = $course->toString();
	
	$history = "$conf_base_path/data/$courseString/task_history";
	$src = "$history/$username/$assignmentString/$taskString";
	$dest = "UUP_GAME/$assignmentString";
	
	wsaccess($user, "delete \"$dest\"");
	wsaccess($user, "mkdir \"$dest\"");
	
	foreach(scandir($src) as $file) {
		if ($file == "." || $file == "..") continue;
		wsaccess($user, "deploy \"$src/$file\" \"$dest/$file\"");
		if ($file == ".autotest2")
			wsaccess($user, "own \"$dest/$file\"");
	}
	log_this("from_history_to_student - $newTaskId");
}



// MAIN SERVICE CODE

// Input is provided as JSON
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

log_this("assignment $assignmentId oldTaskId $oldTaskId newTaskId $newTaskId redo $redo");


// Construct User object (will fail if user is unknown)
$user = null;
try {
	$user = new User($username);
} catch (Exception $e) {
	jsonResponse(false, 500, array("message" => $e->getMessage()));
	log_this("500 - User exception - " . $e->getMessage() );
}


// Construct course object (hardcoded course=1 - FIXME)
$course = null;
try {
	$course = Course::find(1, true);
} catch (Exception $exception) {
	jsonResponse(false, 500, array("message" => $exception->getMessage()));
}


// Student submitted the final task in lesson, send the task to history and clear everything
if ($newTaskId < 0 && $oldTaskId > 0) {
	from_student_to_history($user, $course, $oldTaskId);
	jsonResponse(true, 200, array("message" => "Assignment turned in"));
}


if ($redo === false) {
	if ($oldTaskId < 0) {
		// Student just started a lesson, copy task files from game_files to student workspace
		from_uup_to_student($user, $course, $newTaskId);
		jsonResponse(true, 200, array("message" => "Deployed from game to student"));
		
	} else {
		// Student submitted a task, save student files to history and add files from game to student
		from_student_to_history($user, $course, $oldTaskId);
		from_uup_to_student($user, $course, $newTaskId);
		jsonResponse(true, 200, array("message" => "Deployed from student to history and from game to student"));
	}
} else {
	if ($oldTaskId < 0) {
		// Second chance, but no old task specified
		// just add the files from history to student
		from_history_to_student($user, $course, $newTaskId);
		jsonResponse(true, 200, array("message" => "Deployed from history to student"));
		
	} else {
		// Second chance powerup is used
		// Save student files to history and add files from history to student
		from_student_to_history($user, $course, $oldTaskId);
		from_history_to_student($user, $course, $newTaskId);
		jsonResponse(true, 200, array("message" => "Deployed from student to history and from history to student"));
	}
}

jsonResponse(false, 500, array("message" => "Not yet implemented"));
