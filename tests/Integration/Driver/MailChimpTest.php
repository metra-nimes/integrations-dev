<?php

class Integration_Driver_MailChimpTest extends Integration_DriverTest {

	/**
	 * @var array Valid credentials used for testing purposes
	 */
	protected static $credentials = array(
		'name' => 'My Integration',
		'api_key' => 'a07214d64ab06c2dc7b7918051471d17-us18',
	);

	/**
	 * @var array Some basic meta used for testing purposes
	 */
	protected static $meta = array(
		'lists' => array(
			// Temporary data for local params validation
			'b5d7ed2711' => 'Unit Tests',
		),
		'merge_fields' => array(
			// Temporary data for translations tests
			'b5d7ed2711' => array(
				1 => array(
					'tag' => 'FNAME',
					'name' => 'First Name',
				),
				2 => array(
					'tag' => 'LNAME',
					'name' => 'Last Name',
				),
				777 => array(
					'tag' => 'MYFLD',
					'name' => 'Existing field',
				),
			),
		),
	);

	/**
	 * @var array Valid params used for testing purposes
	 */
	protected static $params = array(
		// Temporary data for local params validation
		'list' => 'b5d7ed2711',
		'double_optin' => FALSE,
	);

	/**
	 * Create environment for all external tests
	 *
	 * @test
	 * @group external
	 */
	public function test_setup_for_external_tests()
	{
		$driver = Integration_Driver::factory($this->get_driver_name())
			->set_credentials(self::$credentials);
		// Looking for orphaned lists left from other tests and removing them
		// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/#read-get_lists
		$r = Integration_Request::factory()
			->method('GET')
			->url($driver->get_endpoint().'/lists')
			->header('Content-Type', 'application/json')
			->data(array(
				// Cannot use some "no limit" value, using extra-big value instead
				'count' => 1000,
			))
			->http_basic_auth('user', $driver->get_credentials('api_key', ''))
			->execute();
		$this->assertTrue($r->is_successful(), 'Cannot get previous MailChimp lists');
		foreach ($r->get('lists', array()) as $list)
		{
			// Removing all test lists, that were created more than 2 minutes ago
			if (strpos($list['name'], 'Unit Tests') !== FALSE AND time() - strtotime(substr($list['name'], 11)) > 120)
			{
				// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/#delete-delete_lists_list_id
				Integration_Request::factory()
					->method('DELETE')
					->url($driver->get_endpoint().'/lists/'.$list['id'])
					->http_basic_auth('user', $driver->get_credentials('api_key', ''))
					->execute();
			}
		}
		// Creating new test list and storing its id
		// http://developer.mailchimp.com/documentation/mailchimp/reference/lists/#create-post_lists
		$r = Integration_Request::factory()
			->method('POST')
			->url($driver->get_endpoint().'/lists')
			->header('Content-Type', 'application/json')
			->data(array(
				'name' => 'Unit Tests '.date('Y-m-d H:i:s'),
				'contact' => array(
					'company' => 'Convertful Tests',
					'address1' => 'Krutitsky val, 11',
					'address2' => '',
					'city' => 'Moscow',
					'state' => '',
					'zip' => '101000',
					'country' => 'RU',
					'phone' => '',
				),
				'permission_reminder' => 'This just is a testing list used for Unit Tests without sending emails',
				'campaign_defaults' => array(
					'from_name' => 'Convertful',
					'from_email' => 'info@convertful.com',
					'subject' => '',
					'language' => 'en',
				),
				'email_type_option' => FALSE,
			))
			->http_basic_auth('user', $driver->get_credentials('api_key', ''))
			->execute();
		$this->assertTrue($r->is_successful(), 'Cannot create new MailChimp list');
		// Storing created list ID for further tests
		$list_id = $r->get('id');
		self::$meta['lists'] = array(
			$list_id => 'Unit Tests',
		);
		self::$meta['merge_fields'][$list_id] = self::$meta['merge_fields'][self::$params['list']];
		self::$params['list'] = $list_id;
	}

