<?php

class Arr extends Kohana_Arr {

	/**
	 * Converts an array to XML string.
	 * Basic input array structure:
	 * array(
	 *  'root_element_name' => array(
	 *      '@attributes' => array(
	 *          'version' => '1.0',
	 *      ),
	 *      '@namespaces' => array(
	 *          'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
	 *      ),
	 *      'foo' => array(
	 *          array('bar'=>123),
	 *          array(
	 *              'bar'=>array(
	 *                  'baz'=>true
	 *              )
	 *          )
	 *      ),
	 *  ),
	 * );
	 *
	 * Results as:
	 * <?xml version="1.0"?>
	 * <root_element_name version="1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
	 *    <foo>
	 *        <bar>123</bar>
	 *        <bar>
	 *            <baz>1</baz>
	 *        </bar>
	 *    </foo>
	 * </root_element_name>
	 *
	 * @param array $array
	 * @param SimpleXMLElement|NULL $xml Parent element
	 * @param int $level Internal
	 * @return string XML representation of $array
	 */
	public static function to_xml(array $array, SimpleXMLElement &$xml = NULL, $level = 0)
	{
		if ($xml === NULL)
		{
			$root_name = key($array);
			$xml = new SimpleXMLElement('<'.$root_name.'/>');
			$array = $array[$root_name];
		}
		foreach ($array as $key => $val)
		{
			if ($key === '@attributes')
			{
				foreach ($val as $k => $v)
				{
					$xml->addAttribute($k, $v);
				}
				continue;
			}
			if ($key === '@namespaces')
			{
				foreach ($val as $k => $v)
				{
					$xml->addAttribute(strtok($k, ':').':'.$k, $v);
				}
				continue;
			}
			if (is_int($key))
			{
				if (is_array($val[key($val)]))
				{
					$child = $xml->addChild(key($val));
					Arr::to_xml($val[key($val)], $child, $level + 1);
				}
				else
				{
					$xml->addChild(key($val), $val[key($val)]);
				}
				continue;
			}
			if (is_array($val))
			{
				$child = $xml->addChild($key);
				Arr::to_xml($val, $child, $level + 1);
			}
			else
			{
				$xml->addChild($key, $val);
			}
		}

		if ($level == 0)
		{
			return $xml->asXML();
		}
		else
		{
			return $xml;
		}
	}

	/**
	 * Converts XML document into array
	 * @param string $xml
	 * @return array
	 * @throws Exception
	 */
	public static function from_xml($xml)
	{
		libxml_use_internal_errors(TRUE);
		$xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		if ( ! $xml)
		{
			$err_message = 'Error parsing XML';
			foreach (libxml_get_errors() as $error)
			{
				$err_message .= ' '.$error->message;
			}
			throw new Exception($err_message);
		};
		$array = array(
			$xml->getName() => json_decode(json_encode($xml), TRUE),
		);

		return $array;
	}

	/**
	 * Convert a multi-dimensional array into a single-dimensional array.
	 *
	 *     $array = array('set' => array('one' => 'something'), 'two' => 'other');
	 *
	 *     // Flatten the array Kohana way
	 *     $array = Arr::kohana_flatten($array, TRUE);
	 *
	 *     // The array will now be
	 *     array('set.one' => 'something', 'two' => 'other');
	 *
	 * @param   array $array array to flatten
	 * @param bool|string $prefix make complex array keys (number keys DO NOT flatten)
	 * @return array
	 * @since   3.0.6
	 */
	public static function kohana_flatten($array, $prefix = FALSE)
	{
		$is_assoc = Arr::is_assoc($array);
		$flat = array();
		if ($prefix === TRUE)
		{
			$prefix = '';
		}

		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$flat = array_merge($flat, Arr::kohana_flatten($value, $prefix.$key.Arr::$delimiter));
			}
			else
			{
				if ($is_assoc)
				{
					$flat[$prefix.$key] = $value;
				}
				elseif ($prefix)
				{
					$flat[trim($prefix, Arr::$delimiter)][] = $value;
				}
				else
				{
					$flat[] = $value;
				}
			}
		}

		return $flat;
	}

	/**
	 * TODO: PHPDOC
	 * @param $array
	 * @return array
	 */
	public static function kohana_unflatten($array)
	{
		$separated_array = array();
		foreach ($array as $key => $value)
		{
			Arr::set_path($separated_array, $key, $value);
		}
		return $separated_array;
	}

	/**
	 * Shuffle array in random order
	 *
	 * @param array $arr
	 *
	 * @return array
	 */
	public static function shuffle_assoc(array $arr)
	{
		$keys = array_keys($arr);
		shuffle($keys);
		$result = array();
		foreach ($keys as $key)
		{
			$result[$key] = $arr[$key];
		}

		return $result;
	}

	/**
	 * Inserts a new key/value before the key in the array.
	 *
	 * @param integer|string $key The key to insert before.
	 * @param array $array An array to insert in to.
	 * @param integer|string $new_key The key to insert.
	 * @param mixed $new_value An value to insert.
	 *
	 * @return array
	 */
	public static function insert_before($key, array $array, $new_key, $new_value)
	{
		if (array_key_exists($key, $array))
		{
			$new = array();
			foreach ($array as $k => $value)
			{
				if ($k == $key)
				{
					$new[$new_key] = $new_value;
				}
				$new[$k] = $value;
			}

			return $new;
		}

		return $array;
	}

	/**
	 * Inserts a new key/value after the key in the array.
	 *
	 * @param integer|string $key The key to insert after.
	 * @param array $array An array to insert in to.
	 * @param integer|string $new_key The key to insert.
	 * @param mixed $new_value An value to insert.
	 *
	 * @return array
	 */
	public static function insert_after($key, array $array, $new_key, $new_value)
	{
		if (array_key_exists($key, $array))
		{
			$new = array();
			foreach ($array as $k => $value)
			{
				$new[$k] = $value;
				if ($k == $key)
				{
					$new[$new_key] = $new_value;
				}
			}

			return $new;
		}

		return $array;
	}

	public static function delete_path( & $array, $path, $delimiter = NULL)
	{
		if ( ! $delimiter)
		{
			// Use the default delimiter
			$delimiter = Arr::$delimiter;
		}

		// The path has already been separated into keys
		$keys = $path;
		if ( ! is_array($path))
		{
			// Split the keys by delimiter
			$keys = explode($delimiter, $path);
		}

		// Set current $array to inner-most array path
		while (count($keys) > 1)
		{
			$key = array_shift($keys);

			if (ctype_digit($key))
			{
				// Make the key an integer
				$key = (int) $key;
			}

			if ( ! isset($array[$key]))
			{
				$array[$key] = array();
			}

			$array = & $array[$key];
		}

		// Set key on inner-most array
		unset($array[array_shift($keys)]);
	}
}