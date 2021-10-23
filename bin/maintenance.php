<?php

// =========================================
// MAINTENANCE.PHP
// C9@ETF project (c) 2015-2021
//
// Webide maintenance daemon
// =========================================



require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


// Some configuration values

$wait_period = 60; // Run at least every 5 minutes

$tasks_file = "$conf_base_path/data/maintenance_tasks";

// Limits for tasks
$limit_load_avg = 4;
$limit_users = 150;
$limit_tasks_in_one_pass = 1000;

$known_tasks = [ "clean_big_files", "update_stats", "git_update", "clean_inodes", "free_space", "clean_bfl", "fixsvn", "stop_node", "logout", "sync_remote", "update_usage" ];

$users = [];



// Check if maintenance is already running

$watchfile = $conf_base_path."/watch/maintenance";
if (file_exists($watchfile)) {
	$pid = trim(file_get_contents($watchfile));
	if (file_exists("/proc/$pid"))
		die("ERROR: maintenance already running $pid");
}
$mypid = getmypid();
exec("echo $mypid > $watchfile");


// Remove watchfile when forcibly terminated

declare(ticks = 1);
pcntl_signal(SIGINT, 'signalHandler');
pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGHUP, 'signalHandler');

$self_hash = md5(file_get_contents(__FILE__));



// Master loop

mlog("Starting maintenance");
while(true) {
	// Skip maintenance if load too big
	if (get_load() > $limit_load_avg) {
		mlog("Load " . get_load() . ", waiting $wait_period seconds");
		sleep($wait_period);
		continue;
	}
	
	// Skip maintenance if too many online users
	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));
	$online_users = 0;
	foreach($users as $user)
		if ($user['status'] == "active") $online_users++;
	if ($online_users > $limit_users) {
		mlog("$online_users users online, waiting $wait_period seconds");
		sleep($wait_period);
		continue;
	}
	
	// Check if maintenance.php is changed
	$new_hash = md5(file_get_contents(__FILE__));
	if ($new_hash != $self_hash) {
		mlog("maintenance.php changed, reloading");
		proc_close(proc_open("php " . __FILE__ . " 2>&1 &", array(), $foo));
		exit(0);
	}
	
	$count = 0; $now = time(); $nexttime = $now + $wait_period;
	$failed_tasks = [];
	if (file_exists($tasks_file)) foreach(file($tasks_file) as $taskdata) {
		$now = time();
		$nexttime = $now + $wait_period;
		$count++;
		
		if ($count > $limit_tasks_in_one_pass) {
			mlog("Done $count tasks, waiting $wait_period seconds");
			$nexttime = $now + $wait_period;
			break; // must add tasks to new_maintenance_tasks
		}
		
		// If high load, wait some more
		if (get_load() > $limit_load_avg) {
			mlog("Load " . get_load() . ", waiting $wait_period seconds");
			$nexttime = $now + $wait_period;
			break;
		}
		
		// Perform tasks in order
		$taskdata_ar = explode(",", trim($taskdata));
		if (count($taskdata_ar) < 2) continue;
		$task = $taskdata_ar[0]; $time = $taskdata_ar[1];
		if (count($taskdata_ar) > 2) $repeat = $taskdata_ar[2]; else $repeat = 0;
		
		if ($time > $now) {
			if ($time < $nexttime) $nexttime = $time;
			continue;
		}
		
		// Get task params
		$task_parts = explode(" ", $task);
		$task_name = array_shift($task_parts);
		$task_params = "";
		foreach ($task_parts as $part) {
			if ($task_params != "") $task_params .= ",";
			$task_params .= escape_for_eval($part);
		}
		
		if (in_array($task_name, $known_tasks)) {
			remove_task($task);
			$dependency = "";
			if (count($taskdata_ar) > 3) {
				$dependency = $taskdata_ar[2];
				if (in_array($dependency, $failed_tasks)) {
					mlog("Not running $task because of failed dependency $dependency");
					add_task($task, $now + $wait_period, $repeat, $dependency);
					$failed_tasks[] = $task;
					continue;
				}
			}
			
			$success = false;
			eval("\$success = $task_name($task_params);");
			if ($success) {
				if ($repeat > 0)
					add_task($task, $now + $repeat, $repeat, $dependency);
			}
			else {
				mlog("Failed: $task");
				add_task($task, $now + $wait_period, $repeat, $dependency);
				$failed_tasks[] = $task;
			}
		} else {
			mlog("Unknown task $task!");
			remove_task($task);
		}
	}
	
	sleep($nexttime - $now);
}




