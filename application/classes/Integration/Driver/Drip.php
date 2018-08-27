<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Class Integration_Driver_Drip
 * @link https://freelansim.ru/tasks/196779
 * @link https://github.com/convertful/integrations-dev
 * @link https://www.getdrip.com/user/applications
 * @link https://developer.drip.com
 *
 * @version 0.5 (27.04.2018)
 */
class Integration_Driver_Drip extends Integration_OauthDriver implements Integration_Interface_ContactStorage {

	protected static $company_name = 'Avenue 81, Inc.';
	protected static $company_address = '251 N. 1st Avenue, Suite 200, Minneapolis, MN 55401, USA';
	protected static $company_url = 'https://www.drip.com/';

	/**
	 * Describe standard properties
	 *
	 * @var array
	 */
	protected $standard_properties = [
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'site' => 'site',
		'company' => 'company',
		'phone' => 'phone',
		'referral_url' => 'original_referrer',
		'landing_page_url' => 'landing_url',
	];

	/**
	 * Get endpoint URL for API calls
	 *
	 * @return string
	 */
	public function get_endpoint()
	{
		return 'https://api.getdrip.com';
	}

	/**
	 * Get Drip Application Client ID
	 *
	 * @return Kohana_Config_Group
	 * @throws Kohana_Exception
	 */
	protected function get_key()
	{
		return Kohana::$config->load('integrations_oauth.Drip.key');
	}

	/**
	 * Get Drip Application Client Secret
	 *
	 * @return Kohana_Config_Group
	 * @throws Kohana_Exception
	 */
	protected function get_secret()
	{
		return Kohana::$config->load('integrations_oauth.Drip.secret');
	}

