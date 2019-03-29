<?php

// Initiate phpwebide
// Assume credentials checked etc.
// - $editable - if true, files can be edited!
// - $tabs - show "tabs" with admin tools
function phpwebide($username, $cur_path, $editable, $tabs) {

	// Pass PHP boolean to JS
	if ($editable) $editable_txt = "true"; else $editable_txt = "false";
	
	?>
	<div id="phpwebide_treebuttons">
	<span class="tree-button"><a href="#" onclick="pwi_tree_showhide(); return false;"><i class="fa fa-eye-slash fa-2x"></i> Hidden</a></span>
	<span class="tree-button"><a href="#" onclick="pwi_tree_show_deleted(); return false;"><i class="fa fa-trash-o fa-2x"></i> Deleted</a></span>
	</div>
	<div id="phpwebide_tree"></div>
	
	<div id="phpwebide_toolbar">
		<span id="phpwebide_spinner" style="display: none"><img src="static/images/busy-light-32x29.gif" width="32" height="29" alt="busy"></span>
		<span id="phpwebide_restore_button" class="tree-button" style="display: none">
			<a href="#" onclick="pwi_restore_revision(); return false;"><i class="fa fa-eye-slash fa-2x"></i> Restore this revision</a>
		</span>
		<span id="phpwebide_homework_button" class="tree-button" style="display: none">
			<a href="#" onclick="pwi_send_homework(); return false;"><i class="fa fa-cloud-upload fa-2x"></i> Send homework</a>
		</span> 
		<span id="phpwebide_test_button" class="tree-button" style="display: none">
			<a href="#" onclick="pwi_test_project(); return false;"><i class="fa fa-check-square-o fa-2x"></i> Test</a>
		</span>
		<span id="phpwebide_test_results" class="tree-button" style="display: none">
			<a href="#" onclick="showMenu(this, 'phpwebide_test_results_widget'); return false;" id="phpwebide_test_results_data"></a>
		</span>
		<span id="phpwebide_deploy_button" class="tree-button" style="display: none">
			<a href="#" onclick="showMenu(this, 'phpwebide_deploy_menu'); return false;"><i class="fa fa-bolt fa-2x"></i> Deploy</a>
		</span>
		<span id="phpwebide_reconstruct_button" class="tree-button" style="display: none">
			<a href="#" onclick="pwi_reconstruct(); return false;"><i class="fa fa-clock-o fa-2x"></i> History</a>
		</span>
		<span id="phpwebide_reconstruct_options" style="display: none">
			<input type="range" id="phpwebide_reconstruct_slider" min="0" max="100" value="0" step="1" onchange="pwi_reconstruct_slider_change(this.value);">
			<span class="tree-button">
				<a href="#" onclick="pwi_reconstruct_play_stop(); return false;"><i class="fa fa-play" id="phpwebide_reconstruct_play_icon"></i></a>
			</span>
			<label for="phpwebide_reconstruct_speed">Speed x:</label>
			<input type="number" id="phpwebide_reconstruct_speed" name="phpwebide_reconstruct_speed" min="1" max="10" value="2" onchange="pwi_reconstruct_speed_change(this.value);">
			
			<input type="checkbox" id="phpwebide_reconstruct_realtime" name="phpwebide_reconstruct_realtime" onchange="pwi_reconstruct_realtime_toggle(this.value);">
			<label for="phpwebide_reconstruct_realtime">Real-time</label>
		</span>
		
		<span id="phpwebide_modified_time" style="float:right;"></span>
	</div>
	
	<div id="editor"></div>
	<div id="status"></div>
	
	<div id="phpwebide_test_results_widget" class="menu-widget" style="display:none"></div>
	<?php
	
	if ($tabs) {
		?>
		<div id="tabs-container">
			<ul class="tabs-menu">
				<li><a id="activity-click" href="#activity" onclick="return pwi_tab_show('activity', this, pwi_current_user, pwi_current_path);">User activity</a></li>
				<li><a id="svn-click" href="#SVN" onclick="return pwi_tab_show('svn', this, pwi_current_user, pwi_current_path);">SVN</a></li>
				<li><a id="git-click" href="#Git" onclick="return pwi_tab_show('git', this, pwi_current_user, pwi_current_path);">Git</a></li>
				<li><a id="deleted-click" href="#Deleted" onclick="return pwi_tab_show('deleted', this, pwi_current_user, pwi_current_path);">Deleted files</a></li>
			</ul>
			<div style="clear: both;"></div>
			<div class="tab">
				<div id="activity" class="tab-content"></div>
				<div id="svn" class="tab-content"></div>
				<div id="git" class="tab-content"></div>
				<div id="deleted" class="tab-content"></div>
			</div>
		</div>
		<div style="clear: both;"></div>
		<script src="static/js/webide-tabs.js" type="text/javascript" charset="utf-8"></script>
		
		<?php
		// Also deploying assigments is only available to admins
		
		?>
		<div id="phpwebide_deploy_menu" class="menu-widget"></div>
		<script src="static/js/assignment.js" type="text/javascript" charset="utf-8"></script>
		<?php
		
		// Parse stats is only available to admins
		?>
		<div id="user-stats-container">
			<table class="users-table single-user" id="user-stats-table">
				<thead><tr><th>Full name</th><th>Last access time</th><th>Options</th></tr></thead>
				<tr>
					<td id="user-stats-table-realname"><a href="?user=USERNAME&amp;backlink=BACKLINK">REALNAME</a></td>
					<td>/</td>
					<td id="user-stats-table-options">
						<a href="admin.php?user=USERNAME&amp;action=reset-conf&amp;return=BACKLINK" onclick="return potvrda('Reset user config', 'REALNAME');"> <img src="static/images/gear_wheel.png" width="16" height="16" alt="Reset" title="Reset user config"></a> &nbsp;
						<a href="admin.php?user=USERNAME&amp;action=logout&amp;return=BACKLINK" onclick="return potvrda('Logout user', 'REALNAME');"><img src="static/images/logout.png" width="16" height="16" alt="Logout" title="Logout user from system"></a> &nbsp;
						<a href="admin.php?user=USERNAME&amp;action=refresh-stats&amp;return=BACKLINK" onclick="return potvrda('Update statistics', 'REALNAME');"><img src="static/images/refresh.gif" width="16" height="16" alt="Update" title="Update user statistics"></a> &nbsp;
						<a href="admin.php?collaborate=USERNAME"><i class="fa fa-television"></i></a>
					</td>
				</tr>
			</table>
			
			<p>LEGEND:<br>
			<i class="fa fa-clock-o"></i> Total time spent (minutes)<br>
			<i class="fa fa-wrench"></i> Number of builds<br>
			<i class="fa fa-gear"></i> Number of runs (successful builds)<br>
			<i class="fa fa-check"></i> Last test results (if user tested the code)</p>
		</div>
		<script type="text/javascript" src="/static/js/user_table.js"></script>
		<?php
	}
	
	?>
	
	<script src="https://zamger.etf.unsa.ba/static/js/ace/ace.js" type="text/javascript" charset="utf-8"></script>
	<script src="static/js/phpwebide.js" type="text/javascript" charset="utf-8"></script>
	<script src="static/js/buildservice.js" type="text/javascript" charset="utf-8"></script>
	
	<SCRIPT>
	var pwi_current_path = '<?=$cur_path?>';
	var pwi_current_user = '<?=$username?>';
	
	window.onload = function() {
		<?php if ($tabs) { ?> pwi_tabs_reset(); <?php } ?>
		pwi_editor_initialize(<?=$editable_txt?>);
		pwi_editor_load(pwi_current_path, "file");
		pwi_tree_load_all(pwi_current_path);
		
		// Fixes for stats
		var namecell = document.getElementById('user-stats-table-realname');
		namecell.id = 'user-stats-table-' + pwi_current_user;
		namecell.innerHTML = userTableFix(namecell.innerHTML);
		document.getElementById('user-stats-table-options').innerHTML = userTableFix(document.getElementById('user-stats-table-options').innerHTML);
		userTableLoad(pwi_current_user, pwi_current_path);
	}
	
	function userTableFix(text) {
		text = text.replace("USERNAME", pwi_current_user);
		text = text.replace("REALNAME", pwi_current_user); // FIXME
		text = text.replace("BACKLINK", "FIXME"); // FIXME
		return text;
	}
	</SCRIPT>

	<form id="pwi_test_results_form" method="post" action="buildservice/render_result.php" target="_blank">
	<input type="hidden" name="tests" value="">
	<input type="hidden" name="test_results" value="">
	<input type="hidden" name="test_id" value="">
	</form>

	<?php
}


?>
