<?php


function assignment_fill_flat(&$flat_array, $asgn, $level, $parent = false) {
	$asgn->level = $level;
	if ($level == 1) 
		$asgn->visible = 1;
	else
		$asgn->visible = 0;
	$asgn->selected = 0;
	if ($parent && $parent->id)
		$asgn->parent = $parent;
	else
		unset($asgn->parent);
	if (count($asgn->getItems()) > 0)
		$asgn->folder = true;
	else
		$asgn->folder = false;
	if ($level>0) $flat_array[] = $asgn;
	if($asgn->files) foreach($asgn->files as $i => $file) {
		if (!is_array($file))
			$asgn->files[$i] = array("filename" => $file, "binary" => false, "show" => true);
	}
	foreach($asgn->getItems() as $item)
		assignment_fill_flat($flat_array, $item, $level+1, $asgn);
}

function assignment_edit($course) {
	global $login;
	
	$user = new User($login);
	$realname = $user->realname;
	
	?>
	<p><a href="/admin.php?<?=$course->urlPart()?>">&lt; Back to course details</a></p>
	<?php
	
	$flat_array = array();
	assignment_fill_flat($flat_array, $course->getAssignments(), 0, false);
	
	// View file link
	
	?>
	<p><b>Assignments:</b></p>
	<table border="0" cellpadding="20">
	<tr><td>
	<select id="assignment_select" style="width:200px" onchange="assignmentsChangeSelected();">
	</select>
	</td><td valign="top">
	<button id="upButton" onclick="assignmentsUpDown(true);" style="width:50px" title="Move up"> <i class="fa fa-arrow-up fa-2x"></i> </button><br>
	<button id="downButton" onclick="assignmentsUpDown(false);" style="width:50px" title="Move down"> <i class="fa fa-arrow-down fa-2x"></i> </button><br>
	<button id="folderButton" onclick="assignmentsAddFolder();" style="width:50px" title="Add folder"> <i class="fa fa-folder-open fa-2x"></i> </button><br>
	<button id="taskButton" onclick="assignmentsAddTask();" style="width:50px" title="Add task"> <i class="fa fa-file fa-2x"></i> </button><br>
	<button id="deleteButton" onclick="assignmentsDelete();" style="width:50px" title="Delete"> <i class="fa fa-trash fa-2x"></i> </button>
	</td><td valign="top">
	
	<style>
	label { display:inline-block; width:100px; }
	</style>
	
	<div id="folderForm" style="display:inline">
	<label for="name">Name</label>
	<input type="text" id="name" name="name"><br>
	<label for="path">Path</label>
	<input type="text" id="path" name="path"><br>
	<label for="hidden">Hidden</label>
	<input type="checkbox" id="hidden" name="hidden"><br>
	<label for="homework_id">Homework ID</label>
	<input type="text" id="homework_id" name="homework_id"><br>
	<label for="author">Created by</label>
	<input type="text" id="author" name="author"><br>
	<div id="filesDiv" style="display:none">
		<br>Files:<br>
		<table border="0" cellspacing="0" cellpadding="5">
		<tr><td>
			<select id="filesSelect" style="width:100px" onchange="assignmentsFileSelected();">
			</select>
		</td><td>
			<button id="addFileButton" onclick="document.getElementById('uploadFileWrapper').style.display='block'; return false;" style="width:30px" title="Add"><i class="fa fa-plus"></i></button><br>
			<button id="removeFileButton" onclick="assignmentsRemoveFile();" style="width:30px" title="Remove"><i class="fa fa-minus"></i></button><br>
			<button id="viewFileButton" onclick="assignmentsViewFile();" style="width:30px" title="View"><i class="fa fa-search"></i></button><br>
			<button id="createFileButton" onclick="assignmentsGenerateFile(true);" style="width:30px; display:none" title="Create"><i class="fa fa-gear"></i></button>
		</td><td>
			<!--label for="fileName" style="width:100px">Name</label>
			<input type="text" id="fileName" name="fileName" size="15"><br-->
			File: <b><span id="fileNameSpan"></span></b><br>
			<label for="fileBinary">Binary</label>
			<input type="checkbox" id="fileBinary" name="fileBinary"><br>
			<label for="fileShow">Auto-open</label>
			<input type="checkbox" id="fileShow" name="fileShow"><br>
			<input type="button" value="Change" onclick="assignmentsFileChange();">
		</td></tr>
		</table>
	</div>
	
	<div id="uploadFileWrapper" style="display:none">
		<br>
		File upload: <input type="file" name="uploadFileWidget" id="uploadFileWidget" onchange="assignmentsUploadFile();">
	</div>
	
	<div id="uploadProgress" style="display:none">
		<br>
		<progress id="uploadProgressBar" max="100"> Upload progress
	</div>
	
	<br>
	<input type="button" value="Change assignment" onclick="assignmentsUpdate();">
	</div>
	
	<div id="assignmentChangeMessage" style="display:none;color:green; font-weight:bold">Assignment changed</div>
	
	<div id="showFileWrapper" style="position:absolute; top: 20px; left:50px; width:80%; margin:0 auto; padding: 10px; box-shadow: 0 2px 4px 0 rgba(0,0,0,0.16),0 2px 10px 0 rgba(0,0,0,0.12)!important; background: #fff; display:none;">
		<div id="showFileFilenameBox"><b>File: <span id="showFileFilename">filename.txt</span></b></div>
		<hr>
		<pre id="showFileContents">
			Here's some content
		</pre>
		<hr>
		<a href="#" onclick="" id="showFileDownloadLink">Download</a> * <a href="#" onclick="document.getElementById('showFileWrapper').style.display='none'; return false;">Close</a>
	</div>
	
	</td></tr></table>

	<script>
	var authorName='<?=$realname;?>';
	var courseUrlPart='<?=str_replace("&amp;", "&", $course->urlPart());?>';
	assignments=<?=json_encode($flat_array);?>;
	assignmentsRender();
	<?php
	if (isset($_REQUEST['assignment'])) {
		?>
		assignmentsClickOn(<?=intval($_REQUEST['assignment'])?>);
		<?php
	}
	?>
	</script>
	
	<?php
}



require_once("../../lib/config.php"); // Webide config
require_once("../../lib/webidelib.php"); // Webide library - niceerror
require_once("../admin/lib.php"); // Admin library - admin_set_headers, admin_session

require_once("../classes/Course.php");

// Verify session and permissions, set headers
admin_set_headers();
if (!admin_session()) {
	niceerror("Your session expired. Please log out then log in.");
	exit(0);
}

try {
	$course = Course::fromRequest();
} catch(Exception $e) {
	niceerror("Unknown course.");
	exit(0);
}
if (!$course->isAdmin($login)) {
	niceerror("Permission denied.");
	exit(0);
}


// HTML

?>
<!DOCTYPE html>
<html>
<head>
	<title>Assignments - actions</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="/static/css/admin.css">
	<link rel="stylesheet" href="/static/css/admin-assignment.css">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
	<script src="/static/js/edit_assignment.js" type="text/javascript" charset="utf-8"></script>
</head>
<body>
	<!-- Progress bar window -->
	<div id="progressWindow">
		<div id="progressBarMsg"></div>
		<div id="myProgress">
			<div id="myBar">
				<div id="progressBarLabel">10%</div>
			</div>
		</div>
	</div>
	
	<?php


// Actions
if (isset($_REQUEST['action'])) {
	if ($_REQUEST['action'] == "edit") assignment_edit($course);
}

?>
</body>
</html>
