<?php

// TREE.PHP - show tree

function pwi_tree($username, $cur_path) {

	$url = "admin.php?user=$username&amp;path=".urlencode($cur_path);
	if (isset($_REQUEST['showhidden']))
		$showhiddenurl = $url;
	else
		$showhiddenurl = $url . "&amp;showhidden=yes";
	if (isset($_REQUEST['showdeleted']))
		$showdeletedurl = $url;
	else
		$showdeletedurl = $url . "&amp;showdeleted=yes";
	
	?>
	<div id="phpwebide_treebuttons">
	<span class="tree-button"><a href="<?=$showhiddenurl?>"><i class="fa fa-eye-slash fa-2x"></i> Hidden</a></span>
	<span class="tree-button"><a href="<?=$showdeletedurl?>"><i class="fa fa-trash-o fa-2x"></i> Deleted</a></span>
	</div>
	<div id="phpwebide_tree">
	<?php
	pwi_tree_path($username, $cur_path, "");
	print "</div>\n";
}

function pwi_tree_path($username, $cur_path, $relpath) {
	global $conf_base_path;
	
//print "pwi_tree_path $username $cur_path $relpath<br>\n";

	$showhidden = false;
	$addurl = "";
	if (isset($_REQUEST['showhidden'])) {
		$showhidden = true;
		$addurl = "&amp;showhidden=yes";
	}
	
	// First read all files
	$dirs = $files = array();
	
	
	//if (!($handle = opendir($path))) return;
	//while (false !== ($entry = readdir($handle))) {
	
	if ($relpath == "") 
		$filelist = `sudo $conf_base_path/bin/wsaccess $username list /`;
	else
		$filelist = `sudo $conf_base_path/bin/wsaccess $username list "$relpath"`;

	foreach(explode("\n", $filelist) as $entry) {
		if ($entry == "." || $entry == ".." || empty($entry)) continue;

		if ($relpath == "") 
			$entryrelpath = $entry;
		else 
			$entryrelpath = "$relpath/$entry";

		if (!$showhidden && $entry[0] == "." && $entryrelpath != $cur_path) continue;
		
		$isdir = `sudo $conf_base_path/bin/wsaccess $username isdir "$entryrelpath"`;
		if ($isdir == 1) 
			$dirs[$entry] = $entryrelpath;
		else 
			$files[$entry] = $entryrelpath;
	}
	
	ksort($dirs, SORT_NATURAL);
	ksort($files, SORT_NATURAL);
	
	foreach($dirs as $name => $path) {
		$dirid = crc32($path);
		$addclass = "";
		if ($path == $cur_path) $addclass = "filelist-selected";
		?>
		<h2 class="filelist filelist-folder <?=$addclass?>" id="folder-<?=$dirid?>" onclick="showhide('folder-content-<?=$dirid?>');"><a href="admin.php?user=<?=$username?>&amp;path=<?=urlencode($path)?><?=$addurl?>"><?=$name?></a></h2>
		<?php
		if (substr($cur_path, 0, strlen($path)) == $path) {
			?>
			<div id="folder-content-<?=$dirid?>" class="filelist-folder-content">
			<?php
			pwi_tree_path($username, $cur_path, $path);
			print "</div>\n";
		}
	}
	
	foreach($files as $name => $path) {
		$addclass = "";
		if ($path == $cur_path) $addclass = "filelist-selected";
		?>
		<h2 class="filelist filelist-file <?=$addclass?>"><a href="admin.php?user=<?=$username?>&amp;path=<?=urlencode($path)?><?=$addurl?>"><?=$name?></a></h2>
		<?php
	}
}

?>