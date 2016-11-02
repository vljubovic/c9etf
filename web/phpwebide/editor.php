<?php

function pwi_editor($username, $cur_path, $editable) {
	global $conf_base_path;
	
	$svn_user_path = setup_paths($username)['svn'];

	$content = "";
	$ace_mode = "c_cpp"; // fixme
	
	$isdir = `sudo $conf_base_path/bin/wsaccess $username isdir "$cur_path"`;
	
	if (isset($_REQUEST['svn_rev'])) {
		$rev = intval($_REQUEST['svn_rev']);
		$svn_file_path = "file://" . $svn_user_path . "/$cur_path";
		$content = htmlspecialchars(svn_cat($svn_file_path, $rev));
	}
	else if (isset($_REQUEST['git_rev'])) {
		$rev = $_REQUEST['git_rev'];
		$content = htmlspecialchars(`sudo $conf_base_path/bin/wsaccess $username git-show "$cur_path" "$rev"`);
	}
	else if ($isdir != 1) {
		$content = htmlspecialchars(`sudo $conf_base_path/bin/wsaccess $username read "$cur_path"`);
	}

	?>
	<div id="editor"><?=$content?></div>
	<div id="status"></div>

	<script src="https://zamger.etf.unsa.ba/js/ace/ace.js" type="text/javascript" charset="utf-8"></script>
	<script>
	    var editor = ace.edit("editor");
	    //editor.setTheme("ace/theme/monokai");
	    editor.getSession().setMode("ace/mode/<?=$ace_mode?>");

	    console.log(window.innerHeight);
	    console.log(document.getElementById('editor').style.top); // Zašto ne radi???
	    console.log(document.getElementById('phpwebide_tree').clientHeight);
	    var newbottom = window.innerHeight - 220 - document.getElementById('phpwebide_tree').clientHeight;
	    console.log(newbottom);
	    newbottom = "" + newbottom;
	    newbottom = newbottom + "px";
    	    document.getElementById('editor').style.bottom = newbottom;

	    
	    editor.focus();
	    

	<?php
	if (!$editable) { ?>
		editor.setOptions({
			readOnly: true,
			highlightActiveLine: false,
			highlightGutterLine: false
		})
		editor.renderer.$cursorLayer.element.style.opacity=0
		editor.textInput.getElement().tabIndex=-1
		editor.commands.commmandKeyBinding={}
		<?php
	} else {
		?>

	    editor.getSession().on("change", scheduleSave);

	    var timeout, has_timeout=0;


	    function scheduleSave(e) {
	    	//alert("hello");
	    	if (has_timeout) clearTimeout(timeout);
	    	timeout = setTimeout('doSave()', 5000);
	    	has_timeout = 1;
	    }

	    function doSave() {
	    	has_timeout = 0;
			var mypostrequest=new ajaxRequest()
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

			var sta = encodeURIComponent('<?=$_REQUEST['sta']?>');
			var akcija = encodeURIComponent("slanje");
			var student = encodeURIComponent(<?=$student?>);
			var zadaca = encodeURIComponent(<?=$zadaca?>);
			var zadatak = encodeURIComponent(<?=$zadatak?>);
			var projekat = encodeURIComponent(<?=$projekat?>);

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
	<?php
	}
	print "</script>\n";
}

?>