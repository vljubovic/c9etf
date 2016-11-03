<?php
	require 'functions.php';
	$advanced=getIntVar("adv"); // Da li ce prikazivati advanced options u formama, ili ne
	if ($advanced===NULL) $advanced=0;
	if ($advanced!=0) $advanced=1;
	$mod=getIntVar("mod"); 
	// mod=0 za editovanje fajla, 
	// mod=1 za brisanje fajla,
	// mod=2 za edit pojedinacnog autotesta
	// mod=3 za brisanje pojedinacnog autotesta
	// mod=4 za dodavanje novog autotesta
	// mod=5 za editovanje zajednickih postavki
?>
<!DOCTYPE html>
<html>
<head>
	<title>Autotest generator</title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<meta name="description" content="ATgenerator">
	<link rel="stylesheet" type="text/css" href="style.css">
	<script src="jquery-1.11.3.js"></script>
	<script src="functions.js" type="text/javascript"></script>
</head>
<body style="margin: 5px; padding: 10px;">
<form action="preview.php" method="post" id="pregledFajla">
	<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
	<input type="hidden" id="fileData" name="fileData" value="<?php print $fileData; ?>">
</form>
<form action="edit.php" method="post" id="editovanjeFajla">
	<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
	<input type="hidden" id="fileData" name="fileData" value="<?php print $fileData; ?>">
