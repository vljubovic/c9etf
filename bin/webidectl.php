<?php

// =========================================
// WEBIDECTL.PHP
// C9@ETF project (c) 2015-2021
//
// Master control script
// =========================================



# Run as root

require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


// Scan parameters
if ($argc == 1) 
	die("ERROR: webidectl.php expects at least one argument\n");
$action = trim($argv[1]);
if ($argc > 2 && $action != "broadcast") $user = trim($argv[2]); else $user = "";


// Prevent running the same command many times
$watchfile = $conf_base_path."/watch/webidectl.$action.$user";
if (file_exists($watchfile) && $action != "server-stats") {
	$pid = trim(file_get_contents($watchfile));
	if (file_exists("/proc/$pid"))
		die("ERROR: webidectl already running $pid");
}
$mypid = getmypid();
exec("echo $mypid > $watchfile");


srand(make_seed());


$users = array();
read_files();

// Second param is always username
if ($argc>2) {
	$username = trim($argv[2]); 
	$username = preg_replace("/\s/", "", $username); // No whitespace in username
	$userdata = setup_paths($username);
}

detect_node_type();


// ACTIONS

switch($action) {
	case "hello":
		print "Hello world!\n\n";
		debug_log ("hello world");
		break;
	
	// Change user data
	case "change-user":
		if ($argc != 5) 
			print "ERROR: Wrong number of parameters.\n";
		else if (!array_key_exists($username, $users))
			print "ERROR: Unknown user $username.\n";
		else {
			$key = $argv[3];
			$value = $argv[4];
			
			bfl_lock("user $username");
			bfl_lock("users file");
			read_files();
			if ($value === "-")
				unset($users[$username][$key]);
			else
				$users[$username][$key] = $value;
			write_files();
			bfl_unlock("users file");
			
			if ($is_control_node) {
				foreach($conf_nodes as $node) {
					if (!is_local($node['address']))
						run_on($node['address'], "$conf_base_path/bin/webidectl change-user " . $userdata['esa'] . " " . escapeshellarg($key) . " " . escapeshellarg($value));
				}
			}
			bfl_unlock("user $username");
			debug_log ("change user $username $key");
		}
		break;
	
	// Input password from stdin and update web password file for user
	case "set-password":
		print "Password: ";
		$password = fgets(STDIN);
		set_password($username, $password);
		break;
	
	// Login or create user
	case "login":
		$ip_address = $argv[3];

		if (array_key_exists($username, $users)) {
			if ($users[$username]["status"] == "active") {
				debug_log ("already logged in $username (".$users[$username]["server"]." ".$users[$username]["port"].")");
				print $users[$username]["port"]; // Already logged in, print port
				`date +%s > /tmp/already-$username`;
			} else if ($users[$username]["status"] == "inactive") {
				activate_user($username, $ip_address);
				if (file_exists("/tmp/already-$username")) unlink("/tmp/already-$username");
			}
		}
		else {
			create_user($username);
			activate_user($username, $ip_address);
		}
		break;
	
	// Create user
	case "add-user":
		if ($argc < 3) 
			print "ERROR: Wrong number of parameters.\n";
		else {
			if (array_key_exists($username, $users))
				print "ERROR: User $username already exists\n";
			else {
				create_user($username);
				if ($argc>3) {
					$password = $argv[3];
					set_password($username, $password);
					
					bfl_lock("users file");
					read_files();
					$users[$username]['realname'] = $argv[4];
					if ($argc>4) $users[$username]['email'] = $argv[5];
					write_files();
					bfl_unlock("users file");
				}
			}
		}
		break;
	
	// Logout user
	case "logout":
		// There is no reason for logout if clear-server is currently active
		if (file_exists($conf_base_path."/watch/webidectl.clear-server."))
			break;
		
		if ($users[$username]["status"] == "inactive") // Already logged out
			break;
		
		$wait = 0;
		if ($argc > 3)
			$wait = intval($argv[3]);
		if ($wait>0) {
			sleep($wait);
			if (file_exists("/tmp/already-$username")) {
				$time = file_get_contents("/tmp/already-$username");
				if ($time+$wait > time()) {
					break;
				}
			}
		}
		read_files();
		deactivate_user($username); // Force all logout operations, even if we list user as not logged in
		unlink("/tmp/already-$username");
		break;

	// Just stop node
	case "stop-node":
		bfl_lock("user $username");
		stop_node($username, false);
		bfl_unlock("user $username");
		break;

	// Move user to another server
	case "kick-user":
		kick_user($username);
		break;

	// Remove user from system
	case "remove":
		if (array_key_exists($username, $users)) {
			if ($users[$username]["status"] == "active")
				deactivate_user($username);
			remove_user($username);
		}
		break;

	// Add a user with local authentication (htpasswd)
	case "add-local-user":
		if ($argc != 4) 
			print "ERROR: Wrong number of parameters.\n";
		else {
			$password_esa = escapeshellarg($argv[3]);
			// This is a different path from $userdata['htpasswd'] !
			$htpasswd = $conf_base_path . "/localusers/" . $userdata['efn'];

			bfl_lock("user $username");
			exec("htpasswd -bc $htpasswd " . $userdata['esa'] . " $password_esa 2>&1");
			exec("chown $conf_nginx_user $htpasswd");
			bfl_unlock("user $username");
			debug_log ("add local user $username");
			print "Created local user $username\n";
		}
		break;
	
	// Regular cleanup operation every hour
	case "culling":
		// FIXME This is done only on control server
		// If memory is high on control it's probably high on all others, due to load balancing
		$active_users = 0;
		foreach($users as $user) if ($user['status'] == "active") $active_users++;
		if ($active_users > $conf_max_users_culling) break; // It just manufactures more load... :(
		
		$stats = server_stats();
		$memlimit = $conf_memory_emergency * 1024 * 1024;
		if ($stats[1] > $memlimit) {
			kill_idle(15, false, 0);
		} else {
			kill_idle(45, false, 5);
		}
		kill_idle_logout(120, false, 5);
		break;
	
	// Logout users who are inactive for over X minutes
	case "kill-idle":
		$minutes = $argv[2]; // users inactive for >$minutes will be killed
		$type = "soft"; // "soft" = just kill nodejs instances, "hard" = logout users
		$sleep = 5; // number of seconds to sleep between kills
		
		if ($argc>3) $type = $argv[3];
		if ($argc>4) $sleep = $argv[4];
		
		if ($type == "hard") 
			kill_idle_logout($minutes, true, $sleep); // $output=true
		else
			kill_idle($minutes, true, $sleep);
		break;
	
	// Purge node servers for logged out users
	case "kill-inactive":
		$sleep = 0;
		foreach($conf_nodes as $node) {
			if (in_array("svn", $node['type'])) {
				foreach(ps_ax($node['address']) as $process) {
					if (!strstr($process['cmd'], "syncsvn.php"))
						continue;
						
					// User possibly logged out in the meantime
					$username = $process['user'];
					if ($users[$username]['status'] == "inactive") {
						print "User $username inactive (syncsvn)\n";
						if (is_local($node['address']))
							exec("kill " . $process['pid']);
						else
							run_on($node['address'], "kill " . $process['pid']);
					}
					if ($sleep > 0) {
						sleep($sleep);
						read_files();
					}
				}
			}
			
			if (!in_array("compute", $node['type'])) continue;
			
			read_files();
			
			foreach(ps_ax($node['address']) as $process) {
				if (!strstr($process['cmd'], "node ") && !strstr($process['cmd'], "nodejs "))
					continue;
					
				// User possibly logged out in the meantime
				$username = $process['user'];
				if ($users[$username]['status'] == "inactive") {
					print "User $username inactive\n";
					if (is_local($node['address']))
						stop_node($username, false);
					else
						run_on($node['address'], "$conf_base_path/bin/webidectl stop-node " . escapeshellarg($username));
				}
				
				else if ($users[$username]['server'] != $node['address']) {
					print "User $username on wrong server\n";
					if (is_local($node['address']))
						stop_node($username, false);
					else
						run_on($node['address'], "$conf_base_path/bin/webidectl stop-node " . escapeshellarg($username));
				}
				if ($sleep > 0) {
					sleep($sleep);
					read_files();
				}
			}
			read_files();
		}
		break;
	
	// Time of last activity for user
	case "last":
		if ($argc > 3 && $argv[3] == "nice")
			print date("d.m.Y H:i:s\n", last_access($username));
		else
			print last_access($username);
		break;
	
	// Reset users configuration (if broken)
	case "reset-config":
		if (array_key_exists($username, $users)) {
			if ($users[$username]["status"] == "inactive") {
				bfl_lock("user $username");
				reset_config($username);
				bfl_unlock("user $username");
			} else
				print "ERROR: User $username logged in\n";
		} else
			print "ERROR: User $username doesn't exist\n";
		break;
	
	// Reinitialize git repo if exists
	case "git-init":
		if (array_key_exists($username, $users)) {
			if ($is_storage_node) {
				bfl_lock("user $username");
				git_init($username);
				bfl_unlock("user $username");
			} else
				run_on($storage_node_addr, "$conf_base_path/bin/webidectl git-init " . $userdata['esa']);
		} else
			print "ERROR: User doesn't exist\n";
		break;

	// Rewrite nginx config and restart (useful in case of corruption)
	case "reset-nginx":
		bfl_lock("users file");
		read_files();
		if ($is_control_node)
			write_nginx_config();
		bfl_unlock("users file");
		break;

	// Restart services for a logged-in user (if neccessary)
	case "verify-user":
		verify_user($username);
		break;

	// Just start syncsvn
	case "syncsvn":
		bfl_lock("user $username");
		if ($is_svn_node)
			syncsvn($username);
		bfl_unlock("user $username");
		break;

	// Same for all users
	case "verify-all-users":
		foreach ($users as $username => $options) {
			if ($options['status'] != "active") continue;
			print "verify $username\n";
			verify_user($username);
		}
		break;

	// List currently logged-in users
	case "list-active":
		foreach ($users as $username => $options) {
			if ($options['status'] != "active") continue;
			$nice_period = round( (time() - last_access($username) ) / 60 , 2 );
			print "$username $nice_period\n";
		}
		break;
	
	// Test if user is online and give last time of access
	case "is-online":
		if ($users[$username]['status'] == "active") print "YES\n"; else print "NO\n";
		$last = last_access($username);
		$last_date_time = date ("d. m. Y H:i:s", last_access($username));
		$last = time() - $last;
		if ($last < 60) $last = "$last seconds ($last_date_time)";
		else if ($last < 60*60) $last = round($last/60, 2) . " minutes ($last_date_time)";
		else if ($last < 24*60*60) $last = round($last/3600, 2) . " hours ($last_date_time)";
		else $last = $last_date_time;
		print "Last access: $last\n";
		break;
	
	// Logout all users and kill all processes that look related
	case "clear-server":
		clear_server();
		break;

	// Update stats for a user or a list of users (listed in a plaintext file)
	// TODO: never used - REMOVE?
	case "update-stats":
		// Single user
		if (array_key_exists($username, $users)) {
			if ($is_svn_node) {
				bfl_lock("user $username");
				exec("$conf_base_path/bin/userstats " . $userdata['esa']);
				bfl_unlock("user $username");
			}
			else if ($is_control_node)
				run_on($svn_node_addr, "$conf_base_path/bin/userstats " . $userdata['esa']);
			break;
		}
			
		// Here second param is actually a file with list of users for statistics update
		$flist = $argv[2];
		$tmplist = array();
		foreach(file($flist) as $stats_user) {
			if (!array_key_exists($stats_user, $users))
				continue; // Skip unknown user
			$username_esa = escapeshellarg($stats_user);
			if ($is_svn_node) {
				bfl_lock("user $stats_user");
				exec("$conf_base_path/bin/userstats $username_esa");
				bfl_unlock("user $stats_user");
			}
			else if ($is_control_node)
				run_on($svn_node_addr, "$conf_base_path/bin/userstats $username_esa");
		}
		break;

	// Update SVN stats for all users (quite long)
	// TODO: never used - REMOVE?
	case "update-all-stats":
		foreach ($users as $username => $options) {
			// We can safely update stats for logged-in users
			print "User $username\n";
			$username_esa = escapeshellarg($username);
			if ($is_svn_node) {
				bfl_lock("user $username_esa");
				exec("$conf_base_path/bin/userstats $username_esa");
				bfl_unlock("user $username");
			}
			else if ($is_control_node)
				run_on($svn_node_addr, "$conf_base_path/bin/userstats $username_esa");
		}
		break;

	// Commit all changed files to git for all users
	case "git-commit":
		if ($is_storage_node)
			foreach($users as $username => $options) {
				print "$username:\n";
				$msg = date("d.m.Y", time() - 60*60*24);
				$workspace = setup_paths($username)['workspace'];
				if (!file_exists($workspace)) {
					print "Workspace not found for $username...\n";
					continue;
				}
				bfl_lock("user $username");
				if (!file_exists($workspace . "/.git")) {
					print "Creating git for user $username...\n";
					git_init($username);
				}
				run_as($username, "cd $workspace; git add --all .; git commit -m \"$msg\" .");
				bfl_unlock("user $username");
			}
		else
			run_on($storage_node_addr, "$conf_base_path/bin/webidectl git-commit");
		break;

	// (Attempt to) fix SVN conflicts for all users
	// TODO: never used directly - REMOVE?
	case "fix-svn":
		if ($is_svn_node) {
			foreach($users as $username => $options) {
				bfl_lock("user $username");
				fixsvn($username);
				bfl_unlock("user $username");
				sleep(5);
			}
		}
		break;
	
	// Create a new SVN repo for user and install into folder - existing repo shall be dropped
	case "user-reinstall-svn":
		if (!array_key_exists($username, $users))
			print "ERROR: Unknown user\n";
		else if ($users[$username]["status"] == "active")
			print "ERROR: User is online\n";
		else if (!$is_svn_node)
			run_on($svn_node_addr, "$conf_base_path/bin/webidectl user-reinstall-svn " . $userdata['esa']);
		else {
			bfl_lock("user $username");
			user_reinstall_svn($username);
			bfl_unlock("user $username");
		}
		break;
	
	// Update stats, delete SVN repo, call user_reinstall_svn
	case "user-reset-svn":
		if (!array_key_exists($username, $users))
			print "ERROR: Unknown user\n";
		else if ($users[$username]["status"] == "active")
			print "ERROR: User is online\n";
		else if (!file_exists($userdata['workspace']))
			print "ERROR: User workspace doesn't exist\n";
		else if (!$is_svn_node)
			run_on($svn_node_addr, "$conf_base_path/bin/webidectl user-reset-svn " . $userdata['esa']);
		else {
			print "Update stats\n";
			bfl_lock("user $username");
			$output = array();
			exec("$conf_base_path/bin/userstats " . $userdata['esa'] . " 2>&1", $output, $return_value);
			$output = join("\n", $output);
			if ($return_value != 0 || strstr($output, "Segmentation") || strstr($output, "FATAL")) {
				print "Userstats failed for $username!\n";
			} else {
				print "Reinstall svn\n";
				user_reinstall_svn($username);
			}
			bfl_unlock("user $username");
		}
		break;
	
	// Call user_reset_svn if inode count goes big (which can be a huge problem if all inodes are used)
	// TODO: moved to maintenance - REMOVE?
	case "clean-inodes":
		if (!$is_svn_node) {
			run_on($svn_node_addr, "$conf_base_path/bin/webidectl clean-inodes");
			break;
		}
		ksort($users);
		$total = count($users);
		$current=1;
		foreach($users as $username => $options) {
			print "$username ($current/$total)\n";
			$current++;
			if ($users[$username]["status"] == "active") { // User logged-in in meantime
				print "User $username is online\n";
				continue;
			}
			
			$usersvn = setup_paths($username)['svn'];
			$workspace = setup_paths($username)['workspace'];
			if (!file_exists($workspace)) {
				print "-- workspace doesn't exist\n";
				continue;
			}
			$lastver = `svnversion $workspace`;
			if ($lastver === "1" || $lastver === "1\n") {
				print "-- nothing changed, skipping\n";
				continue;
			}
			
			$do_reinstall = false;
			if ($conf_max_user_inodes > 0) {
				$number = intval(shell_exec("find $usersvn | wc -l"));
				if ($number > $conf_max_user_inodes) {
					print "$username - inodes $number > $conf_max_user_inodes";
					$do_reinstall = true;
				}
			}
			if (!$do_reinstall && $conf_max_user_svn_disk_usage > 0) {
				$number = intval(shell_exec("du -s $usersvn"));
				if ($number > $conf_max_user_svn_disk_usage) {
					print "$username - svn disk usage $number kB > $conf_max_user_svn_disk_usage kB";
					$do_reinstall = true;
				}
			}
			
			if ($do_reinstall) {
				print " - resetting svn\n";
				print "Update stats\n";
				bfl_lock("user $username");
				$output = array();
				exec("$conf_base_path/bin/userstats " . $userdata['esa'] . " 2>&1", $output, $return_value);
				$output = join("\n", $output);
				if ($return_value != 0 || strstr($output, "Segmentation") || strstr($output, "FATAL")) {
					print "Userstats failed for $username!\n";
				} else {
					print "Reinstall svn\n";
					user_reinstall_svn($username);
				}
				bfl_unlock("user $username");
			
				// Inode statistics update (TODO)
				
				// Release lock for 5 seconds so users can do stuff
				sleep(5);
				read_files();
			}
		}
		break;

	// Start collaboration between current user and partner
	case "collaborate":
		// Second param is username
		$partner = $argv[3];
		if (!array_key_exists($username, $users))
			print "ERROR: Unknown user $username\n";
		else if (!array_key_exists($partner, $users))
			print "ERROR: Unknown user $partner\n";
		/*else if ($users[$username]["status"] == "active")
			print "ERROR: User $username is online\n";*/
		else if ($users[$partner]["status"] == "inactive")
			print "ERROR: User $partner is not online\n";
		else {
			bfl_lock("user $username");
			bfl_lock("users file");
			if (!array_key_exists("collaborate", $users[$username]))
				$users[$username]["collaborate"] = array($partner);
			else if (!in_array($partner, $users[$username]["collaborate"]))
				$users[$username]["collaborate"][] = $partner;
			write_files();
			if ($is_control_node)
				write_nginx_config();
			bfl_unlock("users file");
			bfl_unlock("user $username");
		}
		break;
	
	// Some resource stats on current server
	case "server-stats":
		// Run command on other node
		$found_node = false;
		foreach ($conf_nodes as $node) {
			if ($argc > 2 && $argv[2] == $node['name']) {
				print join(" ", server_stats($node['name']));
				$found_node = true;
				break;
			}
		}
		if ($found_node) break;
		
		$stats = server_stats();
		if ($argc > 2 && $argv[2] == "nice") {
			$mem = round ( ($stats[1] / 1024 / 1024) , 2 ) . " GB";
			$disk = round ( ($stats[4] / 1024) , 2 ) . " GB";
			print "Load avg: ".$stats[0]."\nUsed memory: $mem\nUsers: " . $stats[2] . "\nActive: " . $stats[3] . "\n";
			print "Free disk space: $disk\nFree inodes: ".$stats[5]."\n";	
			
		} else
			// Without "nice" parameter format is:
			// [loadavg] [used_memory] [logged_in_users] [active_users] [free_disk] [free_inodes] [cpuidle] [blocking] [time]
			print join(" ", $stats) . "\n";
		break;
	
	// Update last-access time for user
	case "last-update";
		if ($is_control_node) {
			$lastfile = $conf_home_path . "/last/$username.last";
			file_put_contents($lastfile, time());
			chown($lastfile, $username);
			chmod($lastfile, 0666);
		}
		break;

	// Broadcast message to all users
	case "broadcast":
		$bcfile = $conf_base_path . "/broadcast.txt";
		file_put_contents($bcfile, $argv[2]);
		chmod($bcfile, 0644);
		if ($argc > 3) $sleep = intval($argv[3]); else $sleep = 60;
		if ($sleep > 0) sleep($sleep);
		unlink($bcfile);
		break;

	// Revert to older revision on svn
	case "svnrestore":
		if ($argc < 5) 
			print "ERROR: svnrestore action takes 4 params\n";
		else {
			$relpath = $argv[3];
			$revision = intval($argv[4]);
			if (!$is_svn_node)
				run_on($svn_node_addr, "$conf_base_path/bin/webidectl svnrestore " . $userdata['esa'] . " " . escapeshellarg($relpath) . " $revision");
			else {
				$path = $userdata['workspace'] . "/$relpath";
				if (!file_exists($path)) {
					print "ERROR: path doesn't exist\n";
				} else {
					$head=exec("svn info \"$path\" | grep \"Revision\" | cut -d \" \" -f 2");
					run_as($username, "svn merge -r$head:$revision \"$path\"");
					run_as($username, "svn ci -m svnrestore \"$path\"");
				}
			}
		}
		break;

	// Remove backup files if disk sppace is low
	// TODO: moved to maintenance - REMOVE?
	case "disk-cleanup":
		$home_usage = -1; $root_usage = -1;
		foreach(explode("\n", shell_exec("df")) as $line) {
			$parts = preg_split("/\s+/", $line);
			if (count($parts) < 6) continue;
			if ($parts[5] == "/")
				$root_usage = $parts[3];
			if (starts_with($parts[5], $conf_home_path))
				$home_usage = $parts[3];
		}
		if ($home_usage == -1) $home_usage = $root_usage;
		$home_usage /= 1024;
		$tries = 0; $max_tries = 100; $min_backup_for_erase = 30000;
		print "HU $home_usage\n";
		while ($conf_diskspace_cleanup > 0  && $home_usage < $conf_diskspace_cleanup) {
			$rand_user = array_rand($users);
			print "Cleanup: rand user $rand_user\n";
			$user_ws_backup = setup_paths($rand_user)['workspace'] . ".old";
			print "UWB $user_ws_backup\n";
			if (file_exists($user_ws_backup)) {
				$backup_size = `du -s $user_ws_backup`;
				print "Size $backup_size\n";
				if ($backup_size > $min_backup_for_erase) {
					`rm -fr $user_ws_backup`;
					$stats = server_stats();
				}
			} else
				print "Not exists\n";
				
			$user_svn_backup = setup_paths($rand_user)['svn'] . ".old";
			print "USB $user_svn_backup\n";
			if (file_exists($user_svn_backup)) {
				$backup_size = `du -s $user_svn_backup`;
				print "Size $backup_size\n";
				if ($backup_size > $min_backup_for_erase) {
					`rm -fr $user_svn_backup`;
					$stats = server_stats();
				}
			} else
				print "Not exists\n";
			
			$home_usage = -1; $root_usage = -1;
			foreach(explode("\n", shell_exec("df")) as $line) {
				$parts = preg_split("/\s+/", $line);
				if (count($parts) < 6) continue;
				if ($parts[5] == "/")
					$root_usage = $parts[3];
				if (starts_with($parts[5], $conf_home_path))
					$home_usage = $parts[3];
			}
			if ($home_usage == -1) $home_usage = $root_usage;
			$home_usage /= 1024;
		}
		
		break;
	
	// Check if node for user is running
	case "is-node-up":
		if ($users[$username]["status"] != "active")
			print "false\n";
		else {
			$server = $users[$username]['server'];
			
			if (is_local($server)) {
				// Is node server up?
				$nodeup = false;
				if (file_exists($userdata['node_watch'])) {
					$pid = trim(file_get_contents($userdata['node_watch']));
					if (file_exists("/proc/$pid"))
						$nodeup = true;
				}
				
				if ($nodeup) print "true\n"; else print "false\n";
			} else
				print run_on($server, "$conf_base_path/bin/webidectl is-node-up " . $userdata['esa']);
		}
		
		break;
		
	// Nightly tasks for storage node
	// TODO: moved to maintenance - REMOVE?
	case "storage-nightly":
		if ($argc > 2 && $argv[2] == "all-stats")
			$all_stats = true;
		else
			$all_stats = false;
		
		storage_nightly($all_stats);
		break;
		
	case "sync-local":
		if (!$is_storage_node) break;
		sync_local($username);
		break;
		
	case "sync-remote":
		if (!$is_storage_node) break;
		sync_remote($username);
		break;
		
	case "unlock":
		if ($argc != 3) 
			print "ERROR: insufficient arguments\n";
		else
			bfl_unlock($argv[2]);
		break;
	
	case "help":
print <<<END
WEBIDECTL.PHP
C9@ETF project (c) 2015-2021

Master control script for C9@ETF

Usage: webidectl command [username] [other options]

User management commands:
	login username 		- doesn't check password!
	logout username
	add-user username [password realname email] - creates data for new user
				   will be called automatically by login if neccessary
	add-local-user username password - adds user into local userlist (prepares for add-user)
	set-password username 	- change password for user
	change-user username key value - change a property value for user
	remove username 	- remove users' data from system

User administration commands:
	is-node-up username	- check if webide is running for user
	is-online username	- check if user is currently online
	stop-node username 	- stop webide without logging out user
				  can be called even if user is not logged in
	verify-user username	- restart webide for user if not running
	kick-user username 	- change compute node at which users processes reside
	collaborate username 	- restart webide in collaboration mode
	reset-config username	- revert webide configuration to default for user
	last username 		- time of users' last activity (add "nice" for nicer output)
	last-update username 	- update time of last access to now

Server administration commands:
	server-stats 		- get a list of statistics for server
	culling 		- logout users inactive for >120m, stop nodejs for >45m, shorter if low memory
	kill-idle minutes type sleep - logout users inactive for minutes, type "soft" just stops nodejs,
				  pause sleep seconds for each user
	kill-inactive 		- remove zombie users from other nodes 
				  (marked as logged out but still process running)
	clear-server 		- logout all users and kill any remaining processes
	list-active 		- list currently logged in users
	verify-all-users	- run verify-user for all logged in users
	broadcast message	- show message bubble to all users
	reset-nginx 		- rebuild nginx configuration file

Server maintenance commands:
	disk-cleanup 		- delete some backups to recover disk space
	git-init username 	- create new Git repository for user (delete existing)
	git-commit username 	- commit all changes for user to Git
	update-stats username 	- update usage statistics file for user
	fix-svn username 	- automatically fix all SVN problems for username
	user-reinstall-svn username - create new SVN repository for user (delete existing repo)
	user-reset-svn username	- call update-stats then user-reinstall-svn
	storage-nightly		- nightly tasks to perform on storage server (rebuild stats, fix svn problems...)
	svnrestore username path revision - restore older version of file from SVN

END;
		break;
}

