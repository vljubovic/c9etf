<?php

require_once(__DIR__."/config.php");
require_once(__DIR__."/jsonlib.php");
$webide_path = "/usr/local/webide";

session_start();

$zadaca = intval($_REQUEST['zadaca']);
$zadatak = intval($_REQUEST['zadatak']);
if (isset($_REQUEST['student'])) $student = intval($_REQUEST['student']); else $student = $_SESSION['userid'];
$filename = $_REQUEST['filename'];
$filename = str_replace("../", "", $filename);

if (isset($_REQUEST['username'])) $username = $_REQUEST['username']; else $username = $_SESSION['login'];

// Saznajem ID studenta sa Zamgera
if ($student==0 || isset($_REQUEST['username'])) {
	$parameters = array( "login" => $username );
	if (isset($_SESSION['server_session']) !== "")
		$parameters[session_name()] = $_SESSION['server_session'];
	$result = json_request_retry("https://zamger.etf.unsa.ba/ajah/podaciKorisnika.php", $parameters);
	if (!array_key_exists("success", $result)) {
		die("JSON query podaciKorisnika failed: unknown reason\n");
	} 
	else if ($result["success"] !== "true") {
		if ($result['code'] == "ERR001") die("GRESKA: Istekla sesija");
		die("GRESKA: ".$result['message']);
	}
	$student = $result['data']['id'];
}


//$filename = "/home/c9/workspace/$username/$filename";

// Podaci o zadaći
$parameters = array( "sta" => "ws/zadaca", "id" => $zadaca );
if (isset($_SESSION['server_session']) !== "")
	$parameters[session_name()] = $_SESSION['server_session'];

$repeat = true; $nrepeats=0;
while ($repeat) {
	$result = json_request_retry("https://zamger.etf.unsa.ba/", $parameters);
	if (!array_key_exists("success", $result)) {
		die("JSON query dajZadacu failed: unknown reason\n");
	} 
	else if ($result["success"] !== "true") {
		if ($result['code'] !== "ERR001") die("GRESKA: ".$result['message']);

		//file_put_contents("/tmp/slanjezadace", "$zadaca $zadatak $student $username: istekla sesija\n", FILE_APPEND);
		// Ponovni login
		$conf_json_user = $_SESSION['login'];
		$conf_json_pass = $_SESSION['password'];
		$result = json_login();
		
		//file_put_contents("/tmp/slanjezadace", "$zadaca $zadatak $student $username: login result $result\n", FILE_APPEND);
		if ($result == -5) {
			// Reći ćemo da je istekla sesija pa nek se opet logira
			die("GRESKA: Istekla sesija");
		}
		
		$session_id = $result['sid'];
		$cookie_expire_time = time() + 60*60*12;
		setcookie("zamger_session", $session_id, $cookie_expire_time);
		$_SESSION['server_session'] = $session_id;
		$parameters[session_name()] = $session_id;
		//file_put_contents("/tmp/slanjezadace", "$zadaca $zadatak $student $username: session id $session_id\n", FILE_APPEND);
	}
	else $repeat = false;
	if ($nrepeats++ > 5) die("GRESKA: Istekla sesija");
}

$attach = $result['data'][0]['attachment'];

if ($attach == "1") {
	$content_type = "application/zip";
	$zipfile = "/tmp/hw$zadaca"."_$zadatak"."_$student".".zip";
	$filename = dirname($filename);
	//`zip -r $zipfile $filename`;
	`sudo $webide_path/bin/wsaccess $username zip $filename $zipfile`;
	$file_contents = file_get_contents($zipfile);
	$filename = $zipfile; // filename treba sada biti ime ZIPa da bi ga ws prepoznao kao zip
} else {
	$content_type = "text/plain";
	$file_contents = `sudo $webide_path/bin/wsaccess $username read $filename`;
}


$url = "https://zamger.etf.unsa.ba/";


define('MULTIPART_BOUNDARY', '--------------------------'.microtime(true));
$header = 'Content-Type: multipart/form-data; boundary='.MULTIPART_BOUNDARY;
define('FORM_FIELD', 'attachment'); 


$content =  "--".MULTIPART_BOUNDARY."\r\n".
            "Content-Disposition: form-data; name=\"".FORM_FIELD."\"; filename=\"".basename($filename)."\"\r\n".
            "Content-Type: $content_type\r\n\r\n".
            $file_contents."\r\n";

// add some POST fields to the request too: $_POST['foo'] = 'bar'
$content .= "--".MULTIPART_BOUNDARY."\r\n".
            "Content-Disposition: form-data; name=\"sta\"\r\n\r\n".
            "ws/zadaca\r\n";
$content .= "--".MULTIPART_BOUNDARY."\r\n".
            "Content-Disposition: form-data; name=\"zadaca\"\r\n\r\n".
            "$zadaca\r\n";
$content .= "--".MULTIPART_BOUNDARY."\r\n".
            "Content-Disposition: form-data; name=\"zadatak\"\r\n\r\n".
            "$zadatak\r\n";
$content .= "--".MULTIPART_BOUNDARY."\r\n".
            "Content-Disposition: form-data; name=\"student\"\r\n\r\n".
            "$student\r\n";
$content .= "--".MULTIPART_BOUNDARY."\r\n".
            "Content-Disposition: form-data; name=\"". session_name() ."\"\r\n\r\n".
            "" . $_SESSION['server_session'] . "\r\n";

// signal end of request (note the trailing "--")
$content .= "--".MULTIPART_BOUNDARY."--\r\n";


// Request

$ctx = stream_context_create(array(
	'http' => array(
		'method' => 'POST',
		'header' => $header,
		'content' => $content,
	),
	'ssl' => array(
		"verify_peer"=>false,
		"verify_peer_name"=>false,
	),
));
$fp = fopen($url, 'rb', false, $ctx);
if (!$fp) {
	echo "HTTP request failed for $url (POST)\n";
	return FALSE;
}
$http_result = stream_get_contents($fp);
fclose($fp);


// Procesiranje odgovora servera

$allowed_http_codes = array ("200"); // Only 200 is allowed

if ($http_result===FALSE) {
	die("HTTP request failed for $url?$query ($method)\n");
}
$http_code = explode(" ", $http_response_header[0]);
$http_code = $http_code[1];
if ( !in_array($http_code, $allowed_http_codes) ) {
	die("HTTP request returned code $http_code for $url?$query ($method)\n");
}
	
$json_result = json_decode($http_result, true); // Retrieve json as associative array
if ($json_result===NULL) {
	die("d decode result as JSON\n$http_result\n");
} 

if (!array_key_exists("success", $json_result)) {
	die("JSON query saljiZadacu failed: unknown reason\n");
} 
else if ($json_result["success"] !== "true") {
	if ($json_result['code'] == "ERR001") die("GRESKA: Istekla sesija (z)");
	if ($json_result['code'] == "ERR004") die("GRESKA: Niste student");
	die("GRESKA: ".$json_result['message']);
}

print "Ok.";

?>