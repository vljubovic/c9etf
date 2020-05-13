<?php

// =========================================
// USERSTATS.PHP
// C9@ETF project (c) 2015-2018
// 
// Process SVN logs and update .stats files
// =========================================



# Run as root

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");

debug_log("starting");


// Filenames and directories that should not be committed to svn
$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore", ".valgrind.out");

// Skip calculating diff for filenames matching these regexes
$skip_diff = array ("/^.*?runme$/", "/^core$/", "/^.*?\/core$/", "/^.*?.valgrind.out.core.*?$/", "/\.exe$/", "/\.o$/", "/\.gz$/", 
	"/\.zip$/", "/autotest.txt/", "/.gdb_proxy/", "/speedtest.cpp/");

// Skip calculating diff for files longer than this many bytes
$file_content_limit = 100000; 

// Seconds that will be added to total work time for creating a new file
$create_time = 0;

// Intervals longer than limit seconds will be counted as this many seconds in total work time
$time_break_limit = 60;

// These folders will be kept in separate stats files for perfomance (TODO: tie to defined list of courses)
$split_folder = array("OR", "TP", "OR2015", "TP2015", "OR2016", "TP2016", "OR2017", "TP2017");

// Show debugging messages
$DEBUG = true;

$prefix = "";

// Parameters
if ($argc == 1) { 
	debug_log("username missing");
	die("ERROR: userstats.php expects at least one parameter\n");
}
$username = $argv[1];


	debug_log("read stats");
read_stats($username);
clean_stats();
	debug_log("update_stats");
update_stats_new($username);
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
	
	if (!file_exists($conf_stats_path))
		mkdir($conf_stats_path, 0755, true);
	
	//print "reading file $stat_file\n";
	
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
			//print "goto $goto_path\n";
			if (file_exists($goto_path)) {
				eval(file_get_contents($goto_path));
				foreach($stats_goto as $ks => $vs)
					$stats[$ks] = $vs;
			}
			$stats_goto = null;
		}
	}
}

// Write stats file
function write_stats($username) {
	global $stats, $conf_stats_path, $split_folder, $conf_nginx_user;
	
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
		chown($goto_file, $conf_nginx_user);
		chmod($goto_file, 0640);
	}
	$stats_file = $conf_stats_path . "/$username_efn.stats";
	ensure_write( $stats_file, "\$stats = " . var_export($stats, true) . ";" );
	chown($stats_file, $conf_nginx_user);
	chmod($stats_file, 0640);
}

