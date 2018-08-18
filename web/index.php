<?php

session_start();

require_once("../lib/config.php");
require_once("../lib/webidelib.php");
//require_once("config.php");
require_once("login.php");

$prijavljen = false;


// Radovi u toku
$radovi = "";
if (file_exists("$conf_base_path/razlog_nerada.txt") && !(isset($_POST['login']) && $_POST['login'] == "test"))
	$radovi = file_get_contents("$conf_base_path/razlog_nerada.txt");
if (preg_match("/\w/", $radovi)) {
	?>
	<html>
	<head>
	<title>ETF WebIDE</title>
	<link rel="stylesheet" href="static/css/login.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	</head>


	<body class="align">
		<h1>Radovi u toku</h1>
		<p>U toku su radovi zbog kojih je WebIDE privremeno nedostupan. Aktivnost će se nastaviti <?=$radovi?>. Molimo da budete strpljivi.</p>
		</body>
	</html>
	<?php
	return 0;
}

// Novosti
if (isset($_GET['novosti'])) {
	header("Location: novosti.php");
	return 0;
}

// Login
$greska = "";
if (isset($_POST['login'])) {
	$login = trim(strtolower($_POST['login']));
	$pass = $_POST['password'];
	$login_esa = escapeshellarg($login);
	$pass_esa = escapeshellarg($pass);
	$ip_address = $_SERVER['REMOTE_ADDR'];
	
	$users_file = $conf_base_path . "/users";
	eval(file_get_contents($users_file));
	
	$alreadyloggedin = false;
	//if (array_key_exists($login, $users) && $users[$login]["status"] == "active")
	//	$alreadyloggedin = true;
	
//	$alreadyloggedin = `grep $login_esa $conf_base_path/active_users`;

	// Da li je prekoračen kapacitet servera?
	if (!$alreadyloggedin) {
		$broj = `sudo $conf_base_path/bin/webidectl list-active | wc -l`;
		if ($broj > $conf_limit_users_web && $login != $admin_login) {
			print "Dostignut je maksimalan broj korisnika na serveru. Dodjite kasnije.";
			return;
		}
		
		$primload = `tail -1 /usr/local/webide/c9prim_stats.log | cut -d " " -f 3`;
		$secload = `tail -1 /usr/local/webide/c9sec_stats.log | cut -d " " -f 3`;
		$loadavg = `cat /proc/loadavg | cut -d " " -f 1`;
		if ($conf_limit_loadavg_web > 0 && $loadavg > $conf_limit_loadavg_web && $login != $admin_login) {
			print "Server je trenutno preopterecen. Dodjite kasnije. ($loadavg)";
			return;
		}
		
		$memtotal = intval(`cat /proc/meminfo | grep MemTotal | cut -c 17-25`);
		$memfree = intval(`cat /proc/meminfo | grep MemFree | cut -c 17-25`);
		$membuf = intval(`cat /proc/meminfo | grep Buffers | cut -c 17-25`);
		$memcach = intval(`cat /proc/meminfo | grep ^Cached | cut -c 17-25`);
		$memswaptotal = intval(`cat /proc/meminfo | grep SwapTotal | cut -c 17-25`);
		$memswapfree = intval(`cat /proc/meminfo | grep SwapFree | cut -c 17-25`);

		$memused = $memtotal - $memfree - $membuf - $memcach + $memswaptotal - $memswapfree;
		$memused = $memused / 1024;
		$memused = $memused / 1024;

		if ($memused > $conf_limit_memory_web) {
			print "Server je trenutno preopterecen. Dodjite kasnije. (".number_format($memused,2)." GB)";
			return;
		}
		
		if ($primload > $conf_limit_loadavg && $secload > $conf_limit_loadavg) {
			print "Server je trenutno preopterecen. Dodjite kasnije. ($primload , $secload)";
			return;
		}

	}

	$greska = "";
	if (in_array($login, $conf_deny_users))
		$greska = "Pristup vašem korisniku je trenutno zabranjen $conf_deny_reason. Kontaktirajte administratora ili dodjite kasnije.";
	if (!empty($conf_allow_users) && !in_array($login, $conf_allow_users))
		$greska = "Pristup vašem korisniku je trenutno zabranjen $conf_deny_reason. Kontaktirajte administratora ili dodjite kasnije.";
	

	if ($greska == "") {
		$greska = login($login, $pass);
	}
	
	
	// Check if user already logged in from a different IP address?
	if ($greska == "" && array_key_exists($login, $users) && $users[$login]["status"] == "active" && $users[$login]["ip_address"] != $ip_address) {
		?>
		<html>
		<head>
		<title>ETF WebIDE</title>
		<link rel="stylesheet" href="static/css/login.css">
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		</head>


		<body>
			<h1>Već ste prijavljeni sa drugog računara</h1>
			<p>Želite li da odjavite drugi računar kako biste mogli nastaviti?</p>
			<input type="button" value=" Da " onclick="javascript:window.location = 'index.php?logout';">
			<input type="button" value=" Ne " onclick="alert('Ne možemo nastaviti dalje dok se ne odjavite.'); window.location = 'index.php';">
			</body>
		</html>
		<?php
		return 0;
	}
	
	if ($greska == "") {
		unlink ("/tmp/error-output.txt");
		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
		);
		$output = array();
		$process = proc_open("sudo $conf_base_path/bin/webidectl login $login_esa $ip_address", $descriptorspec, $pipes);
		if (is_resource($process)) {
			fwrite($pipes[0], $pass);
			fclose($pipes[0]);
			$rez = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			proc_close($process);
		}
		header("Location: status.php");
		return;
	}
}

