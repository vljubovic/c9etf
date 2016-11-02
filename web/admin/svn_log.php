<?php


function admin_svn_log($user, $path) {
	$userdata = setup_paths($user);
	
	$svn_url = "file://" . $userdata['svn'] . "/" . $path;
	$ws_url = $userdata['workspace'];
	$revert_possible = !is_dir($ws_url);
	
	$svn_log = svn_log($svn_url);
	if (empty($svn_log)) {
		?>
		<p>Path <b><?=$path?></b> is not on SVN repository.</p>
		<?php 
		if (file_exists($ws_url)) {
			print "<p>Kontaktirajte administratora!</p>\n";
		}
	}
	print "<ul class=\"svn_log\">\n";
	foreach($svn_log as $entry) {
		?>
		<li class="svn_log_entry">(r<?=$entry['rev']?>) <?=date("d. m. Y. H:i:s", strtotime($entry['date']))?> - <?=$entry['msg']?>
			<ul>
		<?php
		foreach($entry['paths'] as $svnpath) {
			$the_path = $svnpath['path'];
			if ($the_path[0] == "/") $the_path = substr($the_path, 1);
			?>
			<li><a href="?user=<?=$user?>&amp;path=<?=urlencode($the_path)?>&amp;svn_rev=<?=$entry['rev']?>"><?=$svnpath['path']?></a></li>
			<?php
		}
		print "</ul></li>\n";
	}
}

?>