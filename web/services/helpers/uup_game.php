<?php

function deployFile(Course $course) {
	$url = "/usr/local/webide/data/log";
	global $conf_base_path;
	if (isset($_REQUEST['fileName']) && isset($_REQUEST['taskId'])){
		$filePath = $_REQUEST['fileName'];
		$taskId = $_REQUEST['taskId'];
		$taskNode = GameNode::findTaskById($taskId, $course);
		if ($taskNode === null) {
			jsonResponse(false, 500, array("message" => "Task does not exist"));
		}
		$assignmentId = $taskNode->parent->id;
		$pathArray = explode('/', $taskNode->parent->path);
		$action = "from-uup-to-student-file";
		$courseString = $course->toString();
		$assignment = end($pathArray);
		$assignmentName = $taskNode->parent->name;
		$pathArray = explode('/', $taskNode->path);
		$task = end($pathArray);
		file_put_contents($url, "Line 20 $task $assignment\n", FILE_APPEND);
		if ($task === false || $assignment === false) {
			$taskString = "Task found: " . ($task ? "True." : "False.");
			$assignmentString = "Assignment found: " . ($assignment ? "True." : "False.");
			jsonResponse(false, 500, array("message" => "$taskString $assignmentString"));
		}

		// get the users from uup game server
		global $game_server_url;
		$response = (new RequestBuilder())
			->setUrl("$game_server_url/uup-game/tasks/students/$assignmentId/$taskId")
			->send();
		$data = json_decode($response->data, true);
		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}
		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}
		$users = $data;
		foreach($users as $userWrapper) {
			$filePairs = array();
			$user = null;
			try {
				$user = new User($userWrapper["student"]);
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

			$cmd = "sudo $conf_base_path/bin/game-deploy \"$user->login\" \"$action\" \"$courseString\" \"$assignment\" \"$assignmentName\" \"$task\" &";
			proc_close(proc_open($cmd, array(), $foo));

			jsonResponse(true, 200, array("message" => "Deployed from game to student"));
		}
	} else {
		jsonResponse(false, 400, array("message" => "filePath parameter missing."));
	}
}

function extractOptionals($keys, $data)
{
	$result = array();
	foreach ($keys as $key) {
		if (isset($data[$key])) {
			$result[] = $data[$key];
		} else {
			$result[] = null;
		}
	}
	return $result;
}

/**
 * @param Course $course
 * @param GameNode $node
 */
function updateGameJson(Course $course, GameNode $node): void
{
	file_put_contents($course->getPath() . '/game.json', $node->getJson());
}

/**
 * @param Course $course
 */
function initializeGame($course)
{
	mkdir($course->getPath() . '/game_files');
	touch($course->getPath() . '/game.json');
	file_put_contents($course->getPath() . '/game.json', "{}");
	jsonResponse(true, 200, array("message" => "Game initialized"));
}

/**
 * @param Course $course
 */
function getAdminAssignments($course)
{
	$node = GameNode::constructGameForCourse($course);
	jsonResponse(true, 200, array("data" => json_decode($node->getJson(), true)));
}

/**
 * @param Course $course
 */
function getStudentAssignments($course): void
{
	global $game_server_url;
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/assignments/all")
		->send();
	$data = json_decode($response->data, true);
	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	foreach ($data as &$lesson) {
		$node = GameNode::findAssignmentById($lesson["id"], $course);
		if ($node !== null) {
			$lesson["path"] = $node->path;
		}
	}
	jsonResponse(true, $response->code, array("data" => $data));
}

/**
 * @param Course $course
 * @throws Exception
 */
function createAssignment(Course $course): void
{
	global $game_server_url;
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(["name", "displayName", "active", "points", "challengePoints"], $input);
		$name = $input["name"];
		$name = str_replace("..", "", $name);
		$name = str_replace("/", "", $name);
		$displayName = $input["displayName"];
		if (file_exists($course->getPath() . "/game_files/$name")) {
			jsonResponse(false, 400, array("message" => "Folder already exists!"));
		}
		$payload = array(
			"name" => $displayName,
			"active" => boolval($input["active"]),
			"points" => floatval($input["points"]),
			"challenge_pts" => floatval($input["challengePoints"])
		);
		$payload = json_encode($payload);
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload)
		);
		$response = (new RequestBuilder())
			->setUrl("$game_server_url/uup-game/assignments/create")
			->setHeaders($headers)
			->setMethod("POST")
			->setBody($payload)
			->send();

		$data = json_decode($response->data, true);
		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}

		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}
		$node = GameNode::constructGameForCourse($course);
		$payload = json_decode($payload, true);
		$node->addGameAssignment($name, $displayName, $payload['active'], $payload['points'], $payload['challenge_pts'], $data['id']);

		updateGameJson($course, $node);
		jsonResponse(true, 200, $data);
	}
}

