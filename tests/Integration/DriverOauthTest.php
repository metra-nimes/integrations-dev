<?php

/**
 * Class Integration_DriverOauthTest
 *
 * !! Important !!
 * Credentials files stored in data directory. See example in data/example.json
 * You can get it after authorization here http://convertful.local/inttests/
 */
abstract class Integration_DriverOauthTest extends Integration_DriverTest {

	protected static $credentials = array();

	/**
	 * @var array saved cache after load credentials
	 */
	protected static $credentials_oauth_cache = array();

	/**
	 * @var bool var for saving credentials to json
	 */
	protected $save_credentials = TRUE;

	/**
	 * @var null|string Filename with credentials
	 */
	protected static $credentials_filename = NULL;

	public static function setUpBeforeClass()
	{
		$class_name = preg_replace('~^.*?([a-zA-Z0-9]+)Test$~', '$1', get_called_class());
		static::$credentials_filename = Kohana::find_file('../tests/data', $class_name, 'json');

		if (static::$credentials_filename)
		{
			static::$credentials['oauth'] = json_decode(file_get_contents(static::$credentials_filename), TRUE);
			static::$credentials_oauth_cache = static::$credentials['oauth'];
		}
		else
		{
			die('Credentials file doesn\'t exist, please create it here: tests/data/'.$class_name.'.json');
		}
	}

	public function setUp()
	{
		parent::setUp();
		$this->save_credentials = TRUE;
	}

	public function tearDown()
	{
		parent::tearDown();
		$new_credentials = $this->driver->get_credentials('oauth', array());
		if (
			! empty($new_credentials)
			AND self::$credentials_oauth_cache != $new_credentials
			AND ! is_null(static::$credentials_filename)
			AND $this->save_credentials
		)
		{
			file_put_contents(static::$credentials_filename, json_encode($new_credentials));
			self::$credentials_oauth_cache = $new_credentials;
			static::$credentials['oauth'] = $new_credentials;
		}
	}

}