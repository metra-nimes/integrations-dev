<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * ConstantContact Integration
 * @link https://developer.constantcontact.com/docs/developer-guides/overview-of-api-endpoints.html
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_ConstantContact extends Integration_Driver implements Integration_Interface_ContactStorage {

	protected static $company_name = 'Constant Contact, Inc.';
	protected static $company_address = '1601 Trapelo Road, Waltham, MA 02451, Massachusetts, USA';
	protected static $company_url = 'https://www.constantcontact.com/';

	public function describe_credentials_fields($refresh = FALSE)
	{
		return array(
			'name' => array(
				'title' => 'Account Name',
				'description' => 'It\'s an internal value, which can be helpful to identify a specific account in future.',
				'type' => 'text',
				'rules' => array(
					array('not_empty'),
				),
			),
			'oauth' => array(
				'title' => 'Connect with ConstantContact',
				'type' => 'oauth',
				// The name of array key that will contain token
				'token_key' => 'access_token',
				// https://developer.constantcontact.com/docs/authentication/authentication.html
				'url' => 'https://oauth2.constantcontact.com/oauth2/oauth/siteowner/authorize?'.
					'response_type=token'.
					'&client_id='.urlencode($this->get_key()).
					'&redirect_uri='.URL::domain().'/api/integration/complete_oauth/ConstantContact',
				'size' => '600x600',
				'rules' => array(
					array('not_empty'),
				),
			),
		);
	}

	public function get_endpoint()
	{
		return 'https://api.constantcontact.com/v2';
	}

	public function get_name()
	{
		return $this->get_credentials('name', '');
	}

	public function get_key()
	{
		return Arr::path(Kohana::$config->load('integrations_oauth')->as_array(), 'ConstantContact.key');
	}

	public function fetch_meta()
	{
		$oauth_token = $this->get_credentials('oauth.access_token', '');
		$this->meta = array(
			'lists' => array(),
		);

		// Fetch contact lists
		// https://developer.constantcontact.com/docs/contact-list-api/contactlist-collection.html
		$r = Integration_Request::factory()
			->method('GET')
			->header('Authorization', 'Bearer '.$oauth_token)
			->url($this->get_endpoint().'/lists?api_key='.urlencode($this->get_key()))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth', 'oAuth token is not valid');
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

		foreach ($r->data as $list)
		{
			if (isset($list['id']) AND isset($list['name']))
			{
				$this->meta['lists'][$list['id']] = $list['name'];
			}
		}

		return $this;
	}

	public function describe_automations()
	{
		$lists = (array) $this->get_meta('lists', []);

		return [
			'add_list_contact' => [
				'title' => 'Add contact to list',
				'params_fields' => [
					'list_id' => [
						'title' => 'List Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $lists,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($lists)]],
						],
					],
				],
				'is_default' => TRUE,
			],
			'remove_list_contact' => [
				'title' => 'Remove contact from list',
				'params_fields' => [
					'list_id' => [
						'title' => 'List Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $lists,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($lists)]],
						],
					],
				],
				'is_default' => TRUE,
			],
			'add_contact_note' => [
				'title' => 'Add note to the contact',
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
				array('max_length', array(':value', 50), 'The maximum length of field\'s content should not exceed 50 characters.'),
			),
			'hidden' => array(
				array('max_length', array(':value', 50), 'The maximum length of field\'s content should not exceed 50 characters.'),
			),
		);
	}

	/**
	 * ConstantContact API doesn't support labeled custom fields, so we store labels in params, and creating custom
	 * field means assigning some param to the proper label
	 * @param string $label
	 * @return string|NULL Field name or NULL if the field cannot be created
	 */
	protected function create_custom_field($label)
	{
		// Enabling custom fields if they are disabled right now
		if ( ! $this->get_params('specify_custom_fields'))
		{
			$this->params['specify_custom_fields'] = TRUE;
		}
		// Taking first spare field
		for ($index = 1; $index < 16; $index++)
		{
			$field_name = 'custom_field_'.$index;
			if (empty($this->params[$field_name.'_label']))
			{
				$this->params[$field_name.'_label'] = $label;

				return $field_name;
			}
		}

		// No more spare fields, ignoring the rest
		return NULL;
	}

	/**
	 * ConstantContact API doesn't support labeled custom fields, so we store labels in params
	 * @return array field_name => field_label
	 */
	public function get_custom_fields()
	{
		$custom_fields = array();
		if ($this->get_params('specify_custom_fields'))
		{
			for ($index = 1; $index < 16; $index++)
			{
				$field_name = 'custom_field_'.$index;
				$field_label = $this->get_params($field_name.'_label', '');
				if ( ! empty($field_label))
				{
					$custom_fields[$field_name] = $field_label;
				}
			}
		}

		return $custom_fields;
	}

	/**
	 * @var array ConstantContact standard fields names for Convertful person fields
	 */
	protected $standard_fields = array(
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'phone' => 'cell_phone',
		'company' => 'company_name',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		// Grabbing custom fields titles if they present
		$custom_fields = $this->get_custom_fields();

		$person_meta = Arr::get($subscriber_data, 'meta', array());
		unset($subscriber_data['meta']);

		$int_data = array();
		foreach ($subscriber_data as $field_type => $value)
		{
			if ($field_type === 'name')
			{
				continue;
			}

			if (isset($this->standard_fields[$field_type]))
			{
				$int_data[$this->standard_fields[$field_type]] = $value;
			}
			else
			{
				// For site and other types that are not yet implemented
				$field_label = Inflector::humanize(ucfirst($field_type));
				$person_meta[$field_label] = $value;
			}
		}

		foreach ($person_meta as $field_label => $value)
		{
			if ( ! ($field_name = array_search($field_label, $custom_fields)))
			{
				if ($create_missing_fields)
				{
					$field_name = $this->create_custom_field($field_label);
					$custom_fields = $this->get_custom_fields();
				}
				if ( ! $field_name)
				{
					// Cannot create missing field
					continue;
				}
			}
			if ( ! isset($int_data['custom_fields']))
			{
				$int_data['custom_fields'] = array();
			}
			$int_data['custom_fields'][] = array(
				'name' => $field_name,
				'value' => mb_substr($value, 0, 50),
			);
		}

		// Trying to use standard first_name / last_name when name is defined
		if (isset($subscriber_data['name']) AND ! empty($subscriber_data['name']) AND empty($int_data['first_name']) AND empty($int_data['last_name']))
		{
			$name = explode(' ', $subscriber_data['name'], 2);
			$int_data['first_name'] = $name[0];
			if (isset($name[1]))
			{
				$int_data['last_name'] = $name[1];
			}
		}

		return $int_data;
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => array(),
		);

		$custom_fields = $this->get_custom_fields();

		foreach ($this->standard_fields as $field_type => $field_name)
		{
			if (isset($int_data[$field_name]) AND ! empty($int_data[$field_name]))
			{
				$subscriber_data[$field_type] = $int_data[$field_name];
			}
		}

		foreach (Arr::get($int_data, 'custom_fields', array()) as $field)
		{
			// ConstantContact outputs custom fields in different format than inputs them :(
			$field['name'] = str_replace('CustomField', 'custom_field_', $field['name']);
			if ( ! empty($field['value']))
			{
				$field_label = Arr::get($custom_fields, $field['name'], $field['label']);
				if (in_array($field_label, array('Site', 'Name')))
				{
					// Standard convertful fields
					$field_name = strtolower($field_label);
					$subscriber_data[$field_name] = $field['value'];
				}
				else
				{
					$subscriber_data['meta'][$field_label] = $field['value'];
				}
			}
		}

		$id = Arr::get($int_data, 'id');
		if ($id)
		{
			Arr::set_path($subscriber_data, '$integration.id', $id);
		}

		$lists = Arr::get($int_data, 'lists');
		if ($lists)
		{
			Arr::set_path($subscriber_data, '$integration.lists', $lists);
		}
		// Trying to use standard first_name / last_name when name is defined
