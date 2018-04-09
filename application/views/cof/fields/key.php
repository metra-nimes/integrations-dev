<?php
/**
 * Convertful Options Field: Key
 * 
 * Some secret string. Entered value is treated as a private and is stored in encrypted form.
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 * @param $field ['description'] string Field title
 *
 * @var $value string Current value
 */
?>
<input type="text" name="<?php echo HTML::chars($name) ?>" id="<?php echo HTML::chars($id) ?>" value="<?php echo HTML::chars($value) ?>" autocomplete="off">