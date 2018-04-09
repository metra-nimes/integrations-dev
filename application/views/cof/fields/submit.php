<?php
/**
 * Convertful Options Field: Submit
 *
 * Simple submit button.
 *
 * @var $name string Field name
 * @var $id string Field ID
 * @var $field array Field options
 *
 * @param $field ['title'] string Field title
 * @param $field ['description'] string Field title
 * @param $field ['style'] string primary / secondary
 * @param $field ['action'] string
 *
 * @var $value string Current value
 */

$field['style'] = isset($field['style']) ? $field['style'] : 'primary';
$classes = ' color_'.$field['style'];
if (isset($field['action']))
{
	$classes .= ' action_'.$field['action'];
}
?>
<a class="g-btn style_solid<?php echo $classes ?>" href="javascript:void(0)"><?php echo $field['title'] ?></a>
