<?php

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

	ksort($assignments);

	
	// Table display
	
	$show_others = isset($_REQUEST['show_others']) || (isset($_REQUEST['path']) && $_REQUEST['path'] != "TP" && $_REQUEST['path'] != "OR"); // FIXME
	?>
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

		?>
		<tr>
		<td id="user-stats-table-<?=$login?>"><a href="?user=<?=$login?>&amp;backlink=<?=$backlink?>"><?=$fullname?></a></td>
		<td>/</td>
		<td>
			<a href="admin.php?user=<?=$login?>&amp;action=reset-conf&amp;return=<?=$backlink?>" onclick="return potvrda('Reset user config', '<?=$fullname?>');"> <img src="static/images/gear_wheel.png" width="16" height="16" alt="Reset" title="Reset user config"></a> &nbsp;
			<a href="admin.php?user=<?=$login?>&amp;action=logout&amp;return=<?=$backlink?>" onclick="return potvrda('Logout user', '<?=$fullname?>');"><img src="static/images/logout.png" width="16" height="16" alt="Logout" title="Logout user from system"></a> &nbsp;
			<a href="admin.php?user=<?=$login?>&amp;action=refresh-stats&amp;return=<?=$backlink?>" onclick="return potvrda('Update statistics', '<?=$fullname?>');"><img src="static/images/refresh.gif" width="16" height="16" alt="Update" title="Update user statistics"></a> &nbsp;
			<a href="admin.php?collaborate=<?=$login?>"><i class="fa fa-television"></i>
		</td>
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
	<script>
	var usersToLoad = [ <?php
		foreach ($members as $login => $fullname) {
			?>
			{ 'username' : '<?=$login?>', 'path' : '<?=$path_log?>' },
			<?php
		}
	?> ];
	userTableLoadAll();
	</script>
	<?php
}


?>
