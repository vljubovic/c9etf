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
	<span id="variant_template" style="display: none;">
		<span id="variant_NUMI_NUMJ">
			<div style="height:1px; visibility:hidden; margin:0;"></div>
			<font class="plava">Varijanta <span id="br_NUMI_NUMJ">NUMJ</span></font>
			<input type="button" value="Obriši" id="bris_NUMI_NUMJ" onclick="brisanjeVarijante(this.id);">
			<br>
			<textarea rows="3" cols="80" name="expected_NUMI_NUMJ"></textarea>
			<div style="height:1px; visibility:hidden; margin:0;"></div>
		</span>
	</span>
	<span id="at_template" style="display: none;">
		<span id="attabela_NUMI">
			<table cellspacing="0" cellpadding="0" border="1" bgcolor="#ffffff">
			<tr bgcolor="#ffbdbd" style="border: 2px solid gray;">
				<td class="tekst celija smaller stronger plava">Autotest <font color="red" id="atbr_NUMI">NUMI</font></td>
				<td align="left" class="tekst celija">
				<input type="button" value="Obriši ovaj autotest" id="atbris_NUMI" onclick="atbrisi(this.id);">
				<input type="button" onclick="naVrh();" value="Scroll top">
				<input type="button" onclick="naDno();" value="Scroll bottom">
				<input type="button" value="Dodaj novi autotest" onclick="dodavanjeata();">						
				</td>
			</tr>
			<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
				<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Zahtijevaj simbole<br>(niz stringova kao: "1", "2")</td>	
				<td class="tekst celija smaller stronger" align="left">
				<input type="text" value='' name="require_symbols_NUMI" style="width: 700px;">
				</td>		
			</tr>
			<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
				<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Zamijeni simbole<br>(niz stringova kao: "1", "2")</td>	
				<td class="tekst celija smaller stronger" align="left">
				<input type="text" value='' name="replace_symbols_NUMI" style="width: 700px;">
				</td>		
			</tr>
			<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
				<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Globalno zaglavlje</td>	
				<td class="tekst celija smaller stronger" align="left">
					<textarea rows="2" cols="80" name="global_top_NUMI"></textarea>
				</td>		
			</tr>
			<tr>
				<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Globalni opseg iznad maina</td>	
				<td class="tekst celija smaller stronger" align="left">
					<textarea rows="2" cols="80" name="global_above_main_NUMI"></textarea>
				</td>		
			</tr>
			<tr>
				<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Kod</td>	
				<td class="tekst celija smaller stronger" align="left">
					<textarea rows="6" cols="80" name="code_NUMI"></textarea>
				</td>		
			</tr>				
			<tr>
				<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Ulaz</td>	
				<td class="tekst celija smaller stronger" align="left">
					<textarea rows="2" cols="80" name="stdin_NUMI"></textarea>
				</td>		
			</tr>
			<tr>
				<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;" bgcolor="#fafdbb">Ocekivani izlaz</td>
				<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;" id="cell_NUMI">
					<input type="hidden" value="1" name="brvar_NUMI">
					<span id="variant_NUMI_1">
						<div style="height:1px; visibility:hidden; margin:0;"></div>
						<font class="plava">Varijanta <span id="br_NUMI_1">1</span></font>
						<input type="button" value="Obriši" id="bris_NUMI_1" onclick="brisanjeVarijante(this.id);">
						<br>
						<textarea rows="3" cols="80" name="expected_NUMI_1"></textarea>
						<div style="height:1px; visibility:hidden; margin:0;"></div>
					</span>
				</td>
			</tr>
			<tr>
				<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;" bgcolor="#fafdbb">&nbsp;</td>	
				<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;">
					<input type="button" value="Dodaj varijantu" id="dodvar_NUMI" onclick="dodavanjeVarijante(this.id);">
				</td>
			</tr>	
			<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
				<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">
				Vrijeme cekanja
				<input type="text" value='10' name="timeout_NUMI" style="width: 100px;">
				Memorija
				<input type="text" value='1000000' name="vmem_NUMI" style="width: 100px;">
				</td>					
			</tr>
			<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
				<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">
				Ocekuje se izuzetak	
				<input type="checkbox" name="expected_exception_NUMI"> |
				Ocekuje se krah
				<input type="checkbox" name="expected_crash_NUMI"> |
				Ignoriši prazno
				<input type="checkbox" name="ignore_whitespace_NUMI"> |
				Regex
				<input type="checkbox" name="regex_NUMI"> |
				Podstring
				<input type="checkbox" name="substring_NUMI">
				</td>
			</tr>			
			</table><br>						
		</span>
	</span>
	
	<form action="api.php" method="post" id="brisanjeFajla">
		<input type="hidden" name="mod" value="1">
		<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
		<input type="hidden" value="<?php print $fileData; ?>" name="fileData" id="fileData">
	</form>
	<form action="preview.php" method="post" id="pregledFajla">
		<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
		<input type="hidden" id="fileData" name="fileData" value="<?php print $fileData; ?>">
	</form>

	<form action="api.php" method="post" id="editovanjeFajla">
	<input type="hidden" value="0" name="mod" id="mod">
	<input type="hidden" name="adv" value="<?php print $advanced; ?>" >
	<input type="hidden" value="<?php print $fileData; ?>" name="fileData" id="fileData">
	<table cellspacing="0" cellpadding="0" border="1">
		<tr bgcolor="#dddddd" style="border: 2px solid gray;">
			<td class="tekst celija smaller stronger plava" align="left">Zajedničke postavke za AT-ove ispod</td>
			<td align="left" class="tekst celija">
			<input type='button' onclick="document.getElementById('pregledFajla').submit();" value='Pregledaj sve'>
			<input type="button" value="Obriši fajl sa autotestovima" title="Brisanje fajla sa autotestovima" 
			onclick="document.getElementById('brisanjeFajla').submit();">
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
	</table><br>
	<input type="hidden" value="<?php print $brATova; ?>" name="numateova">
	<span id="sviATovi">
		<?php
			for ($i=1; $i<=$brATova; $i++) {
				$ovajAT=$json["test_specifications"][$i-1];			
				?>
					<span id="attabela_<?php print $i; ?>">
					<table cellspacing="0" cellpadding="0" border="1" bgcolor="#ffffff">
					<tr bgcolor="#ffbdbd" style="border: 2px solid gray;">
						<td class="tekst celija smaller stronger plava">Autotest <font color="red" id="atbr_<?php print $i; ?>"><?php print $i; ?></font></td>
						<td align="left" class="tekst celija">
						<input type="button" value="Obriši ovaj autotest" id="atbris_<?php print $i; ?>" onclick="atbrisi(this.id);">
						<input type="button" onclick="naVrh();" value="Scroll top">
						<input type="button" onclick="naDno();" value="Scroll bottom">
						<input type="button" value="Dodaj novi autotest" onclick="dodavanjeata();">					
						</td>
					</tr>
					<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
						<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Zahtijevaj simbole<br>(niz stringova kao: "1", "2")</td>	
						<td class="tekst celija smaller stronger" align="left">
						<input type="text" value='<?php 
							$require_symbols=$ovajAT["require_symbols"];
							for ($j=0; $j<count($require_symbols); $j++) {
								print "\"".$require_symbols[$j]."\"";
								if ($j<count($require_symbols)-1) print ", ";
							}
						?>' name="require_symbols_<?php print $i; ?>" style="width: 700px;">
						</td>		
					</tr>
					<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
						<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Zamijeni simbole<br>(niz stringova kao: "1", "2")</td>	
						<td class="tekst celija smaller stronger" align="left">
						<input type="text" value='<?php 
							$replace_symbols=$ovajAT["replace_symbols"];
							for ($j=0; $j<count($replace_symbols); $j++) {
								print "\"".$replace_symbols[$j]."\"";
								if ($j<count($replace_symbols)-1) print ", ";
							}
						?>' name="replace_symbols_<?php print $i; ?>" style="width: 700px;">
						</td>		
					</tr>
					<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
						<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Globalno zaglavlje</td>	
						<td class="tekst celija smaller stronger" align="left">
							<textarea rows="2" cols="80" name="global_top_<?php print $i; ?>"><?php print $ovajAT["global_top"] ?></textarea>
						</td>		
					</tr>
					<tr>
						<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Globalni opseg iznad maina</td>	
						<td class="tekst celija smaller stronger" align="left">
							<textarea rows="2" cols="80" name="global_above_main_<?php print $i; ?>"><?php print $ovajAT["global_above_main"] ?></textarea>
						</td>		
					</tr>
					<tr>
						<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Kod</td>	
						<td class="tekst celija smaller stronger" align="left">
							<textarea rows="6" cols="80" name="code_<?php print $i; ?>"><?php print $ovajAT["code"] ?></textarea>
						</td>		
					</tr>
					<tr>
						<td class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">Ulaz</td>	
						<td class="tekst celija smaller stronger" align="left">
							<textarea rows="2" cols="80" name="stdin_<?php print $i; ?>"><?php print $ovajAT["running_params"]["stdin"]; ?></textarea>
						</td>		
					</tr>
					<tr>
						<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;" bgcolor="#fafdbb">Očekivani izlaz</td>
						<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;" id="cell_<?php print $i; ?>">
						<?php
							$expected=$ovajAT["expected"];
							?>
							<input type="hidden" value="<?php print (count($expected)); ?>" name="brvar_<?php print $i; ?>">
							<?php
							for ($j=0; $j<count($expected); $j++) {							
								?>
									<span id="variant_<?php print $i; ?>_<?php print ($j+1); ?>">
										<div style="height:1px; visibility:hidden; margin:0;"></div>
										<font class="plava">Varijanta <span id="br_<?php print $i; ?>_<?php print ($j+1); ?>"><?php print ($j+1); ?></span></font>
										<input type="button" value="Obriši" id="bris_<?php print $i; ?>_<?php print ($j+1); ?>" onclick="brisanjeVarijante(this.id);">
										<br>
										<textarea rows="3" cols="80" name="expected_<?php print $i; ?>_<?php print ($j+1); ?>"><?php print replace_ln_n($expected[$j]); ?></textarea>
										<div style="height:1px; visibility:hidden; margin:0;"></div>
									</span>
								<?php
							}
						?>
						</td>
					</tr>
					<tr>
						<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;" bgcolor="#fafdbb">&nbsp;</td>	
						<td class="tekst celija smaller stronger" align="left" style="border-top: none; border-bottom: none;">
							<input type="button" value="Dodaj varijantu" id="dodvar_<?php print $i; ?>" onclick="dodavanjeVarijante(this.id);">
						</td>
					</tr>
					<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
						<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">
						Vrijeme čekanja
						<input type="text" value='<?php print $ovajAT["running_params"]["timeout"]; ?>' name="timeout_<?php print $i; ?>" style="width: 100px;">
						Memorija
						<input type="text" value='<?php print $ovajAT["running_params"]["vmem"]; ?>' name="vmem_<?php print $i; ?>" style="width: 100px;">
						</td>			
					</tr>
					<tr name="adv_display" style="<?php if (!$advanced) print "display: none;"; ?>">
						<td colspan=2 class="tekst celija smaller stronger" align="left" bgcolor="#fafdbb">
						Ocekuje se izuzetak
						<?php if ($ovajAT["expected_exception"]=="true") print "<input type='checkbox' checked name='expected_exception_".$i."'>"; else print "<input type='checkbox' name='expected_exception_".$i."'>"; ?> |
						Ocekuje se krah
						<?php if ($ovajAT["expected_crash"]=="true") print "<input type='checkbox' checked name='expected_crash_".$i."'>"; else print "<input type='checkbox' name='expected_crash_".$i."'>"; ?> |
						Ignoriši prazno
						<?php if ($ovajAT["ignore_whitespace"]=="true") print "<input type='checkbox' checked name='ignore_whitespace_".$i."'>"; else print "<input type='checkbox' name='ignore_whitespace_".$i."'>"; ?> |
						Regex
						<?php if ($ovajAT["regex"]=="true") print "<input type='checkbox' checked name='regex_".$i."'>"; else print "<input type='checkbox' name='regex_".$i."'>"; ?> |
						Podstring
						<?php if ($ovajAT["substring"]=="true") print "<input type='checkbox' checked name='substring_".$i."'>"; else print "<input type='checkbox' name='substring_".$i."'>"; ?>
						</td>	
					</tr>
					</table><br>						
					</span>
				<?php
			}
		?>
	</span>
	<div style="height:1px; visibility:hidden; margin:0;"></div>
	<input type="button" value="Dodaj novi autotest" onclick="dodavanjeata();"><br><br>
	<input type="submit" value="Potvrdi sve izmjene">
	</form>	
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
</body>
</html>