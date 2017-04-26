
// DASHBOARD.JS - JavaScript functions for the main dashboard screen
// Version: 2017/03/28 15:25

var attempts=0;
var started=0;
var server_stopped=0;
var idleTimeLimit = 60;

// Timout vars
var loadNewsTimeout, loadUsersTimeout, loadStatsTimeout, checkLoginTimeout, serverStatusTimeout;

// Cross-Browser functions for onclick events (for the webide icon - 
// since apparently it has problems in some browsers...)
function addListener(element, eventName, handler) {
	if (element.addEventListener) {
		element.addEventListener(eventName, handler, false);
	} else if (element.attachEvent) {
		element.attachEvent('on' + eventName, handler);
	} else {
		element['on' + eventName] = handler;
	}
}

function removeListener(element, eventName, handler) {
	if (element.addEventListener) {
		element.removeEventListener(eventName, handler, false);
	} else if (element.detachEvent) {
		element.detachEvent('on' + eventName, handler);
	} else {
		element['on' + eventName] = null;
	}
}

// This function will be called when user clicks on the webide icon
function startWebIde() {
	if (started == 1) {
		var url = "/" + currentUserLogin + "/";
		console.log("Opening "+url+" in new window");
		var win = window.open(url, '_blank');
		win.focus();
	} else {
		var xmlhttp = new XMLHttpRequest();
		var url = "/" + currentUserLogin + "/";
		console.log("Opening "+url+" in new AJAX");
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
		
		var status_icon = document.getElementById('webide_status_icon');
		var status_msg = document.getElementById('webide_status_msg');
		status_icon.style.backgroundImage="url('static/images/busy-dark-128x128.gif')";
		status_msg.innerHTML = "Server se startuje...";
		
		clearTimeout(checkLoginTimeout);
		checkLoginTimeout = setTimeout(checkLogin, 200);
	}
}

// Initial setup for timeouts
function setupTimeouts() {
	console.log("setupTimeouts");
	clearTimeouts();
	loadNewsTimeout = setTimeout(loadNews, 211);
	loadUsersTimeout = setTimeout(loadUsers, 500);
	loadStatsTimeout = setTimeout(loadStats, 207);
	checkLoginTimeout = setTimeout(checkLogin, 200);
	serverStatusTimeout = setTimeout(serverStatus, 1003);
}

function clearTimeouts() {
	console.log("clearTimeouts");
	clearTimeout(loadNewsTimeout);
	clearTimeout(loadUsersTimeout);
	clearTimeout(loadStatsTimeout);
	clearTimeout(checkLoginTimeout);
	clearTimeout(serverStatusTimeout);
}


// Stop all timeouts when window is not focused!
/*window.onfocus = function () { 
	console.log("Fire onfocus! "+Date.now());
	setupTimeouts();
}; 

window.onblur = function () { 
	console.log("Fire onblur! "+Date.now());
	clearTimeouts();
};*/ 

addListener(window, 'focus', setupTimeouts);
addListener(window, 'blur', clearTimeouts);


// Function that performs all logout tasks
function doLogout() {
	window.location.replace("index.php?logout");
}

