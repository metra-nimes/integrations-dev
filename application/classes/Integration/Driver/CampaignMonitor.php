<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * CampaignMonitor Integration
 * @link https://www.campaignmonitor.com/api/getting-started/
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_CampaignMonitor extends Integration_OauthDriver implements Integration_Interface_BackendESP {

	use Integration_Trait_BackendESP;

	protected static $company_name = 'Campaign Monitor Pty Ltd';
	protected static $company_address = 'Campaign Monitor, Level 38, 201 Elizabeth Street, Sydney NSW 2000, Australia';
	protected static $company_url = 'https://www.campaignmonitor.com/';

	private $standard_merge_fields = array(
		'name' => 'Name',
		'first_name' => 'CustomFields.first_name',
		'last_name' => 'CustomFields.last_name',
		'company' => 'CustomFields.company',
		'phone' => 'CustomFields.phone',
		'site' => 'CustomFields.site',
	);

	public function get_endpoint()
	{
		return 'https://api.createsend.com/api/v3.1';
	}

	protected function get_key()
	{
		return Arr::path(Kohana::$config->load('integrations_oauth')->as_array(), 'CampaignMonitor.client_id');
	}

	protected function get_secret()
	{
		return Arr::path(Kohana::$config->load('integrations_oauth')->as_array(), 'CampaignMonitor.client_secret');
	}

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
				'title' => 'Connect with CampaignMonitor',
				'type' => 'oauth',
				// The name of array key that will contain token
				'token_key' => 'code',
				// https://developer.constantcontact.com/docs/authentication/authentication.html
				'url' => 'https://api.createsend.com/oauth'.
					'?type=web_server'.
					'&client_id='.urlencode($this->get_key()).
					'&redirect_uri='.URL::domain().'/api/integration/complete_oauth/CampaignMonitor'.
					'&scope='.urlencode('ManageLists,ImportSubscribers'). //TODO https://www.campaignmonitor.com/api/getting-started/#authenticating-with-oauth
					'&state=xyz',
				'size' => '600x650',
				'rules' => array(
					array('not_empty'),
				),
			),
		);
	}

	public function fetch_meta()
	{
		$this->meta = array(
			'lists' => array(),
			'merge_fields' => array(),
		);

		$this->provide_oauth_access();
		$this->oauth_update_client_info();
		$client_id = $this->get_meta('clients.0.ClientID', '');

		// Get lists
		// https://www.campaignmonitor.com/api/clients/#subscriber_lists
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/clients/'.$client_id.'/lists.json')
			->header('Authorization', 'Bearer '.$this->get_credentials('oauth.access_token', ''))
			->header('Accept-type', 'Application/json')
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 404)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
			}
			elseif ($r->code == 409)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
			}
		}

		$lists = (array) $r->data;
		foreach ($lists as $list)
		{
			$list_id = Arr::get($list, 'ListID', '');
			$list_name = Arr::get($list, 'Name', '');
			$this->meta['lists'][$list_id] = $list_name;

			// Get custom fields
			// https://www.campaignmonitor.com/api/lists/#list_custom_fields
			$r = Integration_Request::factory()
				->method('GET')
				->url($this->get_endpoint().'/lists/'.$list_id.'/customfields.json')
				->header('Authorization', 'Bearer '.$this->get_credentials('oauth.access_token', ''))
				->header('Accept-type', 'Application/json')
				->log_to($this->requests_log)
				->execute();
			if ( ! $r->is_successful())
			{
				if ($r->code == 401 OR $r->code == 403)
				{
					throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
				}
				elseif ($r->code == 404)
				{
					throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
				}
				elseif ($r->code == 409)
				{
					throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
				}
				elseif ($r->code == 500)
				{
					throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
				}
				else
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
				}
			}
			sleep(1);

			$this->meta['merge_fields'][$list_id] = array();
			foreach ($r->data as $custom_field)
			{
				if (in_array(Arr::get($custom_field, 'DataType', ''), array('Text', 'Number')))
				{
					$this->meta['merge_fields'][$list_id][trim(Arr::get($custom_field, 'Key', ''), '[]')] = Arr::get($custom_field, 'FieldName', '');
				}
			}
		}

		return $this;
	}

	public function describe_params_fields()
	{
		$lists = (array) $this->get_meta('lists', array());

		return array(
			'clients' => array(
				'type' => 'hidden',
			),
			'list' => array(
				'title' => 'List',
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

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = array(
			'CustomFields' => array(),
		);

		foreach ($subscriber_data as $subscriber_data_key => $subscriber_data_val)
		{
			switch (TRUE)
			{
				case array_key_exists($subscriber_data_key, $this->standard_merge_fields):
					Arr::set_path($int_data, $this->standard_merge_fields[$subscriber_data_key], $subscriber_data_val);
					break;
				case $subscriber_data_key === 'meta':
					$int_data['CustomFields'] = array_merge($int_data['CustomFields'], $subscriber_data_val);
					break;
			}
		}

		$name = Arr::get($subscriber_data, 'name');
		$first_name = Arr::path($subscriber_data, 'CustomFields.first_name');
		$last_name = Arr::path($subscriber_data, 'CustomFields.last_name');
		if ($name AND is_null($first_name) AND is_null($last_name))
		{
			//split to first+last
			list($first_name, $last_name) = explode(' ', $name.' ');
			if (isset($first_name))
			{
				$int_data['CustomFields']['first_name'] = trim($first_name);
			}

			if (isset($last_name))
			{
				$int_data['CustomFields']['last_name'] = trim($last_name);
			}
		}
		elseif (($first_name || $last_name) AND is_null($name))
		{
			//split to first+last
			$int_data['Name'] = trim($first_name.' '.$last_name);
		}

		if ( ! empty($int_data['CustomFields']))
		{
			foreach ($int_data['CustomFields'] as $custom_key => &$custom_val)
			{
				$create_missing_fields AND ($custom_key = $this->create_custom_field_if_not_exists($custom_key));

				$custom_val = array(
					'Key' => '['.$custom_key.']',
					'Value' => $custom_val,
				);
			}

			$int_data['CustomFields'] = array_values($int_data['CustomFields']);
		}
		else
		{
			unset($int_data['CustomFields']);
		}

		return $int_data;
	}

	/**
	 * @param $data_key
	 * @return NULL|string field name
	 * @throws Integration_Exception
	 */
	protected function create_custom_field_if_not_exists($data_key)
	{
		$list_id = Arr::get($this->params, 'list', '');
		if (empty($list_id))
		{
			return NULL;
		}
		$existing_tags = $this->get_meta('merge_fields.'.$list_id);

		if ($new_field_name = array_search($data_key, $existing_tags))
		{
			return $new_field_name;
		}
		else
		{
			// Create new custom_field
			// https://www.campaignmonitor.com/api/lists/#creating-custom-field
			$r = Integration_Request::factory()
				->method('POST')
				->url($this->get_endpoint().'/lists/'.$list_id.'/customfields.json')
				->data(array(
					"FieldName" => $data_key,
					"DataType" => "Text",
					"VisibleInPreferenceCenter" => TRUE,
				))
				->header('Authorization', 'Bearer '.$this->get_credentials('oauth.access_token', ''))
				->header('Accept-type', 'Application/json')
				->header('Content-Type', 'application/json')
				->log_to($this->requests_log)
				->execute();

			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 404)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
			}
			elseif ($r->code == 409)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
			}

			$new_field_name = '';
			if (is_array($r->data) AND Arr::get($r->data, 'Code', 0) == 255)
			{
				$new_field_name = preg_replace('/[^0-9a-z]+/i', '', $data_key);
			}
			elseif (is_string($r->data))
			{
				$new_field_name = trim($r->data, '[]');
			}

			if ( ! empty($new_field_name))
			{
				Arr::set_path($this->meta, 'merge_fields.'.$list_id.'.'.$new_field_name, $data_key);
			}

			return $new_field_name;
		}
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => array(),
		);
		$list_id = Arr::get($this->params, 'list', '');
		$tags_names = $this->get_meta('merge_fields.'.$list_id);
		foreach (Arr::get($int_data, 'CustomFields', array()) as $custom_field)
		{
			$mf_tag = Arr::get($custom_field, 'Key', NULL);
			$value = Arr::get($custom_field, 'Value', NULL);
			if (empty($value))
			{
				continue;
			}
			if ($f_type = array_search('CustomFields.'.$mf_tag, $this->standard_merge_fields))
			{
				// Standard type
				Arr::set_path($subscriber_data, $f_type, $value);
				continue;
			}
			else
			{
				// Custom type
				$find_key = array_search($mf_tag, $tags_names);
				if ( ! isset($tags_names[$mf_tag]) AND empty($find_key))
				{
					$f_name = $mf_tag;
				}
				else
				{
					$f_name = ! empty($find_key) ? $tags_names[$find_key] : $tags_names[$mf_tag];
				}
				$subscriber_data['meta'][$f_name] = $value;
			}
		}
		unset($int_data['CustomFields']);

		foreach ($int_data as $key => $value)
		{
			if ( ! empty($value) AND ($f_type = array_search($key, $this->standard_merge_fields, TRUE)))
			{
				// Standard type
				Arr::set_path($subscriber_data, $f_type, $value);
			}
		}


		$name = Arr::path($subscriber_data, 'name');
		$first_name = Arr::path($subscriber_data, 'first_name');
		$last_name = Arr::path($subscriber_data, 'last_name');
		if ($name AND is_null($first_name) AND is_null($last_name))
		{
			// IF isset name, set first_name and last name
			list($f_name, $l_name) = explode(' ', $name.' ', 2);
			if ($f_name)
			{
				$subscriber_data['first_name'] = trim($f_name);
			}
			if ($l_name)
			{
				$subscriber_data['last_name'] = trim($l_name);
			}
		}
		else if (is_null($name) AND ($first_name OR $last_name))
		{
			// IF isset first or last name, set name
			$subscriber_data['name'] = trim($first_name.' '.$last_name);
		}

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}

		return $subscriber_data;
	}

	protected $pending_timeout = 10 * Date::MINUTE;

	public function get_person($email, $need_translate = TRUE)
	{
		$this->provide_oauth_access();

		$oauth_token = $this->get_credentials('oauth.access_token', '');
		$listId = $this->get_params('list', '');

		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/subscribers/'.$listId.'.json')
			->header('Authorization', 'Bearer '.$oauth_token)
			->header('Accept-type', 'Application/json')
			->data(array(
				'email' => $email,
			))
			->log_to($this->requests_log)
			->execute();

		if ($r->code == 401 OR $r->code == 403)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
		}
		elseif ($r->code == 404)
		{
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
		}
		elseif ($r->code == 409)
		{
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
		}
		elseif ($r->code == 500)
		{
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR, 'api_key');
		}

		if ( ! $r->is_successful() AND $r->code == 400 AND $r->get('Code') == 203)
		{
			//Not found
			return NULL;
		}

		// Considering a unconfirmed person subscribed if it was added recently
		$is_subscribed = ( ! $need_translate OR ($r->get('State') === 'Active' OR ($r->get('State') === 'Unconfirmed' AND (time() - strtotime($r->get('Date'))) < $this->pending_timeout)));
		if ( ! $is_subscribed)
		{
			return NULL;
		}

		return $need_translate ? $this->translate_int_data_to_subscriber_data($r->data) : $r->data;
	}


	public function create_person($email, $subscriber_data)
	{
		$this->provide_oauth_access();

		$oauth_token = $this->get_credentials('oauth.access_token', '');
		$list_id = $this->get_params('list', '');
		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$int_data['EmailAddress'] = $email;

		$duplicate_person = FALSE;

		// Checking email subscription in selected list
		$person = $this->get_person($email, FALSE);
		if ( ! is_null($person))
		{
			switch (Arr::get($person, 'State', ''))
			{
				case 'Active':
					// Duplicate email
					$duplicate_person = TRUE;
					break;
				case 'Deleted':
				case 'Unsubscribed':
					// Forcing bounced / unsubscribed to restore subscription
					$int_data['Resubscribe'] = TRUE;
					$int_data['RestartSubscriptionBasedAutoresponders'] = TRUE;
					break;
			}
		}

		// Create new subscriber
		// https://www.campaignmonitor.com/api/subscribers/#adding_a_subscriber
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/subscribers/'.$list_id.'.json')
			->data($int_data)
			->header('Authorization', 'Bearer '.$oauth_token)
			->header('Accept-type', 'Application/json')
			->header('Content-Type', 'application/json')
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 1)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail looks fake or invalid');
			}
			elseif ($r->code == 201)
			{
				throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email');
			}
			elseif ($r->code == 205)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Email Address exists in deleted list. Subscriber is not added.');
			}
			elseif ($r->code == 206)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Email Address exists in unsubscribed list. Subscriber is not added.');
			}
			elseif ($r->code == 207)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Email Address exists in bounced list. Subscriber is not added.');
			}
			elseif ($r->code == 208)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Email Address exists in unconfirmed list. Subscriber is not added.');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
			}
			elseif ($code = Arr::get($r->data, 'code') AND $code == 204)
			{
				throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email', 'Email Address exists in suppression list. Subscriber is not added.');
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}

		if ($duplicate_person)
		{
			throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email');
		}
	}

	public function update_person($email, $subscriber_data)
	{
		$this->provide_oauth_access();

		$list_id = $this->get_params('list', '');
		$int_data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);

		$int_data["EmailAddress"] = $email;
		$int_data["Resubscribe"] = TRUE;
		$int_data["RestartSubscriptionBasedAutoresponders"] = TRUE;

		//https://www.campaignmonitor.com/api/subscribers/#updating-a-subscriber
		$r = Integration_Request::factory()
			->method('PUT')
			->url($this->get_endpoint().'/subscribers/'.$list_id.'.json?email='.urlencode($email))
			->data($int_data)
			->header('Authorization', 'Bearer '.$this->get_credentials('oauth.access_token', ''))
			->header('Accept-Type', 'application/json')
			->header('Content-Type', 'application/json')
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 1)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail looks fake or invalid');
			}
			elseif ($r->code == 201)
			{
				throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email');
			}
			elseif ($r->code == 203)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Email Address does not belong to the list. Subscriber not updated.');
			}
			elseif ($r->code == 211)
			{
				throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'New Email Address was invalid. Subscriber not updated.');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
	}

	protected function oauth_refresh_token()
	{
		if (Arr::path($this->credentials, 'oauth.refresh_token', NULL) == NULL)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth', 'refresh_token not found');
		}
		// Refresh access token
		// http://apidocs.getresponse.com/v3/oauth
		$r = Integration_Request::factory()
			->method('POST')
			->http_basic_auth($this->get_key(), $this->get_secret())
			->url('https://api.createsend.com/oauth/token')
			->data(array(
				'grant_type' => 'refresh_token',
				'refresh_token' => Arr::path($this->credentials, 'oauth.refresh_token'),
			))
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			//TODO: Errors during OAuth exchange
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth');
		}
		$refresh_token = $r->get('refresh_token');
		if (empty($refresh_token))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain refresh_token'));
		}
		$access_token = $r->get('access_token');
		if (empty($access_token))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain access_token'));
		}
		$expires_in = $r->get('expires_in');
		if (empty($expires_in))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain expires_in'));
		}
		$this->credentials['oauth']['expires_at'] = time() + $expires_in - 5;
		$this->credentials['oauth']['refresh_token'] = $refresh_token;
		$this->credentials['oauth']['access_token'] = $access_token;

		$this->oauth_update_client_info();
	}

	protected function oauth_get_token()
	{
		// Get access token
		// https://www.campaignmonitor.com/api/getting-started/#authenticating-with-oauth
		$r = Integration_Request::factory()
			->method('POST')
			->url('https://api.createsend.com/oauth/token')
			->data(array(
				'grant_type' => 'authorization_code',
				'client_id' => $this->get_key(),
				'client_secret' => $this->get_secret(),
				'code' => Arr::path($this->credentials, 'oauth.code'),
				'redirect_uri' => URL::domain().'/api/integration/complete_oauth/CampaignMonitor',
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			//TODO: Errors during OAuth exchange
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth');
		}
		$refresh_token = $r->get('refresh_token');
		if (empty($refresh_token))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain refresh_token'));
		}
		$access_token = $r->get('access_token');
		if (empty($access_token))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain access_token'));
		}
		$expires_in = $r->get('expires_in');
		if (empty($expires_in))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'oAuth authorization request doesn\'t contain expires_in'));
		}
		$this->credentials['oauth']['expires_at'] = time() + $expires_in - 60;
		$this->credentials['oauth']['refresh_token'] = $refresh_token;
		$this->credentials['oauth']['access_token'] = $access_token;

		$this->oauth_update_client_info();
	}

	private function oauth_update_client_info()
	{
		// Get access token
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/clients.json')
			->header('Authorization', 'Bearer '.$this->get_credentials('oauth.access_token', ''))
			->header('Accept-type', 'Application/json')
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			//TODO: Errors during OAuth exchange
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'oauth');
		}

		$clients = $r->data;
		if (empty($clients))
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'oauth', $r->get('error_description', 'Clients list is empty'));
		}
		$this->meta['clients'] = $clients;
	}

}