<?php

session_start();
$logged_in = false;

if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
}

if (!$logged_in) {
	header("Location: index.php");
	return;
}

session_write_close();

require_once("../lib/config.php");
require_once("../lib/webidelib.php");

$msg = "";
if (isset($_REQUEST['action']) && $_REQUEST['action'] == "submit") {
	$password = $_POST['password'];
	$repeat = $_POST['repeat'];
	
	if ($password !== $repeat) {
		$msg = "<p style=\"color:red;\">Password doesn't match</p>"; 
	} else if (strlen($password) < 2 || !preg_match("/[a-z]/", $password) || !preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
		$msg = "<p style=\"color:red;\">Password too simple</p><p>Password should have at least 8 characters, with at least one upper case, one lower case letter and one digit.</p>"; 
	} else {
		$login_efn = escape_filename($login);
		$login_esa = escapeshellarg($login);
		$oldpass  = escapeshellarg($_POST['old_password']);
		$result = `echo $oldpass | htpasswd -vi $conf_base_path/localusers/$login_efn $login_esa 2>&1`;
		
		if (strstr($result, "correct")) { // FIXME
			$pass_esa = escapeshellarg($password);
			// add-local-user will overwrite the password
			`sudo $conf_base_path/bin/webidectl add-local-user $login $pass_esa`;
			$msg = "<p>Password is changed</p>"; 
			
		} else {
			$msg = "<p style=\"color:red;\">Old password is incorrect</p>"; 
		}
	}
}


?>
<html>
<head>
	<title>Your profile</title>
	<link rel="stylesheet" href="static/css/dashboard.css">
	<link rel="stylesheet" href="static/css/login.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
	<h1>C9ETF WebIDE - <?=$login?> - Change password</h1>

	<div id="logout"><h2><a href="status.php"><i class="fa fa-tachometer"></i> Dashboard</a> * <a href="index.php?logout"><i class="fa fa-power-off"></i> Logout</a></h2></div>

	<div id="profile" class="box">
		<?=$msg?>
		
		<form action="profile.php" method="POST">
		<input type="hidden" name="action" value="submit">
		
		<p class="box_title">Old password:</p>
		<input type="password" name="old_password" id="old_password">
		
		<p class="box_title">New password:</p>
		<input type="password" name="password" id="password">
		
		<p class="box_title">Repeat new password:</p>
		<input type="password" name="repeat" id="repeat">
		
		<p class="box_title"><input type="submit" style="color:black; padding:4px;" value="Change"></p>
		</form>
	</div>
	
</body>
</html>
