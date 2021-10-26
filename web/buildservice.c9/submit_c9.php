<?php

// SUBMIT_C9.php - shortcut function to ZIP some files and submit to buildservice

function error($code, $msg) {
	print json_encode(get_error($code, $msg));
	exit(0);
}

function get_error($code, $msg) {
	$result = array();
	$result['success'] = "false";
	$result['code'] = $code;
	$result['message'] = $msg;
	return $result;
}


if (!file_exists("config.php"))
	error("ERR004", "Buildservice not configured");
require_once("config.php");
require_once("lib.php");
$webide_path = "/usr/local/webide";


session_start();


$program = $task = $ss = false;

if (isset($_REQUEST['sstudent']))
	$ss = $_REQUEST['sstudent'];
else if (isset($_SESSION['login']))
	$ss = $_SESSION['login'];

if (empty($ss))
	error("ERR010", "Not logged in");


$program_path = $_REQUEST['filename'];
$program_path = str_replace("../", "", $program_path);
$orig_path = $program_path;

$output_path = "$program_path/.at_result";

// Create a ZIP file
$zipfile = "/tmp/submit_$ss"."_".rand(1000,9999).".zip";
`sudo $webide_path/bin/wsaccess $ss zip $program_path $zipfile`;

$task_path = "$program_path/.autotest";
$task = `sudo $webide_path/bin/wsaccess $ss read $task_path`;

// Autotester v2.0
$task2_path = "$program_path/.autotest2";
$task2 = `sudo $webide_path/bin/wsaccess $ss read $task2_path`;
if (substr($task2,0,6) !== "ERROR:" && isset($_REQUEST['autotester'])) {
	// Buildservice v2 aka. autotester
	$conf_push_url = "https://c9.etf.unsa.ba/autotester/server/push.php";
	
	$atstatus_path = "$program_path/.at_status";
	$atstatus_json = `sudo $webide_path/bin/wsaccess $ss read $atstatus_path`;
	
	$submit_task = true;
	if (substr($atstatus_json,0,6) !== "ERROR:") {
		$atstatus = json_decode($atstatus_json, true);
		if (md5($task2) == $atstatus['task_md5']) {
			$submit_task = false;
			$programId = $atstatus['program'];
		}
	}
	
	$result = array();
	$result['success'] = "true";
	$result['message'] = "Instance created";
	$result['version'] = "autotester";
	
	
	if (!$submit_task) {
		$result['atstatus'] = $atstatus;
		$result['instance'] = $programId;
		
		$spf = json_file_upload($conf_push_url,
			array( "action" => "setProgramFile", "id" => $programId),
			array( "program" => $zipfile )
		);
		if (array_key_exists('success', $spf) && ($spf['success'] === "false" || $spf['success'] === false)) {
			if (array_key_exists('message', $spf) && strstr($spf['message'], "ERR005")) {
				$submit_task = true;
			} else if ($spf['success'] != true) {
				$result = $spf;
			}
		}
	}
	
	if ($submit_task) {
		$taskDesc = json_decode($task2, true);
		if (!array_key_exists('languages', $taskDesc))
			error("ERR003", "Invalid task description");
		$language = $taskDesc['languages'][0]; // FIXME?
		
		$taskId = json_query("setTask", array("task" => $task2), "POST" );
		$program = array( "name" => "$ss/$orig_path", "language" => $language, "task" => $taskId );
		$programId = json_query("setProgram", array("program" => json_encode($program)), "POST");
		
		$atstatus = array( "task" => $taskId, "task_md5" => md5($task2), "program" => $programId );
		$atstatus_json = json_encode($atstatus, JSON_PRETTY_PRINT);
		$tmpfile = tempnam("/tmp", "atstatus");
		file_put_contents($tmpfile, $atstatus_json);
		$output = `sudo $webide_path/bin/wsaccess $ss deploy $atstatus_path $tmpfile`;
		unlink($tmpfile);
		
		$result['instance'] = $programId;
		$result['atstatus'] = $atstatus;
		
		$spf = json_file_upload($conf_push_url,
			array( "action" => "setProgramFile", "id" => $programId),
			array( "program" => $zipfile )
		);
		if (array_key_exists('success', $spf) && ($spf['success'] === "false" || $spf['success'] === false)) {
			$result['success'] = "false";
			$result['message'] = $spf['message'];
			$result['spf'] = $spf;
		}
		
	}
	
	session_write_close();
	
	print json_encode($result);
}

