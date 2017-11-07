<?php

// ADMIN/USER_TABLE.PHP - admin.pgp pluggable module for active table of users in a group with
// various stats per folder/project and real-time updating


function admin_user_table($group_id, $members, $backlink) {
	global $conf_limit_loadavg_web, $users;
	
	$assignments = $stats = $last_access = array();
	
	$assignment_folder_regex = "/^(T|Z|ZSR)\d+$/";
	
	// If we have a defined path, we will show the table just for this path
	$path_log = "";
	if (isset($_REQUEST['path'])) $path_log=$_REQUEST['path'];
	
	$loadavg = `cat /proc/loadavg | cut -d " " -f 1`;
	if ($loadavg > $conf_limit_loadavg_web)
		print "<p>Statistics per assignment is disabled due to high server load ($loadavg)</p>";
	else

	ksort($assignments);

	
	// Table display
	
	$show_others = isset($_REQUEST['show_others']) || (isset($_REQUEST['path']) && $_REQUEST['path'] != "TP" && $_REQUEST['path'] != "OR"); // FIXME
	?>
	<SCRIPT> var fullNames = {} </SCRIPT>
	<table id="user-stats-table" class="users-table <?php if (isset($_REQUEST['korisnik'])) print "single-user"; ?>" >
	<thead><tr><th>Full name</th><th>Last access time</th><th>Options</th>
	<?php

	if (!$show_others) {
		$url = "admin.php?";
		foreach ($_REQUEST as $key=>$value) {
			if ($url != "admin.php?") $url .= "&amp;";
			$url .= urlencode($key).'='.urlencode($value);
		}
		?>
		<th id="show_others">Others - <a href="<?=$url?>&amp;show_others=true">expand</a></th>
		<?php
	}
	print "</thead></tr>\n";
	foreach ($members as $login => $fullname) {
		if ($users[$login]['status'] == "active")
			$className = "";
		else 
			$className = "user-is-offline";
		?>
		<tr class="<?=$className?>">
		<td id="user-stats-table-<?=$login?>"><a href="?user=<?=$login?>&amp;backlink=<?=$backlink?>"><i class="fa fa-circle" style="color: rgba(0, 160, 0, 0)" id="color-ball-<?=$login?>"></i> <?=$fullname?></a></td>
		<td>/</td>
		<td>
			<a href="admin.php?user=<?=$login?>&amp;action=reset-conf&amp;return=<?=$backlink?>" onclick="return potvrda('Reset user config', '<?=$fullname?>');"> <img src="static/images/gear_wheel.png" width="16" height="16" alt="Reset" title="Reset user config"></a> &nbsp;
			<a href="admin.php?user=<?=$login?>&amp;action=logout&amp;return=<?=$backlink?>" onclick="return potvrda('Logout user', '<?=$fullname?>');"><img src="static/images/logout.png" width="16" height="16" alt="Logout" title="Logout user from system"></a> &nbsp;
			<a href="admin.php?user=<?=$login?>&amp;action=refresh-stats&amp;return=<?=$backlink?>" onclick="return potvrda('Update statistics', '<?=$fullname?>');"><img src="static/images/refresh.gif" width="16" height="16" alt="Update" title="Update user statistics"></a> &nbsp;
			<a href="admin.php?collaborate=<?=$login?>"><i class="fa fa-television"></i>
		</td>
		<SCRIPT>fullNames['<?=$login?>'] = '<?=$fullname?>';</SCRIPT>
		<?php
		if (!$show_others) { print "<td>/</td>\n"; }
		print "</tr>\n";
	}
	?>
	</table>
	<div id="user-stats-sample-options" style="display:none">
		<a href="admin.php?user=USERNAME&amp;action=reset-conf&amp;return=BACKLINK" onclick="return potvrda('Reset user config', 'REALNAME');"> <img src="static/images/gear_wheel.png" width="16" height="16" alt="Reset" title="Reset user config"></a> &nbsp;
		<a href="admin.php?user=USERNAME&amp;action=logout&amp;return=BACKLINK" onclick="return potvrda('Logout user', 'REALNAME');"><img src="static/images/logout.png" width="16" height="16" alt="Logout" title="Logout user from system"></a> &nbsp;
		<a href="admin.php?user=USERNAME&amp;action=refresh-stats&amp;return=BACKLINK" onclick="return potvrda('Update statistics', 'REALNAME');"><img src="static/images/refresh.gif" width="16" height="16" alt="Update" title="Update user statistics"></a> &nbsp;
		<a href="admin.php?collaborate=USERNAME"><i class="fa fa-television"></i></a></div>
	</div>
	
	<p>LEGEND:<br>
	<i class="fa fa-clock-o"></i> Total time spent (minutes)<br>
	<i class="fa fa-wrench"></i> Number of builds<br>
	<i class="fa fa-gear"></i> Number of runs (successful builds)<br>
	<i class="fa fa-check"></i> Last test results (if user tested the code)</p>
	
	<script type="text/javascript" src="/static/js/user_table.js"></script>
	<script type="text/javascript" src="/static/js/activity.js"></script>
	<script>
	var usersToLoad = [ <?php
		foreach ($members as $login => $fullname) {
			?>
			{ 'username' : '<?=$login?>', 'path' : '<?=$path_log?>' },
			<?php
		}
	?> ];
	userTableLoadAll();
	
	var last_active = {}, last_event_type = {};
	var last_build = {}, last_run = {}; // Timestamp to prevent updating last build time too often
	var frequency = 500; // Update frequency
	var colorChangeSpeed = 100; // Update frequency
	var retryParseAt = 2000; // Update frequency
	var colorLevels = {}
	
	var timenow = 0; // Need this to properly reference time from server
	var last_line = 0;
	
	initActive(function(item) {
		updateUserTableStats(item);
	}, frequency);
	
	setTimeout(updateColor, colorChangeSpeed);
	
	function updateUserTableStats(activityItem) {
		// Trigger activity
		var username = activityItem['username'];
		var file = activityItem['file'];
		var dist = timenow - last_active[username];
		
		// Is user in this table?
		var user_td = document.getElementById("user-stats-table-"+username);
		if (!user_td)
			return;
			
		// Update date/time field
		user_td = user_td.nextSibling;
		if (user_td.tagName != "TD") user_td = user_td.nextSibling;
		user_td.innerHTML = activityItem.datum;
		
		last_active[username] = timenow;
		colorLevels[username] = 100;
		
		// Handle login/logout
		if (file == ".login") {
			last_event_type[username] = "login";
			console.log("LOGIN "+username);
		} else if (file == ".logout") {
			last_event_type[username] = "logout";
			console.log("LOGOUT "+username);
		} else
			last_event_type[username] = "type";
	
		// Do we have this activity in table?
		var path = activityItem['path'];
		path = path.substring(1, path.length-1);
		var td_id = activityItem['username'] + "-" + path;
		var td = document.getElementById(td_id);
		if (!td) return;
		
		if (!global_stats.hasOwnProperty(username) || !global_stats[username])
			global_stats[username] = {};
		if (!global_stats[username].hasOwnProperty(path) || !global_stats[username][path])
			global_stats[username][path] = {};
		
		var pfile = activityItem['path'] + file;
		
		if (file == ".at_result")
			parse_at_result(username, pfile, td);
			
		if (file == ".output" || file == ".valgrind.out") {
			console.log("RUN "+username+" "+path);
			if (!last_run.hasOwnProperty(username) || timenow - last_run[username] > 5) {
				if (!global_stats[username][path].hasOwnProperty('builds_succeeded'))
					global_stats[username][path]['builds_succeeded'] = 1;
				else
					global_stats[username][path]['builds_succeeded']++;
				render_cell(username, path, td);
			}
			last_run[username] = timenow;
		}
			
		if (file == ".runme" || file == ".gcc.out") {
			console.log("BUILD "+username+" "+path);
			if (!last_build.hasOwnProperty(username) || timenow - last_build[username] > 5) {
				if (!global_stats[username][path].hasOwnProperty('builds_succeeded'))
					global_stats[username][path]['builds_succeeded'] = 1;
				else
					global_stats[username][path]['builds_succeeded']++;
				render_cell(username, path, td);
			}
			last_build[username] = timenow;
		}
	}
	function updateColor() {
		for (user in colorLevels) {
			var alpha = colorLevels[user] / 100.0;
			if (colorLevels[user] <= 10) alpha = 0;
			var ball = document.getElementById('color-ball-'+user);
			var tr = ball.parentElement.parentElement.parentElement;
			if (ball) {
				if (last_event_type[user] == "login") {
					ball.className = "fa fa-sign-in";
					ball.style.color = "rgb(0, 0, 0)";
					tr.className = "";
				} else if (last_event_type[user] == "logout") {
					ball.className = "fa fa-sign-out";
					ball.style.color = "rgb(160, 0, 0)";
					tr.className = "user-is-offline";
				} else {
					ball.className = "fa fa-circle";
					ball.style.color = "rgba(0, 160, 0, " + alpha + ")";
					colorLevels[user] -= 10;
					tr.className = "";
				}
			}
		}
		setTimeout(updateColor, colorChangeSpeed);
	}
	
	function parse_at_result(user, file, td) {
		console.log("parse_at "+user+" "+file);
		var xmlhttp = new XMLHttpRequest();
		var url = "services/file.php?user="+user+"&path="+file;
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				if (xmlhttp.responseText.length > 2 && xmlhttp.responseText != "ERROR: File doesn't exist\n") {
					var results = JSON.parse(xmlhttp.responseText);
					var status = "";
					var path = file.substring(1, file.lastIndexOf('/'));
					
					// Compile failed
					if (results.status == 3 || results.status == 6) {
						global_stats[user][path]['test_results'] = "0/0";
					}
					
					// Iterate through test results
					pwi_tests_total=pwi_tests_passed=0;
					for (var key in results.test_results) {
						var found_result = results.test_results[key];
						
						// Test statistics
						pwi_tests_total++;
						if (found_result && found_result.status == 1)
							pwi_tests_passed++;
					}
					
					global_stats[user][path]['test_results'] = "" + pwi_tests_passed + "/" + pwi_tests_total;
					console.log("USER "+user+": path: '"+path+"' result "+global_stats[user][path]['test_results']);
					render_cell(user, path, td);
				} else {
					setTimeout(function() { parse_at_result(user, file, td); }, retryParseAt);
				}
				
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	
	function render_cell(user, path, td) {
		console.log("render_cell "+user+" "+path);
		var backlink = "FIXME";
		// Render cell
		td.innerHTML = "";
		if (global_stats[user][path].hasOwnProperty('time') && global_stats[user][path]['time'] !== undefined)
			td.innerHTML += "<i class=\"fa fa-clock-o\"></i> " + global_stats[user][path]['time'];
		if (global_stats[user][path].hasOwnProperty('builds') && global_stats[user][path]['builds'] !== undefined)
			td.innerHTML += "<i class=\"fa fa-wrench\"></i> " + global_stats[user][path]['builds'];
		if (global_stats[user][path].hasOwnProperty('builds_succeeded') && global_stats[user][path]['builds_succeeded'] !== undefined)
			td.innerHTML += "<i class=\"fa fa-gear\"></i> " + global_stats[user][path]['builds_succeeded'];
		if (global_stats[user][path].hasOwnProperty('test_results') && global_stats[user][path]['test_results'] !== undefined)
			td.innerHTML += "<i class=\"fa fa-check\"></i> " + global_stats[user][path]['test_results'];
			
		td.innerHTML = "<a href=\"?user=" + user + "&amp;path=" + path + "&amp;backlink=" + backlink + "\">" + td.innerHTML + "</a>";
	}
	</SCRIPT>
	<?php
}


?>
