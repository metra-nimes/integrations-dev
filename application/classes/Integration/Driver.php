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
 * 3. Automations - entered by customer, used to describe a specific actions.
 *    Example: subscribe user to list, add tag to user
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
	 * @return Integration_Driver|Integration_OauthDriver
	 * @throws Exception
	 */
	public static function factory($driver_name, array $credentials = NULL, array $meta = NULL)
	{
		$class_name = 'Integration_Driver_'.$driver_name;
		if ( ! class_exists($class_name))
		{
			throw new Exception('Driver class '.$class_name.' not found');
		}

		return new $class_name($credentials, $meta);
	}

	/**
	 * Integration_Driver constructor.
	 *
	 * @param array|NULL $credentials
	 * @param array|NULL $meta
	 */
	protected function __construct(array $credentials = NULL, array $meta = NULL)
	{
		try
		{
			if ($credentials !== NULL)
			{
				$this->set_credentials($credentials);
			}
			if ($meta !== NULL)
			{
				$this->set_meta($meta);
			}
		}
		catch (Integration_Exception $e)
		{
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
		return static::$company_name.' ('.static::$company_url.')';
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
	 * @var array Integration automations
	 */
	protected $automations;

	/**
	 * Rules for form fields
	 * array(
	 *   'type' => array(
	 *        array('rule', array(":field", ":value"), 'Error text'),
	 *      )
	 *    );
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
	 * @param array $params automation params
	 * @param array $subscriber_data
	 *
	 * @throws Integration_Exception
	 */
	public function exec_automation($name, $params, $subscriber_data)
	{
		$email = Arr::get($subscriber_data, 'email', '');
		unset($subscriber_data['email']);
		if (empty($email) OR ! Valid::email($email))
		{
			throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Please enter a valid email');
		}

		if ( ! method_exists($this, $name))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, $name, 'Automation is not valid');
		}
		$this->{$name}($email, $params, $subscriber_data);
	}

	/**
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function exec_automations(array $subscriber_data = [])
	{
		foreach ($this->automations as $automation_id => $automation_params)
		{
			$automation_type = self::get_type_by_id($automation_id);
			$this->exec_automation($automation_type, $automation_params, $subscriber_data);
		}
	}

	/**
	 * Validates entered automations and it's params
	 *
	 * @param array $automations
	 * @throws Integration_Exception if automation params are not valid
	 */
	public function validate_automations(array $automations)
	{
		$errors = [];
		$automations_configs = $this->describe_automations();
		foreach ($automations as $automation_id => $automation)
		{
			$automation_type = self::get_type_by_id($automation_id);
			$automation_rules = Arr::path($automations_configs, [$automation_type, 'params_fields'], []);
			if (empty($automation_rules))
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, $automation_id, 'Automation params are empty');
			}

			if ( ! COF::validate_values($automation, $automation_rules, $errors))
			{
				foreach ($errors as $f_name => $error)
				{
					throw new Integration_Exception(INT_E_WRONG_PARAMS, $f_name, $error);
				}
			}
		}
	}

	/**
	 * Set automations
	 *
	 * @param array $automations
	 * @param bool $validate
	 * @return self
	 * @chainable
	 * @throws Integration_Exception
	 */
	public function set_automations(array $automations, $validate = FALSE)
	{
		$this->automations = $this->filter_automations($automations);
		if ($validate)
		{
			$this->validate_automations($this->automations);
		}

		return $this;
	}

	/**
	 * @todo: phpDoc
	 * @param array $automations
	 * @return array
	 */
	public function filter_automations(array $automations)
	{
		$automations_configs = $this->describe_automations();
		$filtered_automations = [];
		foreach ($automations as $automation_id => $automation_params)
		{
			$automation_type = self::get_type_by_id($automation_id);
			$filtered_automations[$automation_id] = COF::filter_values((array) $automation_params, (array) Arr::path($automations_configs, [$automation_type, 'params_fields'], []));
		}

		return $filtered_automations;
	}

	/**
	 * Get automations
	 *
	 * @param string $path
	 * @param mixed $default
	 * @return mixed
	 */
	public function get_automations($path = NULL, $default = [])
	{
		$automations = $this->automations;
		if ($path === NULL)
		{
			return $automations;
		}

		return Arr::path($automations, $path, $default);
	}

	/**
	 * Get automation type by ID
	 *
	 * @param $id
	 * @return mixed
	 */
	public static function get_type_by_id($id)
	{
		return preg_match('~^([a-z_]+)\:?[\d]*$~', $id, $matches) ? $matches[1] : $id;
	}
}