// --------------------
// MAINTENANCE TASKS
// --------------------


function fixsvn($username) {
	global $conf_base_path;
	$limit_load_avg_fixsvn = 2.5;
	if (get_load() > $limit_load_avg_fixsvn) {
		mlog("fixsvn($username): Load is " . get_load() . ", continuing");
		return false;
	}
	
	$login = false;
	$username_esa = escapeshellarg($username);
	
	if (!($workspace = get_workspace($username, "fixsvn")))
		return true;
	if (is_online($username)) {
		$last = last_access($username);
		if (time() - $last < 3600) {
			mlog("fixsvn($username): User is online");
			return false;
		}
		
		// He's away very long, we will temporarily log him out
		$login = true;
		mlog(shell_exec("$conf_base_path/bin/webidectl logout $username_esa 2>&1"), "detail");
		// Logout will add another fixsvn task, which we will remove
		remove_task("fixsvn $username");
	}
	
	mlog("fixsvn($username)");
	if ($login) {
		// Wait for fixsvn to start working
		proc_close(proc_open("sleep 100; $conf_base_path/bin/webidectl login $username_esa 2>&1 &", array(), $foo));
	}
	shell_exec("$conf_base_path/bin/fixsvn $username_esa 0 2>&1 >/dev/null");
	return true;
}


function update_stats($username) {
	global $conf_base_path;
	if (!($workspace = get_workspace($username, "update_stats")))
		return true;
	
	mlog("update_stats($username)");
	$username_esa = escapeshellarg($username);
	mlog(shell_exec("$conf_base_path/bin/userstats $username_esa 2>&1"), "detail");
	return true;
}


function clean_big_files($username) {
	if (!($workspace = get_workspace($username, "clean_big_files")))
		return true;
		
	mlog("clean_big_files($username)");
	$output = run_as($username, "cd $workspace; find . -name \"*core*\" -exec svn delete {} \; ; svn ci -m corovi .");
	$output .= run_as($username, "cd $workspace; find . -name \"*core*\" -delete");
	mlog($output, "detail");
	return true;
}


function git_update($username) {
	global $conf_base_path;
	if (!($workspace = get_workspace($username, "git_update")))
		return true;
	
	mlog("git_update($username)");
	if (!file_exists($workspace . "/.git")) {
		mlog("git_update($username): Creating git for user $username...", "detail");
		$username_esa = escapeshellarg($username);
		mlog(shell_exec("$conf_base_path/bin/webidectl git-init $username_esa"), "detail");
	}
	
	$msg = date("d.m.Y", time() - 60*60*24);
	mlog(run_as($username, "cd $workspace; git add --all .; git commit -m \"$msg\" . 2>&1"), "detail");
	return true;
}