// Cleanup
if (file_exists($watchfile)) unlink($watchfile);
exit(0);



// -------------------------------
//    HIGH LEVEL USER LIFECYCLE
// -------------------------------

// These functions can be used on any node
// If node type is control, it will run relevant commands on all other nodes
// They handle locking (do not lock anything before calling them)

function activate_user($username, $ip_address) {
	global $conf_defaults_path, $conf_base_path, $conf_c9_group, $conf_nodes, $users;
	global $conf_ssh_tunneling, $conf_port_upper, $conf_port_lower, $conf_my_address;
	global $conf_home_path;
	global $is_control_node, $is_compute_node, $is_svn_node, $svn_node_addr, $is_storage_node;
	
	$userdata = setup_paths($username);
	$port = 0;
	
	// Prevent logging in while clear_server is running
	bfl_lock("clear server", false);
	
	if ($is_control_node) {
		if (!check_limits(server_stats(), /* $output= */ true)) return;
		bfl_lock("user $username");
		
		// v1 migrate
		if (!file_exists($userdata['home']))
			migrate_v1_v3($username);
		
		// Try to sync local folder against remote (if volatile-remote)
		if (array_key_exists("volatile-remote", $users[$username]) && $users[$username]['status'] != "active") {
			if (file_exists($userdata['home'] . "/.in_use")) { 
				print "ERROR: in use\n"; 
				debug_log("Workspace in use for $username");
				bfl_unlock("user $username");
				return; 
			}
			
			if ($is_svn_node) // If control node is also svn node, run locally
				$cmd = "$conf_base_path/bin/webidectl sync-local " . $userdata['esa']; // call function sync_local ?
			else
				$cmd = "ssh $svn_node_addr \"$conf_base_path/bin/webidectl sync-local " . $userdata['esa'] . "\"";
			
			$result = `$cmd`;
			if (strstr($result, "ERROR: in use")) {
				print "ERROR: in use\n"; 
				debug_log("Workspace in use for $username");
				bfl_unlock("user $username");
				return; 
			}
			debug_log("sync-local $username, result: $result");
		}
		
		// Find the compute node to run this on
		$best_node = ""; $best_value = 0;
		foreach($conf_nodes as $node) {
			if (!in_array("compute", $node['type'])) continue;
			
			if (is_local($node['address']))
				$stats = server_stats();
			else
				$stats = server_stats($node['name']);
			if (is_local($node['address'])) $stats[1] += 7000000;
			//if ($node['name'] == "c9prim") $stats[1] += 6000000;
			
			// Skip nodes without minimum level of resources
			if (!check_limits($stats, false)) {
				print "Node ".$node['address']." fails limits\n";
				continue;
			}

			$value = $stats[1]; // memory
			print "Node ".$node['address']." value $value\n";
			
			if ($best_node == "" || $best_value > $value) {
				$best_node = $node['address'];
				$best_value = $value;
			}
		}
		
		if ($best_node == "") {
			print "ERROR: No viable node found.\n";
			debug_log ("no viable node for $username");
			bfl_unlock("user $username");
			return;
		}
		
		print "Best node $best_node value $best_value\n";
		file_put_contents("$conf_base_path/log/" . $userdata['efn'], "\n\n=====================\nStarting webide at: ".date("d.m.Y H:i:s")."\n\n", FILE_APPEND);

		// Let SVN know that user logged in
		// This must be done before syncsvn to avoid conflicts
		// If control_node=storage_node, syncsvn filters only events by node process
		// So we must write .login and .logout from node :(
		if ($is_storage_node)
			$script = $userdata['home'] . "/.c9/node/bin/node $conf_base_path/lib/loginout.js " . $userdata['workspace'] . "/.login";
		else
			$script = "date > " . $userdata['workspace'] . "/.login";
		run_as($username, $script);
	
		// Start syncsvn
		if ($is_svn_node)
			syncsvn($username);
		else
			proc_close(proc_open("ssh $svn_node_addr \"$conf_base_path/bin/webidectl login " . $userdata['esa'] . " &\" 2>&1 &", array(), $foo));
		
		// Finally start service on best node
		if (is_local($best_node)) {
			$port = find_free_port(); 
			print "Found port: $port\n";
		} else {
			$port = run_on($best_node, "$conf_base_path/bin/webidectl login " . $userdata['esa']);
			
			// We can't allow nginx configuration to be invalid, ever
			$port = intval($port);
			while (intval($port) == 0) {
				run_on($best_node, "$conf_base_path/bin/webidectl logout " . $userdata['esa']);
				sleep(5);
				read_files();
				$port = run_on($best_node, "$conf_base_path/bin/webidectl login " . $userdata['esa']);
			}
			print "Port at $best_node is $port\n";
			$users[$username]['port'] = $port;
			if ($conf_ssh_tunneling) {
				$found = -1;
				// Move to port below range
				$local_port = $port - ($conf_port_upper - $conf_port_lower);
				$users[$username]['port'] = $local_port; // FIXME this doesnt work anymore
				proc_close(proc_open("ssh -N -L $local_port:localhost:$port $best_node &", array(), $foo));
			}
		}
		
		// Update local user database
		bfl_lock("users file");
		read_files();
		$users[$username]['server'] = $best_node;
		$users[$username]['port'] = $port;
		$users[$username]['status'] = "active";
		$users[$username]['ip_address'] = $ip_address;
		
		write_files();
		write_nginx_config();
		bfl_unlock("users file");
		
		if (is_local($best_node))
			start_node($username);
		
		// Update last access time to now
		$lastfile = $conf_home_path . "/last/$username.last";
		file_put_contents($lastfile, time());
		chown($lastfile, $username);
		chmod($lastfile, 0666);

		// Personalize files in users home
		if (!($handle = opendir("$conf_defaults_path/c9/customize"))) return;
		while (false !== ($entry = readdir($handle))) {
			if ($entry == "." || $entry == "..") continue;
			personalize($username, "$conf_defaults_path/c9/customize/$entry", $userdata['home'] . "/.c9/customize/$entry");
		}
		
		bfl_unlock("user $username");
		debug_log ("activate_user $username $best_node $port $ip_address");
	}
	
	else {
		bfl_lock("user $username");
		
		if ($is_svn_node)
			syncsvn($username);
		if ($is_compute_node) {
			$port = find_free_port(); 
		}
		
		bfl_lock("users file");
		read_files();
		$users[$username]['status'] = "active";
			
		if ($is_compute_node) {
			$users[$username]['server'] = $conf_my_address;
			$users[$username]['port'] = $port;
		}
		write_files();
		bfl_unlock("users file");
		if ($is_compute_node) {
			start_node($username);
		}
		bfl_unlock("user $username");
		
		debug_log ("activate_user $username $port $ip_address");
	}
	
	// Port goes to stdout
	print $port;
}

