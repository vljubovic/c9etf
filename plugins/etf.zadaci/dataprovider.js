define(function(require, exports, module) {
    "use strict";
    
    var oop = require("ace/lib/oop");
    var Base = require("ace_tree/data_provider");
    
    var TreeData = function(root) {
        Base.call(this, root || {});
        
        // todo compute these automatically
        this.innerRowHeight = 20;
        this.rowHeight = 22;
        
        Object.defineProperty(this, "loaded", {
            get: function(){ return this.visibleItems.length; }
        });
    };
    oop.inherits(TreeData, Base);
    (function() {
        this.$sortNodes = false;
        
        var cache;
        this.updateData = function(subset) {
            this.visibleItems = subset || this.visibleItems;
            this._signal("change");
        };


        this.getEmptyMessage = function(){
            if (!this.keyword)
                return "Učitavam spisak zadataka,<br> molimo sačekajte par sekundi...";
            else
                return "Došlo je do greške prilikom<br>\n učitavanja spiska zadataka:<br>\n " + this.keyword + "<br>\nProbajte logout pa login.";
        };

    }).call(TreeData.prototype);
    
    return TreeData;
});
