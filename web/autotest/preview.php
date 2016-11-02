<?php
	// Prihvata 2 parametra
	// + opcionalno zajednicke parametre za novi autotest file 
	//  (ako se ne proslijedezajednicki parametri, polja ce biti prazna - pogledati funkciju getDefDecodedJson)
	// 'fileData' -> putanja do fajla;
	// 'adv' -> show advanced options (opcionalan parametar, 1 ili 0)
	$akoNemaKreiraj=1; // Misli se na postojanje fajla 'fileData'. Ako ne postoji, kreirat ce se.
	require 'functions.php';
	$json=json_decode(file_get_contents($fileData), true);
	$brATova=count($json["test_specifications"]);
	$advanced=getIntVar("adv"); // Da li ce prikazivati advanced options u formama, ili ne
	if ($advanced===NULL) $advanced=0;
	if ($advanced!=0) $advanced=1;
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
	<script type="text/javascript">
		var advanced=<?php print $advanced; ?>;
	</script>
	<script src="functions.js" type="text/javascript"></script>
</head>
<body style="margin: 5px; padding: 10px;" onload="historija();">
<font class="tekst">
Broj autotestova u trenutku učitavanja: <font color="red"><?php print $brATova; ?> </font>
</font>
<input type="hidden" id="historija" value="0">

<form action="api.php" method="post" id="brisanjeFajla">
	<input type="hidden" name="mod" value="1">
	<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
	<input type="hidden" id="fileData" name="fileData" value="<?php print $fileData; ?>">
</form>
<form action="edit_1.php" method="post" id="dodavanjeAutotesta">
	<input type="hidden" name="mod" value="4">
	<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
	<input type="hidden" id="fileData" name="fileData" value="<?php print $fileData; ?>">
</form>
<form action="edit.php" method="post" id="editovanjeFajla">
	<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
	<input type="hidden" id="fileData" name="fileData" value="<?php print $fileData; ?>">
</form>

<form action="api.php" method="post" id="editovanjeFajla">
	<input type="hidden" value="5" name="mod">
	<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
	<input type="hidden" value="<?php print $fileData; ?>" name="fileData" id="fileData">
	<table cellspacing="0" cellpadding="0" border="1">
		<tr bgcolor="#dddddd" style="border: 2px solid gray;">
			<td class="tekst celija smaller stronger plava" align="left">Zajedničke postavke za AT-ove ispod</td>
			<td align="left" class="tekst celija">
			<input type="button" value="Izmijeni sve" title="Izmijeni zajedničke postavke i autotestove" onclick="document.getElementById('editovanjeFajla').submit();">
			<input type="button" value="Obriši fajl sa autotestovima" title="Brisanje fajla sa autotestovima" onclick="document.getElementById('brisanjeFajla').submit();">		
			<input type='button' onclick='safeLinkBackForw(-1);' value='Nazad'>	
			<input name="adv_button" onclick="showAdvanced();" type="button" value="<?php if ($advanced) print "Sakrij dodatne opcije"; else print "Prikaži dodatne opcije"; ?>">
			</td>
		</tr>
		<tr>
			<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Naziv</td>
			<td class="tekst celija smaller stronger" align="left"><input type="text" value='<?php print $json["name"]; ?>' name="name" style="width: 500px;"></td>	
		</tr>
		<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
			<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Programski jezik</td>
			<td class="tekst celija smaller stronger" align="left"><input type="text" value='<?php print $json["language"]; ?>' name="language" style="width: 150px;"></td>	
		</tr>
		<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
			<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Zahtijevani kompajler</td>
			<td class="tekst celija smaller stronger" align="left"><input type="text" value='<?php print $json["required_compiler"]; ?>' name="required_compiler" style="width: 150px;"></td>			
		</tr>
		<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
			<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Preferirani kompajler</td>	
			<td class="tekst celija smaller stronger" align="left"><input type="text" value='<?php print $json["preferred_compiler"]; ?>' name="preferred_compiler" style="width: 150px;"></td>		
		</tr>		
		<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
			<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Opcije kompajlera</td>
			<td class="tekst celija smaller stronger" align="left"><input type="text" value='<?php print $json["compiler_options"]; ?>' name="compiler_options" style="width: 500px;"></td>			
		</tr>	
		<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
			<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Debug opcije kompajlera</td>
			<td class="tekst celija smaller stronger" align="left"><input type="text" value='<?php print $json["compiler_options_debug"]; ?>' name="compiler_options_debug" style="width: 500px;"></td>			
		</tr>
		<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
			<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Specifičnosti kompajlera<br>(niz stringova kao: "1", "2")</td>	
			<td class="tekst celija smaller stronger" align="left">
			<input type="text" value='<?php 
				$features=$json["compiler_features"];
				for ($i=0; $i<count($features); $i++) {
					print "\"".$features[$i]."\"";
					if ($i<count($features)-1) print ", ";
				}
			?>' name="compiler_features" style="width: 600px;">
			</td>		
		</tr>
		<tr>
			<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">
			Kompajlirati 
			<?php if ($json["compile"]=="true") print "<input type='checkbox' checked name='compile'>"; else print "<input type='checkbox' name='compile'>"; ?> | 
			Pokrenuti
			<?php if ($json["run"]=="true") print "<input type='checkbox' checked name='run'>"; else print "<input type='checkbox' name='run'>"; ?>	|	
			Testirati
			<?php if ($json["test"]=="true") print "<input type='checkbox' checked name='test'>"; else print "<input type='checkbox' name='test'>"; ?> |
			Debugirati
			<?php if ($json["debug"]=="true") print "<input type='checkbox' checked name='debug'>"; else print "<input type='checkbox' name='debug'>"; ?> |
			Profil
			<?php if ($json["profile"]=="true") print "<input type='checkbox' checked name='profile'>"; else print "<input type='checkbox' name='profile'>"; ?>
			</td>		
		</tr>
		<tr>
			<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">
			<input type="submit" value="Potvrdi izmjene u zajedničkim postavkama">
			</td>
		</tr>
	</table><br>