function create_user($username) {
	global $conf_base_path, $conf_nodes, $conf_c9_group, $conf_defaults_path, $users, $conf_home_path;
	global $is_storage_node, $is_control_node, $is_svn_node, $storage_node_addr, $conf_chroot, $conf_default_webide;
	
	$forbidden_usernames = array('root', 'daemon', 'bin', 'sys', 'sync', 'games', 'man', 'lp', 'mail', 'news', 'uucp', 'proxy', 'www-data', 'backup', 'list', 'irc', 'gnats', 'nobody', 'libuuid', 'syslog', 'messagebus', 'landscape', 'sshd', 'c9test', 'c9');
	if (in_array($username, $forbidden_usernames)) {
		debug_log("forbidden username $username");
		print "ERROR: username $username not allowed\n";
		return;
	}
	
	$userdata = setup_paths($username);
	if ($is_control_node)
		debug_log("create_user $username");

	bfl_lock("user $username");

	// Create user on storage node
	if ($is_storage_node) {
		if (!file_exists($conf_home_path . "/" . substr($userdata['efn'],0,1))) {
			exec("mkdir " . $conf_home_path . "/" . substr($userdata['efn'],0,1));
			exec("chmod 755 " . $conf_home_path . "/" . substr($userdata['efn'],0,1));
		}
		exec("useradd -d ". $userdata['home'] . " -g $conf_c9_group -k $conf_defaults_path/home -m " . $userdata['esa']);
		// For some reason files copied from default home aren't owned by user :(
		exec("chown -R " . $userdata['esa'] . ":$conf_c9_group ". $userdata['home']);
	} else {
		if ($is_control_node)
			run_on($storage_node_addr, "$conf_base_path/bin/webidectl add-user " . $userdata['esa']);
		exec("useradd -d " . $userdata['home'] . " -g $conf_c9_group " . $userdata['esa']);
	}
	
	// If this is control node, create user on other nodes
	if ($is_control_node) {
		foreach ($conf_nodes as $node) {
			if (!in_array("control", $node['type']) && !in_array("compute", $node['type']) && !in_array("svn", $node['type']))
				continue;
			if (!is_local($node['address']) && !in_array("control", $node['type']))
				run_on($node['address'], "$conf_base_path/bin/webidectl add-user " . $userdata['esa']);
		}
	}

	// If this is svn node, create repo
	if ($is_svn_node) {
		user_create_svn_workspace($username);
	}
	
	// Copy defaults
	if ($is_storage_node && $is_control_node)
		reset_config($username);
	else
		if ($is_control_node)
			run_on($storage_node_addr, "$conf_base_path/bin/webidectl reset-config " . $userdata['esa']);
			
	// Create chroot
	if ($conf_chroot) {
		if ($is_storage_node && $is_control_node)
			exec("$conf_base_path/lib/create_chroot.sh " . $userdata['esa'] . " $conf_c9_group ". $userdata['home']);
		else if ($is_storage_node)
			run_on($storage_node_addr, "$conf_base_path/lib/create_chroot.sh " . $userdata['esa'] . " $conf_c9_group ". $userdata['home']);
	}

	// Add default files to SVN
	if ($is_svn_node) 
		run_as($username, "cd " . $userdata['workspace'] . "; svn add *; svn ci -m import .");
	
	// Init git on storage node and fix 
	if ($is_storage_node && $is_control_node)
		git_init($username);
	else
		if ($is_control_node)
			run_on($storage_node_addr, "$conf_base_path/bin/webidectl git-init " . $userdata['esa']);

	// Fix c9 link
	if ($is_storage_node)
		run_as($username, "cd " . $userdata['home'] . "; ln -s $conf_base_path/c9fork fork");
	
	bfl_lock("users file");
	read_files();
	$users[$username] = array();
	$users[$username]['status'] = "inactive";
	if ($conf_chroot) $users[$username]['workspace'] = "chroot";
	if (isset($conf_default_webide)) $users[$username]['webide'] = $conf_default_webide;
	write_files();
	bfl_unlock("users file");
	bfl_unlock("user $username");
}


