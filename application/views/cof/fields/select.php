<?php
/**
 * Convertful Options Field: Select
 *
 * Simple select dropdown.
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 * @param $field ['description'] string Field title
 * @param $field ['options'] array List of value => title
 *
 * @var $value string Current value
 */

$placeholder = ! ! Arr::get($field, 'placeholder');
?>
<div class="cof-select">
<select name="<?php echo HTML::chars($name) ?>" id="<?php echo HTML::chars($id) ?>" autocomplete="off">
	<?php foreach (Arr::get($field, 'options', array()) AS $option_value => $option_title): ?>
		<?php if (is_array($option_title)): ?>
			<?php $option_label = Arr::path($field, 'options_labels.'.$option_value, NULL)? Arr::path($field, 'options_labels.'.$option_value, NULL):$option_value;?>
			<optgroup label="<?php echo $option_label ?>" data-id="<?php echo $option_value?>">
				<?php foreach ($option_title AS $sub_option_value => $sub_option_title): ?>
					<option class="<?php if ($placeholder AND empty($option_value)) echo 'holder'?>" value="<?php echo HTML::chars($sub_option_value) ?>"<?php echo(($sub_option_value == $value) ? ' selected' : '') ?>><?php echo HTML::chars($sub_option_title) ?></option>
				<?php endforeach; ?>
			</optgroup>
		<?php else: ?>
			<option class="<?php if ($placeholder AND empty($option_value)) echo 'holder'?>" value="<?php echo HTML::chars($option_value) ?>"<?php echo(($option_value == $value) ? ' selected' : '') ?>><?php echo HTML::chars($option_title) ?></option>
		<?php endif; ?>
	<?php endforeach; ?>
</select>
</div>
