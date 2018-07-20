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
	public static function domain($with_protocol = TRUE, $only_root = FALSE)
	{
		$protocol = $with_protocol ? URL::protocol().'//' : '';
		if (isset($_SERVER['HTTP_HOST']))
		{
			$domain = $_SERVER['HTTP_HOST'];
		}
		else
		{
			// CLI mode
			switch (BASEPATH)
			{
				case '/srv/app.convertful.com/':
					$domain = 'app.convertful.com';
					break;
				case '/srv/app.devcf.su/':
					$domain = 'app.devcf.su';
					break;
				case '/srv/convertful.local/':
					$domain = 'convertful.local';
					break;
				default:
					$domain = 'app.convertful.com';
			}
		}

		if ($only_root)
		{
			$domain = explode('.', $domain);
			$domain = implode('.', array_slice($domain, -2, 2));
		}

		return $protocol.$domain;
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

	public static function is_relative($url)
	{
		return ! self::is_absolute($url);
	}

	public static function is_absolute($url)
	{
		$pattern = "/^(?:https?):\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*
    (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:
    (?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?]
    (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/xi";

		return (bool) preg_match($pattern, $url);
	}

}
