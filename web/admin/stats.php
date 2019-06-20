<?php

// ADMIN/STATS.PHP - pluggable modules for admin.php for showing various stats


// Webide usage stats
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
				$realno = -1;
				$parts = explode(" ", trim($logline));
				if (count($parts)<2) continue;
				
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


// Buildservice stats
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



// Results for an exam
function admin_exam_stats() {
	global $conf_base_path;
	
	$current_exam = "OR/Ispit6";
	$exam_students = array(
//	"saksamovic1", "nbadzak1", "lbecirevic1", "abecirovic3", "vbeglerovi2", "lbegovic2", "acicvara1", "icajo1", "vcelan1", "hcorbo2", "mdokic1", "efejzic1", "kforto1", "fhajdarpas1", "rhandzic1", "aharba2", "bhasanagic1", "nhastor1", "hhodzic2", "ehondo1", "thorozovic1", "eicanovic1", "djugo1", "tkadric1", "mkamali1", "akevric2", "hkovacevic1", "skozic2", "skujrakovi1", "ekurtovic3", "amahmutovi5", "fmaric1", "tmarkesic1", "amehmedovi1", "tmemic1", "smerzic1", "emujanovic3", "lmujic1", "mmurtic2", "tosmanagic1", "spljakic1", "apolutan2", "lsmajlovic1", "rtahic1", "rtomas1", "atopalovic2", "ivrce1", "azahirovic1", "azunic1", "jcaluk1"
//"aalagic2", "bbiberovic1", "hbijedic1", "dbriski1", "ebrljak1", "ecocalic1", "fdemir1", "adizdarevi1", "nduvnjak1", "edzaferovi1", "kdokic1", "ngafic1", "vhasic1", "khatibovic1", "hhadzic3", "bhuseinovi1", "zjavdan1", "mkapo2", "bkarovic1", "ikovac1", "emerdic2", "bniksic1", "nomanovic2", "mparic2", "erazanica2", "fselimovic1", "esmailagic1", "istanic1", "ssuljevic1", "ktafro1", "avelic3"

//"saksamovic1", "aalagic2", "fbazdar1", "abegic2", "lbuturovic1", "abuza2", "aceman1", "fdemir1", "kdokic1", "afazlagic1", "hhadzic3", "ahalilovi15", "rhandzic1", "thasic1", "lhodzic2",  "ahrnjic3", "bhuseinovi1", "ikovac1", "mkapo2", "bkarovic1", "akarzic1", "akojasevic1", "lkovac1", "skujrakovi1", "klazovic1", "nmerdovic1", "anikolic1", "knurikic1", "nomanovic2", "tosmanagic1", "apandur1", "spljakic1", "apolutan2", "nrovcanin1", "msalihovic3", "fselimovic1", "esmailagic1", "ssuljevic1", "bsuljic1",  "tsahovic1", "rtomas1", "stopalovic1"


//"nbadzak1", "bbiberovic1", "dbokan1", "dbriski1", "mcorbo1", "ncenanovic1", "ngafic1", "bhasanagic1", "vhasic1", "zjavdan1", "skahvedzic1", "akevric2", "kkozlica1", "skorac1", "akovacevic4", "ekudic1", "tmehulic1", "dmelunovic1", "mmuhovic2", "anikolic1", "bniksic1", "apajevic1", "apasic2", "tpervan1", "aprljaca1", "epruzan1", "erazanica2", "istanic1", "msubasic2", "esuljkic1", "nsuvalija1", "mucanbarli1", "avelic3", "ivrce1", "azunic1"
"aagic1", "halagic1", "aalic7", "ebrljak1", "ecocalic1", "jcaluk1", "vcelan1", "ndizdarevi1", "nduvnjak1", "edzaferovi1", "neskerica1", 
"mgojak1", "khadzic2", "mhanjalic1", "aharba2", "hhamzic1", "dhasimbego1", "iisabegovi1", "iicanovic1", "ikarabeg1", "mkaravdic1", 
"nkoldzo1", "tkrivosija1", "ekurtovic3", "amajdanac1", "mmujkanovi1", "anesimi1", "dpivac1", "dpopovic1", "erudalija1", "hselimovic1", 
"kselman1", "fsarancic1", "asiljak1", "ksljivo1", "ktafro1", "htopic1", "ezahirovic2", "azaimovic2", "szolota1" 


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
			if ($exists != 1) {
				$path = str_replace("OR", "UUP", $path);
				$exists = exec("sudo $conf_base_path/bin/wsaccess $username exists \"$path\"");
			}
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
