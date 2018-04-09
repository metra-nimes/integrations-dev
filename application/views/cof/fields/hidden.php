<?php
/**
 * Convertful Options Field: Hidden
 *
 * Hidden string field.
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @var $value string Current value
 */
if (is_array($value))
{
	$value = json_encode($value);
}
?>
<input type="hidden" name="<?php echo HTML::chars($name) ?>" id="<?php echo HTML::chars($id) ?>" value="<?php echo HTML::chars($value) ?>">