<?php


function assignment_copy_files($old, $new) {
	global $conf_base_path;
	foreach($old->files as $file) {
		$old_path = $old->filesPath() . "/" . $file['filename'];
		$new_path = $new->filesPath() . "/" . $file['filename'];
		
		if ($file['filename'] == ".autotest") {
			// Call convert.php script
			$target = $new->filesPath() . "/.autotest2";
			`php $conf_base_path/web/autotester/convert.php $old_path > $target`;
			
			// Add .autotest2 to new files list
			$newFile = $file;
			$newFile['filename'] = ".autotest2";
			$new->files[] = $newFile;
			
			// Remove .autotest from files list
			for($i=0; $i<count($new->files); $i++)
				if ($new->files[$i]['filename'] == ".autotest")
					unset ($new->files[$i]);
		}
		else
			copy ($old->filesPath() . "/" . $file['filename'], $new->filesPath() . "/" . $file['filename']);
	}
	
	$new->files = array_values($new->files);
	
	foreach($old->getItems() as $oldItem) {
		foreach($new->getItems() as $newItem) {
			if ($oldItem->id == $newItem->id) {
				assignment_copy_files($oldItem, $newItem);
				break;
			}
		}
	}
}

function assignment_copy($course, $asgn) {
	global $root;
	
	if (isset($_REQUEST['hidden'])) $asgn->hidden = true;
	
	$name_exists = $path_exists = false;
	foreach($root->getItems() as $a) {
		if ($a->name == $asgn->name) $name_exists = true;
		if ($a->path == $asgn->path) $path_exists = true;
	}
	if ($name_exists) {
		niceerror("Assignment with this name already exists");
		return;
	}
	if ($path_exists) {
		niceerror("Assignment with this folder name (path) already exists");
		return;
	}
	
	$asgn->getItems();
	$new_asgn = $root->addItem($asgn);
	
	// Copy data files
	assignment_copy_files($asgn, $new_asgn);
	
	$root->update();
	
	admin_log("assignment copied - " . $asgn->id . " (" . $course->toString() . ")");
	nicemessage("Assignment " . $asgn->name . " (" . $asgn->id . ") successfully copied from last year");
	print "<p><a href=\"/admin.php?" . $course->urlPart() . "\">Go back</a></p>\n";
}



require_once("../../lib/config.php"); // Webide config
require_once("../../lib/webidelib.php"); // Webide library - for nicemessage
//require_once("../login.php"); // Login
require_once("../admin/lib.php"); // Admin library
//require_once("lib.php"); // Assignment library
require_once("../classes/Course.php"); 


// Verify session and permissions, set headers
admin_set_headers();
if (!admin_session()) {
	niceerror("Your session expired. Please log out then log in.");
	exit(0);
}

try {
	$course = Course::fromRequest();
} catch (Exception $e) {
	niceerror($e->getMessage());
	exit(0);
}

if (!$course->isAdmin($login)) {
	niceerror("You don't have permission to access this page.");
	exit(0);
}

$root = $course->getAssignments();
$assignments = $root->getItems();


// HTML

?>
<!DOCTYPE html>
<html>
<head>
	<title>Assignments - copy from last year</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
</head>
<body>
<h1>Copy assignments from previous year</h1>
	<?php


// Initialize various variables related to course/assignment data for the
// previous year of same course
$previous_year = Course::find($course->id, $course->external);
$previous_year->year = $course->year - 1;
$prev_assignments = $previous_year->getAssignments()->getItems();

if (empty($prev_assignments)) {
	niceerror("No data from previous year found");
	return;
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
usort($prev_assignments, "cmp");

if (isset($_REQUEST['action']) && $_REQUEST['action'] == "create") {
	$asgn_id = intval($_REQUEST['asgn']);
	foreach($prev_assignments as $asgn)
		if ($asgn->id == $asgn_id)
			assignment_copy($course, $asgn);
}

else {
	?>
	<form action="copy.php" method="POST">
	<input type="hidden" name="action" value="create">
	<?php
	print $course->htmlForm();
	?>
	<p>Select assignment: <select name="asgn">
	<?php

	print_r($assignments);
	foreach($prev_assignments as $pa) {
		$exists = false;
		foreach($assignments as $a) {
			if ($a->name == $pa->name) $exists = true;
		}
		
		$name = $pa->name;
		if ($exists) $name .= " - EXISTS!";
		?>
		<option value="<?=$pa->id?>"><?=$name?></option>
		<?php
	}
	?>
	</select></p>
	<p><input type="checkbox" name="hidden"> Make hidden</p>
	<input type="submit" value=" Copy "></form>
	<?php
}

?>
</body>
</html>
