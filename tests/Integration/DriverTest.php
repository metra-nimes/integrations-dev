<?php

abstract class Integration_DriverTest extends Unittest_TestCase {

	/**
	 * @var Integration_Driver
	 */
	protected $driver;

	/**
	 * Get driver name based on current tests class name
	 */
	protected function get_driver_name()
	{
		return preg_replace('~^.*?([a-zA-Z0-9]+)Test$~', '$1', get_class($this));
	}

	/**
	 * @before
	 */
	public function setUp()
	{
		parent::setUp();
		// Children's class static props
		$class_props = get_class_vars(get_class($this));
		$this->driver = Integration_Driver::factory($this->get_driver_name())
			->set_credentials($class_props['credentials'])
			->set_meta($class_props['meta'])
			->set_params($class_props['params']);
	}

	/**
	 * Create environment for all external tests
	 *
	 * @test
	 * @group external
	 */
	abstract public function test_setup_for_external_tests();

	/**
	 * Provides test data for test_credentials_validation
	 *
	 * @return array
	 */
	abstract public function provider_validate_credentials();

	/**
	 * Tests Integration_Driver_* -> validate_credentials
	 *
	 * Checks that wrong credentials raise Integration_Exception with proper codes, connected to proper fields and
	 * containing proper messages
	 *
	 * @test
	 * @dataProvider provider_validate_credentials
	 * @group local
	 *
	 * @param array $credentials
	 * @param int $error_field
	 * @param string $error_message_contains
	 */
	public function test_validate_credentials(array $credentials, $error_field, $error_message_contains)
	{
		try
		{
			Integration_Driver::factory($this->get_driver_name())->validate_credentials($credentials);
		}
		catch (Integration_Exception $exception)
		{
			$this->assertEquals(INT_E_WRONG_CREDENTIALS, $exception->getCode(), 'Credentials error got the wrong code');
			$this->assertEquals($error_field, $exception->getField(), 'Credentials error not connected with its field');
			$this->assertContains($error_message_contains, $exception->getMessage(), 'Credentials error got no expected message');

			return;
		}

		$this->fail('Integration_Exception was not raised');
	}

	/**
	 * @test
	 * @group local
	 */
	public function test_set_credentials_returns_self()
	{
		$driver = $this->driver->set_credentials($this->driver->get_credentials());
		$this->assertSame($this->driver, $driver);
	}

	/**
	 * Test that endpoint is correct
	 *
	 * @test
	 * @group local
	 */
	abstract public function test_get_endpoint();

	/**
	 * Test that name is correct
	 *
	 * @test
	 * @group local
	 */
	public function test_get_name()
	{
		$class_props = get_class_vars(get_class($this));
		$driver = Integration_Driver::factory($this->get_driver_name())->set_credentials(Arr::get($class_props, 'credentials'));
		$this->assertEquals(Arr::path($class_props, 'credentials.name'), $driver->get_name());
	}

	/**
	 * Test that fetched meta got the proper structure
	 *
	 * @test
	 * @group external
	 */
	abstract public function test_fetch_meta();

	/**
	 * Provides test data for test_params_validation
	 *
	 * @return array
	 */
	abstract public function provider_validate_params();

	/**
	 * Tests Integration_Driver_* -> validate_params
	 *
	 * Checks that wrong credentials raise Integration_Exception with proper codes, connected to proper fields and
	 * containing proper messages
	 *
	 * @test
	 * @dataProvider provider_validate_params
	 * @group local
	 *
	 * @param array $params
	 * @param int $error_field
	 * @param string $error_message_contains
	 */
	public function test_validate_params(array $params, $error_field, $error_message_contains)
	{
		try
		{
			$this->driver->validate_params($params);
		}
		catch (Integration_Exception $exception)
		{
			$this->assertEquals(INT_E_WRONG_PARAMS, $exception->getCode(), 'Params error got the wrong code');
			$this->assertEquals($error_field, $exception->getField(), 'Params error not connected with its field');
			$this->assertContains($error_message_contains, $exception->getMessage(), 'Params error got no expected message');

			return;
		}

		$this->fail('Integration_Exception was not raised');
	}

