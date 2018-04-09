<?php
/**
 * Output a COF fieldset (group of fields)
 *
 * @var array $fields Fields settings name => field
 * @var array $values Fieldset values
 * @var array $errors Fieldset errors to output on load
 * @var string $id_prefix Prefix to add to names when creating IDs from them
 */
if ( ! isset($values) OR ! is_array($values))
{
	$values = array();
}
if ( ! isset($errors) OR ! is_array($errors))
{
	$errors = array();
}
if ( ! isset($id_prefix))
{
	static $fieldset_index;
	$fieldset_index = isset($fieldset_index) ? ($fieldset_index + 1) : 1;
	$id_prefix = 'cof'.$fieldset_index.'_';
}

foreach ($fields as $field_name => $field)
{
	echo View::factory('cof/field', array(
		'name' => $field_name,
		'id' => $id_prefix.$field_name,
		'field' => $field,
		'values' => $values,
		'errors' => $errors,
	));
}