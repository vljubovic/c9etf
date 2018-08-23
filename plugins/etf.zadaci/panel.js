/* etf.zadaci plugin for Cloud9 - 23. 08. 2018.
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
							clickable: false
						};
						if (courses[i].type == "external") item.external = true;
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
						loadTasks(item);
					}
				} else {
					/*if (result.code == "ERR005")
						showError("Niste prijavljeni na Zamger");
					else*/ if (result.code == "ERR001")
						showError("Vaša sesija je istekla. Probajte Logout pa Login");
					else
						showError("Greška na serveru");
					console.error("ws.php?action=courses success="+result.success);
				}
			}
			if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
				showError("Greška na serveru.");
				console.error("ws.php?action=courses 500");
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	
	function loadTasks(course) {
		var ci=-1;
		for (i=0; i<treeRoot.items.length; i++) {
			if (treeRoot.items[i].id == course.id &&
				treeRoot.items[i].year == course.year)
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
				result = JSON.parse(xmlhttp.responseText);
				if (result.success == "true") {
					courseItems = [];
					asgns = result.data;
					if (asgns.length == 0)
						showError("Spisak zadataka dobijen od servera je invalidan");
					for (i=0; i<asgns.length; i++) {
						item = { 
							id: asgns[i].id,
							label: asgns[i].name,
							path: asgns[i].path,
							items: [],
							clickable: false,
						};
						for (j=1; j<=asgns[i].tasks; j++) {
							taskitem = {
								label: "Zadatak "+j,
								id: j,
								clickable: true
							};
							item.items.push(taskitem);
						}
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
						showError("Vaša sesija je istekla. Probajte Logout pa Login");
					else if (result.code == "ERR003") { // No assignments - delete course
						treeRoot.items.splice(ci,1);
						dataProvider.setRoot(treeRoot);
						dataProvider.updateData(false);
						console.log("No tasks for course "+course.id+" year "+course.year+", deleting from tree");
					}
					else
						showError("Greška na serveru");
					console.error(url + " success="+result.success+" code "+result.code);
				}
			}
			if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
				showError("Greška na serveru.");
				console.error(url + " 500");
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	
        
        function createTaskFolder(node, nohide) {
		if (!node.clickable) return;
       
		// Setup some variables and construct path
		var course = node.parent.parent.id;
		var year = node.parent.parent.year;
		var course_abbrev = node.parent.parent.abbrev;
		var course_external = node.parent.parent.external;
		var asgn_id = node.parent.id;
		var asgn_name = node.parent.label;
		var asgn_path = node.parent.path;
		var task_id = node.id;
		var task_name = node.label;
		var path = "/" + course_abbrev + "/" + asgn_path + "/Z" + task_id;
		var filename;
		if (course_abbrev == "OR")
			filename = "main.c";
		else
			filename = "main.cpp"; // FIXME
		var fullpath = path + "/" + filename;
		var title = asgn_name + ", " + task_name;
		console.log("PATH "+fullpath);
		
		var urlpart = "course="+course+"&year="+year;
		if (course_external)
			urlpart += "&X";
		urlpart += "&assignment="+asgn_id+"&task="+task_id;
    
		nohide || plugin.hide();
		
		window.console.log("etf.zadaci: closing all tabs");
		// Close all tabs
		tttabs = tabs.getTabs();
		for(i=0; i<tttabs.length; i++) {
			if (typeof tttabs[i].path === "undefined") continue;
			console.log(tttabs[i]);
			if (tttabs[i].path != fullpath)
				tttabs[i].close();
		}
		// TODO: Also remove all breakpoints from debugger
		window.console.log("etf.zadaci: does folder exist?");
		
		// If a folder with given name already exists, we just open it in a new tab
		fs.exists(path, function(file_exists) {
			if (file_exists) {
				window.console.log("etf.zadaci: Folder already exists "+path);
				fs.exists(fullpath, function(file_exists) {
					if (file_exists) {
						/*for(i=0; i<tttabs.length; i++) {
							if (typeof tttabs[i].path === "undefined") continue;
							if (tttabs[i].path === fullpath) {
								console.log("Focusing tab "+tttabs[i].path);
								tttabs.focusTab(tttabs[i]);
								panels.panels.tree.expandAndSelect(fullpath);
								return; // Nothing else needs to be done
							}
						}*/

						console.log("etf.zadaci: Open existing file "+fullpath);
						tabs.openFile(fullpath, true, function(err, tab) {
							if (err) return console.error(err);
						});
						
						panels.panels.tree.expandAndSelect(fullpath);
						return; // Nothing else needs to be done
					} else {
						showInfo("Folder "+path+" postoji, ali u njemu nema datoteke "+filename+".\nObrišite folder ili preimenujte datoteku.");
						panels.panels.tree.expandAndSelect(path);
						return;
					}
				});
				return;
			}
			
			loadFileList(urlpart, path, title);
		});
        }
        

	function loadFileList(urlpart, path, title) {
		// Retrieve default files from service
		var xmlhttp2 = new XMLHttpRequest();
		var url = "/assignment/ws.php?action=files&" + urlpart;
		
		console.log("etf.zadaci: URL: "+url);
		xmlhttp2.onreadystatechange = function() {
			if (xmlhttp2.readyState == 4 && xmlhttp2.status == 200) {
				result = JSON.parse(xmlhttp2.responseText);
				if (result.success == "true") {
					window.console.log("etf.zadaci: Making folder "+path);
					files = result.data;
					var url2 = "/assignment/ws.php?action=getFile&" + urlpart;
					url2 += "&replace=" + encodeURIComponent(title);
					
					fs.mkdirP(path, function(err) {
						if (err) { 
							window.console.log("etf.zadaci: Error making folder "+path);
							return console.error(err); // De facto do nothing
						}
						loadFiles(files, url2, path);
					});
				}
			} else if (xmlhttp2.readyState == 4) {
				console.error(url + " status: " + xmlhttp2.status);
				setTimeout(function() { loadFileList(urlpart,path,title); }, 1000);
			}
		}
		xmlhttp2.open("GET", url, true);
		xmlhttp2.send();
	}

        
        function loadFiles(files, urlpart, path) {
		var xmlhttp3 = new XMLHttpRequest();
		var url2 = urlpart + "&file="+files[0];
		var first_char = files[0].charAt(0);
		var fullpathz = path + "/" + files[0];
		console.log("etf.zadaci: URL: "+url2);
		xmlhttp3.onreadystatechange = function() {
			if (xmlhttp3.readyState == 4 && xmlhttp3.status == 200) {
				window.console.log("etf.zadaci: Read file "+fullpathz);
				fs.exists(fullpathz, function(file_exists) {
					if (file_exists) {
						window.console.log("etf.zadaci: File already exists!");
						if (first_char != ".")
							tabs.openFile( fullpathz, true, function(err, tab) {
								if (err) return console.error(err);
								console.log("etf.zadaci: PANELS PANELS TREE:");
								console.log(panels.panels);
								console.log(panels.panels.tree);
								panels.panels.tree.expandAndSelect( fullpathz );
							});
					} else {
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
				});
			}
			if (xmlhttp3.readyState == 4 && xmlhttp3.status == 500) {
				console.error("etf.zadaci: " + url2 + " 500");
				setTimeout(function() { loadFiles(files, urlpart, path); }, 1000);
			}
		}
		xmlhttp3.open("GET", url2, true);
		xmlhttp3.send();
		
		files.splice(0,1);
		if (files.length>0)
			setTimeout(function() { loadFiles(files, urlpart, path); }, 300);
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
