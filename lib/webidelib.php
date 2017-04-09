<?php

// =========================================
// Some commonly used functions for webide
// =========================================



// Random number seed
function make_seed() {
	list($usec, $sec) = explode(' ', microtime());
	return (float) $sec + ((float) $usec * 100000);
}

// Populate some variables about user e.g. properly escaped paths
function setup_paths($username) {
	global $conf_base_path, $conf_svn_path, $conf_home_path;

	$username_efn = escape_filename($username);
	
	$userdata = array();
	$userdata['username']   = $username;
	$userdata['efn']        = $username_efn;
	$userdata['esa']        = escapeshellarg($username);
	$userdata['home']       = $conf_home_path . "/" . substr($username_efn,0,1) . "/" . $username_efn;
	$userdata['workspace']  = $userdata['home'] . "/workspace";
	$userdata['svn']        = $conf_svn_path . "/" . $username_efn;
	$userdata['svn_watch']  = $conf_base_path . "/watch/syncsvn.$username_efn.pid";
	$userdata['node_watch'] = $conf_base_path . "/watch/node.$username_efn.pid";
	$userdata['htpasswd']   = $conf_base_path . "/htpasswd/$username_efn";
	
	return $userdata;
}

// Safe string to use for filename with no surprises
function escape_filename($raw) {
	return preg_replace('/[^A-Za-z0-9_\-]/', '_', $raw);
}

function is_local($host) {
	return $host == "localhost" || $host == "127.0.0.1";
}

// This functions should be in standard lib ;) they shorten the code significantly
function starts_with($string, $substring) {
	return substr($string, 0, strlen($substring)) == $substring;
}

function ends_with($string, $substring) {
	return substr($string, strlen($string) - strlen($substring)) == $substring;
}

function run_as($username, $cmd) {
	return shell_exec("su " . escapeshellarg($username) . " -c '$cmd'");
}

function run_on($server, $cmd) {
	if (is_local($server))
		return exec($cmd);
	return exec("ssh $server \"$cmd\" 2>&1");
}

// Our implementation of ps ax command with parsed result
function ps_ax($server) {
	if (empty($server) || is_local($server))
		$data = shell_exec("ps axo user:30,pid,args");
	else
		$data = shell_exec("ssh $server ps axo user:30,pid,args");
		
	$results = array();
	foreach (explode("\n", trim($data)) as $line) {
		$result = array();
		$result['user'] = trim(substr($line,0,30));
		$result['pid'] = intval(substr($line,30,35));
		$result['cmd'] = trim(substr($line,36));
		$results[] = $result;
	}
	return $results;
}

// Run command line in background and get output in a way that is apparently safe for web scripts
function background($cmd, $filename) {
	global $conf_base_path, $conf_web_background;
	$wait_secs = 0.1;
	$result = "";
	
	if (!file_exists($conf_web_background)) mkdir($conf_web_background);
	$filename = $conf_web_background . "/" . $filename;
	
	if (file_exists($filename)) {
		$result = file_get_contents($filename);
		if ($result !== "" && $result !== "\n" && time() - filemtime($filename) < 2)
			return $result;
	}

	//do {
		proc_close(proc_open("sudo $conf_base_path/bin/webidectl $cmd >$filename &", array(), $foo));
		$totalwait = 0;
		while (($result === "" || $result === "\n") && $totalwait < 1) {
			usleep($wait_secs * 1000000);
			$totalwait += $wait_secs;
			if (file_exists($filename)) $result = file_get_contents($filename);
		}
		if (strstr($result, "ERROR")) usleep($wait_secs * 1000000);
	//} while(strstr($result, "ERROR"));
	return $result;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function niceerror($msg) {
	?>
	<p style="color: red; weight: bold"><?=$msg?></p>
	<?php
}

function nicemessage($msg) {
	?>
	<p style="color: green; weight: bold"><?=$msg?></p>
	<?php
}

?>