function checkLogin() {
	console.log("checkLogin");
	var xmlhttp = new XMLHttpRequest();
	var url = "status-service.php";
	
	var status_icon = document.getElementById('webide_status_icon');
	var status_msg = document.getElementById('webide_status_msg');
	var webide_icon = document.getElementById('webide_icon');
	
	removeListener(status_icon, 'click', startWebIde);
	removeListener(status_msg, 'click', startWebIde);
	removeListener(webide_icon, 'click', startWebIde);
	
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			var status_icon = document.getElementById('webide_status_icon');
			var status_msg = document.getElementById('webide_status_msg');
			var webide_icon = document.getElementById('webide_icon');
			
			if (xmlhttp.responseText == "not-logged-in") {
				window.location.replace("index.php?logout");
			}
			else if (xmlhttp.responseText == "starting") {
				console.log("checkLogin starting");
				clearTimeout(checkLoginTimeout);
				checkLoginTimeout = setTimeout(checkLogin, 500);
				
				status_icon.style.backgroundImage="url('static/images/busy-dark-128x128.gif')";
				status_msg.innerHTML = "Server se startuje...";
				status_msg.style.textDecoration = "none";
			}
			else if (xmlhttp.responseText == "ok") {
				console.log("checkLogin ok");
				clearTimeout(checkLoginTimeout);
				checkLoginTimeout = setTimeout(checkLogin, 5000);

				status_icon.style.backgroundImage="url('static/images/check-icon.png')";
				status_msg.innerHTML = "Server pokrenut. Kliknite ovdje";
				status_msg.style.textDecoration = "underline";
				
				//addListener(status_icon, 'click', startWebIde);
				addListener(status_msg, 'click', startWebIde);
				addListener(webide_icon, 'click', startWebIde);
				started=1;
			}
			else if (xmlhttp.responseText == "idle") {
				console.log("checkLogin idle");
				clearTimeout(checkLoginTimeout);
				checkLoginTimeout = setTimeout(checkLogin, 5000);

				status_icon.style.backgroundImage="url('static/images/clock.png')";
				status_msg.innerHTML = "Server nije pokrenut. Kliknite ovdje da ga pokrenete";
				status_msg.style.textDecoration = "underline";
				
				//addListener(status_icon, 'click', startWebIde);
				addListener(status_msg, 'click', startWebIde);
				addListener(webide_icon, 'click', startWebIde);
				started=0;
			}
			else {
				console.log("checkLogin Neočekivan odgovor: "+xmlhttp.responseText);
				clearTimeout(checkLoginTimeout);
				checkLoginTimeout = setTimeout(checkLogin, 500);

				status_icon.style.backgroundImage="url('static/images/alert.png')";
				status_msg.innerHTML = "Provjera stanja nije uspjela";
				status_msg.style.textDecoration = "none";
			}
		}
		if (xmlhttp.readyState == 4 && xmlhttp.status >= 500) {
			console.log("checkLogin Status: "+xmlhttp.status);
			clearTimeout(checkLoginTimeout);
			checkLoginTimeout = setTimeout(checkLogin, 500);
			var status_icon = document.getElementById('webide_status_icon');
			status_icon.style.backgroundImage="url('static/images/alert.png')";
			var status_msg = document.getElementById('webide_status_msg');
			status_msg.innerHTML = "Provjera stanja nije uspjela";
			status_msg.style.textDecoration = "none";
		}
	}
	xmlhttp.onerror= function(e) {
		console.log("Network error");
		checkLoginTimeout = setTimeout(checkLogin, 5000);
	};
	xmlhttp.open("GET", url, true);
	try {
		xmlhttp.send();
	} catch(e) {
		console.log("Izuzetak: " + e.name);
		console.log(e);
		checkLoginTimeout = setTimeout(checkLogin, 5000);
	}
}

function loadNews() {
	var newWidth = window.innerWidth - 691;
	msgWidth = newWidth + 10;
	msgWidth = "" + msgWidth;
	msgWidth = msgWidth + "px";
	newWidth = "" + newWidth;
	newWidth = newWidth + "px";
	if (window.innerWidth > 800) {
		document.getElementById('news_content').style.width = newWidth;
		document.getElementById('system_msg_box').style.width = msgWidth;
	}
	
	var newHeight = window.innerHeight - 180;
	newHeight = newHeight + "px";
	document.getElementById('news_content').style.height = newHeight;
	
	var xmlhttp = new XMLHttpRequest();
	var url = "news.php";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			var news_content = document.getElementById('news_content');
			var news_click = document.getElementById('news_click');
			
			news_content.innerHTML = xmlhttp.responseText;
			news_click.style.display = "block";
			var images = news_content.getElementsByTagName("img");
			for(var i=0; i<images.length; i++) {
				images[i].style.display = "none";
			}
		}
	}
	xmlhttp.onerror= function(e) {
		console.log("Network error");
		loadNewsTimeout = setTimeout(loadNews, 5000);
	};
	xmlhttp.open("GET", url, true);
	try {
		xmlhttp.send();
	} catch(e) {
		console.log("Izuzetak: " + e.name);
		console.log(e);
		loadNewsTimeout = setTimeout(loadNews, 5000);
	}
}


function showMoreNews() {
	var novosti_box = document.getElementById('news_box');
	var news_content = document.getElementById('news_content');
	var news_click = document.getElementById('news_click');
	
	news_click.style.display = "none";
	news_content.style.height = "";
	var images = news_content.getElementsByTagName("img");
	for(var i=0; i<images.length; i++) {
		images[i].style.display = "inline";
	}
	return false;
}

