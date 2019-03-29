/* etf.zamger plugin for Cloud9 - 7. 10. 2018. 18:36
 * 
 * @author Vedran Ljubovic <vljubovic AT etf DOT unsa DOT ba>
 * 
 * Adds "Send Homework" button to toolbar that sends tasks designated as
 * homework to Zamger IS.
 */

define(function(require, module, exports) {
    main.consumes = [
        "c9", "Plugin", "commands", "layout", "tabManager", "ui", "fs",
        "layout", "dialog.error", "dialog.info",  "dialog.alert"
    ];
    main.provides = ["zamger.gui"];
    return main;

    function main(options, imports, register) {
        var Plugin = imports.Plugin;
        var commands = imports.commands;
        var c9 = imports.c9;
        var ui = imports.ui;
        var fs = imports.fs;
        var layout = imports.layout;
        var tabs = imports.tabManager;
        var showError = imports["dialog.error"].show;
        var showInfo = imports["dialog.info"].show;
        var showAlert = imports["dialog.alert"].show;

        /***** Initialization *****/

        var plugin = new Plugin("Ajax.org", main.consumes);
        var emit = plugin.getEmitter();

        var btnZamger;

        var loaded = false;
        function load(){
            if (loaded) return false;
            loaded = true;

            commands.addCommand({
                name: "saljizadacu",
                group: "Zamger",
                "hint"  : "pošalji zadatak na zamger",
                exec: function(editor, args) {
                    saljiZadacu();
                }
            }, plugin);
            
            setTimeout(zamgerPing, 60000);

            // Draw
            draw();
        }
        
        function zamgerPing() {
		var xmlhttp = new XMLHttpRequest();
		var url = "/zamger/ping.php";
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				if (xmlhttp.responseText.substring(0,10) === "BROADCAST:")
					showInfo(xmlhttp.responseText.substring(11));
				console.log("Ping.php: " + xmlhttp.responseText);
			}
			if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
				console.error("Ping.php: Internal server error");
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
		setTimeout(zamgerPing, 60000);
	}

        var drawn = false;
        function draw(){
            if (drawn) return;
            drawn = true;

            // Menus
            btnZamger = ui.insertByIndex(layout.findParent(plugin),
              new ui.button({
                id: "btnZamger",
                skin: "c9-toolbarbutton-glossy",
                command: "saljizadacu",
                caption: "Pošalji zadatak",
                disabled: true,
                class: "zamgerbtn running",
                icon: "mccloud.png",
            }), 100, plugin);
	    btnZamger.enable();

            emit("draw");
        }

        /***** Helper Methods *****/

        /***** Methods *****/

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

        function saljiZadacu() {
            path = findTabToRun() || "";
	    folder = path.substring(0,path.lastIndexOf("/")+1);
	    fs.exists("/"+folder + "/.zadaca", function(file_exists) {
		if (!file_exists) {
			showError("Trenutno izabrani projekat nije zadaća");
			return;
		}
		fs.readFile("/"+folder + "/.zadaca", function (err, data) {
			if (err) {
				showError("Nije uspjelo otvaranje datoteke sa podacima o zadaći");
				return;
			}
			data = data.replace(/(\"naziv\" : \".*?\"),/, "$1");
			console.log(data);
			zadaca = JSON.parse(data);
			console.log(zadaca);
			var xmlhttp = new XMLHttpRequest();
			var url = "/zamger/slanje_zadace.php?zadaca="+zadaca.id+"&zadatak="+zadaca.zadatak+"&filename="+path;
			xmlhttp.onreadystatechange = function() {
				if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
					if (xmlhttp.responseText == "Ok.")
						showInfo("Zadaća poslana", 3000);
					else if (xmlhttp.responseText == "GRESKA: Istekla sesija")
						showError("Istekla sesija na Zamgeru. Napravite logout pa login");
					else if (xmlhttp.responseText == "GRESKA: Niste student")
						showError("Niste upisani na predmet kojem pripada ova zadaća");
					else {
						showError("Nije uspjelo slanje zadaće "+xmlhttp.responseText);
						console.log(xmlhttp.responseText);
					}
				}
				if (xmlhttp.readyState == 4 && xmlhttp.status == 500) {
					showError("Greška na serveru. Kontaktirajte tutora");
				}
			}
			xmlhttp.open("GET", url, true);
			xmlhttp.send();
		});
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

            /**
             *
             */
            //transformButton: transformButton
        });

        register(null, {
            "zamger.gui": plugin
        });
    }
});
