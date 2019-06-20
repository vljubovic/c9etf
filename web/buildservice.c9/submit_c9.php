<?php

// SUBMIT_C9.php - shortcut function to ZIP some files and submit to buildservice

function error($code, $msg) {
	$result = array();
	$result['success'] = "false";
	$result['code'] = $code;
	$result['message'] = $msg;
	print json_encode($result);
	exit(0);
}


if (!file_exists("config.php")) {
	error("ERR004", "Buildservice not configured");
}
require_once("config.php");
require_once("lib.php");
require_once("../../lib/config.php");

session_start();


$program = $task = false;


if (isset($_REQUEST['sstudent'])) $ss = $_REQUEST['sstudent']; else $ss=$_SESSION['login'];
if (empty($ss)) 
	error("ERR010", "Not logged in");
$program_path = $_REQUEST['filename'];
$program_path = str_replace("../", "", $program_path);
$orig_path = $program_path;

$output_path = "$program_path/.at_result";
$task_path = "$program_path/.autotest";

$task = `sudo $conf_base_path/bin/wsaccess $ss read $task_path`;
if (substr($task,0,6) === "ERROR:")
	error("ERR002", "Task file doesn't exist ".$task_path);
	
// Create a ZIP file
$zipfile = "/tmp/submit_$ss"."_".rand(1000,9999).".zip";
`sudo $conf_base_path/bin/wsaccess $ss zip $program_path $zipfile`;



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
$result['url'] = $conf_push_url;
print json_encode($result);

unlink($zipfile);


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
		error("", "HTTP request failed for $url (POST)");
	}
	$http_result = stream_get_contents($fp);
	fclose($fp);

	if ($http_result===FALSE) {
		error("", "HTTP request failed for $url?$query (POST)");
	}
	$http_code = explode(" ", $http_response_header[0]);
	$http_code = $http_code[1];
	if ( !in_array($http_code, $allowed_http_codes) ) {
		error("", "HTTP request returned code $http_code for $url?$query (POST)");
	}
		
	$result = json_decode($http_result, true); // Retrieve json as associative array
	if ($result===NULL) {
		error("", "Failed to decode result as JSON\n$http_result");
		// Why does this happen!?
		if ($conf_verbosity>0) { print_r($http_result); print_r($parameters); }
		return FALSE;
	} 
	
	if (!array_key_exists("success", $result)) {
		error("", "JSON file upload failed: unknown reason");
	} 
	else if ($result["success"] !== "true") {
		error("", "JSON file upload failed: ". $result['code']. " : ". $result['message']);
	}
	return $result["data"];
}


?>
