<?php

function admin_stats() {
	global $conf_base_path;

	// Parameter "period"
	if (isset($_REQUEST['period'])) {
		$period = $_REQUEST['period'];
		if (substr($period, strlen($period)-1) == "h") 
			$period = intval($period) * 60*60;
		else // days
			$period = intval($period) * 24*60*60;
	} else
		$period = 24*60*60; // default period = 1 day

	$sec_min = time() - $period;
	
	// Show statistics
	?>
	<p id="p-return"><a href="admin.php">Return to courses list</a></p>
	<h1>Usage stats</h1>
	
	<p>Period: <a href="admin.php?stats&amp;period=12h">12 hours</a> * <a href="admin.php?stats&amp;period=1d">1 day</a> * <a href="admin.php?stats&amp;period=7d">7 days</a> * <a href="admin.php?stats&amp;period=30d">30 days</a></p>
	
	<script type="text/javascript"
		src="https://www.google.com/jsapi?autoload={
		'modules':[{
		'name':'visualization',
		'version':'1',
		'packages':['corechart']
		}]
		}"></script>

	<script type="text/javascript">
	google.setOnLoadCallback(drawChart);

	function drawChart() {
		var data = new google.visualization.DataTable();
		data.addColumn('datetime', 'Time');
		data.addColumn('number', 'Users');
		data.addColumn('number', 'Active users');
		
		data.addRows([
		<?php
		$handle = fopen($conf_base_path . "/user_stats.log", "r");
		if ($handle) {
			while (($logline = fgets($handle, 4096)) !== false) {
				$realno=-1;
				$parts = explode(" ", trim($logline));
				$sec = $parts[0];
				$no = intval($parts[1]);
				if (count($parts)>2) $realno = $parts[2];
				if (!isset($realno) || $realno==-1) $realno=$no;
				if ($sec < $sec_min) continue;
				print "[ new Date(" . date("Y,", $sec) . (date("n", $sec)-1) . date(",j,G,i,s", $sec) . ",0), $no, $realno ],\n";
			}
			fclose($handle);
		}
		?>
		]);

		var options = {
		title: 'Number of concurrent users',
		legend: { position: 'bottom' }
		};

		var chart = new google.visualization.LineChart(document.getElementById('chart_users'));

		chart.draw(data, options);
		
		// Second graph - load average
		
		var data2 = new google.visualization.DataTable();
		data2.addColumn('datetime', 'Time');
		data2.addColumn('number', 'c9');
		data2.addColumn('number', 'storage');
		data2.addColumn('number', 'c9prim');
		data2.addColumn('number', 'c9sec');
		
		data2.addRows([
		<?php
		$handle = fopen($conf_base_path . "/load_stats.log", "r");
		if ($handle) {
			while (($logline = fgets($handle, 4096)) !== false) {
				$storage = $prim = $second = 0;
				$ar = explode(" ", trim($logline));
				$sec = intval($ar[0]); $c9 = floatval($ar[1]);
				if (count($ar)>2) $storage = floatval($ar[2]); 
				if (count($ar)>3) $prim = floatval($ar[3]); 
				if (count($ar)>4) $second = floatval($ar[4]); 
				if ($sec < $sec_min) continue;
				print "[ new Date(" . date("Y,", $sec) . (date("n", $sec)-1) . date(",j,G,i,s", $sec) . ",0), $c9, $storage, $prim, $second ],\n";
			}
			fclose($handle);
		}
		?>
		]);

		var options2 = {
		title: 'Server load average (5min)',
		legend: { position: 'bottom' }
		};

		var chart2 = new google.visualization.LineChart(document.getElementById('chart_load'));

		chart2.draw(data2, options2);
		
		// Third graph - ram
		
		var data3 = new google.visualization.DataTable();
		data3.addColumn('datetime', 'Time');
		data3.addColumn('number', 'c9');
		data3.addColumn('number', 'storage');
		data3.addColumn('number', 'c9prim');
		data3.addColumn('number', 'c9sec');
		
		data3.addRows([
		<?php
		$handle = fopen($conf_base_path . "/mem_stats.log", "r");
		if ($handle) {
			while (($logline = fgets($handle, 4096)) !== false) {
				$ar = explode(" ", trim($logline));
				$sec = intval($ar[0]); $c9 = floatval($ar[1]);
				if (count($ar)>2) $storage = floatval($ar[2]); 
				if (count($ar)>3) $prim = floatval($ar[3]); 
				if (count($ar)>4) $second = floatval($ar[4]); 
				if ($sec < $sec_min) continue;
				$c9 = round(($c9 / 1024) / 1024, 3);
				$storage = round(($storage / 1024) / 1024, 3);
				$prim = round(($prim / 1024) / 1024, 3);
				$second = round(($second / 1024) / 1024, 3);
				print "[ new Date(" . date("Y,", $sec) . (date("n", $sec)-1) . date(",j,G,i,s", $sec) . ",0), $c9, $storage, $prim, $second ],\n";
			}
			fclose($handle);
		}
		?>
		]);

		var options3 = {
		title: 'Memory usage (GiB)',
		legend: { position: 'bottom' }
		};

		var chart3 = new google.visualization.LineChart(document.getElementById('chart_mem'));

		chart3.draw(data3, options3);
	}
	</script>
	<div id="chart_users" style="width: 900px; height: 500px"></div>
	<div id="chart_load" style="width: 900px; height: 500px"></div>
	<div id="chart_mem" style="width: 900px; height: 500px"></div>
	<?php
}


