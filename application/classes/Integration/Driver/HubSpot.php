<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * HubSpot Integration
 * @link http://developers.hubspot.com/docs/overview
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_HubSpot extends Integration_OauthDriver implements Integration_Interface_BackendESP {

	private $banned_properties = array(
		'createdate',
		'lastmodifieddate',
		'lifecyclestage',
		'num_conversion_events',
		'num_unique_conversion_events',
		'hs_lifecyclestage_subscriber_date',
		'email',
		'hs_searchable_calculated_phone_number',
		'hs_email_domain',
	);

	/**
	 * Get endpoint URL for API calls
	 *
	 * @return string
	 */
	public function get_endpoint()
	{
		return 'https://api.hubapi.com';
	}

	protected function get_key()
	{
		return Arr::path(Kohana::$config->load('integrations_oauth')->as_array(), 'HubSpot.key');
	}

	protected function get_secret()
	{
		return Arr::path(Kohana::$config->load('integrations_oauth')->as_array(), 'HubSpot.secret');
	}

	/**
	 * Describes COF fieldset config to render a credentials form, so a user could connect integration account
	 *
	 * @param bool $refresh
	 * @return array COF fieldset config
	 */
	public function describe_credentials_fields($refresh = FALSE)
	{
		return array(
			'name' => array(
				'title' => 'Account Name',
				'type' => 'text',
				'description' => 'It\'s an internal value, which can be helpful to identify a specific account in future.',
				'rules' => array(
					array('not_empty'),
				),
			),
			'oauth' => array(
				'title' => 'Connect with HubSpot',
				'type' => 'oauth',
				// The name of array key that will contain token
				'token_key' => 'code',
				// https://developer.constantcontact.com/docs/authentication/authentication.html
				'url' => 'https://app.hubspot.com/oauth/authorize'.
					'?client_id='.$this->get_key().
					//'&scope=contacts%20automation'.
					'&scope=contacts'.
					'&redirect_uri='.urlencode(URL::domain().'/api/integration/complete_oauth/HubSpot'),
				'size' => '600x600',
				'rules' => array(
					array('not_empty'),
				),
			),
		);
	}

	/**
	 * Fetch meta data by integration credentials
	 * @return self
	 * @throws Integration_Exception if cannot connect using the credentials
	 * @chainable
	 */
	public function fetch_meta()
	{
		$this->meta = array(
			'name' => NULL,
			'lists' => array(),
			'properties' => array(),
		);
		$this->provide_oauth_access();

		$access_token = Arr::path($this->credentials, 'oauth.access_token');

		// Fetch account meta
		// http://developers.hubspot.com/docs/methods/lists/get_lists
		$r = Integration_Request::factory()
			->method('GET')
			->header('Accept-type', 'Application/json')
			->url($this->get_endpoint().'/oauth/v1/access-tokens/'.$access_token)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth', 'Account Access Token is not valid');
			}
			elseif ($r->code == 404)
			{
				throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
			}
			elseif ($r->code == 409)
			{
				throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
		else
		{
			$this->meta['name'] = $r->get('hub_domain');
			$this->meta['user'] = $r->get('user');
		}

		// Fetch lists
		// http://developers.hubspot.com/docs/methods/lists/get_lists
		$r = Integration_Request::factory()
			->method('GET')
			->header('Accept-type', 'Application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/contacts/v1/lists')
			->data(array(
				'count' => 100,
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth', 'Account Access Token is not valid');
			}
			elseif ($r->code == 404)
			{
				throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
			}
			elseif ($r->code == 409)
			{
				throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
		else
		{
			foreach ($r->get('lists') as $list)
			{
				$list_id = Arr::get($list, 'listId', NULL);
				if ( ! is_null($list_id) AND Arr::get($list, 'listType') == 'STATIC')
				{
					$this->meta['lists'][$list_id] = trim(Arr::get($list, 'name', ''));
				}
			}
		}

		$this->get_properties(TRUE);

		return $this;
	}

	/**
	 * Describes COF fieldset config to render widget integration parameters
	 *
	 * @return array COF fieldset config for params form, so a user could connect specific optin with integration
	 */
	public function describe_params_fields()
	{
		$lists = Arr::get($this->meta, 'lists', array());

		if (empty($lists))
		{
			$lists[0] = 'Default contact list';
		}

		return array(
			'warning' => array(
				'type' => 'alert',
				'text' => 'The maximum length of field\'s content should not exceed 100 characters.',
				'closable' => TRUE,
				//'classes' => '',
			),
			'listId' => array(
				'title' => 'List',
				'description' => NULL,
				'type' => 'select',
				'options' => $lists,
				'classes' => 'i-refreshable',
			),
		);
	}

	public static function describe_data_rules()
	{
		return array(
			'text' => array(
				array('max_length', array(':value', 100), 'The maximum length of field\'s content should not exceed 100 characters.'),
			),
			'hidden' => array(
				array('max_length', array(':value', 100), 'The maximum length of field\'s content should not exceed 100 characters.'),
			),
		);
	}

	public function validate_params(array $params)
	{
		$listId = Arr::get($params, 'listId', '');
		$available_lists = Arr::get($this->meta, 'lists', array());
		if ( ! is_array($available_lists))
		{
			$available_lists = array();
		}
		if ( ! empty($listId) AND ! isset($available_lists[$listId]))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'listId', 'List not found');
		}
	}


	protected $standard_properties = array(
		'first_name' => 'firstname',
		'last_name' => 'lastname',
		'phone' => 'phone',
		'company' => 'company',
		'site' => 'website',
	);

	/**
	 * Translate person data from standard convertful to integration format
	 *
	 * @param array $person_data Person data in standard convertful format
	 * @param bool $create_missing_fields
	 * @return array Integration-specified person data format
	 * @throws Integration_Exception
	 */
	public function translate_person_data_to_int_data(array $person_data, $create_missing_fields = FALSE)
	{
		$int_data = array(
			'properties' => array(),
		);
		$use_cached_properties = TRUE;
		$properties = $this->get_properties();

		$add_person_property = function ($property_title, $value) use (&$int_data, &$properties, &$use_cached_properties, $create_missing_fields) {
			// try to get normal property name for HubSpot
			if (isset($this->standard_properties[$property_title]))
			{
				$property_key = $this->standard_properties[$property_title];

				if ( ! isset($properties[$property_key]) AND $create_missing_fields)
				{
					// not found, create property
					$property_title = Inflector::humanize($property_title);
					$new_property_key = $this->create_property($property_title);
					if ($new_property_key)
					{
						$properties[$new_property_key] = $property_title;
						$property_key = $new_property_key;
					}
				}
			}
			else
			{
				// search property in cache
				if ($use_cached_properties AND ! in_array($property_title, $properties))
				{
					// not found, update cache
					$properties = $this->get_properties(TRUE);
					$use_cached_properties = FALSE;
				}

				// search again
				if ( ! in_array($property_title, $properties) AND $create_missing_fields)
				{
					// not found, create property
					$new_property_key = $this->create_property($property_title);
					if ($new_property_key)
					{
						$properties[$new_property_key] = $property_title;
					}
				}

				$property_key = array_search($property_title, $properties);
			}

			if ($property_key)
			{
				$int_data['properties'][$property_key] = array(
					"property" => $property_key,
					"value" => $value,
				);
			}
		};

		foreach (Arr::get($person_data, 'meta', array()) as $key => $value)
		{
			$add_person_property($key, $value);
		}

		unset($person_data['meta']);

		foreach ($person_data as $key => $value)
		{
			if ($key !== 'name')
			{
				$add_person_property($key, $value);
			}
		}

		$name = Arr::get($person_data, 'name', NULL);
		$first_name = Arr::path($int_data, 'properties.firstname.value', NULL);
		$last_name = Arr::path($int_data, 'properties.lastname.value', NULL);
		if ($name AND ! $first_name AND ! $last_name)
		{
			if ( $use_cached_properties)
			{
				$properties = $this->get_properties(TRUE);
			}
			$name_arr = explode(' ', $name.' ', 2);
			if (! isset($properties['firstname']))
			{
				$add_person_property('firstname', $name_arr[0]);
			}
			else
			{
				$int_data['properties']['firstname'] = array(
					"property" => 'firstname',
					"value" => $name_arr[0],
				);
			}

			if ($name_arr[1])
			{
				if (! isset($properties['lastname']))
				{
					$add_person_property('lastname', trim($name_arr[1]));
				}
				else
				{
					$int_data['properties']['lastname'] = array(
						"property" => 'lastname',
						"value" => trim($name_arr[1]),
					);
				}
			}
		}

		if (empty($int_data['properties']))
		{
			unset($int_data['properties']);
		}
		else
		{
			$int_data['properties'] = array_values($int_data['properties']);
		}

		return $int_data;
	}

	/**
	 * Translate person data from integration to standard convertful format
	 *
	 * @param array $int_data Person data in integration format
	 * @return array Person data in standard convertful format
	 */
	public function translate_int_data_to_person_data(array $int_data)
	{
		$person_data = array(
			'meta' => array(),
		);
		$use_cached_properties = TRUE;
		$properties = $this->get_properties();

		foreach (Arr::get($int_data, 'properties', array()) as $property_key => $property)
		{
			$value = Arr::get($property, 'value');
			if (empty($value))
			{
				continue;
			}

			if ($p_key = array_search($property_key, $this->standard_properties))
			{
				// main properties
				$person_data[$p_key] = $value;
			}
			else
			{
				// search property in cache
				if ($use_cached_properties AND ! array_key_exists($property_key, $properties))
				{
					// not found, try to update properties cache
					$properties = $this->get_properties(TRUE);
					$use_cached_properties = FALSE;
				}

				if (array_key_exists($property_key, $properties))
				{
					$person_data['meta'][$properties[$property_key]] = $value;
				}
			}
		}

		if (empty($person_data['meta']))
		{
			unset($person_data['meta']);
		}

		return $person_data;
	}

	/**
	 * Get person by email
	 * @param string $email
	 * @return array|NULL Person data or NULL, if person not found
	 * @throws Integration_Exception
	 */
	public function get_person($email)
	{
		$this->provide_oauth_access();
		$access_token = $this->get_credentials('oauth.access_token', '');

		// Search for duplicate
		// @link http://developers.hubspot.com/docs/methods/contacts/get_contact_by_email
		$r = Integration_Request::factory()
			->method('GET')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			))
			->header('Accept-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/contacts/v1/contact/email/'.$email.'/profile')
			->log_to($this->requests_log)
			->execute();

		// The contact does not exists
		if ($r->code == 404)
		{
			return NULL;
		}
		elseif ($r->code == 401 OR $r->code == 403)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
		}
		elseif ($r->code !== 200)
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		return $this->translate_int_data_to_person_data($r->data);
	}

	/**
	 * Create a person with given data
	 *
	 * @param string $email
	 * @param array $person_data
	 * @throws Integration_Exception If couldn't submit
	 */
	public function create_person($email, $person_data)
	{
		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$listId = $this->get_params('listId', NULL);
		$email = strtolower($email);

		$int_data = $this->translate_person_data_to_int_data($person_data, TRUE);
		if ( ! isset($int_data['properties']))
		{
			$int_data['properties'] = array();
		}
		$int_data['properties'][] = array(
			'property' => 'email',
			'value' => $email,
		);

		// Create contact
		// @link http://developers.hubspot.com/docs/methods/contacts/create_contact
		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			))
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/contacts/v1/contact')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 409)
			{
				throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email');
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
		$vid = $r->get('vid');

		if ( $listId AND $vid)
		{
			// Add the contact to the list
			// http://developers.hubspot.com/docs/methods/lists/add_contact_to_list
			$r = Integration_Request::factory()
				->method('POST')
				->curl(array(
					CURLOPT_CONNECTTIMEOUT_MS => 5000,
					CURLOPT_TIMEOUT_MS => 15000,
				))
				->header('Accept-Type', 'application/json')
				->header('Content-Type', 'application/json')
				->header('Authorization', 'Bearer '.$access_token)
				->url($this->get_endpoint().'/contacts/v1/lists/'.$listId.'/add/')
				->data(array(
					'vids' => array(
						$vid,
					),
				))
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				if ($r->code == 404)
				{
					throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
				}
				elseif ($r->code == 401 OR $r->code == 403)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
				}
				else
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
				}
			}
			if (in_array($vid, $r->get('discarded')))
			{
				throw new Integration_Exception(INT_E_EMAIL_DUPLICATE);
			}
			elseif (in_array($vid, $r->get('invalidVids')))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'listId', 'Invalid vid parameter');
			}
			elseif (in_array($vid, $r->get('invalidEmails')))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'listId', 'Invalid E-mail');
			}
		}
	}

	/**
	 * Update a person with given data
	 *
	 * @param string $email
	 * @param array $person_data
	 * @throws Integration_Exception If couldn't submit
	 */
	public function update_person($email, $person_data)
	{
		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$email = strtolower($email);
		$int_data = $this->translate_person_data_to_int_data($person_data, TRUE);

		// Update contact
		// @link https://developers.hubspot.com/docs/methods/contacts/update_contact-by-email
		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			))
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/contacts/v1/contact/email/'.$email.'/profile')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();


		if ( ! $r->is_successful())
		{
			switch ($r->code)
			{
				case 409:
					throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email');
					break;
				case 400:
					// In this case we send empty data to integration
					break;
				default:
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}

	protected function oauth_refresh_token()
	{
		if (Arr::path($this->credentials, 'oauth.refresh_token', NULL) == NULL)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth', 'refresh_token not found');
		}
		// Refresh access token
		// http://developers.hubspot.com/docs/methods/oauth2/refresh-access-token
		$r = Integration_Request::factory()
			->method('POST')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/oauth/v1/token')
			->data(array(
				'grant_type' => 'refresh_token',
				'client_id' => $this->get_key(),
				'client_secret' => $this->get_secret(),
				'redirect_uri' => URL::domain().'/api/integration/complete_oauth/HubSpot',
				'refresh_token' => Arr::path($this->credentials, 'oauth.refresh_token'),
			))
			->log_to($this->requests_log)
			->execute();
		if ($r->is_successful())
		{
			$refresh_token = $r->get('refresh_token');
			$access_token = $r->get('access_token');
			$expires_in = $r->get('expires_in');
			if (empty($refresh_token))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain refresh_token'));
			}
			if (empty($access_token))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain access_token'));
			}
			if (empty($expires_in))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain expires_in'));
			}

			if ( ! isset($this->credentials['oauth']))
			{
				$this->credentials['oauth'] = array();
			}
			$this->credentials['oauth']['expires_at'] = time() + $expires_in - 60;
			$this->credentials['oauth']['refresh_token'] = $refresh_token;
			$this->credentials['oauth']['access_token'] = $access_token;
		}
		else
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
		}
	}

	protected function oauth_get_token()
	{
		// Get access token
		// http://developers.hubspot.com/docs/methods/oauth2/get-access-and-refresh-tokens
		$r = Integration_Request::factory()
			->method('POST')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/oauth/v1/token')
			->data(array(
				'grant_type' => 'authorization_code',
				'client_id' => $this->get_key(),
				'client_secret' => $this->get_secret(),
				'redirect_uri' => URL::domain().'/api/integration/complete_oauth/HubSpot',
				'code' => Arr::path($this->credentials, 'oauth.code'),
			))
			->log_to($this->requests_log)
			->execute();
		if ($r->is_successful())
		{
			$refresh_token = $r->get('refresh_token');
			$access_token = $r->get('access_token');
			$expires_in = $r->get('expires_in');
			if (empty($refresh_token))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain refresh_token'));
			}
			if (empty($access_token))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain access_token'));
			}
			if (empty($expires_in))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain expires_in'));
			}

			if ( ! isset($this->credentials['oauth']))
			{
				$this->credentials['oauth'] = array();
			}
			$this->credentials['oauth']['expires_at'] = time() + $expires_in - 60;
			$this->credentials['oauth']['refresh_token'] = $refresh_token;
			$this->credentials['oauth']['access_token'] = $access_token;
		}
		else
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
		}
	}

	public function get_properties($force_fetch = FALSE)
	{
		if ($force_fetch)
		{
			$this->provide_oauth_access();
			$access_token = $this->get_credentials('oauth.access_token', '');

			//https://developers.hubspot.com/docs/methods/contacts/v2/get_contacts_properties
			$r = Integration_Request::factory()
				->method('GET')
				->header('Accept-Type', 'application/json')
				->header('Content-Type', 'application/json')
				->header('Authorization', 'Bearer '.$access_token)
				->url($this->get_endpoint().'/properties/v1/contacts/properties')
				->log_to($this->requests_log)
				->execute();

			if ( ! $r->is_successful())
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}

			$this->meta['properties'] = array();
			foreach ($r->data as $property)
			{
				$property_id = Arr::get($property, 'name');
				$property_group = Arr::get($property, 'groupName', 'contactinformation');

				if ( ! is_null($property_id) AND $property_group === 'contactinformation' AND ! in_array($property_id, $this->banned_properties))
				{
					$this->meta['properties'][$property_id] = trim(Arr::get($property, 'label', 'Unnamed property'));
				}
			}
		}

		return $this->get_meta('properties', array());
	}

	public function create_property($label)
	{
		$this->provide_oauth_access();
		$access_token = $this->get_credentials('oauth.access_token', '');

		$name = preg_replace('/[^a-z0-9_ ]+/i', '', $label);
		$name = Inflector::underscore($name);
		$name = strtolower($name);
		if (empty($name))
		{
			$name = 'field';
		}

		$properties = $this->get_meta('properties', array());
		$i = 0;
		while (array_key_exists($name.($i ?: ''), $properties))
		{
			if ($i > 100)
			{
				//WTF?! Something went wrong
				break;
			}
			$i++;
		}

		$name = $name.($i ?: '');

		//https://developers.hubspot.com/docs/methods/contacts/v2/create_contacts_property
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/properties/v1/contacts/properties')
			->data(array(
				"name" => $name,
				"label" => $label,
				"groupName" => "contactinformation",
				"type" => "string",
				"fieldType" => "text",
				"formField" => TRUE,
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 409)
			{
				// property already exist, do nothing
				return $name;
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}

		$name = Arr::get($r->data, 'name', NULL);
		if ( ! is_null($name))
		{
			$this->meta['properties'][$name] = $label;
		}

		return $name;
	}
}