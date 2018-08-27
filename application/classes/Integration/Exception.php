<?php

const INT_E_UNKNOWN_ERROR = 0;
const INT_E_WRONG_CREDENTIALS = 10;
const INT_E_WRONG_PARAMS = 21;
const INT_E_WRONG_DATA = 22;
const INT_E_PLAN_LIMITATION = 23;
const INT_E_EMAIL_LIMITATION = 24;
const INT_E_EMAIL_DUPLICATE = 30;
const INT_E_TEMPORARY_ERROR = 42;
const INT_E_WRONG_REQUEST = 50;

class Integration_Exception extends Exception {

	/**
	 * @var array Errors definitions based on their codes
	 * @link https://team.codelights.com/projects/og/wiki/Архитектура_Интеграций#Обработка-ошибок
	 */
	public static $errors = [
		INT_E_UNKNOWN_ERROR => 'Unknown error',
		// 1x: Credentials Errors
		INT_E_WRONG_CREDENTIALS => 'Wrong API credentials',
		// 2x: Account Limitations
		INT_E_WRONG_PARAMS => 'Wrong parameters for account',
		INT_E_WRONG_DATA => 'Wrong form data',
		INT_E_PLAN_LIMITATION => 'Reach plan limits',
		INT_E_EMAIL_LIMITATION => 'Reach email limits',
		// 3x: Duplicate
		INT_E_EMAIL_DUPLICATE => 'Email duplicate',
		// 4x: Temporary Errors
		INT_E_TEMPORARY_ERROR => 'Temporary error',
		// 5x: Integration Errors
		INT_E_WRONG_REQUEST => 'Wrong request',
	];

	protected $field = NULL;

	/**
	 * Integration_Exception constructor.
	 * @param int $code
	 * @param string $field
	 * @param string $message
	 */
	public function __construct($code, $field = NULL, $message = NULL)
	{
		$this->code = $code;
		$this->field = $field;
		$this->message = isset($message) ? $message : Arr::get(self::$errors, $code);
	}

	/**
	 * Get the relevant form field
	 * @param string $default If no field is specified, which will be returned
	 * @return string|NULL
	 */
	public function getField($default = NULL)
	{
		return ($this->field !== NULL) ? $this->field : $default;
	}


	/**
	 * @param Model_Integration $integration
	 * @param array $error_codes
	 * @todo: refactor
	 */
	public static function retry(Model_Integration $integration, $error_codes = [INT_E_WRONG_CREDENTIALS])
	{
		$interrors = DB::select('id')
			->from('automations')
			->where('integration_id', '=', $integration->id)
			->and_where('err_code', 'IN', $error_codes)
			->and_where('status', '=', 'error')
			->execute()
			->as_array('id', 'id');

		if ( ! empty($interrors))
		{
			// Set next retry to now
			DB::update('interrors')
				->set(['next_retry' => date('Y-m-d H:i:s')])
				->where('id', 'IN', array_values($interrors))
				->execute();
		}

		DB::update('notifications')
			->set(array('closed' => 1))
			->where('type', '=', 'integration')
			->and_where('receiver_id', '=', $integration->owner_id)
			->execute();
	}

}