<?php

# Run as root

require(dirname(__FILE__) . "/../lib/config.php");
 
$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore");
$vrijeme_kreiranja = 10;
$vrijeme_limit = 60;

//$svn_base = $workspace_path . "/svn/workspace";
$svn_base = $workspace_path . "/svn";
$prefix = "";

// Parametri
if ($argc != 3) { 
	die("GRESKA: reconstruct.php ocekuje tacno dva parametra\n");
}
$username = $argv[1];
$filename = $argv[2];

//$action = $argv[2];
//$prefix = "/$username"; // Ukloniti kada svaki user bude imao svoj repo

ucitaj_statistiku($username);
reconstruct_file($username, $filename);
print "Završen reconstruct\n";

exit(0);



// Funkcije

function ucitaj_statistiku($username) {
	global $username, $base_path, $stats;
	$stat_path = $base_path . "/stats/$username.stats";
	$stats = NULL;
	if (file_exists($stat_path))
		eval(file_get_contents($stat_path));
	if ($stats == NULL) {
		$stats = array(
			"global_events" => array(),
			"last_update_rev" => 0
		);
	}
}

// Sortiranje svn loga po vremenu commita u rastućem redoslijedu
function svnsort($a, $b) {
	if ($a['unixtime'] == $b['unixtime']) return 0;
	return ($a['unixtime'] < $b['unixtime']) ? -1 : 1;
}


function evtsort($a, $b) {
	if ($a['time'] == $b['time']) return 0;
	return ($a['time'] < $b['time']) ? -1 : 1;
}

function reconstruct_file($username, $filename) {
	global $stats, $base_path;
	
	if (!array_key_exists($filename, $stats))
		die("GRESKA: Nemam taj fajl u logu");
		
	$fille = array();
	$lastline = 0;
	foreach($stats[$filename]['events'] as $event) {
		if ($event['text'] == "created") {
			if (!array_key_exists('content',$event) || empty($event['content']) || $event['content'] == "binary")
				continue;
			$linije = explode("\n", $event['content']);
			for ($i=0; $i<count($linije); $i++) {
				$fille[$i+1] = $linije[$i];
				if ($i+1 > $lastline) $lastline = $i+1;
				print "CREATE ".($i+1)." ".$linije[$i]."\n";
			}
		}
		if ($event['text'] == "modified") {
			foreach($event['diff'] as $difftype => $diffentry) {
				if ($difftype == "change") {
					foreach($diffentry as $lineno => $content) {
						$fille[$lineno] = $content;
						if ($lineno > $lastline) $lastline = $lineno;
						print "CHANGE $lineno $content\n";
					}
				}
				if ($difftype == "add_lines") {
					$prva = true;
					foreach($diffentry as $lineno => $content) {
						if ($prva && strlen(trim($content)) > 2) {
							$fille[$lineno] = $content;
							print "ADD->CHANGE $lineno $content\n";
							$prva = false;
							continue;
						}
						$prva = false;
						for ($i=$lastline; $i>=$lineno; $i--)
							$fille[$i+1] = $fille[$i];
						$lastline++;
						$fille[$lineno] = $content;
						print "ADD $lineno $content\n";
					}
				}
			}
		}
	}
	
	if (empty($fille))
		die("GRESKA: Nije pronadjen nikakav sadrzaj u fajlu $filename");
	
	file_put_contents("/tmp/gen.c", join("\n", $fille));
}


function debug_log($msg) {
	$time = date("d. m. Y. H:i:s");
	`echo $time $msg >> /tmp/rename.log`;
}

?>
