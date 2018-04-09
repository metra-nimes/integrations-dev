<?php

class Integration_Response {

	/**
	 * @var Integration_Request
	 */
	protected $request;

	/**
	 * @var int
	 */
	public $code;

	/**
	 * @var string
	 */
	public $body;

	/**
	 * @var array
	 */
	public $headers = array();

	public $data = array();

	/**
	 * Integration_Response constructor.
	 * @param Integration_Request $request
	 * @param int $code
	 * @param string $body
	 * @param array $headers
	 * @param string $format Force
	 */
	public function __construct($request, $code, $body, array $headers, $format = NULL)
	{
		$this->request = $request;
		$this->code = $code;
		$this->body = $body;
		$this->headers = $headers;

		// Data format is not set: trying to determine it by header
		if ($format === NULL AND isset($this->headers['Content-Type']))
		{
			// TODO Maybe use fast check strstr($this->headers['Content-Type'],'json')?
			if (strtok($this->headers['Content-Type'], ';') == 'application/json')
			{
				$format = 'json';
			}
			elseif (strtok($this->headers['Content-Type'], ';') == 'application/xml')
			{
				$format = 'xml';
			}
			elseif (strtok($this->headers['Content-Type'], ';') == 'text/plain')
			{
				$format = 'plain';
			}
		}

		try
		{
			if ($format === 'json')
			{
				$this->data = json_decode($body, TRUE);
			}
			elseif ($format === 'xml')
			{
				$this->data = Arr::from_xml($body);
			}
			elseif ($format === 'plain')
			{
				parse_str($body, $this->data);
			}
			elseif ($this->is_successful())
			{
				// 415: Unsupported Media Type
				$this->code = 415;
			}
		}
		catch (Exception $e)
		{
			if ($this->is_successful())
			{
				// 422: Unprocessable Entity
				$this->code = 422;
			}
		}
	}

	/**
	 * @return bool
	 */
	public function is_successful()
	{
		return ($this->code >= 200 AND $this->code < 300);
	}

	public function is_temporary()
	{
		return ($this->code >= 500);
	}

	/**
	 * Get value from the response json
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($key, $default = NULL)
	{
		return Arr::get($this->data, $key, $default);
	}

	/**
	 * @param int $wait_before How much time wait before?
	 * @return Integration_Response
	 */
	public function retry_request($wait_before = 0)
	{
		sleep($wait_before);
		return $this->request->execute();
	}

	/**
	 * Get value by path from the response json
	 *
	 * @param string $path
	 * @param mixed $default
	 * @return mixed
	 */
	public function path($path, $default = NULL)
	{
		return Arr::path($this->data, $path, $default);
	}

	public function debug()
	{
		echo '<pre>';
		echo 'REQUEST: '.$this->request->method().' '.$this->request->url()."\n";
		$request_headers = $this->request->headers();
		if ( ! empty($request_headers))
		{
			echo 'HEADERS: '.json_encode($request_headers, JSON_PRETTY_PRINT)."\n";
		}
		$request_data = $this->request->data();
		if ( ! empty($request_data))
		{
			echo 'DATA: '.json_encode($request_data, JSON_PRETTY_PRINT)."\n";
		}
		echo 'RESPONSE: '.$this->code."\n";
		echo 'HEADERS: '.json_encode($this->headers, JSON_PRETTY_PRINT)."\n";
		echo 'DATA: '.json_encode($this->data, JSON_PRETTY_PRINT)."\n";
		echo '</pre>';
	}

}