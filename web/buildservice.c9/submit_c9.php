<?php

// SUBMIT.PHP - web service alpha

function error($code, $msg) {
	$result = array();
	$result['success'] = "false";
	$result['code'] = $code;
	$result['message'] = $msg;
	print json_encode($result);
	exit(0);
}

// Create directory structure that will be used for everything related to this program
function create_instance_from_path($program_path)
{
	global $conf_max_instances;

	// Find unused instance ID
	do {
		$instance = rand(0, $conf_max_instances);
		$path = instance_path($instance);
	} while (file_exists($path));

	directoryCleanup($path);

	`cp -R $program_path/* $path`;
	return $instance;
}


if (!file_exists("config.php")) {
	error("ERR004", "Buildservice not configured");
}
require_once("config.php");
require_once("lib.php");
require_once("buildservice.php");

/*print "Testiranje nije moguće trenutno.\n";
return;*/

session_start();


$program = $task = false;


if (isset($_REQUEST['sstudent'])) $ss = $_REQUEST['sstudent']; else $ss=$_SESSION['login'];
$program_path = $_REQUEST['filename'];
$program_path = str_replace("../", "", $program_path);

$program_path = "/home/c9/workspace/$ss/$program_path";
$output_path = "$program_path/.at_result";

$task = json_decode(file_get_contents($program_path . "/.autotest"), true);
if (!$task)
	error("ERR002", "Task data not sent ".$program_path);

$compiler = find_best_compiler($task['language'], $task['required_compiler'], $task['preferred_compiler'], $task['compiler_features']);
if ($compiler === false)
	error("ERR003", "No suitable compiler found for language ".$task['language']);


$instance = create_instance_from_path($program_path);
$filelist = find_sources($task, $instance);
if ($filelist == array()) 
	error("ERR005", "No sources found");


$json_path = $conf_basepath . "/task_" . $task['id'] . ".js";
file_put_contents($json_path, json_encode($task));

$status_path = instance_path($instance) . "/buildservice_status.json"; // We use .json extension so it wouldn't be confused with JS sources
$status = array("status" => "Background process not started yet");
file_put_contents($status_path, json_encode($status));

$result = array();
$result['success'] = "true";
$result['message'] = "Instance created";
$result['instance'] = $instance;
print json_encode($result);

proc_close(proc_open("sudo /usr/local/webide/bin/webidectl bt-background ".$task['id']." $instance $output_path &", array(), $foo));

?>