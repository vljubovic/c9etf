<?php

require_once("../lib/config.php");

$uri = $_SERVER['REQUEST_URI'];
$parts = explode("/", $uri);

$username = $parts[1];

// We don't know what's going on - node gone stupid, wrong port etc.
if (file_exists("$conf_base_path/razlog_nerada.txt")) {
	$radovi = file_get_contents("$conf_base_path/razlog_nerada.txt");
	if (!preg_match("/\w/", $radovi)) {
		proc_close(proc_open("sudo $conf_base_path/bin/webidectl verify-user ".escapeshellarg($username)." &", array(), $foo));
	}
} else
	proc_close(proc_open("sudo $conf_base_path/bin/webidectl verify-user ".escapeshellarg($username)." &", array(), $foo));

?>
<html>
<head>
<title>Error msg</title>
<style>
p {
	text-align: center;
	color: #606468; 
	font: 400 0.875rem/1.5 "Open Sans", sans-serif;
}
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body bgcolor="#222222">
<h1>&nbsp;</h1>
<p><img src="/loading-logo.png"></p>
<p>Ulazim u Cloud9 WebIDE. Molimo sačekajte...</p>
<script language="JavaScript">
setTimeout(function(){ location.href='/<?=$username?>/'; }, 1000);
</script>
</body>
</html>