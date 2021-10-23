<?php

// ADMIN/STATS.PHP - pluggable modules for admin.php for showing various stats


// Webide usage stats
function admin_user_summary($user) {
	global $conf_home_path, $conf_data_path;
	
	$login = $user->login;
	
	$all = [ 'actions' => [], 'last_online' => 0 ];
	$activity_file = "$conf_data_path/user_activity/$login.json";
	if (file_exists($activity_file))
		$all = json_decode(file_get_contents($activity_file), true);
	
	$last_path = $conf_home_path . "/last/$login.last";
	if (file_exists($last_path)) $last_access = intval(file_get_contents($last_path));
	else $last_access = $all['last_online'];
	
	if (isset($_REQUEST['year'])) {
		if ($_REQUEST['year'] == "all")
			$user_courses = Course::forStudent($login, -1);
		else
			$user_courses = Course::forStudent($login, intval($_REQUEST['year']));
		
		// Fetch year names
		$year_names = [];
		foreach(Cache::getFile("years.json") as $year)
			$year_names[$year['id']] = $year['name'];
	}
	else 
		$user_courses = Course::forStudent($login);
	
	?>
	<p>Login: <b><?=$user->login?></b><br>
	Email: <b><?=$user->email?></b><br>
	Last access time: <b><?=date("d. m. Y. H:i:s", $last_access) ?></b><br>
	Last IP address: <b><?=$user->ipAddress?></b></p>
	<p>Enrolled into courses:</p>
	<ul>
	<?php 
	foreach($user_courses as $course) {
		print "<li><a href=\"?user=$login&amp;path=" . $course->folderName() . "\">" . $course->name;
		if (isset($_REQUEST['year'])) print " (".$year_names[$course->year].")";
		print "</a></li>\n";
	}
	?>
	</ul>
	<a href="?user=<?=$login?>&amp;year=all">Past enrolments</a> * <a href="?user=<?=$login?>&amp;action=enrol">Enrol</a></p>
	<h2>Recent activity</h2>
	<?php

	$start = 0;
	if (isset($_REQUEST['start'])) $start = intval($_REQUEST['start']);
	
	$activity = array_reverse($all['actions'], true);
	
	$count = 1; $limit = 30; $lasttime = 0; $output = [];
	$group = array("logout" => 0, "login" => 0, "items" => []);
	foreach($activity as $time => $action) {
		if ($start > 0 && $time > $start) continue;
		if (!is_array($action)) {
			if ($action == "logout")
				$group = array("logout" => $time, "login" => 0, "items" => []);
			else if ($action == "login") {
				$group['login'] = $time;
				$output[] = $group;
				if ($count++ > $limit) {
					//print "<li><a href=\"?user=$login&amp;start=" . ($time+1) . "\">&gt;&gt; Next</a></li>";
					break;
				}
			}
		} else {
			$action['time'] = $time;
			$group['items'][] = $action;
		}
/*		print "<li>" . date("d. m. Y. H:i:s", $time) . " - ";
		if (is_array($action)) {
			$url = "<a href=\"?user=$login&amp;path=" . urlencode(substr($action['path'], 1)) . "\">" . $action['path'] . "</a>";
			print $url . " (duration: " . nicetime($action['duration']) . ")</li>\n";
		} else
			print $action . "</li>\n";*/
	}
	
	?>
	
	<p>Activity:</p>
	<ul>
	<?php 
	foreach($output as $id => $group) {
		$session_duration = $group['logout'] - $group['login'];
		if ($group['logout'] == 0)
			$session_duration = 0;
		$jsid = "user_activity_$id";
		print "<li>";
		if (count($group['items']) > 0)
			print "<a href=\"#\" onclick=\"showhide('$jsid'); return false;\">";
		
		print date("d. m. Y. H:i:s", $group['login']) . " (" . nicetime($session_duration) . /*" logout ".date("d. m. Y. H:i:s", $group['logout']). */ ")\n";
		if (count($group['items']) > 0)
			print "</a><ul id='$jsid' style=\"display: none\">\n";
		foreach ($group['items'] as $action) {
			$path = $action['path'];
			if ($path[0] == '/') $path = substr($path, 1);
			if ($path[strlen($path)-1] == '/') $path = substr($path, 0, strlen($path)-1);
			$path = urlencode($path);
			
			print "<li>";
			$url = "<a href=\"?user=$login&amp;path=$path\">" . $action['path'] . "</a>";
			print $url . " (start " . date("H:i:s", $action['time']) . " - " . " duration: " . nicetime($action['duration']) . ")</li>\n";
		}
		if (count($group['items']) > 0) print "</ul>";
		print "</li>\n";
	}
	if ($count > $limit)
		print "<li><a href=\"?user=$login&amp;start=" . ($time+1) . "\">&gt;&gt; Next</a></li>";
	?>
	</ul>
	<?php
	
}

function nicetime($time) {
	if ($time < 60) return $time ."s";
	if ($time < 3600 && $time%60 == 0) return round($time/60) . "m";
	if ($time < 3600) return round($time/60) . "m " . round($time%60) . "s";
	$h = round($time / 3600);
	$time = $time % 3600;
	if ($time == 0) return $h . "h";
	return $h . "h " . nicetime($time);
}

?>
