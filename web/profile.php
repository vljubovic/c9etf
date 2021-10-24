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

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

$realname = "";
if (array_key_exists("realname", $users[$login]))
	$realname = $users[$login]['realname'];

$email = "";
if (array_key_exists("email", $users[$login]))
	$email = $users[$login]['email'];

if (file_exists( $conf_base_path . "/localusers/" . $login))
	$changePasswordUrl = "change-password.php";
else
	$changePasswordUrl = "https://mail.etf.unsa.ba";

$msg = "";
if (isset($_REQUEST['action']) && $_REQUEST['action'] == "submit") {
	$m_email = $_POST['email'];
	if (!filter_var($m_email, FILTER_VALIDATE_EMAIL)) {
		$msg = "<p style=\"color:red;\">Invalid email format</p>"; 
	} else {
		$realname = preg_replace('/[^A-Za-z0-9_\-\.čćšđžČĆŠĐŽ]/', ' ', $_POST['realname']);
		$email = $m_email;
		`sudo $conf_base_path/bin/webidectl change-user $login realname '$realname'`;
		`sudo $conf_base_path/bin/webidectl change-user $login email '$email'`;
		$msg = "<p>Your profile has been updated.</p>";
	}
}

$gravatar = md5(trim(strtolower($email)));

?>
<html>
<head>
	<title>Your profile</title>
	<link rel="stylesheet" href="static/css/dashboard.css">
	<link rel="stylesheet" href="static/css/login.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
	<h1>C9ETF WebIDE - <?=$realname?> - Your profile</h1>

	<div id="logout"><h2><a href="status.php"><i class="fa fa-tachometer"></i> Dashboard</a> * <a href="index.php?logout"><i class="fa fa-power-off"></i> Logout</a></h2></div>

	<div id="profile" class="box">
		<?php
		if (!empty(trim($email))) {
			?>
		<div id="gravatar" style="position: absolute; float:left; margin-left:20px; margin-right:20px">
			<p class="box_title">Click on avatar to change it:</p>
			<a href="https://www.gravatar.com/<?=$gravatar?>" target="_blank"><img src="https://www.gravatar.com/avatar/<?=$gravatar?>?s=200&amp;d=retro" width="200" height="200" /></a><br>
			<p>Powered by <a href="https://gravatar.com" target="_blank">Gravatar</a></p>
		</div>
		<?php } ?>
		
		<?=$msg?>
		
		<form action="profile.php" method="POST">
		<input type="hidden" name="action" value="submit">
		
		<p class="box_title">User ID:</p>
		<input type="text" readonly value="<?=$login?>">
		
		<br><br>
		<p class="box_title"><input type="button" onclick="window.location.href='<?=$changePasswordUrl?>';" style="color:black; padding:4px;" value="Change password"></p>
		
		<p class="box_title">Real name:</p>
		<input type="text" name="realname" id="realname" value="<?=$realname?>">
		
		<p class="box_title">E-mail address:</p>
		<input type="text" name="email" id="realname" value="<?=$email?>">
		
		<p class="box_title"><input type="submit" style="color:black; padding:4px;" value="Change"></p>
		</form>
	</div>
	
</body>
</html>
