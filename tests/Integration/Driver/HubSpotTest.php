<?php

class Integration_Driver_HubSpotTest extends Integration_DriverOauthTest {
	/**
	 * @var array Valid credentials used for testing purposes
	 */
	protected static $credentials = array(
		'name' => "aaa",
	);

	/**
	 * @var array Some basic meta used for testing purposes
	 */
	protected static $meta = array(
		"name" => "optin.guru",
        "lists" => [],
        "user" => "integrations@optin.guru",
	);

	/**
	 * @var array Valid params used for testing purposes
	 */
	protected static $params = array(
		// Temporary data for local params validation
		'list' => 0,
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
		$driver->provide_oauth_access();
		self::$credentials = $driver->get_credentials();

		$access_token = $driver->get_credentials('oauth.access_token');
		// Looking for orphaned lists left from other tests and removing them
		// https://developers.hubspot.com/docs/methods/lists/get_lists
		$r = Integration_Request::factory()
			->method('GET')
			->header('Accept-type', 'Application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($driver->get_endpoint().'/contacts/v1/lists')
			->data(array(
				// Cannot use some "no limit" value, using extra-big value instead
				'count' => 100,
			))
			->execute();
		$this->assertTrue($r->is_successful(), 'Cannot get previous HubSpot lists');
		foreach ($r->get('lists', array()) as $list)
		{
			// Removing all test lists, that were created more than 2 minutes ago
			if (strpos($list['name'], 'Unit Tests') !== FALSE AND time() - strtotime(substr($list['name'], 11)) > 120)
			{
				// https://developers.hubspot.com/docs/methods/lists/delete_list
				Integration_Request::factory()
					->method('DELETE')
					->header('Accept-type', 'Application/json')
					->header('Content-Type', 'application/json')
					->header('Authorization', 'Bearer '.$access_token)
					->url($driver->get_endpoint().'/contacts/v1/lists/'.Arr::get($list, 'listId'))
					->execute();
			}
		}
		// Creating new test list and storing its id
		// https://developers.hubspot.com/docs/methods/lists/create_list
		$r = Integration_Request::factory()
			->method('POST')
			->header('Accept-type', 'Application/json')
			->header('Content-Type', 'application/json')
			->header('Authorization', 'Bearer '.$access_token)
			->url($driver->get_endpoint().'/contacts/v1/lists')
			->data(array(
				'name' => 'Unit Tests '.date('Y-m-d H:i:s'),
			))
			->execute();
		$this->assertTrue($r->is_successful(), 'Cannot create new HubSpot list');
		// Storing created list ID for further tests
		$list_id = $r->get('listId');
		self::$meta['lists'] = array(
			$list_id => 'Unit Tests',
		);
		//self::$meta['merge_fields'][$list_id] = self::$meta['merge_fields'][self::$params['list']];
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
			array(array_merge(self::$credentials, array('oauth' => '')), 'oauth', 'must not be empty'),
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
		$proper_endpoint = 'https://api.hubapi.com';
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
		$this->assertArrayHasKey('name', $meta, 'Meta doesn\'t contain name field');

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
			array(array_merge(self::$params, array('listId' => -1)), 'listId', 'List not found'),
		);
	}

