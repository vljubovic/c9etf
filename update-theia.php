<?php

require("lib/config.php");


// UPDATING Theia INSTANCE IN theia DIR

`rm -fr $conf_base_path/theia-upstream`;
`mkdir $conf_base_path/theia-upstream`;
`chmod 777 $conf_base_path/theia-upstream`;

// Install Theia
`chmod 755 $conf_base_path/install-theia-c9.sh`;
$return = 0;
do {
	system("su $conf_c9_user -c \"cd $conf_base_path/theia-upstream; ../install-theia-c9.sh\"", $return);
} while ($return != 0);
`chmod 644 $conf_base_path/install-theia-c9.sh`;

// Fix permisions
`chmod a+r $conf_base_path/theia-upstream -R`;
`chmod 777 $conf_home_path/$conf_c9_user/.cache -R`;
`chmod 777 $conf_home_path/$conf_c9_user/.config -R`;
`chmod 777 $conf_home_path/$conf_c9_user/.npm -R`;
`chmod 777 $conf_home_path/$conf_c9_user/.nvm -R`;
`chmod 777 $conf_home_path/$conf_c9_user/.yarn -R`;
`chmod 644 $conf_home_path/$conf_c9_user/.yarnrc`;

// New theia becomes default theia
`rm -fr $conf_base_path/theia-old`;
`mv $conf_base_path/theia $conf_base_path/theia-old`;
`mv $conf_base_path/theia-upstream $conf_base_path/theia`;
