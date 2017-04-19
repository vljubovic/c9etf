<?php

# Run as root

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");

debug_log("starting");


$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore", ".valgrind.out");
$skip_diff = array ("/^.*?runme$/", "/^core$/", "/^.*?\/core$/", "/^.*?.valgrind.out.core.*?$/", "/\.exe$/", "/\.o$/", "/\.gz$/", "/\.zip$/");
$vrijeme_kreiranja = 0; // Dodajemo ovoliko sekundi za svaki kreirani fajl. Ovo se dosta poveća...
$vrijeme_limit = 60; // Pauzu veću od ovoliko sekundi računamo kao ovoliko sekundi
$file_content_limit = 100000; // Ignorišemo fajlove veće od 100k
$split_folder = array("OR");

//$svn_base = $workspace_path . "/svn";
$prefix = "";

// Parameters
if ($argc == 1) { 
	debug_log("username missing");
	die("ERROR: userstats.php expects at least one parameter\n");
}
$username = $argv[1];
//$stat_path = $conf_base_path . "/stats/$username_efn.stats";


	debug_log("read stats");
read_stats($username);
clean_stats();
	debug_log("update_stats");
update_stats($username);
	debug_log("ksort");
ksort($stats);
	debug_log("file_put_contents ".$conf_base_path . "/stats/$username.stats");
write_stats($username);

exit(0);



// Functions

// Read stats file
function read_stats($username) {
	global $stats, $conf_stats_path;

	$username_efn = escape_filename($username);
	$stat_file = $conf_stats_path . "/" . "$username_efn.stats";
	
	$stats = NULL;
	if (file_exists($stat_file))
		eval(file_get_contents($stat_file));
	if ($stats == NULL) {
		$stats = array(
			"global_events" => array(),
			"last_update_rev" => 0
		);
	}
	// Stats file can reference other files to be included
	foreach ($stats as $key => $value) {
		if (is_array($value) && array_key_exists("goto", $value)) {
			$goto_path = $conf_stats_path . "/" . $value['goto'];
			eval(file_get_contents($goto_path));
			foreach($stats_goto as $ks => $vs)
				$stats[$ks] = $vs;
			$stats_goto = null;
		}
	}
}

// Write stats file
function write_stats($username) {
	global $stats, $conf_stats_path, $split_folder;
	
	$username_efn = escape_filename($username);
	
	foreach ($split_folder as $folder) {
		if (!array_key_exists($folder, $stats)) continue;
		
		$goto_dir = $conf_stats_path . "/" . $folder;
		if (!file_exists($goto_dir)) mkdir($goto_dir);
		
		$goto_file_rel = $folder . "/$username_efn.stats";
		$goto_file = $conf_stats_path . "/" . $goto_file_rel;
		
		$stats_goto = $stats;
		$stats[$folder] = array ("goto" => $goto_file_rel);
		foreach ($stats as $key => &$value) {
			if ($key != $folder."/" && strlen($key) > strlen($folder)+1 && substr($key, 0, strlen($folder)+1) == $folder . "/") {
				$stats[$key] = null;
				unset($stats[$key]);
			}
		}
		foreach ($stats_goto as $key => &$value) {
			if ($key != $folder && !(strlen($key) > strlen($folder)+1 && substr($key, 0, strlen($folder)+1) == $folder . "/")) {
				$stats_goto[$key] = null;
				unset($stats_goto[$key]);
			}
		}
		
		ensure_write( $goto_file, "\$stats_goto = ". var_export($stats_goto, true) . ";" );
		chown($goto_file, "www-data");
		chmod($goto_file, 0640);
	}
	$stats_file = $conf_stats_path . "/$username_efn.stats";
	ensure_write( $stats_file, "\$stats = " . var_export($stats, true) . ";" );
	chown($stats_file, "www-data");
	chmod($stats_file, 0640);
}

