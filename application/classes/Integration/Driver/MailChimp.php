<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * MailChimp Integration
 * @link http://developer.mailchimp.com/documentation/mailchimp/reference/overview/
 * @link https://developer.mailchimp.com/documentation/mailchimp/guides/error-glossary/
 * @link https://developer.mailchimp.com/documentation/mailchimp/guides/get-started-with-mailchimp-api-3/#errors
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_MailChimp extends Integration_Driver implements Integration_Interface_BackendESP {

	protected static $company_name = 'Rocket Science Group LLC d/b/a MailChimp';
	protected static $company_address = '675 Ponce de Leon Ave NE, Suite 5000, Atlanta, GA 30308 USA';
	protected static $company_url = 'https://mailchimp.com/';

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
				'description' => '<a href="/docs/integrations/mailchimp/#step-2-get-your-mailchimp-api-key" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => array(
					array('not_empty'),
					array('regex', array(':value', '~\-[a-z0-9]{2,8}$~')),
				),
			),
			'submit' => array(
				'title' => 'Connect with MailChimp',
				'action' => 'connect',
				'type' => 'submit',
			),
		);
	}

	public function get_endpoint()
	{
		if ( ! preg_match('~\-([a-z0-9]{2,8})$~', $this->get_credentials('api_key', ''), $matches))
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
		}

		return 'https://'.$matches[1].'.api.mailchimp.com/3.0';
	}

	public function fetch_meta()
	{
		$this->meta = array(
			'lists' => [],
		);
		// Getting available lists
		// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/#read-get_lists
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/lists')
			->header('Content-Type', 'application/json')
			->data(array(
				'fields' => 'lists.id,lists.name',
				// Cannot use some "no limit" value, using extra-big value instead
				'count' => 1000,
			))
			->http_basic_auth('user', $this->get_credentials('api_key', ''))
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
				throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE, 'api_key');
			}
			elseif ($r->code == 409)
			{
				throw new Integration_Exception(INT_E_TOO_FREQUENT_REQUESTS, 'api_key');
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR, 'api_key');
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
			}
		}
		foreach ($r->get('lists', []) as $list)
		{
			$this->meta['lists'][Arr::get($list, 'id', '')] = Arr::get($list, 'name', '');
		}
		$this->meta['merge_fields'] = [];

		return $this;
	}

	public function describe_params_fields()
	{
		$lists = (array) $this->get_meta('lists', []);

		return array(
			/*'warning' => array(
				'type' => 'alert',
				'text' => 'The maximum length of custom or hidden field\'s name should not exceed 50 characters.',
				'closable' => TRUE,
				//'classes' => '',
			),*/
			'list' => array(
				'title' => 'Subscription List',
				'description' => NULL,
				'type' => 'select',
				'options' => $lists,
				'classes' => 'i-refreshable',
				'rules' => array(
					array('in_array', array(':value', array_keys($lists))),
				),
			),
			'double_optin' => array(
				'text' => 'Double Opt-in',
				'description' => 'When enabled, subscribers will receive a email with the link to confirm their subscription. <a href="/docs/integrations/mailchimp/#double-opt-in" target="_blank">Read more about it.</a>',
				'type' => 'switcher',
				'std' => TRUE,
			),
		);
	}

	public static function describe_data_rules()
	{
		return array(
			'text' => array(
				array('max_length', array(':field', 50), 'The maximum length of custom field\'s name should not exceed 50 characters.'),
			),
			'hidden' => array(
				array('max_length', array(':value', 50), 'The maximum length of hidden field\'s name should not exceed 50 characters.'),
			),
		);
	}

	/**
	 * Create new merge field for the current list
	 * @param string $tag
	 * @param string $name
	 * @return bool|mixed
	 * @throws Integration_Exception
	 * @throws Exception
	 */
	protected $_create_tag_errors = [];

	public function create_merge_field($tag, $name)
	{
		$current_list = $this->get_params('list', '');
		// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/merge-fields/#create-post_lists_list_id_merge_fields
		$r = Integration_Request::factory()
			->http_basic_auth('user', $this->get_credentials('api_key', ''))
			->method('POST')
			// MailChimp requires JSON-encoded post data
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/lists/'.$current_list.'/merge-fields')
			->data(array(
				'tag' => $tag,
				'name' => $name,
				'type' => 'text',
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			if ($r->code == 400 AND stripos($r->body, 'Merge Max Limit Exceeded') !== FALSE)
			{
				$body = json_decode($r->body, TRUE);
				$this->_create_tag_errors[$name] = 'Integration error while create field: '.Arr::get($body, 'detail', 'Did not create');
				return FALSE;
			}
			elseif ($r->code == 404 AND stripos($r->body, 'The requested resource could not be found') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list', 'List not found');
			}
			elseif (stripos($r->body, 'already exists for this list') !== FALSE)
			{
				return $tag;
			}
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}
		$id = $r->get('merge_id');
		// Adding field to meta
		$merge_fields = $this->get_meta('merge_fields.'.$current_list, []);
		$merge_fields[$id] = array(
			'tag' => $tag,
			'name' => $name,
		);
		$this->meta['merge_fields'][$current_list] = $merge_fields;

		return $tag;
	}

	/**
	 * Get available merge fields for the current list
	 * Fetching merge fields for the current list on demand, and caching it in meta for 24 hours
	 * @param bool $force_fetch Prevent using cached version
	 * @return array tag => label
	 * @throws Integration_Exception
	 */
	public function get_merge_fields($force_fetch = FALSE)
	{
		$current_list = Arr::get($this->params, 'list', '');
		$merge_fields = Arr::get($this->meta, 'merge_fields', []);
		if ( ! isset($merge_fields[$current_list]) OR $force_fetch)
		{
			$mf_fields = [];
			// Getting list-defined merge fields
			// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/merge-fields/#read-get_lists_list_id_merge_fields
			$r = Integration_Request::factory()
				->http_basic_auth('user', $this->get_credentials('api_key', ''))
				->method('GET')
				->data(array(
					'count' => 100,
				))
				->url($this->get_endpoint().'/lists/'.$this->get_params('list', '').'/merge-fields')
				->log_to($this->requests_log)
				->execute();
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 404 AND stripos($r->body, 'The requested resource could not be found') !== FALSE)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list', 'List not found');
			}
			elseif ( ! $r->is_successful())
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
			foreach ($r->get('merge_fields') as $merge_field)
			{
				$mf_id = Arr::get($merge_field, 'merge_id');
				$mf_tag = Arr::get($merge_field, 'tag');
				$mf_name = Arr::get($merge_field, 'name');
				$mf_fields[$mf_id] = array(
					'tag' => $mf_tag,
					'name' => $mf_name,
				);
			}
			$merge_fields[$current_list] = $mf_fields;
		}
		// Updating the data
		$this->meta['merge_fields'] = $merge_fields;

		return $this->get_meta('merge_fields.'.$current_list, []);
	}

	/**
	 * Get tags names for the current method
	 * @param bool $force_fetch Prevent using cached version
	 * @return array
	 */
	public function get_tags_names($force_fetch = FALSE)
	{
		$merge_fields = $this->get_merge_fields($force_fetch);
		$tags_names = array_combine(Arr::pluck($merge_fields, 'tag'), Arr::pluck($merge_fields, 'name'));

		return $tags_names;
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = array(
		'first_name' => 'FNAME',
		'last_name' => 'LNAME',
		'name' => 'NAME',
		'phone' => 'PHONE',
		'company' => 'COMPANY',
		'site' => 'SITE',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$person_meta = Arr::get($subscriber_data, 'meta', []);
		unset($subscriber_data['meta']);
		$using_cached_tags = ($this->get_meta('merge_fields.'.Arr::get($this->params, 'list', '')) !== NULL);
		$mf_values = [];
		$tags_names = $this->get_tags_names(! empty($subscriber_data) OR ! empty($person_meta) OR ! $using_cached_tags);
		// Custom person fields first (so they won't overwrite standard fields)
		if ( ! empty($person_meta))
		{
			// Reserved tags to avoid https://kb.mailchimp.com/merge-tags/reserved-field-names-to-avoid
			$reserved_tags = array('INTERESTS', 'UNSUB', 'FORWARD', 'REWARDS', 'ARCHIVE', 'USER_URL', 'DATE', 'EMAIL', 'EMAIL_TYPE', 'TO');
			foreach ($person_meta as $field_name => $field_value)
			{
				// Trying to find existing relevant merge field by its title
				$tag = array_search($field_name, $tags_names, TRUE);
				if ( ! $tag )
				{
					// Generating field tag
					$tag = mb_substr(preg_replace('~[^A-Z\d\_]+~', '', mb_strtoupper($field_name)), 0, 10);
					$tag_base = mb_substr($tag, 0, 9);
					if (empty($tag))
					{
						// Non-ascii symbols case
						$tag = ($tag_base = 'FIELD').($tag_index = 1);
					}
					while (isset($tags_names[$tag]) OR in_array($tag, $reserved_tags) OR in_array($tag, $this->standard_merge_fields))
					{
						$tag_index = isset($tag_index) ? ($tag_index + 1) : 1;
						if ($tag_index > 9)
						{
							// Too much tries ... just skipping the field
							continue 2;
						}
						$tag = $tag_base.$tag_index;
					}
					unset($tag_index);
					if ($create_missing_fields)
					{
						// Creating new merge field
						$tag = $this->create_merge_field($tag, $field_name);

						if ($tag)
						{
							// Updating $tags_names so the added tag presents there
							$tags_names[$tag] = $field_name;
						}
					}
				}

				if ($tag)
				{
					$mf_values[$tag] = $field_value;
				}
			}
		}
		// Standard person fields
		if ( ! empty($subscriber_data))
		{
			foreach ($subscriber_data as $f_type => $field_value)
			{
				$tag = Arr::get($this->standard_merge_fields, $f_type, mb_strtoupper($f_type));
				if ( ! isset($tags_names[$tag]))
				{
					// Human-readable type format
					$field_name = Inflector::humanize(ucfirst($f_type));
					if ($create_missing_fields)
					{
						// Creating new merge field
						$tag = $this->create_merge_field($tag, $field_name);

						if ($tag)
						{
							// Updating $tags_names so the added tag presents there
							$tags_names[$tag] = $field_name;
						}
					}
				}

				if ($tag)
				{
					$mf_values[$tag] = $field_value;
				}
			}
		}

		// Trying to use standard FNAME / LNAME when name is defined
		if (isset($mf_values['NAME']) AND ! empty($mf_values['NAME']) AND ! isset($mf_values['FNAME']) AND ! isset($mf_values['LNAME']))
		{
			$tags_names = $this->get_tags_names($using_cached_tags);
			$name = explode(' ', $mf_values['NAME'], 2);
			$tag = "FNAME";
			if ($create_missing_fields AND ! isset($tags_names['FNAME']))
			{
				$tag = $this->create_merge_field($tag, 'First name');
			}
			if ($tag)
			{
				$mf_values['FNAME'] = $name[0];
			}

			if (isset($name[1]))
			{
				$tag = "LNAME";
				if ($create_missing_fields AND ! isset($tags_names['LNAME']))
				{
					$tag = $this->create_merge_field($tag, 'Last name');
				}
				if ($tag)
				{
					$mf_values['LNAME'] = $name[1];
				}
			}
		}

		return empty($mf_values) ? [] : array('merge_fields' => $mf_values);
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => [],
		);
		$tags_names = $this->get_tags_names();
		foreach (Arr::get($int_data, 'merge_fields', []) as $mf_tag => $value)
		{
			if (empty($value))
			{
				continue;
			}
			if ($f_type = array_search($mf_tag, $this->standard_merge_fields, TRUE))
			{
				// Standard type
				$subscriber_data[$f_type] = $value;
				continue;
			}
			else
			{
				// Custom type
				if ( ! isset($tags_names[$mf_tag]))
				{
					// Most probably cache is outdated, so fetching new fields once again
					$tags_names = $this->get_tags_names(TRUE);
					if ( ! isset($tags_names[$mf_tag]))
					{
						continue;
					}
				}
				$f_name = $tags_names[$mf_tag];
				$subscriber_data['meta'][$f_name] = $value;
			}
		}

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}

		return $subscriber_data;
	}

	/**
	 * @var int For how long we wait for optin confirmation before we consider that we don't have this subscriber?
	 */
	protected $pending_timeout = 10 * Date::MINUTE;

	public function get_person($email)
	{
		// Getting the subscriber
		// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/#read-get_lists_list_id_members_subscriber_hash
		$r = Integration_Request::factory()
			->http_basic_auth('user', $this->get_credentials('api_key', ''))
			->method('GET')
			->url($this->get_endpoint().'/lists/'.$this->get_params('list', '').'/members/'.md5(strtolower($email)))
			->log_to($this->requests_log)
			->execute();
		if ($r->code == 401 OR $r->code == 403)
		{
			throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
		}
		elseif ($r->code == 500)
		{
			throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
		}
		/*elseif ($r->code == 404 AND stripos($r->body, 'The requested resource could not be found') !== FALSE)
		{
			throw new Integration_Exception(INT_E_WRONG_PARAMS, 'list', 'List not found');
		}*/
		elseif ( ! $r->is_successful())
		{
			return NULL;
		}
		// Considering a unconfirmed person subscribed if it was added recently
		$is_subscribed = ($r->get('status') == 'subscribed' OR ($r->get('status') == 'pending' AND (time() - strtotime($r->get('last_changed'))) < $this->pending_timeout));
		if ( ! $is_subscribed)
		{
			return NULL;
		}

		return $this->translate_int_data_to_subscriber_data(array(
			'merge_fields' => $r->get('merge_fields', []),
		));
	}

	/**
	 * @param string $email
	 * @param array $subscriber_data
	 * @param bool $update
	 * @throws Integration_Exception
	 * @throws Exception
	 */
	protected function put_person($email, $subscriber_data, $update = FALSE)
	{
		$request_data = array_merge($this->translate_subscriber_data_to_int_data($subscriber_data, TRUE), array(
			'status' => $this->get_params('double_optin', TRUE) ? 'pending' : 'subscribed',
			'email_address' => $email,
		));
		if ( ! $update)
		{
			$request_data['ip_signup'] = Request::$client_ip;
		}
		// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/#edit-put_lists_list_id_members_subscriber_hash
		$r = Integration_Request::factory()
			->http_basic_auth('user', $this->get_credentials('api_key', ''))
			->method($update ? 'PATCH' : 'PUT')
			// MailChimp requires JSON-encoded post data
			->header('Content-Type', 'application/json')
			->url($this->get_endpoint().'/lists/'.$this->get_params('list', '').'/members/'.md5(strtolower($email)))
			->data($request_data)
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			if ($r->code == 401 OR $r->code == 403)
			{
				throw new Integration_Exception(INT_E_WRONG_CREDENTIALS, 'api_key', 'Account API Key is not valid');
			}
			elseif ($r->code == 400)
			{
				// Check for banned e-mail
				if (stripos($r->body, 'signed up to a lot of lists') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail not allowed for more signups now');
				}
				// Check for duplicate e-mail
				elseif (stripos($r->body, 'already a list member') !== FALSE)
				{
					throw new Integration_Exception(INT_E_EMAIL_DUPLICATE, 'email', 'User is already a list member');
				}
				elseif (stripos($r->body, 'was permanently deleted and cannot be') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Already unsubscribed');
				}
				// Check for banned e-mail
				elseif (stripos($r->body, 'looks fake or invalid') !== FALSE )
				{
					throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail looks fake or invalid');
				}
				// Check for not valid e-mail
				elseif (stripos($r->body, 'The resource submitted could not be validated') !== FALSE)
				{
					throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'E-mail looks fake or invalid');
				}
				// Check for subscribed e-mail
				elseif (stripos($r->body, 'is in a compliance state') !== FALSE)
				{
					//throw new Integration_Exception(INT_E_WRONG_DATA, 'email', 'Member In Compliance State');
					return TRUE;
				}
				elseif (stripos($r->body, 'Your merge fields were invalid.') !== FALSE)
				{
					$error = json_decode($r->body, TRUE);
					if ( ! empty($error['errors']))
					{
						throw new Integration_Exception(INT_E_WRONG_PARAMS, 'email', $error['errors'][0]['field'].' '.$error['errors'][0]['message']);
					}
				}
				else
				{
					throw new Integration_Exception(INT_E_WRONG_REQUEST);
				}
			}
			elseif ($r->code == 404)
			{
				throw new Integration_Exception(INT_E_WRONG_PARAMS);
			}
			elseif ($r->code == 500)
			{
				throw new Integration_Exception(INT_E_INTERNAL_SERVER_ERROR);
			}
			elseif ($r->code == 508)
			{
				throw new Integration_Exception(INT_E_SERVER_NOT_AVAILABLE);
			}
			else
			{
				throw new Integration_Exception(INT_E_WRONG_REQUEST);
			}
		}
		elseif ( ! empty($this->_create_tag_errors))
		{
			$this->_create_tag_errors = array_unique($this->_create_tag_errors);
			$err_key = key($this->_create_tag_errors);

			throw new Integration_Exception(INT_E_WRONG_PARAMS, $err_key, Arr::get($this->_create_tag_errors, $err_key));
		}

		// Verifying email
		if (strtolower($r->get('email_address')) !== strtolower($email))
		{
			throw new Integration_Exception(INT_E_EMAIL_NOT_VERIFIED);
		}
		// Verifying the data
		foreach (Arr::get($request_data, 'merge_fields', []) as $mf_tag => $mf_value)
		{
			$mf_value = trim(strip_tags($mf_value));
			if (trim(strip_tags($r->path('merge_fields.'.$mf_tag))) !== $mf_value)
			{
				throw new Integration_Exception(INT_E_DATA_NOT_VERIFIED, $mf_tag);
			}
		}
	}

	public function create_person($email, $subscriber_data)
	{
		$this->put_person($email, $subscriber_data, FALSE);
	}

	public function update_person($email, $subscriber_data)
	{
		$this->put_person($email, $subscriber_data, TRUE);
	}

}
