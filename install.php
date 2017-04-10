<?php

require("lib/config.php");


// CONTINUE STAGE OF INSTALLATION

if ($argc > 2 && $argv[1] == "continue") {
	echo "Continuing installation of C9@ETF WebIDE...\n";

	// PERMISSIONS SETUP
	
	// Files that have to be fixed permissions to 644
	$user_readable = array("c9fork/build/standalone/skin/default/dark.css");
	foreach($user_readable as $path) {
		`chmod 644 $conf_base_path/$path`;
	}
	
	
	echo "\n\nInstallation of C9@ETF WebIDE is finished!\n";
	echo "You can now create some users using webidectl\n";
	exit;
}

echo "Start installation of C9@ETF WebIDE...\n";

// Create c9 user
`groupadd $conf_c9_group`;
`useradd $conf_c9_user -g $conf_c9_group -m`;
`mkdir /home/$conf_c9_user/workspace`;
`chown $conf_c9_user:$conf_c9_group /home/$conf_c9_user/workspace`;


// PERMISSIONS SETUP

// Create some directories (git doesn't permit empty directories)
$directories=array("log", "watch", "data", "c9fork", "last", "web/buildservice", "localusers", "htpasswd", "defaults/c9/plugins", "defaults/c9/plugins/_", "defaults/c9/managed", "defaults/c9/managed/plugins", "defaults/c9/dev", "defaults/c9/dev/plugins");
foreach($directories as $dir) {
	`mkdir $conf_base_path/$dir`;
	`chmod 755 $conf_base_path/$dir`;
}

// Directories and files that should be writable by user processes
$user_writable=array("log", "watch", "last");
foreach($user_writable as $path) {
	`chgrp $conf_c9_group $conf_base_path/$path`;
	`chmod 775 $conf_base_path/$path`;
}

// Directories and files that should be writable from web
$web_writable = array("register", "log/admin.php.log", "log/autotest.log");
foreach($web_writable as $path) {
	if (!file_exists("$conf_base_path/$path")) `touch $conf_base_path/$path`;
	`chown www-data $conf_base_path/$path`;
	`chmod 755 $conf_base_path/$path`;
}

// Directories and files that should be readable from web but not by users
$web_readable = array("users");
foreach($web_readable as $path) {
	if (!file_exists("$conf_base_path/$path")) `touch $conf_base_path/$path`;
	`chgrp www-data $conf_base_path/$path`;
	`chmod 750 $conf_base_path/$path`;
}


// INSTALLATION

// Install Cloud9
echo "Downloading Cloud9 IDE\n";
`git clone $cloud9_git_url $conf_base_path/c9fork`;
echo "Installing Cloud9 IDE\n";
`$conf_base_path/c9fork/scripts/install-sdk.sh`;
`chmod 755 $conf_base_path/c9fork -R`;
`cd /home/$conf_c9_user; ln -s $conf_base_path/c9fork fork`;

// This enables wizard to complete
`chmod 777 $conf_base_path/c9fork/build`;
`chmod 644 $conf_base_path/c9fork/build/standalone/skin/default/*`;

// Populate "static" folder with symlinks
`ln -s $conf_base_path/c9fork/node_modules/architect-build/build_support/mini_require.js $conf_base_path/web/static/mini_require.js`;
`ln -s $conf_base_path/c9fork/plugins/c9.nodeapi/events.js $conf_base_path/web/static/lib/events.js`;
`ln -s $conf_base_path/c9fork/node_modules/architect $conf_base_path/web/static/lib/architect`;
`ln -s $conf_base_path/c9fork/plugins $conf_base_path/web/static/plugins`;

// Install Buildservice
echo "Downloading Buildservice\n";
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
`echo www-data ALL=NOPASSWD: WEBIDECTL >> /etc/sudoers`;
`echo www-data ALL=NOPASSWD: USERSTATS >> /etc/sudoers`;
`echo www-data ALL=NOPASSWD: WSACCESS >> /etc/sudoers`;

// Make a backup of some file that randomly gets deleted (!?)
`mkdir $conf_base_path/c9util`;
`cp $conf_base_path/c9fork/node_modules/engine.io-client/engine.io.js $conf_base_path/c9util`;

// Add cron tasks
`echo 45 *     * * *   root    /usr/local/webide/bin/webidectl culling >> /etc/crontab`;
`echo 20 3     * * *   root    /usr/local/webide/lib/nightly_tasks >> /etc/crontab`;
 // This is stupid...
`echo 0,5,10,15,20,25,30,35,40,45,50,55 * * * *   root    cp $conf_base_path/c9util/engine.io.js $conf_base_path/node_modules/engine.io-client >> /etc/crontab`;


// APPLY PATCHES

// Use relative paths instead of absolute (so that e.g. http://hostname/user/ redirects to 
// http://hostname/user/ide.html and not http://hostname/ide.html)
echo "relative_paths.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/relative_paths.diff`;

// Disable "keys" plugin which is confusing and uses screen real estate
echo "disable_keys_plugin.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/disable_keys_plugin.diff`;

// Disable "welcome" screen - in future maybe patch welcome screen to our needs?
echo "disable_welcome_plugin.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/disable_welcome_plugin.diff`;

// Disable "Open terminal here" and similar options
echo "disable_open_terminal.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/disable_open_terminal.diff`;

// Additional C/C++ snippets by cyclone
echo "c_cpp_snippets.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/c_cpp_snippets.diff`;

// Display a progress bar that corresponds to actual files being loaded
echo "progress_bar.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/progress_bar.diff`;

// Retry loading a failed file instead of just die
echo "retry_failed.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/retry_failed.diff`;

// Fix tmux errors caused by many users/processes sharing the same socket for 
// their tmux connections at the same time
echo "tmux_socket_error.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/tmux_socket_error.diff`;

// Detect when user is logged out and redirect to login page, instead of just
// displaying "Reconnecting" forever (maybe localize?)
echo "detect_logout.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/detect_logout.diff`;

// Fix bug where user settings were reverted to default after each login
echo "fix_forget_settings.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/fix_forget_settings.diff`;

// Avoid pointless ENOENT error message for missing watch file
echo "fix_useless_error.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/fix_useless_error.diff`;

// Use our URL for logout menu action
echo "logout_url.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/logout_url.diff`;

// Use tmux binary that came with the OS and not the one compiled by Cloud9
// improves memory sharing
echo "use_system_tmux.diff\n";
echo `cd $conf_base_path/c9fork; patch -p1 < ../patches/use_system_tmux.diff`;



// Done
echo "\n\nDone!\nCloud9 instance is prepared for installation. Now you need to:\n";
echo "1. Start Cloud9 as \"c9\" user.\n";
echo "2. Complete the Setup wizard using your web browser.\n";
echo "3. Come back here and type \"sudo php install.php continue\".\n";



?>
