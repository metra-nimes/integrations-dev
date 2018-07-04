<?php defined('SYSPATH') OR die('No direct script access.');

abstract class Controller extends Kohana_Controller {

	/**
	 * @var array errors container
	 */
	protected $_errors = array();

	/**
	 * Adds Error to _errors array
	 *
	 * @param string $message Error message or code to obtain error message from messages file
	 * @param string $field Relevant field name (to properly put it to frontend later)
	 *
	 * @return bool
	 */
	protected function add_error($message, $field = NULL)
	{
		if (preg_match('~^validation\.([a-z0-9\_]+)~u', $message, $matches))
		{
			$field = isset($field) ? $field : $matches[1];
			$message = Kohana::message('errors', $message, $message);
		}
		$this->_errors[$field] = $message;

		return TRUE;
	}

	protected function has_errors()
	{
		return (count($this->_errors) != 0);
	}

	protected function has_no_errors()
	{
		return (count($this->_errors) == 0);
	}
}