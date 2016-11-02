<?php

header("Content-Type: text/plain");

$input = $_REQUEST['input'];
$mode = $_REQUEST['mode'];
$style = $_REQUEST['style'];
$indent = $_REQUEST['indent'];

$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
   2 => array("pipe", "a") // stderr is a file to write to
);

$cwd = '/tmp';
$tmpfile = "/tmp/astyle.tmp";
file_put_contents($tmpfile, $input);

$env = array('some_option' => 'aeiou');

$cmd = 'astyle --style='.$style.' --mode='.$mode.' '.$indent.' <'.$tmpfile;

$process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env);

if (is_resource($process)) {
    // $pipes now looks like this:
    // 0 => writeable handle connected to child stdin
    // 1 => readable handle connected to child stdout
    // Any error output will be appended to /tmp/error-output.txt

    fwrite($pipes[0], "\n");
    fclose($pipes[0]);

    echo stream_get_contents($pipes[1]);
    fclose($pipes[1]);
  //  echo "\nSTDERR:\n";
    //echo stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    // It is important that you close any pipes before calling
    // proc_close in order to avoid a deadlock
    $return_value = proc_close($process);

    //echo "command returned $return_value\n";
}


?>