function clean_inodes($username) {
	global $conf_max_user_inodes, $conf_max_user_svn_disk_usage, $conf_limit_diskspace, $conf_diskspace_cleanup, $conf_home_path;
	global $conf_base_path;
	global $now, $wait_period;
	global $users;
	
	$skip_users = array( );

	if (!($workspace = get_workspace($username, "clean_inodes")))
		return true;
	if (is_online($username)) {
		mlog("clean_inodes($username): User is online");
		add_task("update_stats $username", $now + $wait_period, 0);
		return false;
	}
	if (in_array($username, $skip_users)) {
		mlog("User $username in \$skip_users\n");
		return true;
	}
	if (is_lock("user $username")) {
		mlog("clean_inodes($username): User is locked");
		add_task("update_stats $username", $now + $wait_period, 0);
		return false;
	}
	$usage = disk_usage($conf_home_path);
	if ($usage < $conf_limit_diskspace * 1024) {
		mlog("clean_inodes($username): Disk space too low ($usage)");
		return false;
	}
	
	mlog("clean_inodes($username)");
	$username_esa = escapeshellarg($username);
	
	$total_usage_stats = get_usage_stats();
	$total_usage_stats[$username]['svn'] = false;
	$total_usage_stats[$username]['inodes'] = false;
	$total_usage_stats[$username]['ws.old'] = false;
	$total_usage_stats[$username]['svn.old'] = false;
	$total_usage_stats[$username]['old.inodes'] = false;
	
	// Prevent user from logging in below this point
	bfl_lock("user $username");
	
	$usersvn = setup_paths($username)['svn'];
	$lastver = `svnversion $workspace`;
	$nochange = false;
	if ($lastver === "1" || $lastver === "1\n") {
		$nochange = true;
	}
	
	$do_reinstall = false;
	if (!$nochange && $users[$username]["status"] != "active" && $conf_max_user_inodes > 0) {
		$total_usage_stats[$username]['inodes'] = intval(shell_exec("find $usersvn | wc -l"));
		if ($total_usage_stats[$username]['inodes'] > $conf_max_user_inodes) {
			mlog("$username - inodes ".$total_usage_stats[$username]['inodes']." > $conf_max_user_inodes", "detail");
			$do_reinstall = true;
		}
	}
	if (!$do_reinstall && !$nochange && $users[$username]["status"] != "active" && $conf_max_user_svn_disk_usage > 0) {
		$total_usage_stats[$username]['svn'] = intval(shell_exec("du -s $usersvn"));
		if ($total_usage_stats[$username]['svn'] > $conf_max_user_svn_disk_usage) {
			mlog("$username - svn disk usage ".$total_usage_stats[$username]['svn']." kB > $conf_max_user_svn_disk_usage kB", "detail");
			$do_reinstall = true;
		}
	}
	
	if ($do_reinstall) {
		$total_usage_stats[$username]['old.inodes'] = $total_usage_stats[$username]['inodes'];
		if ($total_usage_stats[$username]['svn'])
			$total_usage_stats[$username]['svn.old'] = $total_usage_stats[$username]['svn'];
		mlog(" - resetting svn", "detail");
		mlog("Update stats", "detail");
		// If
		$output = array();
		exec("$conf_base_path/bin/userstats $username_esa 2>&1", $output, $return_value);
		$output = join("\n", $output);
		mlog($output, "detail");
		
		if ($return_value != 0 || strstr($output, "Segmentation") || strstr($output, "FATAL")) {
			mlog("Userstats failed for $username!", "detail");
		} else {
			mlog("Reinstall svn", "detail");
			bfl_unlock("user $username"); // We must unlock here because user-reinstall-svn has own lock
			exec("$conf_base_path/bin/webidectl user-reinstall-svn $username_esa 2>&1", $output, $return_value);
			bfl_lock("user $username");
			$output = join("\n", $output);
			mlog($output, "detail");
		}
		$total_usage_stats[$username]['svn'] = intval(shell_exec("du -s $usersvn"));
		$total_usage_stats[$username]['ws'] = intval(shell_exec("du -s " . setup_paths($username)['workspace']));
		$total_usage_stats[$username]['last_update'] = time();
	
		// Inode statistics update (TODO)
	}

	$user_tmp = setup_paths($username)['home'] . "/tmp";
	if (file_exists($user_tmp) && count(scandir($user_tmp)) > 2) {
		mlog("clean_inodes($username): emptying tmp", "detail");
		`rm -fr $user_tmp/*`;
	}
	
	bfl_unlock("user $username");
		
	// We have just changed user data?
	if (array_key_exists("volatile-remote", $users[$username]))
		add_task("sync_remote $username", $now , 0);
	
	// Prepare some per-user usage statistics
	$user_ws_backup = setup_paths($username)['workspace'] . ".old";
	if (file_exists($user_ws_backup))
		$total_usage_stats[$username]['ws.old'] = intval(shell_exec("du -s $user_ws_backup"));
	$user_svn_backup = setup_paths($username)['svn'] . ".old";
	if (file_exists($user_svn_backup) && !$total_usage_stats[$username]['svn.old'])
		$total_usage_stats[$username]['svn.old'] = intval(shell_exec("du -s $user_svn_backup"));
	update_usage_stats($total_usage_stats);
	return true;
}


function sync_remote($username) {
	global $users, $conf_base_path;
	if (!($workspace = get_workspace($username, "sync_remote")))
		return true;
	if (!array_key_exists("volatile-remote", $users[$username])) {
		mlog("sync_remote($username): User not volatile remote", "detail");
		return true;
	}
	
	mlog("sync_remote($username)");
	$username_esa = escapeshellarg($username);
	mlog(shell_exec("$conf_base_path/bin/webidectl sync-remote $username_esa"), "detail");
	return true;
}


