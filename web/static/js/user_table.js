
// USER_TABLE.JS - JavaScript functions for dynamic loading of user table
// Version: 2016/10/20 12:45

//document.addEventListener('load', loadUsers);
//setTimeout(loadUsers, 100);

function loadUsers() {
	if (usersToLoad.length == 0) return;
	var xmlhttp = new XMLHttpRequest();
	var url = "/services/parse_stats.php?login=" + usersToLoad[0] + "&path_log=" + pathLog;
	console.log("parse_stats: "+usersToLoad[0]);
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			result = JSON.parse(xmlhttp.responseText);
			usersToLoad.splice(0,1);

			window.console.log("etf.zadaci: Read file "+fullpathz);
			fs.writeFile( fullpathz, xmlhttp3.responseText, function (err, data) {
				if (err) { 
					window.console.log("etf.zadaci: Error writing file "+fullpathz);
					return console.error(err); // De facto do nothing
				}
				window.console.log("etf.zadaci: Created file "+fullpathz);
				if (first_char != ".")
					tabs.openFile( fullpathz, true, function(err, tab) {
						if (err) return console.error(err);
						console.log("etf.zadaci: PANELS PANELS TREE:");
						console.log(panels.panels);
						console.log(panels.panels.tree);
						panels.panels.tree.expandAndSelect( fullpathz );
					});
			});
		}
		if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
			console.error("error: " + url + " 500");
			setTimeout(loadUsers, 1000);
		}
	}
	xmlhttp.open("GET", url2, true);
	xmlhttp.send();
	
}
