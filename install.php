<?php

require(dirname(__FILE__) . "/lib/config.php");



echo `echo "\033[32mStarting installation of C9@ETF WebIDE...\033[0m"`;
echo "\n";

// Create c9 user
`groupadd $conf_c9_group`;
`useradd $conf_c9_user -g $conf_c9_group -d $conf_home_path/$conf_c9_user -m`;
`mkdir $conf_home_path/$conf_c9_user/workspace`;
`chown $conf_c9_user:$conf_c9_group $conf_home_path/$conf_c9_user/workspace`;


// PERMISSIONS SETUP

// Create some directories (git doesn't permit empty directories)
$directories=array("log", "watch", "data", "web/buildservice", "localusers", "htpasswd", "defaults/c9/plugins", "defaults/c9/plugins/_", "defaults/c9/managed", "defaults/c9/managed/plugins", "defaults/c9/dev", "defaults/c9/dev/plugins");
foreach($directories as $dir) {
	`mkdir $conf_base_path/$dir`;
	`chmod 755 $conf_base_path/$dir`;
}

// Directories and files that should be writable by user processes
$user_writable=array("log", "watch");
foreach($user_writable as $path) {
	`chgrp $conf_c9_group $conf_base_path/$path`;
	`chmod 775 $conf_base_path/$path`;
}

// Directories and files that should be writable from web
$web_writable = array("register", "log/admin.php.log", "log/autotest.log");
foreach($web_writable as $path) {
	if (!file_exists("$conf_base_path/$path")) `touch $conf_base_path/$path`;
	`chown $conf_nginx_user $conf_base_path/$path`;
	`chmod 755 $conf_base_path/$path`;
}

// Directories and files that should be readable from web but not by users
$web_readable = array("users");
foreach($web_readable as $path) {
	if (!file_exists("$conf_base_path/$path")) `touch $conf_base_path/$path`;
	`chgrp $conf_nginx_user $conf_base_path/$path`;
	`chmod 750 $conf_base_path/$path`;
}

// Create a pipe file for vmstat
`mkfifo $conf_base_path/lib/vmstat.pipe`;

// 'last' dir in home
`mkdir $conf_home_path/last`;
`chown $conf_c9_user:$conf_c9_group $conf_home_path/last`;
`chmod 775 $conf_home_path/last`;



// INSTALLATION

// Install Cloud9
`mkdir $conf_base_path/c9util`; // This is where update script will copy engine.io.js
require("$conf_base_path/update-cloud9.php");
`mv $conf_base_path/c9upstream $conf_base_path/c9fork`;

// Create home folder symlink
`cd /home/$conf_c9_user; ln -s $conf_base_path/c9fork fork`;

// Populate "static" folder with symlinks
`ln -s $conf_base_path/c9fork/node_modules/architect-build/build_support/mini_require.js $conf_base_path/web/static/mini_require.js`;
`ln -s $conf_base_path/c9fork/plugins/c9.nodeapi/events.js $conf_base_path/web/static/lib/events.js`;
`ln -s $conf_base_path/c9fork/node_modules/architect $conf_base_path/web/static/lib/architect`;
`ln -s $conf_base_path/c9fork/plugins $conf_base_path/web/static/plugins`;
`ln -s $conf_base_path/c9fork/node_modules/treehugger $conf_base_path/web/static/plugins/node_modules/treehugger`;
`ln -s $conf_base_path/c9fork/node_modules/tern $conf_base_path/web/static/plugins/node_modules/tern`;
`ln -s $conf_base_path/c9fork/node_modules/c9 $conf_base_path/web/static/plugins/node_modules/c9`;
`ln -s $conf_base_path/c9fork/node_modules/c9/assert.js $conf_base_path/web/static/plugins/node_modules/assert.js`;
`ln -s $conf_base_path/web/static $conf_base_path/web/static/static`;

// Install Buildservice
echo "\n";
echo `echo "\033[31mDownloading Buildservice\033[0m"`;
`git clone $buildservice_git_url $conf_base_path/web/buildservice`;
`cp $conf_base_path/web/buildservice.c9/* $conf_base_path/web/buildservice`;
`rm -fr $conf_base_path/web/buildservice.c9`;

// Prepare SVN path
`mkdir $conf_svn_path`;
`chown $conf_c9_user:$conf_c9_group $conf_svn_path`;

// Allow web scripts to use sudo to execute system-level webide commands
`echo >> /etc/sudoers`;
`echo >> /etc/sudoers`;
`echo Cmnd_Alias WEBIDECTL=$conf_base_path/bin/webidectl >> /etc/sudoers`;
`echo Cmnd_Alias USERSTATS=$conf_base_path/bin/userstats >> /etc/sudoers`;
`echo Cmnd_Alias WSACCESS=$conf_base_path/bin/wsaccess >> /etc/sudoers`;
`echo >> /etc/sudoers`;
`echo $conf_nginx_user ALL=NOPASSWD: WEBIDECTL >> /etc/sudoers`;
`echo $conf_nginx_user ALL=NOPASSWD: USERSTATS >> /etc/sudoers`;
`echo $conf_nginx_user ALL=NOPASSWD: WSACCESS >> /etc/sudoers`;

// Make a backup of some file that randomly gets deleted (!?)
`mkdir $conf_base_path/c9util`;
`cp $conf_base_path/c9fork/node_modules/engine.io-client/engine.io.js $conf_base_path/c9util`;

// Add cron tasks
`echo "45 *     * * *   root    $conf_base_path/bin/webidectl culling" >> /etc/crontab`;
`echo "20 3     * * *   root    $conf_base_path/lib/nightly_tasks" >> /etc/crontab`;
`echo "5 *     * * *   root    php $conf_base_path/lib/ensure_running.php" >> /etc/crontab`;
 // This is stupid...
`echo "0,5,10,15,20,25,30,35,40,45,50,55 * * * *   root    cp $conf_base_path/c9util/engine.io.js $conf_base_path/c9fork/node_modules/engine.io-client" >> /etc/crontab`;

// Files that have to be fixed permissions to 644
$user_readable = array("c9fork/build/standalone/skin/default/dark.css");
foreach($user_readable as $path) {
	`chmod 644 $conf_base_path/$path`;
}


// Install new nginx config
echo "\n";
echo `echo "\033[31mReconfiguring nginx\033[0m"`;
`$conf_base_path/bin/webidectl reset-nginx`;

// Ensure some c9 services are running
`php $conf_base_path/lib/ensure_running.php`;

echo "\n\n";
echo `echo "\033[32mInstallation of C9@ETF WebIDE is finished!\033[0m"`;
echo "You can now create some users using webidectl\n";



?>