function free_space() {
	global $conf_diskspace_cleanup, $conf_home_path, $users, $limit_load_avg;
	$min_backup_for_erase = 30000;
	
	$usage = disk_usage($conf_home_path);
	if ($conf_diskspace_cleanup <= 0 || $usage >= $conf_diskspace_cleanup * 1024)
		return true;
	
	mlog("free_space: $usage");
	
	// shuffle_assoc
	$keys = array_keys($users);
	shuffle($keys);
	$users_shuffled = array();
	foreach ($keys as $key)
		$users_shuffled[$key] = $users[$key];
		
	foreach ($users_shuffled as $rand_user => $options) {
		if (trim($rand_user) == "") continue;
		if ($conf_diskspace_cleanup <= 0 || $usage > $conf_diskspace_cleanup * 1024) break;
		if (get_load() > $limit_load_avg) {
			mlog("free_space: Load too big, breaking");
			break;
		}

		$total_usage_stats = get_usage_stats();
		
		$user_ws_backup = setup_paths($rand_user)['workspace'] . ".old";
		$cleaned = false;
		if (file_exists($user_ws_backup)) {
			if (array_key_exists($rand_user, $total_usage_stats) && array_key_exists('ws.old', $total_usage_stats[$rand_user]))
				$backup_size = $total_usage_stats[$rand_user]['ws.old'];
			else {
				$total_usage_stats[$rand_user]['ws.old'] = intval(shell_exec("du -s $user_ws_backup"));
				$backup_size = $total_usage_stats[$rand_user]['ws.old'];
			}
			
			mlog("free_space($rand_user): UWB $user_ws_backup Size $backup_size", "detail");
			if ($backup_size > $min_backup_for_erase) {
				mlog("free_space($rand_user)");
				`rm -fr $user_ws_backup`;
				$cleaned = true;
				$total_usage_stats[$rand_user]['ws.old'] = 0;
			}
		} else
			mlog("free_space($rand_user): UWB $user_ws_backup not exists", "detail");
			
		$user_svn_backup = setup_paths($rand_user)['svn'] . ".old";
		if (file_exists($user_svn_backup)) {
			if (array_key_exists($rand_user, $total_usage_stats) && array_key_exists('svn.old', $total_usage_stats[$rand_user]))
				$backup_size = $total_usage_stats[$rand_user]['svn.old'];
			else {
				$total_usage_stats[$rand_user]['svn.old'] = intval(shell_exec("du -s $user_svn_backup"));
				$backup_size = $total_usage_stats[$rand_user]['svn.old'];
			}
			
			mlog("free_space($rand_user): USB $user_svn_backup Size $backup_size", "detail");
			if ($backup_size > $min_backup_for_erase) {
				if (!$cleaned) mlog("free_space($rand_user)");
				`rm -fr $user_svn_backup`;
				$cleaned = true;
				$total_usage_stats[$rand_user]['svn.old'] = 0;
			}
		} else
			mlog("free_space($rand_user): USB $user_svn_backup not exists", "detail");
			
		update_usage_stats($total_usage_stats);
		
		if ($cleaned) $usage = disk_usage($conf_home_path);
	}
	return true;
}


// Remove invalid items from BFL file
function clean_bfl() {
	$bfl_file = "/tmp/webide.bfl";
	$new_lock_data = "";
	mlog("clean_bfl()");
	
	foreach(file($bfl_file) as $m_lock) {
		$m_lock_parts = explode(",", trim($m_lock));
		if (count($m_lock_parts) == 2 && file_exists("/proc/".$m_lock_parts[1]))
			$new_lock_data .= $m_lock;
		else
			mlog("clean_bfl(): removing invalid entry ".trim($m_lock), "detail");
	}
	file_put_contents($bfl_file, $new_lock_data, LOCK_EX);
	return true;
}


function stop_node($username) {
	global $conf_base_path;
	if (!($workspace = get_workspace($username, "fixsvn")))
		return true;
	
	mlog("stop_node($username)");
	$username_esa = escapeshellarg($username);
	mlog(shell_exec("$conf_base_path/bin/webidectl stop-node $username_esa"), "detail");
	return true;
}


function logout($username) {
	global $conf_base_path;
	if (!($workspace = get_workspace($username, "logout")))
		return true;
	
	mlog("logout($username)");
	$username_esa = escapeshellarg($username);
	mlog(shell_exec("$conf_base_path/bin/webidectl logout $username_esa"), "detail");
	return true;
}


