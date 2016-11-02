<?php

require("parse_stats.php");

function admin_user_table($group_id, $members, $backlink) {
	global $conf_limit_loadavg_web;
	
	$assignments = $stats = $last_access = array();
	
	$assignment_folder_regex = "/^(T|Z|ZSR)\d+$/";
	
	// If we have a defined path, we will show the table just for this path
	$path_log = "";
	if (isset($_REQUEST['path'])) $path_log=$_REQUEST['path'];
	
	$loadavg = `cat /proc/loadavg | cut -d " " -f 1`;
	if ($loadavg > $conf_limit_loadavg_web)
		print "<p>Statistics per assignment is disabled due to high server load ($loadavg)</p>";
	else
	
	foreach ($members as $login => $fullname) {
		parse_stats($login, $assignments, $stats, $last_access, $path_log);
		//if ($_REQUEST['korisnik'] == "egagulic1") print_r($stats);
	}

	ksort($assignments);

	
	// Table display
	
	$show_others = isset($_REQUEST['show_others']) || (isset($_REQUEST['path']) && $_REQUEST['path'] != "TP" && $_REQUEST['path'] != "OR"); // FIXME
	?>
	<table class="users-table <?php if (isset($_REQUEST['korisnik'])) print "single-user"; ?>" >
	<tr><th>Full name</th><th>Last access time</th><th>Options</th>
	<?php
	foreach ($assignments as $name => $data) {
		if ($show_others || preg_match($assignment_folder_regex, $name))
			print "<th>$name</th>";
	}
	if (!$show_others) {
		$url = "admin.php?";
		foreach ($_REQUEST as $key=>$value) {
			if ($url != "admin.php?") $url .= "&amp;";
			$url .= urlencode($key).'='.urlencode($value);
		}
		?>
		<th>Others - <a href="<?=$url?>&amp;show_others=true">expand</a></th>
		<?php
	}
	print "</tr>\n";
	foreach ($members as $login => $fullname) {
		$time = $last_access[$login];
		if ($time == 0) { $time = "Never"; $action = "&nbsp;"; }
		else $time = date("d.m.Y H:i:s", intval($time));

		?>
		<tr>
		<td><a href="?user=<?=$login?>&amp;backlink=<?=$backlink?>"><?=$fullname?></a></td>
		<td><?=$time?></td>
		<td>
			<a href="admin.php?user=<?=$login?>&amp;action=reset-conf&amp;return=<?=$backlink?>" onclick="return potvrda('Reset user config', '<?=$fullname?>');"> <img src="static/images/gear_wheel.png" width="16" height="16" alt="Reset" title="Reset user config"></a> &nbsp;
			<a href="admin.php?user=<?=$login?>&amp;action=logout&amp;return=<?=$backlink?>" onclick="return potvrda('Logout user', '<?=$fullname?>');"><img src="static/images/logout.png" width="16" height="16" alt="Logout" title="Logout user from system"></a> &nbsp;
			<a href="admin.php?user=<?=$login?>&amp;action=refresh-stats&amp;return=<?=$backlink?>" onclick="return potvrda('Update statistics', '<?=$fullname?>');"><img src="static/images/refresh.gif" width="16" height="16" alt="Update" title="Update user statistics"></a> &nbsp;
			<a href="admin.php?collaborate=<?=$login?>"><i class="fa fa-television"></i>
		</td>
		<?php
		$stats_others = 0;
		foreach ($assignments as $assignment_name => $data) {
			// Show assignments named Tn or Zn as they represent tutorials and homework
			if (!$show_others && !preg_match($assignment_folder_regex, $assignment_name)) { 
				if (array_key_exists($login, $stats) && array_key_exists($assignment_name, $stats[$login]))
					$stats_others += $stats[$login][$assignment_name]['time'];
				continue;
			}
			if (array_key_exists($login, $stats) && array_key_exists($assignment_name, $stats[$login])) {
				if (isset($_REQUEST['path']))
					$assignment_path = $path_log . "/$assignment_name";
				else
					$assignment_path = $assignment_name;
				?>
				<td><a href="?user=<?=$login?>&amp;path=<?=$assignment_path?>&amp;backlink=<?=$backlink?>">
					<i class="fa fa-clock-o"></i> <?=round($stats[$login][$assignment_name]['time']/60, 2)?>
				<?php
					if (array_key_exists('builds', $stats[$login][$assignment_name]))
						print "<i class=\"fa fa-wrench\"></i> ".$stats[$login][$assignment_name]['builds'];
					if (array_key_exists('builds_succeeded', $stats[$login][$assignment_name]))
						print "<i class=\"fa fa-gear\"></i> ".$stats[$login][$assignment_name]['builds_succeeded'];
					if (array_key_exists('test_results', $stats[$login][$assignment_name]))
						print "<i class=\"fa fa-check\"></i> " . $stats[$login][$assignment_name]['test_results'];
				?>
				</a></td>
				<?php
			} else {
				print "<td>/</td>";
			}
		}
		if (!$show_others) {
			?>
			<td><a href="?user=<?=$login?>"><i class="fa fa-clock-o"></i> <?=round($stats_others/60, 2)?></a></td>
			<?php
		}
		print "</th>";
	}
	?>
	</table>
	
	<p>LEGEND:<br>
	<i class="fa fa-clock-o"></i> Total time spent (minutes)<br>
	<i class="fa fa-wrench"></i> Number of builds<br>
	<i class="fa fa-gear"></i> Number of runs (successful builds)<br>
	<i class="fa fa-check"></i> Last test results (if user tested the code)</p>
	
	<script>
	var pathLog = '<?=$path_log?>';
	var usersToLoad = [ <?php
		foreach ($members as $login => $fullname)
			print "'$login', ";
	?> ];
	var allUsers = usersToLoad;
	</script>
	<script type="text/javascript" src="/static/js/user_table.js"></script>
	<?php
}


?>