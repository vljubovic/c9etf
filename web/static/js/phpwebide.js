
// PHPWEBIDE.JS - JavaScript portion of lightweight webide
// Version: 2018/05/19 10:56


// ------------ GLOBALS ----------------

// ==== SET THESE IN CODE ====
// Path of current file
//var pwi_current_path;

// Current username
//var pwi_current_user;


// Tasks for tree loading
var pwi_tree_load_paths = [];

// Show hidden files in tree?
var pwi_tree_show_hidden = false;

// Show deleted files in tree?
var pwi_tree_show_deleted = false;

// Editor Autosave
var pwi_save_timeout, pwi_save_has_timeout=false;

// Definition of current homework
var pwi_homework_data = false;

// Definition of current testing status
var pwi_tests_total = false;
var pwi_tests_passed = false;

// Params for restoring from svn/git
var pwi_current_restore_type = false;
var pwi_current_restore_rev = false;

var pwi_was_doubleclick = 0;

var pwi_tasks = [];



// ------------ TREE FUNCTIONS ----------------

// Function that rebuilds the whole tree
function pwi_tree_load_all(path) {
	var tree = document.getElementById("phpwebide_tree");
	// Remove existing child nodes, if any
	while (tree.firstChild) {
		tree.removeChild(tree.firstChild);
	}
	
	// Load root
	pwi_tree_load_paths.push('/');
	
	// Load folders in path (if any)
	x = path.indexOf('/');
	while (x>=0) {
		pwi_tree_load_paths.push(path.substr(0, x));
		x = path.indexOf('/', x+1);
	}
	
	pwi_tree_load(function() { pwi_tree_select(path); } );
}

// Find a node in subtree
function pwi_find_node(path, tree) {
	var children = tree.childNodes;
	var found = false;
	children.forEach(function(item){
		console.log("Searching "+path+" trying '"+item.id+"' type "+item.nodeName);
		if (item.id == path) found = item;
		else if (item.nodeName == "DIV") {
			var content_str = "_content";
			var substr_len = item.id.length - content_str.length;
			if (substr_len > 0) {
				var subpath_id = path.substr(0, substr_len) + content_str;
				//console.log("Trying "+subpath_id);
				if (subpath_id == item.id) {
					var tmpfound = pwi_find_node(path, item);
					if (tmpfound) found=tmpfound;
				}
			}
		}
		else if (item.nodeName == "H3") {
			item.classList.remove("filelist-selected");
		}
	});
	return found;
}

// Set 'path' as currently selected node
function pwi_tree_select(path) {
	if (pwi_current_path) {
		var selected = document.getElementById(pwi_current_path);
		if (selected) selected.classList.remove("filelist-selected");
	}
	
	var tree = document.getElementById("phpwebide_tree");
	var node = pwi_find_node(path, tree);
	console.log("pwi_tree_select "+path+" found node");
	console.log(node);
	if (node) node.classList.add("filelist-selected");
	pwi_current_path = path;
}


