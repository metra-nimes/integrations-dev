<?php
/**
 * Convertful Options Field: Text
 *
 * Simple text line.
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 * @param $field ['placeholder'] string Field placeholder
 *
 * @var $value string Current value
 */
$output = '<input type="text" name="'.HTML::chars($name).'" id="'.HTML::chars($id).'" value="'.HTML::chars($value).'"';
if (isset($field['placeholder']) AND ! empty($field['placeholder']))
{
	$output .= ' placeholder="'.HTML::chars($field['placeholder']).'"';
}
$output .= ' autocomplete="off">';
echo $output;