	/**
	 * Provides test data for test_credentials_validation
	 *
	 * @return array
	 */
	public function provider_validate_credentials()
	{
		return array(
			// Account Name must not be empty
			array(array_merge(self::$credentials, array('name' => '')), 'name', 'must not be empty'),
			// API Key must not be empty
			array(array_merge(self::$credentials, array('api_key' => '')), 'api_key', 'must not be empty'),
			// API Key must be in specific format
			array(array_merge(self::$credentials, array('api_key' => 'd8oa7gdiwagudyaw')), 'api_key', 'does not match the required format'),
		);
	}

	/**
	 * @test
	 * @group local
	 */
	public function test_get_endpoint()
	{
		// Creating new driver
		$driver = Integration_Driver::factory($this->get_driver_name())->set_credentials(self::$credentials);
		preg_match('~\-([a-z0-9]{2,8})$~', self::$credentials['api_key'], $matches);
		$proper_endpoint = 'https://'.$matches[1].'.api.mailchimp.com/3.0';
		$this->assertEquals($proper_endpoint, $driver->get_endpoint());
	}

	/**
	 * @test
	 * @group external
	 */
	public function test_fetch_meta()
	{
		$driver = Integration_Driver::factory($this->get_driver_name())
			->set_credentials(self::$credentials)
			->fetch_meta();
		$meta = $driver->get_meta();

		// Must be an array
		$this->assertInternalType('array', $meta, 'Meta must be an array');

		// Meta contains lists
		$this->assertArrayHasKey('lists', $meta, 'Meta doesn\'t contain array of lists');
		$this->assertInternalType('array', $meta['lists'], 'Meta doesn\'t contain array of lists');

		// Meta lists got the required testing list
		$this->assertArrayHasKey(self::$params['list'], $meta['lists'], 'The list required for unit testing not found');

		// Must contain custom fields array
		$this->assertArrayHasKey('merge_fields', $meta, 'Meta doesn\'t contain array of custom fields');
		$this->assertInternalType('array', $meta['merge_fields'], 'Meta doesn\'t contain array of custom fields');

		// At most 2 requests
		$this->assertLessThanOrEqual(2, count($driver->requests_log), 'Too many requests for meta fetch');

		return $meta;
	}

	/**
	 * Provides test data for test_validate_params
	 *
	 * @return array
	 */
	public function provider_validate_params()
	{
		return array(
			// Subscription List must be one of the available options
			array(array_merge(self::$params, array('list' => 'ddawda')), 'list', 'must be one of the available options'),
		);
	}

	/**
	 * Set custom fields cache for testing purposes
	 *
	 * @param array $merge_fields
	 */
	protected function set_merge_fields_cache(array $merge_fields)
	{
		$current_list = $this->driver->get_params('list');
		$this->driver->set_meta(array_merge(self::$meta, array(
			'merge_fields' => array(
				$current_list => $merge_fields,
			),
		)));
	}

