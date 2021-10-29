
// PHPWEBIDE.JS - JavaScript portion of lightweight webide


// ------------ GLOBALS ----------------

// ==== SET THESE IN CODE ====
// Path of current file
//let pwi_current_path;

// Current username
//let pwi_current_user;


// Tasks for tree loading
let pwi_tree_load_paths = [];

// Show hidden files in tree?
let pwi_tree_show_hidden = false;

// Show deleted files in tree?
let pwi_tree_show_deleted = false;

// Editor Autosave
let pwi_save_timeout, pwi_save_has_timeout=false;

// Definition of current homework
let pwi_homework_data = false;

// Definition of current testing status
let pwi_tests_total = false;
let pwi_tests_passed = false;

// Params for restoring from svn/git
let pwi_current_restore_type = false;
let pwi_current_restore_rev = false;

let pwi_was_doubleclick = 0;

let pwi_tasks = [];

// Options for reconstruct feature
let pwi_reconstruct_limit = 1000; // Maximum number of records 
// (too large number above will use a lot of memory and have slow response from web service)

// Below is just global variables populated by ui ctls and web service
let pwi_reconstruct_data = [];
let pwi_reconstruct_play = false;
let pwi_reconstruct_speed = 2;
let pwi_reconstruct_path = "";
let pwi_reconstruct_has_more = false;
let pwi_reconstruct_realtime = false;

let pwi_image = false;


// ------------ TREE FUNCTIONS ----------------

