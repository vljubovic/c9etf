<?php

require("lib/config.php");


// UPDATING Cloud9 INSTANCE IN c9upstream DIR

`rm -fr $conf_home_path/.c9old`;
`rm -fr $conf_base_path/c9upstream`;


// Install Cloud9
echo `echo "\033[31mDownloading Cloud9 IDE\033[0m"`;
`git clone $cloud9_git_url $conf_base_path/c9upstream`;


echo "\n";
echo `echo "\033[31mInstalling Cloud9 IDE\033[0m"`;
`chown -R $conf_c9_user:$conf_c9_group $conf_base_path/c9upstream`;
`mv $conf_home_path/.c9 $conf_home_path/.c9old`;
`su $conf_c9_user -c $conf_base_path/c9upstream/scripts/install-sdk.sh`;
`chmod -R 755 $conf_base_path/c9upstream`; // ???? this is needed... but why?

// Make a backup of some file that randomly gets deleted (!?)
`cp $conf_base_path/c9upstream/node_modules/engine.io-client/engine.io.js $conf_base_path/c9util`;



// APPLY PATCHES

echo "\n";
echo `echo "\033[31mApplying patches\033[0m"`;

// Disable "keys" plugin which is confusing and uses screen real estate
echo "\ndisable_keys_plugin.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/disable_keys_plugin.diff`;

// Disable "welcome" screen - in future maybe patch welcome screen to our needs?
echo "\ndisable_welcome_plugin.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/disable_welcome_plugin.diff`;

// Disable "Open terminal here" and similar options
echo "\ndisable_open_terminal.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/disable_open_terminal.diff`;

// Disable "Process list" plugin that would enable users to kill eachothers processes
echo "\ndisable_processlist.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/disable_processlist.diff`;

// Disable "Command" widget that allows user to run arbitrary command on server
echo "\ndisable_command.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/disable_command.diff`;

// Disable "Show Home in Favorites" option (in tree) since students would 
// accidentally enable it and then delete important files 
echo "\ndisable_show_home_in_favorites.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/disable_show_home_in_favorites.diff`;

// Additional C/C++ snippets by cyclone
echo "\nc_cpp_snippets.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/c_cpp_snippets.diff`;

// Source code formatting for C, C++ and Java using Astyle
// FIXME: Currently implemented through a web service - move to c9 api
echo "\nformatter_astyle.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/formatter_astyle.diff`;

// By default tmux binary creates a socket in /tmp (owned by user)... this
// sometimes caused permission errors when another user tried to run program.
// This patch creates a separate tmux socket file for each user
echo "\ntmux_socket_error.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/tmux_socket_error.diff`;

// Detect when user is logged out and redirect to login page, instead of just
// displaying "Reconnecting" forever (maybe localize?)
echo "\ndetect_logout.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/detect_logout.diff`;

// Fix bug where user settings were reverted to default after each login
echo "\nfix_forget_settings.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/fix_forget_settings.diff`;

// "Run" button would sometimes get stuck in disabled state
echo "\nfix_stuck_run_button.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/fix_stuck_run_button.diff`;

// Avoid inconsequential ENOENT error message that watch file had dissapeared
echo "\nfix_useless_error.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/fix_useless_error.diff`;

// Fix erroneous error message "No error" that showed up after using gdb
echo "\nfix_gdb_no_error.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/fix_gdb_no_error.diff`;

// Due to poor handling of undefined value, "debug" button would sometimes
// automatically reenable instead of staying disabled
echo "\ndebugging_default_to_false.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/debugging_default_to_false.diff`;

// Support for evaluating vectors and their elements in gdb debugger
// (see http://stackoverflow.com/questions/253099/how-do-i-print-the-elements-of-a-c-vector-in-gdb)
echo "\ngdb_evaluate_vectors_elements.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/gdb_evaluate_vectors_elements.diff`;

