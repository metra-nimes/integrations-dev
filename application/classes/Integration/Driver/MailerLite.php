<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * MailerLite Integration
 * @link http://developers.mailerlite.com/docs/getting-started-with-mailerlite-api
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_MailerLite extends Integration_Driver implements Integration_Interface_ContactStorage {

	protected static $company_name = 'JSC MailerLite';
	protected static $company_address = 'Paupio g. 28, Vilnius 11341, Lithuania';
	protected static $company_url = 'https://www.mailerlite.com/';

	public function describe_credentials_fields($refresh = FALSE)
	{
		return [
			'name' => [
				'title' => 'Account Name',
				'type' => 'text',
				'description' => 'It\'s an internal value, which can be helpful to identify a specific account in future.',
				'rules' => [
					['not_empty'],
				],
			],
			'api_key' => [
				'title' => 'Account API Key',
				'description' => '<a href="/docs/integrations/mailerlite/#step-2-get-your-mailerlite-api-key" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => [
					['regex', [':value', '/^[a-z0-9]{6}\S+$/i']],
					['not_empty'],
				],
			],
			'submit' => [
				'title' => 'Connect with MailerLite',
				'action' => 'connect',
				'type' => 'submit',
			],
		];
	}

	public function get_endpoint()
	{
		return 'https://api.mailerlite.com/api/v2';
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = [
		'first_name' => 'name',
		'ip' => 'signup_ip',
		'created' => 'signup_timestamp',
	];

	public function fetch_meta()
	{
		$this->meta = [
			'groups' => [],
			'fields' => [],
		];

		// Get list of groups
		// @link http://developers.mailerlite.com/reference#groups
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/groups')
			->header('X-MailerLite-ApiKey', $this->get_credentials('api_key', ''))
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
		foreach ($r->data as $group)
		{
			$group_id = Arr::get($group, 'id', NULL);
			if ( ! is_null($group_id))
			{
				$this->meta['groups'][$group_id] = Arr::get($group, 'name', 'Group without name');
			}
		}

		$this->get_fields(TRUE);

		return $this;
	}

	public function describe_automations()
	{
		$groups = (array) $this->get_meta('groups', []);
		return [
			'add_group_subscriber' => [
				'title' => 'Add subscriber to group',
				'params_fields' => [
					'group_id' => [
						'title' => 'Group Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $groups,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($groups)]],
						],
					],
				],
			],
			'remove_group_subscriber' => [
				'title' => 'Remove subscriber from group',
				'params_fields' => [
					'group_id' => [
						'title' => 'Group Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $groups,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($groups)]],
						],
					],
				],
			],
		];
	}

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = [];
		$fields = $this->get_fields(TRUE);
		$cache_refreshed = FALSE;
		// Reserved tags to avoid
		$reserved_tags = ['email'];
		$add_tag_if_not_exist = function ($name) use ($create_missing_fields, &$fields, &$cache_refreshed, $reserved_tags, &$int_data) {

			// default fields
			if (array_key_exists($name, $this->standard_merge_fields))
			{
				$name = $this->standard_merge_fields[$name];
			}
			else
			{
				$name = ucfirst(Inflector::humanize($name));
			}

			// Check again
			$tag = array_search(ucfirst($name), $fields);

			if ($create_missing_fields AND
				(
					empty($tag)
					OR in_array($tag, $reserved_tags)
					OR ! is_null(Arr::path($int_data, 'fields.'.$tag, NULL))
				))
			{
				$tag = $this->create_field($name); // get new tag name from integration
			}

			return mb_strtolower($tag);
		};

		$meta = Arr::get($subscriber_data, 'meta', []);
		if (array_key_exists('meta', $subscriber_data))
		{
			unset($subscriber_data['meta']);
		}

		foreach ($subscriber_data as $name_tag => $value)
		{
			$name_tag = $add_tag_if_not_exist($name_tag);
			if ( ! empty($name_tag))
			{
				$path = $name_tag === 'name' ? 'name' : 'fields.'.$name_tag;
				Arr::set_path($int_data, $path, $value);
			}
		}

		foreach ($meta as $name_tag => $value)
		{
			$name_tag = $add_tag_if_not_exist($name_tag);
			if ( ! empty($name_tag))
			{
				Arr::set_path($int_data, 'fields.'.$name_tag, $value);
			}
		}

		// Trying to use standard FNAME / LNAME when name is defined
		if ($name = Arr::path($int_data, 'name', FALSE) AND ! Arr::path($int_data, 'fields.first_name', FALSE) AND ! Arr::path($int_data, 'fields.last_name', FALSE))
		{
			$name = explode(' ', $name, 2);
			Arr::set_path($int_data, 'name', $name[0]);
			if (isset($name[1]))
			{
				Arr::set_path($int_data, 'fields.last_name', $name[1]);
			}
		}

		return $int_data;
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = [
			'meta' => [],
		];
		$fields_array = $this->get_fields();
		foreach (Arr::get($int_data, 'fields', []) as $field)
		{
			$field_id = Arr::get($field, 'key', NULL);
			$value = Arr::get($field, 'value', '');
			if (empty($value) OR is_null($field_id) OR $field_id === 'email')
			{
				continue;
			}

			if ( ! isset($fields_array[$field_id]))
			{
				// Most probably cache is outdated, so fetching new fields once again
				$fields_array = $this->get_fields(TRUE);
			}

			$field_name = Arr::get($fields_array, $field_id, $field_id);

			if ($field_standard_name = array_search(lcfirst($field_name), $this->standard_merge_fields, TRUE))
			{
				// Standard type
				$subscriber_data[$field_standard_name] = $value;
			}
			else
			{
				$subscriber_data['meta'][$field_name] = $value;
			}
		}

		foreach ($this->standard_merge_fields as $key => $value)
		{
			if ( ! isset($subscriber_data['$key']) AND ! empty($int_data[$value]))
			{
				Arr::set_path($subscriber_data, $key, $int_data[$value]);
			}
		}

		$id = Arr::get($int_data, 'id');
		if ($id)
		{
			Arr::set_path($subscriber_data, '$integration.id', $id);
		}

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}

		return $subscriber_data;
	}

	public function get_subscriber($email)
	{

		$email = mb_strtolower($email);

		// Get single subscriber
		// @link https://developers.mailerlite.com/reference#single-subscriber
		$r = Integration_Request::factory()
			->method('GET')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/subscribers/'.urlencode($email))
			->header('X-MailerLite-ApiKey', $this->get_credentials('api_key', ''))
			->log_to($this->requests_log)
			->execute();

		if ($r->code === 404)
		{
			return NULL;
		}
		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		return $this->translate_int_data_to_subscriber_data($r->data);
	}

	/**
	 * Verifying request
	 * @param $r Integration_Response
	 * @return bool
	 * @throws Integration_Exception
	 */
	protected function verify_response($r)
	{
		if ($r->code == 401 OR $r->code == 403)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
		}
		elseif ($r->code == 400)
		{
			throw new Integration_Exception(INT_E_WRONG_DATA);
		}
		elseif ($r->code == 500)
		{
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
		}
		elseif ($r->code == 508)
		{
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
		}

		return TRUE;
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
		$fields = Arr::get($this->meta, 'fields', []);
		if (empty($fields) OR $force_fetch)
		{
			// Get list of fields
			// @link http://developers.mailerlite.com/reference#fields
			$r = Integration_Request::factory()
				->method('GET')
				->url($this->get_endpoint().'/fields')
				->header('X-MailerLite-ApiKey', $this->get_credentials('api_key', ''))
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				$this->verify_response($r);
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
			}

			$this->meta['fields'] = [];
			//$this->meta['fields_data'] = array();
			foreach ($r->data as $item)
			{
				$field_id = Arr::get($item, 'key', NULL);
				if ( ! is_null($field_id) AND $field_id != 'email')
				{
					$this->meta['fields'][$field_id] = Arr::get($item, 'title', 'Field without name');
					//$this->meta['fields_data'][$field_id] = $item;
				}
			}
		}

		$fields = $this->get_meta('fields', []);
		ksort($fields);

		return $fields;
	}

	/**
	 * Create new field for account
	 *
	 * @param string $name field name
	 * @return NULL|string field ID
	 *
	 * @throws Integration_Exception
	 */
	public function create_field($name)
	{
		$data = ['title' => ucfirst($name)];
		// Create field
		// @link http://developers.mailerlite.com/reference#create-field
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/fields')
			->header('X-MailerLite-ApiKey', $this->get_credentials('api_key', ''))
			->data($data)
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'name');
		}

		$tag = mb_strtolower(Arr::get($r->data, 'key', NULL));
		if ( ! empty($tag))
		{
			$this->meta['fields'][$tag] = Arr::get($r->data, 'title', 'Field without name');
			//$this->meta['fields_data'][$tag] = $r->data;
		}

		return $tag;
	}

	/**
	 * Add new single subscriber to specified group
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function add_group_subscriber($email, $params, $subscriber_data = [])
	{
		$selected_group = Arr::get($params, 'group_id');
		if ( ! isset($selected_group))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, true);

		// Add new single subscriber to specified group
		// @link https://developers.mailerlite.com/reference#add-single-subscriber
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/groups/'.$selected_group.'/subscribers')
			->header('X-MailerLite-ApiKey', $this->get_credentials('api_key', ''))
			->data(array_merge($int_data, [
				'email' => $email,
			]))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Remove subscriber from group
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_group_subscriber($email, $params)
	{
		$selected_group = Arr::get($params, 'group_id');
		if ( ! isset($selected_group))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		// Remove single subscriber from specified group
		// @link https://developers.mailerlite.com/reference#remove-subscriber
		$r = Integration_Request::factory()
			->method('DELETE')
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/groups/'.$selected_group.'/subscribers/'.$email)
			->header('X-MailerLite-ApiKey', $this->get_credentials('api_key', ''))
			->log_to($this->requests_log)
			->execute();

		if ($r->code == 404 AND strpos($r->path('error.message', NULL), 'Subscriber not found') !== FALSE)
		{
			return;
		}
		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}
}