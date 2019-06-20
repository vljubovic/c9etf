/* etf.zadaci plugin for Cloud9 - 2. 5. 2019. 15:45
 * 
 * @author Vedran Ljubovic <vljubovic AT etf DOT unsa DOT ba>
 * 
 * This plugin supports the "assignment" feature of C9@ETF webide. It adds a left side panel
 * with tree containing subjects, and assignments. Users can use this tree instead of the
 * usual file tree as a simpler alternative.
 */

define(function(require, module, exports) {
    main.consumes = ["Panel", "ui", "panels", "dialog.error", "dialog.info", "tabManager", "fs"];
    main.provides = ["zadaci.panel"];
    return main;

    function main(options, imports, register) {
        var Panel = imports.Panel;
        var ui = imports.ui;
        var tabs = imports.tabManager;
        var Tree = require("ace_tree/tree");
        var TreeData = require("./dataprovider");
        var panels = imports.panels;
        var showError = imports["dialog.error"].show;
        var showInfo = imports["dialog.info"].show;
        var fs = imports.fs;

        /***** Initialization *****/

        var plugin = new Panel("ETF", main.consumes, {
            index: 300,
            caption: "Zadaci"
        });

        var winCommands, txtFilter, tree, dataProvider, treeRoot;
      
        function load(){
            panels.on("afterAnimate", function(){
                if (panels.isActive("zadaci.panel"))
                    tree && tree.resize();
            });
        }

        plugin.on("draw", function(e){
	    
	    console.log("PANEL DRAW");
            // Insert css
 	    var css = require("text!./style.css");
	    
	    // Get contrasting color
	    var defaultView = (e.html.ownerDocument || document).defaultView;
	    var color = defaultView.getComputedStyle(e.html, null).getPropertyValue("background-color"); // This doesn't seem to work anymore...
	    var rgb;
	    if (typeof color === 'string' && color == "rgba(0, 0, 0, 0)")
		rgb = [0,0,0,0];
	    else if (typeof color === 'string') 
		rgb = hexToRGBArray(color); // Dies with color 0,0,0,0 !!
		else rgb = color;
	    var luma = (0.2126 * rgb[0]) + (0.7152 * rgb[1]) + (0.0722 * rgb[2]);
	    if (luma>=165) {
		  replaceColor = "black"; 
		  togglerImg = "/static/plugins/c9.ide.theme.flat/images/tree_close_arrow_small_dark_flat_light@1x.png";
	    } else {
		  replaceColor = "white";
		  togglerImg = "/static/plugins/c9.ide.layout.classic/images/tree_close_arrow_small@1x.png";
	    }
	    css = css.replace(/MYCOLOR/g, replaceColor);
	    css = css.replace(/TOGGLERIMG/g, togglerImg);
	    
            ui.insertCss(css, e.staticPrefix, plugin);
	    
	    e.html.innerHTML = "<style>"+css+"</style><div class='zadtree-naslov'>Izaberite predmet:</div><div class='zadtree' id='thecontainer'></div>";
	    var thecontainer=document.getElementById('thecontainer');
	    thecontainer.style.height="93%";
	    
	    /*var thecontainer = document.createElement("DIV");
	    //thecontainer.className="blahblah";
	    thecontainer.appendChild(document.createTextNode("Zadaci"));*/
		var treeParent = e.aml;
		console.log(thecontainer);

		tree = new Tree(thecontainer);
		tree.renderer.setTheme({cssClass: "zadtree"});
		treeRoot = {};
		dataProvider = new TreeData(treeRoot);
		tree.setDataProvider(dataProvider);
		dataProvider.setRoot(treeRoot);
		dataProvider.updateData(false);
		console.log(tree);
		
		tree.on("dblclick", function(e) {
			var domTarget = e.domEvent.target;
			var node = e.getNode();
			if (!node || !domTarget)
				return;
			console.log("DBLCLICK");
			console.log(node);
			createTaskFolder(node, true);
		});

		loadSubjects();
        });
	
	function loadSubjects() {
		treeRoot = {
			label: "root",
			items: []
		};
		var xmlhttp = new XMLHttpRequest();
		var url = "/assignment/ws.php?action=courses";
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				result = JSON.parse(xmlhttp.responseText);
				if (result.success == "true") {
					courses = result.data;
					if (courses.length == 0)
						showError("Nemate nijedan predmet. Kontaktirajte tutora");
					for (i=0; i<courses.length; i++) {
						item = { 
							label: courses[i].name,
							id: courses[i].id,
							year: courses[i].year,
							abbrev: courses[i].abbrev,
							course_type: courses[i].type,
							external: courses[i].external,
							clickable: false,
							language: "C++"
						};
						if (courses[i].type == "external") item.external = true;
						else if (!courses[i].hasOwnProperty("external")) item.external = false;
						if (courses[i].hasOwnProperty("language"))
							item.language = courses[i].language;
						treeRoot.items.push(item);
					}
					console.log("Ucitani predmeti");
					console.log(courses);
					dataProvider.setRoot(treeRoot);
					dataProvider.updateData(false);
					for (var i=0; i<courses.length; i++) {
						item = { 
							label: courses[i].name,
							id: courses[i].id,
							year: courses[i].year,
							abbrev: courses[i].abbrev,
							course_type: courses[i].type,
							external: courses[i].external,
							clickable: false
						};
						if (courses[i].type == "external") item.external = true;
						else if (!courses[i].hasOwnProperty("external")) item.external = false;
						if (courses[i].hasOwnProperty("language"))
							item.language = courses[i].language;
						loadTasks(item);
					}
				} else {
					/*if (result.code == "ERR005")
						showError("Niste prijavljeni na Zamger");
					else*/ if (result.code == "ERR001")
						showError("Session expired. Please login again");
					else
						showError("Unknown server error");
					console.error("ws.php?action=courses success="+result.success);
				}
			}
			else if (xmlhttp.readyState == 4) {
				showError("Unknown server error");
				console.error("ws.php?action=courses status " + xmlhttp.status);
				setTimeout(function() { loadSubjects(); }, 5000);
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}

	function parseSubitems(subitems, courseNode) {
		var items = [];
		if (subitems == null) return items;

		for (var i=0; i<subitems.length; i++) {
		 	var item = {
		 		id: subitems[i].id,
		 		label: subitems[i].name,
		 		courseNode: courseNode
		 	};
			if (subitems[i].hasOwnProperty("path"))
				item.path = subitems[i].path;
			if (subitems[i].hasOwnProperty("items") && subitems[i].items != null && subitems[i].items.length > 0) {
				item.clickable = false;
				item.items = parseSubitems(subitems[i].items, courseNode);
			} else {
				item.clickable = true;
				if (subitems[i].hasOwnProperty("files"))
					item.files = subitems[i].files;
			}
		 	items.push(item);
		}
		return items;
	}
	
	function loadTasks(course) {
		var ci=-1;
		for (i=0; i<treeRoot.items.length; i++) {
			if (treeRoot.items[i].id == course.id &&
				treeRoot.items[i].year == course.year &&
				treeRoot.items[i].external == course.external)
				ci=i;
		}
		if (ci == -1) {
			showError("Greška: nepoznat predmet");
			return;
		}

		var xmlhttp = new XMLHttpRequest();
		var url = "/assignment/ws.php?action=assignments&course="+course.id+"&year="+course.year;
		if (course.external) url += "&X";
		console.log("loadTasks course "+course.id+" year "+course.year + " X: " + course.external);
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				try {
					result = JSON.parse(xmlhttp.responseText);
					if (result.success == "true") {
						courseItems = [];
						asgns = result.data;
						if (asgns.length == 0)
							showError("Spisak zadataka dobijen od servera je invalidan");
						for (var i=0; i<asgns.length; i++) {
							item = { 
								id: asgns[i].id,
								label: asgns[i].name,
								path: asgns[i].path,
								items: [],
								clickable: false,
								courseNode: treeRoot.items[ci]
							};

							item.items = parseSubitems(asgns[i].items, treeRoot.items[ci]);
							courseItems.push(item);
						}
						treeRoot.items[ci].items = courseItems;
						dataProvider.setRoot(treeRoot);
						dataProvider.updateData(false);
						console.log("Tasks loaded for course "+course.id+" year "+course.year);
					} else {
						/*if (result.code == "ERR005")
							showError("Niste prijavljeni na Zamger");
						else*/ if (result.code == "ERR001")
							showError("Session expired. Please login again");
						else if (result.code == "ERR003") { // No assignments - delete course
							treeRoot.items.splice(ci,1);
							dataProvider.setRoot(treeRoot);
							dataProvider.updateData(false);
							console.log("No tasks for course "+course.id+" year "+course.year+", deleting from tree");
						}
						else
							showError("Unknown server error");
						console.error(url + " success="+result.success+" code "+result.code);
					}
				} catch(e) {
					showError("Problems communicating with server");
					console.log("Failed to parse result as JSON");
					setTimeout(function() { loadTasks(course); }, 5000);
				}
			}
			else if (xmlhttp.readyState == 4) {
				showError("Problems communicating with server");
				console.error(url + " " + xmlhttp.status);
				setTimeout(function() { loadTasks(course); }, 5000);
			}
		};
				
		xmlhttp.ontimeout = function(e) {
			showInfo("The server is a bit slow... Please wait");
			console.error("etf.zadaci: loadTasks " + course + " timeout - retry");
			setTimeout(function() { loadTasks(course); }, 5000);
		};
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	
        
    function createTaskFolder(node, nohide) {
		if (!node.clickable) return;
       
		// Setup some variables and construct path
		var course = node.courseNode.id;
		var year = node.courseNode.year;
		var course_external = node.courseNode.external;
		var task_id = node.id;
		var task_name = node.label;
		var path = "/" + node.path;
		var files = node.files;

		var filename;
		console.log("Node:");
		console.log(node);

		var urlpart = "course="+course+"&year="+year;
		if (course_external)
			urlpart += "&X";
		urlpart += "&task_direct="+task_id;
		
		// URL to getFile service
		var getFileUrl = "/assignment/ws.php?action=getFile&" + urlpart;
		getFileUrl += "&replace=true";
    
		nohide || plugin.hide();
		
		// Close all tabs
		window.console.log("etf.zadaci: closing all tabs");
		tttabs = tabs.getTabs();
		for(i=0; i<tttabs.length; i++) {
			if (typeof tttabs[i].path === "undefined") continue;
			console.log(tttabs[i]);
			tttabs[i].close();
		}
		// TODO: Also remove all breakpoints from debugger
		
		
		// If a folder with given name already exists, we just open it in a new tab
		window.console.log("etf.zadaci: does folder exist?");
		fs.exists(path, function(folder_exists) {
			if (folder_exists) {
				window.console.log("etf.zadaci: Folder already exists "+path);
				loadFiles(files, getFileUrl, path, 300);
			}
			
			else {
				// Folder doesn't exist - create it and load files
				window.console.log("etf.zadaci: Making folder "+path);
				
				fs.mkdirP(path, function(err) {
					if (err) { 
						window.console.log("etf.zadaci: Error making folder "+path);
						return console.error(err); // De facto do nothing
					}
					loadFiles(files, getFileUrl, path, 300);
				});
			}
		});
        }

        
        function loadFiles(files, urlpart, path, delay) {
		console.log("loadFiles ");
		console.log(files);
		if (files.length == 0) {
			console.log("File list empty");
			return;
		}
		var filename, fullpath, open;
		var filedata = files[0];
		if (files[0].hasOwnProperty("filename")) {
			filename = files[0].filename;
			open = files[0].show;
			console.log(open);
		} else {
			filename = files[0];
			open = (files[0].charAt(0) != '.');
		}
		fullpath = path + "/" + filename;
		
		fs.exists(fullpath, function(file_exists) {
			if (file_exists) {
				// File exists, just open it
				if (open)
					tabs.openFile( fullpath, true, function(err, tab) {
						if (err) return console.error(err);
						panels.panels.tree.expandAndSelect( fullpath );
					});
			}
			
			else {
				// File doesn't exist, get it from web service
				var xmlhttp3 = new XMLHttpRequest();
				var url2 = urlpart + "&file=" + filename;
				
				console.log("etf.zadaci: URL: "+url2);
				xmlhttp3.onreadystatechange = function() {
					if (xmlhttp3.readyState == 4 && xmlhttp3.status == 200) {
						// Is response a JSON code?
						if (xmlhttp3.response.includes("\"code\": \"ERR") || xmlhttp3.response.includes("\"code\": \"STA")) {
							try {
								var json = JSON.parse(xmlhttp3.responseText);
								if (json.success == false || json.success == "false")
									showError("Service error: " + json.message);
								else {
									// If success is true, service did all the work, just open the file
									window.console.log(json.code + ": " + json.message);
									// Delay opening since it takes some time for server to finish copying
									if (open) 
										setTimeout(function() {
											tabs.openFile( fullpath, true, function(err, tab) {
												if (err) return console.error(err);
												panels.panels.tree.expandAndSelect( fullpath );
											});
										}, 1000);
								}
								return;
							} catch(e) {
								// This is not a JSON file, continue as usual
							}
						}
						
						// Create txt file from JSON response
						// This doesn't work for binary files, fs.writeFile mangles them
						window.console.log("etf.zadaci: Read file "+fullpath);
						fs.writeFile( fullpath, xmlhttp3.response, function (err, data) {
							if (err) { 
								window.console.log("etf.zadaci: Error writing file "+fullpath);
								return console.error(err); // De facto do nothing
							}
							window.console.log("etf.zadaci: Created file "+fullpath);
							if (open) 
								tabs.openFile( fullpath, true, function(err, tab) {
									if (err) return console.error(err);
									panels.panels.tree.expandAndSelect( fullpath );
								});
						});
					}
					else if (xmlhttp3.readyState == 4) {
						// Some kind of error, retry after 1 second
						console.error("etf.zadaci: " + url2 + " " + xmlhttp3.status);
						delay = 1000;
						files.push(filedata);
						if (files.length == 1)
							setTimeout(function() { loadFiles(files, urlpart, path, delay); }, delay);
					}
				};
				
				xmlhttp3.ontimeout = function(e) {
					showInfo("The server is a bit slow... Please wait");
					console.error("etf.zadaci: " + url2 + " timeout - retry");
					delay = 3000;
					files.push(filedata);
					if (files.length == 1)
						setTimeout(function() { loadFiles(files, urlpart, path, delay); }, delay);
				};
				
				xmlhttp3.open("GET", url2, true);
				xmlhttp3.timeout = 5000;
				xmlhttp3.send();
			}
			
			// Move to next file
			files.splice(0,1);
			if (files.length>0)
				setTimeout(function() { loadFiles(files, urlpart, path, delay); }, delay);
		});
	}
        
	function hexToRGBArray(color)
	{
		var cssColorRegex = /^rgb\((\d+),\s?(\d+),\s?(\d+)\)$/;
		var match = cssColorRegex.exec(color);
		if (color.length === 3)
			color = color.charAt(0) + color.charAt(0) + color.charAt(1) + color.charAt(1) + color.charAt(2) + color.charAt(2);
		else if (match) {
			var rgb = [];
			rgb[0] = parseInt(match[1]);
			rgb[1] = parseInt(match[2]);
			rgb[2] = parseInt(match[3]);
			return rgb;
		}
		else if (color.length !== 6)
			throw('Invalid hex color: ' + color);
		var rgb = [];
		for (var i = 0; i <= 2; i++)
			rgb[i] = parseInt(color.substr(i * 2, 2), 16);
		return rgb;
	}

        plugin.load();

        /***** Register *****/

        plugin.freezePublicAPI({

        });

        register("", {
            "zadaci.panel": plugin
        });
    }
});
