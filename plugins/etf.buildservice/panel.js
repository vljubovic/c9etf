// Verzija 03. 04. 2018. 18:36

define(function(require, exports, module) {
    main.consumes = [
        "Panel", "ui", "menus", "panels", "commands", "tabManager", "layout",
        "settings", "fs", "dialog.error", "dialog.info"
    ];
    main.provides = ["buildservice.panel"];
    return main;
    
    function main(options, imports, register) {
        var Panel = imports.Panel;
        var ui = imports.ui;
        var tabs = imports.tabManager;
        var menus = imports.menus;
        var panels = imports.panels;
        var layout = imports.layout;
        var commands = imports.commands;
        var settings = imports.settings;
        var fs = imports.fs;
        var showError = imports["dialog.error"].show;
        var showInfo = imports["dialog.info"].show;

            // Import CSS
        var css = require("text!./style.css");    
        
        var markup = require("text!./panel.xml");
        var Tree = require("ace_tree/tree");
        var ListData = require("./dataprovider");

	var stat_autotest = {
		"1"         	: "OK", 
		"2"           	: "Ne postoji funkcija", 
		"3"           	: "Ne može se kompajlirati", 
		"4"        	: "Predugo izvršavanje", 
		"5"           	: "Testni program se krahira", 
		"6"           	: "Pogrešan rezultat", 
		"8"       	: "Nije pronađen rezultat.", 
		"201"       	: "Ne može se izvršiti", 
		"102"          	: "Memorijska greška", 
		"103"          	: "Nije inicijalizovano", 
		"104"         	: "Curenje memorije", 
		"105"    	: "Loša dealokacija", 
		"106" 		: "Pogrešan dealokator"
	};
        
        /***** Initialization *****/
        
        var plugin = new Panel("Ajax.org", main.consumes, {
            index: options.index || 300,
            caption: "Autotest",
            minWidth: 150,
            autohide: true,
            where: options.where || "right"
        });
        // var emit = plugin.getEmitter();
        
        var winCommands, testirajBtn, tree, ldSearch;
        var lastSearch, project_path, bsInstance, provjeraTimeouts, testSpecification, testResults, testAtResult;
	
	bsInstance={};
	provjeraTimeouts={};
        
        function load(){
            panels.on("afterAnimate", function(){
                if (panels.isActive("buildservice.panel"))
                    tree && tree.resize();
            });  
           
        }
        
        var drawn = false;
        function draw(options) {
            if (drawn) return;
            drawn = true;
            
            // Create UI elements
            ui.insertMarkup(options.aml, markup, plugin);
	    
            ui.insertCss(css, options.staticPrefix, plugin);
             
            var treeParent = plugin.getElement("buildserviceList");
            //txtFilter = plugin.getElement("txtFilter");
	    testirajBtn = new ui.button({ 
		id       : "testirajBtn",
		class    : "testirajBtn",
                    caption: "Testiraj", 
                    height: 24,
                    skin: "c9-toolbarbutton-glossy",
                    icon: "../../etf.buildservice/images/ok.png",
                    onclick: function() {
                        pokreniTestiranje();
                    }
                }); 
            var barStools = plugin.getElement("barTools");
	    barStools.appendChild(testirajBtn);

            winCommands = options.aml;

            // Create the Ace Tree
            tree = new Tree(treeParent.$int);
            ldSearch = new ListData(commands, tabs);
	    console.log(tree);
	    console.log(ldSearch);
            
            tree.renderer.setScrollMargin(0, 10);

            // @TODO this is probably not sufficient
            layout.on("resize", function(){ tree.resize() }, plugin);
            
            function forwardToTree() {
                tree.execCommand(this.name);
            }
            
            tree.on("click", function(ev) {
                var e = ev.domEvent;
                if (!e.shiftKey && !e.metaKey  && !e.ctrlKey  && !e.altKey)
                if (tree.selection.getSelectedNodes().length === 1)
                    execCommand(true);
            });
            
            function onblur(e) {
                if (!winCommands.visible)
                    return;
                
                var to = e.toElement;
                if (!to || apf.isChildOf(winCommands, to, true))
                    return;
                
                // TODO add better support for overlay panels
                setTimeout(function(){ plugin.hide() }, 10);
            }
    
            apf.addEventListener("movefocus", onblur);

            setTimeout(function(){
                // Assign the dataprovider
                tree.setDataProvider(ldSearch);
                tree.selection.$wrapAround = true;
                var val = settings.get("state/commandPanel/@value");
                /*if (val)
                    txtFilter.ace.setValue(val);*/
            }, 200);
        }
        
        /***** Methods *****/
    
	// Put do trenutno otvorenog fajla
        function findTabToRun(){
		var path = tabs.focussedTab && tabs.focussedTab.path;
		if (path) return path.replace(/^\//, "");

		var foundActive;
		if (tabs.getPanes().every(function(pane) {
			var tab = pane.activeTab;
			if (tab && tab.path) {
				if (foundActive) return false;
				foundActive = tab;
			}
			return true;
		}) && foundActive) {
			return foundActive.path.replace(/^\//, "");
		}

		return false;
	}
        
	// Funkcije za uključenje/isključenje dugmeta "Testiraj"
	function disableButton() {
		testirajBtn.disabled = true;
		//testirajBtn.caption = "Nije moguće testiranje";
	}
	
	function enableButton() {
		testirajBtn.disabled = false;
		//testirajBtn.caption = "Testiraj";
	}

	// Ova funkcija će biti pozvana na show() eventu da prikaže trenutno stanje
	function ucitajPodatke(){
		disableButton();
		path = findTabToRun() || "";
		if (!path || path.trim() == "") {
			//ldSearch.keyword = "Nije izabran projekat";
			ldSearch.addORItem({label : "", desc : "Nije izabran projekat"});
			return;
		}
		project_path = path.substring(0,path.lastIndexOf("/"));
		
		console.log("ucitajPodatke: '"+project_path+"'");
		autotest_path = project_path+ "/.autotest";
		ldSearch.updateData([]);
		fs.exists(autotest_path, function(file_exists) {
			if (file_exists) {
				enableButton();
				
				atresult_path = project_path + "/.at_result";
				fs.exists(atresult_path, function(file_exists) {
					if (file_exists) {
						console.log(".at_result file postoji");
						populate(atresult_path);
					} else if (project_path in bsInstance && bsInstance[project_path] != "") {
						provjeriTest();
					} else {
						//ldSearch.keyword = "Ovaj projekat nikada<br>\nnije testiran";
						ldSearch.addORItem({label : "", desc : "Ovaj projekat nikada<br>\nnije testiran"});
					}
					ldSearch.updateData(false);
				});
				
				// Asinhrono ćemo učitati specifikaciju testova u atribut
				fs.readFile(autotest_path, function(err, content) {
					if (err) { showError("Neuspjelo učitavanje specifikacije testova"); return console.error(err); }
					testSpecification = JSON.parse(content);
				});
			} else {
				//ldSearch.keyword = "Nisu definisani testovi<br>\n za ovaj projekat";
				ldSearch.addORItem({label : "", desc : "Nisu definisani testovi<br>\n za ovaj projekat"});
				ldSearch.updateData(false);
			}
		});
	}
	
	// Funkcija čita podatke iz .at_result fajla datog kao parametar i prikazuje
	function populate(file) {
		testResults = {};

		fs.readFile(file, function(err, content) {
			if (err) { 
				//ldSearch.keyword = "Neuspjelo čitanje datoteke<br>\nsa rezultatima testiranja"; 
				ldSearch.addORItem({label : "", desc : "Neuspjelo čitanje datoteke<br>\nsa rezultatima testiranja"});
				return console.error(err); 
			}
			rezultati = JSON.parse(content);
			console.log(rezultati);
			
			// Lijepo formatirano vrijeme posljednjeg testa
			vr = new Date(rezultati.time * 1000);
			
			vrtext = vr.getDate() + ". " + (vr.getMonth()+1) + ". " + vr.getFullYear() + " ";
			if (vr.getHours()<10) vrtext += "0";
			vrtext += vr.getHours() + ":";
			if (vr.getMinutes()<10) vrtext += "0";
			vrtext += vr.getMinutes() + ":";
			if (vr.getSeconds()<10) vrtext += "0";
			vrtext +=  vr.getSeconds();
			
			ldSearch.addORItem({label : "", desc : "Posljednji test:<br>"+vrtext});
			
			// Situacije grešaka
			if ('code' in rezultati && rezultati.code.substring(0,3) == "ERR") {
				ldSearch.addORItem({label : "", desc : "Greška prilikom testiranja<br>" + rezultati.msg});
				ldSearch.updateData(false);
				return;
			}
			if ('compile_result' in rezultati && 'status' in rezultati.compile_result && rezultati.compile_result.status == 2) {
				ldSearch.addORItem({label : "", desc : "Program se ne može<br>kompajlirati"});
				ldSearch.updateData(false);
				return;
			}
			
			// Iscrtavamo widgete za pojedinačni test
			i = 1;
			testResults = rezultati.test_results;
			testAtResult = rezultati;
			
			for(test_id in testResults) {
				test = testResults[test_id]
				status = test.status;
				if (test.status == "7") {
					status = test.profile_result.status + 100;
				}
				ldSearch.addORItem({label : "Test "+i, desc : stat_autotest[status] });
				i++;
			}
			
			// Forsiramo refresh
			ldSearch.updateData(false);
		});
	}
	
	// Ova funkcija se pokreće svakih 0.5 sekundi da provjerimo da li ima rezultata testiranja
	function provjeriTest() {
		console.log("provjeriTest()");
		// Da li je već kreiran .at_result fajl?
		atresult_path = project_path + "/.at_result";
		fs.exists(atresult_path, function(file_exists) {
			if (file_exists) {
				// Sve ok
				enableButton();
				bsInstance[project_path] = "";
				provjeraTimeouts[project_path] = "";
				populate(atresult_path);
				return;
			} 
			
			// Ako nije definisan atribut bsInstance onda ne znamo šta da tražimo
			if (!(project_path in bsInstance) || bsInstance[project_path] == "") {
				//ldSearch.keyword = false;
				//ldSearch.updateData(false);
				//enableButton();
				//setTimeout(provjeriTest, 500);
				
				provjeraTimeouts[project_path] = "";
				ucitajPodatke();
				return;
			}
			
			// AJAXom pozivamo web servis getProgramStatus
			var xmlhttp = new XMLHttpRequest();
			var url = "/buildservice/push.php?action=getProgramStatus&program="+bsInstance[project_path];
			xmlhttp.onreadystatechange = function() {
				if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
					result = JSON.parse(xmlhttp.responseText);
					if (result.success == "true") {
						// result.status ima isti format kao inače rezultati testova
						totalTests = testSpecification.test_specifications.length;
						var status = result.status;
						ldSearch.clearItems();
						
						if ('status' in status && status.status == 1) {
							var msg = "Program čeka na testiranje.";
							if ('queue_items' in status)
								msg += "<br>" + (status.queue_items+1) + " drugih zahtjeva je ispred";
							ldSearch.addORItem({label : "", desc : msg});
							ldSearch.updateData(false);
							provjeraTimeouts[project_path] = setTimeout(provjeriTest, 500);
							return;
						}
						
						if ('status' in status && status.status != 1 && status.status != 7) {
							fs.writeFile(atresult_path, JSON.stringify(status, null, 4), function(err){ 
								if (err) console.log(err);
							}
							);
						}
						
						// Utvrđujemo da li se program uopšte nije uspio kompajlirati
						if ('compile_result' in status && 'status' in status.compile_result) {
							if (status.compile_result.status == 2) {
								var msg = "Testiranje u toku.<br>Program se ne može kompajlirati";
								msg += "<br><br>Da saznate zašto, kliknite na<br>dugme Run.";
								ldSearch.addORItem({label : "", desc : msg});
								ldSearch.updateData(false);
								provjeraTimeouts[project_path] = setTimeout(provjeriTest, 500);
								return;
							}
						}
							
						// Kada se testovi tek pokrenu, objekat uopšte neće imati ovaj član
						if ('test_results' in status)
							// Zahtijeva IE9+, FF4+, Safari 5+
							finishedTests = Object.keys(status.test_results).length;
						else
							finishedTests = 0;
						
						//ldSearch.keyword = "Testiranje u toku.<br>Završeno "+finishedTests+" od "+totalTests+" testova";
						ldSearch.addORItem({label : "", desc : "Testiranje u toku.<br>Završeno "+finishedTests+" od "+totalTests+" testova"});
						ldSearch.updateData(false);
						
						provjeraTimeouts[project_path] = setTimeout(provjeriTest, 500);
					} else {
						ldSearch.clearItems();
						// Jedina mogućnost je da instanca ne postoji, odnosno da se testiranje završilo u međuvremenu
						provjeraTimeouts[project_path] = setTimeout(provjeriTest, 500);
					}
				}
				if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
					showError("Greška na serveru.");
					provjeraTimeouts[project_path] = setTimeout(provjeriTest, 500);
				}
			}
			xmlhttp.open("GET", url, true);
			xmlhttp.send();
		});
	}

	// Funkcija koja se pokreće klikom na dugme Testiraj
	function pokreniTestiranje() {
		console.log("pokrećem testiranje");
		atresult_path = project_path + "/.at_result";
		
		// Da li je već u toku testiranje?
		if (project_path in bsInstance && bsInstance[project_path] != "") {
			provjeriTest();
			return;
		}
		
		// Brišemo stari .at_result
		fs.exists(atresult_path, function(file_exists) {
			if (file_exists) {
				fs.unlink(atresult_path, function(err) {
					if (err) { console.log("Nije uspjelo brisanje .at_result... vjerovatno će rezultati biti zastarjeli"); return console.error(err); }
				});
			}
		});
		
		// Šaljemo AJAX zahtjev za submit zadatka na buildservice
		var xmlhttp = new XMLHttpRequest();
		var url = "/buildservice/submit_c9.php?filename="+project_path;
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				result = JSON.parse(xmlhttp.responseText);
				if (result.success == "true") {
					showInfo("Pokrenuto testiranje", 3000);
					disableButton();
					ldSearch.keyword = "Testiranje u toku.";
					ldSearch.updateData([]);
					bsInstance[project_path] = result.instance;
					provjeraTimeouts[project_path] = setTimeout(provjeriTest, 500);
				} else {
					showError("Došlo je do greške: " + result.message);
					console.log(result);
				}
			}
			else if (xmlhttp.readyState == 4) {
				showError("Greška na serveru. Pokušajte ponovo kasnije");
				console.log("URL: "+url+" status: "+xmlhttp.status);
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();		
	}
	
	
        /**
         * Searches through the dataset
         *
         */
        function filter(keyword, nosel) {
            keyword = keyword.replace(/\*/g, "");
    
            // Needed for highlighting
            ldSearch.keyword = keyword;
            
            var names = Object.keys(commands.commands);
            
            var searchResults;
            if (!keyword) {
                searchResults = names;
            }
            else {
                tree.provider.setScrollTop(0);
                searchResults = search.fileSearch(names, keyword);
            }
    
            lastSearch = keyword;
    
            if (searchResults)
                ldSearch.updateData(searchResults);
                
            if (nosel || !searchResults.length)
                return;
    
            // select the first item in the list
            tree.select(tree.provider.getNodeAtIndex(0));
        }
        
        // Akcija za klik na rezultat testa, generiše ispis
        function execCommand(noanim, nohide) {
            var nodes = tree.selection.getSelectedNodes();
            // var cursor = tree.selection.getCursor();
    
            nohide || plugin.hide();
            
            for (var k = 0, l = nodes.length; k < l; k++) {
		//var name = nodes[i].id;
		//commands.exec(name);
		
		var item = nodes[k].id;
		console.log("FAJL: "+item.label)
		atresult_path = project_path + "/.at_result";
		i = 0;
		
		for(test_id in testResults) {
			testResult = testResults[test_id]
			i++;
			var testname = "Test " + i;
//			console.log("Uporedjujem '" + item.label + "' sa '"+testname+"'");
			
			if (item.label == testname) {
					var tests = JSON.stringify(testSpecification);
					var test_results = JSON.stringify(testAtResult);
					
					var form = document.createElement("form");
					form.setAttribute("method", "post");
					form.setAttribute("action", "https://c9.etf.unsa.ba/buildservice/render_result.php");

					form.setAttribute("target", "view");

					var hiddenField = document.createElement("input"); 
					hiddenField.setAttribute("type", "hidden");
					hiddenField.setAttribute("name", "tests");
					hiddenField.setAttribute("value", tests);
					form.appendChild(hiddenField);
					var hiddenField1 = document.createElement("input"); 
					hiddenField1.setAttribute("type", "hidden");
					hiddenField1.setAttribute("name", "test_results");
					hiddenField1.setAttribute("value", test_results);
					form.appendChild(hiddenField1);
					var hiddenField2 = document.createElement("input"); 
					hiddenField2.setAttribute("type", "hidden");
					hiddenField2.setAttribute("name", "test_id");
					hiddenField2.setAttribute("value", test_id);
					form.appendChild(hiddenField2);
					document.body.appendChild(form);

					window.open('', 'view', 'toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=0,width=700,height=700,left=312,top=234');
					form.submit();
					break;
				
				// Nalazimo specifikaciju
				var i=0;
				var testSpec;
				for (i=0; i<testSpecification.test_specifications.length; i++) {
					testSpec = testSpecification.test_specifications[i];
					if (testSpec.id == test_id) break;
				}
				
				var content = "Rezultati testa: "+testname;
				content += "\n\n";
				
				status = testResult.status;
				if (testResult.status == "7") {
					status = testResult.profile_result.status + 100;
				}
				content += "STATUS: " + stat_autotest[status] + "\n";
				if (testResult.status == "5") {
					content += "Lokacija krahiranja:\n";
					for (i=0; i<testResult.debug_result.parsed_output.length; i++) {
						debugging = testResult.debug_result.parsed_output[i];
						content += "- Fajl: " + debugging.file + " Linija: " + debugging.line + "\n";
					}
				}
				if (testResult.status == "8") {
					content += "Mogući uzroci: testni program se krahira, pozvana funkcija exit()\n";
				}
				content += "\n";

				if ('parsed_output' in testResult.profile_result && testResult.profile_result.parsed_output.length > 0) {
					content += "PROFILERSKE GREŠKE:\n";
					for (i=0; i<testResult.profile_result.parsed_output.length; i++) {
						profiling = testResult.profile_result.parsed_output[i];
						content += "- Fajl: " + profiling.file + " Linija: " + profiling.line + ": " + stat_autotest[profiling.type+100] + "\n";
					}
					content += "\n";
				}

				
				function bn(s) { if (s) return s.replace(/\\n/g, "\n"); else return ""; }
				
				content += "Kod testa:\n" + bn(testSpec.code) + "\n\n";
				if ('global_top' in testSpec && testSpec.global_top != "")
					content += "U globalnom opsegu:\n" + bn(testSpec.global_top) + "\n\n";
				if ('global_above_main' in testSpec && testSpec.global_above_main != "")
					content += "U globalnom opsegu:\n" + bn(testSpec.global_above_main) + "\n\n";
				
				if ('running_params' in testSpec && 'stdin' in testSpec.running_params && testSpec.running_params.stdin != "")
					content += "Standardni ulaz programa:\n" + bn(testSpec.running_params.stdin) + "\n\n";
				
				content += "Očekivani izlaz programa:\n";
				for (i=0; i<testSpec.expected.length; i++) {
					content += bn(testSpec.expected[i]) + "\n\n";
					if (i<testSpec.expected.length - 1)
						content += "Alternativno:\n";
				}
				
				content += "Program je ispisao:\n";
				content += bn(testResult.run_result.output) + "\n\n";
				if (testResult.run_result.status == 2)
					content += "PROGRAM JE RADIO PREDUGO\n\n";
				if (testResult.run_result.status == 3)
					content += "PROGRAM SE KRAHIRAO\n\n";
				
				//console.log(content);
				
				tabs.open( { "title" : testname, "value" : content, "active" : true, "focus" : true}, function(err, tab) {
					if (err) { return console.error(err); }
					console.log("Kreiran tab");
					tab.title = testname;
				} );
				
				break;
			}
		}
            }
        }

        /***** Lifecycle *****/
        
        plugin.on("load", function(){
            load();
        });
        plugin.on("draw", function(e) {
            draw(e);
        });
        plugin.on("enable", function(){
            
        });
        plugin.on("disable", function(){
            
        });
        plugin.on("show", function(e) {
            //txtFilter.focus();
            //txtFilter.select();
		ucitajPodatke();
        });
        plugin.on("hide", function(e) {
		// Zaustavljamo timeout ako postoji
		if (project_path in provjeraTimeouts && provjeraTimeouts[project_path] != "") {
			clearTimeout(provjeraTimeouts[project_path]);
			provjeraTimeouts[project_path] = "";
		}
            // Cancel Preview
            tabs.preview({ cancel: true });
            tree.select("");
        });
        plugin.on("unload", function(){
            drawn = false;
        });
        
        /***** Register and define API *****/
        
        /**
         * Commands panel. Allows a user to find and execute commands by searching
         * for a fuzzy string that matches the name of the command.
         * @singleton
         * @extends Panel
         **/
        /**
         * @command commands
         */
        /**
         * Fires when the commands panel shows
         * @event showPanelCommand.panel
         * @member panels
         */
        /**
         * Fires when the commmands panel hides
         * @event hidePanelCommands.panel
         * @member panels
         */
        plugin.freezePublicAPI({
            /**
             * @property {Object}  The tree implementation
             * @private
             */
            get tree() { return tree; }
        });
        
        register(null, {
            "buildservice.panel": plugin
        });
    }
});