// Create htpasswd or overwrite existing one
function set_password($username, $password) {
	global $conf_c9_group;

	$userdata = setup_paths($username);
	$password_esa = escapeshellarg($password);
	
	exec("htpasswd -bc " . $userdata['htpasswd'] . " " . $userdata['esa'] . " $password_esa 2>&1");
	exec("chown " . $userdata['esa'] . ":$conf_c9_group " . $userdata['htpasswd']);
	chmod($userdata['htpasswd'], 0644);
}


// The philosophy is that deactivate can be called on user who is marked inactive, 
// to stop whatever running services etc. for this user, except on svn node
function deactivate_user($username, $skip_svn = false) {
	global $users, $is_control_node, $is_compute_node, $conf_base_path, $is_svn_node, $svn_node_addr, $conf_svn_problems_log, $conf_ssh_tunneling, $is_storage_node;
	
	// Prevent overloading svn server during clear_server
	if (!$is_control_node && $is_svn_node)
		if ($users[$username]['status'] == "inactive") return;
	
	$userdata = setup_paths($username);
	global $action;
	bfl_lock("user $username");
	
	// If this is a simple compute node, just kill nodejs
	if ($is_compute_node && !$is_control_node) {
		stop_node($username, true); // $cleanup=true -- kill everything owned by user
		
		bfl_lock("users file");
		read_files();
		$users[$username]['status'] = "inactive"; 
		unset($users[$username]['collaborate']);
		unset($users[$username]['server']);
		unset($users[$username]['port']);
		write_files();
		bfl_unlock("users file");
	}
		
	else if ($is_control_node) {
		
		// Update logout file
		// If control_node=storage_node, syncsvn filters only events by node process
		// So we must write .login and .logout from node :(
		if ($is_storage_node)
			$script = $userdata['home'] . "/.c9/node/bin/node $conf_base_path/lib/loginout.js " . $userdata['workspace'] . "/.logout";
		else
			$script = "date > " . $userdata['workspace'] . "/.logout";
		run_as($username, $script);

		// Remove user from nginx - this will inform user that they are logged out
		bfl_lock("users file");
		read_files();
		$server = $users[$username]['server'];
		$users[$username]['status'] = "inactive";
		$port = $users[$username]['port'];
		unset($users[$username]['collaborate']);
		unset($users[$username]['server']);
		unset($users[$username]['port']);
		write_nginx_config();
		write_files();
		bfl_unlock("users file");
	
		// Stop nodejs on server where user is running
		if (is_local($server) || empty($server))
			stop_node($username, false);
		else {
			run_on($server, "$conf_base_path/bin/webidectl logout " . $userdata['esa']);
			if ($conf_ssh_tunneling) {
				foreach (ps_ax("localhost") as $process) {
					if (strstr($process['cmd'], "ssh -N -L $port"))
						exec("kill ".$process['pid']);
				}
			}
		}
		if (!$is_svn_node && !$skip_svn)
			proc_close(proc_open("ssh $svn_node_addr \"$conf_base_path/bin/webidectl logout " . $userdata['esa'] . " &\" 2>&1 &", array(), $foo));
	}
	
	if ($is_svn_node) {
		bfl_lock("users file");
		read_files();
		$users[$username]['status'] = "inactive"; 
		write_files();
		bfl_unlock("users file");

		// Syncsvn deactivate
		sleep(1); // Give some time for syncsvn to sync .logout
		if (file_exists($userdata['svn_watch'])) {
			$pid = trim(file_get_contents($userdata['svn_watch']));
			if (file_exists("/proc/$pid"))
				exec("kill $pid");
			unlink($userdata['svn_watch']);
		}
		stop_inotify($username);
		
		// Commit remaining stuff to svn
		$script  = "cd " . $userdata['workspace'] . "; ";
		$script .= "echo USER: $username >> $conf_svn_problems_log; ";
		$script .= "svn ci -m deactivate_user . 2>&1 >> $conf_svn_problems_log";
		run_as($username, $script);
		$time = time() + 60 + rand(0, 100);
		file_put_contents("$conf_base_path/data/maintenance_tasks", "fixsvn $username,$time\n", FILE_APPEND);
	}
	else if ($is_control_node) {
//		run_on($svn_node_addr, "$conf_base_path/bin/webidectl logout " . $userdata['esa']);
	}
	
	if ($is_control_node) {
		// REMOVED? Users file is updated only now, to prevent user from logging back in during other procedures
		
		// Sync locally changed files to remote
		if (array_key_exists("volatile-remote", $users[$username])) {
			if ($is_svn_node)
				file_put_contents("$conf_base_path/data/maintenance_tasks", "sync_remote $username,$now\n", FILE_APPEND);
			else
				run_on($svn_node_addr, "echo sync_remote $username,$now >> $conf_base_path/data/maintenance_tasks");
			debug_log("sync-remote $username");
		}
	}
	bfl_unlock("user $username");
	debug_log ("deactivate_user $username");
}

