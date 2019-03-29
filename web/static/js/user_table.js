
// USER_TABLE.JS - JavaScript functions for dynamic loading of user per-assignment stats table
// Version: 2018/14/10 19:40


var show_others_regex = /^(T|Z|ZSR)\d+$/;
var global_stats = {};


function userTableLoadAll() {
	var user = usersToLoad.shift();
	userTableLoad(user.username, user.path);
}


function userTableClear() {
	var tbl = document.getElementById('user-stats-table');
	var end = tbl.rows[0].cells.length-1;
	if (userTableShowOthers()) end--;
	
	for (var i=0; i<tbl.rows.length; i++) {
		row = tbl.rows[i];
		for (var j=end; j>2; j--) {
			row.deleteCell(j);
		}
	}
}


function userTableLoad(user, path) {
	var xmlhttp = new XMLHttpRequest();
	var url = "/services/parse_stats.php?user=" + user + "&path=" + path + "&date_format=d.m.Y. H:i:s";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			var result = JSON.parse(xmlhttp.responseText);
			var tbl = document.getElementById('user-stats-table');
			
			if (result.data.assignments !== false) {
				var reconstructButton = document.getElementById('phpwebide_reconstruct_button');
				if (reconstructButton && result.data.assignments.length == 0)
					reconstructButton.style.display = "inline";
				
				// Add columns if neccessary
				userTableMaybeAddColumn(result.data.assignments);
			}
			
			// Add row for user
			userTableUpdateRow(result.data, user);
			
			if (typeof usersToLoad == "object" && usersToLoad.length > 0) 
				setTimeout(userTableLoadAll, 300);
		}
		if (xmlhttp.readyState == 4 && (xmlhttp.status == 500 || xmlhttp.status == 502)) {
			console.error("error: " + url + ": response code " + xmlhttp.status);
			
			if (typeof usersToLoad == "object" && usersToLoad.length > 0) 
				setTimeout(userTableLoadAll, 300);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


// Boolean function, tells if "show others" column exists in table
function userTableShowOthers() {
	var tbl = document.getElementById('user-stats-table');
	var row = tbl.rows[0];
	var end = row.cells.length-1;
	var idlast = row.cells[end].id;
	if (idlast == "show_others") return true;
	return false;
}


// A bit complicated function for adding column for assignment to table if it 
// doesn't already exist, taking into account "show others" column
function userTableMaybeAddColumn(assignments_assoc) {
	var tbl = document.getElementById('user-stats-table');
	var assignments = Object.keys(assignments_assoc).sort();
	var show_others = userTableShowOthers();
	
	for (var i=0; i<assignments.length; i++) {
		var asgn = assignments[i];
		
		// Assignments that don't match "show others regex" are counted under "Others"
		if (show_others && !asgn.match(show_others_regex)) continue;
		var cell_id = "assignment-" + assignments_assoc[asgn].path;
		
		// Iterate thru header cells and look for one named like this
		var previous_cell = false;
		var previous_id = false;
		var end = tbl.rows[0].cells.length;
		if (show_others) end--;
		for (var j = 3; j < end; j++) {
			previous_cell = tbl.rows[0].cells[j];
			// Header cells are ordered alphabetically, stop on first greater
			if (previous_cell.assignmentName >= asgn) { previous_id = j; break; }
		}
		
		// Cell not found in header, let's create it
		if (previous_id == false || previous_cell.assignmentName > asgn) {
			var th = document.createElement('th');
			th.id = cell_id;
			th.assignmentName = asgn;
			th.assignmentPath = assignments_assoc[asgn].path;
			var url = location.href;
			var x1 = url.indexOf("&path=");
			var x2 = url.indexOf("&", x1+1);
			url = url.substring(0,x1+6) + assignments_assoc[asgn].path + url.substring(x2);
			//th.innerHTML = asgn;
			th.innerHTML = "<a href=\"" + url + "\">" + asgn + "</a>";
			//th.onclick = function() { var murl=url; location.assign(murl); }
			
			// If previous_id is false, means all header fields (if any) are smaller
			if (previous_id == false) {
				if (!show_others) { // Special case, let's append
					tbl.rows[0].appendChild(th);
					for (var j=1; j<tbl.rows.length; j++) {
						var username = tbl.rows[j].cells[0].id.substring(17);
						var td = document.createElement('td');
						td.id = username+"-"+assignments_assoc[asgn].path;
						td.innerHTML = '/';
						tbl.rows[j].appendChild(td);
					}
					continue; // Skip to next iteration of i loop
				} else {
					previous_cell = tbl.rows[0].cells[end];
					previous_id = end;
				}
			} 
			tbl.rows[0].insertBefore(th, previous_cell);

			// Add column to all other rows
			for (var j=1; j<tbl.rows.length; j++) {
				var username = tbl.rows[j].cells[0].id.substring(17);
				var td = document.createElement('td');
				td.id = username+"-"+assignments_assoc[asgn].path;
				td.innerHTML = '/';
				previous_cell = tbl.rows[j].cells[previous_id];
				tbl.rows[j].insertBefore(td, previous_cell);
			}
		}
	}
}


// Function that adds a row with data for single user
function userTableUpdateRow(data, user) {
	var show_others = userTableShowOthers();
	var tbl = document.getElementById('user-stats-table');
	var backlink = "FIXME";
	
	var userRow = 0;
	for (var i = 1; i<tbl.rows.length; i++) {
		if (tbl.rows[i].cells[0].id == 'user-stats-table-' + user) 
			userRow=i;
	}
	if (userRow == 0) {
		console.log("userTableAddRow user "+user+" not found!");
		return;
	}
	var row = tbl.rows[userRow];
	
	// Second cell is time of last access
	var accesstime_cell = row.cells[1];
	accesstime_cell.innerHTML = data.last_access[user];
	if (data.last_access[user] == 0 || data.last_access == false) accesstime_cell.innerHTML = "Never";
	
	global_stats[user] = {};
	
	// Header contains sorted list of assignments, so we will use it as template
	var end = tbl.rows[0].cells.length;
	if (show_others) end--;
	for (var i=3; i<end; i++) {
		var cell = tbl.rows[0].cells[i];
		var asgn = cell.assignmentName;
		var asgn_path = cell.assignmentPath;
		
		// No stats for path
		if (data.stats[user].length == 0 || !data.stats[user].hasOwnProperty(asgn)) {
			continue;
		}
		
		var asgn_stats = data.stats[user][asgn];
		
		var asgn_cell = row.cells[i];
		asgn_cell.innerHTML = "<i class=\"fa fa-clock-o\"></i> ";
		// Convert seconds to minutes rounded to 2 places
		var mins = asgn_stats['time'] / 60
		mins = Math.round(mins * 100) / 100;
		asgn_cell.innerHTML += mins;
		
		if (asgn_stats.hasOwnProperty('builds'))
			asgn_cell.innerHTML += "<i class=\"fa fa-wrench\"></i> " + asgn_stats['builds'];
		if (asgn_stats.hasOwnProperty('builds_succeeded'))
			asgn_cell.innerHTML += "<i class=\"fa fa-gear\"></i> " + asgn_stats['builds_succeeded'];
		if (asgn_stats.hasOwnProperty('test_results'))
			asgn_cell.innerHTML += "<i class=\"fa fa-check\"></i> " + asgn_stats['test_results'];
		
		asgn_cell.innerHTML = "<a href=\"?user=" + user + "&amp;path=" + asgn_path + "&amp;backlink=" + backlink + "\">" + asgn_cell.innerHTML + "</a>";
		
		global_stats[user][asgn_path] = { time: mins, builds: asgn_stats['builds'], builds_succeeded: asgn_stats['builds_succeeded'], test_results: asgn_stats['test_results'] };
	}
	
	// Calculate stats for "others" column
	if (show_others) {
		var others_time = 0;
		var others_builds = 0;
		var others_builds_succeeded = 0;
		
		for (var asgn in data.stats[user]) {
			if (!data.stats[user].hasOwnProperty(asgn)) continue;
			if (asgn.match(show_others_regex)) continue;
		
			var asgn_stats = data.stats[user][asgn];
			
			// Add to "others" stats
			others_time += asgn_stats['time'];
			if (asgn_stats.hasOwnProperty('builds')) others_builds += asgn_stats['builds'];
			if (asgn_stats.hasOwnProperty('builds_succeeded')) others_builds_succeeded += asgn_stats['builds_succeeded'];
		}
		
		// Others cell
		var others_cell = row.cells[ row.cells.length - 1 ];
		others_cell.innerHTML = "<i class=\"fa fa-clock-o\"></i> ";
		// Convert seconds to minutes rounded to 2 places
		var mins = others_time / 60
		mins = Math.round(mins * 100) / 100;
		others_cell.innerHTML += mins;
		
		if (others_builds > 0) 
			others_cell.innerHTML += "<i class=\"fa fa-wrench\"></i> " + others_builds;
		if (others_builds_succeeded > 0)
			others_cell.innerHTML += "<i class=\"fa fa-gear\"></i> " + others_builds_succeeded;
	}
}
