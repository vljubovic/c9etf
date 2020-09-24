<?php

/**
 * Class Response
 * @property string $data
 * @property integer $code
 * @property boolean $error
 */
class Response
{
	public $data, $code, $error;
	
	/**
	 * Response constructor.
	 * @param string $data
	 * @param int $code
	 * @param bool $error
	 */
	public function __construct(string $data, int $code, bool $error)
	{
		$this->data = $data;
		$this->code = $code;
		$this->error = $error;
	}
	
}

/**
 * Class RequestBuilder
 */
class RequestBuilder
{
	private $request;
	
	public function __construct()
	{
		$this->request = curl_init();
		curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
	}
	
	public function setUrl(string $url)
	{
		curl_setopt($this->request, CURLOPT_URL, $url);
		return $this;
	}
	
	public function setHeaders(array $headers)
	{
		curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
		return $this;
	}
	
	public function setMethod(string $method)
	{
		curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
		return $this;
	}
	
	public function setBody(string $body)
	{
		curl_setopt($this->request, CURLOPT_POSTFIELDS, $body);
		return $this;
	}
	
	public function send()
	{
		$response = curl_exec($this->request);
		if (curl_errno($this->request) !== 0) {
			return new Response("", curl_errno($this->request), true);
		}
		$code = curl_getinfo($this->request, CURLINFO_RESPONSE_CODE);
		curl_close($this->request);
		curl_init($this->request);
		return new Response($response, $code, false);
	}
	
	function __destruct()
	{
		curl_close($this->request);
	}
}