	/**
	 * Set custom fields cache for testing purposes
	 *
	 * @param array $merge_fields
	 */
	protected function set_properies_cache(array $merge_fields)
	{
		$this->driver->set_meta(array_merge(self::$meta, array(
			'properties' => $merge_fields
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
					'phone' => '9238193331',
					'company' => 'JOHN, INC',
					'site' => 'johnsnow.com',
				),
				array(
					'properties' => array(
						array(
							'property' => 'firstname',
							'value' => 'John'
						),
						array(
							'property' => 'lastname',
							'value' => 'Snow'
						),
						array(
							'property' => 'phone',
							'value' => '9238193331'
						),
						array(
							'property' => 'company',
							'value' => 'JOHN, INC'
						),
						array(
							'property' => 'website',
							'value' => 'johnsnow.com'
						),
					),
				),
				'Standard fields are not properly translated',
			),
			// Full name is properly translated into first / last names
			array(
				array(
					'name' => 'John Snow',
				),
				array(
					'properties' => array(
						array(
							'property' => 'firstname',
							'value' => 'John'
						),
						array(
							'property' => 'lastname',
							'value' => 'Snow'
						),
					),
				),
				'Full name is not properly divided into first / last names',
				TRUE
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
			->get_properties(TRUE);
		$this->assertEquals('Phone Number', Arr::get($tags_names, 'phone'));
	}

	/**
	 * Testing that custom fields values are properly translated to person data
	 * @test
	 * @group external
	 */
	public function test_translate_int_data_to_person_data_with_cache()
	{
		$this->set_properies_cache(array(
			'firstname' => 'first_name',
			'lastname' => 'last_name',
			'company' => 'company',
			'product' => 'My special product',
			'othertag' => 'My special param',
		));

		$int_data = array(
			'properties' => array(
				'firstname' => array(
					'value' => 'John'
				),
				'lastname' => array(
					'value' => 'Smith'
				),
				'company' => array(
					'value' => 'JOHN INC'
				),
				'product' => array(
					'value' => 'Convertful'
				),
				'othertag' => array(
					'value' => 'Spec param value'
				),
				// Field that is not present in meta
				'phone' => array(
					'value' => '1234567890'
				),
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
		$this->driver->create_property('Company');
		// Two custom fields for meta data
		$this->driver->create_property('My special field');
		$this->driver->create_property('Purchased product');

		$tags_names = Integration_Driver::factory($this->get_driver_name())
			->set_credentials(self::$credentials)
			->fetch_meta()
			->set_params(self::$params)
			->get_properties(TRUE);

		$this->assertEquals('Company Name', Arr::get($tags_names, 'company'), 'COMPANY tag was not properly created');
		$this->assertEquals('My special field', Arr::get($tags_names, 'my_special_field'), 'SPECIAL1 tag was not properly created');
		$this->assertEquals('Purchased product', Arr::get($tags_names, 'purchased_product'), 'PRODUCT tag was not properly created');
	}

	/**
	 * Testing that custom fields values are properly translated to person data without cache
	 * @test
	 * @group external
	 */
	public function test_translate_int_data_to_person_data_without_cache()
	{
		$int_data = array(
			'properties' => array(
				'firstname' => array(
					'value' => 'John'
				),
				'lastname' => array(
					'value' => 'Smith'
				),
				'company' => array(
					'value' => 'JOHN INC'
				),
				'purchased_product' => array(
					'value' => 'Convertful'
				),
				'my_special_field' => array(
					'value' => 'Spec param value'
				),
				// Field that is not present in meta
				'phone' => array(
					'value' => '1234567890'
				),
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
				'My special field' => 'Spec param value',
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
		$this->set_properies_cache(array(
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
			'properties' => array(
				'firstname' => array(
					'value' => 'John'
				),
				'company' => array(
					'value' => 'JOHN INC'
				),
				'purchased_product' => array(
					'value' => 'Convertful'
				),
				'my_special_field' => array(
					'value' => 'Spec param value'
				),
			),
		);
		$person_data = $this->driver->translate_int_data_to_person_data($int_data);
		$this->assertEquals(array(
			'first_name' => 'John',
			'company' => 'JOHN INC',
			'meta' => array(
				'My special field' => 'Spec param value',
				'Purchased product' => 'Convertful',
			),
		), $person_data);
	}

	/**
	 * @test
	 * @group external
	 */
	public function test_create_person_with_merge_fields_terribly_outdated_cache_one()
	{
		$this->set_properies_cache(array(
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
		$this->test_create_person('alex2.'.Text::random().'@codelights.com', array(
			'first_name' => 'Alex',
			'meta' => array(
				// Meta field that doesn't exist yet but is present in meta cache
				'Field 10' => 'Value for Field 10',
				'No name' => 'Ya ya field',
			),
		));
	}

	/**
	 * Provides test data for test_create_person
	 *
	 * @return array
	 */
	public function provider_create_person()
	{
		return array(
			// Create person with no fields at all
			array(
				'tom.'.Text::random().'@codelights.com',
				array(),
			),
			// Create person with lots of fields
			array(
				$this->get_person_email(),
				array(
					'first_name' => 'John',
					'last_name' => 'Smith',
					'company' => 'JOHN INC',
					'phone' => '1234567890',
					// Standard field that doesn't exist yet
					'site' => 'http://johnsmith.com',
					'meta' => array(
						'Purchased product' => 'Convertful',
						'My special field' => 'Special field value',
						// Meta param that doesn't exist yet
						'Color' => 'red',
					),
				),
			),
		);
	}

}