function remove_user($username) {
	global $users, $conf_nodes, $conf_base_path, $conf_shared_path, $conf_home_path;
	global $is_storage_node, $is_control_node, $is_svn_node, $is_compute_node;
	
	$userdata = setup_paths($username);
	
	bfl_lock("user $username");
	
	if ($is_svn_node) {
		exec("rm -fr " . $userdata['svn']);
		$svn_backup = $userdata['svn'] . ".old";
		if (file_exists($svn_backup))
			exec("rm -fr $svn_backup");
	}
	
	if ($is_storage_node) {
		$archive_path = $conf_shared_path . "/archive";
		if (!file_exists($archive_path)) mkdir($archive_path);
		$archive_file = $archive_path . "/" . $userdata['efn'] . ".tar.gz";
		$user_ws = $userdata['workspace'];
		if (!file_exists($user_ws)) 
			// Legacy v1 workspace
			$user_ws = "/home/c9/workspace/" . $userdata['efn'];
		
		// Remove svn and git repos from workspace
		exec("rm -fr $user_ws/.svn; rm -fr $user_ws/.git");
		exec("tar czvf $archive_file $user_ws");
		exec("rm -fr $user_ws");
		exec("userdel -r " . $userdata['esa']);
		
		// Archive & remove stats
		$stats_paths = "/home/c9/stats/" . $userdata['efn'] . ".stats";
		$stats_paths .= " /home/c9/stats/*/" . $userdata['efn'] . ".stats";
		$archive_file = $archive_path . "/" . $userdata['efn'] . ".stats.tar.gz";
		exec("tar czvf $archive_file $stats_paths");
		exec("rm $stats_paths");
	}
	else 
		exec("userdel " . $userdata['esa']);
	
	if ($is_compute_node)
		exec("rm $conf_base_path/log/" . $userdata['efn']);
	
	if ($is_control_node) {
		unlink($userdata['htpasswd']);

		$localuser_file = $conf_base_path . "/localusers/" . $userdata['efn'];
		if (file_exists($localuser_file)) unlink($localuser_file);
		
		$lastfile = $conf_home_path . "/last/$username.last";
		if (file_exists($lastfile)) unlink($lastfile);
		
		foreach($conf_nodes as $node)
			run_on($node['address'], "$conf_base_path/bin/webidectl remove " . $userdata['esa']);
	}

	bfl_lock("users file");
	read_files();
	unset($users[$username]);
	write_files();
	if ($is_control_node)
		write_nginx_config();
	bfl_unlock("users file");
	bfl_unlock("user $username");
}

function verify_user($username) {
	global $conf_base_path, $is_control_node, $is_compute_node, $is_svn_node, $svn_node_addr, $users, $conf_home_path, $conf_my_address;
	
	$userdata = setup_paths($username);
	if ($is_control_node) {
		if (!array_key_exists('server', $users[$username])) return;
		$server = $users[$username]['server'];
		
		if ($users[$username]["status"] !== "active") {
			print "ERROR: User $username not logged in!\n";
			return;
		}
	} else {
		if (!array_key_exists('server', $users[$username]))
			$users[$username]['server'] = $conf_my_address;
		$server = $users[$username]['server'];
	}
	
	if (is_local($server) && $is_compute_node) {
		// Is node server up?
		$nodeup = false;
		if (file_exists($userdata['node_watch'])) {
			$pid = trim(file_get_contents($userdata['node_watch']));
			if (file_exists("/proc/$pid"))
				$nodeup = true;
		}
		debug_log ("verify_user $username nodeup $nodeup");
		
		// If it is, return port
		$port = intval($users[$username]['port']);

		// If user is not logged in, this is not a control node, we will log him in and restart node
		if ($users[$username]["status"] !== "active") {
			print "Logged out? ";
			$users[$username]['status'] = "active";
			$nodeup = false;
			$port = 0; // Files will be rewritten
		}
		
		else if ($nodeup) {
			if ($port > 2)
				print "Node running - Found port: $port\n";
			else
				$nodeup = false; // Invalid port, restart
		}
		
		// Node server isn't up, restart
		if (!$nodeup) {
			if (file_exists($userdata['node_watch']))
				unlink($userdata['node_watch']);
			
			// This command will apparently kill some node servers that it shouldn't? too dangerous
			//exec("ps ax | grep tmux | grep " . $userdata['esa'] . " | grep -v grep | cut -c 1-5 | xargs kill");
			
			// Kill related processes
			bfl_lock("user $username");
			stop_node($username, false);
			
			// Check to see if port is in use - sometimes race condition causes this situation
			$log_path = "$conf_base_path/log/" . $userdata['efn'];
			$inuse = `tail -20 $log_path | grep EADDRINUSE`;
			if (!empty(trim($inuse)) || $port <= 2) {
				$port = find_free_port(); 
				print "Starting node - Found port: $port\n";
				
				bfl_lock("users file");
				read_files();
				if (!$is_control_node && $users[$username]["status"] !== "active")
					$users[$username]['status'] = "active";
				$users[$username]['port'] = $port;
				write_files();
				if ($is_control_node) write_nginx_config();
				bfl_unlock("users file");
			} else {
				print "Restarting existing node ($port)\n";
				if (!$is_control_node && $users[$username]["status"] !== "active") {
					bfl_lock("users file");
					read_files();
					$users[$username]['status'] = "active";
					write_files();
					bfl_unlock("users file");
				}
			}
			
			// Restart node
			start_node($username);
			bfl_unlock("user $username");
			debug_log ("restart node user $username port $port");
		}
	}
	else if ($is_control_node) {
		$output = run_on($server, "$conf_base_path/bin/webidectl verify-user " . $userdata['esa']);
		debug_log ("verify_user $username server $server output " .str_replace("\n", "", $output));
		if ($substr = strstr($output, "Found port:")) {
			$port = intval(substr($substr, 11));
			if (intval($port) == 0) $port = 1;
			debug_log ("resetting port to $port");
			
			bfl_lock("users file");
			read_files();
			$users[$username]['port'] = $port;
			write_files();
			write_nginx_config();
			bfl_unlock("users file");
		}
	}
	
	if ($is_control_node) {
		// Update time of last access
		$lastfile = $conf_home_path . "/last/$username.last";
		file_put_contents($lastfile, time());
		chown($lastfile, $username);
		chmod($lastfile, 0666);
	}
	
	if ($is_svn_node) {
		// Is syncsvn up?
		$svnup = false;
		if (file_exists($userdata['svn_watch'])) {
			$pid = trim(file_get_contents($userdata['svn_watch']));
			if (file_exists("/proc/$pid"))
				$svnup = true;
		}
		
		// It isn't, restart
		if (!$svnup) {
			bfl_lock("user $username");
			if (file_exists($userdata['svn_watch'])) 
				unlink($userdata['svn_watch']);
			stop_inotify($username); // New inotify will be started by syncsvn
			print "Starting syncsvn for $username...\n";
			syncsvn($username);
			bfl_unlock("user $username");
		}
	}
	else if ($is_control_node) {
		run_on($svn_node_addr, "$conf_base_path/bin/webidectl verify-user " . $userdata['esa']);
	}
}


function kick_user($username) {
	global $conf_nodes, $users, $conf_base_path;

	$best_node = ""; $best_value = 0;
	foreach($conf_nodes as $node) {
		if (!in_array("compute", $node['type'])) continue;
		if ($node['address'] == $users[$username]['server']) continue;
		
		if (is_local($node['address']))
			$stats = server_stats();
		else
			$stats = server_stats($node['name']);
		if (is_local($node['address'])) $stats[1] += 2000000;
		//if ($node['name'] == "c9prim") $stats[1] += 5000000;
		//if ($node['name'] == "c9sec") $stats[1] += 2000000;
		
		// Skip nodes without minimum level of resources
		if (!check_limits($stats, false)) {
			print "Node ".$node['address']." fails limits\n";
			continue;
		}

		$value = $stats[1]; // memory
		print "Node ".$node['address']." value $value\n";
		
		if ($best_node == "" || $best_value > $value) {
			$best_node = $node['address'];
			$best_value = $value;
		}
	}
	
	print "Best node is $best_node - go there!\n";
	
	bfl_lock("users file");
	read_files();
	$users[$username]['server'] = $best_node;
	write_files();
	write_nginx_config();
	bfl_unlock("users file");
}



// -------------------------------
//    LOW LEVEL PROCESS MGMT
// -------------------------------

// These functions should be called only on relevant node type
// They don't handle locking


// Start nodejs instance for user
function start_node($username) {
	global $conf_base_path, $conf_home_path, $users, $conf_c9_group;
	
	$userdata = setup_paths($username);
	$useropts = $users[$username];
	
	// Prepare the mounts for chroot
	if (array_key_exists('workspace', $users[$username]) && $users[$username]['workspace'] == "chroot") {
		$home = $userdata['home'];
		foreach(file("$conf_base_path/lib/chroot_paths") as $line) {
			list($rootdir,$userdir,$opts) = explode(",", trim($line));
			if (!`grep $home$userdir /proc/mounts`) {
				if ($opts) $opts = "-o $opts"; else $opts = "";
				if (!file_exists($home.$userdir)) `mkdir $home$userdir`;
				`mount --rbind $opts $rootdir $home$userdir`;
			}
		}
		/*$mount = `grep $home/dev/pts /proc/mounts`;
		if (!strstr($mount,"devpts"))
			`chroot $home mount -t devpts devpts /dev/pts`;*/
	}
	
	if (array_key_exists('webide', $useropts))
		$nodecmd     = "$conf_base_path/bin/start" . $useropts['webide'];
	else
		$nodecmd     = "$conf_base_path/bin/startnode";
	$esa         = $userdata['esa'];
	$home        = $userdata['home'];
	$port        = $useropts['port'];
	$listen_addr = $useropts['server'];
	$workspace   = $userdata['workspace'];
	if (array_key_exists('workspace', $useropts) && $useropts['workspace'] == "chroot") 
		$workspace = "/workspace";
	$log_path    = "$conf_base_path/log/" . $userdata['efn'];
	$watch_path  = $userdata['node_watch'];
	
	$lastfile = $conf_home_path . "/last/" . $userdata['efn'] . ".last";
	
	touch($log_path);
	chown($log_path, $username);
	chmod($log_path, 0644);
	touch($lastfile);
	chown($lastfile, $username);
	chmod($lastfile, 0666);
	if (array_key_exists('workspace', $useropts) && $useropts['workspace'] == "chroot") {
		exec("echo chroot --userspec=$esa:$conf_c9_group $home $nodecmd /root $port $listen_addr $workspace $log_path $watch_path >> $log_path");
		shell_exec("chroot --userspec=$esa:$conf_c9_group $home $nodecmd /root $port $listen_addr $workspace $log_path $watch_path");
	} else
		run_as($username, "$nodecmd $home $port $listen_addr $workspace $log_path $watch_path");
}