// Remove unwanted stuff from stats file
function clean_stats() {
	global $stats, $skip_diff, $DEBUG;
	
	$forbidden_strings = array ("M3M4");
	$long_line_limit = 50000;
	
	foreach($stats as $name => &$value) {
		$remember = -1;
		if (is_array($value) && array_key_exists('events', $value) && count($value['events']) > 0) {
			// Blank empty event
			if (array_key_exists('', $value['events'])) {
				if ($DEBUG) print "[WWW] Deleting blank event, file $name\n";
				unset($value['events']['']);
			}
		
			$lastpos = array();
			$lasttime = array();
			$totaltime = array();
			$max = max(array_keys($value['events']))+1;
			$count = count($value['events']);
			if ($max != $count) {
				if ($DEBUG) print "[WWW] Renumbering...\n";
				$value['events'] = array_values($value['events']);
			}
			
			// We must use indexing to change the original array
			for ($i=0; $i<count($value['events']); $i++) {
				if (!array_key_exists($i, $value['events'])) {
					if ($DEBUG) print "[WWW] Skipping event $i, file $name\n";
					continue;
				}
				
				else if (!is_array($value['events'][$i])) {
					if ($DEBUG) print "[WWW] Event $i not an array, file $name\n";
					array_splice($value['events'], $i, 1);
					continue; 
				}
				
				else if (!array_key_exists('text', $value['events'][$i])) {
					if ($DEBUG) print "[EEE] Event without text, file $name, event $i\n";
					$evtext = "";
				}
				else
					$evtext = $value['events'][$i]['text'];
				
				if ($evtext == "deleted" || $evtext == "created") {
					if (!isset($lastpos['del'])) $lastpos['del']=$i;
				} else if (isset($lastpos['del'])) {
					if ($i-$lastpos['del'] > 100) {
						if ($DEBUG) print "[WWW] Splicing 'del' to offset $i\n";
						array_splice($value['events'], $lastpos['del'], $i-$lastpos['del']);
						$i=0;
					}
					unset($lastpos['del']);
				}
				
				if ($evtext == "created") {
					if (!array_key_exists('content', $value['events'][$i]) && $i != 0 && $value['events'][$i]['time'] == $value['events'][0]['time']) {
						if ($DEBUG) print "[WWW] Empty created event, file $name event $i count ".count($value['events']) . "\n";
						array_splice($value['events'], $i, 1);
						$i--;
					} else if (!array_key_exists('content', $value['events'][$i]) && $i != 0) {
						//print "Empty late created event, file $name event $i\n";
					} else if (!array_key_exists('content', $value['events'][$i])) {
						if ($DEBUG) print "[WWW] No content in created event, file $name event $i\n";
						$value['events'][$i]['content'] = "";
						$remember = $i;
					} else if (!preg_match('//u', $value['events'][$i]['content'])) {
						if ($DEBUG) print "[WWW] Invalid unicode file $name event $i (created)\n";
						$value['events'][$i]['content'] = "";
					}
					continue;
				}
				
				// Checking diff contents
				if (array_key_exists($i, $value['events']) && array_key_exists('diff', $value['events'][$i]) &&
					is_array($value['events'][$i]['diff'])) {
					
					// Check and fix unicode
					if (array_key_exists('add_lines', $value['events'][$i]['diff']))
						foreach ($value['events'][$i]['diff']['add_lines'] as &$line) {
							if (!preg_match('//u', $line)) {
								if ($DEBUG) print "[WWW] Invalid unicode file $name event $i (add_lines)\n";
								$line = "";
							}
							if (strlen($line) > $long_line_limit) {
								if ($DEBUG) print "[WWW] Very long line, file $name event $i (add_lines) len ".strlen($line)."\n";
								$line = "";
							}
						}
					if (array_key_exists('change', $value['events'][$i]['diff']))
						foreach ($value['events'][$i]['diff']['change'] as &$line) {
							if (!preg_match('//u', $line)) {
								if ($DEBUG) print "[WWW] Invalid unicode file $name event $i (change)\n";
								$line = "";
							}
							if (strlen($line) > $long_line_limit) {
								if ($DEBUG) print "[WWW] Very long line, file $name event $i (change) len ".strlen($line)."\n";
								$line = "";
							}
						}
					if (array_key_exists('remove_lines', $value['events'][$i]['diff']))
						foreach ($value['events'][$i]['diff']['remove_lines'] as &$line) {
							if (!preg_match('//u', $line)) {
								if ($DEBUG) print "[WWW] Invalid unicode file $name event $i (remove_lines)\n";
								$line = "";
							}
							if (strlen($line) > $long_line_limit) {
								if ($DEBUG) print "[WWW] Very long line, file $name event $i (remove_lines) len ".strlen($line)."\n";
								$line = "";
							}
						}
					
					// Join add/change lines to test for forbidden strings
					/*$txt = "";
					if (array_key_exists('add_lines', $value['events'][$i]['diff']))
						foreach ($value['events'][$i]['diff']['add_lines'] as $line)
							$txt .= $line;
					if (array_key_exists('change', $value['events'][$i]['diff']))
						foreach ($value['events'][$i]['diff']['change'] as $line)
							$txt .= $line;
					
					foreach ($forbidden_strings as $fstr) {
						if (strstr($txt, $fstr)) {
							if (!isset($lastpos[$fstr])) {
								if ($DEBUG) print "Found fstr $fstr in file $name at offset $i\nIt's not set\n";
								$lastpos[$fstr] = $i;
								$lasttime[$fstr] = $value['events'][$i]['time'];
								if ($DEBUG && isset($lastpos[$fstr])) print "Now it is set\n";
								$totaltime[$fstr] = 0;
							}
						} else if (isset($lastpos[$fstr])) {
							if ($DEBUG) print "Splicing to offset $i, time was ".$value['total_time'];
							$value['total_time'] = $value['total_time'] - ($value['events'][$i]['time'] - $lasttime[$fstr]);
							if ($DEBUG) print " now becomes ".$value['total_time']."\n";
							
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
					}*/
				} else
					foreach ($forbidden_strings as $fstr) unset($lastpos[$fstr]);
			}
			if (isset($lastpos['del']) && $i-$lastpos['del'] > 100) {
				if ($DEBUG) print "[WWW] Splicing 'del' at end of file\n";
				array_splice($value['events'], $lastpos['del']);
			}
		}
		
		//if ($remember > -1) print_r($value['events'][$remember]);
	
		$cleanup = false;
		foreach($skip_diff as $cpattern) {
			if (preg_match($cpattern, $name))
				$cleanup = true;
		}
		
		if (!$cleanup) continue;
		
		$found = false;
		foreach($value['events'] as $key => &$event) {
			if (!is_array($event)) { unset($value['events'][$key]); $found = true; continue; }
			if (array_key_exists('diff', $event)) {
				unset($event['diff']);
				$found = false;
			}
			if (array_key_exists('content', $event)) {
				$event['content'] = "";
				$found = false;
			}
			if (array_key_exists('output', $event)) {
				unset($event['output']);
				$found = false;
			}
		}
		if ($DEBUG && $found) print "[WWW] Removing diffs for file $name (in \$skip_diff)\n";
	}
}


