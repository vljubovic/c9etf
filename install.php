<?php

require("lib/config.php");


// Create c9 user
`groupadd $conf_c9_group`;
`useradd $conf_c9_user -g $conf_c9_group -m`;
`mkdir /home/$conf_c9_user/workspace`;
`chown $conf_c9_user /home/$conf_c9_user/workspace`;

// Create some directories and set permissions (git doesn't permit empty directories)
$directories=array("log", "watch", "data", "c9fork", "web/buildservice");
foreach($directories as $dir) {
	`mkdir $conf_base_path/$dir`;
	`chgrp $conf_c9_group $conf_base_path/$dir`;
	`chmod 775 $conf_base_path/$dir`;
}

// FIXME?
`touch $conf_base_path/log/admin.php.log`;
`chown www-data log/admin.php.log`;
`touch $conf_base_path/log/autotest.log`;
`chown www-data log/autotest.log`;

// Install Cloud9
echo Downloading Cloud9 IDE
`git clone $cloud9_git_url $conf_base_path/c9fork`;
echo Installing Cloud9 IDE
`cd $conf_base_path/c9fork; ./scripts/install_sdk.sh`;
`chmod 755 $conf_base_path/c9fork -R`;
`cd /home/$conf_c9_user; ln -s $conf_base_path/c9fork fork`;

// Do we need this?
`chmod 644 $conf_base_path/c9fork/build/standalone/skin/default/*`;

// Populate "static" folder with symlinks
`ln -s $conf_base_path/c9fork/node_modules/architect-build/build_support/mini_require.js $conf_base_path/web/static/mini_require.js`;
`ln -s $conf_base_path/c9fork/plugins/c9.nodeapi/events.js $conf_base_path/web/static/lib/events.js`;
`ln -s $conf_base_path/c9fork/node_modules/architect $conf_base_path/web/static/lib/architect`;
`ln -s $conf_base_path/c9fork/plugins $conf_base_path/web/static/plugins`;

// Install Buildservice
echo Downloading Buildservice
`git clone $buildservice_git_url $conf_base_path/web/buildservice`;
`cp $conf_base_path/web/buildservice.c9/* $conf_base_path/web/buildservice`;
`rm -fr $conf_base_path/web/buildservice.c9`;

// Prepare SVN path
`mkdir $conf_svn_path`;
`chown $c9user:$c9group $conf_svn_path`;

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


?>
