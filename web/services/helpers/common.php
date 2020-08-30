<?php

function error($code, $message)
{
	header('Content-type:application/json;charset=utf-8');
	print json_encode(array('success' => false, 'code' => $code, 'message' => $message));
	exit();
}

function message_and_data($message, $data)
{
	header('Content-type:application/json;charset=utf-8');
	print json_encode(array('success' => true, 'message' => $message, 'data' => $data));
	exit();
}

function message($message)
{
	header('Content-type:application/json;charset=utf-8');
	print json_encode(array('success' => true, 'message' => $message));
	exit();
}

function isSubPath($path1, $path2)
{
	$one = explode('/', $path1);
	$two = explode('/', $path2);
	$result = true;
	if (count($one) > count($two)) {
		for ($i = 0; $i < count($two); $i++) {
			if ($one[$i] !== $two[$i]) {
				$result = false;
			}
		}
	} else {
		for ($i = 0; $i < count($one); $i++) {
			if ($one[$i] !== $two[$i]) {
				$result = false;
			}
		}
	}
	return $result;
}