// Stop nodejs instance and related user processes
// Note: if user is still logged in, node will be restarted automatically by web server
function stop_node($username, $cleanup) {
	global $users, $conf_base_path;
	
	$userdata = setup_paths($username);

	if (file_exists($userdata['node_watch'])) {
		$pid = trim(file_get_contents($userdata['node_watch']));
		if (file_exists("/proc/$pid"))
			exec("kill $pid");
		unlink($userdata['node_watch']);
	}
	
	foreach (ps_ax("localhost") as $process) {
		if ($process['user'] == $username) {
			// $cleanup=true -- kill all processes owned by user
			if ($cleanup) {
				exec("kill ".$process['pid']);
				continue;
			}
			
			// $cleanup=false -- carefully kill just running programs
			if (starts_with($process['cmd'], "tmux"))
				exec("kill ".$process['pid']);
			else if (starts_with($process['cmd'], "/usr/bin/valgrind"))
				exec("kill ".$process['pid']);
			else if (strstr($process['cmd'], "gdb"))
				exec("kill ".$process['pid']);
			else if (strstr($process['cmd'], ".runme"))
				exec("kill ".$process['pid']);
			else if (strstr($process['cmd'], "node "))
				exec("kill ".$process['pid']);
			else if (strstr($process['cmd'], "nodejs "))
				exec("kill ".$process['pid']);
		}
	}
	
	// Unmount binded mounts for chroot
	if (array_key_exists('workspace', $users[$username]) && $users[$username]['workspace'] == "chroot") {
		sleep(2);
		$home = $userdata['home'];
		//`umount $home/dev/pts`;
		foreach(file("$conf_base_path/lib/chroot_paths") as $line) {
			list($rootdir,$userdir,$opts) = explode(",", trim($line));
			`umount -lf $home$userdir`;
		}
		`mount -t devpts devpts /dev/pts -o gid=5,mode=620,ptmxmode=000`; // Will be unmounted by umount -lf /rhome/.../dev
	}
}

function stop_inotify($username) {
	$userdata = setup_paths($username);
	
	$inotify_pid_file = $userdata['workspace'] . "/.inotify_pid";
	if (file_exists($inotify_pid_file)) {
		$pid = trim(file_get_contents($inotify_pid_file));
		if (file_exists("/proc/$pid"))
			exec("kill $pid");
		unlink($inotify_pid_file);
	}
}

// Start syncsvn instance for user
function syncsvn($username) {
	global $conf_base_path, $conf_home_path, $conf_syncsvn_log;
	
	$userdata = setup_paths($username);
	
	$syncsvncmd  = "$conf_base_path/bin/syncsvn";
	$user_efn    = $userdata['esa'];
	// This is in home so it would be visible from control server
	$watch_path  = $userdata['svn_watch'];
	$run_as      = $userdata['esa'];
	
	// We can't use run_as because this command never ends, so we need to redirect output
	//exec("su $run_as -c '$syncsvncmd $user_efn $watch_path 2>&1 >> $conf_syncsvn_log &' 2>&1 >/dev/null");
}

// Migrate old workspace (just calls external script on the appropriate server)
function migrate_v1_v3($username) {
	global $conf_storage_node, $conf_base_path, $conf_svn_path, $conf_c9_group, $storage_node_addr, $conf_nodes;
	global $is_storage_node, $is_control_node, $is_svn_node;
	
	$userdata = setup_paths($username);
	
	$old_student_workspace = "/rhome/c9/workspace/" . $userdata['efn'];
	if (!file_exists($old_student_workspace)) return; // No v1 workspace...
	if (!$is_control_node) return;
	
	run_on($storage_node_addr, "$conf_base_path/lib/migrate_v1_v3 " . $userdata['esa']);

	// Add user locally
	exec("useradd -d ".$userdata['home']." -g $conf_c9_group -M ".$userdata['esa']);
	
	// Add user on remaining nodes (migrate script creates user on storage node with more options)
	foreach($conf_nodes as $node) {
		if (in_array("storage", $node['type'])) continue;
		if (is_local($node['address'])) continue;
		run_on($node['address'], "useradd -d ".$userdata['home']." -g $conf_c9_group -M ".$userdata['esa']);
	}
}

// Some common resource usage stats
function server_stats($server = "local") {
	global $users, $conf_home_path, $conf_base_path, $conf_svn_path, $conf_node_timeout;

	$stats = array();
	if ($server == "local")
		$filename = "server_stats.log";
	else
		$filename = $server."_stats.log";
	$filename = $conf_base_path . "/$filename";
	
	$stats = explode(" ", trim(`tail -1 $filename`));
	
	// Reorder stats because legacy code expects it like this
	$cpuidle = array_shift($stats);
	$blocking = array_shift($stats);
	if (array_key_exists(6, $stats)) $time = $stats[6]; else $time=time();
	$stats[6] = $cpuidle;
	$stats[7] = $blocking;
	$stats[8] = $time;
	
	// If there are no stats for >timeout seconds, node becomes inaccessible
	if ($stats[8] < time() - $conf_node_timeout) {
		$stats[0] = $stats[3] = 100;
		$stats[1] = $stats[2] = 0;
	}
	
	return $stats;
}

// Check if server stats exceed one of the allowed values
function check_limits($stats, $output) {
	global $conf_limit_memory, $conf_limit_loadavg, $conf_limit_users, $conf_limit_active_users, $conf_limit_diskspace, $conf_limit_inodes;
	
	if ($stats[7] > 3) {
		if ($output) print "ERROR: too many blocking\n";
		return false;
	}
	if ($conf_limit_loadavg > 0 && $stats[0] > $conf_limit_loadavg) {
		if ($output) print "ERROR: Load average $stats[0] > $conf_limit_loadavg\n";
		return false;
	}
	$mem = $stats[1] / 1024 / 1024;
	if ($conf_limit_memory > 0  && $mem > $conf_limit_memory)  {
		if ($output) print "ERROR: Memory $mem GB > $conf_limit_memory GB\n";
		return false;
	}
	if ($conf_limit_users  > 0  && $stats[2] > $conf_limit_users) {
		if ($output) print "ERROR: Users $stats[2] > $conf_limit_users\n";
		return false;
	}
	if ($conf_limit_active_users > 0  && $stats[3] > $conf_limit_active_users) {
		if ($output) print "ERROR: Active users $stats[3] > $conf_limit_active_users\n";
		return false;
	}
	if ($conf_limit_diskspace > 0  && $stats[4] < $conf_limit_diskspace) {
		if ($output) print "ERROR: Disk space $stats[4] < $conf_limit_diskspace\n";
		return false;
	}
	if ($conf_limit_inodes > 0  && $stats[5] < $conf_limit_inodes) {
		if ($output) print "ERROR: Inodes $stats[0] < $conf_limit_inodes\n";
		return false;
	}
	return true;
}



// --------------------
//     USER CONFIG
// --------------------

function reset_config($username) {
	global $conf_defaults_path, $conf_c9_group;
	
	$userdata = setup_paths($username);
	
	if (!file_exists($userdata['workspace'])) {
		print "ERROR: User $username workspace doesn't exist\n"; 
		return; 
	}
	
	// We detect that workspace is empty if there is no workspace/.c9 folder
	if (!file_exists( $userdata['workspace'] . "/.c9" ))
		run_as($username, "cp -R $conf_defaults_path/workspace/* " . $userdata['workspace']);
	
	exec ("rm -fr ".$userdata['workspace']."/.c9");
	run_as($username, "cp -R $conf_defaults_path/workspace/.c9 " . $userdata['workspace']);
	
	exec ("rm -fr ".$userdata['workspace']."/.user");
	run_as($username, "cp -R $conf_defaults_path/workspace/.user " . $userdata['workspace']);
	
	exec ("rm -fr ".$userdata['workspace']."/.theia");
	run_as($username, "cp -R $conf_defaults_path/workspace/.theia " . $userdata['workspace']);
	
	exec ("rm -fr ".$userdata['home']."/.c9");
	run_as($username, "cp -R $conf_defaults_path/c9 " . $userdata['home'] . "/.c9");
	
	exec ("rm -fr ".$userdata['home']."/.theia");
	run_as($username, "cp -R $conf_defaults_path/theia " . $userdata['home'] . "/.theia");

	exec ("rm -fr ".$userdata['home']."/.node-gyp");
}


// --------------------
//     MAINTENANCE
// --------------------

// kill nodejs instances for inactive users
function kill_idle($minutes, $output, $sleep) {
	global $users, $conf_base_path, $conf_nodes;

	$keys = array_keys($conf_nodes);
		shuffle($keys);
		$conf_nodes2 = array();
		foreach ($keys as $key)
			$conf_nodes2[$key] = $conf_nodes[$key];
	foreach($conf_nodes2 as $node) {
		if (!in_array("compute", $node['type'])) continue;
		$processed = [];
		foreach(ps_ax($node['address']) as $process) {
			if (!strstr($process['cmd'], "node ") && !strstr($process['cmd'], "nodejs "))
				continue;
				
			// User possibly logged out in the meantime
			$username = $process['user'];
			if (in_array($username, $processed)) continue;
			if ($users[$username]['status'] == "inactive") continue;
			
			$time = last_access($username);
			if ($output)
				print "Username $username inactive ".round((time()-$time)/60, 2)." minutes\n";
			if (time() - $time > $minutes*60) {
				$server = $users[$username]['server'];
				if ($output) print "Stopping node on $server\n";
				$now = time();
				if (is_local($server))
					file_put_contents("$conf_base_path/data/maintenance_tasks", "stop_node $username,$now\n", FILE_APPEND);
				else
					run_on($server, "echo stop_node $username,$now >> $conf_base_path/data/maintenance_tasks");
				$processed[] = $username;
				
				if ($sleep > 0) {
					sleep($sleep);
					read_files();
				}
			}
		}
	}
}


// kill "hard" means logout inactive users
function kill_idle_logout($minutes, $output, $sleep) {
	global $users, $conf_base_path;
	
	foreach ($users as $username => $options) {
		if ($options['status'] == "inactive") continue;
		
		$time = last_access($username);
		if ($output)
			print "Username $username inactive ".round((time()-$time)/60, 2)." minutes\n";
		if (time() - $time > $minutes*60) {
			$now = time();
			file_put_contents("$conf_base_path/data/maintenance_tasks", "logout $username,$now\n", FILE_APPEND);
			
			if ($sleep > 0) {
				sleep($sleep);
				read_files();
			}
		}
	}
}

