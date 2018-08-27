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
				'type' => 'key',
				'description' => '<a href="/docs/integrations/convertkit/#step-2-get-your-convertkit-api-key-and-api-secret" target="_blank">Read where to obtain this code</a>',
				'rules' => [
					['not_empty'],
				],
			],
			'api_secret' => [
				'title' => 'Account API Secret',
				'type' => 'key',
				'description' => 'Required to check email duplicates',
				'rules' => [
					['not_empty'],
				],
			],
			'submit' => [
				'title' => 'Connect with ConvertKit',
				'type' => 'submit',
				'action' => 'connect',
			],
		];
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

		// Getting available forms and tags sequences
		// Their interfaces are pretty much alike, so we can combine them
		foreach ($meta_keys as $key => $value)
		{
			// http://developers.convertkit.com
			$r = Integration_Request::factory()
				->method('GET')
				->url($this->get_endpoint().'/'.$key)
				->data([
					'api_key' => $this->get_credentials('api_key', ''),
				])
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				$this->verify_response($r, 'api_key');
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}

			foreach ($r->get($value, []) as $item)
			{
				$id = Arr::get($item, 'id', 0);
				if ( ! is_null($id))
				{
					$this->meta[$key][$id] = Arr::get($item, 'name', '');
				}
			}
		}

		$tags = $this->get_meta('tags', []);
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
		$forms = $this->get_meta('forms', []);

		return [
			'form' => [
				'title' => 'ConvertKit Form',
				'type' => 'select',
				'description' => NULL,
				'options' => $forms,
				'classes' => 'i-refreshable',
				'rules' => [
					['in_array', [':value', array_keys($forms)]],
					['not_empty'],
				],
			],
			'tags' => [
				'title' => 'Tags to Mark with',
				'type' => 'select2',
				'description' => NULL,
				'options' => $this->get_meta('tags', []),
				'multiple' => TRUE,
				'rules' => [
					[function ($tags) {
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
					}, [':value']],
				],
			],
		];
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = [
		'first_name' => 'First name',
		'name' => 'Name',
	];

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = [];
		$fields = $this->get_fields(TRUE);
		// Reserved tags to avoid
		$reserved_tags = ['email'];
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
					$name = $name.'['.substr(base64_encode($name), 0, 8).']';
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
				if ( ! empty($tag))
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

		$meta = Arr::get($subscriber_data, 'meta', []);
		if (array_key_exists('meta', $subscriber_data))
		{
			unset($subscriber_data['meta']);
		}

		foreach ($subscriber_data as $name_tag => $value)
		{
			if (in_array($name_tag, ['name', 'first_name']))
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
		$subscriber_data = [
			'meta' => [],
		];
		$int_fields = Arr::get($int_data, 'fields', []);
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
			if (in_array($field_name, ['first_name', 'Last name', 'Last Name', 'Phone', 'Company', 'Site']))
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
		// View subscriber by email
		// http://developers.convertkit.com/#list-subscribers
		$r = Integration_Request::factory()
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			])
			->method('GET')
			->url($this->get_endpoint().'/subscribers')
			->data([
				'api_secret' => $this->get_credentials('api_secret'),
				'email_address' => $email,
			])
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			$this->verify_response($r, 'api_secret');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		$subscriber_data = ($r->get('total_subscribers') > 0) ? $this->translate_int_data_to_subscriber_data(current($r->get('subscribers'))) : [];
		return $subscriber_data;
	}

	public function add_form_subscriber($email, $params, $subscriber_data = [])
	{
		$form_id = Arr::get($params, 'form_id');
		if ( ! isset($form_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$data['api_key'] = $this->get_credentials('api_key');
		$data['email'] = $email;

		// Add subscriber to a form
		// http://developers.convertkit.com/#add-subscriber-to-a-form
		$r = Integration_Request::factory()
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			])
			->method('POST')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/forms/'.$form_id.'/subscribe')
			->data($data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r, 'api_key');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

	}

	/**
	 * Verifying request
	 * @param $r Integration_Response
	 * @return bool
	 * @throws Integration_Exception
	 */
	protected function verify_response($r, $field = '')
	{
		if ($r->code == 401 OR $r->code == 403)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $field, 'Account API Key is not valid');
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
		$fields = Arr::get($this->meta, 'fields', []);
		if (empty($fields) OR $force_fetch)
		{
			$data = [];
			$data['api_secret'] = $this->get_credentials('api_secret');
			// List fields
			// http://developers.convertkit.com/#list-fields
			$r = Integration_Request::factory()
				->method('GET')
				->curl([
					CURLOPT_CONNECTTIMEOUT_MS => 15000,
					CURLOPT_TIMEOUT_MS => 30000,
				])
				->url($this->get_endpoint().'/custom_fields')
				->data($data)
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				$this->verify_response($r, 'api_secret');
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
		$data = ['label' => ucfirst($name), 'api_secret' => $this->get_credentials('api_secret')];
		// Create field
		// http://developers.convertkit.com/#create-field
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			])
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


		// Tag a subscriber
		// @link http://developers.convertkit.com/#tag-a-subscriber
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			])
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/tags/'.$tag_id.'/subscribe')
			->log_to($this->requests_log)
			->data([
				'api_key' => $this->get_credentials('api_key'),
				'email' => $email
			])
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r, 'api_key');
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

		// Remove tag from a subscriber by email
		// @link http://developers.convertkit.com/#remove-tag-from-a-subscriber-by-email
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			])
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/tags/'.$tag_id.'/unsubscribe')
			->log_to($this->requests_log)
			->data([
				'api_secret' => $this->get_credentials('api_secret'),
				'email' => $email
			])
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r, 'api_secret');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function add_sequence_subscriber($email, $params, $subscriber_data = [])
	{
		$sequence_id = Arr::get($params, 'sequence_id');

		if ( ! isset($sequence_id) OR empty($sequence_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$data['api_key'] = $this->get_credentials('api_key');
		$data['email'] = $email;

		// Add subscriber to a sequence
		// @link http://developers.convertkit.com/#add-subscriber-to-a-sequence
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			])
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url($this->get_endpoint().'/courses/'.$sequence_id.'/subscribe')
			->log_to($this->requests_log)
			->data($data)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r, 'api_key');
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}
}
