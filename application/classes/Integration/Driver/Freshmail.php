<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Freshmail Integration
 * @link https://freshmail.com/developer-api/description-of-api/
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_Freshmail extends Integration_Driver implements Integration_Interface_BackendESP {

	use Integration_Trait_BackendESP;

	protected static $company_name = 'FRESHMAIL LTD';
	protected static $company_address = '88 Wood Street, EC2V 7RS London, UNITED KINGDOM';
	protected static $company_url = 'http://freshmail.com/';

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
			'api_key' => array(
				'title' => 'Account API Key',
				'description' => '<a href="/docs/integrations/freshmail/#step-2-get-your-freshmail-api-key-and-api-secret" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => array(
					array('regex', array(':value', '/[a-z0-9]{6}\S+/i')),
					array('not_empty'),
				),
			),
			'api_secret' => array(
				'title' => 'Account API Secret',
				'description' => '<a href="/docs/integrations/freshmail/#step-2-get-your-freshmail-api-key-and-api-secret" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => array(
					array('regex', array(':value', '/[a-z0-9]{6}\S+/i')),
					array('not_empty'),
				),
			),
			'submit' => array(
				'title' => 'Connect with Freshmail',
				'action' => 'connect',
				'type' => 'submit',
			),
		);
	}

	/*public function validate_credentials(array $credentials)
	{
		if (empty($api_key))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key cannot be empty');
		}
		if ( empty($api_secret))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_secret', 'Account API Secret is not valid');
		}
	}*/

	public function get_endpoint()
	{
		return 'https://api.freshmail.com';
	}

	public function fetch_meta()
	{
		$this->meta = array(
			'lists' => array(),
			'fields' => array(),
		);

		$api_key = $this->get_credentials('api_key', '');
		$api_secret = $this->get_credentials('api_secret', '');
		$path = '/rest/subscribers_list/lists';

		// Get account meta
		// https://freshmail.com/developer-api/subscribers-subscriber-management/
		$r = Integration_Request::factory()
			->method('GET')
			->header('Content-Type', 'application/json')
			->header('Accept-Type', 'application/json')
			->header('X-Rest-ApiKey', $api_key)
			->header('X-Rest-ApiSign', sha1($api_key.$path.$api_secret))
			->url($this->get_endpoint().$path)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
		}

		foreach ($r->get('lists') as $list)
		{
			$list_hash = Arr::get($list, 'subscriberListHash');
			$list_name = Arr::get($list, 'name');
			$this->meta['lists'][$list_hash] = $list_name;
		}

		return $this;
	}

	public function describe_params_fields()
	{
		$lists = (array) $this->get_meta('lists', array());

		return array(
			'warning' => array(
				'type' => 'alert',
				'text' => 'The maximum length of custom or hidden field\'s name should not exceed 80 characters.<br>The custom field\'s name can only contain letters, numbers, dashes, and underscores.',
				'closable' => TRUE,
				//'classes' => '',
			),
			'list' => array(
				'title' => 'Subscribers List',
				'description' => NULL,
				'type' => 'select',
				'options' => $lists,
				'classes' => 'i-refreshable',
				'rules' => array(
					array('in_array', array(':value', array_keys($lists))),
				),
			),
		);
	}

	public static function describe_data_rules()
	{
		return array(
			'text' => array(
				array('regex', array(':field', '~^[a-zа-я0-9 _-]+$~i'), 'The custom field\'s name can only contain letters, numbers, dashes, and underscores.'),
				array('max_length', array(':field', 80), 'The maximum length of custom field\'s name should not exceed 80 characters.'),
			),
			'hidden' => array(
				array('regex', array(':field', '~^[a-zа-я0-9 _-]+$~i'), 'The hidden field\'s name can only contain letters, numbers, dashes, and underscores.'),
				array('max_length', array(':field', 80), 'The maximum length of hidden field\'s name should not exceed 80 characters.'),
			),
		);
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = array(
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'name' => 'name',
		'site' => 'site',
		'company' => 'company',
		'phone' => 'phone',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = array();
		$fields = $this->get_fields(TRUE);
		// Reserved tags to avoid
		$reserved_tags = array('email');
		$add_tag_if_not_exist = function ($name) use ($create_missing_fields, &$fields, &$cache_refreshed, $reserved_tags, &$int_data) {

			if ( ! array_key_exists($name, $this->standard_merge_fields))
			{
				// fields from integration
				$name = $this->slug($name);
				if (empty($name))
				{
					$name = 'field1';
				}
			}

			$name = ucfirst(Inflector::humanize($name));
			$tag = array_search(strtolower($name), array_map('strtolower', $fields));

			if (empty($tag) AND $create_missing_fields)
			{
				$tag = Inflector::underscore(strtolower($name));
				$tag = preg_replace('/[^a-z0-9_]+/i', '', $tag);
				$tag = preg_replace('/^[^a-z]+/i', '', $tag); //remove digits from beginning

				preg_match('~(.*?)(\d+)?$~', $tag, $matches);
				$tag_base = strtolower($matches[1]);
				$tag_index = $base_tag_index = isset($matches[2]) ? intval($matches[2]) : 0;

				do{
					$tag = ($tag_base ?: 'field').($tag_index ?: '');

					//increase tag index
					$tag_index++;
					if ($tag_index > $base_tag_index + 9)
					{
						// Too much tries ... just skipping the field
						return NULL;
					}
				} while (
					array_key_exists($tag, $fields)
					OR in_array($tag, $reserved_tags)
					OR ! is_null(Arr::path($int_data, 'custom_fields.'.$tag, NULL))
				);

				unset($tag_index);

				if ($tag = $this->create_field($tag, $name)) // get new tag index from integration (can change)
				{
					$fields[$tag] = $name;
				}
			}

			return strtolower($tag);
		};

		$meta = Arr::get($subscriber_data, 'meta', array());
		if (array_key_exists('meta', $subscriber_data))
		{
			unset($subscriber_data['meta']);
		}

		foreach ($subscriber_data as $name_tag => $value)
		{
			if ($name_tag === 'name')
			{
				continue;
			}

			$name_tag = $add_tag_if_not_exist($name_tag);
			if ( ! empty($name_tag))
			{
				Arr::set_path($int_data, 'custom_fields.'.$name_tag, $value);
			}
		}

		foreach ($meta as $name_tag => $value)
		{
			$name_tag = $add_tag_if_not_exist($name_tag);
			if ( ! empty($name_tag))
			{
				Arr::set_path($int_data, 'custom_fields.'.$name_tag, $value);
			}
		}

		// Trying to use standard FNAME / LNAME when name is defined
		$name = Arr::path($subscriber_data, 'name', FALSE);
		$first_name = Arr::path($int_data, 'custom_fields.first_name', FALSE);
		$last_name = Arr::path($int_data, 'custom_fields.last_name', FALSE);
		if ($name AND ! $first_name AND ! $last_name)
		{
			$name = explode(' ', $name, 2);
			Arr::set_path($int_data, 'custom_fields.first_name', trim($name[0]));
			if (isset($name[1]))
			{
				Arr::set_path($int_data, 'custom_fields.last_name', trim($name[1]));
			}
		}

		return $int_data;
	}

	function slug($string)
	{
		$string = htmlentities($string, ENT_QUOTES, 'UTF-8');

		$pattern = '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i';
		$string = preg_replace($pattern, '$1', $string);

		$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

		$pattern = '~[^0-9a-zа-я ]+~ui';
		$string = preg_replace($pattern, ' ', $string);

		return trim($string, '_ ');
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array();
		$fields = $this->get_fields(TRUE);
		foreach (Arr::path($int_data, 'custom_fields', array()) as $custom_field)
		{
			$custom_field_key = Arr::get($custom_field, 'field', NULL);
			$custom_field_value = Arr::get($custom_field, 'value', NULL);
			if ( ! is_null($custom_field_key) AND ! empty($custom_field_value))
			{
				if (isset($this->standard_merge_fields[$custom_field_key]))
				{
					$path = $this->standard_merge_fields[$custom_field_key];
				}
				elseif ($path = array_search($custom_field_key, $fields) AND isset($this->standard_merge_fields[$path]))
				{
					$path = $this->standard_merge_fields[$path];
				}
				else
				{
					$path = 'meta.'.Arr::get($fields, $custom_field_key, $custom_field_key);
				}

				if ( ! is_null($path))
				{
					Arr::set_path($subscriber_data, $path, $custom_field_value);
				}
			}
		}

		return $subscriber_data;
	}

	public function get_person($email)
	{
		$api_key = $this->get_credentials('api_key', NULL);
		$api_secret = $this->get_credentials('api_secret', NULL);
		$list_id = $this->get_params('list', NULL);

		if (empty($api_key) OR empty($api_secret))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key');
		}

		if (empty($list_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list');
		}

		$email = strtolower($email);

		// Check user subscription
		// https://freshmail.com/developer-api/subscribers-managing-subscribers/
		$path = '/rest/subscriber/get/'.$list_id.'/'.$email;
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().$path)
			->header('Content-Type', 'application/json')
			->header('Accept-Type', 'application/json')
			->header('X-Rest-ApiKey', $api_key)
			->header('X-Rest-ApiSign', sha1($api_key.$path.$api_secret))
			->log_to($this->requests_log)
			->execute();

		$this->verify_response($r);
		if (($r->code === 555 OR $r->code === 422) AND ($r->path('errors.0.code') == 1311 OR $r->path('errors.0.code') == 1312))
		{
			return NULL;
		}

		return $this->translate_int_data_to_subscriber_data($r->get('data'));
	}

	public function create_person($email, $subscriber_data)
	{
		$api_key = $this->get_credentials('api_key', NULL);
		$api_secret = $this->get_credentials('api_secret', NULL);
		$list_id = $this->get_params('list', NULL);

		if (empty($api_key) OR empty($api_secret))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key');
		}

		if (empty($list_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list');
		}

		$int_data = array();
		$int_data['email'] = $email;
		$int_data['list'] = $list_id;
		$int_data['status'] = 1;
		$int_data['confirm'] = 1;

		// Add new subscriber
		// https://freshmail.com/developer-api/subscribers-managing-subscribers/
		$path = '/rest/subscriber/add';
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().$path)
			->header('Content-Type', 'application/json')
			->header('Accept-Type', 'application/json')
			->header('X-Rest-ApiKey', $api_key)
			->header('X-Rest-ApiSign', sha1($api_key.$path.json_encode($int_data).$api_secret))
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ($r->code == 422 AND $r->path('errors.0.code') === 1304)
		{
			// Do update request, because custom_data doesn't set.
			$this->update_person($email, $subscriber_data);
			throw new Integration_Exception(INT_E_EMAIL_DUPLICATE);
		}

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		// Do update request, because custom_data doesn't set.
		$this->update_person($email, $subscriber_data);

	}

	public function update_person($email, $subscriber_data)
	{
		$api_key = $this->get_credentials('api_key', NULL);
		$api_secret = $this->get_credentials('api_secret', NULL);
		$list_id = $this->get_params('list', NULL);

		if (empty($api_key) OR empty($api_secret))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key');
		}

		if (empty($list_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list');
		}

		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$int_data['email'] = $email;
		$int_data['list'] = $list_id;

		// Update subscriber
		// https://freshmail.com/developer-api/subscribers-managing-subscribers/
		$path = '/rest/subscriber/edit';
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().$path)
			->header('Content-Type', 'application/json')
			->header('Accept-Type', 'application/json')
			->header('X-Rest-ApiKey', $api_key)
			->header('X-Rest-ApiSign', sha1($api_key.$path.json_encode($int_data).$api_secret))
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		$this->verify_response($r);
		if ( ! $r->is_successful())
		{
			switch (Arr::path($r->data, 'errors.0.code', -1))
			{
				case 1331:
					throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Subscriber doesn\'t exist');
					break;
				case 1302:
					throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list', 'Subscriber list or hash list doesn’t exist');
					break;
				default:
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}

	/**
	 * Verifying request
	 * @param $r Integration_Response
	 * @return bool
	 * @throws Integration_Exception
	 */
	protected static function verify_response($r)
	{
		if ($r->path('errors.0.message') === 'You have not signed the GDPR agreement. You cannot use this option at this time.')
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'You have not signed the GDPR agreement. You cannot use this option at this time.');
		}
		switch ($r->code)
		{
			case 401:
			case 403:
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key or Account API Secret is not valid');
				break;
			case 404:
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
				break;
			case 409:
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
				break;
			case 500:
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
				break;
		}

		return TRUE;
	}

	public function get_fields($force_fetch = FALSE)
	{
		$current_list = $this->get_params('list', '');
		$fields = $this->get_meta('fields', array());
		if ( ! isset($fields[$current_list]) OR $force_fetch)
		{
			$api_key = $this->get_credentials('api_key', '');
			$api_secret = $this->get_credentials('api_secret', '');
			$get_fields_path = '/rest/subscribers_list/getFields';
			$data = array(
				'hash' => $current_list,
			);
			// Get account meta
			// https://freshmail.com/developer-api/subscribers-subscriber-management/
			$r = Integration_Request::factory()
				->method('POST')
				->header('Content-Type', 'application/json')
				->header('Accept-Type', 'application/json')
				->header('X-Rest-ApiKey', $api_key)
				->header('X-Rest-ApiSign', sha1($api_key.$get_fields_path.json_encode($data).$api_secret))
				->url($this->get_endpoint().$get_fields_path)
				->data($data)
				->log_to($this->requests_log)
				->execute();

			$this->verify_response($r);
			if ( ! $r->is_successful())
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}


			$this->meta['fields'][$current_list] = array();
			foreach ($r->get('fields') as $field)
			{
				$tag = Arr::get($field, 'tag', NULL);
				if ( ! is_null($tag))
				{
					$this->meta['fields'][$current_list][$tag] = Arr::get($field, 'name', 'Tag without name');
				}
			}
		}

		return $this->get_meta('fields.'.$current_list, array());
	}

	public function create_field($tag, $name)
	{
		$api_key = $this->get_credentials('api_key', NULL);
		$api_secret = $this->get_credentials('api_secret', NULL);
		$list_id = $this->get_params('list', NULL);

		if (empty($api_key) OR empty($api_secret))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key');
		}

		if (empty($list_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list');
		}

		$data['hash'] = $list_id;
		$data['name'] = ucfirst(Inflector::humanize($name));
		$data['tag'] = strtolower($tag);

		// Add new field
		// https://freshmail.com/developer-api/subscribers-managing-subscribers/
		$path = '/rest/subscribers_list/addField';
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().$path)
			->header('Content-Type', 'application/json')
			->header('Accept-Type', 'application/json')
			->header('X-Rest-ApiKey', $api_key)
			->header('X-Rest-ApiSign', sha1($api_key.$path.json_encode($data).$api_secret))
			->data($data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			switch (Arr::path($r->data, 'errors.0.code', -1))
			{
				case 1622:
					throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list', 'Incorrect or empty hash of subscriber lis');
					break;
				case 1623:
					throw new Integration_Exception(INT_E_WRONG_DATA, 'name', 'Incorrect or empty field name');
					break;
				case 1624:
					//throw new Integration_Exception(INT_E_PARAMS_NOT_VERIFIED, 'tag', 'Incorrect tag name');

					return NULL;
					break;
				case 1625:
					throw new Integration_Exception(INT_E_WRONG_PARAMS, 'type', 'Incorrect field type');
					break;
				case 1626:
					//throw new Integration_Exception(INT_E_PARAMS_NOT_VERIFIED, 'tag', 'Custom field with this tag already exists');
					$this->meta['fields'][$list_id][$tag] = $name;

					return $tag;
					break;
				default:
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}

		$tag = $r->path('field.personalization_tag', NULL);
		if ( ! is_null($tag))
		{
			$this->meta['fields'][$list_id][$tag] = $r->path('field.field_name', 'Tag without name');
		}

		return $tag;
	}
}