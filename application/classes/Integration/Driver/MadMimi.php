<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * MadMimi Integration
 * @link https://madmimi.com/developer
 *
 * PHPDoc for all methods is available in parent Integration_Driver class, and not duplicated here.
 */
class Integration_Driver_MadMimi extends Integration_Driver implements Integration_Interface_BackendESP {

	use Integration_Trait_BackendESP;

	protected static $company_name = 'Mad Mimi, c/o GoDaddy.com, LLC, a Delaware limited liability company';
	protected static $company_address = '1562 First Avenue. Suite 205-6464. New York, NY 10028, USA';
	protected static $company_url = 'https://madmimi.com/';

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
			'email' => array(
				'title' => 'E-mail',
				'description' => 'Your account e-mail address',
				'type' => 'text',
				'rules' => array(
					array('not_empty'),
				),
			),
			'api_key' => array(
				'title' => 'Account API Key',
				'description' => '<a href="/docs/integrations/madmimi/#step-2-get-your-madmimi-api-key" target="_blank">Read where to obtain this code</a>',
				'type' => 'key',
				'rules' => array(
					array('regex', array(':value', '/^[a-z0-9]{32}$/')),
					array('not_empty'),
				),
			),
			'submit' => array(
				'title' => 'Connect with MadMimi',
				'action' => 'connect',
				'type' => 'submit',
			),
		);
	}

	public function get_endpoint()
	{
		return 'https://api.madmimi.com';
	}

	public function fetch_meta()
	{
		$this->meta = array(
			'lists' => array(),
		);

		// Fetch lists
		// https://madmimi.com/developer/lists
		$r = Integration_Request::factory()
			->method('GET')
			->url($this->get_endpoint().'/audience_lists/lists.json')
			->data(array(
				'api_key' => $this->get_credentials('api_key', ''),
				'username' => $this->get_credentials('email', ''),
			))
			->header('Accept-type', 'Application/json')
			->log_to($this->requests_log)
			->execute();
		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST, 'api_key');
		}
		foreach ($r->data as $addr_book)
		{
			$book_id = Arr::get($addr_book, 'id');
			if ( ! is_null($book_id))
			{
				$this->meta['lists'][$book_id] = Arr::get($addr_book, 'name', '');
			}
		}

		return $this;
	}

	public function get_name()
	{
		return $this->get_credentials('email', '');
	}

	public function describe_params_fields()
	{
		$lists = $this->get_meta('lists', array());

		return array(
			'list' => array(
				'title' => 'Audience List',
				'description' => NULL,
				'type' => 'select',
				'options' => $lists,
				'classes' => 'i-refreshable',
				'rules' => array(
					array('in_array', array(':value', array_keys($lists))),
					array('not_empty'),
				),
			),
		);
	}

	/**
	 * @var array Merge fields tag names for Convertful person fields
	 */
	protected $standard_merge_fields = array(
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'phone' => 'phone',
		'company' => 'company',
		'site' => 'site',
	);

	public function translate_subscriber_data_to_int_data(array $subscriber_data, $create_missing_fields = FALSE)
	{
		$int_data = array();

		$meta = Arr::get($subscriber_data, 'meta', array());
		if (array_key_exists('meta', $subscriber_data))
		{
			unset($subscriber_data['meta']);
		}

		foreach ($subscriber_data as $tag => $value)
		{
			if ($tag === 'full_name')
			{
				continue;
			}

			if (array_key_exists($tag, $this->standard_merge_fields))
			{
				Arr::set_path($int_data, $this->standard_merge_fields[$tag], $value);
			}
			else
			{
				Arr::set_path($int_data, $tag, $value);
			}
		}

		$reserved_tags = array(/*excluded tags*/);
		foreach ($meta as $tag => $value)
		{
			$tag = iconv('UTF8', 'ASCII//TRANSLATE', $tag);
			if (empty($tag))
			{
				// Non-ascii symbols case
				$tag = ($tag_base = 'FIELD').($tag_index = 1);
				while (isset($int_data[$tag]) OR in_array($tag, $reserved_tags) OR in_array($tag, $this->standard_merge_fields))
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
			}
			Arr::set_path($int_data, $tag, $value);
		}

		// Trying to use standard FNAME / LNAME when name is defined
		if ($name = Arr::path($subscriber_data, 'name', FALSE) AND ! Arr::path($int_data, 'first_name', FALSE) AND ! Arr::path($int_data, 'last_name', FALSE))
		{
			$name = explode(' ', $name, 2);
			Arr::set_path($int_data, 'first_name', trim($name[0]));
			if (isset($name[1]))
			{
				Arr::set_path($int_data, 'last_name', trim($name[1]));
			}
		}

		return $int_data;
	}

	public function translate_int_data_to_subscriber_data(array $int_data)
	{
		$subscriber_data = array(
			'meta' => array(),
		);

		$add_field = function ($field_id, $value) use (&$subscriber_data) {
			if (empty($value) OR is_null($field_id))
			{
				return;
			}

			if ($field_standard_id = array_search($field_id, $this->standard_merge_fields, TRUE))
			{
				// Standard type
				$subscriber_data[$field_standard_id] = $value;
			}
			else
			{
				$subscriber_data['meta'][ucfirst($field_id)] = $value;
			}
		};

		foreach ($this->standard_merge_fields as $field_id)
		{
			$value = Arr::get($int_data, Inflector::camelize($field_id), NULL);
			$add_field($field_id, $value);
		}

		foreach (Arr::get($int_data, 'auxData', array()) as $field_id => $value)
		{
			$add_field(Inflector::humanize($field_id), $value);
		}

		if (empty($subscriber_data['meta']))
		{
			unset($subscriber_data['meta']);
		}

		return $subscriber_data;
	}

	public function get_person($email)
	{
		// Search subscriber by email
		// @link https://madmimi.com/api/v3/docs#!/subscribers/subscribers_index
		$r = Integration_Request::factory()
			->method('GET')
			->url('https://madmimi.com:443/api/v3/subscribers')
			->data(array(
				'api_key' => $this->get_credentials('api_key', ''),
				'username' => $this->get_credentials('email', ''),
				'query' => strtolower($email),
			))
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
		}

		if ($r->path('pagination.total') > 0)
		{
			$found_lists = Arr::flatten($r->path('subscribers.*.subscriberLists.*.id', array()));
			if (in_array($this->get_params('list', NULL), $found_lists))
			{
				return $this->translate_int_data_to_subscriber_data($r->path('subscribers.0'));
			}
		}

		return NULL;

	}

	public function create_person($email, $subscriber_data)
	{
		$list_id = $this->get_params('list');
		$data = $this->translate_subscriber_data_to_int_data($subscriber_data, TRUE);
		$data['email'] = strtolower($email);
		$data['api_key'] = $this->get_credentials('api_key', '');
		$data['username'] = $this->get_credentials('email', '');

		// Create new member
		// @link https://madmimi.com/developer/lists/add-membership
		$r = Integration_Request::factory()
			->method('POST')
			->url($this->get_endpoint().'/audience_lists/'.$list_id.'/add')
			->data($data)
			->log_to($this->requests_log)
			->execute();

		if ( ! $r->is_successful())
		{
			$this->verify_response($r);
			throw new Integration_Exception(INT_E_WRONG_REQUEST);
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
	protected function verify_response($r)
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

		return TRUE;
	}
}