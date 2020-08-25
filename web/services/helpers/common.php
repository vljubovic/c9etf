<?php

function error($code, $message)
{
	print json_encode(array('success' => false, 'code' => $code, 'message' => $message));
}

function success($message, $data)
{
	print json_encode(array('success' => true, 'message' => $message, 'data' => $data));
}