</form>
<?php
/*
<table cellspacing="0" cellpadding="0" border="1">
	<tr bgcolor="#dddddd" style="border: 2px solid gray;">
		<td class="tekst celija smaller stronger plava" align="left">Zajedničke postavke za AT-ove ispod</td>
		<td align="left" class="tekst celija">
		<input type="button" value="Izmijeni sve" title="Izmijeni zajedničke postavke i autotestove" onclick="document.getElementById('editovanjeFajla').submit();">
		<input type="button" value="Obriši fajl sa autotestovima" title="Brisanje fajla sa autotestovima" onclick="document.getElementById('brisanjeFajla').submit();">		
		<input type='button' onclick='safeLinkBackForw(-1);' value='Nazad'>	
		<input name="adv_button" onclick="showAdvanced();" type="button" value="<?php if ($advanced) print "Sakrij dodatne opcije"; else print "Prikaži dodatne opcije"; ?>">
		</td>
	</tr>
	<tr>
		<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Naziv</td>
		<td class="tekst celija stronger zelena" align="left"><?php print previewformat($json["name"]); ?></td>	
	</tr>
	<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
		<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Programski jezik</td>
		<td class="tekst celija stronger zelena" align="left"><?php print previewformat($json["language"]); ?></td>	
	</tr>
	<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
		<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Zahtijevani kompajler</td>
		<td class="tekst celija stronger zelena" align="left"><?php print previewformat($json["required_compiler"]); ?></td>			
	</tr>
	<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
		<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Preferirani kompajler</td>	
		<td class="tekst celija stronger zelena" align="left"><?php print previewformat($json["preferred_compiler"]); ?></td>		
	</tr>
	<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
		<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Opcije kompajlera</td>
		<td class="tekst celija stronger zelena" align="left"><?php print previewformat($json["compiler_options"]); ?></td>			
	</tr>	
	<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
		<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Debug opcije kompajlera</td>
		<td class="tekst celija stronger zelena" align="left"><?php print previewformat($json["compiler_options_debug"]); ?></td>			
	</tr>
	<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
		<td class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">Specifičnosti kompajlera</td>	
		<td class="tekst celija stronger zelena" align="left">
		<?php
			$features=$json["compiler_features"];
			for ($i=0; $i<count($features); $i++) {
				print '"'.previewformat($features[$i]).'"';
				if ($i<count($features)-1) print ', ';
			}
		?>
		</td>		
	</tr>
	<tr>
		<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">
		Kompajlirati 
		<?php if ($json["compile"]=="true") print "<input type='checkbox' disabled checked name='compile'>"; else print "<input type='checkbox' disabled name='compile'>"; ?> | 
		Pokrenuti
		<?php if ($json["run"]=="true") print "<input type='checkbox' disabled checked name='run'>"; else print "<input type='checkbox' disabled name='run'>"; ?>	|	
		Testirati
		<?php if ($json["test"]=="true") print "<input type='checkbox' disabled checked name='test'>"; else print "<input type='checkbox' disabled name='test'>"; ?> |
		Debugirati
		<?php if ($json["debug"]=="true") print "<input type='checkbox' disabled checked name='debug'>"; else print "<input type='checkbox' disabled name='debug'>"; ?> |
		Profil
		<?php if ($json["profile"]=="true") print "<input type='checkbox' disabled checked name='profile'>"; else print "<input type='checkbox' disabled name='profile'>"; ?>
		</td>			
	</tr>	
	<tr>
		<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#ebecfe">
		<input type="button" value="Potvrdi izmjene u zajedničkim postavkama">
		</td>
	</tr>
</table><br>
*/
?>
	<?php
		for ($i=1; $i<=$brATova; $i++) {
			$ovajAT=$json["test_specifications"][$i-1];			
			?>
				<table cellspacing="0" cellpadding="0" border="1" bgcolor="#ffffff">
				<tr bgcolor="#ffbdbd" style="border: 2px solid gray;">
					<td class="tekst celija smaller stronger plava" width="1px">ID <font color="red"><?php print $ovajAT['id']; ?></font> | Autotest <font color="red"><?php print $i; ?></font></td>
					<td align="left" class="tekst celija">
						<form action="edit_1.php" method="post" style="display: inline; margin: 0; padding: 0;">
							<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
							<input type="hidden" name="id" value="<?php print $ovajAT['id']; ?>">
							<input type="hidden" name="fileData" value="<?php print $fileData; ?>">
							<input type="submit" value="Izmijeni ovaj autotest">
						</form>
						<form action="api.php" method="post" style="display: inline; margin: 0; padding: 0;">
							<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
							<input type="hidden" name="id" value="<?php print $ovajAT['id']; ?>">
							<input type="hidden" name="fileData" value="<?php print $fileData; ?>">
							<input type="hidden" name="mod" value="3">
							<input type="submit" value="Obriši ovaj autotest">
						</form>
						<input type="button" onclick="naVrh();" value="Scroll top">
						<input type="button" onclick="naDno();" value="Scroll bottom">
						<input type="button" value="Dodaj novi autotest" onclick="document.getElementById('dodavanjeAutotesta').submit();">
					</td>
				</tr>
				<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
					<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Zahtijevaj simbole</td>	
					<td class="tekst celija stronger zelena" align="left">
					<?php
						$require_symbols=$ovajAT["require_symbols"];
						for ($j=0; $j<count($require_symbols); $j++) {
							print '"'.previewformat($require_symbols[$j]).'"';
							if ($j<count($require_symbols)-1) print ', ';
						}
					?>
					</td>		
				</tr>
				<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
					<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Zamijeni simbole</td>	
					<td class="tekst celija stronger zelena" align="left">
					<?php
						$replace_symbols=$ovajAT["replace_symbols"];
						for ($j=0; $j<count($replace_symbols); $j++) {
							print '"'.previewformat($replace_symbols[$j]).'"';
							if ($j<count($replace_symbols)-1) print ', ';
						}
					?>
					</td>		
				</tr>
				<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
					<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Globalno zaglavlje</td>	
					<td class="tekst celija stronger zelena" align="left"><?php print previewformat($ovajAT["global_top"]); ?></td>		
				</tr>
				<tr>
					<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Globalni opseg iznad maina</td>	
					<td class="tekst celija stronger zelena" align="left"><?php print previewformat($ovajAT["global_above_main"]); ?></td>		
				</tr>	
				<tr>
					<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Kod</td>	
					<td class="tekst celija stronger zelena" align="left"><?php print previewformat($ovajAT["code"]); ?></td>		
				</tr>
				<tr>
					<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Ulaz</td>	
					<td class="tekst celija stronger zelena" align="left"><?php print previewformat($ovajAT["running_params"]["stdin"]); ?></td>		
				</tr>
				<tr>
					<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;" bgcolor="#fafdbb">Očekivani izlaz</td>
					<td class="tekst celija stronger zelena" align="left" style="border-top: none; border-bottom: none;">
					<?php
						$expected=$ovajAT["expected"];
						for ($j=0; $j<count($expected); $j++) {							
							?>
							<font class="plava">Varijanta <span id="var_<?php print ($j+1); ?>"><?php print ($j+1); ?></span></font><br>
							<?php
							print previewformat($expected[$j], 1)."<br>";
						}
					?>
					</td>
				</tr>			
				<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
					<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">
					Vrijeme čekanja
					<input disabled type="text" value='<?php print $ovajAT["running_params"]["timeout"]; ?>' name="timeout_<?php print $i; ?>" style="width: 100px;">
					Memorija
					<input disabled type="text" value='<?php print $ovajAT["running_params"]["vmem"]; ?>' name="vmem_<?php print $i; ?>" style="width: 100px;">
					</td>			
				</tr>
				<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
					<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">
					Ocekuje se izuzetak
					<?php if ($ovajAT["expected_exception"]=="true") print "<input type='checkbox' disabled checked name='expected_exception_".$i."'>"; else print "<input type='checkbox' disabled name='expected_exception_".$i."'>"; ?> |
					Ocekuje se krah
					<?php if ($ovajAT["expected_crash"]=="true") print "<input type='checkbox' disabled checked name='expected_crash_".$i."'>"; else print "<input type='checkbox' disabled name='expected_crash_".$i."'>"; ?> |
					Ignoriši prazno
					<?php if ($ovajAT["ignore_whitespace"]=="true") print "<input type='checkbox' disabled checked name='ignore_whitespace_".$i."'>"; else print "<input type='checkbox' disabled name='ignore_whitespace_".$i."'>"; ?> |
					Regex
					<?php if ($ovajAT["regex"]=="true") print "<input type='checkbox' disabled checked name='regex_".$i."'>"; else print "<input type='checkbox' disabled name='regex_".$i."'>"; ?> |
					Podstring
					<?php if ($ovajAT["substring"]=="true") print "<input type='checkbox' disabled checked name='substring_".$i."'>"; else print "<input type='checkbox' disabled name='substring_".$i."'>"; ?>
					</td>	
				</tr>
				</table><br>
			<?php
		}
	?>
<input type="button" value="Dodaj novi autotest" onclick="document.getElementById('dodavanjeAutotesta').submit();">	
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