// Logout all users and kill/restart all processes related to webide
function clear_server() {
	global $conf_base_path, $users, $is_compute_node, $is_control_node, $svn_node_addr, $is_svn_node, $conf_nodes;
	
	debug_log("clear server");
	bfl_lock("clear server");
	
	file_put_contents("$conf_base_path/razlog_nerada.txt", "kada se završi restart servera");
	chmod("$conf_base_path/razlog_nerada.txt", 0644);
	
	// SVN server must be cleared before others, to avoid generating large load (due to cleanup operations)
	if ($is_control_node && !$is_svn_node)
		proc_close(proc_open("ssh $svn_node_addr \"$conf_base_path/bin/webidectl clear-server & \" 2>&1 >/dev/null &", array(), $foo));
	
	//print "Čekam 5s...\n";
	sleep(5);
	
	// Just a compute node
	if (!$is_control_node && $is_compute_node) {
		// This just kills node processes, not very efective...
		foreach ($users as $username => $options) {
			if ($options['status'] == "active")
				deactivate_user($username);
		}
		write_files();
		exec("killall node");
		exec("killall nodejs");
		exec("killall tmux");
		exec("killall inotifywait");
		exec("killall gdb");
		bfl_unlock("clear server");
		return;
	}
	
	// Special procedure for svn node, prevents excessive load
	if (!$is_control_node && $is_svn_node) {
		$were_active = array();
		
		// Just mark users as inactive and write files
		bfl_lock("users file");
		read_files();
		foreach ($users as $username => &$options) {
			if ($options['status'] == "active") {
				$options['status'] = "inactive";
				$were_active[] = $username;
			}
		}
		write_files();
		bfl_unlock("users file");
		
		// Now we actually log them out, slowly
		foreach($were_active as $username) {
			sleep(1);
			$userdata = setup_paths($username);
			if (file_exists($userdata['svn_watch'])) {
				$pid = trim(file_get_contents($userdata['svn_watch']));
				if (file_exists("/proc/$pid"))
					exec("kill $pid");
				unlink($userdata['svn_watch']);
			}
			stop_inotify($username);
			
			// Commit remaining stuff to svn
			$script  = "cd " . $userdata['workspace'] . "; ";
			$script .= "echo USER: $username >> $conf_svn_problems_log; ";
			$script .= "svn ci -m deactivate_user . 2>&1 >> $conf_svn_problems_log";
			run_as($username, $script);
			
			// During the next 1200s = 20 minutes, run fixsvn operations
			$time = 60 + rand(0, 1200);
			proc_close(proc_open("$conf_base_path/bin/fixsvn " . $userdata['esa'] . " $time  2>&1 >/dev/null &", array(), $foo));
		}
		bfl_unlock("clear server");
		return;
	}

	// Continue with control node actions
	
	// Logout all users
	read_files();
	foreach ($users as $username => $options) {
		if ($options['status'] == "active")
			deactivate_user($username, true);
	}
	
	// Force restart, since deactivate will just reload... this kills some hanging connections
	exec("service nginx restart");
	
	// Clear other servers
	foreach($conf_nodes as $node) {
		if (is_local($node['address'])) continue;
		if ($node['address'] == $svn_node_addr) continue; // Already done
		$addr = $node['address'];
		proc_close(proc_open("ssh $addr \"$conf_base_path/bin/webidectl clear-server & \" 2>&1 >/dev/null &", array(), $foo));
	}
	
	exec("killall node");
	exec("killall nodejs");
	exec("killall tmux");
	exec("killall inotifywait");
	exec("killall gdb");
	exec("ps aux | grep tmux | cut -c 10-15 | xargs kill"); // apparently we need this...
	
	sleep(60); // Wait for 60 more seconds until allowing login
	unlink("$conf_base_path/razlog_nerada.txt");
	bfl_unlock("clear server");
}


// Nightly tasks to be performed on storage server
// - update stats
// - git commit
// - reset svn if disk usage too large
// - delete backup folders if neccessary
// - generate usage statistic
function storage_nightly($all_stats) {
	global $users, $conf_base_path, $conf_home_path;
	global $conf_max_user_inodes, $conf_max_user_svn_disk_usage, $conf_limit_diskspace, $conf_diskspace_cleanup;
	
	$skip_users = array( );
	
	$total = count($users);
	$current=1;
	$total_usage_stats = array();
	
	// shuffle_assoc
	$keys = array_keys($users);
	shuffle($keys);
	$users_shuffled = array();
	foreach ($keys as $key)
		$users_shuffled[$key] = $users[$key];
	
	foreach ($users_shuffled as $username => $options) {
		if (file_exists("/tmp/storage_nightly_skip")) break;
		if ($username == "") continue;
		
		$last = last_access($username);
		if (time() - $last > 24*60*60) {
			print "Inactive for >24h, skipping.\n";
			continue;
		}
		$time = time();
		
		file_put_contents("$conf_base_path/data/maintenance_tasks", "clean_big_files $username,$time\n", FILE_APPEND);
		file_put_contents("$conf_base_path/data/maintenance_tasks", "update_stats $username,$time\n", FILE_APPEND);
		file_put_contents("$conf_base_path/data/maintenance_tasks", "git_update $username,$time\n", FILE_APPEND);
		file_put_contents("$conf_base_path/data/maintenance_tasks", "clean_inodes $username,$time\n", FILE_APPEND);
	}
}



// --------------------
//      SVN&GIT MGMT
// --------------------

// These functions should only be called on a svn node


// Create workspace for user as a SVN repository
function user_create_svn_workspace($username) {
	global $conf_c9_group;
	
	$userdata = setup_paths($username);

	exec("svnadmin create " . $userdata['svn']);
	exec("chown -R " . $userdata['esa'] . ":$conf_c9_group " . $userdata['svn']);
	exec("chmod -R 755 " . $userdata['svn']);
	
	$svn_ignore_patterns = array(".c9", ".user", ".tmux", ".svn.fifo", ".inotify_pid", ".git", "core", "*.core*");

	$svn_script  = "cd " . $userdata['home'] . "; ";
	$svn_script .= "svn co file://" . $userdata['svn'] . " workspace; ";
	$svn_script .= "chmod 700 workspace; cd workspace; ";

	foreach($svn_ignore_patterns as $patt)
		$svn_script .= "svn propset svn:ignore \"$patt\" .; ";
		
	run_as($username, $svn_script);
}

//  Reinstall svn for user (assumption is that there are no conflicts or locks!)
function user_reinstall_svn($username) {
	global $c9_user, $c9_group, $conf_base_path, $student_workspace, $student_svn, $conf_home_path;
	
	global $conf_base_path;
	
	$userdata = setup_paths($username);

	// Backup old data
	exec("rm -fr " . $userdata['workspace'] . ".old/.theia");
	$script  = "chmod -R 0755 " . $userdata['workspace'] . ".old; chmod -R 0755 " . $userdata['svn'] . ".old; ";
	
	$script .= "rm -fr " . $userdata['workspace'] . ".old; ";
	$script .= "mv " . $userdata['workspace'] . " " . $userdata['workspace'] . ".old; ";
	$script .= "chown -R " . $userdata['efn'] . " " . $userdata['workspace'] . ".old; ";
	$script .= "rm -fr " . $userdata['svn'] . ".old; ";
	$script .= "mv " . $userdata['svn'] . " " . $userdata['svn'] . ".old; ";
	print "SCRIPT: $script\n";
	exec($script);
	sleep(1);
	
	// Recreate workspace
	print "CREATE SVN WS:\n";
	user_create_svn_workspace($username);
	sleep(1);
	
	// Copy old data into new workspace and add to SVN
	print "COPY OLD DATA:\n";
	$copy_paths = array("*", ".c9", ".user", ".gcc.out", ".git", ".login", ".logout", ".theia");
	$svn_add_paths = array("*", ".gcc.out", ".login", ".logout");
	
	$script = "cd " . $userdata['home'] . "; ";
	foreach ($copy_paths as $path)
		$script .= "cp -R " . $userdata['workspace'] . ".old/$path " . $userdata['workspace'] . "; ";
	$script .= "cd " . $userdata['workspace'] . "; ";
	foreach ($svn_add_paths as $path)
		$script .= "svn add $path; ";
	$script .= "svn ci -m import .";
	run_as($username, $script);
	sleep(1);
	
	// Update stats file for new revision 1/2
	$stats_filename = "$conf_home_path/c9/stats/" . $userdata['efn'] . ".stats";
	$stats = file_get_contents($stats_filename);
	$stats = preg_replace("/('last_revision' \=\> )(\d+)/m", '${1}1', $stats);
	$stats = preg_replace("/('last_update_rev' \=\> )(\d+)/m", '${1}2', $stats);
	file_put_contents($stats_filename, $stats);
}

// (Attempt to) fix SVN conflicts for all users
function fixsvn($username) {
	global $conf_base_path;
	$username_esa = escapeshellarg($username);
	$vrijeme = 60 + rand(0, 100);
	proc_close(proc_open("$conf_base_path/bin/fixsvn $username_esa $vrijeme &", array(), $foo));
}

// Create git repository for user
function git_init($username) {
	$userdata = setup_paths($username);

	if (file_exists($userdata['workspace'] . "/.git"))
		exec("rm -fr " . $userdata['workspace'] . "/.git");

	$script  = "cd " . $userdata['workspace'] . "; ";
	$script .= "git init; ";
	$script .= "cp " . $userdata['home'] . "/.c9/git.exclude " . $userdata['workspace'] . "/.git/info/exclude; ";
	$script .= "git add .; git commit -m initial";
	run_as($username, $script);
}

// Syncronize user files from volatile-remote server to local folder
function sync_local($user) {
	global $users, $conf_home_path;
	
	if (!array_key_exists("volatile-remote", $users[$user])) return;
	if ($users[$user]["status"] == "active") return;
	
	$userdata = setup_paths($user);
	
	// Check if folder is in use
	$remote_home = $users[$user]["volatile-remote"] . substr($userdata['home'], strlen($conf_home_path));
	$remote_inuse = $remote_home . "/.in_use";
	$local_inuse = $userdata['home'] . "/.in_use";
	if (file_exists($local_inuse)) { 
		debug_log ("sync_local $user - failed (local in_use)");
		print "ERROR: in use\n"; 
		return; 
	}
	$is_remote_in_use = 0;
	exec("rsync -n $remote_inuse 2>&1 >/dev/null", $output, $is_remote_in_use);
	if ($is_remote_in_use == 0) { 
		debug_log ("sync_local $user - failed (remote in_use)");
		print "ERROR: in use\n"; 
		return; 
	}
	
	// Prevent user from logging in below this point
	debug_log ("sync_local $user");
	bfl_lock("user $user");
	
	// If timestamp is recent then no need to sync
	$remote_last_path = $users[$user]["volatile-remote"] . "/last/" . $userdata['efn'] . ".last";
	exec("rsync -a $remote_last_path /tmp/somelast");
	$remote_mtime = intval(file_get_contents("/tmp/somelast"));
	unlink("/tmp/somelast");
	
	// Start syncing
	$remote_svn_path = $users[$user]["volatile-remote"] . substr($userdata['svn'], strlen($conf_home_path));
	if (file_exists($userdata['home'])) touch($local_inuse);
	$local_home = substr($userdata['home'], 0, strlen($userdata['home']) - strlen($user));
	$local_svn = substr($userdata['svn'], 0, strlen($userdata['svn']) - strlen($user));
	exec("rsync -a $local_inuse $remote_inuse");
	
	debug_log ("sync_local $user home");
	exec("rsync -a $remote_home $local_home");
	debug_log ("sync_local $user svn");
	exec("rsync -a $remote_svn_path $local_svn");
	
	// Sync stats files
	$stats_paths = array("/home/c9/stats");
	foreach(scandir($stats_paths[0]) as $file) {
		$path = $stats_paths[0] . "/$file";
		if ($file != "." && $file != ".." && is_dir($path))
			$stats_paths[] = $path;
	}
	
	debug_log ("sync_local $user stats");
	foreach($stats_paths as $stats_path) {
		$stats_path .= "/$user.stats";
		$remote_stats_path = $users[$user]["volatile-remote"] . substr($stats_path, strlen($conf_home_path));
		// Stats files should never dissapear
		if (file_exists($remote_stats_path))
			exec("rsync -a $remote_stats_path $stats_path");
	}
	
	// Local folder is now ready to use, but remote is still locked
	if (!file_exists($local_inuse)) {
		touch($local_inuse);
		exec("rsync -a $local_inuse $remote_inuse");
	}
	unlink($local_inuse);
	
	bfl_unlock("user $user");
	debug_log ("sync_local $user finished");
}