function loadUsers() {
	var ajaxusers = new XMLHttpRequest();
	var url = "status-service.php?users";
	console.log("loadUsers");
	ajaxusers.onreadystatechange = function() {
		if (ajaxusers.readyState == 4 && ajaxusers.status == 200) {
			var userList = ajaxusers.responseText.split(/\r\n|\r|\n/);
			var userNo = userList.length - 1;
			if (userList[0].indexOf("ERROR:") == 0) {
				console.log("loadUsers "+userList[0]);
			} else {
				console.log("loadUsers "+userNo);
				document.getElementById('users_content').innerHTML = "<ul>\n";
				for (var i=0; i<userNo; i++) {
					var userData = userList[i].split(/\t| /);
					var html;
					if (userData[0] == currentUserLogin)
						html = '<li class="current">' + userData[0] + '</li>\n';
					else if (userData[1] > idleTimeLimit)
						html = '<li class="inactive">' + userData[0] + '</li>\n';
					else
						html = '<li class="active">' + userData[0] + '</li>\n';
					document.getElementById('users_content').innerHTML += html;
				}
				document.getElementById('users_content').innerHTML += "</ul>\n";
				document.getElementById('users_number').innerHTML = userNo;
			}
			loadUsersTimeout = setTimeout(loadUsers, 10000);
		}
	}
	ajaxusers.onerror= function(e) {
		console.log("Network error");
		loadUsersTimeout = setTimeout(loadUsers, 5000);
	};
	ajaxusers.open("GET", url, true);
	try {
		ajaxusers.send();
	} catch(e) {
		console.log("Izuzetak: " + e.name);
		console.log(e);
		loadUsersTimeout = setTimeout(loadUsers, 5000);
	}
}

function loadStats() {
	var ajaxstats = new XMLHttpRequest();
	var url = "status-service.php?stats";
	console.log("loadStats");
	ajaxstats.onreadystatechange = function() {
		if (ajaxstats.readyState == 4 && ajaxstats.status == 200) {
			console.log("loadStats ok");
			
			var serveri = ajaxstats.responseText.split(/\r\n|\r|\n/);
			
			var kod = "", addcss = "";
			for (var i=0; i<serveri.length; i++) {
				var podaci = serveri[i].split(/ /);
				if (podaci.length < 2) continue;
				if (podaci[0][0] == '*') { // user is on this node
					addcss = "; color:#fbb";
					podaci[0] = podaci[0].substr(1);
				} else
					addcss = "";
				kod += '<p class="box_title" style="margin-bottom: 0px; padding-bottom: 0px' + addcss + '">' + podaci[0] + '</p>';
				kod += '<p style="margin-top: 0px; padding-top: 0px">';
				
				// Disk usage
				var diskFree = Math.round(podaci[5] / 1024 * 100) / 100;
				kod += '<img src="static/images/hard-drive-icon.png" width="16" height="16" title="Disk space"> ';
				if (diskError > 0 && podaci[5] < diskError)
					kod += "<font color='red'>" + diskFree + " GB</font><br>";
				else
					kod += diskFree + " GB<br>";
					
				// Load
				kod += '<img src="static/images/hardware-chip-icon.png" width="16" height="16" title="Load average"> ';
				if (loadError > 0 && podaci[1] > loadError)
					kod += "<font color='red'>" + podaci[1] + "</font><br>";
				else if (loadWarn > 0 && podaci[1] > loadWarn)
					kod += "<font color='yellow'>" + podaci[1] + "</font><br>";
				else
					kod += podaci[1] + "<br>";

				// Memory
				var memUsed = Math.round(podaci[2] / 1024 / 1024 * 100) / 100;
				kod += '<img src="static/images/hardware-chip-icon.png" width="16" height="16" title="Memory used"> ';
				if (memError > 0 && memUsed > memError)
					kod += "<font color='red'>" + memUsed + " GB</font><br>";
				else if (memWarn > 0 && memUsed > memWarn)
					kod += "<font color='yellow'>" + memUsed + " GB</font><br>";
				else
					kod += memUsed + " GB<br>";
				
				// Users
				kod += '<img src="static/images/user.png" width="16" height="16" title="Users"> ';
				if (userError > 0 && podaci[3] > userError)
					kod += "<font color='red'>" + podaci[3] + "</font><br>";
				else
					kod += podaci[3] + "<br>";
				
				// Active users
				kod += '<img src="static/images/user.png" width="16" height="16" title="Active users"> ';
				if (actUserError > 0 && podaci[4] > actUserError)
					kod += "<font color='red'>" + podaci[4] + "</font><br>";
				else if (actUserWarn > 0 && podaci[4] > actUserWarn)
					kod += "<font color='yellow'>" + podaci[4] + "</font><br>";
				else
					kod += podaci[4] + "<br>";
			}
			document.getElementById('server_stats').innerHTML = kod;
			loadStatsTimeout = setTimeout(loadStats, 30000);
		}
	}
	ajaxstats.onerror= function(e) {
		console.log("Network error");
		loadStatsTimeout = setTimeout(loadStats, 50000);
	};
	ajaxstats.open("GET", url, true);
	try {
		ajaxstats.send();
	} catch(e) {
		console.log("Izuzetak: " + e.name);
		console.log(e);
		loadStatsTimeout = setTimeout(loadStats, 50000);
	}
}