// Remove unwanted stuff from stats file
function clean_stats() {
	global $stats, $skip_diff;
	
	$forbidden_strings = array ("M3M4");
	
	foreach($stats as $name => &$value) {
		if (is_array($value) && array_key_exists('events', $value) && count($value['events'])>100) {
			$lastpos = array();
			$lasttime = array();
			$totaltime = array();
			for ($i=0; $i<count($value['events']); $i++) {
				//if ($name == "OR/T10/Z2/main.c") print "i: $i\n";
				$evtext = $value['events'][$i]['text'];
				if ($evtext == "deleted" || $evtext == "created") {
					if (!isset($lastpos['del'])) $lastpos['del']=$i;
				} else if (isset($lastpos['del'])) {
					if ($i-$lastpos['del'] > 100) {
						print "Splajsujem del do offseta $i\n";
						array_splice($value['events'], $lastpos['del'], $i-$lastpos['del']);
						$i=0;
					}
					unset($lastpos['del']);
				}
				
				if (array_key_exists($i, $value['events']) && array_key_exists('diff', $value['events'][$i])) {
					$txt = "";
					if (array_key_exists('add_lines', $value['events'][$i]['diff']))
						foreach ($value['events'][$i]['diff']['add_lines'] as $line)
							$txt .= $line;
					if (array_key_exists('change', $value['events'][$i]['diff']))
						foreach ($value['events'][$i]['diff']['change'] as $line)
							$txt .= $line;
					
					foreach ($forbidden_strings as $fstr) {
						if (strstr($txt, $fstr)) {
							if (!isset($lastpos[$fstr])) {
								print "Pronađen fstr $fstr u fajlu $name na offsetu $i\n";
								print "Nije setovan\n";
								$lastpos[$fstr] = $i;
								$lasttime[$fstr] = $value['events'][$i]['time'];
								if (isset($lastpos[$fstr])) print "Sad je setovan\n";
								$totaltime[$fstr] = 0;
							}
						} else if (isset($lastpos[$fstr])) {
							print "Splajsam do offseta $i, vrijeme bilo ".$value['total_time'];
							$value['total_time'] = $value['total_time'] - ($value['events'][$i]['time'] - $lasttime[$fstr]);
							print " postaje ".$value['total_time']."\n";
							
							// Update prema gore
							$parent = $name;
							do {
								$parent = substr($parent, 0, strrpos($parent, "/"));
								if (array_key_exists($parent, $stats)) $stats[$parent]['total_time'] -= $value['total_time'];
							} while (!empty($parent));
							
							$value['total_time'] = 0;
							array_splice($value['events'], $lastpos[$fstr], $i-$lastpos[$fstr]);
							unset($lastpos[$fstr]);
							$i=0;
							break; // iz foreacha
						}
					}
				} else
					foreach ($forbidden_strings as $fstr) unset($lastpos[$fstr]);
			}
			if (isset($lastpos['del']) && $i-$lastpos['del'] > 100) {
				print "Splajsujem del na kraju fajla\n";
				array_splice($value['events'], $lastpos['del']);
			}
		}
	
		$cleanup = false;
		foreach($skip_diff as $cpattern) {
			if (preg_match($cpattern, $name))
				$cleanup = true;
		}
		
		if (!$cleanup) continue;
		
		print "Čistim diffove za fajl $name\n";
		foreach($value['events'] as &$event) {
			if (array_key_exists('diff', $event))
				unset($event['diff']);
			if (array_key_exists('content', $event))
				unset($event['content']);
		}
	}
}


// Sortiranje svn loga po vremenu commita u rastućem redoslijedu
function svnsort($a, $b) {
	if ($a['unixtime'] == $b['unixtime']) return 0;
	return ($a['unixtime'] < $b['unixtime']) ? -1 : 1;
}

