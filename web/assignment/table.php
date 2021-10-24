<?php 

function assignment_table($course) {
	$root = $course->getAssignments();
	$assignments = $root->getItems();

	if (empty($assignments)) {
		?>
			<div class="tekst info">
				There are no assignments on this course.<br>
				To create some assignments, use the form below.<br><br>
			</div>
		<?php
	}
	
	
	// Sort assigments by type, then by name (natural)
	function cmp($a, $b) {
		if ($a->type == $b->type) return strnatcmp($a->name, $b->name); 
		if ($a->type == "tutorial") return -1;
		if ($b->type == "tutorial") return 1;
		if ($a->type == "homework") return -1;
		if ($b->type == "homework") return 1;
		// Other types are considered equal
		return strnatcmp($a->name, $b->name); 
	}
	usort($assignments, "cmp");
	
	// Output table
	
	if (count($assignments) > 0) {
	
		$max = $root->maxTasks();
	?>
	<script src="static/js/assignment.js" type="text/javascript" charset="utf-8"></script>
    <script src="static/js/autotest-genv2/scripts/helpers.js"></script>
	<table cellspacing="0" cellpadding="2" border="0" class="assignment-table">
	<thead>
		<tr bgcolor="#dddddd">
			<td class="text cell">&nbsp;</td>
			<td class="text cell">&nbsp;</td>
			<?php 
				for ($i = 1; $i <= $max; $i++) 
					print "<td class='text cell stronger' align='center'>Task $i</td>";
			?>
		</tr>
		<?php
	}
	
	foreach ($assignments as $a) {
		assignment_print($a, $course, $max, 0);
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
					<input type="hidden" name="course" value="<?=$course->id?>">
					<input type="hidden" name="year" value="<?=$course->year?>">
					<input type="hidden" name="external" value="<?=($course->external)?"1":"0"?>">
					
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
				
				<?php
				
				// Test if the same course was delivered in the previous year
				$previous_year_course = Course::find($course->id, $course->external);
				$previous_year_course->year = $course->year - 1;
				$previous_asgn_root = $previous_year_course->getAssignments();
				if (!empty($previous_asgn_root->getItems())) {
					?>
					<font class="text stronger"><a href="assignment/copy.php?<?=$course->urlPart() ?>">Copy assignment from last year</a></font>
					<?php
				}
				?>
			</font>
		</div>
	<?php
}


// Helper function to print single assignment in a table row
function assignment_print($a, $course, $max, $level) {
	$style = "text-align: left; ";
	if ($a->hidden) {
		if ($a->type == "homework")
			$style .= "background: #f4e1c3; color: #666;";
		else if ($a->type == "tutorial")
			$style .= "background: #e7e9fd; color: #666;";
		else
			$style .= "background: #e2f3c8; color: #666;";
	} else {
		if ($a->type == "homework")
			$style .= "background: #f4d1aa";
		else if ($a->type == "tutorial")
			$style .= "background: #d7d9fd";
		else
			$style .= "background: #d2edb8";
	}
	
	$edit_link = "assignment/edit.php?action=edit&amp;" . $course->urlPart() . "&amp;assignment=" . $a->id;
 
	$levelprint = "";
	for ($i=0; $i<$level; $i++)
		$levelprint .= "&nbsp;&nbsp;&nbsp;";
	if ($level > 0)
		$levelprint .= "&#x2514;";

    $js_link = $course->id . ", " . $course->year . ", ";
    if ($course->external) $js_link .= "true, "; else $js_link .= "false, ";
    $js_link .= $a->id . ", ";

?>
	<tr>
		<td class="text cell stronger" style="<?=$style?>"><?=$levelprint . $a->name?></td>
		<td><a href="<?=$edit_link?>"><i class="fa fa-gear"></i></a></td>
		<?php
	
	$items = $a->getItems();
	// Is there printable subitems?
	$printable = [];
	foreach($items as $item) {
		if (count($item->getItems()))
			$printable[] = $item;
	}
	$i = 1;
	foreach( $a->getItems() as $item) {
		if (count($item->getItems()) == 0) {
			$at_path = $item->filesPath() . "/.autotest2";
			// FIXME: hack to get relative path
			$absolute = $course->getPath() . "/assignment_files/";
			$at_name = substr($at_path, strlen($absolute));
			
			$at_exists = false;
			foreach($item->files as $file)
				if ($file['filename'] == ".autotest2")
					$at_exists = true;
			
			$count_tests = 0;
			if ($at_exists) {
				$autotest = json_decode(file_get_contents($at_path), true);
				if (!empty($autotest) && array_key_exists("test_specifications", $autotest))
					$count_tests = count($autotest['test_specifications']);
				if (!empty($autotest) && array_key_exists("tests", $autotest)) {
					$count_tests = 0;
					foreach ($autotest['tests'] as $test) {
						if (!array_key_exists("options", $test) || !in_array("silent", $test['options']))
							$count_tests++;
					}
				}
				
				
				$link = "autotest/preview.php?fileData=$at_path";
				$local_link = $js_link . $item->id . ", '$at_name'";
				
				?>
				<td>
                    <a href="#" onclick="return doOpenAutotestGenV2(<?=$local_link?>);"><i class="fa fa-check"></i> <?=$count_tests?></a>
                    <a href="#" onclick="return deployAssignmentFile(<?=$local_link?>, 'all-users');"><i class="fa fa-bolt"></i></a>
				</td>
				<?php
			} else {
				?>
				<td>&nbsp;</td>
				<?php
			}
			$i++;
		}
	}
	
	while ($i++ <= $max) {
		print "<td>&nbsp;</td>\n";
	}
	
	foreach($printable as $item) {
		assignment_print($item, $course, $max, $level+1);
	}
}

?>
