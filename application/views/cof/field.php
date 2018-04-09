<?php
/**
 * Output single COF field
 *
 * @var string $name Field name
 * @var string $id Field ID
 * @var array $field Field settings
 * @var array $values Current fieldset values (including value for the current field)
 * @var array $errors Errors to display on load
 */

if (isset($field['place_if']) AND ! $field['place_if'])
{
	return;
}
if ( ! isset($field['type']))
{
	throw new Exception($name.' field has no type defined');
}


if ( ! isset($field['std']))
{
	$field['std'] = ((isset($field['options']) AND is_array($field['options'])) ? key($field['options']) : NULL);
}
$value = isset($values[$name]) ? $values[$name] : $field['std'];

$row_classes = ' type_'.$field['type'];
if ($field['type'] != 'message' AND isset($field['description']) AND ! empty($field['description']))
{
	if ( ! isset($field['description_type']) OR empty($field['description_type']))
	{
		// Setting the default description type
		if ($field['type'] === 'switcher')
		{
			$field['description_type'] = 'right';
		}
		elseif ( ! isset($field['title']) OR empty($field['title']))
		{
			$field['description_type'] = 'bottom';
		}
		else
		{
			$field['description_type'] = 'default';
		}
	}
	$row_classes .= ' desc_'.$field['description_type'];
}
if (isset($field['classes']) AND ! empty($field['classes']))
{
	$row_classes .= ' '.$field['classes'];
}
if (isset($errors) AND isset($errors[$name]))
{
	$row_classes .= ' check_wrong';
}

echo '<div class="cof-form-row'.$row_classes.'" data-name="'.$name.'" data-id="'.$id.'"';
echo '>';
if (isset($field['title']) AND ! empty($field['title']))
{
	echo '<div class="cof-form-row-title">';
	echo '<span>'.$field['title'].'</span>';
	if (Arr::get($field, 'description') AND Arr::get($field, 'description_type') === 'default')
	{
		echo '<div class="cof-form-row-desc">';
		echo '<div class="cof-form-row-desc-icon"></div>';
		echo '<div class="cof-form-row-desc-text">'.$field['description'].'</div>';
		echo '</div>';
	}
	echo '</div>';
}
echo '<div class="cof-form-row-field"><div class="cof-form-row-control">';
// Including the field control itself
echo View::factory('cof/fields/'.$field['type'], array(
	'name' => $name,
	'id' => $id,
	'field' => $field,
	'value' => $value,
));
// Refreshable behavior
if (isset($field['classes']) AND preg_match('~( |^)i-refreshable( |$)~', $field['classes']))
{
	echo '<div class="cof-form-row-control-refresh" title="Refresh"></div>';
}
echo '</div><!-- .cof-form-row-control -->';
if (Arr::get($field, 'description') AND Arr::get($field, 'description_type') !== 'default')
{
	echo '<div class="cof-form-row-desc">';
	echo '<div class="cof-form-row-desc-icon"></div>';
	echo '<div class="cof-form-row-desc-text">'.$field['description'].'</div>';
	echo '</div>';
}
echo '<div class="cof-form-row-state">'.((isset($errors) AND isset($errors[$name])) ? $errors[$name] : '').'</div>';
echo '</div>'; // .cof-form-row-field

echo '</div><!-- .cof-form-row -->';