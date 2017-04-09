<?php


session_start();

require_once("../lib/config.php");
require_once("../lib/webidelib.php");


if (!isset($_SESSION['login'])) {
	print "not-logged-in";
	return 0;
}

$login = $_SESSION['login'];

if (isset($_REQUEST['users'])) {
	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));
	ksort($users);
	foreach($users as $username => $user) {
		$lastfile = $conf_base_path . "/last/$username.last";
		if ($user["status"] === "active") {
			print "$username\t";
			$time = intval(file_get_contents($lastfile));
			print round( (time() - $time ) / 60 , 2 );
			print "\n";
		}
	}

	return 0;
}

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$elogin = escape_filename($login);
$login_watch_path = $conf_base_path . "/watch/webidectl.login.$elogin";
$verify_watch_path = $conf_base_path . "/watch/webidectl.verify-user.$elogin";

if (isset($_REQUEST['serverStatus'])) {
	if ($users[$login]['status'] != "active" && !file_exists($login_watch_path) && !file_exists($verify_watch_path)) {
		print "not-logged-in";
		return 0;
	}
	if (file_exists("$conf_base_path/razlog_nerada.txt"))
		$radovi = file_get_contents("$conf_base_path/razlog_nerada.txt");
	if (preg_match("/\w/", $radovi)) {
		print "radovi $radovi";
		return 0;
	}
	
	if (in_array($login, $conf_deny_users)) {
		print "zabrana $conf_deny_reason";
		return 0;
	}
	if (!empty($conf_allow_users) && !in_array($login, $conf_allow_users)) {
		print "zabrana $conf_deny_reason";
		return 0;
	}
	
	$result = background("server-stats", "server-stats");
	$stats = explode(" ", $result);
	if (!empty($result) && count($stats) == 6) {
		
		// Limiti
		if ($conf_limit_loadavg > 0 && ($stats[0] > $conf_limit_loadavg || $stats[0] > $conf_limit_loadavg_web)) {
			print "limit opterećenja servera";
			return 0;
		}
		$mem = $stats[1] / 1024 / 1024;
		if ($conf_limit_memory > 0  && ($mem > $conf_limit_memory || $mem > $conf_limit_memory_web))  {
			print "limit memorije";
			return 0;
		}
		if ($conf_limit_users  > 0  && $stats[2] > $conf_limit_users) {
			print "limit broja korisnika";
			return 0;
		}
		if ($conf_limit_active_users > 0  && ($stats[3] > $conf_limit_active_users || $stats[3] > $conf_limit_users_web)) {
			print "limit broja aktivnih korisnika";
			return 0;
		}
		if ($conf_limit_diskspace > 0  && $stats[4] < $conf_limit_diskspace) {
			print "limit slobodnog prostora na disku $stats[4]";
			return 0;
		}
		if ($conf_limit_inodes > 0  && $stats[5] < $conf_limit_inodes) {
			print "limit slobodnih inodes na disku $stats[5]";
			return 0;
		}
	}
	
	$nginx_test = shell_exec("/usr/sbin/nginx -t 2>&1");
	if ($login === "test" && !strstr($nginx_test, "syntax is ok") && !strstr($nginx_test, "returned only 0 bytes")) print "nginx ".$nginx_test;
	
	print "ok";
	return 0;
}

if (isset($_REQUEST['stats'])) {
	foreach($conf_nodes as $node) {
		print $node['name']." ";
		if (is_local($node['address']))
			print background("server-stats", "server-stats");
		else {
			//$code = "sudo $conf_base_path/bin/webidectl server-stats " . $node['name'];
			//print shell_exec($code) . "\n";
			$result = background("server-stats " . $node['name'], "server-stats-" . $node['name']);
			print $result . "\n";
		}
	}
	return 0;
}


if (file_exists($login_watch_path) || file_exists($verify_watch_path)) {
	print "starting";
	unlink("/tmp/web-background/is-node-up-$login");
	return 0;
}

//$result = `sudo $conf_base_path/bin/webidectl is-node-up $login`;
$result = background("is-node-up $login", "is-node-up-$login");


//$idle = `ls -l $conf_base_path/watch`;

if (trim($result) == "true") print "ok";
else print "idle";

?>
