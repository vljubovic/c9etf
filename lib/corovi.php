<?php


require(dirname(__FILE__) . "/../lib/config.php");
require(dirname(__FILE__) . "/../lib/webidelib.php");


$users_file = $conf_base_path . "/users";
eval(file_get_contents($users_file));

ksort($users);
$total = count($users);
$current=1;

foreach ($users as $username => $options) {
	print "$username ($current/$total):\n";
	$current++;
	$ws = setup_paths($username)['workspace'];
	if (!file_exists($ws)) continue;
	print run_as($username, "cd $ws; find . -name \"*core*\" -exec svn delete {} \; ; svn ci -m corovi .");
	print run_as($username, "cd $ws; find . -name \"*core*\" -delete");
}

?>