	public function provider_translate_person_data_to_int_data()
	{
		return array(
			// Test for standard fields translation
			array(
				array(
					'first_name' => 'John',
					'last_name' => 'Snow',
					'name' => 'John Snow',
					'phone' => '9238193331',
					'company' => 'JOHN, INC',
					'site' => 'johnsnow.com',
				),
				array(
					'merge_fields' => array(
						'FNAME' => 'John',
						'LNAME' => 'Snow',
						'NAME' => 'John Snow',
						'PHONE' => '9238193331',
						'COMPANY' => 'JOHN, INC',
						'SITE' => 'johnsnow.com',
					),
				),
				'Standard fields are not properly translated',
			),
			// Meta fields
			array(
				array(
					'first_name' => 'John',
					'company' => 'JOHN, INC',
					'meta' => array(
						// Existing field MYFLD
						'Existing field' => 'Some value',
						// Generating tag for simple case
						'Product12' => 'Super One',
						// Generating tag for too long field
						'Favourite Product' => 'Super Two',
						// Generating tag for standard field tag used as meta
						'Company' => 'OTHER ONE LLC',
						// Meta duplicated names are properly handled as well
						'COMPANY' => 'OTHER TWO LLC',
						// Generating tag for field that's named just like reserved tag
						'Rewards' => 'Some',
						// Generating tag for non-ascii field name
						'Нечто другое' => 'Other value',
					),
				),
				array(
					'merge_fields' => array(
						'FNAME' => 'John',
						'COMPANY' => 'JOHN, INC',
						'EXISTINGFI' => 'Some value',
						'PRODUCT12' => 'Super One',
						'FAVOURITEP' => 'Super Two',
						/*'COMPANY1' => 'OTHER ONE LLC',
						'COMPANY2' => 'OTHER TWO LLC',*/
						'REWARDS1' => 'Some',
						'FIELD1' => 'Other value',
					),
				),
				'Custom fields are not properly translated',
			),
			// Full name is properly translated into first / last names
			array(
				array(
					'name' => 'John Snow',
				),
				array(
					'merge_fields' => array(
						'FNAME' => 'John',
						'LNAME' => 'Snow',
						'NAME' => 'John Snow',
					),
				),
				'Full name is not properly divided into first / last names'
			),
		);
	}

	/**
	 * @test
	 * @group external
	 */
	public function test_translate_person_data_to_int_data_creates_missing_fields()
	{
		$person_data = array(
			// Merge field for phone doesn't exist yet
			'phone' => '1234567890',
		);
		$this->driver->translate_person_data_to_int_data($person_data, TRUE);
		$tags_names = Integration_Driver::factory($this->get_driver_name())
			->set_credentials(self::$credentials)
			->set_meta(self::$meta)
			->set_params(self::$params)
			->get_tags_names(TRUE);
		$this->assertEquals('Phone Number', Arr::get($tags_names, 'PHONE'));
	}

	/**
	 * Testing that custom fields values are properly translated to person data
	 * @test
	 * @group external
	 */
	public function test_translate_int_data_to_person_data_with_cache()
	{
		$this->set_merge_fields_cache(array(
			1 => array(
				'tag' => 'FNAME',
				'name' => 'First Name',
			),
			2 => array(
				'tag' => 'LNAME',
				'name' => 'Last Name',
			),
			3 => array(
				'tag' => 'COMPANY',
				'name' => 'Company',
			),
			4 => array(
				'tag' => 'PRODUCT',
				'name' => 'My special product',
			),
			5 => array(
				'tag' => 'OTHERTAG',
				'name' => 'My special param',
			),
		));

		$int_data = array(
			'merge_fields' => array(
				'FNAME' => 'John',
				'LNAME' => 'Smith',
				'COMPANY' => 'JOHN INC',
				'PRODUCT' => 'Convertful',
				'OTHERTAG' => 'Spec param value',
				// Field that is not present in meta
				'PHONE' => '1234567890',
			),
		);
		$this->assertEquals(array(
			'first_name' => 'John',
			'last_name' => 'Smith',
			'company' => 'JOHN INC',
			'phone' => '1234567890',
			'meta' => array(
				'My special product' => 'Convertful',
				'My special param' => 'Spec param value',
			),
		), $this->driver->translate_int_data_to_person_data($int_data));
	}

	/**
	 * @test
	 * @group external
	 */
	public function test_create_merge_field()
	{
		// Merge field for standard type
		$this->driver->create_merge_field('COMPANY', 'Company');
		// Two custom fields for meta data
		$this->driver->create_merge_field('SPECIAL1', 'My special field');
		$this->driver->create_merge_field('PRODUCT', 'Purchased product');

		$tags_names = Integration_Driver::factory($this->get_driver_name())
			->set_credentials(self::$credentials)
			->fetch_meta()
			->set_params(self::$params)
			->get_tags_names(TRUE);

		$this->assertEquals('Company', Arr::get($tags_names, 'COMPANY'), 'COMPANY tag was not properly created');
		$this->assertEquals('My special field', Arr::get($tags_names, 'SPECIAL1'), 'SPECIAL1 tag was not properly created');
		$this->assertEquals('Purchased product', Arr::get($tags_names, 'PRODUCT'), 'PRODUCT tag was not properly created');
	}

