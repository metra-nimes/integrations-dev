<?php
/**
 * Convertful Options Field: postvars
 *
 * options for POST method
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 *
 * @var $value array Current value
 */
if (! is_array($value))
{
	$value = (array)$value;
}
?>
<div class="cof-postvars" data-name="<?php echo HTML::chars($name) ?>" id="<?php echo HTML::chars($id) ?>">
	<table class="cof-postvars-table g-table">
		<tbody class="cof-postvars-list">
		<?php foreach ($value AS $postvar_key => $postvar_value): ?>
			<tr class="cof-postvars-item" data-key="<?php echo $postvar_key ?>">
				<td class="for_name"><?php echo HTML::chars($postvar_key) ?></td>
				<td class="for_field"><input type="text" name="value" placeholder="<?php echo $postvar_value; ?>"></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<template data-id="emptypostvarsitem">
		<tr class="cof-postvars-item">
			<td class="for_name"></td>
			<td class="for_field"><input type="text" name="value" placeholder="Value"></td>
		</tr>
	</template>
</div>