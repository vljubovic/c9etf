<?php

// TOOLBAR.PHP - toolbar buttons above editor

function pwi_toolbar($username, $cur_path) {
	
	print "<div id=\"phpwebide_toolbar\">\n";
	if (isset($_REQUEST['svn_rev'])) {
		$restoreurl = "admin.php?user=$username&amp;path=".urlencode($cur_path)."&amp;action=restore_revision&amp;svn_rev=".intval($_REQUEST['svn_rev']);
		?>
		<span class="tree-button"><a href="<?=$restoreurl?>"><i class="fa fa-eye-slash fa-2x"></i> Restore this revision</a></span>
		<?php
	}

	send_homework_button($username, $cur_path);
	test_button($username, $cur_path);
	
	modify_time($username, $cur_path);
	print "</div>\n";
}

function send_homework_button($username, $cur_path) {
	global $conf_base_path;
	
	// Look for folder with file .zadaca
	$homework_path = "$cur_path/.zadaca";
	$exists = `sudo $conf_base_path/bin/wsaccess $username exists "$homework_path"`;
	while ($exists != 1) {
		$pos = strrpos($cur_path, "/");
		if (!$pos) return;
		$cur_path = substr($cur_path, 0, $pos);
		$homework_path = "$cur_path/.zadaca";
		$exists = `sudo $conf_base_path/bin/wsaccess $username exists "$homework_path"`;
	}
	
	// Look for a file with code
	$path = "$cur_path";
	$code_file = "";
	if ($cur_path == "") 
		$filelist = `sudo $conf_base_path/bin/wsaccess $username list /`;
	else
		$filelist = `sudo $conf_base_path/bin/wsaccess $username list "$cur_path"`;

	if (substr($filelist, 0, 6) != "ERROR:") {
		foreach(explode("\n", $filelist) as $entry) {
			if ($entry[0] != "." && $entry != "runme") {
				$code_file = $entry;
				break;
			}
		}
		if ($code_file === "") return;
		$code_path = "$cur_path/$code_file";
	} else $code_path = $cur_path;
	
	$homework_data = json_decode(`sudo $conf_base_path/bin/wsaccess $username read $homework_path`,true);
	$send_url = "zamger/slanje_zadace.php?username=$username&amp;filename=$code_path&amp;zadaca=" . $homework_data['id'] . "&amp;zadatak=" . $homework_data['zadatak'];
	?>
	<span class="tree-button"><a href="<?=$send_url?>"><i class="fa fa-cloud-upload fa-2x"></i> Send homework</a></span>
	<?php
}