</form>
<?php
	if ($mod==1) { // Mod za brisanje fajla
		unlink($fileData);
		if (file_exists($fileData)) {
			ispisGreske("Fajl '$fileData' nije obrisan!");
			zavrsi();
		}
		print "File je obrisan!";
		admin_log("delete at file $fileData");
	} else if ($mod==0) { // Mod za Edit svih autotestova zajedno
		// Treba primljene podatke sacuvati u file kao json
		$json=json_decode(file_get_contents($fileData),true);
		
		$name=getVar("name");
		if ($name!==NULL) {
			$json["name"]=$name;
		}  
		$language=getVar("language");
		if ($language!==NULL) {
			$json["language"]=$language;
		} 
		$required_compiler=getVar("required_compiler");
		if ($required_compiler!==NULL) {
			$json["required_compiler"]=$required_compiler;
		} 
		$preferred_compiler=getVar("preferred_compiler");
		if ($preferred_compiler!==NULL) {
			$json["preferred_compiler"]=$preferred_compiler;
		} 
		$compiler_features=getVar("compiler_features");
		if ($compiler_features!==NULL) {
			$p=json_decode("[$compiler_features]", true);
			$json["compiler_features"]=$p;
		} 
		$compiler_options=getVar("compiler_options");
		if ($compiler_options!==NULL) {
			$json["compiler_options"]=$compiler_options;
		} 	
		$compiler_options_debug=getVar("compiler_options_debug");
		if ($compiler_options_debug!==NULL) {
			$json["compiler_options_debug"]=$compiler_options_debug;
		} 
		
		$compile=getBoolVar("compile");		
		$json["compile"]=$compile;
		
		$run=getBoolVar("run");
		$json["run"]=$run; 
		
		$test=getBoolVar("test");
		$json["test"]=$test;

		$debug=getBoolVar("debug");
		$json["debug"]=$debug;
		
		$profile=getBoolVar("profile");
		$json["profile"]=$profile;
				
		$numateova=getIntVar("numateova");
		if ($numateova===NULL) {
			$numateova=count($json["test_specifications"]);
		} else if ($numateova<0) {
			ispisGreske("'numateova' mora biti >=0.");
			zavrsi();
		}
		
		for ($i=0; $i<$numateova; $i++) {
			if (!isset($json["test_specifications"][$i]["id"]))
				$json["test_specifications"][$i]["id"]=getNewId();
			
			$require_symbols=getVar("require_symbols_".($i+1));
			if ($require_symbols!==NULL) {
				$p=json_decode("[$require_symbols]", true);
				$json["test_specifications"][$i]["require_symbols"]=$p;
			} else if (!isset($json["test_specifications"][$i]["require_symbols"])) {
				$p=json_decode("[]", true);
				$json["test_specifications"][$i]["require_symbols"]=$p;
			} // U suprotnom ostaje nepromijenjeno
			$replace_symbols=getVar("replace_symbols_".($i+1));
			if ($replace_symbols!==NULL) {
				$p=json_decode("[$replace_symbols]", true);
				$json["test_specifications"][$i]["replace_symbols"]=$p;
			} else if (!isset($json["test_specifications"][$i]["replace_symbols"])) {
				$p=json_decode("[]", true);
				$json["test_specifications"][$i]["replace_symbols"]=$p;
			} // U suprotnom ostaje nepromijenjeno
			$code=getVar("code_".($i+1));
			if ($code!==NULL) {
				$json["test_specifications"][$i]["code"]=$code;
			} 
			$global_above_main=getVar("global_above_main_".($i+1));
			if ($global_above_main!==NULL) {
				$json["test_specifications"][$i]["global_above_main"]=$global_above_main;
			} 
			$global_top=getVar("global_top_".($i+1));
			if ($global_top!==NULL) {
				$json["test_specifications"][$i]["global_top"]=$global_top;
			} 			
			if (!isset($json["test_specifications"][$i]["running_params"]))
					$json["test_specifications"][$i]["running_params"]=array();
			$timeout=getVar("timeout_".($i+1));
			if ($timeout!==NULL) {					
				$json["test_specifications"][$i]["running_params"]["timeout"]=$timeout;
			} 
			$vmem=getVar("vmem_".($i+1));
			if ($vmem!==NULL) {					
				$json["test_specifications"][$i]["running_params"]["vmem"]=$vmem;
			} 
			$stdin=getVar("stdin_".($i+1));
			if ($stdin!==NULL) {					
				$json["test_specifications"][$i]["running_params"]["stdin"]=$stdin;
			} 
			if (!isset($json["test_specifications"][$i]["expected"]))
				$json["test_specifications"][$i]["expected"]=array();	
			$brvar=getIntVar("brvar_".($i+1));
			if ($brvar===NULL) {
				$brvar=count($json["test_specifications"][$i]["expected"]);
			} else if ($brvar<0) {
				ispisGreske("'brvar_x' mora biti >=0.");
				zavrsi();
			}
			for ($k=0; $k<$brvar; $k++) {
				// Ako je postavljena odgovarajuca varijabla, uzeti je i ubaciti
				// Ako neka i nije, onda samo "";
				$expected=getVar("expected_".($i+1)."_".($k+1));
				if ($expected!==NULL) {
					$json["test_specifications"][$i]["expected"][$k]=replace_n_ln($expected);
				} else if (!isset($json["test_specifications"][$i]["expected"][$k])) {
					$json["test_specifications"][$i]["expected"][$k]="";
				} // u suprotnom zadržava staru vrijednost				
			}
			if (count($json["test_specifications"][$i]["expected"])>$brvar) {
				// Izbrisati sav višak varijanti!
				array_splice($json["test_specifications"][$i]["expected"], $brvar);
			}
			$expected_exception=getBoolVar("expected_exception_".($i+1));			
			$json["test_specifications"][$i]["expected_exception"]=$expected_exception;			
			$expected_crash=getBoolVar("expected_crash_".($i+1));
			$json["test_specifications"][$i]["expected_crash"]=$expected_crash;
			$ignore_whitespace=getBoolVar("ignore_whitespace_".($i+1));
			$json["test_specifications"][$i]["ignore_whitespace"]=$ignore_whitespace;
			$regex=getBoolVar("regex_".($i+1));
			$json["test_specifications"][$i]["regex"]=$regex;
			$substring=getBoolVar("substring_".($i+1));
			$json["test_specifications"][$i]["substring"]=$substring;								
		}
		if (count($json["test_specifications"])>$numateova) {
			// Izbrisati sav višak AT-ova iz json-a
			array_splice($json["test_specifications"], $numateova);
		}
		// Kreiran je json
		saveJson($fileData, $json);	
		print "Editovanje izvršeno!";	
		admin_log("edit at file $fileData (mod=$mod)");
	} else if ($mod==2) { // Edit pojedinacnog autotesta
		// Treba primljene podatke sacuvati u file kao json
		$json=json_decode(file_get_contents($fileData),true);
		$brATova=count($json["test_specifications"]);
		$id=getIntVar("id");
		// Potrebno saznati redni broj autotesta sa datim id-em
		$i=0;
		$k=1;
		while ($k<=$brATova) {
			if ($json["test_specifications"][$k-1]["id"]==$id) {	
				$i=$k;
				break;
			}
			$k++;
		}
		if ($i==0) {
			ispisGreske("Nije pronađen autotest sa id-em: $id");
			zavrsi();
		}
		$i--;			
		$require_symbols=getVar("require_symbols_".($i+1));
		if ($require_symbols!==NULL) {
			$p=json_decode("[$require_symbols]", true);
			$json["test_specifications"][$i]["require_symbols"]=$p;
		} else if (!isset($json["test_specifications"][$i]["require_symbols"])) {
			$p=json_decode("[]", true);
			$json["test_specifications"][$i]["require_symbols"]=$p;
		} // U suprotnom ostaje nepromijenjeno
		$replace_symbols=getVar("replace_symbols_".($i+1));
		if ($replace_symbols!==NULL) {
			$p=json_decode("[$replace_symbols]", true);
			$json["test_specifications"][$i]["replace_symbols"]=$p;
		} else if (!isset($json["test_specifications"][$i]["replace_symbols"])) {
			$p=json_decode("[]", true);
			$json["test_specifications"][$i]["replace_symbols"]=$p;
		} // U suprotnom ostaje nepromijenjeno
		$code=getVar("code_".($i+1));
		if ($code!==NULL) {
			$json["test_specifications"][$i]["code"]=$code;
		} 
		$global_above_main=getVar("global_above_main_".($i+1));
		if ($global_above_main!==NULL) {
			$json["test_specifications"][$i]["global_above_main"]=$global_above_main;
		} 
		$global_top=getVar("global_top_".($i+1));
		if ($global_top!==NULL) {
			$json["test_specifications"][$i]["global_top"]=$global_top;
		} 			
		if (!isset($json["test_specifications"][$i]["running_params"]))
				$json["test_specifications"][$i]["running_params"]=array();
		$timeout=getVar("timeout_".($i+1));
		if ($timeout!==NULL) {					
			$json["test_specifications"][$i]["running_params"]["timeout"]=$timeout;
		} 
		$vmem=getVar("vmem_".($i+1));
		if ($vmem!==NULL) {					
			$json["test_specifications"][$i]["running_params"]["vmem"]=$vmem;
		} 
		$stdin=getVar("stdin_".($i+1));
		if ($stdin!==NULL) {					
			$json["test_specifications"][$i]["running_params"]["stdin"]=$stdin;
		} 
		if (!isset($json["test_specifications"][$i]["expected"]))
			$json["test_specifications"][$i]["expected"]=array();	
		$brvar=getIntVar("brvar_".($i+1));
		if ($brvar===NULL) {
			$brvar=count($json["test_specifications"][$i]["expected"]);
		} else if ($brvar<0) {
			ispisGreske("'brvar_x' mora biti >=0.");
			zavrsi();
		}
		for ($k=0; $k<$brvar; $k++) {
			// Ako je postavljena odgovarajuca varijabla, uzeti je i ubaciti
			// Ako neka i nije, onda samo "";
			$expected=getVar("expected_".($i+1)."_".($k+1));
			if ($expected!==NULL) {
				$json["test_specifications"][$i]["expected"][$k]=replace_n_ln($expected);
			} else if (!isset($json["test_specifications"][$i]["expected"][$k])) {
				$json["test_specifications"][$i]["expected"][$k]="";
			} // u suprotnom zadržava staru vrijednost				
		}
		if (count($json["test_specifications"][$i]["expected"])>$brvar) {
			// Izbrisati sav višak varijanti!
			array_splice($json["test_specifications"][$i]["expected"], $brvar);
		}
		$expected_exception=getBoolVar("expected_exception_".($i+1));			
		$json["test_specifications"][$i]["expected_exception"]=$expected_exception;			
		$expected_crash=getBoolVar("expected_crash_".($i+1));
		$json["test_specifications"][$i]["expected_crash"]=$expected_crash;
		$ignore_whitespace=getBoolVar("ignore_whitespace_".($i+1));
		$json["test_specifications"][$i]["ignore_whitespace"]=$ignore_whitespace;
		$regex=getBoolVar("regex_".($i+1));
		$json["test_specifications"][$i]["regex"]=$regex;
		$substring=getBoolVar("substring_".($i+1));
		$json["test_specifications"][$i]["substring"]=$substring;								
		// Kreiran je json
		saveJson($fileData, $json);	
		admin_log("edit at file $fileData (mod=$mod)");
		print "Editovanje izvršeno!";	
	} else if ($mod==3) { // Brisanje pojedinacnog autotesta
		$json=json_decode(file_get_contents($fileData),true);
		$brATova=count($json["test_specifications"]);
		$id=getIntVar("id");
		// Potrebno saznati redni broj autotesta sa datim id-em
		$i=0;
		$k=1;
		while ($k<=$brATova) {
			if ($json["test_specifications"][$k-1]["id"]==$id) {	
				$i=$k;
				break;
			}
			$k++;
		}
		if ($i==0) {
			ispisGreske("Nije pronađen autotest sa id-em: $id");
			zavrsi();
		}
		$i--;
		// Slijedi izbacivanje AT-a iz json-a, nakon toga modifikovati $data
		for ($k=$i; $k<$brATova-1; $k++) {
			$json["test_specifications"][$k]=$json["test_specifications"][$k+1];
			$json["test_specifications"][$k]["id"]=getNewId();
		}
		$brATova--;
		array_splice($json["test_specifications"], $brATova); // Izbacimo zadnji element
		saveJson($fileData, $json);	
		print "Brisanje izvršeno!";
		admin_log("delete single at, file $fileData (mod=$mod)");
	} else if ($mod==4) { // Dodavanje jednog autotesta
		$newATjson=json_decode(getDefAT(),true);
		$json=json_decode(file_get_contents($fileData),true);
		$brATova=count($json["test_specifications"]);
		$json["test_specifications"][$brATova]=$newATjson;
		saveJson($fileData, $json);	
		?>
		Novi autotest je uspješno dodan.<br>
		<form action="edit_1.php" id="izmijeniAT" method="post">
			<input type="hidden" name="id" value="<?php print $newATjson["id"]; ?>" >
			<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
			<input type="hidden" name="fileData" value="<?php print $fileData; ?>" >
			<input type="submit" value="Izmijeni novi AT">
		</form><br>
		<?php
		admin_log("add single at, file $fileData (mod=$mod)");
	} else if ($mod==5) { // Izmjena zajednickih postavki za dati fajl
		$json=json_decode(file_get_contents($fileData),true);
		
		$name=getVar("name");
		if ($name!==NULL) {
			$json["name"]=$name;
		}  
		$language=getVar("language");
		if ($language!==NULL) {
			$json["language"]=$language;
		} 
		$required_compiler=getVar("required_compiler");
		if ($required_compiler!==NULL) {
			$json["required_compiler"]=$required_compiler;
		} 
		$preferred_compiler=getVar("preferred_compiler");
		if ($preferred_compiler!==NULL) {
			$json["preferred_compiler"]=$preferred_compiler;
		} 
		$compiler_features=getVar("compiler_features");
		if ($compiler_features!==NULL) {
			$p=json_decode("[$compiler_features]", true);
			$json["compiler_features"]=$p;
		} 
		$compiler_options=getVar("compiler_options");
		if ($compiler_options!==NULL) {
			$json["compiler_options"]=$compiler_options;
		} 	
		$compiler_options_debug=getVar("compiler_options_debug");
		if ($compiler_options_debug!==NULL) {
			$json["compiler_options_debug"]=$compiler_options_debug;
		} 
		
		$compile=getBoolVar("compile");		
		$json["compile"]=$compile;
		
		$run=getBoolVar("run");
		$json["run"]=$run; 
		
		$test=getBoolVar("test");
		$json["test"]=$test;

		$debug=getBoolVar("debug");
		$json["debug"]=$debug;
		
		$profile=getBoolVar("profile");
		$json["profile"]=$profile;
		
		// Kreiran je json
		saveJson($fileData, $json);	
		print "Editovanje izvršeno!";
		admin_log("edit at file $fileData (mod=$mod)");
	}
	
	

function admin_log($msg) {
	$login = $_SESSION['login'];
	$conf_base_path = "/usr/local/webide";
	$msg = date("Y-m-d H:i:s") . " - $login - $msg\n";
	file_put_contents("$conf_base_path/log/admin.php.log", $msg, FILE_APPEND);
}

?>
<br>
<input type='button' onclick='safeLinkBackForw(-1);' value='Nazad'>
<input type='button' onclick="document.getElementById('pregledFajla').submit();" value='Pregledaj sve'>
<input type='button' onclick="document.getElementById('editovanjeFajla').submit();" value='Edituj sve'>
<script type='text/javascript'>
	// Za Mozillu treba custom pageshow event, posto onload event nece funkcionisati nakon 'Back'
	if (browser.name.indexOf("Firefox") != -1) {
        $(window).bind('pageshow', function() {
            // Firefox doesn't reload the page when the user uses the back button, or when we call history.go.back().
            // Doc: https://developer.mozilla.org/en-US/docs/Listening_to_events_in_Firefox_extensions 
            FFhistorija();
        }); 
    }
</script>
</body></html>