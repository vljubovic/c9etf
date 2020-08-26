<?php

function error($code, $message)
{
	header('Content-type:application/json;charset=utf-8');
	print json_encode(array('success' => false, 'code' => $code, 'message' => $message));
	exit();
}

function messageAndData($message, $data)
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