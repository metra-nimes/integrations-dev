<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Interface Interface_Integration
 *
 * Terms that are used here:
 * 1. Credentials - entered by customer, used to connect a single account of an integration provider. Example:
 *    username/password or api-key. If we cannot fetch account name from a provider, internal name should be also
 *    specified here.
 * 2. Meta - fetched from integration provider using the credentials, some account-related information that will be used
 *    later. Example: available lists, internal account name (if it can be fetched).
 * 3. Params - entered by customer, used to connect a specific widget to a specific integrated account. Example: a
 *    subscription list to add users to, use double opt-in?, send welcome message?
 * 4. Person Data - data about a person in Convertful internal format
 * 5. Integration Data (short: int_data) - data about a person in integration-compatible format (may vary depending on
 *    whether it's in or out data)
 */
abstract class Integration_Driver {

	/**
	 * Create a single integration driver instance
	 *
	 * @param string $driver_name
	 * @param array $credentials If provided will be validated
	 * @param array $meta
	 * @param array $params If provided will be validated
	 * @return Integration_Driver|Integration_OauthDriver
	 * @throws Exception
	 */
	public static function factory($driver_name, array $credentials = NULL, array $meta = NULL, array $params = NULL)
	{
		$class_name = 'Integration_Driver_'.$driver_name;
		if ( ! class_exists($class_name))
		{
			throw new Exception('Driver class '.$class_name.' not found');
		}

		return new $class_name($credentials, $meta, $params);
	}

	protected function __construct(array $credentials = NULL, array $meta = NULL, array $params = NULL)
	{
		if ($credentials !== NULL)
		{
			$this->set_credentials($credentials);
		}
		if ($meta !== NULL)
		{
			$this->set_meta($meta);
		}
		if ($params !== NULL)
		{
			$this->set_params($params);
		}
	}

	/**
	 * Get driver name based on class name
	 *
	 * @return string
	 */
	public function get_driver_name()
	{
		return preg_replace('~^.*?([a-zA-Z0-9]+)$~', '$1', get_class($this));
	}

	/**
	 * Name of the legal entity
	 * @var null
	 */
	protected static $company_name = NULL;

	/**
	 * Address of the legal entity
	 * @var null
	 */
	protected static $company_address = NULL;

	/**
	 * Main site url
	 * @var null
	 */
	protected static $company_url = NULL;

	/**
	 * Get formatted company information
	 *
	 * @return string
	 */
	public static function get_company_info()
	{
		return static::$company_name . ' (' .static::$company_url . ')';
	}

	/**
	 * @var array Driver request log
	 */
	public $requests_log = array();

	/**
	 * @var array Integration Credentials
	 */
	protected $credentials = NULL;

	/**
	 * Describes COF fieldset config to render a credentials form, so a user could connect integration account
	 *
	 * @param $refresh_credentials
	 * @return array COF fieldset config
	 */
	abstract public function describe_credentials_fields($refresh_credentials = FALSE);

	/**
	 * Format the entered credentials data before it's validated and saved in a database. This function is efficient for
	 * removing excess spaces and providing fault tolerance for all kinds of alternative spellings for entered data.
	 *
	 * (!) When overloading this function make sure this parent function is called first
	 *
	 * @param array $credentials Integration credentials
	 * @return array Filtered credentials
	 */
	public function filter_credentials(array $credentials)
	{
		return COF::filter_values($credentials, $this->describe_credentials_fields());
	}

	/**
	 * Validates the provided entered credentials
	 * @param array $credentials
	 * @throws Integration_Exception if credentials have some error
	 */
	public function validate_credentials(array $credentials)
	{
		$errors = array();
		if ( ! COF::validate_values($credentials, $this->describe_credentials_fields(), $errors))
		{
			foreach ($errors as $f_name => $error)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $f_name, $error);
			}
		}
	}

	/**
	 * Set credentials
	 *
	 * @param array $credentials
	 * @param bool $validate
	 * @return self
	 * @throws Integration_Exception When validated and contains error(s)
	 */
	public function set_credentials(array $credentials, $validate = FALSE)
	{
		$credentials = $this->filter_credentials($credentials);
		if ($validate)
		{
			$this->validate_credentials($credentials);
		}
		$this->credentials = $credentials;
		return $this;
	}

	/**
	 * Get specific credential by path or all credentials
	 *
	 * @param string $path Dot-separated path of a needed credential
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function get_credentials($path = NULL, $default = NULL)
	{
		$credentials = $this->credentials;
		if ($path === NULL)
		{
			return $credentials;
		}

		return Arr::path($credentials, $path, $default);
	}

	/**
	 * @param array $other_integrations
	 * @throws Integration_Exception
	 */
	public function validate_if_unique(&$other_integrations)
	{
		foreach ($this->describe_credentials_fields() as $f_name => $field)
		{
			// Names and keys shouldn't duplicate
			if ($f_name === 'name')
			{
				foreach ($other_integrations as $other_integration)
				{
					if (Arr::get($this->credentials, $f_name) == Arr::get($other_integration->credentials, $f_name))
					{
						throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $f_name, 'You already used this name for some other integration');
					}
				}
			}
			// Keys fields shouldn't duplicate
			elseif ($field['type'] === 'key')
			{
				foreach ($other_integrations as $other_integration)
				{
					if (Arr::get($this->credentials, $f_name) == Arr::get($other_integration->credentials, $f_name))
					{
						throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $f_name, sprintf('You already connected this %s', Arr::get($field, 'title')));
					}
				}
			}
			// oAuth tokens shouldn't duplicate
			elseif ($field['type'] === 'oauth')
			{
				$token_key = Arr::get($field, 'token_key', 'code');
				foreach ($other_integrations as $other_integration)
				{
					if (Arr::path($this->credentials, array($f_name, $token_key)) == Arr::path($other_integration->credentials, array($f_name, $token_key)))
					{
						throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $f_name, 'You already connected this account');
					}
				}
			}
		}
	}

	/**
	 * Get internal name for the given integration account, that will be shown in accounts lists for selection
	 *
	 * @return string
	 */
	public function get_name()
	{
		return $this->get_credentials('name', '');
	}

	/**
	 * @var array Integration Meta
	 */
	protected $meta;

	/**
	 * Fetch meta data by integration credentials
	 * @return self
	 * @throws Integration_Exception if cannot connect using the credentials
	 * @chainable
	 */
	abstract public function fetch_meta();

	/**
	 * Manually set meta
	 *
	 * @param array $meta
	 * @return self
	 * @chainable
	 */
	public function set_meta(array $meta)
	{
		$this->meta = $meta;
		return $this;
	}

	/**
	 * Get specific meta by path or all credentials
	 *
	 * @param string $path Dot-separated path of a needed meta
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function get_meta($path = NULL, $default = NULL)
	{
		$meta = $this->meta;
		if ($path === NULL)
		{
			return $meta;
		}

		return Arr::path($meta, $path, $default);
	}

	/**
	 * @var array Integration Params
	 */
	protected $params;

	/**
	 * Describes COF fieldset config to render widget integration parameters
	 *
	 * @return array COF fieldset config for params form, so a user could connect specific optin with integration
	 */
	public function describe_params_fields(){
		return [];
	}

	/**
	 * Format the entered credentials data before it's validated and saved in a database. This function is efficient for
	 * removing excess spaces and providing fault tolerance for all kinds of alternative spellings for entered data.
	 *
	 * (!) When overloading this function make sure this parent function is called first
	 *
	 * @param array $params
	 * @return array
	 */
	public function filter_params(array $params)
	{
		return COF::filter_values($params, $this->describe_params_fields());
	}

	/**
	 * Validates entered params
	 *
	 * @param array $params
	 * @throws Integration_Exception if params are not valid
	 */
	public function validate_params(array $params)
	{
		$errors = array();
		if ( ! COF::validate_values($params, $this->describe_params_fields(), $errors))
		{
			foreach ($errors as $f_name => $error)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, $f_name, $error);
			}
		}
	}

	/**
	 * Set params
	 *
	 * @param array $params
	 * @param bool $validate
	 * @return self
	 * @chainable
	 */
	public function set_params(array $params, $validate = FALSE)
	{
		$this->params = $this->filter_params($params);
		if ($validate)
		{
			$this->validate_params($this->params);
		}
		return $this;
	}

	/**
	 * Get params
	 *
	 * @param string $path
	 * @param mixed $default
	 * @return mixed
	 */
	public function get_params($path = NULL, $default = NULL)
	{
		$params = $this->params;
		if ($path === NULL)
		{
			return $params;
		}

		return Arr::path($params, $path, $default);
	}

	/**
	 * Rules for form fields
	 * array(
	 *   'type' => array(
	 *	    array('rule', array(":field", ":value"), 'Error text'),
	 *	  )
	 *	);
	 *
	 * @return array
	 */
	public static function describe_data_rules()
	{
		return array();
	}


	/**
	 * @todo: this method should be abstract
	 * @return mixed
	 */
	public function describe_show_if()
	{
		return [];
	}


	/**
	 * @todo: this method should be abstract
	 * @return array
	 */
	public function suggest_custom_fields()
	{
		return [];
	}

	/**
	 * @todo: this method should be abstract
	 * @return mixed
	 */
	public function describe_automations()
	{
		return [];
	}

	/**
	 * Execute automation
	 *
	 * @param string $name
	 * @param array $params
	 * @param array $subscriber_data
	 *
	 * @return
	 */
	public function exec_automation($name, $params, $subscriber_data)
	{
		//TODO: validate automation name and params

		$email = Arr::get($subscriber_data, 'email', '');
		unset($subscriber_data['email']);
		if (empty($email) OR ! Valid::email($email))
		{
			return $this->add_error('Please enter a valid email', 'email');
		}

		return $this->{$name}($email, $params, $subscriber_data);
	}
}
