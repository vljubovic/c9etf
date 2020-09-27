<?php

function validateRequired($keys, $array)
{
	foreach ($keys as $key) {
		if (!array_key_exists($key, $array)) {
			error(400, "Required field $key not present in body!");
		}
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
}

/**
 * @param Course $course
 */
function getAdminAssignments($course)
{
	$node = GameNode::constructGameForCourse($course);
	jsonResponse(true, 200, array("data" => json_decode($node->getJson(), true)));
}

function getStudentAssignments(): void
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
		jsonResponse(false, $response->code, $data);
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
			jsonResponse(false, $response->code, $data);
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
			jsonResponse(false, $response->code, $data);
		}
		$payload = json_decode($payload, true);
		$assignment->editAssignment($name, $payload['active'], $payload['points'], $payload['challenge_pts']);
		
		updateGameJson($course, $assignment);
		jsonResponse(false, 200, $data);
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
			jsonResponse(false, $response->code, $data);
		}
		$node = GameNode::findAssignmentById($assignmentId, $course);
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
			jsonResponse(false, $response->code, $data);
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
		jsonResponse(false, $response->code, $data);
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
		jsonResponse(false, 404, array("message"=>"File not found"));
	}
	$content = $file->getFileContent();
	if ($content == false) {
		$content = "";
	}
	if ($file !== null) {
		jsonResponse(true, 200, array('data'=>array('content' => $content)));
	} else {
		jsonResponse(false, 404, array("message"=>"File not found"));
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
		updateGameJson($course,$task);
		jsonResponse(true, 200, array("message"=>"File deleted"));
	} catch (Exception $exception) {
		jsonResponse(false,500,array("message" => $exception->getMessage()));
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
		jsonResponse(false, $response->code, $data);
	}
	jsonResponse(true, 200, array("data" => $data));
}
