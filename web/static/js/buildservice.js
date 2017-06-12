
// BUILDSERVICE.JS - Scripts for invoking buildservice via AJAX
// Version: 2017/03/22 12:12

var buildservice_finished = 0;

var buildservice_test_status = [ "", "Ok", "Symbol not found", "Doesn't compile", "Timeout", "Crash", "Wrong output", "Profiler error", "Output not found", "Exception" ];


// Invoke a web service that copies the .at_result file into user workspace

function buildserviceCopyFile(bsInstance, username, code_path) {
	var xmlhttp = new XMLHttpRequest();
	var url = "/buildservice/copyFile.php?program=" + bsInstance + "&username=" + username + "&filename=" + code_path;
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


// Update progress bar (for startTestMulti)

function buildserviceIncrementProgress(total) {
	buildservice_finished++;
	percent = Math.round((buildservice_finished / total) * 100);
	updateProgress(percent);
	if (buildservice_finished == total) setTimeout(hideProgress, 2000);
}


// Function verifyTest asynchronously test for buildservice request status every 0.5 seconds
// When testing is finished, .at_result will be copied into user workspace
// If total=0, a messagebox is shown, otherwise a progress bar is updated

function buildserviceVerifyTest(bsInstance, username, code_path, total) {
	var xmlhttp = new XMLHttpRequest();
	var url = "/buildservice/push.php?action=getProgramStatus&program="+bsInstance;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			if (result.success == "true") {
				// result.status has the same format as test results otherwise have
				//totalTests = testSpecification.test_specifications.length;
				var status = result.status;
				console.log("buildserviceVerifyTest "+code_path+" status "+status.status);
				
				if ('status' in status && status.status != 1 && status.status != 7) {
					if (status.status == 3 && total==0)
						showMsg("Doesn't compile");
					else if (status.status == 6 && total==0)
						showMsg("Sources not found");
					else if (!('test_results' in status) && total==0)
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
						if (total==0) showMsg("Result: "+testsPassed+"/"+totalTests);
						console.log("buildserviceVerifyTest "+code_path+" passed "+testsPassed+" of "+totalTests);
					}
					if (total > 0) 
						buildserviceIncrementProgress(total);
					else 
						setTimeout(hideMsg,5000);
					buildserviceCopyFile(bsInstance, username, code_path);
					return true;
				}
				
				// When tests are just started, object doesn't have this member at all
				if ('test_results' in status)
					// Requires IE9+, FF4+, Safari 5+
					finishedTests = Object.keys(status.test_results).length+1;
				else
					finishedTests = 1;

				//console.log("Testing in progress. Done "+finishedTests+" of "+totalTests);
				if (total==0) showMsg("Testing "+finishedTests);
				setTimeout(function(){ buildserviceVerifyTest(bsInstance, username, code_path, total); }, 500);
			} else {
				// The only possibility is that instance doesn't exist, that is the testing finished in the meanwhil
				setTimeout(function(){ buildserviceVerifyTest(bsInstance, username, code_path, total); }, 500);
			}
			return false;
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


// Start test for single project (if third param is 0)

function buildserviceStartTest(username, code_path, total) {
	var url = 'buildservice/submit_c9.php?sstudent='+username+'&filename='+code_path;
	console.log("buildserviceStartTest("+username+","+code_path+","+total+")");
	if (total==0) showMsg("Starting tests...");
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			if (result.success == "true") {
				bsInstance = result.instance;
				if (total==0) showMsg("Testing started "+bsInstance);
				console.log("Testing started "+code_path+" "+bsInstance);
				setTimeout(function(){ buildserviceVerifyTest(bsInstance, username, code_path, total); }, 500);
			} else {
				console.log("Success !true "+code_path);
				if (total==0) {
					showMsg("Test failed to start");
					setTimeout(hideMsg,5000);
				} else {
					buildserviceIncrementProgress(total);
				}
				console.log(result);
				console.log(result.message);
			}
		}
		if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
			if (total==0) {
				showMsg("Test failed to start");
				setTimeout(hideMsg,5000);
			} else {
				buildserviceIncrementProgress(total);
			}
			console.log("submit_c9 "+code_path+"readyState "+xmlhttp.readyState+" status "+xmlhttp.status);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


// Start test for many projects
function buildserviceStartTestMulti(test_dirs_list) {
	buildservice_finished = 0;
	total = test_dirs_list.length;
	showProgress("Testing " + total + " projects");
	for (i=0; i<total; i++) {
		buildserviceStartTest(test_dirs_list[i].username, test_dirs_list[i].path, total);
	}
}
