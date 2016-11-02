<html>
<head>
<title>ETF WebIDE</title>
<link rel="stylesheet" href="static/css/login.css">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body style="margin: 10px">

<?php

if (isset($_REQUEST['slanje'])) {
	if (!strstr($_REQUEST['email'], '@')) {
		?>
		<h1><font color="red">Greška - email adresa ne sadrži znak at '@'</font></h1>
		<h1>Unijeli ste: <?php
			print htmlentities($_REQUEST['email']);
		?></h1>
		<?php
	} else if (strstr($_REQUEST['username'], " ")) {
		?>
		<h1><font color="red">Greška - nije dozvoljen znak razmaka u korisničkom imenu</font></h1>
		<h1>Unijeli ste: <?php
			print htmlentities($_REQUEST['username']);
		?></h1>
		<?php
	} else {
		file_put_contents("/usr/local/webide/register", $_REQUEST['imeprezime'] . ", " . $_REQUEST['email'] . ", " . $_REQUEST['username'] . ", " . $_REQUEST['status'] . ", " . $_REQUEST['informacije'] . ", " . date("d.m.Y H:i:s") . "\n", FILE_APPEND);
		?>
		<h1>Hvala na registraciji. Bićete kontaktirani narednih dana</h1>
		<p><a href="index.php">Nazad na login stranicu</a></p>
		<?php
	}

} else {

?>


<h2>Registrujte se za C9@ETF WebIDE</h2>
<form action="register.php" method="POST">
<p>Vaše ime i prezime:<br>
<input type="text" name="imeprezime"></p>

<p>Kontakt e-mail:<br>
<input type="text" name="email"></p>

<p>Željeno korisničko ime:<br>
<input type="text" name="username"></p>

<p>Vaš status:<br>
<input type="radio" name="status" value="student_etf"> Student/ica ETFa Sarajevo<br>
<input type="radio" name="status" value="student_drugi"> Student/ica nekog drugog fakulteta (navedite ispod)<br>
<input type="radio" name="status" value="profesor_etf"> Nastavno osoblje ETFa Sarajevo (profesor/ica, asistent/ica...)<br>
<input type="radio" name="status" value="profesor_drugi"> Nastavno osoblje nekog drugog fakulteta (navedite ispod)<br>
<input type="radio" name="status" value="bivsi_etf"> Bivši student/ica ili saradnik/ica ETFa Sarajevo<br>
<input type="radio" name="status" value="ostalo" selected> Ostalo</p>

<p>Kako ste čuli za WebIDE? Navedite još neke informacije o sebi:<br>
<textarea name="informacije" rows="10" cols="80"></textarea></p>

<input type="submit" name="slanje" value=" Pošalji zahtjev ">

</form>

<p><a href="index.php">Nazad na login stranicu</a></p>

<?php

}

?>


</body>
</html>