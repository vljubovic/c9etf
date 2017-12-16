<?php

$rustart = getrusage();

session_start();

?>
<html>
<head>
	<title>ETF WebIDE</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="static/css/admin.css">
	<link rel="stylesheet" href="static/css/admin-log.css">
	<link rel="stylesheet" href="static/css/phpwebide.css">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
	<script src="static/js/tools.js" type="text/javascript" charset="utf-8"></script>
</head>


<body bgcolor="#ffffff">
	<DIV id="msgDisplay"></div>
	
	<!-- Progress bar window -->
	<div id="progressWindow">
		<div id="progressBarMsg"></div>
		<div id="myProgress">
			<div id="myBar">
				<div id="progressBarLabel">10%</div>
			</div>
		</div>
	</div>

<?php

require_once("../lib/config.php");
require_once("../lib/webidelib.php");

require_once("login.php");

eval(file_get_contents("../users"));


// Library
require_once("admin/lib.php");


// Admin modules
require_once("admin/courses.php");
require_once("admin/stats.php");
require_once("admin/user_table.php");

require_once("admin/notices.php");

require_once("assignment/table.php");
require_once("assignment/files.php");

require_once("phpwebide/phpwebide.php");

require_once("zamger/status_na_predmetu.php");

$logged_in = false;
$error = "";


// LOGIN
if (isset($_POST['login'])) {
	$login = $_POST['login'];
	
	$error = login($login, $_POST['password']);
	if ($error == "") {
		$logged_in = true;
		admin_log("login");
		if ($conf_zamger) {
			require_once("zamger/update_all.php");
			zamger_update_all($login);
		}
	} else
		admin_log("unknown user or wrong password");
} else {
	if (isset($_SESSION['login'])) {
		$login = $_SESSION['login'];
		$session_id = $_SESSION['server_session'];
		if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
	}
}

// This must be here because session can be inherited from index.php
if ($logged_in && !in_array($login, $conf_admin_users)) {
	$error = "Access not allowed. AA";
	admin_log("access not allowed");
	logout();
	$logged_in = false;
}



