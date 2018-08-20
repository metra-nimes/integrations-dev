<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Klaviyo Integration
 * @link https://www.klaviyo.com/docs/http-api
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_Klaviyo extends Integration_Driver implements Integration_Interface_ContactStorage {

	protected static $company_name = 'Klaviyo Inc.';
	protected static $company_address = '225 Franklin St., Boston, Massachusetts 02110, USA';
	protected static $company_url = 'https://www.klaviyo.com/';

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
			'api_key' => array(
				'title' => 'Account API Key',
				'description' => '<a href="/docs/integrations/klaviyo/#step-2-get-your-klaviyo-api-key" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => array(
					array('regex', array(':value', '/^\S{10,}/i')),
					array('not_empty'),
				),
			),
			'submit' => array(
				'title' => 'Connect with Klaviyo',
				'action' => 'connect',
				'type' => 'submit',
			),
		);
	}

	public function get_endpoint()
	{
		return 'https://a.klaviyo.com/api/v2';
	}

	public function fetch_meta()
	{
		$this->meta = array(
			'lists' => array(),
		);

		// Get account meta
		// https://www.klaviyo.com/docs/api/lists
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/lists')
			->data(array(
				'api_key' => $this->get_credentials('api_key', ''),
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		foreach ($r->get_response_data() as $list)
		{
			$list_id = Arr::get($list, 'list_id', 0);
			if ($list_id)
			{
				$this->meta['lists'][$list_id] = Arr::get($list, 'list_name', 'No name');
			}
		}

		return $this;
	}

	public function describe_automations()
	{
		$lists = (array) $this->get_meta('lists', []);

		return [
			'add_list_subscriber' => [
				'title' => 'Add subscriber to a list',
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
						'placeholder' => TRUE
					],
				],
			],
			'remove_list_subscriber' => [
				'title' => 'Remove subscriber from a list',
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
						'placeholder' => TRUE
					],
				],
			],

		];
	}

	public function describe_params_fields()
	{
		$lists = $this->get_meta('lists', array());

		return array(
			'list' => array(
				'title' => 'List',
				'description' => NULL,
				'type' => 'select',
				'options' => $lists,
				'classes' => 'i-refreshable',
				'rules' => array(
					array('in_array', array(':value', array_keys($lists))),
					array('not_empty'),
				),
			),
			'double_optin' => array(
				'text' => 'Double Opt-in',
				'description' => 'When enabled, subscribers will receive a email with the link to confirm their subscription. <a href="/docs/integrations/klaviyo/#double-opt-in" target="_blank">Read more about it.</a>',
				'type' => 'switcher',
				'std' => TRUE,
			),
		);
	}

	/**
	 * @var array ConstantContact standard fields names for Convertful person fields
	 */
	protected $standard_fields = array(
		'first_name' => '$first_name',
		'last_name' => '$last_name',
		'phone' => '$phone_number',
		'company' => '$organization',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = [];

		foreach (Arr::get($subscriber_data, 'meta', []) as $key => $value)
		{
			if ( ! is_null($value))
			{
				if (strpos($key, 'agreement') !== false)
				{
					Arr::set_path($int_data, '$consent', 'web');
				}
				Arr::set_path($int_data, $key, $value?: NULL);
			}
		}

		foreach ($subscriber_data as $key => $value)
		{
			if ( ! isset($this->standard_fields[$key]) && $key != 'meta')
			{
				Arr::set_path($int_data, $key, $value);
			}
		}

		foreach ($this->standard_fields as $person_key => $int_key)
		{
			$value = Arr::path($subscriber_data, $person_key, NULL);
			if (! is_null($value))
			{
				Arr::set_path($int_data, $int_key, $value?: NULL);
			}
		}

		// Trying to use standard first_name / last_name when name is defined
		if (isset($subscriber_data['name']) AND ! empty($subscriber_data['name']) AND ! isset($int_data['$first_name']) AND ! isset($int_data['$last_name']))
		{
			$name = explode(' ', $subscriber_data['name'], 2);
			$int_data['$first_name'] = $name[0];
			if (isset($name[1]))
			{
				$int_data['$last_name'] = $name[1];
			}
		}

		return $int_data;
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array();
		foreach ($this->standard_fields as $person_key => $int_key)
		{
			if ($value = Arr::path($int_data, 'person.'.$int_key, NULL))
			{
				Arr::set_path($subscriber_data, $person_key, $value);
			}
		}

		$id = Arr::get($int_data['person'], 'id');
		if ($id)
		{
			Arr::set_path($subscriber_data, '$integration.id', $id);
		}

		return $subscriber_data;
	}

	public function get_subscriber($email)
	{
		$endpoint_v1 = 'https://a.klaviyo.com/api/v1';

		foreach ($this->meta['lists'] as $list_id => $list)
		{
			// get subscriber
			// https://www.klaviyo.com/docs/api/lists#list-members
			$r = Integration_Request::factory()
				->method('GET')
				->curl(array(
					CURLOPT_CONNECTTIMEOUT_MS => 15000,
					CURLOPT_TIMEOUT_MS => 30000,
				))
				->url($endpoint_v1.'/list/'.$list_id.'/members')
				->data(array(
					'api_key' => $this->get_credentials('api_key', ''),
					'email' => $email,
				))
				->log_to($this->requests_log)
				->execute();

			if ( ! $r->is_successful())
			{
				$this->verify_response($r);
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}

			if ($r->get('total') == 0)
			{
				return NULL;
			}

			return $this->translate_int_data_to_subscriber_data($r->path('data.0'));
		}
	}

	public function create_person($email, $subscriber_data)
	{
		$list_id = $this->get_params('list', NULL);
		if (is_null($list_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$data = array();
		$data['api_key'] = $this->get_credentials('api_key', '');
		$data['email'] = strtolower($email);
		$data['confirm_optin'] = $this->get_params('double_optin') ? 'true' : 'false';

		$props = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		if ( ! empty($props))
		{
			$data['properties'] = json_encode($props);
		}
		// Create new user
		// https://www.klaviyo.com/docs/api/lists#list-members-add
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/list/'.$list_id.'/members')
			->data($data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
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
	protected static function verify_response($r)
	{
		switch ($r->code)
		{
			case 401:
			case 400:
				if (strpos($r->get('result_message'), 'Request is missing or has an invalid API key.') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
				}
				elseif (strpos($r->get('result_message'), 'Request is missing or has a bad parameter.') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_PARAMS);
				}
				break;
			case 404:
				throw new Integration_Exception(INT_E_WRONG_DATA);
				break;
			case 409:
			case 500:
				throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
				break;
			case 503:
			case 508:
		}

		return TRUE;
	}

	/**
	 * Add subscriber to a list
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function add_list_subscriber($email, $params, $subscriber_data = array())
	{
		$current_list = Arr::get($params, 'list_id');
		if ( ! isset($current_list) OR empty($current_list))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);

		// Subscribe to List
		// https://www.klaviyo.com/docs/api/v2/lists
		$r = Integration_Request::factory()
			->method('POST')
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/list/'.$current_list.'/members')
			->data(array(
				'api_key' => $this->get_credentials('api_key', ''),
				'profiles' => array(array_merge($int_data,
					array(
						'email' => $email,
						'$consent' => 'web'
					)
				))
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Remove subscriber from a list
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_list_subscriber($email, $params)
	{
		$current_list = Arr::get($params, 'list_id');
		if ( ! isset($current_list) OR empty($current_list))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		// Unsubscribe from List
		// https://www.klaviyo.com/docs/api/v2/lists
		$r = Integration_Request::factory()
			->method('DELETE')
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/list/'.$current_list.'/subscribe')
			->data(array(
				'api_key' => $this->get_credentials('api_key', ''),
				'emails' => array($email)
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == '415')
			{
				return;
			}
			else
			{
				$this->verify_response($r);
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}
}