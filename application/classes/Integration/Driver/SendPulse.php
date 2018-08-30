<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * SendPulse Integration
 * @link https://sendpulse.com/integrations/api
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_SendPulse extends Integration_OauthDriver implements Integration_Interface_BackendESP {

	use Integration_Trait_BackendESP;

	protected static $company_name = 'SendPulse Inc.';
	protected static $company_address = '119 West 24th Street, 4th Floor New York, NY 10011, USA';
	protected static $company_url = 'https://sendpulse.com/';

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
			'rest_id' => array(
				'title' => 'REST API ID',
				'type' => 'key',
				'description' => '<a href="/docs/integrations/sendpulse/#step-2-get-your-rest-api-id-and-rest-api-secret" target="_blank">Read where to obtain REST API ID</a>',
				'rules' => array(
					array('not_empty'),
				),
			),
			'rest_secret' => array(
				'title' => 'REST API Secret',
				'type' => 'key',
				'description' => '<a href="/docs/integrations/sendpulse/#step-2-get-your-rest-api-id-and-rest-api-secret" target="_blank">Read where to obtain REST API Secret</a>',
				'rules' => array(
					array('not_empty'),
				),
			),
			'submit' => array(
				'title' => 'Connect with SendPulse',
				'type' => 'submit',
				'action' => 'connect',
			),
		);
	}

	public function get_endpoint()
	{
		return 'https://api.sendpulse.com';
	}

	public function fetch_meta()
	{
		$token = $this->provide_oauth_access(TRUE);

		$this->meta = array(
			'addressbooks' => array(),
		);
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/addressbooks')
			->header('Authorization', 'Bearer '.$token)
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'rest_secret', 'Cannot authenticate to SendPulse API');
		}
		if (is_array($r->data))
		{
			foreach ($r->data as $addressbook)
			{
				$book_id = Arr::get($addressbook, 'id', 0);
				if ($book_id)
				{
					$this->meta['addressbooks'][$book_id] = Arr::get($addressbook, 'name', '');
				}
			}
		}

		return $this;
	}

	public function describe_params_fields()
	{
		$address_books = $this->get_meta('addressbooks', array());

		return array(
			'warning' => array(
				'type' => 'alert',
				'text' => 'The custom field\'s name can contain any symbols except the colon and space.',
				'closable' => TRUE,
				//'classes' => '',
			),
			'addressbook' => array(
				'title' => 'Contact List',
				'type' => 'select',
				'description' => 'Emails will be added to this list',
				'options' => $address_books,
				'classes' => 'i-refreshable',
				'rules' => array(
					array('in_array', array(':value', array_keys($address_books))),
					array('not_empty'),
				),
			),
		);
	}

	public static function describe_data_rules()
	{
		return array(
			'text' => array(
				array('regex', array(':field', '~[^: ]+~i'), 'The custom field\'s name can contain any symbols except the colon  and space.'),
			),
			'hidden' => array(
				array('regex', array(':field', '~[^: ]+~i'), 'The custom field\'s name can contain any symbols except the colon and space.'),
			),
		);
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = array(
		'first_name' => 'First name',
		'last_name' => 'Last name',
		'name' => 'Name',
		'phone' => 'Phone',
		'company' => 'Company',
		'site' => 'Site',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = array_merge(
			Arr::get($subscriber_data, 'meta', array()),
			$subscriber_data
		);

		unset($int_data['meta']);

		// translate standard merge fields
		foreach ($int_data as $key => $v)
		{
			unset ($int_data[$key]);
			$new_key = Arr::get($this->standard_merge_fields, $key, $key);
			$new_key = str_replace(':', '', $new_key);
			$v = htmlentities($v);
			$int_data[$new_key] = empty($v) ? '—' : $v;
		}

		return ! (empty($int_data)) ? array(
			'variables' => $int_data,
		) : array();
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => array(),
		);
		foreach (Arr::get($int_data, 'variables', array()) as $variable)
		{
			$field_key = Arr::get($variable, 'name', NULL);
			$field_value = Arr::get($variable, 'value', NULL);
			if ( ! empty($field_key) AND $field_value !== '—')
			{
				if ($standard_field_key = array_search($field_key, $this->standard_merge_fields))
				{
					$subscriber_data[$standard_field_key] = $field_value;
				}
				else
				{
					$subscriber_data['meta'][$field_key] = $field_value;
				}
			}
		}

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}

		return $subscriber_data;
	}

	public function get_person($email)
	{
		$addressbook = intval($this->get_params('addressbook', NULL));
		$token = $this->provide_oauth_access();
		if ($addressbook === 0)
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'addressbook', 'Address book not found');
		}

		// Checking if the current subscriber already exists
		// https://sendpulse.com/integrations/api/bulk-email#email-info
		$req = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/emails/'.$email)
			->header('Authorization', 'Bearer '.$token)
			->log_to($this->requests_log);
		$r = $req->execute();

		if ($r->code === 401)
		{
			$token = $this->provide_oauth_access(TRUE);
			$r = $req
				->header('Authorization', 'Bearer '.$token)
				->execute();
		}

		if ($r->is_successful())
		{
			return $this->translate_int_data_to_subscriber_data($r->data[0]);
		}

		return NULL;
	}

	public function create_person($email, $subscriber_data)
	{
		$addressbook = intval($this->get_params('addressbook', NULL));
		$token = $this->provide_oauth_access();
		if ($addressbook === 0)
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'addressbook', 'Address book not found');
		}

		$data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$data['email'] = $email;

		// https://sendpulse.com/integrations/api/bulk-email#add-list
		$req = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/addressbooks/'.$addressbook.'/emails')
			->header('Authorization', 'Bearer '.$token)
			->data(array(
				'emails' => serialize(array($data)),
			))
			->log_to($this->requests_log);
		$r = $req->execute();

		if ($r->code === 401)
		{
			$token = $this->provide_oauth_access(TRUE);
			$r = $req
				->header('Authorization', 'Bearer '.$token)
				->execute();
		}

		if ( ! $r->is_successful())
		{
			// SendPulse API returns only 404 in case of error, so we have no idea about the error details here
			throw new Integration_Exception(INT_E_TEMPORARY_ERROR);
		}

		if ($r->path('result') != 'true')
		{
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
	}

	public function update_person($email, $subscriber_data)
	{
		$this->create_person($email, $subscriber_data);
	}

	public function provide_oauth_access($refresh = FALSE)
	{
		$token = $this->get_credentials('token', NULL);
		if ($refresh OR is_null($token))
		{
			return $this->oauth_get_token();
		}

		return $token;
	}

	protected function oauth_refresh_token()
	{
	}

	/**
	 * Get OAuth Token
	 *
	 * @return string or NULL if credentials are wrong
	 * @throws Integration_Exception
	 */
	protected function oauth_get_token()
	{
		// Doing 5 attempts, because SendPulse sometimes simply lags and outputs that api key is not valid for valid keys
		for ($i = 0; $i < 5; $i++)
		{
			$r = Integration_Request::factory()
				->method('POST')
				->url($this->get_endpoint().'/oauth/access_token')
				->data(array(
					'grant_type' => 'client_credentials',
					'client_id' => $this->get_credentials('rest_id', ''),
					'client_secret' => $this->get_credentials('rest_secret', ''),
				))
				->log_to($this->requests_log)
				->execute();
			if ($r->is_successful())
			{
				$this->credentials['token'] = $r->get('access_token', NULL);

				return $this->credentials['token'];
			}
		}

		throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'rest_secret', 'Rest ID and Secret are not valid');
	}

}