// Calculate workspace usage (other stats will be gathered by clean_inodes)
function update_usage() {
	global $conf_base_path, $users;
	$stats_update_time = 60*60*24;
	$limit_load_avg_usage = 2.5;
	$max_users_to_update = 100;
	
	mlog("update_usage");
	
	// shuffle_assoc
	$keys = array_keys($users);
	shuffle($keys);
	$users_shuffled = array();
	foreach ($keys as $key)
		$users_shuffled[$key] = $users[$key];
		
	$count = 0;
	foreach ($users_shuffled as $rand_user => $options) {
		if (trim($rand_user) == "") continue;
		
		if (get_load() > $limit_load_avg_usage) {
			mlog("update_usage: Load too big, breaking");
			break;
		}
		
		$total_usage_stats = get_usage_stats();
		if (array_key_exists($rand_user, $total_usage_stats) && array_key_exists('last_update', $total_usage_stats[$rand_user])
			&& time() - $total_usage_stats[$rand_user]['last_update'] < $stats_update_time) continue;
		
		$userdata = setup_paths($rand_user);
		$workspace = $userdata['home'];
		if (!file_exists($workspace)) continue;
		
		mlog("update_usage($rand_user)");
		
		$total_usage_stats[$rand_user]['last_update'] = time();
		$total_usage_stats[$rand_user]['ws'] = intval(shell_exec("du -s $workspace"));
		if (file_exists($workspace . "/workspace.old"))
			if (!array_key_exists('ws.old', $total_usage_stats[$rand_user]) || !$total_usage_stats[$rand_user]['ws.old'])
				$total_usage_stats[$rand_user]['ws.old'] = intval(shell_exec("du -s $workspace" . "/workspace.old"));
		update_usage_stats($total_usage_stats);
		$count++;
		if ($count >= $max_users_to_update) break;
	}
	return true;
}


// --------------------
// HELPER FUNCTIONS
// --------------------


function get_workspace($username, $action) {
	$userdata = setup_paths($username);
	$username_esa = $userdata['esa'];
	$workspace = $userdata['workspace'];
	if (!file_exists($workspace)) {
		mlog("$action($username): Workspace doesn't exist", "detail");
		return false;
	}
	
	$last = last_access($username);
	if (time() - $last > 24*60*60) {
		mlog("$action($username): Inactive for >24h, skipping.", "detail");
		return false;
	}
	return $workspace;
}

function mlog($msg, $log = "base") {
	global $conf_base_path;
	if (empty(trim($msg))) return;
	$log_path = $conf_base_path . "/log/maintenance.log";
	if ($log == "detail") $log_path = $conf_base_path . "/log/maintenance_detail.log";
	$time = date("d. m. Y. H:i:s");
	file_put_contents($log_path, "$time $msg\n", FILE_APPEND);
}


function escape_for_eval($string) {
	return "'" . addslashes($string) . "'";
}

function is_online($username) {
	global $users, $conf_base_path;
	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));
	return ($users[$username]["status"] == "active");
}

function remove_task($task) {
	global $tasks_file;
	$new_tasks = file($tasks_file);
	foreach ($new_tasks as $key => $taskdata) {
		$taskdata_ar = explode(",", trim($taskdata));
		if ($taskdata_ar[0] == $task)
			unset($new_tasks[$key]);
	}
	file_put_contents($tasks_file, join("", $new_tasks));
}

function add_task($task, $time, $repeat, $dependency = "") {
	global $tasks_file;
	if (!empty($dependency))
		file_put_contents($tasks_file, "$task,$time,$repeat,$dependency\n", FILE_APPEND);
	else if ($repeat > 0)
		file_put_contents($tasks_file, "$task,$time,$repeat\n", FILE_APPEND);
	else
		file_put_contents($tasks_file, "$task,$time\n", FILE_APPEND);
}

function get_usage_stats() {
	global $conf_base_path;
	$usage_stats_path = $conf_base_path . "/data/usage_stats";
	$total_usage_stats = json_decode(file_get_contents($usage_stats_path), true);
	if (!is_array($total_usage_stats)) $total_usage_stats = [];
	return $total_usage_stats;
}

