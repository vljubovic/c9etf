
// WEBIDE-TABS.JS - Functionality of "tabs" with admin tools related to webide (requires phpwebide.js)
// Version: 12.6.2017 10:09


var pwi_tabs_list = [ "activity", "svn", "git", "deleted" ];
var pwi_tabs_svn_clicked = false;

function pwi_tab_show(tabname, clicktab, username, path) {
	disptab = document.getElementById(tabname);
	if (disptab.style.display == "block") {
		clicktab.parentNode.className = "";
		disptab.style.display = "none";
		disptab.parentNode.style.height = "0px";
		return false;
	}
	
	if (disptab.textContent == "Please wait...") {
		var xmlhttp = new XMLHttpRequest();
		var url = "admin_show_module.php?module="+tabname+"&user="+username+"&path="+path;
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				disptab.innerHTML = xmlhttp.responseText;
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	
	clicktab.parentNode.className = "current";
	disptab.style.display = "block";
	disptab.parentNode.style.height = "600px";
	
	sibling = clicktab.parentNode.parentNode.firstChild;
	for (; sibling; sibling = sibling.nextSibling) {
		if (sibling.nodeType == 1 && sibling != clicktab.parentNode && sibling.className == "current")
			sibling.className = "";
	}
	
	sibling = disptab.parentNode.firstChild;
	for (; sibling; sibling = sibling.nextSibling) {
		if (sibling.nodeType == 1 && sibling != disptab && sibling.style.display == "block")
			sibling.style.display = "none";
	}
	return false;
}

function pwi_tabs_reset() {
	for (var i=0; i<pwi_tabs_list.length; i++) {
		var disptab = document.getElementById(pwi_tabs_list[i]);
		var clicktab = document.getElementById(pwi_tabs_list[i] + "-click");
		if (disptab.style.display == "block") {
			clicktab.parentNode.className = "";
			disptab.style.display = "none";
			disptab.parentNode.style.height = "0px";
		}
		disptab.innerHTML = '<center><img src="static/images/busy-light-84x84.gif" width="84" height="84" align="center">Please wait...</center>';
	}
}

function pwi_tabs_svn_click(el, path, rev) {
	pwi_editor_load(path, 'svn', rev); 
	if (pwi_tabs_svn_clicked) pwi_tabs_svn_clicked.className = "svn-log";
	el.className = "svn-log-clicked";
	pwi_tabs_svn_clicked = el;
	pwi_toolbar_restore_button('svn', rev);
	return false;
}


function pwi_tabs_git_click(el, path, rev) {
	pwi_editor_load(path, 'git', rev); 
	if (pwi_tabs_svn_clicked) pwi_tabs_svn_clicked.className = "svn-log";
	el.className = "svn-log-clicked";
	pwi_tabs_svn_clicked = el;
	pwi_toolbar_restore_button('git', rev);
	return false;
}