if ($logged_in) {
	?>
	<p id="p-login">Login: <span id="username"><?=$login?></span> - <a href="admin.php?logout">logout</a></p>
	<?php

	// ACTIONS with redirect, common for all admin modules
	// These actions mostly invoke webidectl which is run through sudo - take care!

	$msg = $error = "";
	// Logout admin
	if (isset($_REQUEST['logout'])) {
		$msg = "You are now logged out.";
		admin_log("logout");
		logout();
	}
	
	if (isset($_REQUEST['action'])) {
		// Logout user
		if ($_REQUEST['action'] == "logout") {
			$user = escapeshellarg($_REQUEST['user']);
			$msg = "User $user is logged out from system.";
			admin_log("logout $user");
			proc_close(proc_open("sudo $conf_base_path/bin/webidectl logout $user &", array(), $foo));
		}
		
		// Reset config for user
		if ($_REQUEST['action'] == "reset-conf") {
			$user = escapeshellarg($_REQUEST['user']);
			$output = exec("sudo $conf_base_path/bin/webidectl reset-config $user");
			if (strstr($output, "ERROR")) {
				$error = "There was an error!<br>$output";
			} else {
				$msg = "Configuration was reset for user $user.";
				admin_log("reset-conf $user");
			}
		}
		
		// Update usage statistics for user
		if ($_REQUEST['action'] == "refresh-stats") {
			$loadavg = `cat /proc/loadavg | cut -d " " -f 1`;
			if ($loadavg > $loadavg_limit) {
				print "Updating statistics is temporarily disabled due to high server load. Please try again later.";
				return 0;
			}
			$user = escapeshellarg($_REQUEST['user']);
			// We can't get progress info (TODO?)
			$msg = "User statistics updated.";
			admin_log("refresh stats $user");
			proc_close(proc_open("sudo $conf_base_path/bin/userstats $user &", array(), $foo));
		}
		
		// Update usage statistics for all users in a group
		if ($_REQUEST['action'] == "refresh-stats-group") {
			$loadavg = `cat /proc/loadavg | cut -d " " -f 1`;
			if ($loadavg > $loadavg_limit) {
				print "Updating statistics is temporarily disabled due to high server load. Please try again later.";
				return 0;
			}
			$group = $_REQUEST['group'];
			$msg = "Group statistics updated.";
			admin_log("refresh stats group $group");
			
			print "Statistics for group are now being updated... this can take some time.";
			
			$group_path = $conf_data_path . "/groups/$group";
			if (file_exists($group_path))
				$group_data = json_decode(file_get_contents($group_path), true);
			else {
				niceerror("Unknown group");
				return 0;
			}
		
			foreach($group_data['members'] as $login => $name) {
				print "<p>Updating statistics for $name...</p>\n";
				proc_close(proc_open("sudo $conf_base_path/bin/userstats $login &", array(), $foo));
				sleep(1); // We give users some time to perform other tasks
			}
		}
		
		// Clear server
		if ($_REQUEST['action'] == "clear_server") {
			$msg = "Server is now cleared";
			admin_log("clear server");
			proc_close(proc_open("sudo $conf_base_path/bin/webidectl clear-server &", array(), $foo));
		}
		
		// Restore file from older revision
		if ($_REQUEST['action'] == "restore_revision") {
			$user = escapeshellarg(str_replace("../", "", $_REQUEST['user']));
			$path = escapeshellarg($_REQUEST['path']);
			$svn_rev = intval($_REQUEST['svn_rev']);
			
			$output = `sudo $conf_base_path/bin/webidectl svnrestore $user $path $svn_rev`;
			if (!empty($output)) $msg = "Possible error: $output";
			
			$_REQUEST['return'] = "user=".$_REQUEST['user']."&path=".$_REQUEST['path'];

			$msg = "Revision $svn_rev of file $path is restored (user $user)";
			admin_log("restore_revision $user $path $svn_rev");
		}
	}

	// Display message with redirect
	if ($msg != "" || $error != "") {
		$return = "";
		if (isset($_REQUEST['return'])) {
			$return = "?" . htmlentities($_REQUEST['return'], ENT_QUOTES);
			$return = str_replace("&amp;", "&", $return);
		}
			
		?>
		<p style="color: green; weight: bold"><?=$msg?></p>
		<p style="color: red; weight: bold"><?=$error?></p>
		<script language="JavaScript">
		setTimeout(function(){ location.href='/admin.php<?=$return?>'; }, 2000);
		</script>
		<?php
		return;
	}
	
	
	// VIEW modules
	
	if (isset($_REQUEST['stats'])) {
		admin_log("stats");
		admin_stats();
	}


	
	else if (isset($_REQUEST['buildservice_stats'])) {
		admin_log("buildservice stats");
		admin_bsstats();
	}
	
	else if (isset($_REQUEST['exam_stats'])) {
		admin_log("exam stats");
		admin_exam_stats();
	}
	
	
	// Show data for single user
	else if (isset($_REQUEST['user'])) {
		$user = str_replace("../", "", $_REQUEST['user']);
		if (isset($_REQUEST['path'])) {
			$path = $_REQUEST['path'];
			admin_log("user $user, path $path");
		} else {
			$path = "TP";
			$_REQUEST['path'] = "TP";
			admin_log("user $user");
		}
		$backlink = "";
		if (isset($_REQUEST['backlink']))
			$backlink = htmlentities($_REQUEST['backlink'], ENT_QUOTES);
		
		$user_realname = $users[$user]['realname'];
		if (empty($user_realname)) $user_realname=$user;
		
		// Show modules: phpwebide, log and user stats
		
		?>
		<p id="p-return"><a href="admin.php?<?=$backlink?>">Return to group</a></p>
		
		<h1><?=$user_realname?></h1>
		<p>&nbsp;</p>
		<?php
		
		phpwebide($user, $path, false, true);
	}
	
	// Send message to all users
	else if (isset($_REQUEST['broadcast'])) {
		$msg = $_REQUEST['broadcast'];
		proc_close(proc_open("sudo $conf_base_path/bin/webidectl broadcast \"$msg\" &", array(), $foo));
		admin_log("broadcast $msg");
		nicemessage("Message sent");
		exit;
	}
	
	// Collaborate with users
	else if (isset($_REQUEST['collaborate'])) {
		$user = $_REQUEST['collaborate'];
		$output = `sudo $conf_base_path/bin/webidectl collaborate $login $user`;
		if (substr($output,0,6) == "ERROR:") {
			niceerror("User is not currently online");
		} else {
			$target = $login . "-" . $user;
			?>
			<p>Please wait while we prepare the collaboration interface...</p>
			<SCRIPT>
			setTimeout(function(){ window.location.replace("/<?=$target?>/"); }, 1000);
			</SCRIPT>
			<?php
			admin_log("collaborate $user");
		}
		exit;
	}
	
	// Show all users in a group
	else if (isset($_REQUEST['group'])) {
		$group = basename($_REQUEST['group']);
		
		$group_path = $conf_data_path . "/groups/$group";
		if (file_exists($group_path))
			$group_data = json_decode(file_get_contents($group_path), true);
		else {
			niceerror("Unknown group");
			return 0;
		}
		
		if ($_REQUEST['group'] == "active") {
			$group_name = "Active users";
			foreach($users as $username => $userdata) {
				if ($userdata['status'] == "active")
					$members[$username] = $userdata['realname'];
			}
			
		} else {
			$members = $group_data['members'];
			$group_name = $group_data['name'];
		}
		
		admin_log("group $group_name");
		
		$backlink = "";
		if (isset($_REQUEST['backlink']))
			$backlink = htmlentities($_REQUEST['backlink'], ENT_QUOTES);
		
		$add_title = "";
		if (isset($_REQUEST['path'])) $add_title = " - " . $_REQUEST['path'];
		?>
		<p id="p-return"><a href="admin.php?<?=$backlink?>">Return to course page</a></p>
		
		<h1><?=$group_name.$add_title?></h1>
		<?php
		
		$link_here = urlencode("group=$group");
		
		if ($group > 0) {
			?>
			<p><a href="admin.php?action=refresh-stats-group&amp;group=<?=$group?>&amp;return=<?=$link_here?>">
				<i class="fa fa-refresh"></i> Update stats for group
			</a></p>
			<?php
		} else {
			print "<p>&nbsp;</p>\n";
		}
		
		admin_user_table($group, $members, $link_here);
	}
	
	// Currently active users
	else if (isset($_REQUEST['active'])) {
		?>
		<p id="p-return"><a href="admin.php">Return to list of courses</a></p>
		<h1>Active users</h1>
		<script type="text/javascript" src="/static/js/activity.js"></script>
		<SCRIPT>
		var global_activity = []; // Global array contains last activity for each user
		var last_line = 0;
		var frequency = 500; // Update frequency
		var timenow = 0;
		initActive(function(item) {
			global_activity[item['username']] = item;
		}, frequency);
		setInterval(renderResults, frequency);

		</SCRIPT>
		<ul>
			<p>Load average: <span id="loadavg"></div></p>
			<div id="activeUsers"></div>
			</p>
		</ul>
		<?php
		admin_log("active users");
	}
	
	// Page for a single course
	else if (isset($_REQUEST['course'])) {
		$course = intval($_REQUEST['course']);
		$year = intval($_REQUEST['year']);
		$backlink = "course=$course&year=$year";
		
		if (isset($_REQUEST['X'])) {
			$external = true;
			$course_path = "X$course"."_$year";
			$backlink .= "&X";
		} else {
			$external = false;
			$course_path = "$course"."_$year";
		}
		
		// Get course name (and possibly other info)
		$courses = admin_courses();
		$course_data = array();
		foreach($courses as $c) {
			if ($external && $c['type'] != "external") continue;
			if (!$external && $c['type'] == "external") continue;
			if ($c['id'] == $course) $course_data = $c;
		}
		
		$perms = admin_permissions($login);
		if (!empty($perms) && !in_array($course_path, $perms)) {
			admin_log("course $course_path access denied");
			niceerror("You are not allowed to access this course");
			print "<p>If this is a mistake, please contact administrator</p>\n";
			print "</body></html>\n";
			return 0;
		}
		
		// List of groups
		?>
		<p id="p-return"><a href="admin.php">Return to list of courses</a></p>
		<h1><?=$course_data['name']?></h1>

		<div id="group-list">
		<h2>Groups</h2>
		<ul class="groups">
		<?php
		
		$groups_path = $conf_data_path . "/$course_path/groups";
		$groups = json_decode(file_get_contents($groups_path), true);
		foreach ($groups as $group_id => $group_name) {
			?>
			<li><a class="grouplnk" href="admin.php?group=<?=$group_id?>&amp;path=<?=$course_data['abbrev']?>&amp;backlink=<?=urlencode($backlink)?>"><?=$group_name?></a></li>
			<?php
		}
		?>
			<li style="margin-top: 30px"><a class="grouplnk" href="admin.php?active=active&amp;path=<?=$course_data['abbrev']?>">Active users</a></li>
		</ul></p>
		</div>
		<?php
		
		
		// Notices
		?>
		<div id="notices">
		<h2>Notices</h2>
		<?php
		
		admin_notices($course, $year, $external);
		
		
		// Create assignments
		?>
		</div>
		
		<div id="assignments">
		<h2>Assignments</h2>
		<?php
		assignment_table($course, $year, $external);
		
		?>
		</div>
		
		<div id="files">
		<h2>Files</h2>
		<p>Here you can define default files that will be automatically created for all assignments on this course. You can change them for each assignment individually.</p>
		<p>If the text ===TITLE=== exists in a file it will be replaced with activity title.</p>
		<?php
		assignment_files($course, $year, $external);
		
		?>
		</div>
		<?php
	}
	
	else {
		?><h1>Select course</h1>
		<ul class="groups">
		<?php
				
		$courses = admin_courses();
		if (empty($courses) && !in_array($login, $conf_sysadmins)) {
			niceerror("There are no courses defined on system.");
			print "<p>Please contact the administrator to create some courses.</p>\n";
			return 0;
		}
	
		if (isset($_REQUEST['year'])) $year = intval($_REQUEST['year']); else $year = $conf_current_year;
		$perms = admin_permissions($login);
		
		function coursecmp($a, $b) { return $a['name']>$b['name']; }
		
		usort($courses, "coursecmp");
		
		foreach ($courses as $course) {
			if (!empty($perms)) {
				// Check permissions
				$c9id = $course['id'] . "_" . $year;
				if ($course['type'] == "external") $c9id = "X" . $c9id;
				$found = false;
				foreach($perms as $perm) {
					if ($perm == $c9id) $found = true;
				}
				if (!$found) continue;
			}
			
			$add = "";
			if ($course['type'] == "external") $add = "&amp;X";

			?>
			<li><a class="grouplnk" href="admin.php?course=<?=$course['id']?>&amp;year=<?=$year?><?=$add?>"><?=$course['name']?></a></li>
			<?php
		}
		?>
			<li style="margin-top: 30px"><a class="grouplnk" href="admin.php?active=active">Active users</a></li>
		</ul>
		<?php
		
		if (in_array($login, $conf_sysadmins)) {
			?>
			<p id="p-clear"><a href="admin.php?action=clear_server" onclick="return confirmation('Logout', '*ALL*');">Log out all users and clear server</a></p>
			<?php
		} else { print "<p></p>\n"; }
		
		?>
		<form action="admin.php" method="POST">
		Message to all users:<br>
		<input type="text" size="50" name="broadcast"> <input type="submit" value="Send"></form>
		<p id="p-stats"><a href="admin.php?stats">Usage statistics</a></p>
		
		<div style="position:absolute; top:200px; left:600px; width: 400px; border: 1px solid black; background: #ddd; font-size: small; padding: 5px" id="admin_news">
		<h2>Admin news</h2>
		<input type="button" value="Close" onclick="document.getElementById('admin_news').style.display='none';">
		</div>
		<?php
	}
	
	
// Script end
function rutime($ru, $rus, $index) {
    return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
     -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
}

$ru = getrusage();
/*echo "This process used " . rutime($ru, $rustart, "utime") .
    " ms for its computations\n";
echo "It spent " . rutime($ru, $rustart, "stime") .
    " ms in system calls\n";
*/
	
	?>

	<div id="copyright">Admin panel for C9 WebIDE by Vedran Ljubović<br>&copy; Elektrotehnički fakultet Sarajevo / Faculty of Electrical Engineering Sarajevo 2015-2017.</div>
	</body></html>
	<?php
	

	return 0;
}

?>


  <div class="site__container">

    <div class="grid__container">

      <form action="admin.php" method="post" class="form form--login">
        <h1>ETF WebIDE</h1>
        <?php if ($error !== "") print "<p style=\"color: red; font-weight: bold\">$error</p>\n"; ?>
        <div class="form__field">
          <label class="fontawesome-user" for="login__username"><span class="hidden">Username</span></label>
          <input id="login__username" name="login" type="text" class="form__input" placeholder="Username" required>
        </div>

        <div class="form__field">
          <label class="fontawesome-lock" for="login__password"><span class="hidden">Password</span></label>
          <input id="login__password" name="password" type="password" class="form__input" placeholder="Password" required>
        </div>

        <div class="form__field">
          <input type="submit" value="Go">
        </div>
	<!--p><a href="https://zamger.etf.unsa.ba">Zamger</a></p-->

      </form>

    </div>

  </div>
  <div id="copyright">Admin panel for C9 WebIDE by Vedran Ljubović<br>&copy; Elektrotehnički fakultet Sarajevo / Faculty of Electrical Engineering Sarajevo 2015-2017.</div>
</body>
</html>
