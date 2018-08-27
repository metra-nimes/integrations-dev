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
	 * @param array $array array to flatten
	 * @param bool|string $prefix make complex array keys (number keys DO NOT flatten)
	 * @param array $exceptions Paths to exclude from flattening
	 * @return array
	 * @since   3.0.6
	 */
	public static function kohana_flatten($array, $prefix = FALSE, $exceptions = array())
	{
		$is_assoc = Arr::is_assoc($array);
		$flat = array();
		if ($prefix === TRUE)
		{
			$prefix = '';
		}

		foreach ($array as $key => $value)
		{
			if (in_array($prefix.$key, $exceptions))
			{
				// Setting value "as is"
				$flat[$prefix.$key] = $value;
			}
			elseif (is_array($value))
			{
				$flat = array_merge($flat, Arr::kohana_flatten($value, $prefix.$key.Arr::$delimiter, $exceptions));
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

	/**
	 * http://uk1.php.net/array_walk_recursive implementation that is used to remove nodes from the array.
	 *
	 * @param array The input array.
	 * @param callable $callback Function must return boolean value indicating whether to remove the node.
	 * @return array
	 */
	public static function walk_recursive_remove (array $array, callable $callback) {
		foreach ($array as $k => $v) {
			if (is_array($v)) {
				$array[$k] = self::walk_recursive_remove($v, $callback);
			} else {
				if ($callback($v, $k)) {
					unset($array[$k]);
				}
			}
		}
		return $array;
	}

	public static function recursive_diff($aArray1, $aArray2) {
		$aReturn = array();

		foreach ($aArray1 as $mKey => $mValue) {
			if (array_key_exists($mKey, $aArray2)) {
				if (is_array($mValue)) {
					$aRecursiveDiff = self::recursive_diff($mValue, $aArray2[$mKey]);
					if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
				} else {
					if ($mValue != $aArray2[$mKey]) {
						$aReturn[$mKey] = $mValue;
					}
				}
			} else {
				$aReturn[$mKey] = $mValue;
			}
		}
		return $aReturn;
	}

	/**
	 *  Recursive Sorting
	 *
	 * @param  array $array
	 * @return bool
	 */
	public static function recursive_ksort(&$array, $sort_flags = SORT_REGULAR)
	{
		if ( ! is_array($array))
		{
			return false;
		}
		ksort($array, $sort_flags);
		foreach ($array as &$arr)
		{
			static::recursive_ksort($arr, $sort_flags);
		}
		return true;
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


	/**
	 * Retrieves muliple single-key values from a list of arrays.
	 *
	 *     // Get all of the "id" values from a result
	 *     $ids = Arr::pluck($result, 'id');
	 *
	 * [!!] A list of arrays is an array that contains arrays, eg: array(array $a, array $b, array $c, ...)
	 *
	 * @param array $array list of arrays to check
	 * @param string $key key to pluck
	 * @param bool $preserve_keys Preserve keys of the given array?
	 * @return array
	 */
	public static function pluck($array, $key, $preserve_keys = FALSE)
	{
		$values = array();

		foreach ($array as $k => $row)
		{
			if (isset($row[$key]))
			{
				// Found a value in this row
				if ($preserve_keys)
				{
					$values[$k] = $row[$key];
				}
				else
				{
					$values[] = $row[$key];
				}
			}
		}

		return $values;
	}
}