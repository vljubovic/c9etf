<?php

  session_start();
  require_once("../../lib/config.php");
  require_once("../../lib/webidelib.php");
  require_once("../login.php");
  require_once("../admin/lib.php");
  require_once("../classes/Course.php");

  eval(file_get_contents("../../users"));
  require_once("../phpwebide/phpwebide.php");


  // Verify session and permissions, set headers

  $logged_in = false;
  if (isset($_SESSION['login'])) {
    $login = $_SESSION['login'];
    $session_id = $_SESSION['server_session'];
    if (preg_match("/[a-zA-Z0-9]/",$login)) $logged_in = true;
  }

  if (!$logged_in) {
    $result = array ('success' => "false", "message" => "You're not logged in");
    print json_encode($result);
    return 0;
  }

  session_write_close();

  // If user is not admin, they can only access their own files
  if (in_array($login, $conf_admin_users) && isset($_GET['user']))
    $username = escape_filename($_GET['user']);
  else
    $username = $login;

  if (isset($_REQUEST['year'])) $year = intval($_REQUEST['year']); else $year = $conf_current_year;
  $courses = Course::forAdmin($login, $year);
  
  function coursecmp($a, $b) { return $a->name > $b->name; }
  
  usort($courses, "coursecmp");


  ini_set('default_charset', 'UTF-8');
  header('Content-Type: application/json; charset=UTF-8');
  $result = array();

  if ($error == "") {
    $result['success'] = true;
    $result['message'] = 'OK';
    $result['courses'] = $courses;
  } else {
    $result['success'] = false;
    $result['message'] = $error;
  }

  print json_encode($result);
?>