// Function that rebuilds the whole tree
function pwi_tree_load_all(path) {
	let tree = document.getElementById("phpwebide_tree");
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
	let children = tree.childNodes;
	let found = false;
	children.forEach(function(item){
		//console.log("Searching "+path+" trying '"+item.id+"' type "+item.nodeName);
		if (item.id == path) found = item;
		else if (item.nodeName == "DIV") {
			let content_str = "_content";
			let substr_len = item.id.length - content_str.length;
			if (substr_len > 0) {
				let subpath_id = path.substr(0, substr_len) + content_str;
				//console.log("Trying "+subpath_id);
				if (subpath_id == item.id) {
					let tmpfound = pwi_find_node(path, item);
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
		let selected = document.getElementById(pwi_current_path);
		if (selected) selected.classList.remove("filelist-selected");
	}
	
	let tree = document.getElementById("phpwebide_tree");
	let node = pwi_find_node(path, tree);
	console.log("pwi_tree_select "+path+" found node");
	console.log(node);
	if (node) node.classList.add("filelist-selected");
	pwi_current_path = path;
}


// Recursively iterate through pats in 'pwi_tree_load_paths' and load them into tree
function pwi_tree_load(final_callback) {
	if (pwi_tree_load_paths.length === 0) {
		final_callback();
		pwi_clear_task("pwi_tree_load");
		return;
	}
	
	pwi_add_task("pwi_tree_load");
	let path = pwi_tree_load_paths.shift();
	console.log("loading "+path);

	// Find parent node of path
	let parent = document.getElementById("phpwebide_tree");
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
	let xmlhttp = new XMLHttpRequest();
	let url = "services/file.php?user="+pwi_current_user+"&path="+encodeURIComponent(path)+"&type=tree";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				let response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				pwi_clear_task("pwi_tree_load");
				return;
			}
			
			let items = xmlhttp.responseText.split("\n");
			for (i=0; i<items.length; i++) {
				let item=items[i];
				if (item.length <= 1 || item == "./" || item == "../") continue;
				if (!pwi_tree_show_hidden && item[0] == ".") continue;
				
				let lastchr = item[item.length-1];
				if (lastchr == "=" || lastchr == ">" || lastchr == "@" || lastchr == "|") continue;
				
				// Prepare item name
				if (lastchr == "*" || lastchr == "/") item = item.substr(0, item.length-1);
				
				// Create element
				let element = document.createElement('h2');
				element.className = "filelist ";
				
				if (path == "/") 
					element.id = item;
				else
					element.id = path + "/" + item;
				
				// Fix for # in path/filename
				element.id = element.id.replace("#", "%23");
					
				if (lastchr == "/") {
					element.className += "filelist-folder";
					element.onclick = function() { let tid=this.id; setTimeout( function() { pwi_tree_onclick(tid);}, 100); }
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
					let content = document.createElement('div');
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
	let node = document.getElementById("phpwebide_tree");
	if (path != '/') {
		node = pwi_find_node(path + "_content", node);
		if (!node) return;
	}
	
	//let previously_selected = pwi_current_path;
	
	// If there are child nodes, remove them
	let removed = false;
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
	userTableLoad(pwi_current_user, path);
	pwi_editor_load(path, "file");
	pwi_tree_select(path);
}

// Show/hide hidden files
function pwi_tree_showhide() {
	pwi_tree_show_hidden = !pwi_tree_show_hidden;
	pwi_tree_load_all(pwi_current_path);
}

// Show/hide deleted files
function pwi_tree_show_deleted_toggle() {
	// TODO not implemented yet
	pwi_tree_show_deleted = !pwi_tree_show_deleted;
}


// ------------ TOOLBAR FUNCTIONS ----------------

// Update everything on toolbar
function pwi_toolbar_update() {
	pwi_toolbar_homework_button();
	pwi_toolbar_test_button();
	document.getElementById('phpwebide_restore_button').style.display = "none";
	pwi_toolbar_modified_time();
	pwi_populate_deploy_menu();
	document.getElementById('phpwebide_reconstruct_options').style.display = "none";
}


// Update homework button
function pwi_toolbar_homework_button() {
	// Find parent folder of file
	let x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_homework_data = false;
		document.getElementById('phpwebide_homework_button').style.display = "none";
		return;
	}
	pwi_add_task("pwi_homework_button");
	
	// Find .zadaca file in folder
	let zadaca_file = pwi_current_path.substr(0,x+1) + ".zadaca";
	let xmlhttp = new XMLHttpRequest();
	let url = "services/file.php?user="+pwi_current_user+"&path="+zadaca_file;
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
	let x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_button').style.display = "none";
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	pwi_add_task("pwi_test_button");
	
	// Find .autotest file in folder
	let autotest_file = pwi_current_path.substr(0,x+1) + ".autotest";
	let xmlhttp = new XMLHttpRequest();
	let url = "services/file.php?user="+pwi_current_user+"&path="+autotest_file;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText == "ERROR: File doesn't exist\n") {
				let autotest_file = pwi_current_path.substr(0,x+1) + ".autotest2";
				let xmlhttp2 = new XMLHttpRequest();
				let url = "services/file.php?user="+pwi_current_user+"&path="+autotest_file;
				xmlhttp2.onreadystatechange = function() {
					if (xmlhttp2.readyState == 4 && xmlhttp2.status == 200) {
						if (xmlhttp2.responseText == "ERROR: File doesn't exist\n") {
							pwi_tests_total = pwi_tests_passed = false;
							document.getElementById('phpwebide_test_button').style.display = "none";
							document.getElementById('phpwebide_test_results').style.display = "none";
						} else {
							let tests;
							try {
								tests = JSON.parse(xmlhttp2.responseText);
								pwi_tests_total = tests.tests.length-1; // HACK za invisible test
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
				};
				xmlhttp2.open("GET", url, true);
				xmlhttp2.send();
			} else {
				let tests;
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
	let x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	
	// Find .at_result file in folder
	let at_result_file = pwi_current_path.substr(0,x+1) + ".at_result";
	let xmlhttp = new XMLHttpRequest();
	let url = "services/file.php?user="+pwi_current_user+"&path="+at_result_file;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			pwi_tests_passed = 0;
			if (xmlhttp.responseText == "ERROR: File doesn't exist\n") {
				document.getElementById('phpwebide_test_results').style.display = "none";
			} else {
				document.getElementById('phpwebide_test_results').style.display = "inline";
				
				let results_widget = document.getElementById('phpwebide_test_results_widget');
				let results_button = document.getElementById('phpwebide_test_results_data');
				
				// Clear results widget
				while (results_widget.firstChild)
					results_widget.removeChild(results_widget.firstChild);
				results_button.innerHTML = '';
				
				let results;
				try {
					results = JSON.parse(xmlhttp.responseText);
				} catch(e) {
					console.log("JSON is malformed");
					pwi_clear_task("pwi_is_tested");
					return;
				}
				
				// Compile failed
				if (results.status == 3 || results.status == 6) {
					document.getElementById('phpwebide_test_results_data').innerHTML = " (error)";
					pwi_clear_task("pwi_is_tested");
					return;
				}
				
				// Position results widget below button
				let rect = results_button.getBoundingClientRect();
				results_widget.style.left = rect.left + "px";
				let top = rect.bottom;
				results_widget.style.top = top + "px";
				results_widget.style.width = "200px"; // Set minimum width

				
				// Iterate through test results
				let test_specifications;
				if (tests.hasOwnProperty('tests'))
					test_specifications = tests.tests;
				if (tests.hasOwnProperty('test_specifications'))
					test_specifications = tests.test_specifications;
				for (let i=0; i<test_specifications.length; i++) {
					if (!test_specifications[i].hasOwnProperty('id')) continue;
					if (test_specifications[i].hasOwnProperty('options') && test_specifications[i].options.includes('silent')) continue;
					
					let found_result = false;
					if (results.test_results.hasOwnProperty(test_specifications[i].id))
						found_result = results.test_results[test_specifications[i].id];
					
					// Test statistics
					if (found_result && found_result.status == 1)
						pwi_tests_passed++;
					
					// Populate results widget
					let element = document.createElement('h2');
					element.className = "filelist ";
					
					// Temporary scope hack
					(function(i){
						element.onclick = function() { pwi_render_test_result(tests, results, test_specifications[i].id); }
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
	let x = pwi_current_path.lastIndexOf('/');
	if (x==-1) {
		return;
	}
	
	let autotest_file = pwi_current_path.substr(0,x+1) + ".autotest";
	let at_result_file = pwi_current_path.substr(0,x+1) + ".at_result";
	
	let xmlhttp = new XMLHttpRequest();
	let url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(autotest_file) + "&type=mtime";
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			let autotest_mtime = parseInt(xmlhttp.responseText);
			
			let xmlhttp2 = new XMLHttpRequest();
			let url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(at_result_file) + "&type=mtime";
			xmlhttp2.onreadystatechange = function() {
				if (xmlhttp2.readyState == 4 && xmlhttp2.status == 200) {
					let at_result_mtime = parseInt(xmlhttp2.responseText);
					if (at_result_mtime < autotest_mtime) {
						let results_button = document.getElementById('phpwebide_test_results_data');
						results_button.innerHTML = "<i class=\"fa fa-clock-o\"></i> " + results_button.innerHTML;
					}
					pwi_clear_task("pwi_is_test_outdated");
				}
			}
			xmlhttp2.open("GET", url, true);
			xmlhttp2.send();
			
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
	let xmlhttp = new XMLHttpRequest();
	let url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(pwi_current_path) + "&type=mtime&format=d. m. Y. H:i:s";
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
			
	let xmlhttp = new XMLHttpRequest();
	let url = "zamger/slanje_zadace.php?username=" + pwi_current_user + "&filename=" + pwi_current_path + "&zadaca=" + pwi_homework_data.id + "&zadatak=" + pwi_homework_data.zadatak;
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
	let x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	
	buildserviceStartTest(pwi_current_user, pwi_current_path.substr(0,x), 0);
}


// For now we will just open the .at_result_file
// Can't remember where I wanted to call this
function pwi_show_test_results() {
	// Find parent folder of file
	let x = pwi_current_path.lastIndexOf('/');
	if (x==-1) { 
		pwi_tests_total = pwi_tests_passed = false;
		document.getElementById('phpwebide_test_results').style.display = "none";
		return;
	}
	
	// Find .at_result file in folder
	let at_result_file = pwi_current_path.substr(0,x+1) + ".at_result";
	
	pwi_editor_load(at_result_file, "file"); 
	pwi_tree_select(at_result_file);
}


// Redirect to URL for restoring older version of file
function pwi_restore_revision() {
	let url;
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
	let menu = document.getElementById('phpwebide_deploy_menu');
	let button = document.getElementById('phpwebide_deploy_button');
	
	// If menu is visible for some reason, hide it
	menu.style.display = "none";
	button.style.display = "none";
	
	// If path didn't change, that's it
	if (menu.hasOwnProperty('pwi_path') && menu.pwi_path == pwi_current_path)
		return;
	let path = pwi_current_path;
	if (path.split("/").length < 4) path += "/";

	if (path.split("/")[0] == "UUP_GAME")
		return pwi_populate_deploy_menu_uup_game();
		
	// Contact web service to get a list of files in menu
	assignmentFromPath(path, function(t) {
		console.log("assignmentFromPath");
		listAssignmentFiles(t.course, t.year, t.external, t.assignment, t.task, function(files) {
			console.log("LENGTH "+files.length);
			// No files to deploy
			if (files.length === 0) return;
			
			// There are files
			button.style.display = "inline";
			
			// Clear existing menu
			while (menu.firstChild)
				menu.removeChild(menu.firstChild);
		
			for (let i=0; i<files.length; i++) {
				let element = document.createElement('h2');
				element.className = "filelist filelist-file";
				
				let file = files[i];
				if (typeof file === 'object') file = files[i].filename;
				element.filename = file;
				
				// Temporary scope hack
				(function(t,i){
					element.onclick = function() { 
						deployAssignmentFile(t.course, t.year, t.external, t.assignment, t.task, this.filename, pwi_current_user); 
						setTimeout( function() { 
							pwi_editor_load(pwi_current_path,'file');
						}, 3000);
					}
				})(t,i);
		
				element.innerHTML = file;
				menu.appendChild(element);
			}
		});
	});
}


// Special Deploy function for UUP GAME
function pwi_populate_deploy_menu_uup_game() {
	let menu = document.getElementById('phpwebide_deploy_menu');
	let button = document.getElementById('phpwebide_deploy_button');

	// Clear existing menu
	while (menu.firstChild)
		menu.removeChild(menu.firstChild);

	let pathParts = pwi_current_path.split("/");
	if (pathParts.length < 2) return;

	uupg_get_assignments(function(assignments) {
		let asgn = assignments.find(function(asgn) {
			return asgn.path == "/" + pathParts[1];
		});
		if (asgn) {
			uupg_get_current_task(pwi_current_user, asgn.id, function(taskId) {
				if (!taskId) return;
				let taskDetails = asgn.children.find(function(taskDesc) {
					return taskDesc.id = taskId;
				});
				let thereBeFiles = false;
				taskDetails.children.forEach(function (file, i) {
					if (!thereBeFiles) {
						thereBeFiles = true;
						button.style.display = "inline";
					}

					let element = document.createElement('h2');
					element.className = "filelist filelist-file";
					element.filename = file.name;

					// Temporary scope hack
					(function(taskId,fileName){
						element.onclick = function() {
							uupg_deploy_file_to_student(pwi_current_user, taskId, fileName);
							setTimeout( function() {
								pwi_editor_load(pwi_current_path,'file');
							}, 3000);
						}
					})(taskId,file.name);

					element.innerHTML = file.name;
					menu.appendChild(element);
				})
			});
		}
	})
}

// Check if there's something to deploy
function pwi_render_test_result(tests, results, test) {
	let form = document.getElementById('pwi_test_results_form');
	form.task.value = JSON.stringify(tests);
	form.result.value = JSON.stringify(results);
	form.test.value = test;
	
	window.open('about:blank','Popup_Window','toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=0,width=700,height=700,left=312,top=234');
	form.target = 'Popup_Window';
	form.submit();
}




// ------------ EDITOR FUNCTIONS ----------------

function pwi_editor_initialize(editable) {
	let editor = ace.edit("editor");
	
	// Don't know why I need to resize twice ?
	let newbottom = window.innerHeight - 220 - document.getElementById('phpwebide_tree').clientHeight;
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
		editor.$blockScrolling = Infinity;
	} else {
		editor.getSession().on("change", pwi_schedule_save);
	}
}

function pwi_editor_load(path, type, rev) {
	pwi_add_task("pwi_editor_load "+path);
	
	let xmlhttp = new XMLHttpRequest();
	let url = "services/file.php?user=" + pwi_current_user + "&path=" + encodeURIComponent(path) + "&type=" + type;
	if (rev) url += "&rev=" + rev;
	
	if (pwi_image !== false) {
		document.body.removeChild(pwi_image)
		pwi_image = false;
	}
	if (path.endsWith(".png") || path.endsWith(".jpg") || path.endsWith(".jpeg") || path.endsWith(".gif")) {
		pwi_image = document.createElement("img");
		pwi_image.src = url;
		pwi_image.style.position = "absolute";
		let rect = document.getElementById("editor").getBoundingClientRect();
		pwi_image.style.left = rect.left +  window.scrollX + "px";
		pwi_image.style.top = rect.top +  window.scrollY + "px";
		pwi_image.style.zIndex = 1000;
		document.body.appendChild(pwi_image);
		pwi_clear_task("pwi_editor_load "+path);
		return;
	}
	
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				let response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				pwi_clear_task("pwi_editor_load "+path);
				return;
			}
			
			let editor = ace.edit("editor");
			editor.setValue(xmlhttp.responseText);
			editor.getSession().setMode("ace/mode/c_cpp"); // FIXME hardcodirano
			
			// Scroll and position cursor on first line
			editor.resize(true);
			editor.scrollToLine(1, true, true, function () {});
			editor.gotoLine(1); 
			
			// Resize editor again
			let newbottom = window.innerHeight - 220 - document.getElementById('phpwebide_tree').clientHeight;
			document.getElementById('editor').style.bottom = "" + newbottom + "px";

			editor.focus();
			
			if (type == "file") {
				pwi_toolbar_update();
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
		let url = window.location.href, newurl;
		let x = url.lastIndexOf('&path=');
		if (x>0) {
			newurl = url.substr(0,x) + "&path="+path;
			let y = url.indexOf('&', x+1);
			if (y>0)
				newurl += url.substr(y);
			let stateObj = { foo: "bar" };
			window.history.pushState(stateObj, document.title, newurl);
		}
	}
}


// Old autosave functions for webide - FIXME

function pwi_schedule_save(e) {
	console.log("Schedule save " + e);
	if (pwi_save_has_timeout) clearTimeout(pwi_save_timeout);
	pwi_save_timeout = setTimeout('pwi_do_save()', 5000);
	pwi_save_has_timeout = true;
}

function pwi_do_save() {
	pwi_save_has_timeout = false;
	
	let mypostrequest = new XMLHttpRequest();
	mypostrequest.onreadystatechange=function() {
		if (mypostrequest.readyState==4){
			if (mypostrequest.status==200 || window.location.href.indexOf("http")==-1){
				let xmldata=mypostrequest.responseText; //retrieve result as an text
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
	let editor = ace.edit("editor");
	let code = encodeURIComponent(editor.getSession().getValue())

	// FIXME broken
	sta = akcija = student = zadaca = zadatak = projekat = "";
	/*let sta = encodeURIComponent('<?=$_REQUEST[sta]?>');
	let akcija = encodeURIComponent("slanje");
	let student = encodeURIComponent(<?=$student?>);
	let zadaca = encodeURIComponent(<?=$zadaca?>);
	let zadatak = encodeURIComponent(<?=$zadatak?>);
	let projekat = encodeURIComponent(<?=$projekat?>);*/

	let parameters="sta="+sta+"&akcija="+akcija+"&student="+student+"&zadaca="+zadaca+"&zadatak="+zadatak+"&projekat="+projekat+"&code="+code;
	mypostrequest.open("POST", "index.php", true)
	mypostrequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded")
	mypostrequest.send(parameters)
}




// ------------ TASK MANAGER ----------------

function pwi_add_task(task) {
	console.log("pwi_add_task "+task);
	let found=false;
	for (let i=0; i<pwi_tasks.length; i++)
		if (pwi_tasks[i] == task)
			found=true;
	if (!found) pwi_tasks.push(task);
	if (pwi_tasks.length >= 1) document.getElementById('phpwebide_spinner').style.display = "inline";
}


function pwi_clear_task(task) {
	console.log("pwi_clear_task "+task);
	for (let i=0; i<pwi_tasks.length; i++) {
		if (pwi_tasks[i] == task)
			pwi_tasks.splice(i,1);
	}
	if (pwi_tasks.length === 0) document.getElementById('phpwebide_spinner').style.display = "none";
}




// ------------ RECONSTRUCT ----------------

function pwi_reconstruct(path="", start=1) {
	if (path == "") path = pwi_current_path;
	pwi_add_task("pwi_reconstruct "+path);
	
	let xmlhttp = new XMLHttpRequest();
	let url = "services/stats_reconstruct.php?user=" + pwi_current_user + "&filename=" + encodeURIComponent(path) + "&start=" + start + "&limit=" + pwi_reconstruct_limit;
	
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText.includes("{\"success\":\"false\",")) {
				let response = JSON.parse(xmlhttp.responseText);
				showMsg(response.message);
				setTimeout(hideMsg,5000);
				pwi_clear_task("pwi_reconstruct "+path);
				return;
			}
			
			pwi_reconstruct_path = path;
			
			let response = JSON.parse(xmlhttp.responseText);
			pwi_reconstruct_data = response.data;
			pwi_reconstruct_has_more = response.hasMore;
			pwi_reconstruct_show(0);
			
			let slider = document.getElementById('phpwebide_reconstruct_slider');
			slider.min = 0;
			slider.max = pwi_reconstruct_data.length;
			slider.value = 0;
			
			let speed_ctl = document.getElementById('phpwebide_reconstruct_speed').value;
			pwi_reconstruct_speed_change(speed_ctl);
			pwi_reconstruct_play_stop();
			
			document.getElementById('phpwebide_reconstruct_options').style.display = "inline";
			document.getElementById('phpwebide_reconstruct_button').style.display = "none";
			
			pwi_clear_task("pwi_reconstruct "+path);
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();

}


function pwi_reconstruct_next(i) {
	if (pwi_reconstruct_play) {
		pwi_reconstruct_show(i);
		if (i == pwi_reconstruct_data.length && pwi_reconstruct_has_more)
			pwi_reconstruct( pwi_reconstruct_path, pwi_reconstruct_data[i-1].version );
		else if (i < pwi_reconstruct_data.length) {
			let delay = 1000 / pwi_reconstruct_speed;
			if (pwi_reconstruct_realtime && pwi_reconstruct_data[i] && pwi_reconstruct_data[i+1])
				delay = (pwi_reconstruct_data[i+1].timestamp - pwi_reconstruct_data[i].timestamp) * delay;
			pwi_reconstruct_play = setTimeout(function() { 
				pwi_reconstruct_next( parseInt(i) + 1 ); 
			}, delay);
		}
		else
			pwi_reconstruct_play_stop();
	}
}

function pwi_reconstruct_show(i) {
	if (i >= pwi_reconstruct_data.length) {
		return;
	}
	
	let editor = ace.edit("editor");
	editor.setOptions({
		highlightActiveLine: true,
	});
	console.log("Reconstruct i=" + i + " length=" + pwi_reconstruct_data.length)
	console.log( pwi_reconstruct_data[i] );
	editor.setValue( pwi_reconstruct_data[i].contents );
	//editor.getSession().setMode("ace/mode/c_cpp"); // FIXME hardcodirano
	
	// Scroll and position cursor on first line
	//editor.resize(true);
	//editor.scrollToLine( pwi_reconstruct_data[version].line, true, true, function () {} );
	//editor.gotoLine( pwi_reconstruct_data[version].line ); 
	//editor.focus();
	editor.clearSelection();
	editor.moveCursorTo( pwi_reconstruct_data[i].firstLine, 0, false );
	if (pwi_reconstruct_data[i].firstLine < pwi_reconstruct_data[i].lastLine)
		editor.selection.selectTo ( pwi_reconstruct_data[i].lastLine+1, 0 );
	editor.centerSelection();
	
	document.getElementById('phpwebide_modified_time').innerHTML = pwi_reconstruct_data[i].datetime;
	
	let slider = document.getElementById('phpwebide_reconstruct_slider');
	slider.value = i;
}

function pwi_reconstruct_slider_change(value) {
	if (pwi_reconstruct_play) pwi_reconstruct_play_stop();
	pwi_reconstruct_show(value);
}


function pwi_reconstruct_speed_change(value) { pwi_reconstruct_speed = value; }
function pwi_reconstruct_realtime_toggle(value) {
	pwi_reconstruct_realtime = value;
	// Don't wait for the next step, as it may be very long
	if (!value) {
		const slider = document.getElementById('phpwebide_reconstruct_slider');
		clearTimeout(pwi_reconstruct_play);
		pwi_reconstruct_play = setTimeout(function() {
			pwi_reconstruct_next( slider.value + 1 );
		}, 10);
	}
}

function pwi_reconstruct_play_stop() {
	console.log("pwi_play_stop");
	if (pwi_reconstruct_play) {
		clearTimeout(pwi_reconstruct_play);
		pwi_reconstruct_play = false;
		document.getElementById('phpwebide_reconstruct_play_icon').className = "fa fa-play";
	}
	else {
		const slider = document.getElementById('phpwebide_reconstruct_slider');
		pwi_reconstruct_play = setTimeout(function() { 
			pwi_reconstruct_next(slider.value); 
		}, 1000 / pwi_reconstruct_speed);
		document.getElementById('phpwebide_reconstruct_play_icon').className = "fa fa-pause";
	}
}
