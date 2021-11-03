<?php

// =========================================
// ENSURE_RUNNING.PHP
// C9@ETF project (c) 2015-2018
//
// Ensure that all neccessary components for webide are started
// =========================================


require("config.php");
require("webidelib.php");

// check_php script that stops/starts php
$check_php=`ps aux | grep check_php | grep -v grep`;
if (!$check_php)
	proc_close(proc_open("nohup $conf_base_path/lib/check_php > /dev/null 2>&1 &", array(), $foo));

// check_php script that stops/starts php
$killold=`ps aux | grep killold | grep -v grep`;
if (!$killold)
	proc_close(proc_open("nohup $conf_base_path/lib/killold.sh > /dev/null 2>&1 &", array(), $foo));

// Server stats monitoring (needed to see if there are sufficient resources)
$stats_monitor=`ps aux | grep stats_monitor.php | grep -v grep`;
if (!$stats_monitor)
	proc_close(proc_open("nohup php $conf_base_path/lib/stats_monitor.php > $conf_base_path/server_stats.log 2>&1 &", array(), $foo));

// Maintenance task
$maintenance=`ps aux | grep maintenance.php | grep -v grep`;
if (!$maintenance)
	proc_close(proc_open("nohup php $conf_base_path/bin/maintenance.php 2>&1 &", array(), $foo));

// Check for ssh tail processes that copy stats from other nodes to control node
$is_control_node = false;
foreach ($conf_nodes as $node) {
	if (is_local($node['address']) && in_array("control", $node['type'])) {
		$is_control_node = true;
		break;
	}
}
if ($is_control_node) {
	foreach ($conf_nodes as $node) {
		if (!is_local($node['address'])) {
			$ssh_tail = "ssh ".$node['address']." tail";
			$ssh_tail_proc = `ps aux | grep "$ssh_tail" | grep -v grep`;
			$log_file_name = $node['name'] . "_stats.log";
			if (!$ssh_tail_proc)
				proc_close(proc_open("nohup $ssh_tail -f $conf_base_path/server_stats.log > $conf_base_path/$log_file_name 2>&1 &", array(), $foo));
		}
	}
}


