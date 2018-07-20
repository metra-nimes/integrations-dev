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
class Integration_Driver_Drip extends Integration_OauthDriver implements Integration_Interface_BackendESP {

	protected static $company_name = 'Avenue 81, Inc.';
	protected static $company_address = '251 N. 1st Avenue, Suite 200, Minneapolis, MN 55401, USA';
	protected static $company_url = 'https://www.drip.com/';

	/**
     * Describe standard properties
     *
     * @var array
     */
    protected $standard_properties = array(
       // 'email' => 'email',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'name' => 'name',
        'site' => 'site',
        'company' => 'company',
        'phone' => 'phone',
    );

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
                'title' => 'Connect with Drip',
                'type' => 'oauth',
                'token_key' => 'code',
                // http://developer.drip.com/#oauth
                'url' => 'https://www.getdrip.com/oauth/authorize?'.http_build_query(
                        array(
                            'response_type' => 'code',
                            'client_id' => $this->get_key(),
                            'redirect_uri' => URL::domain().'/api/integration/complete_oauth/Drip',
                        )
                    ),
                'size' => '600x600',
                'rules' => array(
                    array('not_empty'),
                ),
            ),
        );
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
        $this->provide_oauth_access();

        $access_token = Arr::path($this->credentials, 'oauth.access_token');

        // http://developer.drip.com/#list-all-accounts
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
            $accounts = $r->get('accounts');

            foreach ($accounts as $account)
            {
                $this->meta['accounts'][$account['id']] = $account['name'];
            }
        }

        foreach ($this->meta['accounts'] as $id => $name)
        {
            // http://developer.drip.com/#list-all-campaigns
            $r = Integration_Request::factory()
                ->method('GET')
                ->header('Accept-type', 'Application/json')
                ->header('Authorization', 'Bearer '.$access_token)
                ->url($this->get_endpoint().'/v2/'.$id.'/campaigns')
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
                $campaigns = $r->get('campaigns');

                foreach ($campaigns as $key => $campaign)
                {
                    $this->meta['campaigns'][$campaign['id']] = array('account_id' => $id, 'name' => $campaign['name'],);
                }
            }

        }

        return $this;
    }

    /**
     * Describe COF fieldset config to render widget integration parameters
     *
     * @return array COF fieldset config for params form, so a user could connect specific optin with integration
     */
    public function describe_params_fields()
    {
        $accounts = Arr::get($this->meta, 'accounts', array());

        $campaigns = Arr::get($this->meta, 'campaigns', array());
        $grouped_campaigns = [];

        foreach ($campaigns as $campaign_id => $campaign)
        {
			Arr::set_path($grouped_campaigns, Arr::get($campaign, 'account_id').'.'.$campaign_id, Arr::get($campaign, 'name'));
        }

        return array(
            'warning' => array(
                'type' => 'alert',
                'text' => 'The maximum length of field\'s content should not exceed 100 characters.',
                'closable' => TRUE,
            ),
            'accountId' => array(
                'title' => 'Account',
                'description' => NULL,
                'type' => 'select',
                'options' => $accounts,
                'classes' => 'i-refreshable',
            ),
            'listId' => array(
                'title' => 'Campaign',
                'description' => NULL,
                'type' => 'select',
                'options' => $grouped_campaigns,
	            'options_labels' => $accounts,
                'classes' => 'i-refreshable',
            ),
	        'tags' => array(
		        'title' => 'Tags to Mark with',
		        'type' => 'select2',
		        'description' => NULL,
		        'options' => $this->get_meta('tags', array()),
		        'multiple' => TRUE,
		        'tokenize' => TRUE,
		        'rules' => array(
			        array(function ($tags) {
				        if (is_null($tags))
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
     * Describe Data Rules
     *
     * @return array
     */
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

    /**
     * Describe validation parameters
     *
     * @param array $params
     * @throws Integration_Exception
     */
    public function validate_params(array $params)
    {
        $accountId = Arr::get($params, 'accountId', '');

        $available_accounts = Arr::get($this->meta, 'accounts', array());

        if ( ! is_array($available_accounts))
        {
            $available_accounts = array();
        }

        if ( ! empty($accountId) AND ! isset($available_accounts[$accountId]))
        {
            throw new Integration_Exception(INT_E_WRONG_PARAMS, 'accountId', 'Account not found');
        }

        $listId = Arr::get($params, 'listId', '');

        $available_lists = Arr::get($this->meta, 'campaigns', array());

        if ( ! is_array($available_lists))
        {
            $available_lists = array();
        }

        if ( ! empty($listId) AND ! isset($available_lists[$listId]))
        {
            throw new Integration_Exception(INT_E_WRONG_PARAMS, 'listId', 'Campaign not found');
        }
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

        $int_data = array();

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
            if (empty($int_data['custom_fields']['name'])) {
                if ( ! empty($int_data['custom_fields']['first_name']) AND ! empty($int_data['custom_fields']['last_name'])) {
                    $int_data['custom_fields']['name'] = implode(' ', array(
                            $int_data['custom_fields']['first_name'],
                            $int_data['custom_fields']['last_name'])
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
            $custom_fields = array();

            foreach ($int_data['custom_fields'] as $key => $value)
            {
                $key = Text::translit($key);
                $key = UTF8::strtolower($key);
                $key = Inflector::underscore($key);

                $key = preg_replace ('/[^a-z0-9_]/','', $key);

                if (isset($custom_fields[$key]))
                {
                    $i = 0;
                    do {
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
        $data = $int_data['subscribers']['0'];

        $subscriber_data = array();

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
                    $subscriber_data['meta'][$key] = $value;
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
        if (empty($subscriber_data['name'])) {
            if ( ! empty($subscriber_data['first_name']) AND ! empty($subscriber_data['last_name'])) {
                $subscriber_data['name'] = implode(' ', array(
                        $subscriber_data['first_name'],
                        $subscriber_data['last_name'])
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

        return $subscriber_data;
    }

    /**
     * Get person by email
     * @link http://developer.drip.com/#list-all-of-a-subscriber-39-s-campaign-subscriptions
     *
     * @param string $email
     * @return array|NULL
     * @throws Exception
     * @throws Integration_Exception
     */
    public function get_person($email)
    {
        $this->provide_oauth_access();

        $access_token = $this->get_credentials('oauth.access_token', '');
        $accountId = $this->get_params('accountId', NULL);
        $listId = $this->get_params('listId', NULL);
        $email = strtolower($email);

        $r = Integration_Request::factory()
            ->method('GET')
            ->header('Accept-type', 'Application/json')
            ->header('Authorization', 'Bearer '.$access_token)
            ->url($this->get_endpoint().'/v2/'.$accountId.'/subscribers/'.$email.'/campaign_subscriptions')
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
            $subscriptions = $r->get('campaign_subscriptions');

            foreach ($subscriptions as $key => $subscription)
            {
                if ($listId == $subscription['campaign_id'])
                {
                    $r = Integration_Request::factory()
                        ->method('GET')
                        ->curl(array(
                            CURLOPT_CONNECTTIMEOUT_MS => 5000,
                            CURLOPT_TIMEOUT_MS => 15000,
                        ))
                        ->header('Accept-Type', 'application/json')
                        ->header('Authorization', 'Bearer '.$access_token)
                        ->url($this->get_endpoint().'/v2/'.$accountId.'/subscribers/'.$email)
                        ->log_to($this->requests_log)
                        ->execute();

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

                    return $this->translate_int_data_to_subscriber_data($r->data);
                }
            }
        }

        return NULL;
    }


    /**
     * Create a person with given data
     * @link http://developer.drip.com/#subscribe-someone-to-a-campaign
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
        $accountId = $this->get_params('accountId', NULL);
        $listId = $this->get_params('listId', NULL);
        $email = strtolower($email);

        $int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);

        $int_data = array(
            'subscribers' => array(
                array_merge($int_data, array(
                    'email' => $email,
                    'tags' => Arr::get($this->params, 'tags'),
                )),
            )
        );

        $r = Integration_Request::factory()
            ->method('POST')
            ->curl(array(
                CURLOPT_CONNECTTIMEOUT_MS => 5000,
                CURLOPT_TIMEOUT_MS => 15000,
            ))
            ->header('Accept-Type', 'application/json')
            ->header('Content-Type', 'application/json')
            ->header('Authorization', 'Bearer '.$access_token)
            ->url($this->get_endpoint().'/v2/'.$accountId.'/campaigns/'.$listId.'/subscribers')
            ->data($int_data)
            ->log_to($this->requests_log)
            ->execute();

        if ( ! $r->is_successful())
        {
        	var_dump($r->body);
        	if ($r->code === 422 AND $r->path('errors.0.message') === 'Email is already subscribed')
	        {
		        throw new Integration_Exception(INT_E_EMAIL_DUPLICATE);
	        }
	        elseif ($r->code === 422 AND $r->path('errors.0.message') === 'Campaign is not currently active')
	        {
		        throw new Integration_Exception(INT_E_WRONG_PARAMS, 'listId');
	        }
            throw new Integration_Exception(INT_E_WRONG_REQUEST);
        }
    }

    /**
     * Update a person with given data
     *
     * @param string $email
     * @param array $subscriber_data
     * @throws Exception
     * @throws Integration_Exception
     */
    public function update_person($email, $subscriber_data)
    {
        return $this->create_person($email, $subscriber_data);
    }

    /**
     * Refresh Oauth Access Token
     * @link http://developer.drip.com/#oauth
     *
     * @return bool
     */
    protected function oauth_refresh_token()
    {
        return TRUE;
    }

    /**
     * Get Oauth Access Token
     * @link http://developer.drip.com/#oauth
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
            ->data(array(
                'response_type' => 'token',
                'client_id' => $this->get_key(),
                'client_secret' => $this->get_secret(),
                'code' => Arr::path($this->credentials, 'oauth.code'),
                'redirect_uri' => URL::domain().'/api/integration/complete_oauth/Drip',
                'grant_type' => 'authorization_code',
            ))
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
                $this->credentials['oauth'] = array();
            }

            $this->credentials['oauth']['expires_at'] = NULL;
            $this->credentials['oauth']['access_token'] = $access_token;
        }
        else
        {
            throw new Integration_Exception(INT_E_WRONG_CREDENTIALS);
        }
    }
}