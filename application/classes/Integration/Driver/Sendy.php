<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Sendy Integration
 * @link https://sendy.co/api
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_Sendy extends Integration_Driver implements Integration_Interface_BackendESP {

	use Integration_Trait_BackendESP;

	public static function get_company_info()
	{
		return NULL;
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
			'url' => array(
				'title' => 'Sendy Resource URL',
				'description' => 'Absolute URL to your Sendy app: https://sendy.yourdomain.com',
				'type' => 'text',
				'rules' => array(
					array('regex', array(':value', '/^https?\:\/\/\S+$/')),
					array('not_empty'),
				),
			),
			'api_key' => array(
				'title' => 'Account API Key',
				'description' => '<a href="/docs/integrations/sendy/#step-2-get-your-sendy-account-api-key-and-list-id" target="_blank">Read where to obtain this key</a>',
				'type' => 'key',
				'rules' => array(
					array('regex', array(':value', '/^\S{10,}/i')),
					array('not_empty'),
				),
			),
			'list_id' => array(
				'title' => 'List ID',
				'description' => '<a href="/docs/integrations/sendy/#step-2-get-your-sendy-account-api-key-and-list-id" target="_blank">Read where to obtain List ID</a>',
				'type' => 'key',
				'rules' => array(
					array('not_empty'),
				),
			),
			'submit' => array(
				'title' => 'Connect with Sendy',
				'action' => 'connect',
				'type' => 'submit',
			),
		);
	}

	public function filter_credentials(array $credentials)
	{
		$credentials = parent::filter_credentials($credentials);
		$credentials['url'] = rtrim(Arr::get($credentials, 'url', ''), '\/');

		return $credentials;
	}


	public function get_name()
	{
		return $this->get_credentials('name', '');
	}

	public function get_endpoint()
	{
		return $this->get_credentials('url', '');
	}

	public function fetch_meta()
	{
		// Test connection
		// https://sendy.co/api
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/api/subscribers/active-subscriber-count.php')
			->data(array(
				'api_key' => $this->get_credentials('api_key', ''),
				'list_id' => $this->get_credentials('list_id', ''),
			))
			->log_to($this->requests_log)
			->execute();

		if (preg_match('/^\d+$/', $r->body))
		{
			$this->meta = array();

			return $this;
		}

		$this->verify_response($r);
		throw new Integration_Exception(INT_E_WRONG_REQUEST);
	}

	public function describe_params_fields()
	{
		return array(
			'warning' => array(
				'type' => 'alert',
				'text' => 'Sendy only supports two form fields that match default list data: "Full name" and "Email". Also note, that maximum length of field\'s content should not exceed 100 characters.',
				'closable' => TRUE,
				//'classes' => '',
			),
		);
	}

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


	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		if ($meta = Arr::get($subscriber_data, 'meta', NULL))
		{
			unset($subscriber_data['meta']);
			$subscriber_data = array_merge($meta, $subscriber_data);
		}

		if ($name = Arr::get($subscriber_data, 'name', NULL) AND is_null(Arr::get($subscriber_data, 'first_name', NULL)) AND is_null(Arr::get($subscriber_data, 'last_name', NULL)))
		{
			list($first_name, $last_name) = explode(' ', $name.' ', 2);
			$subscriber_data['first_name'] = $first_name;
			if ( ! empty($last_name))
			{
				$subscriber_data['last_name'] = trim($last_name);
			}
		}

		if (Arr::get($subscriber_data, 'gdpr'))
		{
			$subscriber_data['gdpr'] = true;
		}

		return $subscriber_data;
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		return $int_data;
	}

	public function get_person($email, $need_translate = TRUE)
	{
		// View a single subscriber
		// https://sendy.co/api
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/api/subscribers/subscription-status.php')
			->data(array(
				'api_key' => $this->get_credentials('api_key'),
				'list_id' => $this->get_credentials('list_id'),
				'email' => strtolower($email),
			))
			->log_to($this->requests_log)
			->execute();

		// Handle the results.
		switch ($r->body)
		{
			case 'Subscribed!':
			case 'Subscribed':
				return array();
				break;
			case 'Unsubscribed':
			case 'Bounced':
			case 'Soft bounced':
			case 'Complained':
			case 'Unconfirmed':
			default:
				return NULL;
				break;
		}
	}

	public function create_person($email, $subscriber_data)
	{
		$data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$data['email'] = strtolower($email);
		$data['list'] = $this->get_credentials('list_id');
		$data['boolean'] = 'true';

		// Add subscriber
		// https://sendy.co/api
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/subscribe')
			->data($data)
			->log_to($this->requests_log)
			->execute();

		if ($r->body === 'Already subscribed.')
		{
			throw new Integration_Exception(INT_E_EMAIL_DUPLICATE);
		}
		elseif ($r->code == 404)
		{
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
		}
		elseif ($r->body === 'Invalid email address.')
		{
			throw new Integration_Exception(INT_E_WRONG_DATA, 'email');
		}
		elseif (stripos($r->body, 'Can\'t connect to database') !== FALSE)
		{
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
		}
		elseif ($r->body != 1 AND $r->body !== 'Consent not given.')
		{
			if ($r->code === 508)
			{
				throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
			}

			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function update_person($email, $subscriber_data)
	{
		$data['email'] = strtolower($email);
		$data['list_id'] = $this->get_credentials('list_id');
		$data['api_key'] = $this->get_credentials('api_key');

		// Unsubscribe
		// https://sendy.co/api
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/api/subscribers/delete.php')
			->data($data)
			->log_to($this->requests_log)
			->execute();

		if ($r->code == 508)
		{
			// retry query
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
		}

		$this->verify_response($r);

		if ($r->body == 1 OR $r->body === 'Subscriber does not exist')
		{
			$this->create_person($email, $subscriber_data);
		}
	}

	protected function verify_response($r)
	{
		switch ($r->body)
		{
			// Trailing dots is important
			case 'Invalid API key':
			case 'API key not passed':
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', $r->body);
			case 'List ID not passed':
			case 'Invalid list ID.':
				throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list_id', $r->body);
			case 'No data passed':
			case 'Some fields are missing.':
			case 'Invalid email address.':
				throw new Integration_Exception(INT_E_WRONG_DATA, NULL, $r->body);
			case 'List does not exist':
				throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list_id', $r->body);
		}

		return TRUE;
	}


	/**
	 * @param array $other_integrations
	 * @throws Integration_Exception
	 */
	public function validate_if_unique(&$other_integrations)
	{
		foreach ($this->describe_credentials_fields() as $f_name => $field)
		{
			// Names and keys shouldn't duplicate
			if ($f_name === 'name')
			{
				foreach ($other_integrations as $other_integration)
				{
					if (Arr::get($this->credentials, $f_name) == Arr::get($other_integration->credentials, $f_name))
					{
						throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $f_name, 'You already used this name for some other integration');
					}
				}
			}
			// Keys fields shouldn't duplicate
			elseif ($field['type'] === 'key' AND $f_name === 'api_key')
			{
				foreach ($other_integrations as $other_integration)
				{
					if (
						Arr::get($this->credentials, $f_name) == Arr::get($other_integration->credentials, $f_name)
						AND Arr::get($this->credentials, 'list_id') == Arr::get($other_integration->credentials, 'list_id')
					)
					{
						throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, $f_name, sprintf('You already connected this %s', Arr::get($field, 'title')));
					}
				}
			}
		}
	}
}