	/**
	 * Describe COF fieldset config to render a credentials form, so a user could connect integration account
	 *
	 * @param bool $refresh
	 * @return array
	 * @throws Kohana_Exception
	 */
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
			'oauth' => [
				'title' => 'Connect with Drip',
				'type' => 'oauth',
				'token_key' => 'code',
				// http://developer.drip.com/#oauth
				'url' => 'https://www.getdrip.com/oauth/authorize?'.http_build_query(
						[
							'response_type' => 'code',
							'client_id' => $this->get_key(),
							'redirect_uri' => URL::domain().'/api/integration/complete_oauth/Drip',
						]
					),
				'size' => '600x600',
				'rules' => [
					['not_empty'],
				],
			],
			'account_id' => [
				'type' => 'hidden',
				'std' => NULL,
			],
		];
	}

	/**
	 * Fetch meta data by integration credentials
	 * @link http://developer.drip.com/#list-all-accounts
	 * @link http://developer.drip.com/#list-all-campaigns
	 *
	 * @return $this|Integration_Driver
	 * @throws Exception
	 * @throws Integration_Exception
	 */
	public function fetch_meta()
	{
		$this->meta = [
			'campaigns' => [],
			'workflows' => [],
			'events' => [],
			'tags' => []
		];

		$meta_keys = [
			'tags' => 'tags',
			'campaigns' => 'campaigns',
			'workflows' => 'workflows',
			'events' => 'event_actions'
		];

		$this->provide_oauth_access();

		$access_token = Arr::path($this->credentials, 'oauth.access_token');

		// https://developer.drip.com/#list-all-accounts
		$r = Integration_Request::factory()
			->method('GET')
			->header('Accept-type', 'Application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/accounts')
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
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
			elseif ($r->code == 409)
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
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
		else
		{
			$accounts = $r->get('accounts');

			foreach ($accounts as $account)
			{
				Arr::set_path($this->credentials, 'account_id', $account['id']);
			}

			$account_id = $this->get_credentials('account_id', '');
		}

		foreach ($meta_keys as $key => $action)
		{
			// https://developer.drip.com
			$r = Integration_Request::factory()
				->method('GET')
				->header('Accept-type', 'Application/json')
				->header('Authorization', 'Bearer '.$access_token)
				->url($this->get_endpoint().'/v2/'.$account_id.'/'.$action)
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
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
				}
				elseif ($r->code == 409)
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
				}
				elseif ($r->code == 500)
				{
					throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS);
				}
				else
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
				}
			}
			else
			{
				if ($action == 'tags' OR $action == 'event_actions')
				{
					$this->meta[$key] = $r->get($action);
				}
				else
				{
					foreach ($r->get($action) as $value)
					{
						$this->meta[$key][$value['id']] = $value['name'];
					}
				}
			}

		}
		return $this;
	}

	public function describe_automations()
	{
		$campaigns = (array) $this->get_meta('campaigns', []);
		$tags = (array) $this->get_meta('tags', []);
		$workflows = (array) $this->get_meta('workflows', []);
		$events = (array) $this->get_meta('events', []);

		return [
			'add_campaign_subscriber' => [
				'title' => 'Subscribe to campaign',
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
			'remove_campaign_subscriber' => [
				'title' => 'Unsubscribe from campaign',
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
			'add_subscriber_tag' => [
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
			'remove_subscriber_tag' => [
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
			'add_workflow_subscriber' => [
				'title' => 'Add to workflow',
				'params_fields' => [
					'workflow_id' => [
						'title' => 'Workflow Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $workflows,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($workflows)]],
						],
					],
				],
			],
			'remove_workflow_subscriber' => [
				'title' => 'Remove from workflow',
				'params_fields' => [
					'workflow_id' => [
						'title' => 'Workflow Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $workflows,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($workflows)]],
						],
					],
				],
			],
			'record_event' => [
				'title' => 'Record an event',
				'params_fields' => [
					'event' => [
						'title' => 'Event Name',
						'description' => NULL,
						'type' => 'select',
						'options' => $events,
						'classes' => 'i-refreshable',
						'rules' => [
							['in_array', [':value', array_keys($events)]],
						],
					],
				],
			],
		];
	}


	/**
	 * Describe Data Rules
	 *
	 * @return array
	 */
	public static function describe_data_rules()
	{
		return [
			'text' => [
				['max_length', [':value', 100], 'The maximum length of field\'s content should not exceed 100 characters.'],
			],
			'hidden' => [
				['max_length', [':value', 100], 'The maximum length of field\'s content should not exceed 100 characters.'],
			],
		];
	}

	/**
	 * Translate person data from standard convertful to integration format
	 *
	 * @param array $subscriber_data Person data in standard convertful format
	 * @param bool $create_missing_fields
	 * @return array Integration-specified person data format
	 */
	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$data = $subscriber_data;

		$int_data = [];

		if (isset($data['meta']))
		{
			foreach ($data['meta'] as $key => $value)
			{
				$int_data['custom_fields'][$key] = $value;
			}
		}

		foreach ($data as $key => $value)
		{
			if (key_exists($key, $this->standard_properties))
			{
				$int_data['custom_fields'][$key] = $value;
			}
		}

		// Automatically fill in the required values
		if (isset($int_data['custom_fields']))
		{
			if (empty($int_data['custom_fields']['name']))
			{
				if ( ! empty($int_data['custom_fields']['first_name']) AND ! empty($int_data['custom_fields']['last_name']))
				{
					$int_data['custom_fields']['name'] = implode(' ', [
							$int_data['custom_fields']['first_name'],
							$int_data['custom_fields']['last_name']]
					);
				}
			}
		}

		// Automatically fill in the required values
		if (empty($int_data['custom_fields']['first_name']) AND empty($int_data['custom_fields']['last_name']))
		{
			if ( ! empty($int_data['custom_fields']['name']))
			{
				$fields = explode(' ', $int_data['custom_fields']['name'], 2);

				if (count($fields) == 2)
				{
					list($int_data['custom_fields']['first_name'], $int_data['custom_fields']['last_name']) = $fields;
				}
				else
				{
					list($int_data['custom_fields']['first_name']) = $fields;
				}
			}
		}

		// Clear custom fields array keys according to the required format
		if (isset($int_data['custom_fields']))
		{
			$custom_fields = [];

			foreach ($int_data['custom_fields'] as $key => $value)
			{
				$key = Text::translit($key);
				$key = UTF8::strtolower($key);
				$key = Inflector::underscore($key);

				$key = preg_replace('/[^a-z0-9_]/', '', $key);

				if (isset($custom_fields[$key]))
				{
					$i = 0;
					do
					{
						$i++;
					} while (isset($custom_fields[$key.$i]));

					$key = $key.$i;
				}

				$custom_fields[$key] = $value;
			}

			$int_data['custom_fields'] = $custom_fields;
		}

		return $int_data;
	}

	/**
	 * Translate person data from integration to standard convertful format
	 *
	 * @param array $int_data Integration-specified person data format
	 * @return array Person data in standard convertful format
	 */
	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$data = $int_data;

		$subscriber_data = [];

		if (isset($data['custom_fields']))
		{
			foreach ($data['custom_fields'] as $key => $value)
			{
				if (key_exists($key, $this->standard_properties))
				{
					$subscriber_data[$key] = $value;
				}
				else
				{
					if (in_array($key, $this->standard_properties))
					{
						$subscriber_data[array_search($key, $this->standard_properties)] = $value;
					}
					else
					{
						$subscriber_data['meta'][$key] = $value;
					}
				}
			}
		}

		foreach ($data as $key => $value)
		{
			if (key_exists($key, $this->standard_properties))
			{
				$subscriber_data[$key] = $value;
			}
		}

		// Automatically fill in the required values
		if (empty($subscriber_data['name']))
		{
			if ( ! empty($subscriber_data['first_name']) AND ! empty($subscriber_data['last_name']))
			{
				$subscriber_data['name'] = implode(' ', [
						$subscriber_data['first_name'],
						$subscriber_data['last_name']]
				);
			}
		}

		// Automatically fill in the required values
		if (empty($subscriber_data['first_name']) AND empty($subscriber_data['last_name']))
		{
			if ( ! empty($subscriber_data['name']))
			{
				$fields = explode(' ', $subscriber_data['name'], 2);

				if (count($fields) == 2)
				{
					list($subscriber_data['first_name'], $subscriber_data['last_name']) = $fields;
				}
				else
				{
					list($subscriber_data['first_name']) = $fields;
				}
			}
		}

		$id = Arr::get($int_data, 'id');
		if ($id)
		{
			Arr::set_path($subscriber_data, '$integration.id', $id);
		}

		return $subscriber_data;
	}

	/**
	 * Get person by email
	 * @link https://developer.drip.com/?shell#fetch-a-subscriber
	 *
	 * @param string $email
	 * @return array|NULL
	 * @throws Exception
	 * @throws Integration_Exception
	 */
	public function get_subscriber($email)
	{
		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');
		$email = mb_strtolower($email);

		// Get person by email
		// @link https://developer.drip.com/?shell#fetch-a-subscriber
		$r = Integration_Request::factory()
			->method('GET')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-type', 'Application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/subscribers/'.$email)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
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
		}
		else
		{
			$subscriber_data = $this->translate_int_data_to_subscriber_data(current($r->get('subscribers')));
		}

		return $subscriber_data;
	}


	/**
	 * Create a person with given data
	 * @link https://developer.drip.com/?shell#create-or-update-a-subscriber
	 *
	 * @param string $email
	 * @param array $subscriber_data
	 * @throws Exception
	 * @throws Integration_Exception
	 */
	public function create_person($email, $subscriber_data)
	{
		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');
		$email = mb_strtolower($email);

		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);

		$int_data = [
			'subscribers' => [
				array_merge($int_data, [
					'new_email' => $email,
					'email' => $email
				]),
			]
		];

		// Create a person with given data
		// @link https://developer.drip.com/?shell#create-or-update-a-subscriber
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/subscribers')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Refresh Oauth Access Token
	 * @link https://developer.drip.com/?shell#oauth
	 *
	 * @return bool
	 */
	protected function oauth_refresh_token()
	{
		return TRUE;
	}

	/**
	 * Get Oauth Access Token
	 * @link https://developer.drip.com/?shell#oauth
	 *
	 * @throws Exception
	 * @throws Integration_Exception
	 * @throws Kohana_Exception
	 */
	protected function oauth_get_token()
	{
		$r = Integration_Request::factory()
			->method('POST')
			->header('Content-Type', 'application/x-www-form-urlencoded')
			->url('https://www.getdrip.com/oauth/token')
			->data([
				'response_type' => 'token',
				'client_id' => $this->get_key(),
				'client_secret' => $this->get_secret(),
				'code' => Arr::path($this->credentials, 'oauth.code'),
				'redirect_uri' => URL::domain().'/api/integration/complete_oauth/Drip',
				'grant_type' => 'authorization_code',
			])
			->log_to($this->requests_log)
			->execute();

		if ($r->is_successful())
		{
			$access_token = $r->get('access_token');

			if (empty($access_token))
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain access_token'));
			}

			if ( ! isset($this->credentials['oauth']))
			{
				$this->credentials['oauth'] = [];
			}

			$this->credentials['oauth']['expires_at'] = NULL;
			$this->credentials['oauth']['access_token'] = $access_token;
		}
		else
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
		}
	}

	/**
	 * Add subscriber to campaign
	 * @link https://developer.drip.com/?shell#subscribe-someone-to-a-campaign
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function add_campaign_subscriber($email, $params, $subscriber_data = [])
	{
		$campaign_id = Arr::get($params, 'campaign_id');
		if ( ! isset($campaign_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			$this->create_person($email, $subscriber_data);
		}

		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');

		$int_data['subscribers'][] = [
			'email' => $email
		];

		// Add subscriber to campaign
		// @link https://developer.drip.com/?shell#subscribe-someone-to-a-campaign
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/campaigns/'.$campaign_id.'/subscribers')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			elseif ($r->code === 422)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, '', $r->path('errors.0.message'));
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Remove subscriber from campaign
	 * @link https://developer.drip.com/?shell#remove-a-subscriber-from-one-or-all-campaigns
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_campaign_subscriber($email, $params)
	{
		$campaign_id = Arr::get($params, 'campaign_id');
		if ( ! isset($campaign_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			return;
		}

		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');

		// Remove subscriber from campaign
		// @link https://developer.drip.com/?shell#remove-a-subscriber-from-one-or-all-campaigns
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/subscribers/'.$email.'/remove')
			->data([
				'campaign_id' => $campaign_id
			])
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			elseif ($r->code === 422)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Add new tag to a subscriber
	 * @link https://developer.drip.com/?shell#apply-a-tag-to-a-subscriber
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function add_subscriber_tag($email, $params, $subscriber_data = [])
	{
		$tag_id = Arr::get($params, 'tag_id');
		if ( ! isset($tag_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			$this->create_person($email, $subscriber_data);
		}

		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');

		$int_data['tags'][] = [
			'email' => $email,
			'tag' => $this->meta['tags'][$tag_id]
		];

		// Add new tag to a subscriber
		// @link https://developer.drip.com/?shell#apply-a-tag-to-a-subscriber
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/tags')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			elseif ($r->code === 422)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Remove tag from a subscriber
	 * @link https://developer.drip.com/?shell#remove-a-tag-from-a-subscriber
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_subscriber_tag($email, $params)
	{
		$tag_id = Arr::get($params, 'tag_id');
		if ( ! isset($tag_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			return;
		}

		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');

		// Remove tag from a subscriber
		// @link https://developer.drip.com/?shell#remove-a-tag-from-a-subscriber
		$r = Integration_Request::factory()
			->method('DELETE')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/subscribers/'.$email.'/tags/'.$this->meta['tags'][$tag_id])
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			elseif ($r->code === 422)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS);
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Add subscriber on a workflow
	 * @link https://developer.drip.com/?shell#start-someone-on-a-workflow
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 * @throws Integration_Exception
	 */
	public function add_workflow_subscriber($email, $params, $subscriber_data = [])
	{
		$workflow_id = Arr::get($params, 'workflow_id');
		if ( ! isset($workflow_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			$this->create_person($email, $subscriber_data);
		}

		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');

		$int_data['subscribers'][] = [
			'email' => $email
		];

		// Add subscriber on a workflow
		// @link https://developer.drip.com/?shell#start-someone-on-a-workflow
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/workflows/'.$workflow_id.'/subscribers')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			elseif ($r->code === 422)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, '', $r->path('errors.0.message'));
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Remove subscriber from workflow
	 * @link https://developer.drip.com/?shell#remove-a-subscriber-from-a-workflow
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function remove_workflow_subscriber($email, $params)
	{
		$workflow_id = Arr::get($params, 'workflow_id');
		if ( ! isset($workflow_id))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$subscriber = $this->get_subscriber($email);
		if ($subscriber === NULL)
		{
			return;
		}

		$this->provide_oauth_access();

		$access_token = $this->get_credentials('oauth.access_token', '');
		$account_id = $this->get_credentials('account_id', '');

		// Remove subscriber from workflow
		// @link https://developer.drip.com/?shell#remove-a-subscriber-from-a-workflow
		$r = Integration_Request::factory()
			->method('DELETE')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/workflows/'.$workflow_id.'/subscribers/'.$email)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			elseif ($r->code === 422)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, '', $r->path('errors.0.message'));
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	/**
	 * Record an event subscriber
	 * @link https://developer.drip.com/?shell#events
	 * @param $email
	 * @param $params
	 * @throws Integration_Exception
	 */
	public function record_event($email, $params)
	{
		$event = Arr::get($params, 'event');
		if ( ! isset($event) OR empty($event))
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS);
		}

		$account_id = $this->get_credentials('account_id', '');
		$access_token = $this->get_credentials('oauth.access_token', '');

		$int_data['events'][] = [
			'email' => $email,
			'action' => $this->meta['events'][$event],
		];

		// Record an event subscriber
		// @link https://developer.drip.com/?shell#events
		$r = Integration_Request::factory()
			->method('POST')
			->curl([
				CURLOPT_CONNECTTIMEOUT_MS => 5000,
				CURLOPT_TIMEOUT_MS => 15000,
			])
			->header('Accept-type', 'Application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($this->get_endpoint().'/v2/'.$account_id.'/events')
			->data($int_data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
			}
			elseif ($r->code === 422)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, '', $r->path('errors.0.message'));
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}
}