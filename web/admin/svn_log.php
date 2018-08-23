<?php


function admin_svn_log($user, $path) {
	$userdata = setup_paths($user);
	
	$svn_url = "file://" . $userdata['svn'] . "/" . $path;
	$ws_url = $userdata['workspace'];
	$revert_possible = !is_dir($ws_url);
	
	try {
		$xml = `svn info --xml $svn_url`;
		$svn_info_xml = new SimpleXMLElement($xml);
	} catch(Exception $e) {
		?>
		<p>Path <b><?=$path?></b> is not on SVN repository.</p>
		<?php 
		if (file_exists($ws_url)) {
			print "<p>Kontaktirajte administratora!</p>\n";
		}
		exit(1);
	}
	
	$lastrev = $svn_info_xml->entry['revision'];
	if ($lastrev > 1000) $firstrev=$lastrev-1000; else $firstrev=0;
	
	try {
		$xml = `svn log -r$lastrev:$firstrev -v --xml $svn_url`;
		$svn_log_xml = new SimpleXMLElement($xml);
	} catch(Exception $e) {
		print "XML: <br>\n";
		print htmlentities($xml);
		print "<br>\n";
		?>
		<p>Path <b><?=$path?></b> is not on SVN repository.</p>
		<?php 
		if (file_exists($ws_url)) {
			print "<p>Kontaktirajte administratora!</p>\n";
		}
		exit(1);
	}
	if (empty($svn_log_xml)) {
		?>
		<p>Path <b><?=$path?></b> is not on SVN repository.</p>
		<?php 
		if (file_exists($ws_url)) {
			print "<p>Kontaktirajte administratora!</p>\n";
		}
	}
	print "<ul class=\"svn_log\">\n";
	foreach($svn_log_xml->children() as $entry) {
		?>
		<li class="svn_log_entry">(r<?=$entry['revision']?>) <?=date("d. m. Y. H:i:s", strtotime($entry->date))?> - <?=$entry->msg?>
			<ul>
		<?php
		foreach($entry->paths->children() as $svnpath) {
			$the_path = $svnpath[0];
			if ($the_path[0] == "/") $the_path = substr($the_path, 1);
			?>
			<li class="svn-log"><a href="#" onclick="return pwi_tabs_svn_click(this.parentNode, '<?=$the_path?>', <?=$entry['revision']?>);"><?=$svnpath[0]?></a></li>
			<?php
		}
		print "</ul></li>\n";
	}
}

?>