// Recursively iterate through pats in 'pwi_tree_load_paths' and load them into tree
function pwi_tree_load(final_callback) {
	if (pwi_tree_load_paths.length == 0) {
		final_callback();
		pwi_clear_task("pwi_tree_load");
		return;
	}
	
	pwi_add_task("pwi_tree_load");
	var path = pwi_tree_load_paths.shift();
	console.log("loading "+path);

	// Find parent node of path
	var parent = document.getElementById("phpwebide_tree");
	if (path != '/') {
		parent = pwi_find_node(path + "_content", parent);
		
		// Not found - let's reload everything
		if (!parent) {
			if (pwi_tree_load_paths.length > 0)
				path = pwi_tree_load_paths[ pwi_tree_load_paths.length-1 ] ;
			pwi_tree_load_paths = [];
			pwi_tree_load_all( path);
			return;
		}
	}
	
	// AJAX call to list files
	var xmlhttp = new XMLHttpRequest();
	var url = "services/file.php?user="+pwi_current_user+"&path="+encodeURIComponent(path)+"&type=tree";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				var response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				pwi_clear_task("pwi_tree_load");
				return;
			}
			
			var items = xmlhttp.responseText.split("\n");
			for (i=0; i<items.length; i++) {
				var item=items[i];
				if (item.length <= 1 || item == "./" || item == "../") continue;
				if (!pwi_tree_show_hidden && item[0] == ".") continue;
				
				var lastchr = item[item.length-1];
				if (lastchr == "*" || lastchr == "=" || lastchr == ">" || lastchr == "@" || lastchr == "|") continue;
				
				// Prepare item name
				if (lastchr == "/") item = item.substr(0, item.length-1);
				
				// Create element
				var element = document.createElement('h2');
				element.className = "filelist ";
				
				if (path == "/") 
					element.id = item;
				else
					element.id = path + "/" + item;
				
				// Fix for # in path/filename
				element.id = element.id.replace("#", "%23");
					
				if (lastchr == "/") {
					element.className += "filelist-folder";
					element.onclick = function() { var tid=this.id; setTimeout( function() { pwi_tree_onclick(tid);}, 100); }
					// Reload stats on doubleclick
					if (typeof userTableLoad == "function") 
						element.ondblclick = function() { pwi_tree_ondblclick(this.id); }
				} else {
					element.className += "filelist-file";
					element.onclick = function() { pwi_editor_load(this.id, "file"); pwi_tree_select(this.id); }
				}
				
				element.innerHTML = item;
				parent.appendChild(element);
				
				if (lastchr == "/") {
					var content = document.createElement('div');
					content.id = element.id + "_content";
					content.className = "filelist-folder-content";
					parent.appendChild(content);
				}
			}
			pwi_tree_load(final_callback);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


// Onclick event for folders in tree, show/hide folder contents
function pwi_tree_onclick(path) {
	if (pwi_was_doubleclick>0) {
		pwi_was_doubleclick--;
		return;
	}
	console.log("pwi_tree_onclick "+path);
	
	// Find clicked node
	var node = document.getElementById("phpwebide_tree");
	if (path != '/') {
		node = pwi_find_node(path + "_content", node);
		if (!node) return;
	}
	
	var previously_selected = pwi_current_path;
	
	// If there are child nodes, remove them
	var removed = false;
	while (node.firstChild) {
		node.removeChild(node.firstChild);
		removed = true;
	}
	
	// Else, load the subtree
	if (!removed) {
		pwi_tree_load_paths.push(path);
		pwi_tree_load( function(){ pwi_tree_select(pwi_current_path); } );
	}
}


// Ondblclick event for folders - load stats for folder
function pwi_tree_ondblclick(path) {
	console.log("pwi_tree_ondblclick "+path);
	pwi_was_doubleclick = 2;
	
	userTableClear();
	userTableLoad(pwi_current_user, path)
}

// Show/hide hidden files
function pwi_tree_showhide() {
	pwi_tree_show_hidden = !pwi_tree_show_hidden;
	pwi_tree_load_all(pwi_current_path);
}

// Show/hide deleted files
function pwi_tree_show_deleted() {
	// TODO
}


// ------------ TOOLBAR FUNCTIONS ----------------

// Update everything on toolbar
function pwi_toolbar_update(rev) {
	pwi_toolbar_homework_button();
	pwi_toolbar_test_button();
	document.getElementById('phpwebide_restore_button').style.display = "none";
	pwi_toolbar_modified_time();
	pwi_populate_deploy_menu();
}


