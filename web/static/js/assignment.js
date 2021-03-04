
// ASSIGNMENT.JS - Functions for deploying assignment files to users
// Version: 2018/02/12 11:20

var currentlyDeploying = false;


// Helper function that constructs part of ws url with a bunch of data
function assignmentBuildUrl(course, year, external, assignment, task, filename, user) {
	var url = "course=" + course + "&year=" + year;
	if (external == true || external == 1 || external == "X") url += "&X";
	url += "&assignment=" + assignment + "&task=" + task;
	if (filename) url += "&file=" + filename;
	if (user) url += "&user=" + user;
	return url;
}


function deployAssignmentFile(course, year, external, asgn_id, task_id, filename, user) {
	// Double-click protection
	if (currentlyDeploying != false) {
		console.log("Deployment in progress");
		return;
	}
	
	var url = "/assignment/ws.php?action=deploy&" 
		+ assignmentBuildUrl(course, year, external, asgn_id, task_id, filename, user);
	
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			if (result.success == "true") {
				currentlyDeploying = result.data;
				if (user == "all-users") {
					showProgress("Deploying file "+filename+" to all users");
					setTimeout( function() { deploymentStatus(user); }, 100 );
				} else {
					currentlyDeploying = false;
					showMsg("File successfully deployed");
				}
			} else {
				console.error("FAIL: " + url + " " + result.code);
			}
		}
		if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
			console.error("FAIL: " + url + " 500");
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
	return false;
}


function deploymentStatus(user) {
	if (currentlyDeploying == false) {
		return;
	}
	var url = "/assignment/ws.php?action=deploy_status&id="+currentlyDeploying;
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			if (result.success == "true") {
				if (user == "all-users") {
					var percent = Math.round((result.data.done / result.data.total) * 100);
					updateProgress(percent);
					console.log("updateProgress "+percent);
					if (percent > 99.9) {
						currentlyDeploying = false;
						setTimeout(hideProgress, 1000);
					} else
						setTimeout( function() { deploymentStatus(user); }, 100);
				} else {
					if (result.data.done > 0) {
						currentlyDeploying = false;
						showMsg("File successfully deployed");
					} else
						setTimeout( function() { deploymentStatus(user); }, 100);
				}
			} else {
				currentlyDeploying = false;
				if (user == "all-users")
					setTimeout(hideProgress, 1000);
				else
					showMsg("File deployment failed");
				console.error("FAIL: " + url + " " + result.code);
			}
		}
		if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
			currentlyDeploying = false;
			if (user == "all-users")
				setTimeout(hideProgress, 1000);
			else
				showMsg("File deployment failed");
			console.error("FAIL: " + url + " 500");
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


function listAssignmentFiles(course, year, external, assignment, task, callback_function) {
	var url = "/assignment/ws.php?action=files&"
		+ assignmentBuildUrl(course, year, external, assignment, task);
	
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			if (result.success == "true") {
				files = result.data;
				callback_function(files);
			}
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


function assignmentFromPath(task_path, callback_function) {
	var url = "/assignment/ws.php?action=from_path&task_path=" + task_path;
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			if (result.success == "true") {
				task_data = result.data;
				callback_function(task_data);
			}
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


function doOpenAutotestGenV2(course, year, external, assignment, task, path) {
	var url = "/assignment/ws.php?action=getFile&"
		+ assignmentBuildUrl(course, year, external, assignment, task, path);
	
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			window.localStorage.setItem('.autotest-content', xmlhttp.responseText);
			const newWindow = Helpers.openGenerator('static/js/autotest-genv2/html/index.html','', true);
			
			newWindow.addEventListener('load', () => {
				const button = newWindow.document.getElementById('export-button');
				button.addEventListener("click", () => {
					updateAutotestFile(course, year, external, assignment, task, path);
				});
			}, false);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
	return false;
}


function updateAutotestFile(course, year, external, assignment, task, path) {
	var url = "/assignment/ws.php?action=updateFile&"
		+ assignmentBuildUrl(course, year, external, assignment, task, path);
	
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			console.log(xmlhttp.responseText);
		}
	}
	
	var data = "data=" + encodeURIComponent(window.localStorage.getItem('.autotest-content'));
	
	xmlhttp.open("POST", url, true);
	xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xmlhttp.send(data);
}
