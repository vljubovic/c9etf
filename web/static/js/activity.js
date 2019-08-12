
// ACTIVITY.JS - Functions for monitoring user activity
// Version: 2018/01/08 19:59


function initActive(updateFunc, frequency) {
	var xmlhttp = new XMLHttpRequest();
	var url = "/services/activity.php?get_lines";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			
			// Set load average statistics
			var loadavg = document.getElementById('loadavg');
			if (loadavg) loadavg.innerHTML = result['loadavg'];
			timenow = result['its_now'];
			
			last_line = result['lines'] - 1000;

			setInterval(function(){ getActive(updateFunc); }, frequency);
		}
		if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
			console.error(url + " 500");
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

function getActive(updateFunc) {
	var xmlhttp = new XMLHttpRequest();
	var url = "/services/activity.php?start_from=" + last_line;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			
			// Update global array
			for(key in result) {
				if (result.hasOwnProperty(key) && key != "its_now" && key != "loadavg" && key != "lines") {
					updateFunc(result[key]);
					last_line++;
				}
			}
			
			// Set load average statistics
			var loadavg = document.getElementById('loadavg');
			if (loadavg) loadavg.innerHTML = result['loadavg'];
			timenow = result['its_now'];
			//setTimeout(function(){ getActive(updateFunc); }, frequency);
		}
		if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
			console.error(url + " 500");
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

function renderResults() {
	// Regenerate HTML list of active users
	var obj=document.getElementById('activeUsers');
	obj.innerHTML="";
	Object.keys(global_activity).sort().forEach(function(username) {
		var dist=timenow - global_activity[username]['timestamp'];
		if (dist>255) return;
		
		var color = dist.toString(16);
		if (dist<16) color="0"+color;
		if (global_activity[username]['file'] == ".logout")
			color = "#FF"+color+color;
		else if (global_activity[username]['file'] == ".login")
			color = "#"+color+"FF" + color;
		else
			color = "#"+color+color+color;
			
		pfile = global_activity[username]['path'] + global_activity[username]['file'];
		pfile = pfile.substr(1);
		
		link = "admin.php?user=" + username;
		file_link = link + "&amp;path=" + encodeURIComponent(pfile);
		
		obj.innerHTML += "<div style=\"color:"+color+"\"><a href=\"" + link + "\">" + username + "</a> - " +
			global_activity[username]['datum'] + " - <a href=\"" + file_link + "\">" + pfile + "</a></div>";
	});
}
	