// Update homework button
function pwi_toolbar_homework_button() {
	// Find parent folder of file
	var x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_homework_data = false;
		document.getElementById('phpwebide_homework_button').style.display = "none";
		return;
	}
	pwi_add_task("pwi_homework_button");
	
	// Find .zadaca file in folder
	var zadaca_file = pwi_current_path.substr(0,x+1) + ".zadaca";
	var xmlhttp = new XMLHttpRequest();
	var url = "services/file.php?user="+pwi_current_user+"&path="+zadaca_file;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText == "ERROR: File doesn't exist\n") {
				pwi_homework_data = false;
				document.getElementById('phpwebide_homework_button').style.display = "none";
			} else {
				try {
					pwi_homework_data = JSON.parse(xmlhttp.responseText);
					document.getElementById('phpwebide_homework_button').style.display = "inline";
				} catch(e) {
					document.getElementById('phpwebide_homework_button').style.display = "none";
					pwi_homework_data = false;
				}
			}
			pwi_clear_task("pwi_homework_button");
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

// Update test button
function pwi_toolbar_test_button() {
	// Find parent folder of file
	var x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_button').style.display = "none";
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	pwi_add_task("pwi_test_button");
	
	// Find .autotest file in folder
	var autotest_file = pwi_current_path.substr(0,x+1) + ".autotest";
	var xmlhttp = new XMLHttpRequest();
	var url = "services/file.php?user="+pwi_current_user+"&path="+autotest_file;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText == "ERROR: File doesn't exist\n") {
				pwi_tests_total = pwi_tests_passed = false;
				document.getElementById('phpwebide_test_button').style.display = "none";
				document.getElementById('phpwebide_test_results').style.display = "none";
			} else {
				var tests;
				try {
					tests = JSON.parse(xmlhttp.responseText);
					pwi_tests_total = tests.test_specifications.length;
					pwi_tests_passed = 0;
					document.getElementById('phpwebide_test_button').style.display = "inline";
					document.getElementById('phpwebide_test_results').style.display = "none";
				
					pwi_toolbar_is_tested(tests);
				} catch(e) {
					pwi_tests_total = pwi_tests_passed = false;
					document.getElementById('phpwebide_test_button').style.display = "none";
					document.getElementById('phpwebide_test_results').style.display = "none";
				}
			}
			pwi_clear_task("pwi_test_button");
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

