<?php
/**
 * Convertful Options Field: OAuth
 *
 * OAuth client authorization url
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 * @param $field ['url'] string
 * @param $field ['size'] string In '600x600' format
 *
 * @var $value string Current value
 */
$field['size'] = isset($field['size']) ? $field['size'] : '600x600';
?>
<div class="cof-oauth" data-url="<?php echo HTML::chars($field['url']) ?>" data-size="<?php echo HTML::chars($field['size']) ?>">
	<input type="hidden" name="<?php echo HTML::chars($name) ?>" value="<?php echo HTML::chars(json_encode($value)) ?>">
	<a class="g-btn style_solid color_primary action_connect" href="javascript:void(0)"><?php echo $field['title'] ?></a>
</div>
