<?php 

function assignment_table($course, $year, $external) {
	global $conf_data_path;
	
	// Read data from files and set vars
	$url_part = "course=$course&amp;year=$year";
	if ($external) {
		$course_path_part = "X$course"."_$year";
		$url_part .= "&amp;X";
	} else
		$course_path_part = "$course"."_$year";
		
	$course_path = $conf_data_path . "/$course_path_part";
	$asgn_file_path = $course_path . "/assignments";
	$assignments = array();
	if (file_exists($asgn_file_path))
		$assignments = json_decode(file_get_contents($asgn_file_path), true);

	if (empty($assignments)) {
		?>
			<font class="tekst info">
				There are no assignments on this course.<br>
				To create some assignments, use the form below.<br><br>
			</font>
		<?php
	}
	
	
	// Sort assigments by type, then by name (natural)
	function cmp($a, $b) { 
		if ($a['type'] == $b['type']) return strnatcmp($a['name'], $b['name']); 
		if ($a['type'] == "tutorial") return -1;
		if ($b['type'] == "tutorial") return 1;
		if ($a['type'] == "homework") return -1;
		if ($b['type'] == "homework") return 1;
		// Other types are considered equal
		return strnatcmp($a['name'], $b['name']); 
	}
	usort($assignments, "cmp");
	
	$max_tasks = 1;
	foreach($assignments as $a) {
		if ($a['tasks'] > $max_tasks) $max_tasks = $a['tasks'];
	}
	
	// Output table
	
	if (count($assignments) > 0) {
	
	?>
	<script src="static/js/assignment.js" type="text/javascript" charset="utf-8"></script>
	<table cellspacing="0" cellpadding="2" border="0" class="assignment-table">
	<thead>
		<tr bgcolor="#dddddd">
			<td class="text cell">&nbsp;</td>
			<td class="text cell">&nbsp;</td>
			<?php 
				for ($i=1; $i<=$max_tasks; $i++) 
					print "<td class='text cell stronger' align='center'>Task $i</td>";
			?>
		</tr>
		<?php
	}
	
	foreach ($assignments as $a) {
		if ($a['type'] == "homework") $color = "#f4d1aa";
		else if ($a['type'] == "tutorial") $color = "#d7d9fd";
		else $color = "#d2edb8";
		
		$edit_link = "assignment/edit.php?action=edit&amp;$url_part&amp;assignment=" . $a['id'];
		
		$deploy_js = "return deployAssignmentFile($course, $year, ";
		if ($external) $deploy_js .= "true, ";
		
		?>
		<tr>
			<td class="text cell stronger" align="left" bgcolor="<?=$color?>"><?=$a['name']?></td>
			<td><a href="<?=$edit_link?>"><i class="fa fa-gear"></i></a></td>
			<?php
		
		for ($i=1; $i<=$max_tasks; $i++) {
			if ($i <= $a['tasks']) {
				$at_path = $course_path . "/assignment_files/" . $a['path'] . "/Z$i/.autotest";
				$at_name = $a['path'] . "/Z$i/.autotest";
				$count_tests = 0;
				if (file_exists($at_path)) {
					$autotest = json_decode(file_get_contents($at_path), true);
					if (!empty($autotest) && array_key_exists("test_specifications", $autotest))
						$count_tests = count($autotest['test_specifications']);
				}
			
				
				$link = "autotest/preview.php?fileData=$at_path";
				$deploy_js_this = $deploy_js . $a['id'] . ", $i, '$at_name', 'all-users');";
				
				?>
				<td>
					<a href="<?=$link?>"><i class="fa fa-check"></i> <?=$count_tests?></a>
					<a href="#" onclick="<?=$deploy_js_this?>"><i class="fa fa-bolt"></i></a>
				</td>
				<?php
			} else
				print "<td>&nbsp;</td>\n";
		}
	}
	
	?>
	</table><br>
		<script>
		function showOther(selectType) {
			var others = document.getElementById('assignment_type_other');
			var homework = document.getElementById('zamger_homework_id');
			
			if (selectType.value == "other") others.style.display = "inline";
			else others.style.display = "none";
			if (selectType.value == "homework") homework.style.display = "inline";
			else homework.style.display = "none";
		}
		</script>
		<div style="margin-left: 20px;">
			<font class="text">
				<font class="text stronger">Create new assignment:</font>
				<form action="assignment/edit.php" method="post">
					<input type="hidden" name="action" value="create">
					<input type="hidden" name="course" value="<?=$course?>">
					<input type="hidden" name="year" value="<?=$year?>">
					<input type="hidden" name="external" value="<?=($external)?"1":"0"?>">
					
					Assignment type: <select name="type" onchange="showOther(this);">
						<option value="tutorial">Tutorial</option>
						<option value="homework">Homework</option>
						<option value="independent">Independent study assignments</option>
						<option value="exam">Exam</option>
						<option value="other">Other</option>
					</select><br>
					<div id="assignment_type_other" style="display: none">Enter assignment type: <input type="text" name="type_other"><br></div>
					<div id="zamger_homework_id" style="display: none">Enter homework Zamger ID: <input type="text" style="width: 60px;" name="homework_id"><br></div>
					Assignment number: <input type="text" style="width: 30px;" name="assignment_number"><br>
					Number of tasks: <input type="text" style="width: 30px;" name="nr_tasks"><br>
					<input type="submit" value="Confirm">
				</form>
			</font>
		</div>
	<?php
}
?>