function editAssignment(Course $course): void
{
	global $game_server_url;
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		if (isset($_REQUEST['assignmentId'])) {
			$id = intval($_REQUEST['assignmentId']);
		} else {
			jsonResponse(false, 400, array("message" => "Parameter assignmentId not set"));
		}
		$assignment = GameNode::findAssignmentById($id, $course);
		if ($assignment === null) {
			jsonResponse(false, 404, array("message" => "No such assignment with id " . $id));
		}
		if (array_key_exists("name", $input)) {
			$name = $input["name"];
		} else {
			$name = $assignment->name;
		}
		if (array_key_exists("active", $input)) {
			$active = $input["active"];
		} else {
			$active = $assignment->data["active"];
		}
		if (array_key_exists("points", $input)) {
			$points = $input["points"];
		} else {
			$points = $assignment->data["points"];
		}
		if (array_key_exists("challenge_pts", $input)) {
			$challengePoints = $input["challenge_pts"];
		} else {
			$challengePoints = $assignment->data["challengePoints"];
		}

		$name = str_replace("..", "", $name);
		$name = str_replace("//", "/", $name);

		$payload = array(
			"name" => $name,
			"active" => boolval($active),
			"points" => floatval($points),
			"challenge_pts" => floatval($challengePoints)
		);
		$payload = json_encode($payload);
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload)
		);
		$response = (new RequestBuilder())
			->setUrl("$game_server_url/uup-game/assignments/$id")
			->setHeaders($headers)
			->setMethod("PUT")
			->setBody($payload)
			->send();

		$data = json_decode($response->data, true);
		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}

		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}
		$payload = json_decode($payload, true);
		$assignment->editAssignment($name, $payload['active'], $payload['points'], $payload['challenge_pts']);

		updateGameJson($course, $assignment);
		jsonResponse(true, 200, $data);
	}
}

function createTask(Course $course)
{
	global $game_server_url;
	if (isset($_REQUEST["assignmentId"])) {
		$assignmentId = $_REQUEST["assignmentId"];
	} else {
		jsonResponse(false, 400, array("message" => "assignmentId field not set"));
	}
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(["name", "displayName", "category", "hint"], $input);
		$name = $input["name"];
		$name = str_replace("..", "", $name);
		$name = str_replace("/", "", $name);
		$displayName = $input["displayName"];
		$node = GameNode::findAssignmentById($assignmentId, $course);
		if (file_exists($node->getAbsolutePath() . "/$name")) {
			jsonResponse(false, 400, array("message" => "Folder already exists"));
		}
		$payload = array(
			"task_name" => $displayName,
			"category_id" => intval($input["category"]),
			"hint" => $input["hint"],
			"assignment_id" => intval($assignmentId)
		);
		$payload = json_encode($payload);
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload)
		);
		$response = (new RequestBuilder())
			->setUrl("$game_server_url/uup-game/tasks/create")
			->setHeaders($headers)
			->setMethod("POST")
			->setBody($payload)
			->send();

		$data = json_decode($response->data, true);
		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}

		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}
		try {
			$node->addAssignmentTask($data["id"], $input["name"], $input["displayName"], $input["category"], $input["hint"]);
			updateGameJson($course, $node);
		} catch (Exception $exception) {
			jsonResponse(false, 400, array("message" => $exception->getMessage()));
		}
		jsonResponse(true, 200, $data);
	}
}

function editTask(Course $course)
{
	global $game_server_url;
	if (isset($_REQUEST["taskId"])) {
		$taskId = $_REQUEST["taskId"];
	} else {
		jsonResponse(false, 400, array("message" => "taskId field not set"));
	}
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$task = GameNode::findTaskById($taskId, $course);
		$name = isset($input["name"]) ? $input["name"] : $task->name;
		$category = isset($input["category"]) ? $input["category"] : $task->data['category'];
		$hint = isset($input["hint"]) ? $input["hint"] : $task->data['hint'];

		$payload = array(
			"task_name" => $name,
			"category_id" => intval($category),
			"hint" => $hint,
			"assignment_id" => intval($task->parent->id)
		);
		$payload = json_encode($payload);
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload)
		);
		$response = (new RequestBuilder())
			->setUrl("$game_server_url/uup-game/tasks/update/$taskId")
			->setHeaders($headers)
			->setMethod("PUT")
			->setBody($payload)
			->send();

		$data = json_decode($response->data, true);
		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}

		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}

		try {
			$task->editTask($name, $category, $hint);
			updateGameJson($course, $task);
		} catch (Exception $exception) {
			jsonResponse(false, 400, array("message" => $exception->getMessage()));
		}
		jsonResponse(true, 200, $data);
	}
}