function update_usage_stats($total_usage_stats) {
	global $conf_base_path;
	$usage_stats_path = $conf_base_path . "/data/usage_stats";
	file_put_contents($usage_stats_path, json_encode($total_usage_stats, JSON_PRETTY_PRINT));
}

function get_load() {
	global $conf_base_path;
	$stats = explode(" ", trim(`tail -1 $conf_base_path/server_stats.log`));
	return $stats[2];
}


// --------------------
// COPIED FROM WEBIDECTL (move to webidelib?)
// --------------------


// Time of last activity from user
function last_access($username) {
	global $conf_base_path, $conf_home_path, $is_svn_node, $is_control_node, $svn_node_addr;
	
	$userdata = setup_paths($username);
	
	$last_path = $conf_home_path . "/last/" . $userdata['efn'] . ".last";
	if (file_exists($last_path)) return intval(file_get_contents($last_path));

	// No last file, check svn data
	if (!file_exists($userdata['workspace'])) return 0;
	if ($is_svn_node) {
		run_as($username, "cd " . $userdata['workspace'] . "; svn update");
		$svn_output = shell_exec("cd " . $userdata['workspace'] . "; svn log -l 1 | cut -d \" \" -f 5-6");
		foreach(explode("\n", $svn_output) as $svn_line) {
			$time = strtotime($svn_line);
			if ($time > 0) {
				file_put_contents($time, $last_path);
				return $time; 
			}
		}
	} else if ($is_control_node) {
		$last = run_on($svn_node_addr, "$conf_base_path/bin/webidectl last " . $userdata['esa']);
		file_put_contents($last, $last_path);
		return intval($last);
	}
	
	// This is too slow!
	//$time = exec("find $student_workspace -type f -printf '%T@\n' | sort -n | tail -1"); // Timestamp of last modified file
	//return $time;;
	return 0;
}


// Read users file
function read_files() {
	global $conf_base_path, $users;
	
	
	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));
}


// Usage of disk partition where path is located
function disk_usage($path) {
	$path_match = "";
	$usage = -1;
	foreach(explode("\n", shell_exec("df")) as $line) {
		$parts = preg_split("/\s+/", $line);
		if (count($parts) < 6) continue;
		if (starts_with($parts[5], $path) && strlen($parts[5]) > strlen($path_match)) {
			$usage = $parts[3];
			$path_match = $parts[5];
		}
	}
	return $usage;
}

function signalHandler($signo) {
	global $watchfile;
	$type = "Uknown signal $signo";
	if ($signo == SIGINT) $type = "Interrupt";
	if ($signo == SIGTERM) $type = "Shutdown";
	if ($signo == SIGHUP) $type = "Hang-up (shell is closed)";
	mlog("Received signal: $type, ending");
	print "Terminated by signal: $type\n";
	
	unlink($watchfile);
	exit(0);
}



// --------------------
// BIG FUCKN LOCK (BFL)
// --------------------

function is_lock($lock = "all") {
	$bfl_file = "/tmp/webide.bfl";
	foreach(file($bfl_file) as $m_lock)
		if (explode(",", trim($m_lock))[0] === $lock) return true;
	return false;
}

function bfl_lock($lock = "all", $take_lock = true) {
	global $action, $mypid;
	
	$bfl_file = "/tmp/webide.bfl";
	$wait = 100000; // Initially wait 0.1s
	$wait_inc = 100000; // Every time increase interval by 0.1s
	$wait_add = $wait_inc;
	$ultimate_limit = 100000000; // Break in after 100s

	while (is_lock($lock)) {
		mlog("$action ceka na bfl $lock pid ".getmypid());
		print "Čekam na bfl - ak\n";
		usleep($wait);
		$wait += $wait_add;
		$wait_add += $wait_inc;
		//if ($wait >= $ultimate_limit) break;
	}
	
	if ($take_lock) {
//		debug_log("$action stavlja lock $lock pid ".getmypid());
		file_put_contents($bfl_file, "$lock,$mypid\n", FILE_APPEND | LOCK_EX);
	}
}

function bfl_unlock($lock = "all") {
	global $action;
	
	$bfl_file = "/tmp/webide.bfl";
	$new_locks = "";
//	debug_log("$action unlock $lock pid ".getmypid());
	foreach(file($bfl_file) as $m_lock)
		if (explode(",", trim($m_lock))[0] !== $lock) $new_locks .= $m_lock;
	file_put_contents($bfl_file, $new_locks);
}
