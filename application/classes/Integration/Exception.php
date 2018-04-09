<?php

const INT_E_UNKNOWN_ERROR = 0;
const INT_E_WRONG_CREDENTIALS = 10;
const INT_E_ACCOUNT_BLOCKED = 11;
const INT_E_ACCOUNT_LIMITATION = 20;
const INT_E_WRONG_PARAMS = 21;
const INT_E_WRONG_DATA = 22;
const INT_E_PLAN_LIMITATION = 23;
const INT_E_EMAIL_DUPLICATE = 30;
const INT_E_NOT_REACHABLE = 40;
const INT_E_SERVER_NOT_AVAILABLE = 41;
const INT_E_TOO_FREQUENT_REQUESTS = 42;
const INT_E_INTERNAL_SERVER_ERROR = 43;
const INT_E_WRONG_REQUEST = 50;
const INT_E_OUTDATED_API = 51;
const INT_E_FREQUENT_TEMPORARY_ERR = 52;
const INT_E_EMAIL_NOT_VERIFIED = 60;
const INT_E_PARAMS_NOT_VERIFIED = 61;
const INT_E_DATA_NOT_VERIFIED = 62;

class Integration_Exception extends Exception {

	/**
	 * @var array Errors definitions based on their codes
	 * @link https://team.codelights.com/projects/og/wiki/Архитектура_Интеграций#Обработка-ошибок
	 */
	public static $errors = array(
		INT_E_UNKNOWN_ERROR => 'Unknown error',
		// 1x: Credentials Errors
		INT_E_WRONG_CREDENTIALS => 'Wrong API credentials',
		INT_E_ACCOUNT_BLOCKED => 'Account is blocked',
		// 2x: Account Limitations
		INT_E_ACCOUNT_LIMITATION => 'Internal account limitations',
		INT_E_WRONG_PARAMS => 'Wrong parameters for account',
		INT_E_WRONG_DATA => 'Wrong form data',
		INT_E_PLAN_LIMITATION => 'Reach plan limits',
		// 3x: Duplicate
		INT_E_EMAIL_DUPLICATE => 'Email duplicate',
		// 4x: Temporary Errors
		INT_E_NOT_REACHABLE => 'Network not reachable',
		INT_E_SERVER_NOT_AVAILABLE => 'Server temporarily is not available',
		INT_E_TOO_FREQUENT_REQUESTS => 'Too frequent requests',
		INT_E_INTERNAL_SERVER_ERROR => 'Internal server error',
		// 5x: Integration Errors
		INT_E_WRONG_REQUEST => 'Wrong request',
		INT_E_OUTDATED_API => 'Outdated API interface',
		INT_E_FREQUENT_TEMPORARY_ERR => 'Frequent temporarily error',
		// 6x: Verification Errors
		INT_E_EMAIL_NOT_VERIFIED => 'Email not verified',
		INT_E_PARAMS_NOT_VERIFIED => 'Params not verified',
		INT_E_DATA_NOT_VERIFIED => 'Data not verified',
	);

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
		// TODO Log when needed
		// TODO Notify when needed
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

}