function test_button($username, $cur_path) {
	global $conf_base_path;
	
	// Is there an .autotest file?
	$at_path = "$cur_path";
	$isdir = `sudo $conf_base_path/bin/wsaccess $username isdir "$at_path"`;
	if ($isdir != 1) {
		$pos = strrpos($cur_path, "/");
		if (!$pos) return;
		$cur_path = substr($cur_path, 0, $pos);
		$at_path = $cur_path;
		$isdir = `sudo $conf_base_path/bin/wsaccess $username isdir "$cur_path"`;
	}
	$at_path .= "/.autotest";
	
	$exists = `sudo $conf_base_path/bin/wsaccess $username exists "$at_path"`;
	if ($exists != 1) return;

	$code_path = $cur_path;
	$send_url = "buildservice/submit_c9.php?sstudent=$username&filename=$code_path";

	?>
	<SCRIPT>
	var bsInstance = 0;
	
	function copyFile() {
		var xmlhttp = new XMLHttpRequest();
		var url = "/buildservice/copyFile.php?program="+bsInstance+"&username=<?=$username?>&filename=<?=$code_path?>";
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				result = JSON.parse(xmlhttp.responseText);
				if (result.success == "true") {
					console.log("File copied");
				}
			}
			if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
				console.log("Server error. Contact administrator");
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	
	function verifyTest() {
		var xmlhttp = new XMLHttpRequest();
		var url = "/buildservice/push.php?action=getProgramStatus&program="+bsInstance;
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				result = JSON.parse(xmlhttp.responseText);
				if (result.success == "true") {
					// result.status has the same format as test results otherwise have
					//totalTests = testSpecification.test_specifications.length;
					var status = result.status;
					
					if ('status' in status && status.status != 1 && status.status != 7) {
						//console.log("Testing finished. Copying file");
						//copyFile();
						if (status.status == 3)
							showMsg("Doesn't compile");
						else if (status.status == 6)
							showMsg("Sources not found");
						else if (!('test_results' in status))
							showMsg("Test successful, no results");
						else {
							testResults = status.test_results;
							testsPassed = totalTests = 0;
							for(test_id in testResults) {
								test = testResults[test_id];
								totalTests++;
								if (test.status == "1")
									testsPassed++;
							}
							showMsg("Result: "+testsPassed+"/"+totalTests);
						}
						setTimeout(hideMsg,5000);
						return;
					}
					
					// When tests are just started, object doesn't have this member at all
					if ('test_results' in status)
						// Requires IE9+, FF4+, Safari 5+
						finishedTests = Object.keys(status.test_results).length+1;
					else
						finishedTests = 1;

					//console.log("Testing in progress. Done "+finishedTests+" of "+totalTests);
					showMsg("Testing "+finishedTests);
					setTimeout(verifyTest, 500);
				} else {
					// The only possibility is that instance doesn't exist, that is the testing finished in the meanwhil
					setTimeout(verifyTest, 500);
				}
			}
			if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
				showMsg("Test failed to run");
				setTimeout(hideMsg,5000);
				console.log("Server error. Contact administrator");
				console.log("push.php readyState "+xmlhttp.readyState+" status "+xmlhttp.status);
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();		
	}
	
	function startTest() {
		showMsg("Starting tests...");
		var xmlhttp = new XMLHttpRequest();
		var url = "<?=$send_url?>";
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				result = JSON.parse(xmlhttp.responseText);
				if (result.success == "true") {
					bsInstance = result.instance;
					showMsg("Testing started "+bsInstance);
					console.log("Testing started "+bsInstance);
					setTimeout(verifyTest, 500);
				} else {
					showMsg("Test failed to start");
					setTimeout(hideMsg,5000);
					console.log(result);
					console.log(result.message);
				}
			}
			if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
				showMsg("Test failed to start");
				setTimeout(hideMsg,5000);
				console.log("submit_c9 readyState "+xmlhttp.readyState+" status "+xmlhttp.status);
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	</SCRIPT>
	<span class="tree-button"><a href="#" onclick="startTest(); return false;"><i class="fa fa-check-square-o fa-2x"></i> Test</a></span>
	<?php

	$path_atresult = "$cur_path/.at_result";
	$exists = `sudo $conf_base_path/bin/wsaccess $username exists "$path_atresult"`;
	if ($exists != 1) return;
	
	$tests = json_decode(`sudo $conf_base_path/bin/wsaccess $username read "$at_path"`, true);
	$test_results = json_decode(`sudo $conf_base_path/bin/wsaccess $username read "$path_atresult"`, true);

	$total_tests = count($tests['test_specifications']);
	$successful_tests = 0;
	foreach ($test_results['test_results'] as $test) {
		if ($test['status'] == 1) $successful_tests++;
	}
	$output = "$successful_tests/$total_tests";

	$code_path = "$cur_path/main.c";
	$exists = `sudo $conf_base_path/bin/wsaccess $username exists $code_path`;
	if ($exists != 1) $code_path = "$cur_path/main.cpp";
	if (`sudo $conf_base_path/bin/wsaccess $username filemtime "$at_path"` > `sudo $conf_base_path/bin/wsaccess $username filemtime "$path_atresult"` || file_exists($code_path) && `sudo $conf_base_path/bin/wsaccess $username filemtime "$code_path"` > `sudo $conf_base_path/bin/wsaccess $username filemtime "$path_atresult"`)
		$output = "<i class=\"fa fa-clock-o\"></i> ".$output;
	$output = "(" . $output . ")";
		
	$link_to_results = "admin.php?user=$username&amp;path=$cur_path/.at_result";
	
	?>
	<span class="tree-button"><a href="<?=$link_to_results?>"><?=$output?></a></span>
	<?php
	
}

function modify_time($username, $cur_path) {
	global $conf_base_path;
	
	// Is it a file?
	$isdir = `sudo $conf_base_path/bin/wsaccess $username isdir $cur_path`;
	if ($isdir == 1) return;
	
	$time = date("d.m.Y H:i:s", intval(`sudo $conf_base_path/bin/wsaccess $username filemtime "$cur_path"`));

	?>
	<span style="float:right;"><?=$time?></span>
	<?php
}

?>