function update_stats($username) {
	global $username, $conf_svn_path, $stats, $prefix, $vrijeme_kreiranja, $svn_ignore, $vrijeme_limit, $skip_diff, $file_content_limit;
	
	$svn_path = "file://" . $conf_svn_path . "/" . $username . "/";
	
	// Provjeravamo da li je Last_update_rev bitno veći od trenutne revizije
	$tmp_log = svn_log($svn_path, SVN_REVISION_HEAD, SVN_REVISION_HEAD);
	if (empty($tmp_log)) {
		print "SVN repozitorij za $username je prazan!\n";
		exit(1);
	}
	$svn_last_rev = $tmp_log[0]['rev'];
	if ($svn_last_rev < $stats['last_update_rev']-1) {
		print "Repozitorij se resetovao u međuvremenu :(\n";
		$stats['last_update_rev'] = SVN_REVISION_INITIAL;
	}

	// Uzimamo log sa SVNa
	$svn_log = svn_log($svn_path, SVN_REVISION_HEAD, $stats['last_update_rev']);
	if (!$svn_log || empty($svn_log)) return;
	foreach($svn_log as &$entry)
		$entry['unixtime'] = strtotime($entry['date']);
	usort($svn_log, "svnsort");
	
	$last_time = $old_time = 0;
	$last_deletion = $last_addition = array( "time" => 0 );
	
	foreach($svn_log as $entry) {
		$stats['last_update_rev'] = $entry['rev'];
	
		// Jedan entry može obuhvaćati više datoteka
		foreach($entry['paths'] as $path) {
			//print "Path $path\n"
			// svn_diff funkcija ne radi :(
			/*list($diff, $errors) = svn_diff($svn_path, $rev, $svn_path, $old_rev);
			if ($diff) {
				$contents = '';
				while (!feof($diff)) {
					$contents .= fread($diff, 8192);
				}
				fclose($diff);
				fclose($errors);
				$log_zapisi[$old_rev]['diff'] = $contents;
			}*/
			
			$filepath = $path['path'];
			if (substr($filepath, 0, strlen($prefix)+1) !== "$prefix/") {
				//print "Greska: nije iz prefixa!\n";
				continue;
			}
			$filepath = substr($filepath, strlen($prefix)+1);
			$svn_file_path = $svn_path . $filepath;
			
			// Specijalno procesiranje za login i logout
			if ($filepath == ".login") {
				$ftime = strtotime(svn_cat($svn_file_path, $entry['rev']));
				array_push($stats['global_events'], array(
					"time" => $entry['unixtime'],
					"real_time" => $ftime,
					"text" => "login"
				) );
				continue;
			}
			
			if ($filepath == ".logout") {
				$ftime = strtotime(svn_cat($svn_file_path, $entry['rev']));
				array_push($stats['global_events'], array(
					"time" => $entry['unixtime'],
					"real_time" => $ftime,
					"text" => "logout"
				) );
				continue;
			}
			
			// Ostali eventi
			
			// Praćenje vremena
			$old_time = $last_time;
			$last_time = $entry['unixtime'];
			
			// Sjeckamo put na dijelove
			$path_parts = explode("/", $filepath);
			$ignored = false;
			foreach ($path_parts as $part)
				if(in_array($part, $svn_ignore))
					$ignored = true;
			if ($ignored) continue; // Ignorisani putevi

			// Kompajliranje/pokretanje/autotest - event pridružujemo parent folderu
			$compiled = $runned = $tested = false;
			$filename = end($path_parts);
			if ($filename == ".gcc.out") {
				$compiled = true;
				if (count($path_parts) > 1) {
					array_pop($path_parts);
					$filepath = substr($filepath, 0, strlen($filepath) - strlen("/.gcc.out"));
				}
			}
			else if ($filename == "runme" || $filename == ".runme") {
				$runned = true;
				if (count($path_parts) > 1) {
					array_pop($path_parts);
					$filepath = substr($filepath, 0, strlen($filepath) - strlen($filename) - 1);
				}
			}
			else if ($filename == ".at_result") {
				$tested = true;
				if (count($path_parts) > 1) {
					array_pop($path_parts);
					$filepath = substr($filepath, 0, strlen($filepath) - strlen("/.at_result"));
				}
			}

			// Ako je modifikacija, uzimamo diff
			else if ($path['action'] == "M" || $path['action'] == "R") {
				$diff = true;
				foreach ($skip_diff as $cpattern)
					if(preg_match($cpattern, $filename))
						$diff = false;
				if ($diff) {
					$rev = $entry['rev'];
					$old_rev = $stats[$filepath]['last_revision'];
					$scpath = str_replace(" ", "\\ ", $svn_file_path);
					$scpath = str_replace(")", "\\)", $svn_file_path);
					$scpath = str_replace("(", "\\(", $svn_file_path);
					$diff_contents = `svn diff $scpath@$old_rev $scpath@$rev`;
					$diff_result = compressed_diff($diff_contents);
				}
			}
			
			// Praćenje ukupnog vremena rada
			if ($last_time - $old_time < $vrijeme_limit)
				$vrijeme_zadatka = $last_time - $old_time;
			else
				$vrijeme_zadatka = $vrijeme_limit; // ?? ispade da se više isplati raditi sporo?
			//print "$filepath last_time $last_time old_time $old_time vrijeme_zadatka $vrijeme_zadatka\n";

			// Rekurzivno ažuriramo sve nadfoldere
			$subpaths = array();
			foreach($path_parts as $part) {
				if (!empty($subpaths))
					$part = $subpaths[count($subpaths)-1] . "/$part";
				array_push($subpaths, $part);
			}
			
			// Kreiramo sve nadfoldere ako ne postoje
			$kreiran = false;
			foreach($subpaths as $subpath) {
				// Ako nije ranije postojao put, kreiramo ga
				if (!array_key_exists($subpath, $stats)) {
					$stats[$subpath] = array(
						"total_time" => $vrijeme_kreiranja,
						"builds" => 0,
						"builds_succeeded" => 0,
						"testings" => 0,
						"last_test_results" => "",
						"events" => array(),
						"last_revision" => $entry['rev'],
						"entries" => array(),
					);
					//print "Kreiram novi node $subpath vrijeme $vrijeme_kreiranja\n";
					
					// Akcije vezane za kreiranje finalnog puta
					if ($subpath == $filepath) {
						// Da li je ovo rename?
						$this_folder = substr($subpath, 0, strlen($subpath)-strlen($filename));
						if ($entry['unixtime'] - $last_deletion['time'] < 3 && $this_folder == $last_deletion['folder'] && $filepath != $last_deletion['filepath']) {
							// print "-- detektovan rename\n";
							$delpath = $last_deletion['filepath'];
							array_pop($stats[$delpath]['events']); // Brišemo event brisanja

							array_push($stats[$subpath]['events'], array(
								"time" => $entry['unixtime'],
								"text" => "rename",
								"filename" => $filename,
								"old_filename" => $last_deletion['filename'],
								"old_filepath" => $last_deletion['filepath'],
							) );
							$last_deletion = array( "time" => 0 );
							
						} else if ($entry['unixtime'] - $last_deletion['time'] < 3 && $filename == $last_deletion['filename'] && $filepath != $last_deletion['filepath']) {
							// print "-- detektovan move\n";
							$delpath = $last_deletion['filepath'];
							array_pop($stats[$delpath]['events']); // Brišemo event brisanja

							array_push($stats[$subpath]['events'], array(
								"time" => $entry['unixtime'],
								"text" => "move",
								"filename" => $filename,
								"old_filename" => $last_deletion['filename'],
								"old_filepath" => $last_deletion['filepath'],
							) );
							$last_deletion = array( "time" => 0 );
							
						} else {
							// Nije rename
							$text = "created";
							$scpath = str_replace(" ", "%20", $svn_file_path);
							$content = @svn_cat($scpath, $entry['rev']);
							$lastError = error_get_last();
							if (strstr($lastError['message'], "refers to a directory")) {
								// print "Ovo je direktorij\n";
								$text = "created_folder";
								
							} else if (strstr($lastError['message'], "File not found") || strstr($lastError['message'], "Unable to find repository")) {
								// Funkcija svn_cat nekad radi nekad ne radi :( neobjašnjivo
								$scpath = str_replace(" ", "\\ ", $svn_file_path);
								$cmd = "svn cat $scpath@".$entry['rev']." 2>&1";
								$content = `$cmd`;
								if (strstr($content, "refers to a directory")) {
									$text = "created_folder";
								}
							} else if (!strstr($lastError['message'], "Undefined variable: undef_var")) {
								print "Neka nova greška: ".$lastError['message']."\n";
							}
							
							// Resetovanje PHP grešaka
							set_error_handler('var_dump', 0);
							@$undef_var;
							restore_error_handler();

							// Detekcija binarne datoteke preko magic-a
							if (substr($content,1,3) == "ELF") $content="binary";
							
							// Fajlovi čiji sadržaj ne uzimamo
							$skip_content = false;
							foreach ($skip_diff as $cpattern)
								if(preg_match($cpattern, $filename))
									$skip_content = true;
							if ($skip_content) $content = "binary";
							
							// Skraćujemo datoteke >100k
							if (strlen($content) > $file_content_limit) $content = substr($content, 0, 100000) . "...";

							// Dodajemo evenet
							array_push($stats[$subpath]['events'], array(
								"time" => $entry['unixtime'],
								"text" => $text,
								"filename" => $filename,
								"content" => $content,
							) );
							$kreiran = true;
							
							// Ako je u pitanju kreiranje finalnog puta, nećemo povećavati vrijeme svih nadfoldera
							// Ovo se nažalost mora uraditi ovako jer su folderi složeni od viših ka nižim jer je to 
							// prirodan redoslijed kreiranja (ako nisu postojali ranije)
							foreach($subpaths as $subpath2) {
								if ($stats[$subpath2]['total_time'] > $vrijeme_kreiranja && $stats[$subpath2]['total_time'] > $vrijeme_zadatka) {
									$stats[$subpath2]['total_time'] -= $vrijeme_zadatka;
									//print "Smanjujem vrijeme za $subpath2 za $vrijeme_zadatka\n";
								}
							}
							
							// Praćenje move/rename akcija
							$foulder = substr($filepath, 0, strlen($filepath) - strlen($filename));
							$last_addition = array(
								"time" => $entry['unixtime'],
								"filepath" => $filepath,
								"filename" => $filename,
								"folder" => $foulder
							);
						}
						
					} else {
						// Nadfolder
						array_push($stats[$subpath]['events'], array(
							"time" => $entry['unixtime'],
							"text" => "created",
							"filename" => $filename
						) );
					}
					$kreiran = true;
				} else {
					$stats[$subpath]['total_time'] += $vrijeme_zadatka;
					//print "Povećavam vrijeme za $subpath za $vrijeme_zadatka\n";
				}
			}
			
			// Ažuriramo entries
			$previous = "";
			foreach(array_reverse($subpaths) as $subpath) {
				if ($previous != "") {
					if (!in_array($previous, $stats[$subpath]['entries']))
						array_push($stats[$subpath]['entries'], $previous);
				}
				$previous = $subpath;
			}
			
			// Dodajemo event na stavku
			end($stats[$filepath]['events']);
			$lastk = key($stats[$filepath]['events']);
			$last_event = &$stats[$filepath]['events'][$lastk];
			$stats[$filepath]['last_revision'] = $entry['rev'];
			
			// Brisanje
			if ($path['action'] == "D") {
				if ($entry['unixtime'] - $last_addition['time'] < 3 && $this_folder == $last_addition['folder'] && $filepath != $last_addition['filepath']) {
					// Rename
					end($stats[$last_addition['filepath']]['events']);
					$lastk = key($stats[$last_addition['filepath']]['events']);
					$last_event = &$stats[$last_addition['filepath']]['events'][$lastk];
					
					$addpath = $last_addition['filepath'];
					$last_event['text'] = "rename";
					$last_event['old_filename'] = $filename;
					$last_event['old_filepath'] = $filepath;
					$last_addition = array( "time" => 0 );
				} elseif ($entry['unixtime'] - $last_addition['time'] < 3 && $filename == $last_addition['filename'] && $filepath != $last_addition['filepath']) {
					// Move
					end($stats[$last_addition['filepath']]['events']);
					$lastk = key($stats[$last_addition['filepath']]['events']);
					$last_event = &$stats[$last_addition['filepath']]['events'][$lastk];

					$addpath = $last_addition['filepath'];
					$last_event['text'] = "move";
					$last_event['old_path'] = $filepath;
					$last_addition = array( "time" => 0 );
				} else {
					// Fajl obrisan
					array_push($stats[$filepath]['events'], array(
						"time" => $entry['unixtime'],
						"text" => "deleted"
					) );
					$foulder = substr($filepath, 0, strlen($filepath) - strlen($filename));
					$last_deletion = array(
						"time" => $entry['unixtime'],
						"filepath" => $filepath,
						"filename" => $filename,
						"folder" => $foulder,
					);
				}
				
			// Kompajliranje
			} else if ($compiled) {
				$stats[$filepath]['builds']++;
				if ($last_event['text'] == "compiled successfully" && abs($last_event['time'] - $entry['unixtime']) < 3) {
					// Samo ćemo dodati izlaz kompajlera na runme
					$scpath = str_replace(" ", "%20", $svn_file_path);
					$last_event['output'] = svn_cat($scpath, $entry['rev']);
					// print "Rev: ".$entry['rev']." OUTPUT:\n".$last_event['output']."\n";
					
				} else {
					// Za sada ne znamo da li je uspješno pokrenut program
					$scpath = str_replace(" ", "%20", $svn_file_path);
					$output = svn_cat($scpath, $entry['rev']);
					array_push($stats[$filepath]['events'], array(
						"time" => $entry['unixtime'],
						"text" => "compiled",
						"output" => $output,
						"rev" => $entry['rev']
					) );
				}
				
			// Uspješno kompajliranje
			} else if ($runned) {
				$stats[$filepath]['builds_succeeded']++;
				if ($last_event['text'] == "compiled" && abs($last_event['time'] - $entry['unixtime']) < 3) {
					// Ako već postoji gcc output, samo ćemo označiti da je uspješno
					$last_event['text'] = "compiled successfully";
				
				} else {
					array_push($stats[$filepath]['events'], array(
						"time" => $entry['unixtime'],
						"text" => "compiled successfully",
						"rev" => $entry['rev']
					) );
				}
				
			// Pokrenut buildservice za autotestove
			} else if ($tested) {
				$stats[$filepath]['testings']++;

				// Rezultati testiranja
				$scpath = str_replace(" ", "%20", $svn_file_path);
				$rezultati_testova = json_decode(svn_cat($scpath, $entry['rev']), true);
				$svn_test_path = $svn_path . $filepath . "/.autotest";
				$scpath = str_replace(" ", "%20", $svn_test_path);
				$testovi = json_decode(svn_cat($scpath, $entry['rev']), true);

				$ukupno_testova = count($testovi['test_specifications']);
				$uspjesnih_testova = 0;
				if (is_array($rezultati_testova) && array_key_exists("test_results", $rezultati_testova) && is_array($rezultati_testova['test_results'])) {
					foreach ($rezultati_testova['test_results'] as $test) {
						if ($test['status'] == 1) $uspjesnih_testova++;
					}
				}
				$stats[$filepath]['last_test_results'] = "$uspjesnih_testova/$ukupno_testova";
				
				array_push($stats[$filepath]['events'], array(
					"time" => $entry['unixtime'],
					"text" => "ran tests",
					"test_results" => "$uspjesnih_testova/$ukupno_testova"
				) );
				
			// Izmjena fajla
			} else if ($path['action'] != "A") {
				array_push($stats[$filepath]['events'], array(
					"time" => $entry['unixtime'],
					"text" => "modified",
					"diff" => $diff_result,
				) );
			
			// SVN je registrovao kreiranje za put koji već imamo u statistici - dodajemo event "created"
			} else if (!$kreiran) {
				array_push($stats[$filepath]['events'], array(
					"time" => $entry['unixtime'],
					"text" => "created",
					"filename" => $filename
				) );
			}
		}
	}
}