// Update test results
function pwi_toolbar_is_tested(tests) {
	// Find parent folder of file
	pwi_add_task("pwi_is_tested");
	var x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	
	// Find .at_result file in folder
	var at_result_file = pwi_current_path.substr(0,x+1) + ".at_result";
	var xmlhttp = new XMLHttpRequest();
	var url = "services/file.php?user="+pwi_current_user+"&path="+at_result_file;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			pwi_tests_passed = 0;
			if (xmlhttp.responseText == "ERROR: File doesn't exist\n") {
				document.getElementById('phpwebide_test_results').style.display = "none";
			} else {
				document.getElementById('phpwebide_test_results').style.display = "inline";
				
				var results = JSON.parse(xmlhttp.responseText);
				
				var results_widget = document.getElementById('phpwebide_test_results_widget');
				var results_button = document.getElementById('phpwebide_test_results_data');
				
				// Clear results widget
				while (results_widget.firstChild)
					results_widget.removeChild(results_widget.firstChild);
				
				// Compile failed
				if (results.status == 3 || results.status == 6) {
					document.getElementById('phpwebide_test_results_data').innerHTML = " (error)";
					pwi_clear_task("pwi_is_tested");
					return;
				}
				
				// Position results widget below button
				var rect = results_button.getBoundingClientRect();
				results_widget.style.left = rect.left + "px";
				var top = rect.bottom;
				results_widget.style.top = top + "px";
				results_widget.style.width = "200px"; // Set minimum width

				
				// Iterate through test results
				for (var i=0; i<tests.test_specifications.length; i++) {
					var found_result = false;
					if (results.test_results.hasOwnProperty(tests.test_specifications[i].id))
						found_result = results.test_results[tests.test_specifications[i].id];
					
					// Test statistics
					if (found_result && found_result.status == 1)
						pwi_tests_passed++;
					
					// Populate results widget
					var element = document.createElement('h2');
					element.className = "filelist ";
					
					// Temporary scope hack
					(function(i){
						element.onclick = function() { pwi_render_test_result(tests, results, tests.test_specifications[i].id); }
					})(i);
					
					if (!found_result) {
						element.className += "testresult_fail";
						element.innerHTML = "Not tested";
					} else {
						if (found_result.status == 1)
							element.className += "testresult_success";
						else
							element.className += "testresult_fail";
						if (typeof buildservice_test_status == "object")
							element.innerHTML = buildservice_test_status[found_result.status];
						else
							element.innerHTML = found_result.status;
					}
					results_widget.appendChild(element);
				}
				results_button.innerHTML = " ("+pwi_tests_passed+"/"+pwi_tests_total+")";
				pwi_toolbar_is_test_outdated();
				
			}
			pwi_clear_task("pwi_is_tested");
			
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


// Function which tests whether test results are older than test specification
function pwi_toolbar_is_test_outdated() {
	// Find parent folder of file
	pwi_add_task("pwi_is_test_outdated");
	var x = pwi_current_path.lastIndexOf('/');
	if (x==-1) {
		return;
	}
	
	var autotest_file = pwi_current_path.substr(0,x+1) + ".autotest";
	var at_result_file = pwi_current_path.substr(0,x+1) + ".at_result";
	
	var xmlhttp = new XMLHttpRequest();
	var url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(autotest_file) + "&type=mtime";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			var autotest_mtime = parseInt(xmlhttp.responseText);
			
			var xmlhttp2 = new XMLHttpRequest();
			var url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(at_result_file) + "&type=mtime";
			xmlhttp.onreadystatechange = function() {
				if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
					var at_result_mtime = parseInt(xmlhttp.responseText);
					if (at_result_mtime < autotest_mtime) {
						var results_button = document.getElementById('phpwebide_test_results_data');
						results_button.innerHTML = "<i class=\"fa fa-clock-o\"></i> " + results_button.innerHTML;
					}
					pwi_clear_task("pwi_is_test_outdated");
				}
			}
			xmlhttp.open("GET", url, true);
			xmlhttp.send();
			
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

// Update restore revision button
function pwi_toolbar_restore_button(type, rev) {
	pwi_current_restore_type = type;
	pwi_current_restore_rev = rev;
	document.getElementById('phpwebide_restore_button').style.display = "inline";
}


// Update modified time
function pwi_toolbar_modified_time() {
	var xmlhttp = new XMLHttpRequest();
	var url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(pwi_current_path) + "&type=mtime&format=d. m. Y. H:i:s";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			document.getElementById('phpwebide_modified_time').innerHTML = xmlhttp.responseText;
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


// Action for "Send Homework" button
function pwi_send_homework() {
	if (!pwi_homework_data) return;
			
	var xmlhttp = new XMLHttpRequest();
	var url = "zamger/slanje_zadace.php?username=" + pwi_current_user + "&filename=" + pwi_current_path + "&zadaca=" + pwi_homework_data.id + "&zadatak=" + pwi_homework_data.zadatak;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText == "Ok.")
				showMsg("Homework sent");
			else {
				showMsg("Error sending homework. See console log");
				console.log(xmlhttp.responseText);
			}
			
			setTimeout(hideMsg,5000);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


// Start testing on current project (needs buildservice.js)
function pwi_test_project() {
	// Find parent folder of file
	var x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	
	buildserviceStartTest(pwi_current_user, pwi_current_path.substr(0,x), 0);
}


// For now we will just open the .at_result_file
function pwi_show_test_results() {
	// Find parent folder of file
	var x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	
	// Find .at_result file in folder
	var at_result_file = pwi_current_path.substr(0,x+1) + ".at_result";
	
	pwi_editor_load(at_result_file, "file"); 
	pwi_tree_select(at_result_file);
}


// Redirect to URL for restoring older version of file
function pwi_restore_revision() {
	var url;
	if (pwi_current_restore_type == "svn")
		url = "admin.php?user=" + pwi_current_user + "&path=" + pwi_current_path + "&action=restore_revision&svn_rev=" + pwi_current_restore_rev;
	else if (pwi_current_restore_type == "git") {
		showMsg("Restoring from git not supported yet...");
		setTimeout(hideMsg,5000);
		return;
	}
	
	location.replace(url);
}


// Check if there's something to deploy
function pwi_populate_deploy_menu() {
	var menu = document.getElementById('phpwebide_deploy_menu');
	var button = document.getElementById('phpwebide_deploy_button');
	
	// If menu is visible for some reason, hide it
	menu.style.display = "none";
	button.style.display = "none";
	
	// If path didn't change, that's it
	if (menu.hasOwnProperty('pwi_path') && menu.pwi_path == pwi_current_path)
		return;
		
	// Contact web service to get a list of files in menu
	assignmentFromPath(pwi_current_path, function(t) {
		console.log("assignmentFromPath");
		listAssignmentFiles(t.course, t.year, t.external, t.assignment, t.task, function(files) {
			console.log("LENGTH "+files.length);
			// No files to deploy
			if (files.length == 0) return;
			
			// There are files
			button.style.display = "inline";
			
			// Clear existing menu
			while (menu.firstChild)
				menu.removeChild(menu.firstChild);
		
			for (var i=0; i<files.length; i++) {
				var element = document.createElement('h2');
				element.className = "filelist filelist-file";
				
				// Temporary scope hack
				(function(t,i){
					element.onclick = function() { 
						deployAssignmentFile(t.course, t.year, t.external, t.assignment, t.task, files[i], pwi_current_user); 
						setTimeout( function() { 
							pwi_editor_load(pwi_current_path,'file');
						}, 3000);
					}
				})(t,i);
		
				element.innerHTML = files[i];
				menu.appendChild(element);
			}
		});
	});
}


// Check if there's something to deploy
function pwi_render_test_result(tests, results, test) {
	var form = document.getElementById('pwi_test_results_form');
	form.tests.value = JSON.stringify(tests);
	form.test_results.value = JSON.stringify(results);
	form.test_id.value = test;
	
	var w = window.open('about:blank','Popup_Window','toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=0,width=700,height=700,left=312,top=234');
	form.target = 'Popup_Window';
	form.submit();
}




// ------------ EDITOR FUNCTIONS ----------------

function pwi_editor_initialize(editable) {
	var editor = ace.edit("editor");
	
	// Don't know why I need to resize twice ?
	var newbottom = window.innerHeight - 220 - document.getElementById('phpwebide_tree').clientHeight;
	document.getElementById('editor').style.bottom = "" + newbottom + "px";
	
	if (!editable) { 
		editor.setOptions({
			readOnly: true,
			highlightActiveLine: false,
			highlightGutterLine: false
		})
		editor.renderer.$cursorLayer.element.style.opacity=0
		editor.textInput.getElement().tabIndex=-1
		editor.commands.commmandKeyBinding={}
	} else {
		editor.getSession().on("change", pwi_schedule_save);
	}
}

function pwi_editor_load(path, type, rev) {
	pwi_add_task("pwi_editor_load "+path);
	
	var xmlhttp = new XMLHttpRequest();
	var url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(path) + "&type=" + type;
	if (rev) url += "&rev=" + rev;
	
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				var response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				pwi_clear_task("pwi_editor_load "+path);
				return;
			}
			var editor = ace.edit("editor");
			editor.setValue(xmlhttp.responseText);
			editor.getSession().setMode("ace/mode/c_cpp"); // FIXME hardcodirano
			
			// Scroll and position cursor on first line
			editor.resize(true);
			editor.scrollToLine(1, true, true, function () {});
			editor.gotoLine(1); 
			
			// Resize editor again
			var newbottom = window.innerHeight - 220 - document.getElementById('phpwebide_tree').clientHeight;
			document.getElementById('editor').style.bottom = "" + newbottom + "px";

			editor.focus();
			
			if (type == "file") {
				pwi_toolbar_update(path);
				pwi_set_address_bar(path);
				if (typeof pwi_tabs_reset == "function") pwi_tabs_reset();
			}
			
			pwi_clear_task("pwi_editor_load "+path);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}

// Try to update address bar with new path
function pwi_set_address_bar(path) {
	if (window.hasOwnProperty('history') && typeof window.history.pushState === "function") {
		var url = window.location.href, newurl;
		var x = url.lastIndexOf('&path=');
		if (x>0) {
			newurl = url.substr(0,x) + "&path="+path;
			var y = url.indexOf('&', x+1);
			if (y>0)
				newurl += url.substr(y);
			var stateObj = { foo: "bar" };
			window.history.pushState(stateObj, document.title, newurl);
		}
	}
}


// Old autosave functions for webide - FIXME

function pwi_schedule_save(e) {
	//alert("hello");
	if (pwi_save_has_timeout) clearTimeout(pwi_save_timeout);
	pwi_save_timeout = setTimeout('pwi_do_save()', 5000);
	pwi_save_has_timeout = true;
}

function pwi_do_save() {
	pwi_save_has_timeout = false;
	
	var mypostrequest=new ajaxRequest(); // FIXME replace with standard AJAX
	mypostrequest.onreadystatechange=function() {
		if (mypostrequest.readyState==4){
			if (mypostrequest.status==200 || window.location.href.indexOf("http")==-1){
				var xmldata=mypostrequest.responseText; //retrieve result as an text
				if (xmldata.indexOf("GRESKA") > -1)
					document.getElementById('status').innerHTML = xmldata; //"Greška pri snimanju A";
				else
					document.getElementById('status').innerHTML = "Program snimljen";
			}
			else {
				document.getElementById('status').innerHTML = "Greška pri snimanju B";
			}
		}
	}
	var editor = ace.edit("editor");
	var code = encodeURIComponent(editor.getSession().getValue())

	// FIXME broken
	sta = akcija = student = zadaca = zadatak = projekat = "";
	/*var sta = encodeURIComponent('<?=$_REQUEST[sta]?>');
	var akcija = encodeURIComponent("slanje");
	var student = encodeURIComponent(<?=$student?>);
	var zadaca = encodeURIComponent(<?=$zadaca?>);
	var zadatak = encodeURIComponent(<?=$zadatak?>);
	var projekat = encodeURIComponent(<?=$projekat?>);*/

	var parameters="sta="+sta+"&akcija="+akcija+"&student="+student+"&zadaca="+zadaca+"&zadatak="+zadatak+"&projekat="+projekat+"&code="+code;
	mypostrequest.open("POST", "index.php", true)
	mypostrequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded")
	mypostrequest.send(parameters)
}

function ajaxRequest() {
	var activexmodes=["Msxml2.XMLHTTP", "Microsoft.XMLHTTP"] //activeX versions to check for in IE
	if (window.ActiveXObject){ //Test for support for ActiveXObject in IE first (as XMLHttpRequest in IE7 is broken)
		for (var i=0; i<activexmodes.length; i++){
			try{
				return new ActiveXObject(activexmodes[i])
			}
			catch(e){
			//suppress error
			}
		}
	}
	else if (window.XMLHttpRequest) // if Mozilla, Safari etc
		return new XMLHttpRequest()
	else
		return false
}




// ------------ TASK MANAGER ----------------

function pwi_add_task(task) {
	console.log("pwi_add_task "+task);
	var found=false;
	for (var i=0; i<pwi_tasks.length; i++)
		if (pwi_tasks[i] == task)
			found=true;
	if (!found) pwi_tasks.push(task);
	if (pwi_tasks.length >= 1) document.getElementById('phpwebide_spinner').style.display = "inline";
}


function pwi_clear_task(task) {
	console.log("pwi_clear_task "+task);
	for (var i=0; i<pwi_tasks.length; i++) {
		if (pwi_tasks[i] == task)
			pwi_tasks.splice(i,1);
	}
	if (pwi_tasks.length == 0) document.getElementById('phpwebide_spinner').style.display = "none";
}