function deleteTask(Course $course)
{
	global $game_server_url;
	if (isset($_REQUEST["taskId"])) {
		$taskId = $_REQUEST["taskId"];
	} else {
		jsonResponse(false, 400, array("message" => "taskId field not set"));
	}
	$task = GameNode::findTaskById($taskId, $course);
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/tasks/$taskId")
		->setMethod("DELETE")
		->send();

	$data = json_decode($response->data, true);
	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}

	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}

	$node = $task->parent;
	$task->deleteTask();
	updateGameJson($course, $node);
	jsonResponse(true, 200, array("data" => $data));
}

function getFileContent(Course $course)
{
	if (isset($_REQUEST["taskId"])) {
		$taskId = $_REQUEST["taskId"];
	} else {
		jsonResponse(false, 400, array("message" => "taskId field not set"));
	}
	if (isset($_REQUEST["name"])) {
		$name = $_REQUEST["name"];
	} else {
		jsonResponse(false, 400, array("message" => "taskId field not set"));
	}
	$task = GameNode::findTaskById($taskId, $course);
	$file = null;
	foreach ($task->children as $child) {
		if ($child->name == $name) {
			$file = $child;
		}
	}
	if ($file === null) {
		jsonResponse(false, 404, array("message" => "File not found"));
	}
	$content = $file->getFileContent();
	if ($content == false) {
		$content = "";
	}
	if ($file !== null) {
		jsonResponse(true, 200, array('data' => array('content' => $content)));
	} else {
		jsonResponse(false, 404, array("message" => "File not found"));
	}
}

function createTaskFile(Course $course)
{
	if (isset($_REQUEST["taskId"])) {
		$taskId = $_REQUEST["taskId"];
	} else {
		jsonResponse(false, 400, array("message" => "taskId field not set"));
	}
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(["name", "content"], $input);
		$binary = false;
		$show = true;
		$name = $input["name"];
		$content = $input["content"];
		if (isset($input["binary"])) {
			$binary = $input["binary"];
		}
		if (isset($input["show"])) {
			$show = $input["show"];
		}
		$task = GameNode::findTaskById($taskId, $course);
		if ($task == null) {
			jsonResponse(false, 404, array("message" => "Task with id " . $taskId . " not found."));
		}
		$task->addFileToTask($name, $content, $binary, $show);
		updateGameJson($course, $task);
		jsonResponse(true, 200, array("message" => "Successfully added a file to task"));
	}
}

function editTaskFile(Course $course)
{
	if (isset($_REQUEST["taskId"])) {
		$taskId = $_REQUEST["taskId"];
	} else {
		jsonResponse(false, 400, array("message" => "taskId field not set"));
	}
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		validateRequired(["name"], $input);
		$name = $input["name"];
		list($binary, $show, $content) = extractOptionals(["binary", "show", "content"], $input);

		$task = GameNode::findTaskById($taskId, $course);
		if ($task == null) {
			jsonResponse(false, 404, array("message" => "Task with id " . $taskId . " not found."));
		}
		foreach ($task->children as &$child) {
			if ($child->name == $name) {
				$child->editFile($content, $binary, $show);
			}
		}
		updateGameJson($course, $task);
		jsonResponse(true, 200, array("message" => "Successfully edited " . $input["name"]));
	}
}

function deleteTaskFile(Course $course)
{
	if (isset($_REQUEST["taskId"])) {
		$taskId = $_REQUEST["taskId"];
	} else {
		jsonResponse(false, 400, array("message" => "taskId field not set"));
	}
	if (isset($_REQUEST["name"])) {
		$name = $_REQUEST["name"];
	} else {
		jsonResponse(false, 400, array("message" => "name field not set"));
	}

	$task = GameNode::findTaskById($taskId, $course);
	if ($task == null) {
		jsonResponse(false, 404, array("message" => "Task with id " . $taskId . " not found."));
	}
	try {
		foreach ($task->children as &$child) {
			if ($child->name == $name) {
				$child->deleteFile();
			}
		}
		updateGameJson($course, $task);
		jsonResponse(true, 200, array("message" => "File deleted"));
	} catch (Exception $exception) {
		jsonResponse(false, 500, array("message" => $exception->getMessage()));
	}
}

function getTaskCategories(): void
{
	global $game_server_url;
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/tasks/categories/all")
		->send();
	$data = json_decode($response->data, true);
	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("data" => $data));
}


/**
 * @param $login
 */