// Sort svn log by entry time, ascending
function svnsort($a, $b) {
	if ($a['unixtime'] == $b['unixtime']) return 0;
	return ($a['unixtime'] < $b['unixtime']) ? -1 : 1;
}

function update_stats_new($username) {
	global $username, $conf_svn_path, $stats, $prefix, $create_time, $svn_ignore, $time_break_limit, $skip_diff, $file_content_limit, $DEBUG;
	
	$svn_path = "file://" . $conf_svn_path . "/" . $username . "/";
	
	// Checking if repository is healthy and last revision number
	try {
		$svn_info_xml = new SimpleXMLElement(`svn info --xml $svn_path`);
	} catch(Exception $e) {
		print "FATAL: SVN repository for $username is broken!\n";
		exit(1);
	}
	
	$svn_last_rev = $svn_info_xml->entry['revision'][0];
	if ($svn_last_rev < $stats['last_update_rev']-1) {
		if ($DEBUG) print "Repository was updated in the meantime :( starting from revision 1\n";
		$stats['last_update_rev'] = 1;
	}
	
	// Retrieving log
	try {
		$revision = "-r 0:HEAD";
		if ($stats['last_update_rev'] > 1) $revision = "-r ".$stats['last_update_rev'].":HEAD";
		$xml = `svn log -v --xml $revision $svn_path`;
		$svn_log_xml = new SimpleXMLElement($xml);
	} catch(Exception $e) {
		// last_update_rev will be set to 2 when repository is empty...
		if ($stats['last_update_rev'] == 2)
			// Everything is ok, don't exit(1)
			return;
		print "FATAL: SVN repository for $username is broken!\n";
		exit(1);
	}
	// sort by time?
	
	$event_time = $previous_event_time = 0;
	$last_deletion = $last_addition = array( "time" => 0 );
	
	foreach($svn_log_xml->children() as $entry) {
		$entry['unixtime'] = strtotime($entry->date);
		$stats['last_update_rev'] = intval($entry['revision']);
		$rev = intval($entry['revision']);
	
		// One log entry can affect multiple paths
		foreach($entry->paths->children() as $path) {			
			$filepath = $path[0];
			if (substr($filepath, 0, strlen($prefix)+1) !== "$prefix/") {
				continue;
			}
			$filepath = substr($filepath, strlen($prefix)+1);
			$svn_file_path = $svn_path . $filepath;
			$svn_cmd_path = svn_cmd_escape($svn_file_path);
			
			// Special processing for login/logout events (not counted in event time)
			if ($filepath == ".login") {
				$ftime = strtotime(`svn cat -r$rev $svn_cmd_path`);
				array_push($stats['global_events'], array(
					"time" => intval($entry['unixtime']),
					"real_time" => $ftime,
					"text" => "login"
				) );
				continue;
			}
			
			if ($filepath == ".logout") {
				$ftime = strtotime(`svn cat -r$rev $svn_cmd_path`);
				array_push($stats['global_events'], array(
					"time" => intval($entry['unixtime']),
					"real_time" => $ftime,
					"text" => "logout"
				) );
				continue;
			}
			
			// Other events
			
			// Time tracking
			$previous_event_time = $event_time;
			$event_time = intval($entry['unixtime']);
			
			// Cut path into segments
			$path_parts = explode("/", $filepath);
			
			// Is path in ignored files/folders list?
			$ignored = false;
			foreach ($path_parts as $part)
				if(in_array($part, $svn_ignore))
					$ignored = true;
			if ($ignored) continue; 

			// Compile/run/autotest event is attached to parent directory as well
			$compiled = $runned = $tested = false;
			$filename = end($path_parts);
			if ($filename == ".gcc.out") {
				// For some reason empty .gcc.out gets produced which doesn't correspond to compile event?
				$content = trim(`svn cat -r$rev $svn_cmd_path`);
				if (!empty($content)) {
					$compiled = true;
					if (count($path_parts) > 1) {
						array_pop($path_parts);
						$filepath = substr($filepath, 0, strlen($filepath) - strlen("/.gcc.out"));
					}
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

			// If it's a modify event, get diff
			else if ($path['action'] == "M" || $path['action'] == "R") {
				$diff = true;
				$diff_result = null;
				foreach ($skip_diff as $cpattern)
					if(preg_match($cpattern, $filename))
						$diff = false;
				if ($diff) {
					$old_rev = $stats[$filepath]['last_revision'];
					if (intval($old_rev) < 1) $old_rev=1;
					$diff_contents = `svn diff -r$old_rev:$rev $svn_cmd_path`;
					$diff_result = compressed_diff($diff_contents);
					$diff_result_old = compressed_diff_old($diff_contents);
				}
			}
			
			// Total worktime tracking
			if ($event_time - $previous_event_time < $time_break_limit)
				$task_time = $event_time - $previous_event_time;
			else
				$task_time = $time_break_limit;

			// Recursively update all parent directories
			$subpaths = array();
			foreach($path_parts as $part) {
				if (!empty($subpaths))
					$part = $subpaths[count($subpaths)-1] . "/$part";
				array_push($subpaths, $part);
			}
			
			// Create all parent directories if neccessary
			$created_path = false;
			foreach($subpaths as $subpath) {
				// If path wasn't known in stats, we create a new entry
				if (!array_key_exists($subpath, $stats)) {
					$stats[$subpath] = array(
						"total_time" => $create_time,
						"builds" => 0,
						"builds_succeeded" => 0,
						"testings" => 0,
						"last_test_results" => "",
						"events" => array(),
						"last_revision" => $rev,
						"entries" => array(),
						"stats_version" => "2",
					);
					
					// Actions related to creation of a new file entry
					if ($subpath == $filepath) {
					
						// Is this a rename event?
						$current_folder = substr($subpath, 0, strlen($subpath)-strlen($filename));
						
						// If a file was deleted less than 3 seconds ago, then it is
						if ($event_time - $last_deletion['time'] < 3 && $current_folder == $last_deletion['folder'] && $filepath != $last_deletion['filepath']) {
							$delpath = $last_deletion['filepath'];
							array_pop($stats[$delpath]['events']); // Remove the delete event

							array_push($stats[$subpath]['events'], array(
								"time" => $event_time,
								"text" => "rename",
								"filename" => $filename,
								"old_filename" => $last_deletion['filename'],
								"old_filepath" => $last_deletion['filepath'],
							) );
							$last_deletion = array( "time" => 0 );
							
						// If filename is the same, but folder is different, then it's a move
						} else if ($event_time - $last_deletion['time'] < 3 && $filename == $last_deletion['filename'] && $filepath != $last_deletion['filepath']) {
							$delpath = $last_deletion['filepath'];
							array_pop($stats[$delpath]['events']); // Remove the delete event

							array_push($stats[$subpath]['events'], array(
								"time" => $event_time,
								"text" => "move",
								"filename" => $filename,
								"old_filename" => $last_deletion['filename'],
								"old_filepath" => $last_deletion['filepath'],
							) );
							$last_deletion = array( "time" => 0 );
							
						} else {
							// Not a rename
							$text = "created";
							$content = `svn cat -r$rev $svn_cmd_path`;
							// Actually this is a folder
							if (strstr($content, "refers to a directory"))
								$text = "created_folder";

							// Detect binary files using magic strings
							if (substr($content,1,3) == "ELF") $content="binary";
							
							// Is file one of those whose content we skip?
							$skip_content = false;
							foreach ($skip_diff as $cpattern)
								if(preg_match($cpattern, $filename))
									$skip_content = true;
							if ($skip_content) $content = "binary";
							
							// Files longer than file_content_limit will be truncated
							if (strlen($content) > $file_content_limit) 
								$content = substr($content, 0, 100000) . "...";

							// Add a create event to this entry
							array_push($stats[$subpath]['events'], array(
								"time" => $event_time,
								"text" => $text,
								"filename" => $filename,
								"content" => $content,
							) );
							$created_path = true;
							
							// If this is a create event, don't increment total work time for parent folders
							// Otherwise time for certain popular folders could be huge
							foreach($subpaths as $subpath2) {
								if ($stats[$subpath2]['total_time'] > $create_time && $stats[$subpath2]['total_time'] > $task_time) {
									$stats[$subpath2]['total_time'] -= $task_time;
								}
							}
							
							// This array is used for tracking move/rename operations
							$last_addition = array(
								"time" => $event_time,
								"filepath" => $filepath,
								"filename" => $filename,
								"folder" => $current_folder
							);
						}
						
					} else {
						// Add a creation event to parent folder
						array_push($stats[$subpath]['events'], array(
							"time" => $event_time,
							"text" => "created",
							"filename" => $filename
						) );
					}
					$created_path = true;
				} else {
					// Increment time for parent folders
					$stats[$subpath]['total_time'] += $task_time;
				}
			}
			
			// The "entries" field contains all members of a folder
			$previous = "";
			foreach(array_reverse($subpaths) as $subpath) {
				if ($previous != "") {
					// Create entries field if it doesn't exist
					if (!array_key_exists("entries", $stats[$subpath]) || empty($stats[$subpath]['entries']))
						$stats[$subpath]['entries'] = array();
					if (!in_array($previous, $stats[$subpath]['entries']))
						array_push($stats[$subpath]['entries'], $previous);
				}
				$previous = $subpath;
			}
			
			
			// Add a new entry to the "events" field
			
			// Find last event (for detecting move/rename)
			end($stats[$filepath]['events']);
			$lastk = key($stats[$filepath]['events']);
			$last_event = &$stats[$filepath]['events'][$lastk];
			$stats[$filepath]['last_revision'] = $rev;
			
			// Delete event
			if ($path['action'] == "D") {
				// If a new file was created less than 3 seconds ago, this is a rename event
				if ($event_time - $last_addition['time'] < 3 && $current_folder == $last_addition['folder'] && $filepath != $last_addition['filepath']) {
					// Rename
					end($stats[$last_addition['filepath']]['events']);
					$lastk = key($stats[$last_addition['filepath']]['events']);
					$last_event = &$stats[$last_addition['filepath']]['events'][$lastk];
					
					$addpath = $last_addition['filepath'];
					$last_event['text'] = "rename";
					$last_event['old_filename'] = $filename;
					$last_event['old_filepath'] = $filepath;
					$last_addition = array( "time" => 0 );
					
				// If filename is the same, but folder is different, then it's a move
				} elseif ($event_time - $last_addition['time'] < 3 && $filename == $last_addition['filename'] && $filepath != $last_addition['filepath']) {
					// Move
					end($stats[$last_addition['filepath']]['events']);
					$lastk = key($stats[$last_addition['filepath']]['events']);
					$last_event = &$stats[$last_addition['filepath']]['events'][$lastk];

					$addpath = $last_addition['filepath'];
					$last_event['text'] = "move";
					$last_event['old_path'] = $filepath;
					$last_addition = array( "time" => 0 );
					
				} else {
					// Actual delete event
					array_push($stats[$filepath]['events'], array(
						"time" => $event_time,
						"text" => "deleted"
					) );
					$current_folder = substr($filepath, 0, strlen($filepath) - strlen($filename));
					$last_deletion = array(
						"time" => $event_time,
						"filepath" => $filepath,
						"filename" => $filename,
						"folder" => $current_folder,
					);
				}
				
			// Compile event
			} else if ($compiled) {
				$stats[$filepath]['builds']++;
				
				// recursively update parents
				$parent = $filepath;
				while ($k = strrpos($parent, "/")) {
					$parent = substr($parent, 0, $k);
					$stats[$parent]['builds']++;
				}
				
				if ($last_event['text'] == "compiled successfully" && abs($last_event['time'] - $event_time) < 3) {
					// Just copy compiler output to previous event
					$last_event['output'] = `svn cat -r$rev $svn_cmd_path`;
					
				} else {
					// For now we don't know if compiling was successful
					$output = `svn cat -r$rev $svn_cmd_path`;
					array_push($stats[$filepath]['events'], array(
						"time" => $event_time,
						"text" => "compiled",
						"output" => $output,
						"rev" => $rev
					) );
				}
				
			// Successful compile
			} else if ($runned) {
				$stats[$filepath]['builds_succeeded']++;
				
				// recursively update parents
				$parent = $filepath;
				while ($k = strrpos($parent, "/")) {
					$parent = substr($parent, 0, $k);
					$stats[$parent]['builds_succeeded']++;
				}
				
				if ($last_event['text'] == "compiled" && abs($last_event['time'] - $event_time) < 3) {
					// If there is a compile event, we will just mark it as successful
					$last_event['text'] = "compiled successfully";
				
				} else {
					array_push($stats[$filepath]['events'], array(
						"time" => $event_time,
						"text" => "compiled successfully",
						"rev" => $rev
					) );
				}
				
			// Program was tested
			} else if ($tested) {
				$stats[$filepath]['testings']++;
				
				// recursively update parents
				$parent = $filepath;
				while ($k = strrpos($parent, "/")) {
					$parent = substr($parent, 0, $k);
					$stats[$parent]['testings']++;
				}
				
				// This is .at_result file, so get number of successful tests
				$testing_results = json_decode(`svn cat -r$rev $svn_cmd_path`, true);

				// Get total number of tests from .autotest file
				$svn_test_path = svn_cmd_escape( $svn_path . $filepath . "/.autotest" );
				$tests = json_decode(`svn cat -r$rev $svn_test_path`, true);

				$total_tests = count($tests['test_specifications']);
				$passed_tests = 0;
				if (is_array($testing_results) && array_key_exists("test_results", $testing_results) && is_array($testing_results['test_results'])) {
					foreach ($testing_results['test_results'] as $test) {
						if ($test['status'] == 1) $passed_tests++;
					}
				}
				$stats[$filepath]['last_test_results'] = "$passed_tests/$total_tests";
				
				array_push($stats[$filepath]['events'], array(
					"time" => $event_time,
					"text" => "ran tests",
					"test_results" => "$passed_tests/$total_tests"
				) );
				
			// Other file change
			} else if ($path['action'] != "A") {
				// Old vs. new diff format
				if (array_key_exists('stats_version', $stats[$filepath]) && $stats[$filepath]['stats_version'] == "2") {
					array_push($stats[$filepath]['events'], array(
						"time" => $event_time,
						"text" => "modified",
						"diff" => $diff_result,
					) );
				} else {
					array_push($stats[$filepath]['events'], array(
						"time" => $event_time,
						"text" => "modified",
						"diff" => $diff_result_old,
					) );
				}
			
			// SVN registered a create event for path that already exists in stats??
			// Add a "created" event
			} else if (!$created_path) {
				array_push($stats[$filepath]['events'], array(
					"time" => $event_time,
					"text" => "created",
					"filename" => $filename
				) );
			}
		}
	}
}

function update_stats($username) {
	global $username, $conf_svn_path, $stats, $prefix, $create_time, $svn_ignore, $time_break_limit, $skip_diff, $file_content_limit;
	
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
	$svn_log = svn_log($svn_path, SVN_REVISION_HEAD, $stats['last_update_rev'], 0, SVN_DISCOVER_CHANGED_PATHS);
	if (!$svn_log || empty($svn_log)) return;
	foreach($svn_log as &$entry)
		$entry['unixtime'] = strtotime($entry['date']);
	usort($svn_log, "svnsort");
	
	$event_time = $previous_event_time = 0;
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
			$previous_event_time = $event_time;
			$event_time = $entry['unixtime'];
			
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
				// Provjeravamo da li je prazan fajl
				$scpath = str_replace(" ", "%20", $svn_file_path);
				$content = trim(@svn_cat($scpath, $entry['rev']));
				if (!empty($content)) {
					$compiled = true;
					if (count($path_parts) > 1) {
						array_pop($path_parts);
						$filepath = substr($filepath, 0, strlen($filepath) - strlen("/.gcc.out"));
					}
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
					$scpath = str_replace(")", "\\)", $scpath);
					$scpath = str_replace("(", "\\(", $scpath);
					$diff_contents = `svn diff -r $scpath@$old_rev $scpath@$rev`;
					$diff_result = compressed_diff_old($diff_contents);
				}
			}
			
			// Praćenje ukupnog vremena rada
			if ($event_time - $previous_event_time < $time_break_limit)
				$task_time = $event_time - $previous_event_time;
			else
				$task_time = $time_break_limit; // ?? ispade da se više isplati raditi sporo?
			//print "$filepath last_time $event_time old_time $previous_event_time vrijeme_zadatka $task_time\n";

			// Rekurzivno ažuriramo sve nadfoldere
			$subpaths = array();
			foreach($path_parts as $part) {
				if (!empty($subpaths))
					$part = $subpaths[count($subpaths)-1] . "/$part";
				array_push($subpaths, $part);
			}
			
			// Kreiramo sve nadfoldere ako ne postoje
			$created_path = false;
			foreach($subpaths as $subpath) {
				// Ako nije ranije postojao put, kreiramo ga
				if (!array_key_exists($subpath, $stats)) {
					$stats[$subpath] = array(
						"total_time" => $create_time,
						"builds" => 0,
						"builds_succeeded" => 0,
						"testings" => 0,
						"last_test_results" => "",
						"events" => array(),
						"last_revision" => $entry['rev'],
						"entries" => array(),
					);
					//print "Kreiram novi node $subpath vrijeme $create_time\n";
					
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
							$created_path = true;
							
							// Ako je u pitanju kreiranje finalnog puta, nećemo povećavati vrijeme svih nadfoldera
							// Ovo se nažalost mora uraditi ovako jer su folderi složeni od viših ka nižim jer je to 
							// prirodan redoslijed kreiranja (ako nisu postojali ranije)
							foreach($subpaths as $subpath2) {
								if ($stats[$subpath2]['total_time'] > $create_time && $stats[$subpath2]['total_time'] > $task_time) {
									$stats[$subpath2]['total_time'] -= $task_time;
									//print "Smanjujem vrijeme za $subpath2 za $task_time\n";
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
					$created_path = true;
				} else {
					$stats[$subpath]['total_time'] += $task_time;
					//print "Povećavam vrijeme za $subpath za $task_time\n";
				}
			}
			
			// Ažuriramo entries
			$previous = "";
			foreach(array_reverse($subpaths) as $subpath) {
				if ($previous != "") {
					if (!array_key_exists("entries", $stats[$subpath]) || empty($stats[$subpath]['entries']))
						$stats[$subpath]['entries'] = array();
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
			} else if (!$created_path) {
				array_push($stats[$filepath]['events'], array(
					"time" => $entry['unixtime'],
					"text" => "created",
					"filename" => $filename
				) );
			}
		}
	}
}

// Function that converts unified diff format into something highly condensed that's good enough for us
function compressed_diff($diff_text) {
	$result = array( 'remove_lines' => array(), 'add_lines' => array() );
	$current_line = $added_line = $changed_line = $recently_removed = $recently_added = 0;
	$header = true;
	foreach(explode("\n", $diff_text) as $line) {
		// Skip header lines
		if ($header && starts_with($line, "Index: "))
			continue;
		if ($header && starts_with($line, "==========="))
			continue;
		if ($header && (starts_with($line, "--- ") || starts_with($line, "+++ ")))
			continue;
		$header = false;
		
		// Starting line numbers for removing and adding
		if (strlen($line) > 2 && substr($line, 0, 2) == "@@") {
			if (preg_match("/@@ \-(\d+)\,\d+ \+(\d+),\d+/", $line, $matches)) {
				$current_line = $changed_line = $matches[1];
				$added_line = $matches[2];
				continue;
			}
		}

		// Lines removed
		if (strlen($line) > 0 && $line[0] == '-') {
			if ($recently_removed)
				$recently_removed = 0; // We want only single-line removes
			else
				$recently_removed = $current_line;
			$result['remove_lines'][$current_line++] = substr($line,1);
			$changed_line++;
		}
		
		// Lines added
		else if (strlen($line) > 0 && $line[0] == '+') {
			if ($recently_added)
				$recently_removed = 0; // ...and single-line adds after them
			else if ($recently_removed) 
				$recently_added = $added_line;
			$result['add_lines'][$added_line++] = substr($line,1);
		} 
		
		// Context
		else {
			// Single-line remove + add = change
			if ($recently_added && $recently_removed) {
				$result['change'][$changed_line-1] = $result['add_lines'][$recently_added];
				//$result['before_change'][$changed_line-1] = $result['remove_lines'][$recently_removed];
				unset($result['remove_lines'][$recently_removed]);
				unset($result['add_lines'][$recently_added]);
			}
			$current_line++;
			$added_line++;
			$changed_line++;
			$recently_removed = $recently_added = 0;
		}
	}
	
	// Change in last line?
	if ($recently_added && $recently_removed)
		$result['change'][$changed_line-1] = $result['add_lines'][$recently_added];
	
	// Save space
	if (count($result['remove_lines']) == 0) unset($result['remove_lines']);
	if (count($result['add_lines']) == 0) unset($result['add_lines']);
	
	return $result;
}


function compressed_diff_old($diff_text) {
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
        } else if (count($result['add_lines']) > 0) {
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
		$fh = fopen($filename, "w");
		if ($fh) {
			if (fwrite($fh, $content)) {
				fclose($fh);
				return;
			} else
				print "fwrite failed $filename... ";
		} else print "Can't open $filename... ";
		//if (file_put_contents($filename, $content)) return;
		print "retry in $retry seconds\n";
		sleep($retry);
	}
}


function debug_log($msg) {
	$time = date("d. m. Y. H:i:s");
	`echo $time $msg >> /tmp/userstats.log`;
}


// Escape file path in a way that works with svn command line
function svn_cmd_escape($path) {
	$path = str_replace(" ", "\\ ", $path);
	$path = str_replace(")", "\\)", $path);
	$path = str_replace("(", "\\(", $path);
	return $path;
}


?>
