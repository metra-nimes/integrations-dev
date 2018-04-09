<?php

class URL extends Kohana_URL {

	/**
	 * @return string Current protocol
	 */
	public static function protocol()
	{
		if (Request::$initial instanceof Request)
		{
			if (Request::$initial->secure())
			{
				return 'https:';
			}
			list($protocol) = explode('/', strtolower(Request::$initial->protocol()));

			return $protocol.':';
		}

		return 'http:';
	}

	/**
	 * @return string Current domain (with protocol included)
	 */
	public static function domain()
	{
		if (isset($_SERVER['HTTP_HOST']))
		{
			return URL::protocol().'//'.$_SERVER['HTTP_HOST'];
		}

		return 'http://integrations.convertful.local/';
	}

	/**
	 * Build url from parts (like parse_url function)
	 * @param array $parts
	 * @return string
	 */
	public static function build_url(array $parts)
	{
		return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '').
			((isset($parts['user']) || isset($parts['host'])) ? '//' : '').
			(isset($parts['user']) ? "{$parts['user']}" : '').
			(isset($parts['pass']) ? ":{$parts['pass']}" : '').
			(isset($parts['user']) ? '@' : '').
			(isset($parts['host']) ? "{$parts['host']}" : '').
			(isset($parts['port']) ? ":{$parts['port']}" : '').
			(isset($parts['path']) ? "{$parts['path']}" : '').
			(isset($parts['query']) ? "?{$parts['query']}" : '').
			(isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
	}

	/**
	 * Return host from url
	 *
	 * @param $url
	 * @return string
	 */
	public static function trim_domain($url)
	{
		$parseUrl = parse_url(trim($url));
		$tmp_path = explode('/', Arr::get($parseUrl, 'path', ''), 2);

		return trim(
			Arr::get(
				$parseUrl,
				'host',
				array_shift(
					$tmp_path
				)
			)
		);
	}

}