/*		if ( ! isset($subscriber_data['name']) AND ( ! empty($subscriber_data['first_name']) OR ! empty($subscriber_data['last_name'])))
		{
			$subscriber_data['name'] = trim($subscriber_data['first_name'].' '.$subscriber_data['last_name']);
		}*/

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}

		return $subscriber_data;
	}

	/**
	 * @var array Cache of a single contact to prevent additional request (stored as email => contact)
	 */
	protected $contact_cache = array();

	public function get_subscriber($email)
	{
		// Get contact
		// https://developer.constantcontact.com/docs/contacts-api/contacts-collection.html
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/contacts?api_key='.urlencode($this->get_key()))
			->header('Authorization', 'Bearer '.$this->get_credentials('oauth.access_token', ''))
			->data(array(
				'email' => $email,
				'status' => 'ALL',
			))
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth', 'oAuth token is not valid');
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

		$results = $r->get('results', array());
		// Checking if the contact exists at all
		if (empty($results))
		{
			return NULL;
		}
		$int_data = $this->contact_cache[$email] = $results[0];

		return $this->translate_int_data_to_subscriber_data($int_data);
	}

	/**
	 * Subscriber create/update/delete
	 * @param $method
	 * @param $subscriber_id
	 * @param array $int_data
	 * @throws Integration_Exception
	 */
	protected function subscriber_crud($method, $subscriber_id, $int_data = array())
	{
		$action_by = ($method != 'DELETE') ? '&action_by=ACTION_BY_VISITOR' : '';

		// Contact create/update/delete
		// @link http://developer.constantcontact.com/docs/contacts-api/contacts-index.html
		$r = Integration_Request::factory()
			->method($method)
			->url($this->get_endpoint().'/contacts/'.$subscriber_id.'?api_key='.urlencode($this->get_key()).$action_by)
			->header('Authorization', 'Bearer '.$this->get_credentials('oauth.access_token', ''))
			->header('Content-Type', 'application/json')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ($method == 'DELETE' AND $r->code == 415)
		{
			return;
		}

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth', 'oAuth token is not valid');
			}
			elseif ($r->code == 400 AND strpos($r->path('0.error_message'), 'Only one note is allowed') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS,'email',$r->path('0.error_message'));
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

	/** Add contact to list
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function add_list_contact($email, $params, $subscriber_data = array())
	{
		$current_list = Arr::get($params, 'list_id');

		if ( ! isset($current_list) OR empty($current_list))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);

		$int_data['email_addresses'] = array(
			array(
				'email_address' => $email,
			),
		);

		$int_data['lists'] = array(
			array(
				'id' => $current_list,
			),
		);

		$subscriber = $this->get_subscriber($email);

		if ($subscriber === NULL)
		{
			$method = 'POST';
			$subscriber_id = '';
		}
		else
		{
			$method = 'PUT';
			$subscriber_id  = Arr::path($subscriber, '$integration.id');
			$int_data['lists'] = array_merge($int_data['lists'],Arr::path($subscriber, '$integration.lists',[]));
		}

		$this->subscriber_crud($method,$subscriber_id,$int_data);
	}

	/**
	 * Remove contact from list
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_list_contact($email, $params)
	{
		$current_list = Arr::get($params, 'list_id');

		if ( ! isset($current_list) OR empty($current_list))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);

		if ($subscriber === NULL)
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}
		else
		{
			$subscriber_id  = Arr::path($subscriber, '$integration.id');
			$int_data['email_addresses'] = array(
				array(
					'email_address' => $email,
				),
			);
			$int_data['lists'] = array_values(array_filter(Arr::path($subscriber, '$integration.lists',[]), function($item) use (&$current_list)
				{
					return $item['id'] != $current_list;
				}
			));

			if (empty($int_data['lists']))
			{
				$method = 'DELETE';
				$int_data = [];
			}
			else
			{
				$method = 'PUT';
			}


			$this->subscriber_crud($method,$subscriber_id,$int_data);
		}
	}

	/**
	 * Add note to the contact
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function add_contact_note($email, $params)
	{
		$text = Arr::get($params, 'text');

		if ( ! isset($text) OR empty($text))
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
			$subscriber_id  = Arr::path($subscriber, '$integration.id');
			$method = 'PUT';
			$int_data['email_addresses'] = array(
				array(
					'email_address' => $email,
				),
			);
			$int_data['lists'] = Arr::path($subscriber, '$integration.lists',[]);
			$int_data['notes'] = array(
				array(
					'note' => $text
				),
			);

			$this->subscriber_crud($method,$subscriber_id,$int_data);
		}
	}
}