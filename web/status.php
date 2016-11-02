<?php

header('Content-Encoding: none;');
session_start();

if (isset($_SESSION['login'])) {
	$login = $_SESSION['login'];
	$session_id = $_SESSION['server_session'];
	if (preg_match("/[a-zA-Z0-9]/",$login)) $prijavljen = true;
} else {
	header("Location: index.php");
}

require_once("../lib/config.php");

$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));
$realname = $users[$login]['realname'];
if (empty($realname)) $realname = $login;

?>
<html>
<head>
	<title>C9 Dashboard</title>
	<link rel="stylesheet" href="static/css/dashboard.css">
	<link rel="stylesheet" href="static/css/login.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<script>
			var currentUserLogin='<?=$login?>';
			var diskError = <?=$conf_limit_diskspace?>;
			var diskWarn = <?=$conf_diskspace_cleanup?>;
			var loadError = <?=$conf_limit_loadavg?>;
			var loadWarn = <?=$conf_limit_loadavg_web?>;
			var memError = <?=$conf_limit_memory_web?>;
			var memWarn = <?=$conf_memory_emergency?>;
			var userError = <?=$conf_limit_users?>;
			var actUserError = <?=$conf_limit_active_users?>;
			var actUserWarn = <?=$conf_limit_users_web?>;
	</script>
	<script type="text/javascript" src="static/js/dashboard.js"></script>
</head>
<body onload="setupTimeouts();">
	<h1>C9ETF WebIDE Dashboard - <?=$realname?></h1>

	<div id="logout"><h2><a href="index.php?logout"><img src="static/images/exit.png" width="32" height="32" align="center"> Logout</a></h2></div>

	<div id="left_column">
		<div id="webide_status" class="box">
			<p class="box_title">Vaš WebIDE:</p>
			<div id="webide_icon">
				<div id="webide_status_icon"></div>
			</div>
			<p class="status_msg" id="webide_status_msg">Server se startuje...</p>
		</div>
		<div id="server_stats" class="box">
			<img src="static/images/busy-dark-24x24.gif" width="24" height="24" align="center"> Učitavam sistemske informacije...
		</div>
	</div>
	<div id="center_column">
		<div id="system_msg_box" class="box">
			<h2 id="system_msg_title">Ovo je važno!</h2>
			<p id="system_msg_text">Ovo je važno!</p>
		</div>
		<div id="news_box" class="box">
			<h2>Novosti</h2>
			<div id="news_content">
			<img src="static/images/busy-dark-24x24.gif" width="24" height="24" align="center"> Učitavam novosti...
			</div>
			<div id="news_click" style="display: none"><a href="" onclick="return showMoreNews();"  style="color: #aaf">Prikaži više novosti...</a></div>
		</div>
	</div>
	<div id="right_column">
		<div id="users_box" class="box">
			<p class="box_title">Trenutno prijavljeni (<span id="users_number">0</span>):</p>
			<div id="users_content">
			<img src="static/images/busy-dark-24x24.gif" width="24" height="24" align="center"> Učitavam korisnike...
			</div>
		</div>
	</div>
</body>
</html>