function admin_bsstats() {
	global $conf_base_path;
	$bs_path = "/tmp/buildservice";
	$file = file_get_contents("/tmp/buildservice/queuefile");
	if (empty($file)) {
		$queue=0; 
	} else {
		$queue = count(explode("\n", $file));
	}
	$stats = json_decode(file_get_contents("/tmp/buildservice/stats"), true);
	
	// Show statistics
	?>
	<p id="p-return"><a href="admin.php">Return to courses list</a></p>
	<h1>Buildservice stats</h1>
	<p>Tasks in queue: <?=$queue?></p>
	<h2>Build hosts:</h2>
	<table><tr><th>Name:</th><th>IP address:</th><th>Last build time:</th></tr>
	<?php
	foreach ($stats as $name => $data) {
		print "<tr><td>$name</td><td>".$data['ip']."</td><td>".date("d.m.Y H:i:s", $data['time'])."</td></tr>\n";
	}
	?>
	</table>
	<?php
}



function admin_exam_stats() {
	global $conf_base_path;
	
	$current_exam = "OR/Ispit13";
	$exam_students = array(
	"hahmatovic1", "salic3", "aavdic6", "abeslagic1", "nbisevac1", "ebosnjakov1", "abuljina1", "dcikic1", "acoko2", "lcoloman1", "edeljo1", "iduracak1", "nduvnjak1", "adelmo2", "afajic1", "mfisic1", "agobeljic1", "bhadzic2", "nhanic2", "aharbas1", "eherenda1", "ridrizovic1", "eimamovic2", "lkafedzic1", "skehic1", "aklepic1", "dlukovic1", "emehic3", "amehmedovi1", "fmesic1", "hmezit1", "amilisic2", "mmujezinov1", "amusic5", "mmusinovic2", "anedzibovi1", "anocajevic1", "jpetrovic1", "mplakalovi1", "apuljiz1", "arizvo2", "ksarajlic1", "msirco1", "islagalo1", "asuljagic1", "ksuljic1", "ssabic2", "nsahovic1", "asikalo2", "nsljivo1", "msosic1", "ltalic1", "statar1", "augljesa2", "avujcic1", "nvelic3", "izdralovic1"
	);

	// Hardcoded 4 assignments
	?>
	<p id="p-return"><a href="admin.php">Return to courses list</a></p>
	<h1>Exam stats</h1>
	<table><tr><th>Username:</th><th>Z1:</th><th>Z2:</th><th>Z3:</th><th>Z4:</th><th>Score</th></tr>
	<?php
	$tasks = array();
	
	foreach ($exam_students as $username) {
		print "<tr><td>$username</td>";
		$score = 0;
		for ($i=1; $i<=4; $i++) {
			$path = $current_exam . "/Z$i/.at_result";
			$exists = exec("sudo $conf_base_path/bin/wsaccess $username exists \"$path\"");
			if ($exists == 1) {
				$at_result = json_decode(shell_exec("sudo $conf_base_path/bin/wsaccess $username read \"$path\""), true);
				//print_r($at_result);
				//return;
				$tests = count($at_result["test_results"]);
				$passed = 0;
				foreach ($at_result["test_results"] as $test) {
					if ($test['status'] == 1) $passed++;
				}
				print "<td id=\"$username-Z$i\" style=\"width: 50px\">$passed/$tests</td>\n";
				$score += ($passed/$tests) * 10;
			} else 
				print "<td id=\"$username-Z$i\" style=\"width: 50px\">/</td>\n";
			$tasks[$username."-Z$i"] = $path;
		}
		$score = round($score, 2);
		print "<td style=\"width: 50px\">$score</td></tr>\n";
	}
	?>
	</table>
	
	<SCRIPT language="JavaScript">
	
	</SCRIPT>
	<?php
}

?>
