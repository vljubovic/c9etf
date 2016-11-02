<?php


function admin_activity_log($user, $path) {
	global $conf_stats_path;

	$svn_ignore = array(".c9", ".svn", ".tmux", ".user", ".svn.fifo", ".inotify_pid", ".nakignore", "global_events", "last_update_rev", ".gcc.out");
	
	// Učitaj stats file
	unset($stats);
	$user_efn = escape_filename($user); // Make username safe for filename
	$stat_path = $conf_stats_path . "/$user_efn.stats";
	
	if (file_exists($stat_path)) 
		eval(file_get_contents($stat_path));
	if (!isset($stats)) {
		?>
		<p>Activity log file doesn't exist or isn't valid... it's possible that stats were never generated for this user.</p>
		<?php 
		return;
	}
	
	// Stats file can reference other files to be included
	foreach ($stats as $key => $value) {
		if (is_array($value) && array_key_exists("goto", $value)) {
			$goto_path = $conf_stats_path . "/" . $value['goto'];
			eval(file_get_contents($goto_path));
			foreach($stats_goto as $ks => $vs)
				$stats[$ks] = $vs;
			$stats_goto = null;
		}
	}
	
	// Show stats for complete tree below current path
	$log = merge_down($stats, $path);
	
	$counter = show_log($log);
	if ($counter== 0) {
		?>
		<p>There is no recorded activity in path <b><?=$path?></b>.<br>User probably created this path after the last statistics update. You can update the stats manually. If the problem persists after update, please contact your administrator.</p>
		<?php 
	}
}



function evtsort($a, $b) {
	if ($a['time'] == $b['time']) return 0;
	return ($a['time'] < $b['time']) ? -1 : 1;
}

function merge_down(&$stats, $subpath) {
	$events = $stats['global_events'];
	foreach($events as &$event) 
		if (array_key_exists('real_time', $event))
			$event['time'] = $event['real_time'];
	if ($subpath == "") {
		foreach($stats as $path => $data) {
			if (!is_array($data) || !array_key_exists('events', $data)) continue;
			foreach($data['events'] as &$event)
				$event['path'] = $path;
			$events = array_merge($events, $data['events']);
		}
	} else
		$events = array_merge($events, merge_down_recursive($stats, $subpath));
	
	usort($events, 'evtsort');
	return $events;
}


function merge_down_recursive(&$stats, $subpath) {
	foreach($stats[$subpath]['events'] as &$event) {
		$event['path'] = $subpath; // I ovdje trebamo setovati path
	}
	$events = $stats[$subpath]['events'];
	$entries = $stats[$subpath]['entries'];
	foreach ($entries as $entry)
		$events = array_merge($events, merge_down_recursive($stats, $entry));

	return $events;
}

