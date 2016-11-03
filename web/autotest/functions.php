<?php
	// Check session
	session_start();
	require_once("../../lib/config.php");
	require_once("../../lib/webidelib.php");
	require_once("../login.php");
	$login = $_SESSION['login'];
	if (!in_array($login, $conf_admin_users)) {
		print "<p><b><font color=\"red\">Access denied!</font></b></p>\n";
		exit(0);
	}
	
	$fileLastId="last_id.txt";
	$fileData=getVar("fileData");
	if (!file_exists($fileData)) {
		if (!isset($akoNemaKreiraj) || $akoNemaKreiraj==0) {
			ispisGreske("Fajl <$fileData> ne postoji!");
			exit;
		} else {
			// Kreiranje autotest fajla sa zajednickim postavkama koje su proslijedjene
			saveJson($fileData, getDefDecodedJson());
		}
	}
	function getVar($var) {
		// Safe getting variable, any: POST or GET;
		// Returns NULL if no variable
		if (isset($_POST[$var])) return get_magic_quotes_gpc() ? stripslashes($_POST[$var]) : $_POST[$var];
		else if (isset($_GET[$var])) return get_magic_quotes_gpc() ? stripslashes($_GET[$var]) : $_GET[$var];
		else return NULL;
	}
	function getIntVar($var) {
		// Vraca cijeli broj, ili NULL if no variable
		$stringVal=getVar($var);
		if ($stringVal===NULL) return NULL;
		else return intval($stringVal);
	}
	function getBoolVar($var) {
		if (isset($_POST[$var])) return "true";
		else if (isset($_GET[$var])) return "true";
		else return "false";
	}
	function getConsoleVar($arg) {
		return json_decode('"'.$arg.'"', true);
	}
	function ispisGreske($greska) {
		print "<font class='tekst info'>GRE&#352;KA: $greska</font>";
	}
	function zavrsi() {
		print "<br><input type='button' onclick='safeLinkBackForw(-1);' value='Nazad'>";
		print "</body></html>";
		exit;
	}
	function saveJson($path, $json) {
		if ($path=="") {
			ispisGreske("Naziv fajla u funkciji saveJson() ne mo&#382;e biti prazan string.");
			zavrsi();
		}
		if(!($fw = fopen($path, "w"))) {
			ispisGreske("Problem sa otvaranjem fajla <$path>.");
			zavrsi();
		}	
		if(flock($fw, LOCK_EX)){
			// Treba upisati novi sadržaj
			fwrite($fw, replace_rn_ln(json_encode($json, JSON_PRETTY_PRINT)));
			fflush($fw);
			flock($fw, LOCK_UN);
			fclose($fw);
		}
	}	
	function deleteDir($dirPath) {
	    if (!is_dir($dirPath)) {
	        ispisGreske("$dirPath mora biti folder");
	        zavrsi();
	    }
	    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
	        $dirPath .= '/';
	    }
	    $files = glob($dirPath . '*', GLOB_MARK);
	    foreach ($files as $file) {
	        if (is_dir($file)) {
	            self::deleteDir($file);
	        } else {
	            unlink($file);
	        }
	    }
	    rmdir($dirPath);
	}
	function crPrazanFile($path) {
		if ($path=="") {
			ispisGreske("Naziv fajla u funkciji crPrazanFile() ne mo&#382;e biti prazan string.");
			zavrsi();
		}
		if(!($fw = fopen($path, "w"))) {
			ispisGreske("Problem sa otvaranjem fajla <$path>.");
			zavrsi();
		}	
		if(flock($fw, LOCK_EX)){			
			flock($fw, LOCK_UN);
			fclose($fw);
		}	
	}	
	function replace_ln_n_br($recenica) {
		return str_replace(array("\\r\\n", "\\r", "\\n", "\r\n","\r","\n"),"<br>", $recenica);
	}
	function replace_n_br($recenica) {
		return str_replace(array("\r\n","\r","\n"),"<br>", $recenica);
	}
	function replace_ln_br($recenica) {
		return str_replace(array("\\r\\n", "\\r","\\n"),"<br>", $recenica);
	}
	function replace_ln_n($recenica) {
		return str_replace(array("\\r\\n", "\\r","\\n"),"\n", $recenica);
	}
	function replace_n_ln($recenica) {
		return str_replace(array("\r\n","\r","\n"),"\\n", $recenica);
	}
	function replace_rn_ln($recenica) {
		return str_replace(array("\\r\\n","\\r"),"\\n", $recenica);
	}
	function replace_space_nbsp($recenica) {
		$recenica=str_replace(array(" "),"&nbsp;", $recenica);
		$recenica=str_replace(array("\t"),"&nbsp;&nbsp;&nbsp;&nbsp;", $recenica);		
		return $recenica;
	}
	function previewformat($recenica, $izlaz=0) {
		$recenica=htmlentities($recenica);
		$recenica=replace_space_nbsp($recenica);
		if ($izlaz==0) { // Nije expected output
			$recenica=replace_n_br($recenica);
		} else { // Jeste expected output
			$recenica=replace_ln_br($recenica);
		}			
		return $recenica;
	}	
	function getNewId() {
		global $fileLastId;
		if ($fileLastId=="") {
			ispisGreske("Naziv fajla u funkciji getNewId() ne mo&#382;e biti prazan string.");
			zavrsi();
		}
		if(!($fw = fopen($fileLastId, "c+"))) {
			ispisGreske("Problem sa otvaranjem fajla <$fileLastId>.");
			zavrsi();
		}	
		if(flock($fw, LOCK_EX)){
			rewind($fw); // Vrati se na pocetak fajla, spreman za citanje			
			$id = intval(trim(fgets($fw)));
			$id = ($id>=PHP_INT_MAX)?1:$id+1; 
			ftruncate($fw, 0); // Pobriše sadržaj fajla
			rewind($fw); // Kursor na pocetak fajla ponovo
			// Treba upisati novi sadržaj
			fwrite($fw, $id);
			fflush($fw);
			flock($fw, LOCK_UN);
			fclose($fw);
		}
		return $id;
	}
	function getDefAT() {
		$json='{
            "id": '.getNewId().',
            "require_symbols": [],
            "replace_symbols": [],
            "code": "",
            "global_above_main": "",
            "global_top": "",
            "running_params": {
                "timeout": "10",
                "vmem": "1000000",
                "stdin": ""
            },
            "expected": [
                ""
            ],
            "expected_exception": "false",
            "expected_crash": "false",
            "ignore_whitespace": "false",
            "regex": "false",
            "substring": "false"
        }';
        return $json;
	}
	function getDefDecodedJson() { // Poziva se u slucaju da ne postoji autotest file + treba ga kreirati	
		$def_name=getVar("def_name");
		$def_language=getVar("def_language");
		$def_required_compiler=getVar("def_required_compiler");
		$def_preferred_compiler=getVar("def_preferred_compiler");
		
		$def_compiler_features=getVar("def_compiler_features");
		$def_compiler_options=getVar("def_compiler_options");
		$def_compiler_options_debug=getVar("def_compiler_options_debug");
		
		$def_compile=getBoolVar("def_compile"); 
		$def_run=getBoolVar("def_run");
		$def_test=getBoolVar("def_test");
		$def_debug=getBoolVar("def_debug");
		$def_profile=getBoolVar("def_profile");
		
		$json='
			{  
			    "id":'.getNewId().',
			    "name":"'.$def_name.'",
			    "language":"'.$def_language.'",
			    "required_compiler":"'.$def_required_compiler.'",
			    "preferred_compiler":"'.$def_preferred_compiler.'",
			    "compiler_features":[  
			    	'.$def_compiler_features.'
			    ],
			    "compiler_options":"'.$def_compiler_options.'",
			    "compiler_options_debug":"'.$def_compiler_options_debug.'",
			    "compile":"'.$def_compile.'",
			    "run":"'.$def_run.'",
			    "test":"'.$def_test.'",
			    "debug":"'.$def_debug.'",
			    "profile":"'.$def_profile.'",
			    "test_specifications":[ '.getDefAT().' ]
		    }
		';
		return json_decode($json, true);
	}
?>