// Logout
if (isset($_REQUEST['logout'])) {
	$login = logout();
	$login_esa = escapeshellarg($login);
	if ($login != "") {
		?>
		<html>
		<head>
		<title>ETF WebIDE</title>
		<style>
		p {
			text-align: center;
			color: #606468; 
			font: 400 0.875rem/1.5 "Open Sans", sans-serif;
		}
		</style>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		</head>
		<body bgcolor="#222222">
		<h1>&nbsp;</h1>
		<p><img src="static/images/loading-logo.png"></p>
		<p>Dođite nam opet <?=$login?>!</p>
		<script language="JavaScript">
		setTimeout(function(){ location.href='/'; }, 2000);
		</script>
		<?php

		proc_close(proc_open("sudo $conf_base_path/bin/webidectl logout $login_esa &", array(), $foo));
		return;
	}
}

?>
<html>
<head>
	<title>ETF WebIDE</title>
	<link rel="stylesheet" href="static/css/style.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>


<body class="align">

  <div class="site__container">

    <div class="grid__container">
      <form action="index.php" method="post" class="form form--login">
        <h1>ETF WebIDE</h1>
        <?php if ($greska !== "") print "<p style=\"color: red; font-weight: bold\">$greska</p>\n"; ?>
        <div class="form__field">
          <label class="fontawesome-user" for="login__username"><span class="hidden">Username</span></label>
          <input id="login__username" name="login" type="text" class="form__input" placeholder="Username" required>
        </div>

        <div class="form__field">
          <label class="fontawesome-lock" for="login__password"><span class="hidden">Password</span></label>
          <input id="login__password" name="password" type="password" class="form__input" placeholder="Password" required>
        </div>

        <div class="form__field">
          <input type="submit" value="Kreni">
        </div>
	<p>Prijavite se sa <a href="https://zamger.etf.unsa.ba">Zamger</a> podacima,<br>
	ili se <a href="register.php">registrujte za novi account</a>.</p>
	<p>Novo: <a href="faq.php">Često postavljana pitanja (FAQ)</a>.</p>
      </form>

    </div>

  </div>
</body>
</html>
