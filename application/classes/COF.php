<?php

/**
 * Copied from CFORMS and trimmed to required methods only
 */
class COF {

	/**
	 * Filter user defined values based on fieldset config
	 *
	 * @param array $values
	 * @param array $fields COF fieldset config
	 * @return array
	 */
	public static function filter_values(array $values, array $fields)
	{
		$result = array();
		foreach ($fields as $key => $field)
		{
			$type = Arr::get($field, 'type', 'text');
			// Types that have no value at all
			if ($type == 'submit' OR $type == 'alert')
			{
				continue;
			}
			$value = Arr::get($values, $key, Arr::get($field, 'std'));
			if (isset($field['options']) AND is_array($field['options']) AND ! Arr::get($field, 'tokenize', FALSE))
			{
				if (isset($field['multiple']) AND $field['multiple'])
				{
					$value = is_array($value) ? $value : array();
					$value = array_intersect(array_values($value), array_keys($field['options']));
				}
				else
				{
					if ( ! isset($field['options'][$value]))
					{
						$value_verified = FALSE;
						foreach ($field['options'] as $opt_value => $opt_label)
						{
							if (is_array($opt_label) AND isset($opt_label[$value]))
							{
								$value_verified = TRUE;
								break;
							}
						}
						$value = $value_verified ? $value : NULL;
					}
				}
			}

			// Types where value is supposed to be boolean
			elseif ($type == 'switcher')
			{
				$value = (bool) $value;
			}
			// Types with string values
			elseif ($type == 'text' OR $type == 'key' OR $type == 'password')
			{
				$value = trim($value);
			}

			$result[$key] = $value;
		}

		return $result;
	}

	public static function validate_values(array $values, array $fields, array &$errors = array())
	{
		$validation = Validation::factory($values);
		foreach ($fields as $f_name => $field)
		{
			if (isset($field['rules']) AND is_array($field['rules']))
			{
				$validation
					->rules($f_name, $field['rules'])
					->label($f_name, Arr::get($field, 'title'));
			}
		}
		if (($is_valid = $validation->check()) === FALSE)
		{
			$errors = $validation->errors('validation', FALSE);

		}
		return $is_valid;
	}

}