	/**
	 * Testing that custom fields values are properly translated to person data without cache
	 * @test
	 * @group external
	 */
	public function test_translate_int_data_to_person_data_without_cache()
	{
		$int_data = array(
			'merge_fields' => array(
				'FNAME' => 'John',
				'LNAME' => 'Smith',
				'COMPANY' => 'JOHN INC',
				'PRODUCT' => 'Convertful',
				'SPECIAL1' => 'Special field value',
				// Field that is not present in meta
				'PHONE' => '1234567890',
			),
		);

		$person_data = $this->driver->translate_int_data_to_person_data($int_data);
		$this->assertEquals(array(
			'first_name' => 'John',
			'last_name' => 'Smith',
			'company' => 'JOHN INC',
			'phone' => '1234567890',
			'meta' => array(
				'Purchased product' => 'Convertful',
				'My special field' => 'Special field value',
			),
		), $person_data);
	}

	/**
	 * Should be used after test_create_merge_field
	 *
	 * @test
	 * @group external
	 */
	public function test_translate_int_data_to_person_data_with_outdated_cache()
	{
		$this->set_merge_fields_cache(array(
			1 => array(
				'tag' => 'FNAME',
				'name' => 'First Name',
			),
			2 => array(
				'tag' => 'LNAME',
				'name' => 'Last Name',
			),
		));
		$int_data = array(
			'merge_fields' => array(
				'FNAME' => 'John',
				'COMPANY' => 'JOHN INC',
				'SPECIAL1' => 'Special data',
				'PRODUCT' => 'Convertful',
			),
		);
		$person_data = $this->driver->translate_int_data_to_person_data($int_data);
		$this->assertEquals(array(
			'first_name' => 'John',
			'company' => 'JOHN INC',
			'meta' => array(
				'My special field' => 'Special data',
				'Purchased product' => 'Convertful',
			),
		), $person_data);
	}

	/**
	 * @test
	 * @group external
	 * @expectedException Integration_Exception
	 * @expectedExceptionCode 22
	 */
	public function test_create_person_with_fake_email_is_properly_handled()
	{
		$email = 'test@example.com';
		$this->driver->create_person($email, array());
	}

	/**
	 * @test
	 * @group external
	 */
	public function test_create_person_with_merge_fields_terribly_outdated_cache_one()
	{
		$this->set_merge_fields_cache(array(
			1 => array(
				'tag' => 'FNAME',
				'name' => 'First Name',
			),
		));
		$this->test_create_person('alex.'.Text::random().'@codelights.com', array(
			'first_name' => 'Alex',
			// Standard field that exists but isn't present in meta cache
			'company' => 'ALEX INC',
			'meta' => array(
				// Meta field that exists but isn't present in meta cache
				'Purchased product' => 'Convertful',
			),
		));
	}

	/**
	 * @test
	 * @group external
	 */
	public function test_create_person_with_merge_fields_terribly_outdated_cache_two()
	{
		$this->set_merge_fields_cache(array(
			1 => array(
				'tag' => 'FNAME',
				'name' => 'First Name',
			),
			// Field doesn't exist in the real but exists in cache
			10 => array(
				'tag' => 'FIELD10',
				'name' => 'Field 10',
			),
		));
		$this->test_create_person('alex2.'.Text::random().'@codelights.com', array(
			'first_name' => 'Alex',
			'meta' => array(
				// Meta field that doesn't exist yet but is present in meta cache
				'Field 10' => 'Value for Field 10',
			),
		));
	}

}