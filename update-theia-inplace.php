<?php


// =========================================
// UPDATE-THEIA-INPLACE.PHP
// C9@ETF project (c) 2015-2020
//
// Software installation component
// Download and install Theia webide
// =========================================

require("lib/config.php");


// UPDATING Theia INSTANCE IN theia DIR

`mkdir -p $conf_base_path/theia`;
`chmod 777 $conf_base_path/theia`;

// Install Theia
`chmod 755 $conf_base_path/install-theia-c9.sh`;
system("su $conf_c9_user -c \"cd $conf_base_path/theia; ../install-theia-c9.sh\" &", $return);
`chmod 644 $conf_base_path/install-theia-c9.sh`;

// Fix permisions
`chmod a+rX $conf_base_path/theia -R`;
`chmod g+rwX $conf_shared_path/.cache -R`;
`chmod a+rX $conf_shared_path/.config -R`;
`chmod g+rX $conf_shared_path/.npm -R`;
`chmod g+rX $conf_shared_path/.nvm -R`;
`chmod g+rwX $conf_shared_path/.yarn -R`;
