<?php
/**
 * Convertful Options Field: Select2
 *
 * Advanced select2-driven dropdown.
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 * @param $field ['description'] string Field title
 * @param $field ['options'] array List of value => title
 * @param $field ['multiple'] bool Can multiple values be selected?
 * @param $field ['tokenize'] bool Automatic tokenization behavior https://select2.github.io/examples.html#tokenizer
 *
 * @var $value string|array Current value (or set of values for multiple selector
 */
$multiple = ! ! Arr::get($field, 'multiple');
$tokenize = ! ! Arr::get($field, 'tokenize');
$field['options'] = isset($field['options']) ? $field['options'] : array();
if ($tokenize)
{
	$multiple = TRUE;
}
$output = '<select name="'.HTML::chars($name).'" id="'.HTML::chars($id).'" autocomplete="off"';
if ($multiple)
{
	$value = (empty($value) OR ! is_array($value)) ? array() : $value;
	$output .= ' multiple="multiple"';
}
if ($tokenize)
{
	$output .= ' class="i-tokenize"';
	// add values to options
	$field['options'] = array_merge($field['options'], array_combine($value, $value));
}
$output .= '>';

foreach ($field['options'] as $option_value => $option_title)
{
	if (is_array($option_title))
	{
		// Option Group
		if (isset($option_title['title']) AND isset($option_title['options']))
		{
			// Format 1: index => array( title => ... , options => ... )
			$optgroup_title = &$field['options'][$option_value]['title'];
			$optgroup_options = &$field['options'][$option_value]['options'];
		}
		else
		{
			// Format 2: title => options
			$optgroup_title = $option_value;
			$optgroup_options = $option_title;
		}
		$output .= '<optgroup label="'.HTML::chars($optgroup_title).'">';
		foreach ($optgroup_options as $option_value => $option_title)
		{
			$is_selected = ($multiple ? in_array($option_value, $value) : ($option_value === $value));
			$output .= '<option value="'.HTML::chars($option_value).'"'.($is_selected ? ' selected' : '').'>'.HTML::chars($option_title).'</option>';
		}
		$output .= '</optgroup>';
	}
	else
	{
		// Just an option
		$is_selected = ($multiple ? in_array($option_value, $value) : ($option_value === $value));
		$output .= '<option value="'.HTML::chars($option_value).'"'.($is_selected ? ' selected' : '').'>'.HTML::chars($option_title).'</option>';
	}
}

$output .= '</select>';
if ($tokenize)
{
	$output .= '<div class="cof-field-hider"></div>';
}
echo $output;
