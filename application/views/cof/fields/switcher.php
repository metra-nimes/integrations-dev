<?php
/**
 * Convertful Options Field: Switch
 *
 * On-off switcher
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 * @param $field ['options'] array Array of two key => title pairs
 * @param $field ['text'] array Additional text to show right near the switcher
 * @param $field ['off_confirm'] string Confirm text on Off state
 * @param $field ['on_confirm'] string Confirm text on On state
 *
 * @var $value string Current value
 */
if ( ! isset($field['options']) OR empty($field['options']))
{
	$field['options'] = array(
		TRUE => 'On',
		FALSE => 'Off',
	);
}
$field_keys = array_keys($field['options']);
if (count($field_keys) < 2)
{
	return;
}
?>
<div class="cof-switcher">
	<input type="hidden" name="<?php echo HTML::chars($name) ?>" value="<?php echo HTML::chars($field_keys[1]) ?>" autocomplete="off"
	<?php if (isset($field['off_confirm']) AND ! empty($field['off_confirm'])): ?>
		data-offconfirm="<?php echo $field['off_confirm']; ?>"
	<?php endif; ?>
	<?php if (isset($field['on_confirm']) AND ! empty($field['on_confirm'])): ?>
		data-onconfirm="<?php echo $field['on_confirm']; ?>"
	<?php endif; ?>
>
	<input type="checkbox" value="<?php echo HTML::chars($field_keys[0]) ?>" name="<?php echo HTML::chars($name) ?>"
			id="<?php echo HTML::chars($id) ?>"<?php echo $value == $field_keys[0] ? ' checked' : ''; ?> autocomplete="off">
	<label for="<?php echo HTML::chars($id) ?>">
		<span class="cof-switcher-box"><i></i></span>
		<?php if (isset($field['text']) AND ! empty($field['text'])): ?>
			<span class="cof-switcher-text"><?php echo $field['text'] ?></span>
		<?php endif; ?>
	</label>
</div>