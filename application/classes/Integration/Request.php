<?php

class Integration_Request {

	public static function factory()
	{
		return new self;
	}

	protected function __construct()
	{
	}

	/**
	 * @var string Request method: GET / POST / ...
	 */
	protected $method = 'GET';

	/**
	 * @var array Request headers
	 */
	protected $headers = [];

	/**
	 * @var array oAuth headers
	 */
	protected $oauth_params = [];

	/**
	 * The key for creating a oAuth signature
	 * @var string
	 */
	private $oauth_key = NULL;

	/**
	 * @var array Request cookies
	 */
	protected $cookies = [];

	/**
	 * @var string Request URL
	 */
	protected $url;

	/**
	 * @var array|string POST/GET-data that should be sent
	 */
	protected $data = [];

	/**
	 * @var array Response headers
	 */
	protected $response_headers = [];

	/**
	 * @var array|null Response log
	 */
	public $_log = array();


	protected $curl_options = [
		CURLOPT_FRESH_CONNECT     => TRUE,
		CURLOPT_TCP_NODELAY       => TRUE,
		CURLOPT_CONNECTTIMEOUT_MS => 5000,
		CURLOPT_TIMEOUT_MS        => 12000,
		CURLOPT_FAILONERROR       => FALSE,
		CURLOPT_SSL_VERIFYPEER    => FALSE,
		CURLOPT_SSL_VERIFYHOST    => FALSE,
		// TODO define correct user-agent. FeedBlitz API does not accept POST request without it
		CURLOPT_USERAGENT         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:49.0) Gecko/20100101 Firefox/49.0',
	];

	/**
	 * Method setter/getter
	 *
	 * @chainable
	 * @param string|NULL $method
	 * @return Integration_Request|string
	 */
	public function method($method = NULL)
	{
		if ($method === NULL)
		{
			return $this->method;
		}
		$this->method = strtoupper($method);

		return $this;
	}

	/**
	 * Set curl options
	 *
	 * @chainable
	 * @param array|NULL $curl
	 * @return Integration_Request|array
	 */
	public function curl($curl = NULL)
	{
		if ($curl === NULL)
		{
			return $this->curl_options;
		}
		$this->curl_options = Arr::merge($this->curl_options, $curl);

		return $this;
	}

	/**
	 * Single request header setter/getter
	 *
	 * @chainable
	 * @param string|NULL $key
	 * @param string|NULL $value
	 * @return Integration_Request|string
	 */
	public function header($key, $value = NULL)
	{
		if ($value === NULL)
		{
			return Arr::get($this->headers, $key);
		}
		$this->headers[$key] = $value;

		return $this;
	}

	/**
	 * Request headers setter/getter
	 *
	 * @chainable
	 * @param array|NULL $headers
	 * @return Integration_Request|array
	 */
	public function headers($headers = NULL)
	{
		if ($headers === NULL)
		{
			return $this->headers;
		}
		$this->headers = array_merge($this->headers, $headers);

		return $this;
	}

	/**
	 * Request cookies setter/getter
	 *
	 * @chainable
	 * @param array|NULL $cookies
	 * @return Integration_Request|array
	 */
	public function cookies($cookies = NULL)
	{
		if ($cookies === NULL)
		{
			return $this->cookies;
		}
		$this->headers = array_merge($this->cookies, $cookies);

		return $this;
	}

	/**
	 * URL setter/getter
	 *
	 * @chainable
	 * @param string|NULL $url
	 * @return Integration_Request|string
	 */
	public function url($url = NULL)
	{
		if ($url === NULL)
		{
			return $this->url;
		}
		$this->url = $url;

		return $this;
	}

	/**
	 * Request data setter/getter
	 *
	 * @chainable
	 * @param array|NULL $data
	 * @return Integration_Request|array
	 */
	public function data($data = NULL)
	{
		if ($data === NULL)
		{
			return $this->data;
		}
		$this->data = array_merge($this->data, $data);

		return $this;
	}

	/**
	 * Prepare request header for a basic HTTP authentication
	 * @link https://en.wikipedia.org/wiki/Basic_access_authentication
	 *
	 * @param string $user
	 * @param string $pass
	 * @return Integration_Request
	 */
	public function http_basic_auth($user, $pass)
	{
		return $this->header('Authorization', 'Basic '.base64_encode($user.':'.$pass));
	}

	/**
	 * * Request headers setter/getter
	 *
	 * @chainable
	 * @param string $key
	 * @param  string $value
	 * @return Integration_Request|string
	 */
	public function oauth_param($key, $value = NULL)
	{
		if ($value === NULL)
		{
			return Arr::get($this->oauth_params, $key);
		}
		$this->oauth_params[$key] = $value;
		return $this;
	}

	/**
	 * Request oauth params setter/getter
	 *
	 * @chainable
	 * @param  array $oauth_params
	 * @return Integration_Request|string
	 */
	public function oauth_params($oauth_params = NULL)
	{
		if ($oauth_params === NULL)
		{
			return $this->oauth_params;
		}
		$this->oauth_params = array_merge($this->oauth_params, $oauth_params);
		return $this;
	}

	/**
	 * Sign the oauth request using hmac-sha1 method
	 * This method is called after the installation of data and oauth_params!
	 *
	 * @param string $key
	 * @param string $secret
	 * @param string $oauth_token
	 * @param string $oauth_secret
	 * @return Integration_Request
	 *
	 * @link https://oauth.net/core/1.0a/
	 * @link https://nullinfo.wordpress.com/oauth-twitter/
	 */
	public function generate_oauth_signature(string $key, string $secret, string $oauth_token = '', string $oauth_secret = '')
	{
		$params = [
			'oauth_consumer_key'     => $key,
			'oauth_nonce'            => md5(microtime().mt_rand()),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => time(),
			'oauth_version'          => '1.0',
		];

		if (! empty($oauth_token))
		{
			$this->oauth_params['oauth_token'] = $oauth_token;
		}

		if (! empty($this->oauth_params))
		{
			$this->oauth_params = array_merge($params, $this->oauth_params);
		}

		$this->oauth_key = rawurlencode($secret).'&'.rawurlencode($oauth_secret);

		unset($params);
		return $this;
	}

	/**
	 * @return Integration_Response
	 * @throws Exception
	 */
	public function execute()
	{
		// Curl options
		$options = $this->curl_options;

		// Create oauth signature
		if (count($this->oauth_params) AND $this->oauth_key !== NULL)
		{
			if ( ! empty($this->data))
			{
				$this->oauth_params = Arr::merge($this->data, $this->oauth_params);
			}

			Arr::recursive_ksort($this->oauth_params);

			$sig_string = strtoupper($this->method).'&'.
								rawurlencode($this->url).'&'.
								rawurlencode(http_build_query($this->oauth_params, NULL, '&', PHP_QUERY_RFC3986));

			$signature  = base64_encode(hash_hmac('sha1', $sig_string, $this->oauth_key, TRUE));
			$this->oauth_params['oauth_signature'] = $signature;
			Arr::recursive_ksort($this->oauth_params);

			if( ! empty($this->data))
			{
				# https://oauth.net/core/1.0a/#RFC3986
				$this->data = $this->oauth_params;
			}
			else
			{
				$request_param = 'OAuth';
				$first = TRUE;
				foreach ($this->oauth_params as $key => $val)
				{
					$request_param .= ($first) ? ' ' : ", ";
					$request_param .= rawurlencode($key).'="'.rawurlencode($val).'"';
					$first = FALSE;
				}
				$this->header('Authorization', $request_param);
			}

			$this->oauth_params = [];
			$this->oauth_key = NULL;
		}

		if ($this->method === 'POST')
		{
			$options[CURLOPT_POST] = TRUE;
		}
		elseif ($this->method !== 'GET')
		{
			$options[CURLOPT_CUSTOMREQUEST] = $this->method;
		}

		if ($this->method !== 'GET' AND ! empty($this->data))
		{
			$data = $this->data;
			if (preg_match('~^application\/.*?json$~', $this->header('Content-Type')))
			{
				$data = json_encode($data);
			}
			elseif (preg_match('~^application\/.*?xml~', $this->header('Content-Type')))
			{
				if ($this->header('X-xmlRPC-method') !== NULL)
				{
					$data = xmlrpc_encode_request($this->header('X-xmlRPC-method'), $data);
					unset($this->headers['X-xmlRPC-method']);
				}
				else
				{
					$data = Arr::to_xml($data);
				}
			}
			elseif ($this->header('Content-Type') == 'application/x-www-form-urlencoded')
			{
				$data = http_build_query($data, NULL, '&', PHP_QUERY_RFC3986);
			}
			elseif (count(Arr::flatten($data)) > count($data))
			{
				// Multi-dimensional array
				$data = http_build_query($data, NULL, '&', PHP_QUERY_RFC3986);
				if ($this->header('Content-Type') === NULL)
				{
					$this->header('Content-Type', 'multipart/form-data');
				}
			}
			$options[CURLOPT_POSTFIELDS] = $data;
		}

		if ( ! empty($this->headers))
		{
			$headers = [];
			foreach ($this->headers as $key => $value)
			{
				$headers[] = $key.': '.$value;
			}
			$options[CURLOPT_HTTPHEADER] = $headers;
		}

		if ( ! empty($this->cookies))
		{
			$options[CURLOPT_COOKIE] = http_build_query($this->cookies, NULL, '; ');
		}

		$options[CURLOPT_RETURNTRANSFER] = TRUE;
		$options[CURLOPT_HEADERFUNCTION] = [$this, 'parse_response_headers'];
		$options[CURLOPT_HEADER]         = FALSE;

		$url = $this->url;
		if ($this->method === 'GET' AND ! empty($this->data))
		{
			$url .= (strpos($url, '?') !== FALSE) ? '&' : '?';
			$url .= http_build_query($this->data, NULL, '&');
		}

		$curl = curl_init($url);

		if ( ! curl_setopt_array($curl, $options))
		{
			throw new Exception('Failed to set CURL options');
		}

		$body = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($body === FALSE AND $code != 204)
		{
			$errno = curl_errno($curl);
			switch ($errno)
			{
				case CURLE_OK:
					$code = 200;
					break;
				case CURLE_OPERATION_TIMEOUTED;
					$code = 508;
					break;
				case CURLE_COULDNT_RESOLVE_PROXY:
				case CURLE_COULDNT_RESOLVE_HOST:
				case CURLE_COULDNT_CONNECT:
				case CURLE_HTTP_NOT_FOUND:
					$code = 404;
					break;
				default:
					$code = 400;
					break;
			}
		}

		if (isset($this->headers['Accept-Type']) AND preg_match('/^application\/\S?(xml|json)$/i', $this->headers['Accept-Type'], $matches))
		{
			$force_format = $matches[1];
		}
		else
		{
			$force_format = NULL;
		}

		$response = new Integration_Response($this, $code, $body, $this->response_headers, $force_format);
		$this->_log[] = [
			'request'          => $this->method().' '.$url,
			'request_made_at'  => date('Y-m-d H:i:s'),
			'request_headers'  => $this->headers(),
			'request_data'     => $this->data(),
			'response_code'    => $code,
			'response_headers' => $this->response_headers,
			'response_data'    => $response->data,
			'response_body'    => $body,
		];

		return $response;
	}

	protected function parse_response_headers($ch, $header_line)
	{
		if (preg_match('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_line, $matches))
		{
			$this->response_headers[Text::ucfirst($matches[1])] = $matches[2];
		}

		return strlen($header_line);
	}

	/**
	 * Set log var
	 *
	 * @param array $log
	 * @return Integration_Request $this
	 */
	public function log_to(array &$log = NULL)
	{
		if ( ! is_null($log))
		{
			$this->_log = &$log;
		}

		return $this;
	}

}