function show_log($log, $skip_empty = true) {
	$limit_stranica = 3000; // PROBA
	$vrijeme_limit = 60;
	$paste_limit_linija = 5;

	// Ovo će biti zabavno
	?>
	<script language="JavaScript">
	function showhide(id) {
		var o = document.getElementById(id);
		if (o.style.display=="inline"){
			o.style.display="none";
		} else {
			o.style.display="inline";
		}
	}
	</script>
	<?php
	
	// Razdvoji po danima
	$po_danima = array();
	foreach ($log as &$entry) {
		$date = mktime(0,0,0, date("n",$entry['time']), date("j",$entry['time']), date("Y",$entry['time']));
		$juce = mktime(0,0,0, date("n",$entry['time']-24*60*60), date("j",$entry['time']-24*60*60), date("Y",$entry['time']-24*60*60));
		if (array_key_exists($juce, $po_danima) && end($po_danima[$juce])['text'] != "logout" && $entry['text'] != "login")
			array_push($po_danima[$juce], $entry);
		else {
			if (!array_key_exists($date, $po_danima))
				$po_danima[$date] = array();
			array_push($po_danima[$date], $entry);
		}
	}
	
	// Preskačem dane kada nije bilo ništa osim login/logout
	foreach($po_danima as $datum => $stavke) {
		$ima = false;
		foreach($stavke as $stavka) {
			if ($stavka['text'] != "login" && $stavka['text'] != "logout") {
				$ima = true;
				break;
			}
		}
		if (!$ima) {
			unset($po_danima[$datum]);
		}
	}
	
	krsort($po_danima); // Sortiraj unazad
	
	function htmlize($niz) {
		foreach($niz as &$el) {
			if (strlen($el)>1000)
				$el = substr($el,0,1000) . "...";
			$el = htmlspecialchars($el);
		}
		return join("<br>", $niz);
	}
	
	function print_polje($klasa, $lijevo, $id_detalji, $detalji) {
		if (count($detalji) == 0) {
		?>
		<div class="log <?=$klasa?>">
			<span class="log-lijevo"><?=$lijevo?></span>
		</div>
		<?php
			return;
		}
		?>
		<div class="log <?=$klasa?>" onclick="javascript:showhide('<?=$id_detalji?>')">
			<span class="log-lijevo"><?=$lijevo?></span>
			<span class="log-detalji-link">detalji</span>
		</div>
		<div id="<?=$id_detalji?>" class="log-detalji">
		<?php
		foreach ($detalji as $detalj) {
			?>
			<div class="log-detalji1"><?=date("H:i:s", $detalj['time'])?>
			<?php
			if (array_key_exists('add_lines', $detalj['text'])) 
				print "<span class=\"log-detalji1-add\">".htmlize($detalj['text']['add_lines'])."</span>";
			if (array_key_exists('remove_lines', $detalj['text'])) 
				print "<span class=\"log-detalji1-remove\">".htmlize($detalj['text']['remove_lines'])."</span>";
			if (array_key_exists('change', $detalj['text'])) 
				print "<span class=\"log-detalji1-change\">".htmlize($detalj['text']['change'])."</span>";
			if (array_key_exists('lines', $detalj['text'])) 
				print "<span class=\"log-detalji1-lines\">".htmlize($detalj['text']['lines'])."</span>";
			print "</div>\n";
		}
		print "</div>\n";
	}
	
	function print_mod(&$mod) {
		$klasa = "log-edit";
		$lijevo = date("H:i:s", $mod['start']) . " - " . date("H:i:s", $mod['end']) . " rad na datoteci " . $mod['path'];
		$id_detalji = "modified-".$mod['end'];
		print_polje ($klasa, $lijevo, $id_detalji, $mod['diffs']);
		$mod['path'] = ""; 
		$mod['diffs'] = array();
	}
	
	$brojac = 0;
	$imena_dana = array("Nedjelja", "Ponedjeljak", "Utorak", "Srijeda", "Četvrtak", "Petak", "Subota");
	$bio_create = false;
	$rbr=0;
	foreach($po_danima as $datum => $stavke) {
		$ddatum = date("d.m.Y", $datum);
		$dan = date("w", $datum);
		print "<h3>".$imena_dana[$dan].", $ddatum</h3>\n";
		
		$mod = array( "path" => "", "diffs" => array() );
		
		foreach($stavke as $stavka) {
			$rbr++;
			$vrijeme = date("H:i:s", $stavka['time']);

			// Izmjena fajla
			if ($stavka['text'] == "modified") {
				// Da li je paste
				$promijenjenih_linija = 0;
				if (array_key_exists('add_lines', $stavka['diff'])) $promijenjenih_linija += count($stavka['diff']['add_lines']);
				if (array_key_exists('remove_lines', $stavka['diff'])) $promijenjenih_linija += count($stavka['diff']['remove_lines']);
				if (array_key_exists('change', $stavka['diff'])) $promijenjenih_linija += count($stavka['diff']['change']);
				if ($promijenjenih_linija > $paste_limit_linija) {
					if ($mod['path'] != "")
						print_mod($mod);
						
					// Printamo paste
					$klasa = "log-paste";
					$lijevo = $vrijeme . " paste u datoteci " . $stavka['path'] . " ($promijenjenih_linija linija)";
					$id_detalji = "paste-$rbr";
					$detalji = array(array( 'time' => $stavka['time'], 'text' => $stavka['diff'] ));
					print_polje($klasa, $lijevo, $id_detalji, $detalji);
					continue; // Da se ne bi započeo $mod
				}
				
				if ($mod['path'] != "") {
					if ($stavka['path'] != $mod['path'] || $stavka['time'] - $mod['end'] > $vrijeme_limit)
						print_mod($mod);
					else {
						$mod['end'] = $stavka['time'];
						$diff = array('time' => $stavka['time'], 'text' => $stavka['diff']);
						array_push($mod['diffs'], $diff);
					}
				}
				if ($mod['path'] == "") { 
					$mod['path'] = $stavka['path'];
					$mod['start'] = $mod['end'] = $stavka['time'];
					$mod['diffs'] = array( array('time' => $stavka['time'], 'text' => $stavka['diff']) );
				}
			} else {
				if ($mod['path'] != "")
					print_mod($mod);
			}
			if ($stavka['text'] == "login") print "<div class=\"log log-login\">$vrijeme login</div>";
			if ($stavka['text'] == "logout") print "<div class=\"log log-login\">$vrijeme logout</div>";
			if ($stavka['text'] == "logout" && $bio_create) break;
			if ($stavka['text'] == "created" || $stavka['text'] == "created_folder") {
				$klasa = "log-create";
				if ($stavka['path'] == ".gcc.out" || $stavka['path'] == "runme") continue;
				if ($stavka['text'] == "created_folder") 
					$lijevo = "$vrijeme kreiran folder " . $stavka['path'];
				else
					$lijevo = "$vrijeme kreirana datoteka " . $stavka['path'];
				$id_detalji = "create-$rbr";
				$detalji = array();
				if (array_key_exists('content',$stavka) && !empty($stavka['content']))
					if ($stavka['content'] == "binary")
						$lijevo .= " (binarna)";
					else
						$detalji = array(array ( 'time' => $stavka['time'], 'text' => array ( 'add_lines' => explode("\n", $stavka['content']) ) ));
				print_polje($klasa, $lijevo, $id_detalji, $detalji);
				//if (isset($_REQUEST['putanja']) && ($stavka['filename'] == $_REQUEST['putanja'] || array_key_exists('path', $stavka) && $stavka['path'] == $_REQUEST['putanja'])) 
				//	$bio_create = true;
			}
			if ($stavka['text'] == "deleted") {
				$klasa = "log-delete";
				if ($stavka['path'] == ".gcc.out" || $stavka['path'] == "runme") continue;
				$lijevo = "$vrijeme obrisana datoteka " . $stavka['path'];
				$id_detalji = "delete-$rbr";
				$detalji = array();
				print_polje($klasa, $lijevo, $id_detalji, $detalji);
			}
			if ($stavka['text'] == "rename") {
				$klasa = "log-rename";
				$lijevo = "$vrijeme promijenjeno ime datoteke iz " . $stavka['old_filepath'] . " u " .$stavka['path'];
				$id_detalji = "delete-$rbr";
				$detalji = array();
				print_polje($klasa, $lijevo, $id_detalji, $detalji);
			}
			if ($stavka['text'] == "move") {
				$klasa = "log-rename";
				$fouldeur = substr($stavka['path'], 0, strlen($stavka['path']) - strlen($stavka['filename']) - 1);
				if ($fouldeur == "") $fouldeur = "/";
				$lijevo = "$vrijeme datoteka " . $stavka['old_filepath'] . " premještena u folder $fouldeur";
				$id_detalji = "delete-$rbr";
				$detalji = array();
				print_polje($klasa, $lijevo, $id_detalji, $detalji);
			}
			if ($stavka['text'] == "compiled") {
			//print_r($stavka);
				$klasa = "log-build";
				if ($stavka['path'] == ".gcc.out")
					$lijevo = "$vrijeme neki program u početnom folderu (vjerovatno hello.c) je kompajliran\n";
				else
					$lijevo = "$vrijeme program " . $stavka['path'] . " kompajliran sa greškama";
				$id_detalji = "build-$rbr";
				$detalji = array();
				if (array_key_exists('output',$stavka) && !empty($stavka['output']))
					$detalji = array(array ( 'time' => $stavka['time'], 'text' => array ( 'add_lines' => explode("\n", $stavka['output']) ) ));
				//print_r($detalji);
				print_polje($klasa, $lijevo, $id_detalji, $detalji);
			}
			if ($stavka['text'] == "compiled successfully") {
				$klasa = "log-run";
				if ($stavka['path'] == "runme")
					$lijevo = "$vrijeme neki program u početnom folderu (vjerovatno hello.c) je pokrenut\n";
				else
					$lijevo = "$vrijeme program " . $stavka['path'] . " kompajliran i pokrenut";
				$id_detalji = "run-$rbr";
				$detalji = array();
				if (array_key_exists('output',$stavka) && !empty($stavka['output']))
					$detalji = array(array ( 'time' => $stavka['time'], 'text' => array ( 'add_lines' => explode("\n", $stavka['output']) ) ));
				//print_r($detalji);
				print_polje($klasa, $lijevo, $id_detalji, $detalji);
			}
			if ($stavka['text'] == "ran tests") {
				$klasa = "log-test";
				if ($stavka['path'] == ".at_result")
					$lijevo = "$vrijeme testovi izvršeni u korijenskom folderu ";
				else
					$lijevo = "$vrijeme program " . $stavka['path'] . " testiran ";
				$lijevo .= "(".$stavka['test_results'].")";
				$id_detalji = "test-$rbr";
				$detalji = array();
				//print_r($detalji);
				print_polje($klasa, $lijevo, $id_detalji, $detalji);
			}
		}
		if ($stavka['text'] == "logout" && $bio_create) break;

		// Posljednji mod (mada bi u pravilu poslije ovoga trebao biti barem logout...)
		if ($mod['path'] != "")
			print_mod($mod);
		
		$brojac += count($stavke);
		if ($brojac > $limit_stranica) break;
	}
	return $brojac;
}


?>