// Funkcija koja konvertuje unified diff format u nešto vrlo sažeto što je nama dovoljno
function compressed_diff($diff_text) {
	$result = array( 'remove_lines' => array(), 'add_lines' => array() );
	$current_line = -1;
	$removed = 0;
	foreach(explode("\n", $diff_text) as $line) {
		// Preskačemo zaglavlje
		if (strlen($line) > 3 && (substr($line, 0, 3) == "+++" || substr($line, 0, 3) == "---"))
			continue;
		
		// Uzimamo redni broj prve linije
		if (strlen($line) > 2 && substr($line, 0, 2) == "@@") {
			$current_line = intval(substr($line, 4)) - 1; // sljedeći prolaz će ga uvećati za 1
			continue;
		}

		$current_line++;
		if (strlen($line) > 0 && $line[0] == '-') {
			$result['remove_lines'][$current_line] = substr($line,1);
			// Linije izbačene iz source-a ćemo oduzeti od countera
			$removed++;
		} else {
			$current_line -= $removed;
			$removed = 0;
		}
		if (strlen($line) > 0 && $line[0] == '+')
			$result['add_lines'][$current_line-$removed] = substr($line,1);
	}
	// Dodatno kompresujemo jedan čest slučaj
	if (count($result['remove_lines']) == 1 && count($result['add_lines']) == 1) {
		// Uzimamo broj linije
		$lineno = array_keys($result['remove_lines'])[0];
		
		$result['change'] = $result['add_lines'];
		$result['remove_lines'] = array();
		$result['add_lines'] = array();
	} else if (count($result['add_lines'] > 0)) {
		// Ne interesuje nas šta je staro, samo šta je novo
		//$result['remove_lines'] = array();
	}
	// Save space
	if (count($result['remove_lines']) == 0) unset($result['remove_lines']);
	if (count($result['add_lines']) == 0) unset($result['add_lines']);
	
	return $result;
}


function ensure_write($filename, $content) {
	$retry = 1;
	while(true) {
		if (file_put_contents($filename, $content)) return;
		print "Error writing $filename... retry in $retry seconds\n";
		sleep($retry);
	}
}


function debug_log($msg) {
	$time = date("d. m. Y. H:i:s");
	`echo $time $msg >> /tmp/userstats.log`;
}

?>
