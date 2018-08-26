<?php

session_start();
require_once("../../lib/config.php");
require_once("../../lib/webidelib.php");
require_once("../../lib/Reconstruct.php");
 

// Verify session and permissions, set headers

$logged_in = false;
if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
}

if (!$logged_in) {
	$result = array ('success' => "false", "message" => "You're not logged in");
	print json_encode($result);
	return 0;
}


// If user is not admin, they can only access their own files
if (in_array($login, $conf_admin_users) && isset($_GET['user']))
	$username = escape_filename($_GET['user']);
else
	$username = $login;

$filename = $_GET['filename'];

$r = new Reconstruct($username, true);
$stats = $r->ReadStats();

if (!array_key_exists($filename, $stats)) {
	$result = array ('success' => "false", "message" => "File '$filename' not found in stats for '$username'");
	print json_encode($result);
	return 0;
}

$start = intval($_GET['start']);
if ($start < 1) $start = 1;

end($stats[$filename]['events']);
$end = key($stats[$filename]['events']) + 1;

$limit = intval($_GET['limit']);
if ($limit < 1)
	$limit = 1000;

$data = array();
$error = false;
$actual_events = 0;

for ($i=$start; $i<=$end; $i++) {
	if (!array_key_exists($i-1, $stats[$filename]['events'])) continue;
	if (++$actual_events > $limit) break;
	if (!$r->TryReconstruct($filename, "+$i")) {
		$error = $i;
		break;
	}
	if (array_key_exists($i-1, $stats[$filename]['events']) && array_key_exists('time', $stats[$filename]['events'][$i-1]))
		$time = $stats[$filename]['events'][$i-1]['time'];
	$data[] = array(
		"version" => $i, 
		"timestamp" => $time, 
		"datetime" => date("d. m. Y. H:i:s", $time), 
		"firstLine" => $r->GetFirstLineAffected(),
		"lastLine" => $r->GetLastLineAffected(),
		"contents" => join("", $r->GetFile())
	);
	//$end = $r->GetTotalEvents();
}

if ($error)
	$result = array ('success' => "false", "message" => "Reconstruct failed on step $error", "data" => $data);
else
	$result = array('success' => "true", "data" => $data, "hasMore" => ($actual_events > $limit));
print json_encode($result, JSON_PRETTY_PRINT);

?>
