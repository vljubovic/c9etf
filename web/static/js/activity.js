
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
			
			if (result.hasOwnProperty('lines')) last_line = parseInt(result['lines']) + 1;
			
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
		
		if (activity_filter) {
			var part = global_activity[username]['path'].substr(1, activity_filter.length);
			if (part != activity_filter) return;
		}
		
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

let tileUsers = [], colorLevels = {}, lastFile = {}, oldCode = [];
// Initialize oldCode array with strings, so we don't get errors :(
for (i=0; i<tiled_max_tiles; i++) oldCode[i] = '';

function getFullName(username, tile) {
	var xmlhttp = new XMLHttpRequest();
	var url = "services/users.php?user=" + username;
	xmlhttp.onreadystatechange = function () {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			if (result.hasOwnProperty('success') && (result.success === true || result.success === "true"))
				document.getElementById('tile' + tile + "_fullname").innerText = result.data.realname;
		}
	};
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

function renderTiledResults() {
	Object.keys(global_activity).sort().forEach(function(username) {
		// timenow is received from server in getActive()
		var dist = timenow - global_activity[username]['timestamp'];

		// Dissapear users who weren't active for 5 minutes
		if (dist > 300) {
			let tile = -1;
			for (i=0; i < tileUsers.length; i++) {
				if (tileUsers[i] == username) {
					tile = i;
				} else if (tile > -1) {
					// A bit ugly - copy all properties to previous tile
					document.getElementById('tile' + (i-1) + '_username').innerHTML = document.getElementById('tile' + i + '_username').innerHTML;
					document.getElementById('tile' + (i-1) + '_fullname').innerHTML = document.getElementById('tile' + i + '_fullname').innerHTML;
					document.getElementById('tile' + (i-1) + '_path').innerHTML = document.getElementById('tile' + i + '_path').innerHTML;
					document.getElementById('tile' + (i-1) + '_time').innerHTML = document.getElementById('tile' + i + '_time').innerHTML;
					document.getElementById('tile' + (i-1) + '_code').innerHTML = document.getElementById('tile' + i + '_code').innerHTML;
					document.getElementById('tile' + (i-1)).style.backgroundColor = document.getElementById('tile' + i).style.backgroundColor;
					oldCode[i-1] = oldCode[i];
				}
			}
			if (tile > -1) {
				// Dissapear last tile
				document.getElementById('tile' + (tileUsers.length-1)).style.backgroundColor = '';
				document.getElementById('tile' + (tileUsers.length-1)).style.display = 'none';
				tileUsers.splice(tile, 1);
			}
		}

		// Don't bother updating events from more than 1 minute
		if (dist>60)
			return;

		if (activity_filter) {
			var part = global_activity[username]['path'].substr(1, activity_filter.length);
			if (part != activity_filter) return;
		}

		pfile = global_activity[username]['path'] + global_activity[username]['file'];
		pfile = pfile.substr(1);

		// Magic values, give the best dynamics for rendering
		colorLevels[username] = (30 - dist) / 0.30;
		lastFile[username] = pfile;

		link = "admin.php?user=" + username;
		file_link = link + "&path=" + encodeURIComponent(pfile);

		// Find user tile
		let tile=-1;
		for (let i=0; i<tileUsers.length; i++) {
			if (tileUsers[i] == username)
				tile = i;
		}

		// Not found, attempt to add a new one
		if (tile == -1) {
			if (tileUsers.length >= tiled_max_tiles) return;
			tile = tileUsers.length;
			tileUsers.push(username);
			document.getElementById('tile' + tile).style.display = "inline-block";
			document.getElementById('tile' + tile + '_username').innerHTML = username;
			getFullName(username, tile);
			document.getElementById('tile' + tile + "_code").innerText = '';
			// Force to update right now
			dist = 0;
		}
		document.getElementById('tile' + tile + "_path").innerHTML = pfile;
		document.getElementById('tile' + tile + "_path").href = file_link;
		const dateParts = global_activity[username]['datum'].split(' ');
		document.getElementById('tile' + tile + "_time").innerHTML = dateParts[1];

		// Ignore files that begin with .
		const pathParts = pfile.split("/");
		const theChar = pathParts[pathParts.length-1].charAt(0);
		if (dist < 5 && theChar != '.') {
			// Fetch file contents to show in "code" box
			var xmlhttp = new XMLHttpRequest();
			var url = "services/file.php?user=" + username + "&path=" + encodeURIComponent(pfile);
			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
					document.getElementById('tile' + tile + "_code").innerText = xmlhttp.responseText;

					// Calculate scroll position
					if (oldCode[tile] && xmlhttp.responseText != oldCode[tile]) {
						let scrollLine = -7;
						for (i=0; i<oldCode[tile].length; i++) {
							if (i >= xmlhttp.responseText.length || oldCode[tile].charAt(i) != xmlhttp.responseText.charAt(i))
								break;
							if (oldCode[tile].charAt(i) == '\n') scrollLine++;
						}
						if (i < oldCode[tile].length - 1) {
							if (scrollLine < 0) scrollLine = 0;
							document.getElementById('tile' + tile + "_code").scrollTop = (scrollLine * 10);
						}
					}
					oldCode[tile] = xmlhttp.responseText;
				}
			};
			xmlhttp.open("GET", url, true);
			xmlhttp.send();
		}
	});
}

function updateTileColors() {
	for (let i=0; i<tileUsers.length; i++) {
		let user = tileUsers[i];

		// alpha>0.5 is too strong on the eyes so we will scale it to [0,0.5]
		var alpha = colorLevels[user] / 200.0;
		// Alpha this low is basically invisible
		if (alpha < 0.05) alpha = 0;
		if (lastFile[user] == ".login") {
			color = "rgba(50, 50, 200, " + alpha + ")";
		} else if (lastFile[user] == ".logout") {
			color = "rgba(200, 0, 0, " + alpha + ")";
		} else {
			color = "rgba(0, 200, 0, " + alpha + ")";
		}
		document.getElementById('tile' + i).style.backgroundColor = color;
	}
}
