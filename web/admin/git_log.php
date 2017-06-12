<?php


function admin_git_log($user, $path) {
	global $conf_base_path;
	
	$git_log = shell_exec("sudo $conf_base_path/bin/wsaccess $user git-log $path 2>&1");
	$history = array(); $commit = array();
	foreach (explode("\n", $git_log) as $line) {
		if(strpos($line, 'commit')===0){
			if(!empty($commit)){
				array_push($history, $commit);	
				unset($commit);
			}
			$commit['hash']   = trim(substr($line, strlen('commit')));
		}
		else if(strpos($line, 'Author')===0){
			$commit['author'] = substr($line, strlen('Author:'));
		}
		else if(strpos($line, 'Date')===0){
			$commit['date']   = substr($line, strlen('Date:'));
		}
		else{
			if (!array_key_exists("msg", $commit)) $commit['msg'] = "";
			$commit['msg']  .= $line;
		}
	}
	if(!empty($commit)){
		array_push($history, $commit);	
		unset($commit);
	}

	print "<ul class=\"svn_log\">\n";
	foreach($history as $entry) {
		?>
		<li class="svn_log_entry">(<?=$entry['hash']?>) <?=date("d. m. Y. H:i:s", strtotime($entry['date']))?> - &quot;<?=$entry['msg']?>&quot;
			<ul>
		<?php
//		foreach($entry['paths'] as $path) {
			$the_path = $path;
//			if ($the_path[0] == "/") $the_path = substr($the_path, 1);
			?>
			<li class="svn-log"><a href="#" onclick="return pwi_tabs_git_click(this.parentNode, '<?=$the_path?>', '<?=$entry['hash']?>');"><?=$the_path?></a></li>
			<?php
	//	}
		print "</ul></li>\n";
	}
	
}

?>