define(function(require, module, exports) {
    main.consumes = ["Panel", "ui", "tabManager"];
    main.provides = ["openpanel"];
    return main;
    
    function main(options, imports, register) {
        var Panel = imports.Panel;
        var ui = imports.ui;
        var manager = imports.tabManager;
        var editor; // = imports.tabManager.focussedTab.editor;
        
        // when the panel opens it should display search results for the highlighted text
        var ace; // = editor.ace;

        /***** Initialization *****/

        // create right-side panel 
        var plugin = new Panel("ETF Help", main.consumes, {
            index: 0,
            caption: "C++ reference",
            width: 800,
            minWidth: 800,
            where: "right",
            autohide: true
        });
        
        plugin.on("draw", function(e) {
                    editor = imports.tabManager.focussedTab.editor;
		    ace = editor.ace;
            var query = ace.getCopyText();
            ui.insertCss(require("text!./panel.css"), options.staticPrefix, plugin);

            e.html.innerHTML = "<div class='myCSS'><iframe src='https://en.cppreference.com/mwiki/index.php?title=Special%3ASearch&search=" 
            + query 
            + "' width='800' height='1000'/></iframe></div>";
        });
        
    
        // this will refresh the search bar 
        plugin.on("hide", function(e) {
            plugin.unload();
            plugin.load();
        });
        
        plugin.on("show", function(e) {
            plugin.draw();
        });
        
        /***** Register *****/

        plugin.freezePublicAPI({

        });
        
        plugin.setCommand({
            name: "togglepanel",
            hint: "toggles panel",
            bindKey: { mac: "Command-U", win: "Ctrl-I" }
        });

        register("", {
            "openpanel": plugin
        });
    }
});