function sync_remote($user) {
	global $users, $conf_home_path;
	
	if (!array_key_exists("volatile-remote", $users[$user])) return;
	
	$userdata = setup_paths($user);
	
	// Prevent user from logging in below this point
	bfl_lock("user $user");
	
	// Update remote timestamp
	$local_last_path = $conf_home_path . "/last/" . $userdata['efn'] . ".last";
	$remote_last_path = $users[$user]["volatile-remote"] . "/last/" . $userdata['efn'] . ".last";
	exec("rsync -a $local_last_path $remote_last_path");
	
	// Start syncing
	$remote_home = $users[$user]["volatile-remote"] . substr($userdata['home'], strlen($conf_home_path));
	$remote_home_trunc = substr($remote_home, 0, strlen($remote_home) - strlen($user));
	$remote_svn = $users[$user]["volatile-remote"] . substr($userdata['svn'], strlen($conf_home_path));
	$remote_svn_trunc = substr($remote_svn, 0, strlen($remote_svn) - strlen($user));
	
	// Create dirs
	if (strchr($users[$user]["volatile-remote"], ":")) {
		$host = substr($users[$user]["volatile-remote"], 0, strpos($users[$user]["volatile-remote"], ":"));
		$path = substr($remote_home, strpos($users[$user]["volatile-remote"], ":")+1);
		exec("ssh $host \"mkdir $path\"");
		exec("ssh $host \"chown " . $userdata['esa'] . " $path\"");
		$path = substr($remote_svn, strpos($users[$user]["volatile-remote"], ":")+1);
		exec("ssh $host \"mkdir $path\"");
		exec("ssh $host \"chown " . $userdata['esa'] . " $path\"");
	}
	
	touch($userdata['home'] . "/.in_use");
	$local_home = $userdata['home'];
	$local_svn = $userdata['svn'];
	exec("rsync -a $local_home $remote_home_trunc");
	exec("rsync -a $local_svn $remote_svn_trunc");
	
	$stats_paths = array("/home/c9/stats");
	foreach(scandir($stats_paths[0]) as $file) {
		$path = $stats_paths[0] . "/$file";
		if ($file != "." && $file != ".." && is_dir($path))
			$stats_paths[] = $path;
	}
	
	foreach($stats_paths as $stats_path) {
		$stats_path .= "/$user.stats";
		$remote_stats_path = $users[$user]["volatile-remote"] . substr($stats_path, strlen($conf_home_path));
		if (file_exists($stats_path))
			exec("rsync -a $stats_path $remote_stats_path");
	}
	
	// Unlock both folders
	unlink($userdata['home'] . "/.in_use");
	// Hack - we must assume that protocol is ssh :(
	if (strchr($users[$user]["volatile-remote"], ":")) {
		$host = substr($users[$user]["volatile-remote"], 0, strpos($users[$user]["volatile-remote"], ":"));
		$path = substr($remote_home, strpos($users[$user]["volatile-remote"], ":")+1);
		exec("ssh $host \"rm $path/.in_use\"");
	} else
		unlink("$remote_home/.in_use");

	bfl_unlock("user $user");
}




// --------------------
// BIG FUCKN LOCK (BFL)
// --------------------

function bfl_lock($lock = "all", $take_lock = true) {
	global $action;
	
	$bfl_file = "/tmp/webide.bfl";
	$wait = 100000; // Initially wait 0.1s
	$wait_inc = 100000; // Every time increase interval by 0.1s
	$wait_add = $wait_inc;
	$ultimate_limit = 100000000; // Break in after 100s

	if (file_exists($bfl_file))
	while (in_array($lock."\n", file($bfl_file))) {
		debug_log("$action ceka na bfl $lock pid ".getmypid());
		print "Čekam na bfl - ak\n";
		usleep($wait);
		$wait += $wait_add;
		$wait_add += $wait_inc;
		//if ($wait >= $ultimate_limit) break;
	}
	
	if ($take_lock) {
//		debug_log("$action stavlja lock $lock pid ".getmypid());
		file_put_contents($bfl_file, file_get_contents($bfl_file) . $lock . "\n");
	}
}

function bfl_unlock($lock = "all") {
	global $action;
	
	$bfl_file = "/tmp/webide.bfl";
	$new_locks = "";
	if (file_exists($bfl_file))
	foreach(file($bfl_file) as $m_lock)
		if ($m_lock !== $lock . "\n") $new_locks .= $m_lock;
	file_put_contents($bfl_file, $new_locks);
}


// ----------------------------------------
//    FUNCS FOR CONFIG AND OTHER FILES
// ----------------------------------------

// Replace some strings in a file with data of currently logged in user
function personalize($username, $infile, $outfile) {
	global $conf_base_path, $users, $conf_port_lower, $conf_port_upper;
	
	$port = $users[$username]['port'];
	$debug_port = ( $port - $conf_port_lower ) * 2 + $conf_port_upper;
	if (array_key_exists('realname', $users[$username])) 
		$realname = $users[$username]['realname'];
	else
		$realname = $username;

	$content = file_get_contents($infile);
	$content = str_replace("USERNAME", $username, $content);
	$content = str_replace("FULLNAME", $realname, $content);
	$content = str_replace("NODEPORT", $port, $content);
	$content = str_replace("DEBUGPORT", $debug_port, $content);
	file_put_contents($outfile, $content);
}

// Read users file
function read_files() {
	global $conf_base_path, $users;
	
	
	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));
}

// Write users file
function write_files() {
	global $conf_base_path, $users;
	
	if (empty($users)) return;

	$users_file = $conf_base_path . "/users";
	file_put_contents( $users_file, "\$users = ". var_export($users, true) . ";" );
}

// Create nginx config from $users
// TODO It would be better if each user have their own config file...
function write_nginx_config() {
	global $users, $conf_nginx_conf_path, $conf_base_path, $conf_ssh_tunneling;
	
	$config = "";
	$active_conf = file_get_contents($conf_base_path . "/nginx.active.conf");
	$inactive_conf = file_get_contents($conf_base_path . "/nginx.inactive.conf");
	
	foreach ($users as $username => $options) {
		if ($options['status'] == "active") $code = $active_conf;
		if ($options['status'] == "inactive") $code = $inactive_conf;
		
		$user_conf = str_replace("USERNAME", $username, $code);
		$user_conf = str_replace("HTPASSWD", "$conf_base_path/htpasswd/$username", $user_conf);

		if ($conf_ssh_tunneling) 
			$user_conf = str_replace("SERVER", "127.0.0.1", $user_conf);
		else if (array_key_exists("server", $options)) 
			$user_conf = str_replace("SERVER", $options['server'], $user_conf);
		else 
			$user_conf = str_replace("SERVER", "127.0.0.1", $user_conf);

		if (array_key_exists("port", $options)) 
			$user_conf = str_replace("PORT", $options['port'], $user_conf);
		// Port is not set but required! Just avoid invalid config...
		else 
			$user_conf = str_replace("PORT", "12345", $user_conf);
		
		$config .= $user_conf . "\n";
		
		if (array_key_exists("collaborate", $options) && $options['status'] === "active")
		foreach ($options['collaborate'] as $partner) {
			$partner_port = 0;
			foreach($users as $maybe_partner => $partner_options) {
				if ($partner == $maybe_partner) {
					$partner_port = $partner_options['port'];
					$partner_server = $partner_options['server'];
				}
			}
			if ($partner_port == 0) continue;
			if ($conf_ssh_tunneling) $partner_server = "127.0.0.1";
			
			if ($options['status'] == "active") $code = $active_conf;
			$user_conf = str_replace("USERNAME", "$username-$partner", $code);
			$user_conf = str_replace("HTPASSWD", "$conf_base_path/htpasswd/$username", $user_conf);
			$user_conf = str_replace("SERVER", $partner_server, $user_conf);
			$user_conf = str_replace("PORT", $partner_port, $user_conf);
			$config .= $user_conf . "\n";
		}
		
	}

	$nginx_final_config = str_replace( "# --- HERE ---", $config, file_get_contents($conf_base_path . "/nginx.skeleton.conf") );
	
	// Find php socket file
	$sock_file = `find /var/run/ | grep php | grep sock`;
	$nginx_final_config = str_replace( "SOCKET_FILENAME", $sock_file, $nginx_final_config );
	
	file_put_contents($conf_nginx_conf_path, $nginx_final_config);
	$retval = 0; $output = "";
	exec("service nginx reload", $output, $retval);
	if ($retval > 0 ) {
		echo "ERROR: nginx reload failed\n";
		exit(1);
	}
}



// --------------------
//    HELPER FUNCS 
// --------------------


// Find available port for nodejs
function find_free_port() {
	global $conf_port_lower, $conf_port_upper, $users;

	// Uzimamo random port da bismo umanjili vjerovatnoću race-condition gdje dva korisnika slučajno dobiju
	// isti port prije nego što je startovan odgovarajući node server
	$found = $port = 0;
	$tried = array();
	while ($found == 0) {
		do {
			$port = rand($conf_port_lower, $conf_port_upper);
		} while(in_array($port, $tried));
		
		$taken = false;
		foreach ($users as $username => $options) {
			if ($options['status'] == "active" && is_local($options['server']) && $options['port'] == $port)
				$taken = true;
		}
		$retval = 0; $output = "";
		if (!$taken)
			exec("netstat -n -t -l -4 | grep $port > /dev/null", $output, $retval);
		if ($retval > 0) {
			$found=$port;
		}
		array_push($tried, $port);
		if (count($tried) >= $conf_port_upper - $conf_port_lower - 1) $found=-1;
	}
	if ($found == -1) {
		echo "ERROR: No available ports\n";
		exit(1);
	}
	return $found;
}

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
				return $time; 
			}
		}
	} else if ($is_control_node) {
		$last = run_on($svn_node_addr, "$conf_base_path/bin/webidectl last " . $userdata['esa']);
		return intval($last);
	}
	
	// This is too slow!
	//$time = exec("find $student_workspace -type f -printf '%T@\n' | sort -n | tail -1"); // Timestamp of last modified file
	//return $time;;
	return 0;
}

// Write msg to debug log
function debug_log($msg) {
	global $conf_base_path;
	$time = date("d. m. Y. H:i:s");
	$msg = escapeshellarg($msg);
	`echo $time $msg >> $conf_base_path/log/webidectl.log`;
}
