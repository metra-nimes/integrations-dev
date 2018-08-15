<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * ConvertKit Integration
 * @link http://help.convertkit.com/article/33-api-documentation-v3
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_ConvertKit extends Integration_Driver implements Integration_Interface_ContactStorage {

	protected static $company_name = 'ConvertKit LLC';
	protected static $company_address = '113 Cherry St #92768, Seattle, WA, 98104-2205, USA';
	protected static $company_url = 'https://convertkit.com/';

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
				'type' => 'key',
				'description' => '<a href="/docs/integrations/convertkit/#step-2-get-your-convertkit-api-key-and-api-secret" target="_blank">Read where to obtain this code</a>',
				'rules' => array(
					array('not_empty'),
				),
			),
			'api_secret' => array(
				'title' => 'Account API Secret',
				'type' => 'key',
				'description' => 'Required to check email duplicates',
				'rules' => array(
					array('not_empty'),
				),
			),
			'submit' => array(
				'title' => 'Connect with ConvertKit',
				'type' => 'submit',
				'action' => 'connect',
			),
		);
	}

	public function get_endpoint()
	{
		return 'https://api.convertkit.com/v3';
	}


	public function fetch_meta()
	{
		$this->meta = [
			'forms' => [],
			'fields' => [],
			'sequences' => [],
			'tags' => []
		];

		$meta_keys = [
			'tags' => 'tags',
			'forms' => 'forms',
			'sequences' => 'courses'
		];

		// Getting available forms and tags
		// Their interfaces are pretty much alike, so we can combine them
		foreach ($meta_keys as $key => $value)
		{
			// http://help.convertkit.com/article/33-api-documentation-v3
			$r = Integration_Request::factory()
				->method('GET')
				->url($this->get_endpoint().'/'.$key)
				->data(array(
					'api_key' => $this->get_credentials('api_key', ''),
				))
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				$this->verify_response($r,'api_key');
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}

			foreach ($r->get($value, array()) as $item)
			{
				$id = Arr::get($item, 'id', 0);
				if ( ! is_null($id))
				{
					$this->meta[$key][$id] = Arr::get($item, 'name', '');
				}
			}
		}

		$tags = $this->get_meta('tags', array());
		uasort($tags, function ($a, $b) {
			return strcasecmp($a, $b);
		});

		if ( ! empty($tags))
		{
			$this->meta['tags'] = $tags;
		}

		$this->get_fields(TRUE);

		return $this;
	}

	public function describe_automations()
	{
		$forms = (array) $this->get_meta('forms', []);
		$tags = (array) $this->get_meta('tags', []);
		$sequences = (array) $this->get_meta('sequences', []);

		return [
			'add_form_subscriber' => [
				'title' => 'Add subscriber to a form',
				'params_fields' => [
					'form_id' => [
						'title' => 'Form Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $forms,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($forms)]],
						],
					],
				],
				'is_default' => TRUE,
			],
			'add_contact_tag' => [
				'title' => 'Add new tag to a subscriber',
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
				'title' => 'Remove tag from a subscriber',
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
			'add_sequence_subscriber' => [
				'title' => 'Add subscriber to a sequence',
				'params_fields' => [
					'sequence_id' => [
						'title' => 'Sequence Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $sequences,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($sequences)]],
						],
					],
				],
			],
		];
	}

	public function describe_params_fields()
	{
		$forms = $this->get_meta('forms', array());

		return array(
			'form' => array(
				'title' => 'ConvertKit Form',
				'type' => 'select',
				'description' => NULL,
				'options' => $forms,
				'classes' => 'i-refreshable',
				'rules' => array(
					array('in_array', array(':value', array_keys($forms))),
					array('not_empty'),
				),
			),
			'tags' => array(
				'title' => 'Tags to Mark with',
				'type' => 'select2',
				'description' => NULL,
				'options' => $this->get_meta('tags', array()),
				'multiple' => TRUE,
				'rules' => array(
					array(function ($tags) {
						if (empty($tags))
						{
							return;
						}

						foreach ($tags as $tag)
						{
							if ( ! Valid::alpha_numeric($tag))
							{
								throw new Integration_Exception(INT_E_WRONG_PARAMS, 'tags', 'Tags should be alphanumeric');
							}
						}
					}, array(':value')),
				),
			),
		);
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = array(
		'first_name' => 'First name',
		'name' => 'Name',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = array();
		$fields = $this->get_fields(TRUE);
		// Reserved tags to avoid
		$reserved_tags = array('email');
		$add_tag_if_not_exist = function ($name) use ($create_missing_fields, &$fields, $reserved_tags, &$int_data) {

			// default fields
			if (array_key_exists($name, $this->standard_merge_fields))
			{
				$tag = $this->standard_merge_fields[$name];
			}
			elseif (array_key_exists($name, $fields))
			{
				$tag = $name;
			}
			else
			{
				$tag = array_search($name, $fields);
				$name = Inflector::humanize($name);
				if (empty(preg_replace('~[^A-Z\d\_]+~i', '', $name)))
				{
					$name = $name. '[' . substr(base64_encode($name), 0, 8).']';
					$tag = array_search($name, $fields);
				}
			}


			if ($create_missing_fields AND
				(
					empty($tag)
					OR in_array($tag, $reserved_tags)
					OR ! is_null(Arr::path($int_data, 'fields.'.$tag, NULL))
				))
			{
				if (! empty($tag))
				{
					preg_match('~(.*?)(\d+)?$~', $tag, $matches);
					$tag_base = $tag = $matches[0];
					$tag_index = $matches[1] ?: 0;

					while (isset($fields[$tag]) OR in_array($tag, $reserved_tags))
					{
						$tag_index = isset($tag_index) ? ($tag_index + 1) : 0;
						if ($tag_index > 9)
						{
							// Too much tries ... just skipping the field
							return NULL;
						}
						$tag = $tag_base.($tag_index ?: '');
					}
					unset($tag_index);
					$name = $tag;
				}

				$tag = $this->create_field($name); // get new tag name from integration
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
			if (in_array($name_tag, array('name', 'first_name')))
			{
				// Convert Kit store first name as key
				Arr::set_path($int_data, $name_tag, $value);
				continue;
			}

			$name_tag = $add_tag_if_not_exist($name_tag);
			if ( ! empty($name_tag))
			{
				$path = 'fields.'.$name_tag;
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
		$name = Arr::path($subscriber_data, 'name', FALSE);
		$first_name = Arr::path($int_data, 'first_name', FALSE);
		$last_name = Arr::path($int_data, 'fields.last_name', FALSE);
		if ($name AND ! $first_name AND ! $last_name)
		{
			$int_data['name'] = trim($name);
			$name = explode(' ', $name, 2);
			Arr::set_path($int_data, 'first_name', $name[0]);
			if (isset($name[1]))
			{
				Arr::set_path($int_data, 'fields.last_name', $name[1]);
			}
		}
		elseif (is_null($name) AND ($first_name OR $last_name))
		{
			$int_data['name'] = trim($first_name.' '.$last_name);
		}

		return $int_data;
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => array(),
		);
		$int_fields = Arr::get($int_data, 'fields', array());
		if (array_key_exists('first_name', $int_data))
		{
			$int_fields['first_name'] = $int_data['first_name'];
		}
		$fields_array = $this->get_fields();
		foreach ($int_fields as $field_id => $value)
		{
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
			if (in_array($field_name, array('first_name', 'Last name', 'Last Name', 'Phone', 'Company', 'Site')))
			{
				// Standard type
				$subscriber_data[strtolower(Inflector::underscore($field_name))] = $value;
			}
			else
			{
				$subscriber_data['meta'][$field_name] = $value;
			}
		}

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}

		$id = Arr::get($int_data, 'id');
		if ($id)
		{
			Arr::set_path($subscriber_data, '$integration.id', $id);
		}

		return $subscriber_data;
	}

	public function get_subscriber($email)
	{
		// View a single subscriber
		// http://help.convertkit.com/article/33-api-documentation-v3
		$r = Integration_Request::factory()
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->method('GET')
			->url($this->get_endpoint().'/subscribers')
			->data(array(
				'api_secret' => $this->get_credentials('api_secret'),
				'email_address' => $email,
			))
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			$this->verify_response($r,'api_secret');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		$subscriber_data = ($r->get('total_subscribers') > 0) ? $this->translate_int_data_to_subscriber_data(current($r->get('subscribers'))) : array();
		return $subscriber_data;
	}

	public function add_form_subscriber($email, $params, $subscriber_data = array())
	{
		$form_id = Arr::get($params, 'form_id');
		if ( ! isset($form_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$data['api_key'] = $this->get_credentials('api_key');
		$data['email'] = $email;

		// Create subscriber
		// http://help.convertkit.com/article/33-api-documentation-v3
		$r = Integration_Request::factory()
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->method('POST')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/forms/'.$form_id.'/subscribe')
			->data($data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r,'api_key');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

	}

	public function update_person($email, $subscriber_data)
	{
		$this->create_person($email, $subscriber_data);
	}


	/**
	 * Verifying request
	 * @param $r Integration_Response
	 * @return bool
	 * @throws Integration_Exception
	 */
	protected function verify_response($r,$field='')
	{
		if ($r->code == 401 OR $r->code == 403)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $field,'Account API Key is not valid');
		}
		elseif ($r->code == 404 && Arr::get($r->data, 'error') === 'Form not found')
		{
			throw new Integration_Exception(INT_E_WRONG_DATA, 'form');
		}
		elseif ($r->code == 404)
		{
			throw new Integration_Exception(INT_E_WRONG_DATA);
		}
		elseif ($r->code == 500)
		{
			throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
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
		$fields = Arr::get($this->meta, 'fields', array());
		if (empty($fields) OR $force_fetch)
		{
			$data = array();
			$data['api_secret'] = $this->get_credentials('api_secret');
			// List fields
			// http://help.convertkit.com/article/33-api-documentation-v3
			$r = Integration_Request::factory()
				->method('GET')
				->curl(array(
					CURLOPT_CONNECTTIMEOUT_MS => 15000,
					CURLOPT_TIMEOUT_MS => 30000,
				))
				->url($this->get_endpoint().'/custom_fields')
				->data($data)
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				$this->verify_response($r,'api_secret');
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}

			//$this->meta['fields'] = array();
			//$this->meta['fields_data'] = array();
			foreach (Arr::get($r->data, 'custom_fields') as $item)
			{
				$field_id = Arr::get($item, 'key', NULL);
				if ( ! is_null($field_id) /*AND $field_id != 'email'*/)
				{
					$this->meta['fields'][$field_id] = Arr::get($item, 'label', 'Field without label');
					//$this->meta['fields_data'][$field_id] = $item;
				}
			}
		}

		$fields = $this->get_meta('fields', array());
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
		$data = array('label' => ucfirst($name), 'api_secret' => $this->get_credentials('api_secret'));
		// Create field
		// http://help.convertkit.com/article/33-api-documentation-v3
		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/custom_fields')
			->log_to($this->requests_log)
			->data($data)
			->execute();
		if ( ! $r->is_successful())
		{
			if (strstr($r->path('message', ''), 'Label has already been taken') !== FALSE)
			{
				$fields = $this->get_fields(TRUE);

				return array_search($name, $fields);
			}
			else
			{
				$this->verify_response($r);
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'name');
			}
		}

		//The key is an ASCII-only, lowercased, underscored representation of your label.
		$tag = strtolower(Arr::get($r->data, 'key', NULL));
		if ( ! empty($tag))
		{
			$this->meta['fields'][$tag] = Arr::get($r->data, 'label', 'Field without label');
			//$this->meta['fields_data'][$tag] = $r->data;
		}

		return $tag;
	}

	public function add_contact_tag($email, $params)
	{
		$tag_id = Arr::get($params, 'tag_id');

		if ( ! isset($tag_id) OR empty($tag_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if (empty($subscriber))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/tags/'.$tag_id.'/subscribe')
			->log_to($this->requests_log)
			->data(array(
				'api_key' => $this->get_credentials('api_key'),
				'email' => $email
			))
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r,'api_key');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function remove_contact_tag($email, $params)
	{
		$tag_id = Arr::get($params, 'tag_id');

		if ( ! isset($tag_id) OR empty($tag_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if (empty($subscriber))
		{
			return;
		}

		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/tags/'.$tag_id.'/unsubscribe')
			->log_to($this->requests_log)
			->data(array(
				'api_secret' => $this->get_credentials('api_secret'),
				'email' => $email
			))
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r,'api_secret');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function add_sequence_subscriber($email, $params, $subscriber_data = array())
	{
		$sequence_id = Arr::get($params, 'sequence_id');

		if ( ! isset($sequence_id) OR empty($sequence_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$data['api_key'] = $this->get_credentials('api_key');
		$data['email'] = $email;

		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/courses/'.$sequence_id.'/subscribe')
			->log_to($this->requests_log)
			->data($data)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r,'api_key');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}
}
