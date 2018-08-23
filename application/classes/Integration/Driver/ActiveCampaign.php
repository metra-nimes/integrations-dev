<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * ActiveCampaign Integration
 * @link http://www.activecampaign.com/api/overview.php
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_ActiveCampaign extends Integration_Driver implements Integration_Interface_ContactStorage {

	protected static $company_name = 'ActiveCampaign, LLC';
	protected static $company_address = '1 N Dearborn, 5th Floor, Chicago, IL 60601, United States';
	protected static $company_url = 'https://www.activecampaign.com/';

	public function describe_credentials_fields($refresh = FALSE)
	{
		return [
			'name' => [
				'title' => 'Account Name',
				'description' => 'It\'s an internal value, which can be helpful to identify a specific account in future.',
				'type' => 'text',
				'rules' => [
					['not_empty'],
				],
			],
			'api_url' => [
				'title' => 'ActiveCampaign Account API URL',
				'description' => '<a href="/docs/integrations/activecampaign/"  target="_blank">Read where to obtain this code</a>',
				'type' => 'text',
				'rules' => [
					['not_empty'],
					['regex', [':value', '~^https?:\/\/[a-z\d\_\-]+\.api\-[a-z]{2,4}\d{1,2}\.com$~']],
				],
			],
			'api_key' => [
				'title' => 'ActiveCampaign Account API Key',
				'description' => '<a href="/docs/integrations/activecampaign/" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => [
					['not_empty'],
					['min_length', [':value', 10]],
				],
			],
			'submit' => [
				'title' => 'Connect with ActiveCampaign',
				'action' => 'connect',
				'type' => 'submit',
			],
		];
	}

	public function filter_credentials(array $credentials)
	{
		$credentials = parent::filter_credentials($credentials);
		if (isset($credentials['api_url']) AND ! empty($credentials['api_url']))
		{
			if (parse_url($credentials['api_url'], PHP_URL_SCHEME) == '')
			{
				$credentials['api_url'] = 'https://'.$credentials['api_url'];
			}
			$credentials['api_url'] = strtolower(rtrim($credentials['api_url'], '/'));
		}

		return $credentials;
	}

	public function get_endpoint()
	{
		if ($this->get_credentials('api_url') === NULL)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_url', 'Account API URL cannot be empty');
		}

		return $this->get_credentials('api_url');
	}

	protected static $field_types = [
		'text' => 1,
		'textarea' => 2,
		'checkbox' => 3,
		'radio' => 4,
		'dropdown' => 5,
		'hidden' => 6,
		'listbox' => 7,
		'date' => 9,
	];

	public function fetch_meta()
	{
		$this->meta = [
			// { id => name }
			'lists' => [],
			// { id => title }
			'custom_fields' => [],
			// { list_id => [field1_id, field2_id, ...] }
			'lists_custom_fields' => [],
		];

		$meta_actions = [
			'tags' => 'tags_list',
			'automations' => 'automation_list',
			'deal_pipelines' => 'deal_pipeline_list',
			'deal_stages' => 'deal_stage_list',
			'forms' => 'form_getforms'
		];

		foreach ($meta_actions as $meta_key => $action)
		{
			// http://www.activecampaign.com/api/example.php?call=tags_list
			// http://www.activecampaign.com/api/example.php?call=automation_list
			// http://www.activecampaign.com/api/example.php?call=deal_pipeline_list
			// http://www.activecampaign.com/api/example.php?call=deal_stage_list
			// http://www.activecampaign.com/api/example.php?call=form_getforms
			$r = Integration_Request::factory()
				->method('GET')
				->url($this->get_credentials('api_url', '').'/admin/api.php')
				->curl(array(
					CURLOPT_CONNECTTIMEOUT_MS => 15000,
					CURLOPT_TIMEOUT_MS => 30000,
				))
				->data(array(
					'api_action' => $action,
					'api_key' => $this->get_credentials('api_key', ''),
					'api_output' => 'json'
				))
				->log_to($this->requests_log)
				->execute();

			if ( ! $r->is_successful() OR $r->get('result_code') === 0)
			{
				if (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
				}
				elseif (strpos($r->get('result_message'), 'not authorized') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
				}
				elseif (strpos($r->get('result_message'), 'Nothing is returned') !== FALSE)
                {
                    Arr::set_path($this->meta, $meta_key, array());
                }
			}

			foreach ($r->data as $key => $value)
			{
				$id = Arr::get($value, 'id', 0);
				if (is_numeric($key) AND $id)
				{
					switch ($meta_key)
					{
						case 'deal_pipelines':
							Arr::set_path($this->meta, $meta_key.'.'.$id, Arr::get($value, 'title'));
							break;
						case 'deal_stages':
							Arr::set_path($this->meta, $meta_key.'.'.Arr::get($value, 'pipeline').'.'.$id, Arr::get($value, 'title'));
							break;
						default:
							Arr::set_path($this->meta, $meta_key.'.'.$id, Arr::get($value, 'name'));
							break;
					}
				}
			}
		}

		// Get account lists
		// http://www.activecampaign.com/api/example.php?call=list_list
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->data(array(
				'api_action' => 'list_list',
				'api_key' => $this->get_credentials('api_key', ''),
				'api_output' => 'json',
				'ids' => 'all',
				'global_fields' => '1',
				// Needed to gain fields
				'full' => '1',
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful() OR $r->get('result_code') === 0)
		{
			if (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
			}
			elseif (strpos($r->get('result_message'), 'not authorized') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
            elseif (strpos($r->get('result_message'), 'Nothing is returned') !== FALSE)
            {
                Arr::set_path($this->meta, 'lists', array());
            }
		}

		foreach ($r->data as $list_data)
		{
			if ( ! is_array($list_data))
			{
				continue;
			}
			$list_id = (int) Arr::get($list_data, 'id');
			$list_name = Arr::get($list_data, 'name');
			Arr::set_path($this->meta, 'lists.'.$list_id, $list_name);

			// Storing fields
			if ( ! isset($this->meta['lists_custom_fields'][$list_id]))
			{
				$this->meta['lists_custom_fields'][$list_id] = [];
			}
			$custom_fields = (array) Arr::get($list_data, 'fields', []);
			foreach ($custom_fields as $custom_field)
			{
				$custom_field_id = (int) Arr::get($custom_field, 'id');
				$custom_field_title = Arr::get($custom_field, 'title');
				$custom_field_type = Arr::get($custom_field, 'type');
				$this->meta['custom_fields'][$custom_field_id] = [
					'title' => $custom_field_title,
					// Storing types, as they are needed for field update request
					'type' => Arr::get(self::$field_types, $custom_field_type, 1),
				];
				$this->meta['lists_custom_fields'][$list_id][] = $custom_field_id;
			}
		}

		return $this;
	}

	public function describe_automations()
	{
		$lists = (array) $this->get_meta('lists', []);
		$tags = (array) $this->get_meta('tags', []);
		$pipelines = (array) $this->get_meta('deal_pipelines', []);
		$stages = (array) $this->get_meta('deal_stages', []);
		$automations = (array) $this->get_meta('automations', []);
		$forms = (array) $this->get_meta('forms', []);

		return [
			'add_contact_tag' => [
				'title' => 'Add new tag to a contact',
				'params_fields' => [
					'tag_id' => [
						'title' => 'Tag Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$tags),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($tags)]],
						],
						'placeholder' => TRUE
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
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$tags),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($tags)]],
						],
						'placeholder' => TRUE
					],
				],
			],
			'add_contact_list' => [
				'title' => 'Add contact to list',
				'params_fields' => [
					'list_id' => [
						'title' => 'List Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$lists),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($lists)]],
						],
						'placeholder' => TRUE
					],
				],
				'is_default' => TRUE,
			],
			'remove_contact_list' => [
				'title' => 'Remove contact from list',
				'params_fields' => [
					'list_id' => [
						'title' => 'List Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$lists),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($lists)]],
						],
						'placeholder' => TRUE
					],
				],
			],
			'create_deal' => [
				'title' => 'Make a deal with contact',
				'params_fields' => [
					'pipeline_id' => [
						'title' => 'Pipeline Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$pipelines),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($pipelines)]],
						],
						'placeholder' => TRUE
					],
					'stage_id' => [
						'title' => 'Stage Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$stages),
						'options_labels' => $pipelines,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($stages)]],
						],
						'influence' => [
							'deal_pipeline'
						],
						'placeholder' => TRUE
					],
				],
			],
			'update_deal_stage' => [
				'title' => 'Set current deal stage',
				'params_fields' => [
					'stage_id' => [
						'title' => 'Stage Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$stages),
						'options_labels' => $pipelines,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($stages)]],
						],
						'influence' => [
							'deal_pipeline'
						],
						'placeholder' => TRUE
					],
				],
			],
			'add_contact_to_automation' => [
				'title' => 'Add contact to automation',
				'params_fields' => [
					'automation_id' => [
						'title' => 'Automation Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$automations),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($automations)]],
						],
						'placeholder' => TRUE
					],
				],
			],
			'remove_contact_from_automation' => [
				'title' => 'Remove contact from automation',
				'params_fields' => [
					'automation_id' => [
						'title' => 'Automation Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$automations),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($automations)]],
						],
						'placeholder' => TRUE
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
			'create_contact_via_form' => [
				'title' => 'Add contact via form',
				'params_fields' => [
					'form_id' => [
						'title' => 'Form Name',
						'description' => NULL,
						'type' => 'select',
						'options' => Arr::merge([
							'' => '(Not specified)'
						],$forms),
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($forms)]],
						],
						'placeholder' => TRUE
					],
				],
			],
		];
	}

	/**
	 * Creates new custom field for the current list
	 * (If relevant field exists, but isn't enabled for the current list, enables it)
	 * @param string $title
	 * @return int ID of the created list
	 * @throws Integration_Exception
	 */
	public function create_custom_field($title)
	{
		$current_list = $this->get_params('list', '');
		foreach ($this->get_meta('custom_fields', array()) as $field_id => $field)
		{
			if ($field['title'] === $title)
			{
				// Field already exists, need to attach current list to it
				// Getting ids of already attached lists
				$field_lists = array();
				foreach ($this->get_meta('lists_custom_fields', array()) as $list_id => $list_fields)
				{
					if (in_array($field_id, $list_fields))
					{
						$field_lists[] = $list_id;
					}
				}
				// Attaching current list
				if ( ! in_array($current_list, $field_lists))
				{
					$field_lists[] = $current_list;
					// Storing to meta
					$this->meta['lists_custom_fields'][$current_list][] = $field_id;
				}
				// https://www.activecampaign.com/api/example.php?call=list_field_edit
				$r = Integration_Request::factory()
					->method('POST')
					->curl(array(
						CURLOPT_CONNECTTIMEOUT_MS => 15000,
						CURLOPT_TIMEOUT_MS => 30000,
					))
					->url($this->get_credentials('api_url', '').'/admin/api.php')
					->header('Content-Type', 'application/x-www-form-urlencoded')
					->data(array(
						'api_action' => 'list_field_edit',
						'api_key' => $this->get_credentials('api_key', ''),
						'api_output' => 'json',
						'id' => $field_id,
						'title' => $title,
						'type' => $field['type'],
						'p' => array_combine($field_lists, $field_lists),
					))
					->log_to($this->requests_log)
					->execute();
				if ( ! $r->is_successful())
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
				}
				elseif ($r->get('result_code') === 0 AND strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
				}
				elseif ($r->get('result_code') === 0 AND strpos($r->get('result_message'), 'not authorized') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
				}
				elseif ($r->get('result_code') === 0)
				{
					throw new Integration_Exception(INT_E_UNKNOWN_ERROR, 'api_key', $r->get('result_message'));
				}

				return $field_id;
			}
		}
		// Existing field not found anywhere, creating the new one
		$lists_ids = array_keys($this->get_meta('lists', array()));
		// https://www.activecampaign.com/api/example.php?call=list_field_add
		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->data(array(
				'api_action' => 'list_field_add',
				'api_key' => $this->get_credentials('api_key', ''),
				'api_output' => 'json',
				'title' => $title,
				'type' => self::$field_types['text'],
				'req' => 0,
				// Attaching to all lists
				'p' => array_combine($lists_ids, $lists_ids),
			))
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
		elseif ($r->get('result_code') === 0 AND strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
		}
		elseif ($r->get('result_code') === 0 AND strpos($r->get('result_message'), 'not authorized') !== FALSE)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
		}
		elseif ($r->get('result_code') === 0 AND strpos($r->get('result_message'), 'Nothing is returned') !== FALSE)
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'api_key', 'Please create list for subscribers inside your ActiveCampaign account');
		}
		elseif ($r->get('result_code') === 0)
		{
			throw new Integration_Exception(INT_E_UNKNOWN_ERROR, 'api_key', $r->get('result_message'));
		}

		$field_id = (int) $r->get('fieldid');
		// Storing to meta
		$this->meta['custom_fields'][$field_id] = array(
			'title' => $title,
			'type' => self::$field_types['text'],
		);
		foreach ($this->meta['lists_custom_fields'] as $list_id => $fields)
		{
			$this->meta['lists_custom_fields'][$list_id] = array_merge($fields, array($field_id));
		}

		return $field_id;
	}

	/**
	 * Get available custom fields for the current list
	 * Fetching custom fields for the current list on demand, and caching it in meta for 24 hours
	 * @param bool $force_fetch Prevent using cached version
	 * @return array field_id => field_title
	 * @throws Integration_Exception
	 */
	public function get_custom_fields($force_fetch = FALSE)
	{
		if ($force_fetch)
		{
			$this->fetch_meta();
		}
		$current_list = $this->get_params('list', '');
		$current_list_fields = $this->get_meta('lists_custom_fields.'.$current_list, array());
		$result = array();
		foreach ($this->get_meta('custom_fields', array()) as $field_id => $field)
		{
			if (in_array($field_id, $current_list_fields))
			{
				$result[$field_id] = mb_strtoupper($field['title']);
			}
		}

		return $result;
	}

	/**
	 * @var array ActiveCampaign standard fields names for Convertful person fields
	 */
	protected $standard_fields = array(
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'name' => 'name',
		'phone' => 'phone',
		'company' => 'orgname',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_custom_fields = FALSE)
	{
		$custom_fields_data = Arr::get($subscriber_data, 'meta', array());
		unset($subscriber_data['meta']);
		$int_data = array();

		// Trying to use standard first_name / last_name with filled name type when possible
		if (isset($subscriber_data['name']) AND ! empty($subscriber_data['name']) AND ! isset($subscriber_data['first_name']) AND ! isset($subscriber_data['last_name']))
		{
			$name = explode(' ', $subscriber_data['name'], 2);
			$subscriber_data['first_name'] = $name[0];
			if (isset($name[1]))
			{
				$subscriber_data['last_name'] = $name[1];
			}
		}

		// Standard person fields
		foreach ($subscriber_data as $f_type => $f_value)
		{
			if (isset($this->standard_fields[$f_type]))
			{
				// ActiveCampaign got standard field for this type
				$int_data[$this->standard_fields[$f_type]] = $f_value;
			}
			else
			{
				// No standard field: using custom field instead
				// Human-readable type format
				$field_name = Inflector::humanize(ucfirst($f_type));
				$custom_fields_data[$field_name] = $f_value;
			}
		}

		// Preventing outdated cache
		$custom_fields = $this->get_custom_fields();
		if ($create_missing_custom_fields)
		{
			foreach ($custom_fields_data as $f_name => $f_value)
			{
				if ( ! in_array(mb_strtoupper($f_name), $custom_fields))
				{
					$custom_fields = $this->get_custom_fields(TRUE);
					break;
				}
			}
		}

		// Handling custom fields
		foreach ($custom_fields_data as $f_name => $f_value)
		{
			// Trying to find existing relevant custom field by its title
			if ( ! ($cf_id = array_search(mb_strtoupper($f_name), $custom_fields, TRUE)))
			{
				if ( ! $create_missing_custom_fields)
				{
					continue;
				}
				// Creating new custom field
				$cf_id = $this->create_custom_field($f_name);
				// Updating $custom_fields so the new field is present there
				$custom_fields = $this->get_custom_fields();
			}
			Arr::set_path($int_data, 'field.'.$cf_id.',0', $f_value);
		}

		return $int_data;
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => array(),
		);

		foreach ($this->standard_fields as $field_type => $field_name)
		{
			if (isset($int_data[$field_name]) AND ! empty($int_data[$field_name]))
			{
				$subscriber_data[$field_type] = $int_data[$field_name];
			}
		}
		foreach (Arr::get($int_data, 'fields', array()) as $field)
		{
			if ( ! empty($field['val']))
			{
				if ($field['title'] === 'Site')
				{
					$subscriber_data['site'] = $field['val'];
				}
				else
				{
					$subscriber_data['meta'][$field['title']] = $field['val'];
				}
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

	/**
	 * @var array Cache of a single contact ID to prevent additional request (stored as email => id)
	 */
	protected $contact_id_cache = array();

	public function get_subscriber($email)
	{
		// Getting the subscriber
		// http://www.activecampaign.com/api/example.php?call=contact_list
		$r = Integration_Request::factory()
			->method('GET')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->data(array(
				'api_action' => 'contact_list',
				'api_output' => 'json',
				'filters' => array(
					'email' => $email,
				),
				'api_key' => $this->get_credentials('api_key', ''),
			))
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
		elseif ($r->get('result_code') === 0 AND strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
		}
		elseif ($r->get('result_code') === 0 AND strpos($r->get('result_message'), 'not authorized') !== FALSE)
		{
			// ActiveCampaign doesn't return any error codes :(
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
		}
		elseif ($r->get('result_code') === 0)
		{
			// Not found
			return NULL;
		}
		$subscriber_data = $this->translate_int_data_to_subscriber_data($r->get('0'));
		return $subscriber_data;
	}


	public function add_contact_list($email, $params, $subscriber_data = NULL)
	{
		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$current_list = Arr::get($params, 'list_id');
		if ( ! isset($current_list) OR empty($current_list))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			// Create new user
			// http://www.activecampaign.com/api/example.php?call=contact_add
			$action = 'contact_add';
		}
		else
		{
			// Update user
			// https://www.activecampaign.com/api/example.php?call=contact_edit
			$action = 'contact_edit';
			$int_data['id'] = Arr::path($subscriber, '$integration.id');

			//TODO: validate subscriber id
		}

		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			])
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->data(array_merge($int_data, [
				'api_action' => $action,
				'api_key' => $this->get_credentials('api_key', ''),
				'api_output' => 'json',
				'email' => $email,
				'ip4' => Request::$client_ip,
				'status' => [
					$current_list => 1,
				],
				'status' => 1,
				'p' => [
					$current_list => $current_list,
				],
			]))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
		{
			if (strpos($r->get('result_message'), 'not authorized') !== FALSE )
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
			}
			elseif ($r->code === 508)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			// Not 100% sure about this part: maybe result_code could be 0 for some other cases
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function remove_contact_list($email, $params)
    {
        $current_list = Arr::get($params, 'list_id');
        if ( ! isset($current_list) OR empty($current_list))
        {
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

        $subscriber = $this->get_subscriber($email);
        if ($subscriber === NULL)
        {
            return;
        }

        $this->contact_sync($email,array(
            'p' => array(
                $current_list => $current_list
            ),
            'status' => array(
                $current_list => 2
            )
        ));
    }

    protected function contact_sync($email, $data = array())
    {
        $action = 'contact_sync';
        $r = Integration_Request::factory()
            ->method('POST')
            ->curl([
                CURLOPT_CONNECTTIMEOUT_MS => 15000,
                CURLOPT_TIMEOUT_MS => 30000,
            ])
            ->url($this->get_credentials('api_url', '').'/admin/api.php')
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->data(array_merge($data,array(
                'api_action' => $action,
                'api_key' => $this->get_credentials('api_key', ''),
                'api_output' => 'json',
                'email' => $email,
                'ip4' => Request::$client_ip,
            )))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
        {
            if (strpos($r->get('result_message'), 'not authorized') !== FALSE )
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
            }
            elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
            }
            elseif ($r->code === 508)
            {
                throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
            }

            throw new Integration_Exception(INT_E_WRONG_REQUEST);
        }
        else
        {
            return $r->get('subscriber_id');
        }
    }

    public function add_contact_tag($email, $params, $subscriber_data)
    {
        $selected_tag = Arr::get($params, 'tag_id');
        if ( ! isset($selected_tag))
        {
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

        $subscriber = $this->get_subscriber($email);
        if ($subscriber === NULL)
        {
            $this->contact_sync($email);
        }

        $action = 'contact_tag_add';

        $r = Integration_Request::factory()
            ->method('POST')
            ->curl(array(
                CURLOPT_CONNECTTIMEOUT_MS => 15000,
                CURLOPT_TIMEOUT_MS => 30000,
            ))
            ->url($this->get_credentials('api_url', '').'/admin/api.php')
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->data(array(
                'api_action' => $action,
                'api_key' => $this->get_credentials('api_key', ''),
                'api_output' => 'json',
                'email' => $email,
                'tags' => $this->meta['tags'][$selected_tag]
            ))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
        {

            if (strpos($r->get('result_message'), 'not authorized') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
            }
            elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
            }
            elseif ($r->code === 508)
            {
                throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
            }
            throw new Integration_Exception(INT_E_WRONG_REQUEST);
        }
    }

    public function remove_contact_tag($email, $params)
    {
        $selected_tag = Arr::get($params, 'tag_id');
        if ( ! isset($selected_tag) OR empty($selected_tag))
        {
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

        $action = 'contact_tag_remove';

        $r = Integration_Request::factory()
            ->method('POST')
            ->curl(array(
                CURLOPT_CONNECTTIMEOUT_MS => 15000,
                CURLOPT_TIMEOUT_MS => 30000,
            ))
            ->url($this->get_credentials('api_url', '').'/admin/api.php')
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->data(array(
                'api_action' => $action,
                'api_key' => $this->get_credentials('api_key', ''),
                'api_output' => 'json',
                'email' => $email,
                'tags' => $this->meta['tags'][$selected_tag]
            ))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
        {
            if (strpos($r->get('result_message'), 'not authorized') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
            }
            elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
            }
            elseif ($r->code === 508)
            {
                throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
            }
            throw new Integration_Exception(INT_E_WRONG_REQUEST);
        }
    }

    public function add_contact_note($email, $params)
    {
        $note = Arr::get($params, 'text');
        if ( ! isset($note) OR empty($note))
        {
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

        $subscriber = $this->get_subscriber($email);
        $subscriber_id = ($subscriber === NULL) ? $this->contact_sync($email) : Arr::path($subscriber, '$integration.id');

        if ( ! isset($subscriber_id) OR empty($subscriber_id))
        {
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

        $action = 'contact_note_add';

        $r = Integration_Request::factory()
            ->method('POST')
            ->curl(array(
                CURLOPT_CONNECTTIMEOUT_MS => 15000,
                CURLOPT_TIMEOUT_MS => 30000,
            ))
            ->url($this->get_credentials('api_url', '').'/admin/api.php')
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->data(array(
                'api_action' => $action,
                'api_key' => $this->get_credentials('api_key', ''),
                'api_output' => 'json',
                'id' => $subscriber_id,
                'note' => $note,
                'listid' => 0
            ))
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
        {
            if (strpos($r->get('result_message'), 'not authorized') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
            }
            elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
            {
                throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
            }
            elseif ($r->code === 508)
            {
                throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
            }
            throw new Integration_Exception(INT_E_WRONG_REQUEST);
        }
    }

    public function create_deal($email, $params)
	{
		$pipeline = Arr::get($params, 'pipeline_id');
		$stage = Arr::get($params, 'stage_id');
		if ( ! isset($pipeline) OR ! isset($stage) OR empty($pipeline) OR empty($stage))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		$subscriber_id = ($subscriber === NULL) ? $this->contact_sync($email) : Arr::path($subscriber, '$integration.id');

		if ( ! isset($subscriber_id) OR empty($subscriber_id))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		$action = 'deal_add';

		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->data(array(
				'api_action' => $action,
				'api_key' => $this->get_credentials('api_key', ''),
				'api_output' => 'json',
				'pipeline' => $pipeline,
				'stage' => $stage,
				'title' => 'Deal',
				'value' => '0',
				'currency' => 'usd',
				'contactid' => $subscriber_id,
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
		{
			if (strpos($r->get('result_message'), 'not authorized') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
			}
			elseif ($r->code === 508)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			elseif (strpos($r->get('result_message'), 'The provided stage does not exist or is not part of the pipeline provided') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function update_deal_stage($email, $params)
	{
		$stage = Arr::get($params, 'stage_id');
		if ( ! isset($stage) OR empty($stage))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		$action = 'deal_list';

		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->data(array(
				'api_action' => $action,
				'api_key' => $this->get_credentials('api_key', ''),
				'api_output' => 'json',
				'filters' => array(
					'status' => 0,
					'email' => $email,
					'stage' => $stage
				)
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
		{
			if (strpos($r->get('result_message'), 'not authorized') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
			}
			elseif ($r->code === 508)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
		else {
			$deals = $r->get('deals');
			if ( ! empty($deals))
			{
				usort($deals, function($deal_1,$deal_2)
					{
						return strtotime($deal_1['created']) < strtotime($deal_2['created']);
					}
				);
				$curent_deal = array_shift($deals);
			}
		}
	}

	public function add_contact_to_automation($email, $params)
	{
		$automation = Arr::get($params, 'automation_id');
		if ( ! isset($automation) OR empty($automation))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		$action = 'automation_contact_add';

		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->data(array(
				'api_action' => $action,
				'api_key' => $this->get_credentials('api_key', ''),
				'api_output' => 'json',
				'automation' => $automation,
				'contact_email' => $email
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
		{
			if (strpos($r->get('result_message'), 'not authorized') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
			}
			elseif ($r->code === 508)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function remove_contact_from_automation($email, $params)
	{
		$automation = Arr::get($params, 'automation_id');
		if ( ! isset($automation) OR empty($automation))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}
		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		$action = 'automation_contact_remove';

		$r = Integration_Request::factory()
			->method('POST')
			->curl(array(
				CURLOPT_CONNECTTIMEOUT_MS => 15000,
				CURLOPT_TIMEOUT_MS => 30000,
			))
			->url($this->get_credentials('api_url', '').'/admin/api.php')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->data(array(
				'api_action' => $action,
				'api_key' => $this->get_credentials('api_key', ''),
				'api_output' => 'json',
				'automation' => $automation,
				'contact_email' => $email
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful() OR $r->get('result_code') !== 1)
		{
			if (strpos($r->get('result_message'), 'not authorized') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif (strpos($r->get('result_message'), 'This account is currently unavailable') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'This account is currently unavailable');
			}
			elseif ($r->code === 508)
			{
				throw new Integration_Exception(INT_E_FREQUENT_TEMPORARY_ERR);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function create_contact_via_form($email, $params)
	{
		$current_form = Arr::get($params, 'form_id');
		if ( ! isset($current_form) OR empty($current_form))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			$this->contact_sync($email,array(
				'form' => $current_form
			));
		}
	}
}