	/**
	 * Provides test data for test_translate_person_data_to_int_data
	 *
	 * @return array
	 */
	abstract public function provider_translate_person_data_to_int_data();

	/**
	 * Tests Integration_Driver_* -> translate_person_data_to_int_data
	 *
	 * Check the translations logic itself without creating missing fields
	 *
	 * @test
	 * @dataProvider provider_translate_person_data_to_int_data
	 * @group local
	 *
	 * @param array $person_data
	 * @param array $expected_int_data
	 * @param string $error_message
	 * @param bool $create_missing_fields
	 */
	public function test_translate_person_data_to_int_data($person_data, $expected_int_data, $error_message, $create_missing_fields = FALSE)
	{
		$this->assertEquals($expected_int_data, $this->driver->translate_person_data_to_int_data($person_data, $create_missing_fields), $error_message);
	}

	/**
	 * @test
	 * @group external
	 */
	abstract public function test_translate_person_data_to_int_data_creates_missing_fields();

	/**
	 * @return string randomly generated email (unique per each driver)
	 */
	protected function get_person_email()
	{
		static $random_emails = array();
		$driver_name = $this->get_driver_name();
		if ( ! isset($random_emails[$driver_name]))
		{
			$random_emails[$driver_name] = 'john.'.Text::random().'@codelights.com';
		}

		return $random_emails[$driver_name];
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
					'site' => 'johnsmith.com',
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

	/**
	 * @test
	 * @dataProvider provider_create_person
	 * @group external
	 */
	public function test_create_person($email, $person_data)
	{
		try
		{
			$this->driver->create_person($email, $person_data);
		}
		catch (Integration_Exception $e)
		{
			// Don't throw data errors to simplify debug
			if ($e->getCode() !== INT_E_DATA_NOT_VERIFIED)
			{
				throw new Integration_Exception($e->getCode(), $e->getField(), $e->getMessage());
			}
		}

		sleep(2);
		// Validating by a new driver instance
		$stored_person_data = Integration_Driver::factory($this->get_driver_name())
			->set_credentials($this->driver->get_credentials())
			->set_meta($this->driver->get_meta())
			->set_params($this->driver->get_params())
			->get_person($email);
		$this->assertEquals($person_data, $stored_person_data);
	}

	/**
	 * @test
	 * @group external
	 * @expectedException Integration_Exception
	 * @expectedExceptionCode 30
	 */
	public function test_create_person_duplicate()
	{
		// Replicating the same mechanic that's in the original submit function
		if ($this->driver->get_person($this->get_person_email()) !== NULL)
		{
			throw new Integration_Exception(INT_E_EMAIL_DUPLICATE);
		}
	}

	/**
	 * @test
	 * @group external
	 * @expectedException Integration_Exception
	 * @expectedExceptionCode 10
	 */
	public function test_create_person_with_wrong_credentials()
	{
		$this->save_credentials = FALSE;
		$this->driver->set_credentials(array())
			->create_person('john.'.Text::random().'@codelights.com', array());
	}

	/**
	 * @test
	 * @group external
	 */
	public function test_update_person()
	{
		$person_data = array(
			'meta' => array(
				'Color' => 'blue',
			),
		);
		try
		{
			$this->driver->update_person($this->get_person_email(), $person_data);
		}
		catch (Integration_Exception $e)
		{
			// Don't throw data errors to simplify debug
			if ($e->getCode() !== INT_E_DATA_NOT_VERIFIED)
			{
				throw new Integration_Exception($e->getCode(), $e->getField(), $e->getMessage());
			}
		}

		$stored_person_data = $this->driver->get_person($this->get_person_email());

		// Checking that original data remained
		$this->assertEquals('John', Arr::get($stored_person_data, 'first_name'), 'update_person corrupts original person data');
		$this->assertEquals('Convertful', Arr::path($stored_person_data, 'meta.Purchased product'), 'update_person corrupts original person data');

		// Checking that new data is applied
		$this->assertEquals('blue', Arr::path($stored_person_data, 'meta.Color'), 'update_person doesn\'t apply new person data');
	}

}