else if (substr($task,0,6) !== "ERROR:") {
	
	$taskDesc = json_decode($task, true);
	if (!array_key_exists('language', $taskDesc) || trim($taskDesc['language']) == "")
		error("ERR003", "Invalid task description");
	if (!array_key_exists('required_compiler', $taskDesc) || trim($taskDesc['required_compiler']) == "")
		error("ERR003", "Invalid task description");
	if (!array_key_exists('preferred_compiler', $taskDesc) || trim($taskDesc['preferred_compiler']) == "")
		error("ERR003", "Invalid task description");
	
	// JSON stuff
	
	if ($conf_json_login_required)
		$session_id = json_login();
	
	$taskid = json_query("setTask", array("task" => $task), "POST" );
	if ($taskid == 0)
		error("ERR002", "Task file doesn't exist ".$task_path);
	
	$progid = json_file_upload($conf_push_url,
		array( "action" => "addProgram", "task" => $taskid['id'], "name" => "$ss/$orig_path"),
		array( "program" => $zipfile )
	);
	
	session_write_close();
	
	$result = array();
	$result['success'] = "true";
	$result['message'] = "Instance created";
	$result['instance'] = $progid['id'];
	$result['version'] = "buildservice";
	print json_encode($result);
}
else
	error("ERR002", "Task file doesn't exist ".$task_path);

`sudo rm $zipfile`;


function json_file_upload($url, $parameters, $files)
{
	global $conf_verbosity;
	
	$disableSslCheck = array(
		'ssl' => array(
			"verify_peer"=>false,
			"verify_peer_name"=>false,
		),
	);
	
	$allowed_http_codes = array ("200"); // Only 200 is allowed
	
	// Reimplement http_build_query, per http://stackoverflow.com/questions/13785433/php-upload-file-to-another-server-without-curl
	//$query = http_build_query($parameters);
	$data = "";
	$boundary = "---------------------".substr(md5(rand(0,32000)), 0, 10);
	
	//Collect Postdata
	foreach($parameters as $key => $val)
	{
		$data .= "--$boundary\n";
		$data .= "Content-Disposition: form-data; name=\"".$key."\"\n\n".$val."\n";
	}
	
	$data .= "--$boundary\n";
	
	//Collect Filedata
	foreach($files as $key => $filepath)
	{
		$filename = basename($filepath);
		$fileContents = file_get_contents($filepath);
		
		$data .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"$filename\"\n";
		$data .= "Content-Type: application/zip\n";
		$data .= "Content-Transfer-Encoding: binary\n\n";
		$data .= $fileContents."\n";
		$data .= "--$boundary\n";
	}
	
	
	// Only POST is possible
	$params = array('http' => array(
		'method' => 'POST',
		'header' => "Content-Type: multipart/form-data; boundary=" . $boundary,
		'content' => $data,
	),
		'ssl' => array(
			"verify_peer"=>false,
			"verify_peer_name"=>false,
		),
	);
	
	$ctx = stream_context_create($params);
	$fp = fopen($url, 'rb', false, $ctx);
	if (!$fp) {
		return get_error("", "HTTP request failed for $url (POST)");
	}
	$http_result = stream_get_contents($fp);
	fclose($fp);
	
	if ($http_result===FALSE) {
		return get_error("", "HTTP request failed for $url (POST)");
	}
	$http_code = explode(" ", $http_response_header[0]);
	$http_code = $http_code[1];
	if ( !in_array($http_code, $allowed_http_codes) ) {
		return get_error("", "HTTP request returned code $http_code for $url (POST)");
	}
	
	$result = json_decode($http_result, true); // Retrieve json as associative array
	if ($result===NULL) {
		return get_error("", "Failed to decode result as JSON\n$http_result");
	}
	
	if (!array_key_exists("success", $result)) {
		return get_error("", "JSON file upload failed: unknown reason");
	}
	else if ($result["success"] !== "true" && $result["success"] !== true) {
		return get_error("", "JSON file upload failed: ". $result['code']. " : ". $result['message']);
	}
	return $result;
}

