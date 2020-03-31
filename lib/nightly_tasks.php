<?php

// =========================================
// NIGHTLY_TASKS.PHP
// C9@ETF project (c) 2015-2020
// 
// Tasks that are performed every night at cca. 3:20
// =========================================



require("config.php");


// Special tasks are performed if there is remote storage
$storage_node = false;
foreach ($conf_nodes as $node) {
	if (in_array("storage", $node['type']) && count($node['type']) == 1)
		$storage_node = $node['address'];
	if (in_array("storage", $node['type']) && in_array("svn", $node['type']) && count($node['type']) == 2)
		$storage_node = $node['address'];
}

// Notify users that restart is about to happen
`$conf_base_path/bin/webidectl broadcast "Restart servera za 5 minuta..."`;
if ($storage_node)
	`mount -o remount $conf_home_path`;
`$conf_base_path/bin/webidectl broadcast "Restart servera za 4 minuta..."`;
`$conf_base_path/bin/webidectl broadcast "Restart servera za 3 minuta..."`;
`$conf_base_path/bin/webidectl broadcast "Restart servera za 2 minuta..."`;
`$conf_base_path/bin/webidectl broadcast "Restart servera za 1 minut..." 30`;
`$conf_base_path/bin/webidectl broadcast "Restart servera za 30 sekundi..." 15`;
`$conf_base_path/bin/webidectl broadcast "Restart servera za 15 sekundi..." 15`;

// Tasks for which all users must be logouted
`echo kada se zavrsi redovno dnevno odrzavanje \(minut-dva\) > $conf_base_path/razlog_nerada.txt`;
`chmod 644 $conf_base_path/razlog_nerada.txt`;
`echo Clear server >> $conf_base_path/log/webidectl.log`;
`$conf_base_path/bin/webidectl clear-server >> $conf_base_path/log/webidectl.log`;

// Restart nfs
if ($storage_node) {
	`echo storage nfs restart >> $conf_base_path/log/webidectl.log`;
	run_on($storage_node, "/etc/init.d/nfs-kernel-server restart &>> $conf_base_path/log/webidectl.log");
}

// rm fr
`echo rm fr >> $conf_base_path/log/webidectl.log`;
`rm -fr /tmp/buildservice`;
`rm -fr /tmp/submit*`;
`rm -fr $conf_base_path/watch/*`;
`rm -fr /tmp/web-background/*`;
`rm -fr /tmp/hw*`;
`rm -fr /tmp/vgdb-pipe*`;
`rm -fr /tmp/tmux-*`;
`rm -fr /tmp/bs_download*`;

// Server is now online
`echo > $conf_base_path/razlog_nerada.txt`;
`chmod 644 $conf_base_path/razlog_nerada.txt`;

// Kill inactive users, if they somehow remained logged in
`echo kill-inactive >> $conf_base_path/log/webidectl.log`;
`$conf_base_path/bin/webidectl kill-inactive`;
