<?php

// =========================================
// WEBIDECTL.PHP
// C9@ETF project (c) 2015-2019
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


// Following webidectl commands are not locked with bfl
$skip_bfl = array(
	// Readonly commands (for webide files)
	"list-active", "last", "server-stats", "is-node-up", "help", "reset-nginx", "svnrestore",
	// Commands that happen very often and don't really need to be locked
	"last-update", "broadcast",
	// Commands that take a long time and are executed on a regular basis
	// but users complain if they can't login during that time
	"update-all-stats", "culling", "verify-all-users", "fix-svn", "kill-inactive", "git-commit", "disk-cleanup", "clean-inodes", "storage-nightly",
	// Implement locking manually (for performance reasons)
	"verify-user", "logout"
);


srand(make_seed());

// BFL - Big Fucking Lock
if (!in_array($action, $skip_bfl))
	bfl_lock();


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
		$key = $argv[3];
		$value = $argv[4];
		if ($value === "-")
			unset($users[$user][$key]);
		else
			$users[$user][$key] = $value;
		write_files();
		
		if ($is_control_node) {
			foreach($conf_nodes as $node) {
				if (!is_local($node['address']))
					run_on($node['address'], "$conf_base_path/bin/webidectl change-user " . $userdata['esa'] . " " . escapeshellarg($key) . " " . escapeshellarg($value));
			}
		}
		break;
	
	// Login or create user
	case "login":
		$ip_address = $argv[3]; $password = "";
		
		if ($is_control_node) {
			print "Password: ";
			$password = fgets(STDIN);
		}

		if (array_key_exists($username, $users)) {
			if ($users[$username]["status"] == "active") {
				debug_log ("already logged in $username (".$users[$username]["server"]." ".$users[$username]["port"].")");
				print $users[$username]["port"]; // Already logged in, print port
				`date +%s > /tmp/already-$username`;
			} else if ($users[$username]["status"] == "inactive") {
				activate_user($username, $password, $ip_address);
				if (file_exists("/tmp/already-$username")) unlink("/tmp/already-$username");
			}
		}
		else {
			create_user($username, $password);
			activate_user($username, $password, $ip_address);
		}
		break;
	
	// Create user
	case "add-user":
		if ($argc < 3) 
			print "ERROR: Wrong number of parameters.\n";
		else {
			$password = $argv[3];

			if (array_key_exists($username, $users))
				print "ERROR: User $username already exists\n";
			else {
				create_user($username, $password);
				if ($argc>3) {
					$users[$username]['realname'] = $argv[4];
					if ($argc>4) $users[$username]['email'] = $argv[5];
					write_files();
				}
			}
		}
		break;
	
	// Logout user
	case "logout":
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
		bfl_lock();
		read_files();
		deactivate_user($username); // Force all logout operations, even if we list user as not logged in
		bfl_unlock();
		unlink("/tmp/already-$username");
		break;

	// Just stop node
	case "stop-node":
		stop_node($username, false);
		break;

	// Move user to another server
	case "kick-user":
		kick_user($username);
		break;

	// Remove user from system
	case "remove":
		if (array_key_exists($username, $users)) {
			if ($users[$username]["status"] == "active") {
				deactivate_user($username);
			}
			
			remove_user($username);
		}
		if ($is_control_node)
			write_nginx_config();
		write_files();
		break;

	// Add a user with local authentication (htpasswd)
	case "add-local-user":
		if ($argc != 4) 
			print "ERROR: Wrong number of parameters.\n";
		else {
			$password_esa = escapeshellarg($argv[3]);
			// This is a different path from $userdata['htpasswd'] !
			$htpasswd = $conf_base_path . "/localusers/" . $userdata['efn'];

			exec("htpasswd -bc $htpasswd " . $userdata['esa'] . " $password_esa 2>&1");
			exec("chown $conf_nginx_user $htpasswd");
			print "Created local user $username\n";
		}
		break;
	
	// Regular cleanup operation every hour
	case "culling":
		// FIXME This is done only on control server
		// If memory is high on control it's probably high on all others, due to load balancing
		$active_users = 0;
		foreach($users as $user) if ($user['status'] == "active") $active_users++;
		if ($active_users > 170) break; // It just manufactures more load... :( FIXME
		
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
						bfl_unlock();
						sleep($sleep);
						bfl_lock();
					}
				}
			}
			
			if (!in_array("compute", $node['type'])) continue;
			
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
					bfl_unlock();
					sleep($sleep);
					bfl_lock();
				}
			}
		}
		bfl_unlock();
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
			if ($users[$username]["status"] == "inactive")
				reset_config($username);
			else
				print "ERROR: User $username logged in\n";
		} else
			print "ERROR: User $username doesn't exist\n";
		break;
	
	// Reinitialize git repo if exists
	case "git-init":
		if (array_key_exists($username, $users)) {
			if ($is_storage_node)
				git_init($username);
			else
				run_on($storage_node_addr, "$conf_base_path/bin/webidectl git-init " . $userdata['esa']);
		} else
			print "ERROR: User doesn't exist\n";
		break;

	// Rewrite nginx config and restart (useful in case of corruption)
	case "reset-nginx":
		/*foreach ($active_users as $username => $act) {
			if (array_key_exists($username, $inactive_users)) {
				unset($inactive_users[$username]);
				write_files();
			}
		}*/
		if ($is_control_node)
			write_nginx_config();
		break;

	// Restart services for a logged-in user (if neccessary)
	case "verify-user":
		if (array_key_exists($username, $users) && $users[$username]["status"] == "active")
			verify_user($username);
		break;

	// Just start syncsvn
	case "syncsvn":
		if ($is_svn_node)
			syncsvn($username);
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
			print "$username\t$nice_period\n";
		}
		break;

	// Logout all users and kill all processes that look related
	case "clear-server":
		clear_server();
		break;

	// Update stats for a user or a list of users (listed in a plaintext file)
	case "update-stats":
		// Single user
		if (array_key_exists($username, $users)) {
			if ($is_svn_node)
				exec("$conf_base_path/bin/userstats " . $userdata['esa']);
			else if ($is_control_node)
				run_on($svn_node_addr, "$conf_base_path/bin/userstats " . $userdata['esa']);
		}
			
		// Here second param is actually a file with list of users for statistics update
		$flist = $argv[2];
		$tmplist = array();
		foreach(file($flist) as $stats_user) {
			if (!array_key_exists($stats_user, $users))
				continue; // Skip unknown user
			$username_esa = escapeshellarg($stats_user);
			if ($is_svn_node)
				exec("$conf_base_path/bin/userstats $username_esa");
			else if ($is_control_node)
				run_on($svn_node_addr, "$conf_base_path/bin/userstats $username_esa");
		}
		break;

	// Update SVN stats for all users (quite long)
	case "update-all-stats":
		foreach ($users as $username => $options) {
			// We can safely update stats for logged-in users
			print "User $username\n";
			$username_esa = escapeshellarg($username);
			if ($is_svn_node)
				exec("$conf_base_path/bin/userstats $username_esa");
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
				if (!file_exists($workspace . "/.git")) {
					print "Creating git for user $username...\n";
					git_init($username);
				}
				run_as($username, "cd $workspace; git add --all .; git commit -m \"$msg\" .");
			}
		else
			run_on($storage_node_addr, "$conf_base_path/bin/webidectl git-commit");
		break;

	// (Attempt to) fix SVN conflicts for all users
	case "fix-svn":
		if ($is_svn_node) {
			foreach($users as $username => $options)
				fixsvn($username);
			sleep(5);
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
		else
			user_reinstall_svn($username);
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
			exec("$conf_base_path/bin/userstats " . $userdata['esa']);
			print "Reinstall svn\n";
			user_reinstall_svn($username);		
		}
		break;
	
	// Call user_reset_svn if inode count goes big (which can be a huge problem if all inodes are used)
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
				bfl_lock();
				exec("$conf_base_path/bin/userstats " . escapeshellarg($username));
				print "Reinstall svn\n";
				user_reinstall_svn($username);
			
				// Inode statistics update (TODO)
				
				// Release lock for 5 seconds so users can do stuff
				bfl_unlock();
				sleep(5);
				bfl_lock();
				read_files();
				bfl_unlock();
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
			if (!array_key_exists("collaborate", $users[$username]))
				$users[$username]["collaborate"] = array($partner);
			else if (!in_array($partner, $users[$username]["collaborate"]))
				$users[$username]["collaborate"][] = $partner;
			write_files();
			if ($is_control_node)
				write_nginx_config();
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
			// [loadavg] [used_memory] [logged_in_users] [active_users] [free_disk] [free_inodes]
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
		sleep(60);
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

	// Revert to older revision on svn
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
	case "storage-nightly":
		if ($argc > 2 && $argv[2] == "all-stats")
			$all_stats = true;
		else
			$all_stats = false;
		
		$total = count($users);
		$current=1;
		$total_usage_stats = array();
		
		// shuffle_assoc
		$keys = array_keys($users);
		shuffle($keys);
		$users_shuffled = array();
		foreach ($keys as $key)
			$users_shuffled[$key] = $users[$key];

		// First pass - update stats, reinstall svn if neccessary
		foreach ($users_shuffled as $username => $options) {
			if (file_exists("/tmp/storage_nightly_skip")) break;
			if ($username == "") continue;
			
			// We can safely update stats for logged-in users
			print "$username ($current/$total) - ".date("d.m.Y H:i:s")."\n";
			$current++;
			$userdata = setup_paths($username);
			$username_esa = $userdata['esa'];
			$workspace = $userdata['workspace'];
			if (!file_exists($workspace)) {
				print "Workspace not found for $username...\n";
				continue;
			}
			
			// Clean core files
			print run_as($username, "cd $workspace; find . -name \"*core*\" -exec svn delete {} \; ; svn ci -m corovi .");
			print run_as($username, "cd $workspace; find . -name \"*core*\" -delete");

			print shell_exec("$conf_base_path/bin/userstats $username_esa");
			
			// Git commit
			$msg = date("d.m.Y", time() - 60*60*24);
			if (!file_exists($workspace . "/.git")) {
				print "Creating git for user $username...\n";
				git_init($username);
			}
			print run_as($username, "cd $workspace; git add --all .; git commit -m \"$msg\" .");
			
			// Clean inodes
			bfl_lock();
			read_files();
			bfl_unlock();
			
			$total_usage_stats[$username]['svn'] = false;
			$total_usage_stats[$username]['inodes'] = false;
			$total_usage_stats[$username]['ws.old'] = false;
			$total_usage_stats[$username]['svn.old'] = false;
			$total_usage_stats[$username]['old.inodes'] = false;
			
			if ($users[$username]["status"] == "active") {
				print "User $username is online! Not cleaning inodes\n";
				continue;
			}
			
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
					print "$username - inodes ".$total_usage_stats[$username]['inodes']." > $conf_max_user_inodes";
					$do_reinstall = true;
				}
			}
			if (!$do_reinstall && !$nochange && $users[$username]["status"] != "active" && $conf_max_user_svn_disk_usage > 0) {
				$total_usage_stats[$username]['svn'] = intval(shell_exec("du -s $usersvn"));
				if ($total_usage_stats[$username]['svn'] > $conf_max_user_svn_disk_usage) {
					print "$username - svn disk usage ".$total_usage_stats[$username]['svn']." kB > $conf_max_user_svn_disk_usage kB";
					$do_reinstall = true;
				}
			}
			
			if ($do_reinstall) {
				$total_usage_stats[$username]['old.inodes'] = $total_usage_stats[$username]['inodes'];
				if ($total_usage_stats[$username]['svn'])
					$total_usage_stats[$username]['svn.old'] = $total_usage_stats[$username]['svn'];
				print " - resetting svn\n";
				print "Update stats\n";
				bfl_lock();
				print exec("$conf_base_path/bin/userstats " . escapeshellarg($username));
				print "\nReinstall svn\n";
				user_reinstall_svn($username);
				$total_usage_stats[$username]['svn'] = intval(shell_exec("du -s $usersvn"));
			
				// Inode statistics update (TODO)
				
				// Release lock for 5 seconds so users can do stuff
				bfl_unlock();
			}
			
			$user_ws_backup = setup_paths($username)['workspace'] . ".old";
			if (file_exists($user_ws_backup))
				$total_usage_stats[$username]['ws.old'] = intval(shell_exec("du -s $user_ws_backup"));
			$user_svn_backup = setup_paths($username)['svn'] . ".old";
			if (file_exists($user_svn_backup) && !$total_usage_stats[$username]['svn.old'])
				$total_usage_stats[$username]['svn.old'] = intval(shell_exec("du -s $user_svn_backup"));
				
			// Check disk usage
			do {
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

				if ($home_usage >= $conf_limit_diskspace * 5) break;
				print "Disk usage $home_usage MB < " . ($conf_limit_diskspace * 5) . "MB\n";
				print "Waiting until disk is freed\n\n";
				sleep(120);
			} while (1);
		}
		
		// Do we need cleanup?
		print "\n\nCLEANUP USERS\n=============\n\n";
		
		// Second pass - delete backup workspace or svn if neccessary
		$tries = 0; $max_tries = 100; $min_backup_for_erase = 30000;
		print "HU $home_usage\n";
		$current = 1;

		foreach ($users_shuffled as $rand_user => $options) {
			if ($rand_user == "") continue;
			if ($conf_diskspace_cleanup <= 0 || $home_usage > $conf_diskspace_cleanup) break;

			print "Cleanup: $rand_user ($current/$total) - ".date("d.m.Y h:i:s")."\n";
			$current++;
			$user_ws_backup = setup_paths($rand_user)['workspace'] . ".old";
			print "UWB $user_ws_backup\n";
			if (file_exists($user_ws_backup)) {
				$backup_size = $total_usage_stats[$rand_user]['ws.old'];
				if ($backup_size === false) {
					$total_usage_stats[$username]['ws.old'] = intval(shell_exec("du -s $user_ws_backup"));
					$backup_size = $total_usage_stats[$rand_user]['ws.old'];
				}
				print "Size $backup_size\n";
				if ($backup_size > $min_backup_for_erase) {
					`rm -fr $user_ws_backup`;
					$stats = server_stats();
					$total_usage_stats[$rand_user]['ws.old'] = 0;
				}
			} else
				print "Not exists\n";
				
			$user_svn_backup = setup_paths($rand_user)['svn'] . ".old";
			print "USB $user_svn_backup\n";
			if (file_exists($user_svn_backup)) {
				$backup_size = $total_usage_stats[$rand_user]['svn.old'];
				print "Size $backup_size\n";
				if ($backup_size === false) {
					$total_usage_stats[$username]['svn.old'] = intval(shell_exec("du -s $user_svn_backup"));
					$backup_size = $total_usage_stats[$rand_user]['svn.old'];
				}
				if ($backup_size > $min_backup_for_erase) {
					`rm -fr $user_svn_backup`;
					$stats = server_stats();
					$total_usage_stats[$rand_user]['svn.old'] = 0;
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
		
		// Output usage stats to a file
		print "\n\nUSAGE STATS\n===========\n\n";
		$fp = fopen('/usr/local/webide/log/usage_stats.txt', 'w');
		fwrite($fp, "Root usage: $root_usage\nHome usage: $home_usage\n\n");
		fwrite($fp, "USER            WS      WS.OLD  SVN     SVN.OLD SVN.INODES  STATS\n");
		$users_sorted = $users;
		ksort($users_sorted);
		$total_ws = $total_ws_old = $total_svn = $total_svn_old = $total_stats = 0;
		
		$stats_paths = array("/home/c9/stats");
		foreach(scandir($stats_paths[0]) as $file) {
			$path = $stats_paths[0] . "/$file";
			if ($file != "." && $file != ".." && is_dir($path))
				$stats_paths[] = $path;
		}
		
		foreach ($users_sorted as $username => $options) {
			if ($username == "") continue;
			print "Usage stats: $username - ".date("d.m.Y h:i:s")."\n";
			$user_ws = setup_paths($username)['workspace'];
			$total_usage_stats[$username]['ws'] = intval(shell_exec("du -s $user_ws"));

			$user_ws_backup = $user_ws . ".old";
			if (!file_exists($user_ws_backup)) $total_usage_stats[$username]['ws.old'] = 0;
			else if ($total_usage_stats[$username]['ws.old'] === false) {
				if ($all_stats)
					$total_usage_stats[$username]['ws.old'] = intval(shell_exec("du -s $user_ws_backup"));
				else
					$total_usage_stats[$username]['ws.old'] = "?";
			}

			$user_svn = setup_paths($username)['svn'];
			if ($total_usage_stats[$username]['svn'] === false || $total_usage_stats[$username]['svn'] == "?") {
				if ($all_stats)
					$total_usage_stats[$username]['svn'] = intval(shell_exec("du -s $user_svn"));
				else
					$total_usage_stats[$username]['svn'] = "?";
			}
			if ($total_usage_stats[$username]['inodes'] === false) $total_usage_stats[$username]['inodes'] = "?";

			$user_svn_backup = $user_svn . ".old";
			if (!file_exists($user_svn_backup)) $total_usage_stats[$username]['svn.old'] = 0;
			else if ($total_usage_stats[$username]['svn.old'] === false) {
				if ($all_stats)
					$total_usage_stats[$username]['svn.old'] = intval(shell_exec("du -s $user_svn_backup"));
				else
					$total_usage_stats[$username]['svn.old'] = "?";
			}
			
			$stats_usage = 0;
			foreach($stats_paths as $path) {
				$stats_file = $path . "/$username.stats";
				if (file_exists($stats_file)) $stats_usage += filesize($stats_file);
			}
			$stats_usage /= 1024;
			
			fwrite($fp, sprintf("%-16s%-8d%-8d%-8d%-8d%-12d%d\n", $username, $total_usage_stats[$username]['ws'], $total_usage_stats[$username]['ws.old'], $total_usage_stats[$username]['svn'], $total_usage_stats[$username]['svn.old'], $total_usage_stats[$username]['inodes'], $stats_usage));
			$total_ws += $total_usage_stats[$username]['ws'];
			$total_ws_old += $total_usage_stats[$username]['ws.old'];
			$total_svn += $total_usage_stats[$username]['svn'];
			$total_svn_old += $total_usage_stats[$username]['svn.old'];
			$total_stats += $stats_usage;
		}
		fwrite($fp, "\n\n");
		fwrite($fp, sprintf("%-14s%-10d%-8d%-10d%-8d%-12d%d\n", "TOTAL", $total_ws, $total_ws_old, $total_svn, $total_svn_old, 0,  $total_stats));
		fwrite($fp, "SUM TOTAL: " . ($total_ws+$total_ws_old+$total_svn+$total_svn_old+$total_stats) . "\n");
		$nr_users = count($users_sorted);
		fwrite($fp, sprintf("%-16s%-8d%-8d%-8d%-8d%-12d%d\n", "AVERAGE", $total_ws/$nr_users, $total_ws_old/$nr_users, $total_svn/$nr_users, $total_svn_old/$nr_users, 0,  $total_stats/$nr_users));
		fclose($fp);
		break;
		
	case "sync-local":
		if (!$is_storage_node) break;
		if (!array_key_exists("volatile-remote", $users[$user])) break;
		if ($users[$user]["status"] == "active") break;
		
		// Check if folder is in use
		$remote_home = $users[$user]["volatile-remote"] . substr($userdata['home'], strlen($conf_home_path));
		$remote_inuse = $remote_home . "/.in_use";
		$local_inuse = $userdata['home'] . "/.in_use";
		if (file_exists($local_inuse)) { 
			print "ERROR: in use\n"; 
			break; 
		}
		$is_remote_in_use = 0;
		exec("rsync -n $remote_inuse 2>&1 >/dev/null", $output, $is_remote_in_use);
		if ($is_remote_in_use == 0) { print "ERROR: in use\n"; break; }
		
		// If timestamp is recent then no need to sync
		$last_path = $conf_home_path . "/last/" . $userdata['efn'] . ".last";
		$local_mtime = intval(file_get_contents($last_path));
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
		
		if ($remote_mtime - $local_mtime < 5 && file_exists($userdata['home'])) break; // 5 seconds allowed for syncing time
		
		exec("rsync -a $remote_home $local_home");
		exec("rsync -a $remote_svn_path $local_svn");
		
		// Sync stats files
		$stats_paths = array("/home/c9/stats");
		foreach(scandir($stats_paths[0]) as $file) {
			$path = $stats_paths[0] . "/$file";
			if ($file != "." && $file != ".." && is_dir($path))
				$stats_paths[] = $path;
		}
		
		foreach($stats_paths as $stats_path) {
			$stats_path .= "/$username.stats";
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
		
		break;
		
	case "sync-remote":
		if (!$is_storage_node) break;
		if (!array_key_exists("volatile-remote", $users[$user])) break;
		
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
			$stats_path .= "/$username.stats";
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

		break;
	
	case "help":
		print "webidectl.php\n\nUsage:\n";
		print "\tlogin username \t- doesn't check password!\n";
		print "\tlogout username\n";
		print "\tstop-node username \t\t- stop nodejs without logging out user\n\t\t\t\t\t  can be called even if user is listed as not logged in\n";
		print "\tadd-user username \t- creates data for new user\n\t\t\t\t\t  will be called automatically by login if neccessary\n";
		print "\tadd-local-user username password - adds user into local userlist (doesn't call add-user)\n";
		print "\tremove username \t\t- remove user data from system\n";
		print "\tcollaborate username \t\t- start collab version of nodejs for user\n";
		print "\n";
		print "\tculling \t\t\t- logout users inactive for >120m, stop nodejs for >45m, shorter if low memory\n";
		print "\tkill-idle minutes type sleep\t- logout users inactive for minutes, type \"soft\" just stops nodejs,\n\t\t\t\t\t  pause sleep seconds for each user\n";
		print "\tkill-inactive \t\t\t- remove zombie users from other nodes (marked as logged out but still process running)\n";
		print "\n";
		print "\tlast username \t\t\t- time of users last activity (add \"nice\" for nicer output)\n";
		print "\tlast-update username \t\t- update time of last access to now\n";
		print "\tlist-active \t\t\t- list of currently logged in users\n";
		print "\tverify-user username \t\t- restart services that are not running for user\n";
		print "\tverify-all-users \t\t- this may be very long\n";
		print "\tis-node-up username \t\t- check if nodejs is running for user\n";
		print "\tserver-stats\n";
		print "\tbroadcast message\t\t- show message bubble to all users\n";
		print "\n";
		print "\treset-config username \t\t- revert Cloud9 configuration to default for user\n";
		print "\treset-nginx \t\t\t- rebuild nginx configuration file\n";
		print "\tdisk-cleanup \t\t\t- delete some backups to recover disk space\n";
		print "\n";
		print "\tgit-init username \t\t- create new Git repository for user (delete existing)\n";
		print "\tgit-commit username \t\t- commit all changes for user to Git\n";
		print "\tupdate-stats username \t\t- update usage statistics file for user\n";
		print "\tupdate-all-stats\t\t- this may be very long\n";
		print "\tfix-svn username \t\t- automatically fix all SVN problems for username\n";
		print "\tuser-reinstall-svn username \t- create new SVN repository for user (delete existing)\n";
		print "\tuser-reset-svn username \t- call update-stats then user-reinstall-svn\n";
		print "\tclean-inodes \t\t\t- call user-reset-svn for all users exceeding inode or disk usage limit\n";
		print "\tsvnrestore username path revision - restore older version of file from SVN\n";
		
		
		break;
}

// Cleanup
if (!in_array($action, $skip_bfl))
	bfl_unlock();
if (file_exists($watchfile)) unlink($watchfile);
//	write_files();	
exit(0);


// -------------------------------
//    HIGH LEVEL USER LIFECYCLE
// -------------------------------

// This can be used on any node
// If node type is control, it will run relevant commands on all other nodes

function activate_user($username, $password, $ip_address) {
	global $conf_defaults_path, $conf_base_path, $conf_c9_group, $conf_nodes, $users;
	global $conf_ssh_tunneling, $conf_port_upper, $conf_port_lower, $conf_my_address;
	global $conf_home_path;
	global $is_control_node, $is_compute_node, $is_svn_node, $svn_node_addr;
	
	$userdata = setup_paths($username);
	$password_esa = escapeshellarg($password);
	$port = 0;
	
	if ($is_control_node) {
		if (!check_limits(server_stats(), /* $output= */ true)) return;
		// v1 migrate
		if (!file_exists($userdata['home']))
			migrate_v1_v3($username);

		// Create htpasswd i.e. overwrite existing one (due to possible pwd change)
		exec("htpasswd -bc " . $userdata['htpasswd'] . " " . $userdata['esa'] . " $password_esa 2>&1");
		exec("chown " . $userdata['esa'] . ":$conf_c9_group " . $userdata['htpasswd']);
		chmod($userdata['htpasswd'], 0644);
		
		// Try to sync local folder against remote (if volatile-remote)
		if (array_key_exists("volatile-remote", $users[$username])) {
			if (file_exists($userdata['home'] . "/.in_use")) { 
				print "ERROR: in use\n"; 
				debug_log("Workspace in use for $username");
				return; 
			}
			
			bfl_unlock();
			
			if ($is_svn_node)
				$cmd = "$conf_base_path/bin/webidectl sync-local " . $userdata['esa'];
			else
				$cmd = "ssh $svn_node_addr \"$conf_base_path/bin/webidectl sync-local " . $userdata['esa'] . "\"";
			
			bfl_lock();
			read_files();
			
			$result = `$cmd`;
			if (strstr($result, "ERROR: in use")) {
				print "ERROR: in use\n"; 
				debug_log("Workspace in use for $username");
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
			if (is_local($node['address'])) $stats[1] += 4000000;
			
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
			return;
		}
		
		print "Best node $best_node value $best_value\n";
		file_put_contents("$conf_base_path/log/" . $userdata['efn'], "\n\n=====================\nStarting webide at: ".date("d.m.Y H:i:s")."\n\n", FILE_APPEND);
		$users[$username]['server'] = $best_node;

		// Let SVN know that user logged in
		// This must be done before syncsvn to avoid conflicts
		$script  = "date > " . $userdata['workspace'] . "/.login; ";
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
			$users[$username]['port'] = $port;
			start_node($username);
		} else {
			$port = run_on($best_node, "$conf_base_path/bin/webidectl login " . $userdata['esa'] . " $password_esa");
			
			// We can't allow nginx configuration to be invalid, ever
			$port = intval($port);
			while (intval($port) == 0) {
				run_on($best_node, "$conf_base_path/bin/webidectl logout " . $userdata['esa']);
				bfl_unlock();
				sleep(5);
				bfl_lock();
				read_files();
				$port = run_on($best_node, "$conf_base_path/bin/webidectl login " . $userdata['esa'] . " $password_esa");
			}
			print "Port at $best_node is $port\n";
			$users[$username]['port'] = $port;
			if ($conf_ssh_tunneling) {
				$found = -1;
				// Move to port below range
				$local_port = $port - ($conf_port_upper - $conf_port_lower);
				$users[$username]['port'] = $local_port;
				proc_close(proc_open("ssh -N -L $local_port:localhost:$port $best_node &", array(), $foo));
			}
		}
		
		// Update local user database
		$users[$username]['status'] = "active";
		$users[$username]['ip_address'] = $ip_address;
		debug_log ("activate_user $username $best_node $port $ip_address");
		
		write_files();
		write_nginx_config();
		
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
	}
	
	else {
		$users[$username]['status'] = "active";
		if ($is_svn_node)
			syncsvn($username);
			
		if ($is_compute_node) {
			$port = find_free_port(); 
			$users[$username]['server'] = $conf_my_address;
			$users[$username]['port'] = $port;
			start_node($username);
		}
		debug_log ("activate_user $username $port $ip_address");
		write_files();
	}
	
	// Port goes to stdout
	print $port;
}

function create_user($username, $password) {
	global $conf_base_path, $conf_nodes, $conf_c9_group, $conf_defaults_path, $users, $conf_home_path;
	global $is_storage_node, $is_control_node, $is_svn_node, $storage_node_addr;
	
	$forbidden_usernames = array('root', 'daemon', 'bin', 'sys', 'sync', 'games', 'man', 'lp', 'mail', 'news', 'uucp', 'proxy', 'www-data', 'backup', 'list', 'irc', 'gnats', 'nobody', 'libuuid', 'syslog', 'messagebus', 'landscape', 'sshd', 'c9test', 'c9');
	if (in_array($username, $forbidden_usernames)) {
		debug_log("forbidden username $username");
		print "ERROR: username $username not allowed\n";
		return;
	}
	
	$userdata = setup_paths($username);
	if ($is_control_node)
		debug_log("create_user $username");

	// Create user on storage node
	if ($is_storage_node) {
		if (!file_exists($conf_home_path . "/" . substr($userdata['efn'],0,1)))
			exec("mkdir " . $conf_home_path . "/" . substr($userdata['efn'],0,1));
		exec("useradd -d ". $userdata['home'] . " -g $conf_c9_group -k $conf_defaults_path/home -m " . $userdata['esa']);
		// For some reason files copied from default home aren't owned by user :(
		exec("chown -R " . $userdata['esa'] . ":$conf_c9_group ". $userdata['home']);
	} else {
		if ($is_control_node)
			run_on($storage_node_addr, "$conf_base_path/bin/webidectl add-user " . $userdata['esa'] . " " . escapeshellarg($password));
		exec("useradd -d " . $userdata['home'] . " -g $conf_c9_group " . $userdata['esa']);
	}
	
	// If this is control node, create user on other nodes
	if ($is_control_node) {
		foreach ($conf_nodes as $node) {
			if (!in_array("control", $node['type']) && !in_array("compute", $node['type']) && !in_array("svn", $node['type']))
				continue;
			if (!is_local($node['address']) && !in_array("control", $node['type']))
				run_on($node['address'], "$conf_base_path/bin/webidectl add-user " . $userdata['esa'] . " " . escapeshellarg($password));
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
	
	$users[$username] = array();
	$users[$username]['status'] = "inactive";
	write_files();
}

// The philosophy is that deactivate can be called on user who is marked inactive, 
// to stop whatever running services etc. for this user, except on svn node
function deactivate_user($username, $skip_svn = false) {
	global $users, $is_control_node, $is_compute_node, $conf_base_path, $is_svn_node, $svn_node_addr, $conf_svn_problems_log, $conf_ssh_tunneling;
	
	// Prevent overloading svn server during clear_server
	if (!$is_control_node && $is_svn_node)
		if ($users[$username]['status'] == "inactive") return;
	
	$userdata = setup_paths($username);
	debug_log ("deactivate_user $username");
	
	// If this is a simple compute node, just kill nodejs
	if ($is_compute_node && !$is_control_node) {
		stop_node($username, true); // $cleanup=true -- kill everything owned by user
		$users[$username]['status'] = "inactive"; 
		unset($users[$username]['collaborate']);
		unset($users[$username]['server']);
		unset($users[$username]['port']);
		write_files();
	}
		
	else if ($is_control_node) {
		
		// Update logout file
		$script = "date > " . $userdata['workspace'] . "/.logout";
		run_as($username, $script);

		// Remove user from nginx - this will inform user that they are logged out
		$server = $users[$username]['server'];
		$users[$username]['status'] = "inactive";
		$port = $users[$username]['port'];
		unset($users[$username]['collaborate']);
		unset($users[$username]['server']);
		unset($users[$username]['port']);
		write_nginx_config();
		
		// Stop nodejs on server where user is running
		if (is_local($server) || empty($server))
			stop_node($username, false);
		else {
			proc_close(proc_open("ssh $server \"$conf_base_path/bin/webidectl logout " . $userdata['esa'] . " &\" 2>&1 &", array(), $foo));
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
		$users[$username]['status'] = "inactive"; 
		write_files();

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
		fixsvn($username);
	}
	else if ($is_control_node) {
//		run_on($svn_node_addr, "$conf_base_path/bin/webidectl logout " . $userdata['esa']);
	}
	
	if ($is_control_node) {
		// Users file is updated only now, to prevent user from logging back in during other procedures
		// (BFL should prevent it but alas...)
		write_files();
		bfl_unlock();
		
		// Sync locally changed files to remote
		if (array_key_exists("volatile-remote", $users[$username])) {
			if ($is_svn_node)
				proc_close(proc_open("$conf_base_path/bin/webidectl sync-remote " . $userdata['esa'] . " 2>&1 &", array(), $foo));
			else
				proc_close(proc_open("ssh $svn_node_addr \"$conf_base_path/bin/webidectl sync-remote " . $userdata['esa'] . " &\" 2>&1 &", array(), $foo));
			debug_log("sync-remote $username");
		}
	}
}

function remove_user($username) {
	global $users, $conf_nodes, $conf_base_path, $conf_shared_path, $conf_home_path;
	global $is_storage_node, $is_control_node, $is_svn_node, $is_compute_node;
	
	$userdata = setup_paths($username);
	
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

	unset($users[$username]);
}

function verify_user($username) {
	global $conf_base_path, $is_control_node, $is_compute_node, $is_svn_node, $svn_node_addr, $users, $conf_home_path;
	
	$userdata = setup_paths($username);
	if (!array_key_exists('server', $users[$username])) return;
	$server = $users[$username]['server'];
	
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
		if ($nodeup) {
			if ($port > 2)
				print "Node running - Found port: $port\n";
			else
				$nodeup = false; // Invalid port, restart
		}
		
		// It isn't, restart
		if (!$nodeup) {
			if (file_exists($userdata['node_watch']))
				unlink($userdata['node_watch']);
			
			// This command will apparently kill some node servers that it shouldn't? too dangerous
			//exec("ps ax | grep tmux | grep " . $userdata['esa'] . " | grep -v grep | cut -c 1-5 | xargs kill");
			
			// Kill related processes
			stop_node($username, false);
			
			// Check to see if port is in use - sometimes race condition causes this situation
			$log_path = "$conf_base_path/log/" . $userdata['efn'];
			$inuse = `tail -20 $log_path | grep EADDRINUSE`;
			if (!empty(trim($inuse)) || $port <= 2) {
				$port = find_free_port(); 
				print "Starting node - Found port: $port\n";
				$users[$username]['port'] = $port;
				bfl_lock();
				write_files();
				if ($is_control_node) write_nginx_config();
				bfl_unlock();
			} else
				print "Restarting existing node ($port)\n";
			
			// Restart node
			start_node($username);
			debug_log ("restart node user $username port $port");
		}
	}
	else if ($is_control_node) {
		$output = run_on($server, "$conf_base_path/bin/webidectl verify-user " . $userdata['esa']);
		debug_log ("verify_user $username server $server output " .str_replace("\n", "", $output));
		if ($substr = strstr($output, "Found port:")) {
			$port = intval(substr($substr, 11));
			if (intval($port) == 0) $port = 1;
			$users[$username]['port'] = $port;
			debug_log ("resetting port to $port");
			bfl_lock();
			write_files();
			write_nginx_config();
			bfl_unlock();
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
			if (file_exists($userdata['svn_watch'])) 
				unlink($userdata['svn_watch']);
			stop_inotify($username); // New inotify will be started by syncsvn
			print "Starting syncsvn for $username...\n";
			syncsvn($username);
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
	
	$users[$username]['server'] = $best_node;
	
	write_files();
	write_nginx_config();
}



// -------------------------------
//    LOW LEVEL PROCESS MGMT
// -------------------------------

// These functions should be called only on relevant node type


// Start nodejs instance for user
function start_node($username) {
	global $conf_base_path, $conf_home_path, $users;
	
	$userdata = setup_paths($username);
	$useropts = $users[$username];
	
	$nodecmd     = "$conf_base_path/bin/startnode";
	$c9_path     = $userdata['home'] . "/fork";
	$port        = $useropts['port'];
	$listen_addr = $useropts['server'];
	$workspace   = $userdata['workspace'];
	$log_path    = "$conf_base_path/log/" . $userdata['efn'];
	$watch_path  = $userdata['node_watch'];
	
	$lastfile = $conf_home_path . "/last/" . $userdata['efn'] . ".last";
	
	touch($log_path);
	chown($log_path, $username);
	chmod($log_path, 0644);
	touch($lastfile);
	chown($lastfile, $username);
	chmod($lastfile, 0666);
	run_as($username, "$nodecmd $c9_path $port $listen_addr $workspace $log_path $watch_path");
}

// Stop nodejs instance and related user processes
// Note: if user is still logged in, node will be restarted automatically by web server
function stop_node($username, $cleanup) {
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
	
	exec ("rm -fr ".$userdata['home']."/.c9");
	run_as($username, "cp -R $conf_defaults_path/c9 " . $userdata['home'] . "/.c9");

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
		foreach(ps_ax($node['address']) as $process) {
			if (!strstr($process['cmd'], "node ") && !strstr($process['cmd'], "nodejs "))
				continue;
				
			// User possibly logged out in the meantime
			$username = $process['user'];
			if ($users[$username]['status'] == "inactive") continue;
			
			$time = last_access($username);
			if ($output)
				print "Username $username inactive ".round((time()-$time)/60, 2)." minutes\n";
			if (time() - $time > $minutes*60) {
				$server = $users[$username]['server'];
				if ($output) print "Stopping node on $server\n";
				if (is_local($server))
					stop_node($username, false);
				else
					proc_close(proc_open("ssh $server \"$conf_base_path/bin/webidectl stop-node " . escapeshellarg($username) . " &\" 2>&1 &", array(), $foo));
				
				if ($sleep > 0) {
					bfl_unlock();
					sleep($sleep);
					bfl_lock();
				}
			}
		}
	}
	bfl_unlock();
}


// kill "hard" means logout inactive users
function kill_idle_logout($minutes, $output, $sleep) {
	global $users;
	
	foreach ($users as $username => $options) {
		if ($options['status'] == "inactive") continue;
		
		$time = last_access($username);
		if ($output)
			print "Username $username inactive ".round((time()-$time)/60, 2)." minutes\n";
		if (time() - $time > $minutes*60) {
			deactivate_user($username);
			
			if ($sleep > 0) {
				bfl_unlock();
				sleep($sleep);
				bfl_lock();
				read_files();
			}
		}
	}
	bfl_unlock();
}

// Logout all users and kill/restart all processes related to webide
function clear_server() {
	global $conf_base_path, $users, $is_compute_node, $is_control_node, $svn_node_addr, $is_svn_node;
	
	debug_log("clear server");
	
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
		return;
	}
	
	// Special procedure for svn node, prevents excessive load
	if (!$is_control_node && $is_svn_node) {
		$were_active = array();
		
		// Just mark users as inactive and write files
		foreach ($users as $username => &$options) {
			if ($options['status'] == "active") {
				$options['status'] = "inactive";
				$were_active[] = $username;
			}
		}
		write_files();
		bfl_unlock();
		
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
		return;
	}

	// Continue with control node actions
	
	// Kill webidectl processes waiting to login
	$mypid = getmypid();
	foreach (ps_ax("127.0.0.1") as $process) {
		if ($process['pid'] != $mypid && strstr($process['cmd'], "webidectl") && strstr($process['cmd'], "php"))
			exec("kill ".$process['pid']);
	}
	
	// Logout all users
	foreach ($users as $username => $options) {
		if ($options['status'] == "active")
			deactivate_user($username, true);
	}
	
	// Force restart, since deactivate will just reload... this kills some hanging connections
	exec("service nginx restart");
	
	// Again, someone somehow reached the login page and tried to login
	foreach (ps_ax("127.0.0.1") as $process) {
		if ($process['pid'] != $mypid && strstr($process['cmd'], "webidectl") && strstr($process['cmd'], "php"))
			exec("kill ".$process['pid']);
		if (strstr($process['cmd'], "syncsvn.php"))
			exec("kill ".$process['pid']);
	}
	
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

	// Again write files, to nuke someone who managed to login
	write_files();
	
	// Again, someone cheated the race condition
	exec("service nginx restart");
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
	global $c9_user, $c9_group, $conf_base_path, $student_workspace, $student_svn;
	
	global $conf_base_path;
	
	$userdata = setup_paths($username);

	// Backup old data
	$script  = "chmod -R 755 " . $userdata['workspace'] . ".old; chmod -R 755 " . $userdata['svn'] . ".old; ";
	
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
	$copy_paths = array("*", ".c9", ".user", ".gcc.out", ".git", ".login", ".logout");
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
	$stats_filename = "/home/c9/stats/" . $userdata['efn'] . ".stats"; // FIXME! Hardcoded path
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





// --------------------
// BIG FUCKN LOCK (BFL)
// --------------------

function bfl_lock() {
	global $action;

	$bfl_file = "/tmp/webide.login.bfl";
	$wait = 100000; // Initially wait 0.1s
	$wait_inc = 100000; // Every time increase interval by 0.1s
	$wait_add = $wait_inc;
	$ultimate_limit = 100000000; // Break in after 100s

	while (file_exists($bfl_file)) {
		debug_log("$action ceka na bfl");
		print "Čekam na bfl\n";
		usleep($wait);
		$wait += $wait_add;
		$wait_add += $wait_inc;
		if ($wait >= $ultimate_limit) break;
	}
	
	exec("touch $bfl_file");
}

function bfl_unlock() {
	$bfl_file = "/tmp/webide.login.bfl";
	unlink($bfl_file);
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

// Detect type of current node, find where is storage node
function detect_node_type() {
	global $conf_nodes;
	global $is_control_node, $is_compute_node, $is_storage_node, $is_svn_node, $storage_node_addr, $svn_node_addr;

	$is_control_node = $is_compute_node = $is_storage_node = $is_svn_node = false;
	$storage_node_addr = $svn_node_addr = "";
	foreach ($conf_nodes as $node) {
		if (is_local($node['address'])) {
			if (in_array("control", $node['type']))
				$is_control_node = true;
			if (in_array("compute", $node['type']))
				$is_compute_node = true;
			if (in_array("storage", $node['type']))
				$is_storage_node = true;
			if (in_array("svn", $node['type']))
				$is_svn_node = true;
		} else {
			if (in_array("storage", $node['type']))
				$storage_node_addr = $node['address'];
			if (in_array("svn", $node['type']))
				$svn_node_addr = $node['address'];
		}
	}

}

?>
