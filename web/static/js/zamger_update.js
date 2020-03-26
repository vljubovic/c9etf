
// ZAMGER_UPDATE.JS - Update course data from Zamger
// Version: 2018/10/07 17:37



function runUpdates() {
	if (zamgerUpdateTasks.length == 0) return;
	var task = zamgerUpdateTasks.shift();
	if (task[0] == "X") {
		zamger_update_groups(task, false);
		task = "Y" + task.substr(1);
		zamgerUpdateTasks.push(task);
	}
	else if (task[0] == "Y") {
		task = "X" + task.substr(1);
		zamger_update_allstudents(task, false);
	}
	else if (task == "courses") {
		zamger_update_teacher_courses(false);
	}
	else
		zamger_update_group(task, false);
}


function zamger_update_groups(course, force) {
	var xmlhttp = new XMLHttpRequest();
	var url = "zamger/update.php?action=groups&force="+force+"&course_id="+course;
	showMsg('<i class="fa fa-refresh fa-spin"></i> Updating groups for '+course);
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			hideMsg();
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				var response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				return;
			}
			runUpdates();
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


function zamger_update_allstudents(course, force) {
	var xmlhttp = new XMLHttpRequest();
	var url = "zamger/update.php?action=all_students&force="+force+"&course_id="+course;
	showMsg('<i class="fa fa-refresh fa-spin"></i> Updating all students list for '+course);
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			hideMsg();
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				var response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				return;
			}
			runUpdates();
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


function zamger_update_group(group, force) {
	var xmlhttp = new XMLHttpRequest();
	var url = "zamger/update.php?action=group&force="+force+"&group_id="+group;
	showMsg('<i class="fa fa-refresh fa-spin"></i> Updating group members for '+group);
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			hideMsg();
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				var response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				return;
			}
			runUpdates();
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


function zamger_update_teacher_courses(force) {
	var xmlhttp = new XMLHttpRequest();
	var url = "zamger/update.php?action=teacher_courses&force="+force;
	showMsg('<i class="fa fa-refresh fa-spin"></i> Updating courses list for teacher');
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			hideMsg();
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				var response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				return;
			}
			runUpdates();
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}