function serverStatus() {
	var xmlhttp = new XMLHttpRequest();
	var url = "status-service.php?serverStatus";
	console.log("serverStatus");
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			var system_msg_box = document.getElementById('system_msg_box');
			console.log("Server status: "+xmlhttp.responseText);
			if (xmlhttp.responseText != "ok") {
				var status_icon = document.getElementById('webide_status_icon');
				var status_msg = document.getElementById('webide_status_msg');
				var webide_icon = document.getElementById('webide_icon');
				
				status_icon.style.backgroundImage="url('static/images/alert.png')";
				status_msg.innerHTML = "Server trenutno nije u funkciji";
				status_msg.style.textDecoration = "none";
				
				clearTimeout(checkLoginTimeout);
				removeListener(status_icon, 'click', startWebIde);
				removeListener(status_msg, 'click', startWebIde);
				removeListener(webide_icon, 'click', startWebIde);

				system_msg_box.style.display = "block";
				
				if (xmlhttp.responseText.substring(0,13) == "not-logged-in") {
					document.getElementById('system_msg_title').innerHTML = "Odjavljeni ste sa servera";
					document.getElementById('system_msg_text').innerHTML = "Kliknite na logout dugme a zatim se opet prijavite.";
					//setTimeout(doLogout, 1000); 
				}
				else if (xmlhttp.responseText.substring(0,6) == "radovi") {
					document.getElementById('system_msg_title').innerHTML = "Radovi na serveru";
					document.getElementById('system_msg_text').innerHTML = "U toku su radovi zbog kojih je WebIDE privremeno nedostupan. Aktivnost će se nastaviti " + xmlhttp.responseText.substring(6) + ". Molimo da budete strpljivi.";
				}
				else if (xmlhttp.responseText.substring(0,7) == "zabrana") {
					document.getElementById('system_msg_title').innerHTML = "Zabranjen pristup";
					document.getElementById('system_msg_text').innerHTML = "Pristup vašem korisniku je trenutno zabranjen " + xmlhttp.responseText.substring(7) + ". Kontaktirajte administratora ili dođite kasnije.";
				}
				else if (xmlhttp.responseText.substring(0,5) == "limit") {
					document.getElementById('system_msg_title').innerHTML = "Manjak resursa na serveru";
					document.getElementById('system_msg_text').innerHTML = "Trenutno nije moguće otvarati nove instance servera zbog toga što je prekoračeno ograničenje " + xmlhttp.responseText.substring(5) + ". Molimo da budete strpljivi i sačekate da se resursi oslobode.";
				}
				else if (xmlhttp.responseText.substring(0,5) == "nginx") {
					document.getElementById('system_msg_title').innerHTML = "nginx problem";
					document.getElementById('system_msg_text').innerHTML = "Došlo je do problema u konfiguraciji web servera. Hitno kontaktirajte sistem administratora!";
				}
				else {
					document.getElementById('system_msg_title').innerHTML = "Neočekivan odgovor";
					document.getElementById('system_msg_text').innerHTML = "Servis koji služi za provjeru statusa servera je vratio neočekivan odgovor '" + xmlhttp.responseText + "'. Molimo da kontaktirate administratora";
				}

				
				server_stopped = 1;
			} else if (server_stopped == 1) {
				server_stopped = 0;
				checkLoginTimeout = setTimeout(checkLogin, 200);
				system_msg_box.style.display = "none";
			}
				
			serverStatusTimeout = setTimeout(serverStatus, 10000);
		}
	}
	xmlhttp.onerror= function(e) {
		console.log("Network error");
		serverStatusTimeout = setTimeout(serverStatus, 10000);
	};
	xmlhttp.open("GET", url, true);
	try {
		xmlhttp.send();
	} catch(e) {
		console.log("Izuzetak: " + e.name);
		console.log(e);
		serverStatusTimeout = setTimeout(serverStatus, 10000);
	}
}