function buyPowerUp($login): void
{
	global $game_server_url;

	$powerUpType = $_REQUEST["type_id"];
	if ($powerUpType === null) {
		jsonResponse(false, 400, array("message" => "Set the type_id field"));
	}

	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/powerups/buy/$login/$powerUpType")
		->setMethod('POST')
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 */
function getTasksForAssignment(): void
{
	global $game_server_url;
	$assignment_id = null;
	if (isset($_REQUEST['assignment_id'])) {
		$assignment_id = $_REQUEST['assignment_id'];
	} else {
		jsonResponse(false, 400, array('message' => "Assignment id is not set in query"));
	}
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/assignments/$assignment_id/tasks")
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 */
function getPowerUpTypes(): void
{
	global $game_server_url;
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/powerups/types")
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 */
function getChallengeConfig(): void
{
	global $game_server_url;
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/challenge/config")
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function getStudentData($login): void
{
	global $game_server_url;
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/$login")
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function startAssignment($login): void
{
	global $game_server_url;
	$assignmentId = $_REQUEST["assignment_id"];
	if ($assignmentId === null) {
		error(400, "Set the assignment_id field");
	}
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/assignments/$assignmentId/$login/start")
		->setMethod('POST')
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function resetRetard($login): void
{
	global $game_server_url;
	$assignmentId = $_REQUEST["assignment_id"];
	if ($assignmentId === null) {
		error(400, "Set the assignment_id field");
	}
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/assignments/reset/$login/$assignmentId")
		->setMethod('GET')
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function setTokens($login): void
{
	global $game_server_url;
	$tokens = intval($_REQUEST["amount"]);
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/powerups/tokens/set/$login/$tokens")
		->setMethod('GET')
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function turnTaskIn($login): void
{
	global $game_server_url;
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$payload = json_encode($input);
		$assignmentId = $_REQUEST['assignment_id'];
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload)
		);
		$response = (new RequestBuilder())
			->setUrl("$game_server_url/uup-game/tasks/turn_in/$login/$assignmentId")
			->setMethod('POST')
			->setHeaders($headers)
			->setBody($payload)
			->send();
		$data = json_decode($response->data, true);
		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}
		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}
		jsonResponse(true, 200, array("data" => $data));
	}
}

/**
 * @param $login
 */
function swapTask($login): void
{
	global $game_server_url;
	$assignmentId = $_REQUEST['assignment_id'];

	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/tasks/swap/$login/$assignmentId")
		->setMethod('POST')
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function hint($login): void
{
	global $game_server_url;
	$assignmentId = $_REQUEST['assignment_id'];

	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/tasks/hint/$login/$assignmentId")
		->setMethod('POST')
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function getAvailableTasks($login): void
{
	global $game_server_url;
	$assignmentId = $_REQUEST['assignment_id'];
	$typeId = $_REQUEST['type_id'];

	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/tasks/turned_in/$login/$assignmentId/$typeId")
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function secondChance($login): void
{
	global $game_server_url;
	$input = json_decode(file_get_contents('php://input'), true);
	if ($input) {
		$assignmentId = $_REQUEST['assignment_id'];
		$payload = json_encode($input);
		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload)
		);
		$response = (new RequestBuilder())
			->setUrl("$game_server_url/uup-game/tasks/second_chance/$login/$assignmentId")
			->setMethod('PUT')
			->setHeaders($headers)
			->setBody($payload)
			->send();
		$data = json_decode($response->data, true);

		if ($response->error) {
			jsonResponse(false, 500, array("message" => "Game Server not responding"));
		}
		if ($response->code >= 400) {
			jsonResponse(false, $response->code, array("data" => $data));
		}
		jsonResponse(true, 200, array("message" => "OK", "data" => $data));
	}
}

/**
 * @param $login
 */
function getUsedHint($login): void
{
	global $game_server_url;
	$assignmentId = $_REQUEST["assignment_id"];
	$taskNumber = $_REQUEST["task_number"];
	if ($assignmentId === null || $taskNumber === null) {
		jsonResponse(false, 400, array("message" => "Set assignment_id and task_number field"));
	}
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/powerups/hints/used/$login/$assignmentId/$taskNumber")
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}

/**
 * @param $login
 */
function getTaskPreviousPoints($login): void
{
	global $game_server_url;
	$assignmentId = $_REQUEST["assignment_id"];
	$taskNumber = $_REQUEST["task_number"];
	if ($assignmentId === null || $taskNumber === null) {
		jsonResponse(false, 400, array("message" => "Set assignment_id and task_number field"));
	}
	$response = (new RequestBuilder())
		->setUrl("$game_server_url/uup-game/tasks/previousTask/points/get/$login/$assignmentId/$taskNumber")
		->send();
	$data = json_decode($response->data, true);

	if ($response->error) {
		jsonResponse(false, 500, array("message" => "Game Server not responding"));
	}
	if ($response->code >= 400) {
		jsonResponse(false, $response->code, array("data" => $data));
	}
	jsonResponse(true, 200, array("message" => "OK", "data" => $data));
}
