/* etf.annotate plugin for Cloud9 - 23. 04. 2016
 * 
 * @author Vedran Ljubovic <vljubovic AT etf DOT unsa DOT ba>
 * 
 * Displays annotations in code for gcc, gdb and valgrind messages.
 * This is achieved with a special runner that redirects output of these
 * commands into hidden text files. Plugin sets up watches that wait for
 * these files, then uses buildservice to parse their output and insert 
 * annotations into code editor.
 */

// TODO: see about dir watches, workspace path hardcoded (with /rhome)

define(function(require, module, exports) {
    main.consumes = [
        "c9", "Plugin", "run", "layout", "tabManager", "ui", "fs", "proc",
        "layout", "dialog.error", "dialog.info",  "dialog.alert"
    ];
    main.provides = ["etf.annotate"];
    return main;

    function main(options, imports, register) {
	var Plugin = imports.Plugin;
        var run = imports.run;
        var c9 = imports.c9;
        var ui = imports.ui;
        var fs = imports.fs;
        var layout = imports.layout;
        var tabs = imports.tabManager;
        var proc = imports.proc;
        var showError = imports["dialog.error"].show;
        var showInfo = imports["dialog.info"].show;
        var showAlert = imports["dialog.alert"].show;

        /***** Initialization *****/

        var plugin = new Plugin("Ajax.org", main.consumes);
        var emit = plugin.getEmitter();
	
        var watches = [];
	var failedWatches = [];
	var watchedFiles = [ ".gcc.out", ".gdb.out", ".valgrind.out" ];
	var bs_path = "/usr/local/webide/web/buildservice";
	var username;
	var annotations = {};
	var longTime;
	
	var waitToParseoutput = 1000;
	var waitToRewatch = 1000;
	var waitToRetryFailed = 60000;

        var loaded = false;
        function load(){
            if (loaded) return false;
            loaded = true;
            
            tabs.on("open", tabOpened); 
            tabs.on("tabDestroy", tabClosed);
	    
	    // TODO This is ugly but I don't see other way right now
	    // It's impossible to watch nonexistant files and watching a directory
	    // creates so many events that ide slows down terribly
		setTimeout(function() { retryFailedWatches(); }, waitToRetryFailed);
	    
		proc.execFile("whoami", function(err, stdout, stderr) {
			if (err) {
				console.log("Failed to run whoami");
				console.log(err);
				return;
			}
			username=stdout.trim();
		});
		longTime = true;

            // Draw
            draw();
        }

        var drawn = false;
        function draw(){
            if (drawn) return;
            drawn = true;

            emit("draw");
        }

        /***** Helper Methods *****/
	
	// Get parent directory from file path
	function basename(filepath) {
		var pos=-1;
		while (filepath.indexOf("/", pos+1) != -1)
			pos = filepath.indexOf("/", pos+1);
		return filepath.substring(0, pos+1);
	}

	// Find source file in path
	function findsrc(filepath) {
		var path = basename(filepath);
		
		// Look at all open tabs and find in given path
		tttabs = tabs.getTabs();
		for(i=0; i<tttabs.length; i++) {
			if (typeof tttabs[i].path === "undefined") continue;
			var tpath = tttabs[i].path;
			if (tpath.substring(0, path.length) == path)
				return tttabs[i].path;
		}
		
		// FIXME
		console.log("annotate.js - findsrc - no tab opened for "+path);
		return path + "/main.cpp";
	}

        /***** Methods *****/
	
	function tabOpened(e) {
		console.log("tabOpened ");
		if (typeof e.tab.path === "undefined")
			return;
		var path = basename(e.tab.path);
		console.log("Path: "+path);
		
		// Watch all files in path
		for (i=0; i<watchedFiles.length; i++) {
			console.log("Watching "+path+watchedFiles[i]);
			setupWatch(path, watchedFiles[i]);
		}
	}
	
	
	function tabClosed(e) {
		console.log("tabClosed");
		if (typeof e.tab.path === "undefined")
			return;
		var path = basename(e.tab.path);
		console.log("Path: "+path);
		
		// Unwatch all watched files
		for (i=0; i<watchedFiles.length; i++) {
			var filename = path + watchedFiles[i];
			var pos = failedWatches.indexOf(filename);
			if (pos == -1) {
				console.log("Unwatching "+filename);
				fs.unwatch(filename, function(err, event, fn) {
					var filepath = path + fn;
					if (err) {
						console.log("Error unwatching file " + filepath);
						console.log(err);
					}
				});
			} else {
				failedWatches.splice(pos, 1);
			}
		}
		
		/*fs.unwatch(path, function(err, event, fn) {
			if (err) {
				console.log("Error unwatching dir " + path);
				console.log(err);
			}
		});*/
	}
        
	// Setup a single watch (monitor for file change)
	function setupWatch(path, filename) {
		console.log("Setup watch "+path+filename);
		var filepath = path + filename;
		fs.exists(filepath, function(file_exists) {
			if (!file_exists) {
				console.log("File doesn't exist " + filepath);
				failedWatches.push(filepath);
				return;
			}
			fs.watch(filepath, function(err, event, fn) {
				// Watch code will be executed sometimes...
				if (err) {
					// Failed to watch file, probably doesn't exist yet
					// We will retry when directory changes to see if file appeared
					
					// fn will be undefined so we have to extract filename 
					// from error message :(
					var fn = filepath;
					var pos1 = err.message.indexOf(path);
					if (pos1 != -1) {
						var pos2 = err.message.indexOf("'", pos1);
						fn = err.message.substring(pos1, pos2);
					}
					
					console.log("Error watching file " + fn);
					//console.log(err);
					failedWatches.push(fn);
					return;
				}
				var mfilepath = path + fn;
				if (event == "change" && typeof watches[mfilepath] === "undefined") {
					// Delay so that file can be written etc.
					setTimeout(function() { parseFile(mfilepath); }, waitToParseoutput);
					watches[mfilepath] = true;
				}
			});
		});
	}
	
	function retryFailedWatches() {
		var retryWatches = failedWatches;
		failedWatches = [];
		for (i=0; i<retryWatches.length; i++) {
			var path = basename(retryWatches[i]);
			var filename = retryWatches[i].substring(path.length);
			setupWatch(path, filename);
		}
		setTimeout(function() { retryFailedWatches(); }, waitToRetryFailed);
		
		longTime = true;
	}
	
	/*// Watch changes on dir in case new files appear
	function setupDirWatch(path) {
		fs.watch(path, function(err, event, fn) {
			if (err) {
				console.log("Error watching dir " + path);
				console.log(err);
				return;
			}
			console.log("Dir watch event "+event);
			
			for (key in annotations) {
				if (typeof key === "undefined") continue;
			 console.log("Key "+key);
				if (basename(key) == path) {
					console.log("Clearing annotations");
					annotations[key] = [];
				}
			}
			
			var retryWatches = [];
			for (i=0; i<failedWatches.length; i++) {
				if (failedWatches[i].substring(0, path.length) == path) {
					retryWatches.push( failedWatches[i] );
					failedWatches.splice(i, 1);
					i--;
				}
			}
			for (i=0; i<retryWatches.length; i++) {
				console.log("Retry watching " + retryWatches[i]);
				setupWatch(path, retryWatches[i].substring(path.length) );
			}
			
			if (event !== "init") setTimeout(function() { setupDirWatch(path); }, waitToRewatch);
		});
	}*/
 
        /***** Parse output files *****/
	
	function parseFile(filepath) {
		console.log("------------- ParseFile "+filepath);
		var path = basename(filepath);
		filename = filepath.substring(path.length);
		watches[filepath] = undefined;

		// Clear annotations
		if (longTime) {
			for (key in annotations) {
				if (typeof key === "undefined") continue;
				if (basename(key) == path) {
					annotations[key] = [];
				}
			}
			longTime=false;
		}
		if (filename == ".gcc.out") 
			parseFileGcc(filepath, updateAnnotations);
		else if (filename == ".gdb.out")
			parseFileGdb(filepath, updateAnnotations);
		else if (filename == ".valgrind.out")
			parseFileValgrind(filepath, updateAnnotations);
		else
			console.log("Don't know how to parse file "+filename);
		
		// Watch is cleared after signal event, so we need to recreate it!
		setTimeout(function() { setupWatch(path, filename); }, waitToRewatch);
	}
	
	// Update annotations for all tabs belonging to path
	function updateAnnotations(filepath) {
		var path = basename(filepath);
		tttabs = tabs.getTabs();
		for(i=0; i<tttabs.length; i++) {
			if (typeof tttabs[i].path === "undefined") continue;
			var tpath = tttabs[i].path;
			if (tpath.substring(0, path.length) != path) continue;
			console.log("Annotating tab "+tpath);
       
			tttabs[i].document.getSession().session.setAnnotations( annotations[tpath] );
		}
	}
	
	function parseFileGcc(filepath, callback) {
		var srcFile = findsrc(filepath);
		var realpath = "/rhome/"+username[0]+"/"+username+"/workspace"; // FIXME
		
		var cmd = "/usr/bin/php";
		var gccargs = [ "parse_output.php", "-c", "gcc", realpath+filepath, realpath+srcFile ];
		proc.execFile(cmd, { cwd: bs_path, args: gccargs  }, function(err, stdout, stderr) {
			if (err) {
				console.log("Failed to run parse_output -c gcc " + realpath+filepath);
				console.log(err);
				return;
			}
			
			result = JSON.parse(stdout);
			var firstError=false, errorText = "";
			for (i=0; i<result.length; i++) {
				if (typeof result[i] === "undefined" || typeof result[i].file === "undefined")
					continue;
				var file = result[i].file.substring( realpath.length );
				var annotation = {
					"type"  : result[i].type,
					"row"   : result[i].line-1,
					"column": result[i].col,
					"text"  : result[i].message
				};
				if (typeof annotations[file] === "undefined") annotations[file] = [];
				annotations[file].push(annotation);

				if (result[i].type == "error" && firstError == false) {
					firstError = result[i].line;
					errorText  = result[i].message;
				}
			      
				console.log("Adding annotation "+result[i].type+" ("+result[i].line+")");
				//console.log(annotation);
			}
			
			if (firstError !== false)
				showAlert(
					"Kompajlerska greška",
					"Vaš program se nije kompajlirao jer imate grešku u liniji "+firstError,
					errorText
				);
			
			callback(filepath);
			setTimeout(function() { longTime=true; }, waitToRewatch);
		});
	}

	function parseFileGdb(filepath, callback) {
		var srcFile = findsrc(filepath);
		var realpath = "/rhome/"+username[0]+"/"+username+"/workspace"; // FIXME
		
		var cmd = "/usr/bin/php";
		var gccargs = [ "parse_output.php", "-d", "gdb", realpath+filepath, realpath+srcFile ];
		proc.execFile(cmd, { cwd: bs_path, args: gccargs  }, function(err, stdout, stderr) {
			if (err) {
				console.log("Failed to run parse_output -d gdb " + realpath+filepath);
				console.log(err);
				return;
			}
			
			result = JSON.parse(stdout);
			var firstError=false;
			for (i=0; i<result.length; i++) {
				if (typeof result[i] === "undefined" || typeof result[i].file === "undefined")
					continue;
				var file = result[i].file.substring( realpath.length );
				var annotation = {
					"type"  : "error",
					"row"   : result[i].line-1,
					"column": 1,
					"text"  : "krahiranje"
				};
				if (typeof annotations[file] === "undefined") annotations[file] = [];
				annotations[file].push(annotation);

				if (firstError == false) {
					firstError = result[i].line;
				}
			      
				console.log("Adding annotation crash ("+result[i].line+")");
				//console.log(annotation);
			}
			
			if (firstError !== false)
				showAlert(
					"Krahiranje",
					"Vaš program se krahirao u liniji "+firstError,
					"Više informacija možete saznati koristeći debugger"
				);
			else 
				showAlert(
					"Krahiranje",
					"Vaš program se krahirao u nepoznatoj liniji",
					"Više informacija možete saznati koristeći debugger"
				);
			
			callback(filepath);
		});
	}

	function parseFileValgrind(filepath, callback) {
		var srcFile = findsrc(filepath);
		var path = basename(filepath);
		var realpath = "/rhome/"+username[0]+"/"+username+"/workspace"; // FIXME
		var valgrind_messages = [ "", "", "Memorijska greška", "Pristup neinicijalizovanoj vrijednosti", "Curenje memorije", "Loša dealokacija", 
		"Pogrešan dealokator" ];
		
		var cmd = "/usr/bin/php";
		var gccargs = [ "parse_output.php", "-p", "valgrind", realpath+filepath, realpath+srcFile ];
		proc.execFile(cmd, { cwd: bs_path, args: gccargs  }, function(err, stdout, stderr) {
			if (err) {
				console.log("Failed to run parse_output -p valgrind " + realpath+filepath);
				console.log(err);
				return;
			}
			
			result = JSON.parse(stdout);
			var firstError=false, errorText = "";
			for (i=0; i<result.length; i++) {
				if (typeof result[i] === "undefined" || typeof result[i].file === "undefined")
					continue;
				var file = path + result[i].file;
				var annotation = {
					"type"  : "info",
					"row"   : result[i].line-1,
					"column": 1,
					"text"  : valgrind_messages[result[i].type]
				};
				if (typeof annotations[file] === "undefined") annotations[file] = [];
				annotations[file].push(annotation);

				if (result[i].type == "error" && firstError == false) {
					firstError = result[i].line;
					errorText  = result[i].message;
				}
			      
				console.log("Adding annotation "+valgrind_messages[result[i].type]+" ("+result[i].line+") ("+file+")");
				//console.log(annotation);
			}
			
			callback(filepath);
		});
	}

        /***** Lifecycle *****/

        plugin.on("load", function(){
            load();
        });
        plugin.on("enable", function(){

        });
        plugin.on("disable", function(){

        });
        plugin.on("unload", function(){
            loaded = false;
            drawn = false;
        });

        /***** Register and define API *****/

        /**
         * UI for the {@link run} plugin. This plugin is responsible for the Run
         * menu in the main menu bar, as well as the settings and the
         * preferences UI for the run plugin.
         * @singleton
         */
        /**
         * @command run Runs the currently focussed tab.
         */
        /**
         * @command stop Stops the running process.
         */
        /**
         * @command runlast Stops the last run file
         */
        plugin.freezePublicAPI({
            get lastRun(){ return lastRun },
            set lastRun(lr){ lastRun = lr },

            /**
             *
             */
            //transformButton: transformButton
        });

        register(null, {
            "etf.annotate": plugin
        });
    }
});