// Debugging the debugger is painful due to async file writes - this fixes it at the cost of 
// performance (we're not always debugging the debugger)
// Also log file is stored in users workspace and timestamp added
//echo "gdb_sync_log.diff\n";
//echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/gdb_sync_log.diff`;

// Don't crash debugger when there is open brace (but not closed!) inside string
// Also some warnings
//echo "gdb_fix_brace_inside_string.diff\n";
//echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/gdb_fix_brace_inside_string.diff`;

// Make sure truncated packages (sometimes returned by gdb) are valid JSON
// Such packages sometimes caused crashes
//echo "gdb_fix_truncated_package.diff\n";
//echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/gdb_fix_truncated_package.diff`;

// Properly escape octal numbers in gdb output (also sometimes causing crashes)
//echo "gdb_fix_escaping_octal_numbers.diff\n";
//echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/gdb_fix_escaping_octal_numbers.diff`;

// Cleanup some warnings thrown by nodejs in gdb shim code, add some more debugging
//echo "gdb_warnings_debugging.diff\n";
//echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/gdb_warnings_debugging.diff`;

// Use our URL for logout menu action
echo "\nlogout_url.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/logout_url.diff`;

// Patches that failed to port:
// - Remove "Enable Auto-Save" from preferences but leave enabled (was in plugins/c9.ide.save/autosave.js)
// - parse_program_output.diff is currently considered obsolete, but it's the only way to detect if program run too long
// - gdb prevent infinite recursion, e.g. with evaluating C++ string object on stack 
// - gdb evaluate C++ vector object on stack
// - gdb limit number of children fetched to prevent slowness/crashes with oversized response package



// Use relative paths instead of absolute (so that e.g. http://hostname/user/ redirects to 
// http://hostname/user/ide.html and not http://hostname/ide.html)
echo "\nrelative_paths.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/relative_paths.diff`;

// Display a progress bar that corresponds to actual files being loaded
echo "\nprogress_bar.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/progress_bar.diff`;

// Retry loading a failed file instead of just die
echo "\nretry_failed.diff\n";
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/retry_failed.diff`;


// Install ETF plugins - we need nginx to route to these
echo "\n";
echo `echo "\033[31mInstall ETF plugins\033[0m"`;

echo "etf.annotate\n";
`cp -R $conf_base_path/plugins/etf.annotate $conf_base_path/c9upstream/plugins`;
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/etf_annotate.diff`;

echo "etf.buildservice\n";
`cp -R $conf_base_path/plugins/etf.buildservice $conf_base_path/c9upstream/plugins`;
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/etf_buildservice.diff`;

echo "etf.zadaci\n";
`cp -R $conf_base_path/plugins/etf.zadaci $conf_base_path/c9upstream/plugins`;
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/etf_zadaci.diff`;

echo "etf.zamger\n";
`cp -R $conf_base_path/plugins/etf.zamger $conf_base_path/c9upstream/plugins`;
echo `cd $conf_base_path/c9upstream; patch -p1 < ../patches/etf_zamger.diff`;

// Disable some runners: all C and C++ runners (we provide our own) and Shell command runners
`mkdir $conf_base_path/c9upstream/runners.disabled`;
`mv $conf_base_path/c9upstream/plugins/c9.ide.run/runners/C\ * $conf_base_path/c9upstream/runners.disabled`;
`mv $conf_base_path/c9upstream/plugins/c9.ide.run/runners/C\+\+* $conf_base_path/c9upstream/runners.disabled`;
`mv $conf_base_path/c9upstream/plugins/c9.ide.run/runners/Shell* $conf_base_path/c9upstream/runners.disabled`;
`rm $conf_base_path/c9upstream/plugins/c9.ide.run/runners-docker/C\ *`;
`rm $conf_base_path/c9upstream/plugins/c9.ide.run/runners-docker/C\+\+*`;
`rm $conf_base_path/c9upstream/plugins/c9.ide.run/runners-docker/Shell*`;



?>
