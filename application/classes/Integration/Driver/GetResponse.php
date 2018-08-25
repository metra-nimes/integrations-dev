<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * GetResponse Integration
 * @link https://apidocs.getresponse.com/v3
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_GetResponse extends Integration_Driver implements Integration_Interface_ContactStorage {

	protected static $company_name = 'GetResponse Sp. z o.o. (Polish limited liability company)';
	protected static $company_address = 'Arkońska 6/A3, 80-387 Gdańsk, Poland';
	protected static $company_url = 'https://www.getresponse.com/';

	protected static $email_cache = array(
		// 'email' => id
	);
	/**
	 * Get endpoint URL for API calls
	 *
	 * @var string API Endpoint
	 * @return string
	 * @todo: getresponse360 api
	 */
	public function get_endpoint()
	{
		return 'https://api.getresponse.com/v3';
	}

	public function validate_params(array $params)
	{
		$campaign = Arr::get($params, 'campaign', '');
		$available_campaigns = Arr::get($this->meta, 'campaigns', array());
		if ( ! is_array($available_campaigns))
		{
			$available_campaigns = array();
		}
		if ( ! isset($available_campaigns[$campaign]))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'campaign', 'Campaign not found');
		}
	}

	public function validate_credentials(array $credentials)
	{
		$name = Arr::get($credentials, 'name', '');
		if (empty($name))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'name', 'Account name cannot be empty');
		}
	}

	/**
	 * Describes COF fieldset config to render a credentials form, so a user could connect integration account
	 *
	 * @return array COF fieldset config
	 */
	public function describe_credentials_fields($refresh = FALSE)
	{
		return [
			'name' => [
				'title' => 'Account Name',
				'type' => 'text',
				'description' => 'It\'s an internal value, which can be helpful to identify a specific account in future.',
			],
			'api_key' => [
				'title' => 'Account API Key',
				'description' => '<a href="/docs/integrations/mailchimp/#step-2-get-your-mailchimp-api-key" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => [
					['not_empty'],
					/*array('regex', array(':value', '~\-[a-z0-9]{2,8}$~')),*/
				],
			],
			'submit' => [
				'title' => 'Connect with GetResponse',
				'action' => 'connect',
				'type' => 'submit',
			],
		];
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
			'campaigns' => array(),
			'tags' => array(),
			'fields' => array(),
			'fields_data' => array()
		);

		$meta_actions = [
			'campaigns' => 'campaigns',
			'tags' => 'tags',
		];

		$api_key = $this->get_credentials('api_key', '');

		foreach ($meta_actions as $meta_key => $action)
		{
			$r = Integration_Request::factory()
				->method('GET')
				->url($this->get_endpoint().'/'.$action)
				->header('X-Auth-Token', 'api-key '.$api_key)
				->log_to($this->requests_log)
				->execute();

			if ( ! $r->is_successful())
			{
				if ($r->code == 401 OR $r->code == 403)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
				}
				elseif ($r->code == 500)
				{
					throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
				}
				else
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
				}
			}

			$id = ($action == 'tags') ? 'tagId' : 'campaignId';
			foreach ($r->data as $item)
			{
				$this->meta[$meta_key][Arr::get($item, $id, '')] = Arr::get($item, 'name', '');
			}
		}

		$this->get_fields(TRUE);

		return $this;
	}

	public function describe_automations()
	{
		$campaigns = (array) $this->get_meta('campaigns', []);
		$tags = (array) $this->get_meta('tags', []);

		return [
			'add_campaign_contact' => [
				'title' => 'Add contact to campaign',
				'params_fields' => [
					'campaign_id' => [
						'title' => 'Campaign Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $campaigns,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($campaigns)]],
						],
					],
				],
				'is_default' => TRUE,
			],
			'remove_campaign_contact' => [
				'title' => 'Remove contact from campaign',
				'params_fields' => [
					'campaign_id' => [
						'title' => 'Campaign Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $campaigns,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($campaigns)]],
						],
					],
				],
			],
			'add_contact_tag' => [
				'title' => 'Add new tag to a contact',
				'params_fields' => [
					'tag_id' => [
						'title' => 'Tag Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $tags,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($tags)]],
						],
					],
				],
			],
			'remove_contact_tag' => [
				'title' => 'Remove tag from a contact',
				'params_fields' => [
					'tag_id' => [
						'title' => 'Tag Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $tags,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($tags)]],
						],
					],
				],
			],
			'add_contact_note' => [
				'title' => 'Add note to a contact',
				'params_fields' => [
					'text' => [
						'title' => 'Note Text',
						'description' => NULL,
						'type' => 'text',
						'rules' => [
							['not_empty'],
						],
					],
				],
			],
		];
	}

	public static function describe_data_rules()
	{
		return array(
			'text' => array(
				array('max_length', array(':field', 32), 'The maximum length of custom field\'s name should not exceed 32 characters.'),
			),
			'hidden' => array(
				array('max_length', array(':field', 32), 'The maximum length of hidden field\'s name should not exceed 32 characters.'),
			),
		);
	}

	/**
	 * Translate person data from standard convertful to integration format
	 *
	 * @param array $subscriber_data Person data in standard convertful format
	 * @param bool $create_missing_fields
	 * @return array Integration-specified person data format
	 * @throws Integration_Exception
	 */
	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$person_meta = Arr::get($subscriber_data, 'meta', array());
		unset($subscriber_data['meta']);
		$cache_refreshed = FALSE;
		$int_data = array(
			'customFieldValues' => [],
		);
		$fields = $this->get_fields(TRUE);

		$name = NULL;
		$first_name = NULL;
		$last_name = NULL;

		$reserved_names = array('name', 'email', 'firstname', 'first_name', 'lastname', 'last_name', 'twitter', 'facebook', 'buzz', 'myspace', 'linkedin', 'digg', 'googleplus', 'pinterest', 'responder', 'campaign', 'change');

		$temp_subscriber_data = array_merge($subscriber_data,$person_meta);

		if (empty($temp_subscriber_data))
			return NULL;

		if (array_key_exists('name',$temp_subscriber_data) AND ! empty($temp_subscriber_data['name']))
		{
			$full_name_arr = explode(' ',$temp_subscriber_data['name'],2);
			$full_name_count = count($full_name_arr);

			if ($full_name_count > 1)
			{
				$temp_subscriber_data['first_name'] = (isset($temp_subscriber_data['first_name'])) ? $temp_subscriber_data['first_name'] : $full_name_arr[0];
				$temp_subscriber_data['last_name'] = (isset($temp_subscriber_data['last_name'])) ? $temp_subscriber_data['last_name'] : $full_name_arr[1];
			}
			else
			{
				$temp_subscriber_data['first_name'] = (isset($temp_subscriber_data['first_name'])) ? $temp_subscriber_data['first_name'] : $full_name_arr[0];
			}
		}

		foreach ($temp_subscriber_data as $field_name => $field_value)
		{
			if (array_key_exists($field_name, $this->standard_merge_fields))
			{
				$int_data[$this->standard_merge_fields[$field_name]] = trim($field_value);
			}
			else
			{
				if (in_array($field_name,$reserved_names))
				{
					preg_match("/(.*?)(\d+)?$/", $field_name, $matches);
					$field_name = $matches[0].(intval($matches[1]) + 1);
				}

				$field_key = array_search($field_name,$fields);

				if ( ! $field_key)
				{
					$field_key = $this->create_field($field_name);
				}

				$int_data['customFieldValues'][$field_key] = array(
					'customFieldId' => $field_key,
					'value' => $this->format_field_value($field_key, $field_value)
				);
			}
		}

		return $int_data;
	}

	/**
	 * Translate person data from integration to standard convertful format
	 *
	 * @param array $int_data Person data in integration format
	 * @return array Person data in standard convertful format
	 */
	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => array(),
		);
		$fields_array = $this->get_fields();

		foreach (Arr::get($int_data, 'customFieldValues', array()) as $custom_field_array)
		{
			$field_id = Arr::get($custom_field_array, 'customFieldId', NULL);
			$value = Arr::path($custom_field_array, 'value.0');
			if (empty($value) OR is_null($field_id))
			{
				continue;
			}

			if ( ! isset($fields_array[$field_id]))
			{
				// Most probably cache is outdated, so fetching new fields once again
				$fields_array = $this->get_fields(TRUE);
			}

			$f_name = Arr::get($fields_array, $field_id, $field_id);
			if ($f_type = array_search($f_name, $this->standard_merge_fields, TRUE))
			{
				// Standard type
				$subscriber_data[$f_type] = $value;
			}
			else
			{
				$subscriber_data['meta'][$f_name] = $value;
			}
		}

		foreach ($this->standard_merge_fields as $key => $field)
		{
			if ( !empty(Arr::get($int_data, $field, NULL)))
			{
				$subscriber_data[$key] = Arr::get($int_data, $field, NULL);
			}
		}

		$int_fields = [
			'id' => 'contactId',
			'campaign_id' => 'campaign.campaignId',
			'tags' => 'tags'
		];

		foreach ($int_fields as $int_key => $int_value)
		{
			$int_field = Arr::path($int_data, $int_value,NULL);
			if ($int_field )
			{
				if ($int_key == 'tags')
				{
					$int_field = array_map(function ($item) {
						return array('tagId' => $item['tagId']);
					},$int_field);
				}
				Arr::set_path($subscriber_data, '$integration.'.$int_key, $int_field);
			}
		}

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}


		return $subscriber_data;
	}


	/**
	 * Get person by email
	 * @param string $email
	 * @return array|NULL Person data or NULL, if person not found
	 * @throws Integration_Exception
	 */
	public function get_subscriber($email, $need_translate = TRUE)
	{
		$api_key = $this->get_credentials('api_key', '');

		$client_id = $this->get_clientId_by_email($email);

		if ($client_id)
		{
			// Get FULL contact information
			// https://apidocs.getresponse.com/v3/resources/contacts#contacts.get
			$r = Integration_Request::factory()
				->method('GET')
				->header('Accept-Type', 'application/json')
				->header('Content-Type', 'application/json')
				->header('X-Auth-Token', 'api-key '.$api_key)
				->url($this->get_endpoint().'/contacts/'.$client_id)
				->log_to($this->requests_log)
				->execute();

			if ( ! $r->is_successful())
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, NULL, $r->get('codeDescription'));
			}

			return $need_translate ? $this->translate_int_data_to_subscriber_data($r->data) : $r->data;
		}

		return NULL;
	}

	private function get_clientId_by_email($email)
	{
		if (isset(self::$email_cache[$email]))
		{
			return self::$email_cache[$email];
		}

		$api_key = Arr::path($this->credentials, 'api_key');
		// https://apidocs.getresponse.com/v3/resources/contacts
		// NOTE: Resources returned by this method do not include custom fields assigned to the contact.
		// To get the custom fields, please use the GET /contacts/{contactId} method.
		$r = Integration_Request::factory()
			->method('GET')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->url($this->get_endpoint().'/contacts')
			->data(array(
				'query' => array(
					'email' => $email,
				),
				'additionalFlags' => 'exactMatch',
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, NULL, $r->get('codeDescription'));
		}

		self::$email_cache[$email] = Arr::path($r->data, '0.contactId', 0);
		return self::$email_cache[$email];
	}

	/**
	 * Get available merge fields for the current list
	 * Fetching merge fields for the current list on demand, and caching it in meta for 24 hours
	 * @param bool $force_fetch Prevent using cached version
	 * @return array tag => label
	 * @throws Integration_Exception
	 */
	public function get_fields($force_fetch = FALSE)
	{
		$fields = Arr::get($this->meta, 'fields', array());
		if (empty($fields) OR $force_fetch)
		{
			$api_key = Arr::path($this->credentials, 'api_key');

			// Getting custom fields
			// https://apidocs.getresponse.com/v3/resources/customfields#customfields.get.all
			$r = Integration_Request::factory()
				->method('GET')
				->url($this->get_endpoint().'/custom-fields')
				->header('X-Auth-Token', 'api-key '.$api_key)
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				if ($r->code == 401 OR $r->code == 403)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
				}
				elseif ($r->code == 500)
				{
					throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR, 'api_key');
				}
				else
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
				}
			}

			$this->meta['fields'] = array();
			$this->meta['fields_data'] = array();
			foreach ($r->data as $item)
			{
				$field_id = Arr::get($item, 'customFieldId', NULL);
				if ( ! is_null($field_id))
				{
					$this->meta['fields'][$field_id] = Arr::get($item, 'name', '');
					$this->meta['fields_data'][$field_id] = $item;
				}
			}
		}

		return $this->get_meta('fields', array());
	}

	/**
	 * Create new field for account
	 *
	 * @param string $name field name
	 * @return NULL|string field ID
	 *
	 * TODO: now we can create only text fields, create different fields
	 * @throws Integration_Exception
	 */
	public function create_field($name)
	{
		$api_key = Arr::path($this->credentials, 'api_key');
		$name = Inflector::underscore($name);
		$name = mb_strtolower(preg_replace('/[^a-z0-9_]+/im', '', $name));
		$name = mb_strimwidth($name, 0, 32);

		//https://apidocs.getresponse.com/v3/resources/customfields#customfields.create
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/custom-fields')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->data(array(
				'name' => $name,
				'type' => 'text',
				'hidden' => 'true',
				'values' => array(),
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->get('message') === 'Custom name already exists')
			{
				$fields = $this->get_fields(TRUE);

				return array_search($name, $fields);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'name');
			}
		}

		$field_id = Arr::get($r->data, 'customFieldId', NULL);

		if ( ! is_null($field_id))
		{
			$this->meta['fields'][$field_id] = $name;
			$this->meta['fields_data'][$field_id] = $r->data;
		}

		return $field_id;
	}

	/**
	 * Format field like field in Get response
	 * @param $field_id
	 * @param $value
	 * @return mixed
	 *
	 * @url https://apidocs.getresponse.com/v3/resources/customfields#customfields.create
	 */
	protected function format_field_value($field_id, $value)
	{
		$field_settings = Arr::path($this->meta, 'fields_data.'.$field_id, array());

		switch (Arr::get($field_settings, 'format'))
		{
			case 'number':
				$value = floatval($value);
				break;
			case 'date':
				$value = date('Y-m-d', strtotime($value));
				break;
			case 'datetime':
				$value = date('Y-m-d H:i:s', strtotime($value));
				break;
			case 'single_select':
				break;
		}

		switch (Arr::get($field_settings, 'valueType'))
		{
			case 'phone':
				$value = ltrim(preg_replace('/[^0-9]+/i', '', $value), 0);
				switch (TRUE)
				{
					case strlen($value) >= 11:
						$value = '+'.$value;
						break;
					case strlen($value) === 10:
						$value = '+1'.$value;
						break;
					case strlen($value) === 0:
						$value = NULL;
						break;
					default:
						$value = '+18'.str_pad($value, 9, '0', STR_PAD_LEFT);
				}
				break;
			case 'string':
				$value = mb_strimwidth($value, 0, 255, '...');
				break;
		}

		return array($value);
	}

	/**
	 * @param $name
	 * @param bool $force_fetch
	 * @return mixed
	 */
	protected function get_field_id_by_field_name($name, $force_fetch = FALSE)
	{
		$fields = $this->get_fields($force_fetch);

		return array_search($name, $fields, TRUE);
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = array(
		'first_name' => 'name',
		'ip' => 'ipAddress',
	);

	/**
	 * Get campaign contacts
	 * @param $campaign_id
	 * @param $email
	 * @return mixed
	 * @throws Integration_Exception
	 */
	protected function get_campaign_contact($campaign_id, $email)
	{
		$api_key = Arr::path($this->credentials, 'api_key');

		// Allows to retrieve all contacts from given campaigns.
		// https://apidocs.getresponse.com/v3/resources/campaigns#campaigns.contacts.get
		$r = Integration_Request::factory()
			->method('GET')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_endpoint().'/campaigns/'.$campaign_id.'/contacts')
			->data(array(
				'query' => array(
					'email' => $email,
				),
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}

		return Arr::path($r->data, '0', NULL);
	}

	/**
	 * Add contact to campaign
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function add_campaign_contact($email, $params, $subscriber_data = array())
	{
		$current_campaign = Arr::get($params, 'campaign_id');
		if ( ! isset($current_campaign) OR empty($current_campaign))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber != NULL)
		{
			$subscriber_id = Arr::path($subscriber, '$integration.id');
			if ( ! isset($subscriber_id) OR empty($subscriber_id))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
		else
		{
			$subscriber_id = '';
		}

		$api_key = Arr::path($this->credentials, 'api_key');

		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);

		$int_data['email'] = $email;
		$int_data['campaign'] = array(
			'campaignId' => $current_campaign,
		);

		// Add/Update contact to campaign
		// https://apidocs.getresponse.com/v3/resources/contacts#contacts.create
		// https://apidocs.getresponse.com/v3/resources/contacts#contacts.update
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_endpoint().'/contacts/'.$subscriber_id)
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ($r->get('code') == 1000 OR $r->get('code') == 1002)
		{
			$message = $r->get('message');

			foreach ($r->get('context') as $mess)
			{
				$message .= ' '.$mess;
			}
			throw new Integration_Exception(INT_E_WRONG_PARAMS, $r->get('context.0.fieldName.0', 'attr_value'), $message);
		}

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}

	/**
	 * Remove contact from a campaign
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_campaign_contact($email, $params)
	{
		$current_campaign = Arr::get($params, 'campaign_id');
		if ( ! isset($current_campaign) OR empty($current_campaign))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_campaign_contact($current_campaign,$email);
		if ($subscriber === NULL)
		{
			return;
		}
		else
		{
			$subscriber_id = Arr::path($subscriber, 'contactId');

			if ( ! isset($subscriber_id) OR empty($subscriber_id))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}

		$api_key = Arr::path($this->credentials, 'api_key');

		// Remove contact to campaign
		// https://apidocs.getresponse.com/v3/resources/contacts#contacts.delete
		$r = Integration_Request::factory()
			->method('DELETE')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_endpoint().'/contacts/'.$subscriber_id)
			->data(array(
				'messageId' => $current_campaign
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}

	/**
	 * Add new tag to a contact
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function add_contact_tag($email, $params)
	{
		$current_tag = Arr::get($params, 'tag_id');
		if ( ! isset($current_tag) OR empty($current_tag))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			return;
		}
		else
		{
			$subscriber_id = Arr::path($subscriber, '$integration.id');

			if ( ! isset($subscriber_id) OR empty($subscriber_id))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}

		$api_key = Arr::path($this->credentials, 'api_key');

		$int_data['tags'] = array(
			'tagId' => $current_tag,
		);

		// Add new tag to a contact
		// https://apidocs.getresponse.com/v3/resources/contacts#contacts.upsert.tags
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_endpoint().'/contacts/'.$subscriber_id.'/tags')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}

	/**
	 * Remove tag from a contact
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_contact_tag($email, $params)
	{
		$current_tag = Arr::get($params, 'tag_id');
		if ( ! isset($current_tag) OR empty($current_tag))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);

		if ($subscriber === NULL)
		{
			return;
		}
		else
		{
			$subscriber_id = Arr::path($subscriber, '$integration.id');

			if ( ! isset($subscriber_id) OR empty($subscriber_id))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}

			$tags = Arr::path($subscriber, '$integration.tags',[]);
			$removed_tag_key = array_search($current_tag, array_column($tags, 'tagId'));

			if ($removed_tag_key === FALSE)
			{
				return;
			}
			else
			{
				unset($tags[$removed_tag_key]);
			}
		}

		$api_key = Arr::path($this->credentials, 'api_key');

		$int_data['tags'] = array_values($tags);

		// Remove tag from a contact
		// https://apidocs.getresponse.com/v3/resources/contacts#contacts.update
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_endpoint().'/contacts/'.$subscriber_id)
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}

	/**
	 * Add note to a contact
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function add_contact_note($email, $params)
	{
		$note = Arr::get($params, 'text');
		if ( ! isset($note) OR empty($note))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);

		if ($subscriber === NULL)
		{
			return;
		}
		else
		{
			$subscriber_id = Arr::path($subscriber, '$integration.id');

			if ( ! isset($subscriber_id) OR empty($subscriber_id))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}

		$api_key = Arr::path($this->credentials, 'api_key');

		$int_data['note'] = $note;

		// Add note to a contact
		// https://apidocs.getresponse.com/v3/resources/contacts#contacts.update
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('X-Auth-Token', 'api-key '.$api_key)
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_endpoint().'/contacts/'.$subscriber_id)
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}
}
