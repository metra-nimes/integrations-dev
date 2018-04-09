<?php
/**
 * Convertful Options Field: Alert
 *
 * Closable alert that has no own value.
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['style'] string Alert style: 'note' / 'warning'
 * @param $field ['text'] string Alert text
 * @param $field ['closable'] bool Is the alert closable? (Default: TRUE)
 */

$classes = '';
if (isset($field['style']))
{
	$classes .= ' type_'.$field['style'];
}
$field['closable'] = ( ! isset($field['closable']) OR $field['closable']);
?>
<div class="g-alert<?php echo $classes ?>">
<?php if ($field['closable']): ?>
	<div class="g-alert-closer"></div>
<?php endif; ?>
	<div class="g-alert-body"><?php echo $field['text'] ?></div>
</div>