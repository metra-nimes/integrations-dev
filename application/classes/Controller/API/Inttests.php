<?php

class Controller_API_Inttests extends Controller_API {

	public $driver_log = array();
	private $_changed = array();


	/**
	 * GET /api/inttests/describe_credentials_fields
	 * @get string driver_name Integration driver name
	 */
	public function action_describe_credentials_fields()
	{
		$driver_name = $this->request->query('driver_name');
		$drivers = Kohana::$config->load('integrations')->as_array();
		if ( ! isset($drivers[$driver_name]))
		{
			$this->add_error('access.404');
		}
		$driver = Integration_Driver::factory($driver_name);
		$this->driver_log = &$driver->requests_log;
		$this->output = View::factory('cof/fieldset', array(
			'fields' => $driver->describe_credentials_fields(TRUE),
			'values' => array(),
		))->render();
	}

	/**
	 * GET /api/inttests/fetch_meta
	 * @get string driver_name
	 * @get array credentials
	 * @get int duplicate
	 */
	public function action_fetch_meta()
	{
		$driver_name = $this->request->query('driver_name');
		$credentials = $this->request->query('credentials');
		$duplicate = ! ! $this->request->query('duplicate');

		try
		{
			/** @var Integration_Driver $driver */
			$driver = Integration_Driver::factory($driver_name)
				->set_credentials($credentials, TRUE);
			$this->driver_log = &$driver->requests_log;
			$other_integrations = array();
			if ($duplicate)
			{
				// Imitating existing duplicating integration
				$other_integrations[] = ORM::factory('Integration')->values(array(
					'credentials' => $credentials,
				));
			}
			$driver->validate_if_unique($other_integrations);
			$driver->fetch_meta();
			$this->output['meta'] = $driver->get_meta();
			$this->output['credentials'] = $driver->get_credentials();

			$automations = $driver->describe_automations();
			if (! empty($automations))
			{
				$automations = array_map(function ($item){
					return Arr::get($item, 'title', 'No Name');
				}, $automations);
				$this->output['automations'] = View::factory('cof/field', array(
					'name' => 'automation',
					'id' => 'cof_automation',
					'field' => array(
						'title' => 'Automation',
						'type' => 'select2',
						'options' => array_merge(['' => 'Choose automation ...'], $automations),
					),
					'values' => array(
						'driver' => '',
					),
				))->render();
			}

			$this->update_driver_data($driver, $credentials, array());
		}
		catch (Integration_Exception $e)
		{
			return $this->add_error($e->getCode().': '.$e->getMessage(), $e->getField());
		}
	}

	/**
	 * GET /api/inttests/describe_automation
	 * @get string automation_name Integration driver name
	 */
	public function action_describe_automation()
	{
		$driver_name = $this->request->query('driver_name');
		$credentials = $this->request->query('credentials');
		$meta = $this->request->query('meta');

		$driver = Integration_Driver::factory($driver_name)
			->set_credentials($credentials, TRUE)
			->set_meta(json_decode($meta, true));

		$automation_name = $this->request->query('automation_name');
		$automations = $driver->describe_automations();
		if ( ! isset($automations[$automation_name]))
		{
			$this->add_error('access.404');
		}

		$fields = array_merge(
			Arr::path($automations, [$automation_name, 'params_fields'], []),
			[
				'submit' => [
					'title' => 'Test automation',
					'action' => $automation_name,
					'type' => 'submit',
				],
			]
		);
		$this->output = View::factory('cof/fieldset', [
			'fields' => $fields,
			'values' => [],
		])->render();
	}

	/**
	 * GET /api/inttests/validate_params
	 * @get string driver_name
	 * @get array credentials
	 * @get array meta
	 * @get array params
	 */
	public function action_validate_params()
	{
		$driver_name = $this->request->query('driver_name');
		$credentials = (array) $this->request->query('credentials');
		$meta = (array) $this->request->query('meta');
		$params = (array) $this->request->query('params');

		try
		{
			Integration_Driver::factory($driver_name)
				->set_credentials($credentials, FALSE)
				->set_meta($meta)
				->set_params($params, TRUE);
		}
		catch (Integration_Exception $e)
		{
			return $this->add_error($e->getCode().': '.$e->getMessage(), $e->getField());
		}
	}


	/**
	 * POST /api/inttests/get_subscriber
	 */
	public function action_get_subscriber()
	{
		$driver_name = $this->request->post('driver_name');
		$credentials = Arr::get($this->request->post(), 'credentials', array());
		$meta = Arr::get($this->request->post(), 'meta', array());
		$params = Arr::get($this->request->post(), 'params', array());
		$subscriber_data = Arr::get($this->request->post(), 'data', array());


		$email = Arr::get($subscriber_data, 'email', '');
		unset($subscriber_data['email']);
		if (empty($email) OR ! Valid::email($email))
		{
			return $this->add_error('Please enter a valid email', 'email');
		}
		try
		{
			$driver = Integration_Driver::factory($driver_name, $credentials, $meta, $params);
			$this->driver_log = &$driver->requests_log;
			// This part replicates the same part from Daemon::submit_integration
			$person = $driver->get_subscriber($email);
			if ($person)
			{
				$this->output['person'] = $person;
			}

			$this->update_driver_data($driver, $credentials, $meta, $params);
		}
		catch (Integration_Exception $e)
		{
			$this->_errors = array(
				'error' => $e->getCode().': '.$e->getMessage(), $e->getField(),
			);
		}
	}

	public function action_automation()
	{
		$driver_name = $this->request->post('driver_name');
		$credentials = Arr::get($this->request->post(), 'credentials', []);
		$meta = Arr::get($this->request->post(), 'meta', []);
		$subscriber_data = Arr::get($this->request->post(), 'data', []);

		$automation = Arr::get($this->request->post(), 'automation', []);
		$automation_params = Arr::get($this->request->post(), 'automation_params', []);

		try
		{
			$driver = Integration_Driver::factory($driver_name, $credentials, $meta);
			$this->driver_log = &$driver->requests_log;

			$driver->exec_automation($automation, $automation_params, $subscriber_data);

			$this->update_driver_data($driver, $credentials, $meta);
		}
		catch (Integration_Exception $e)
		{
			$this->_errors = array(
				'error' => $e->getCode().': '.$e->getMessage(), $e->getField(),
			);
		}
	}

	protected function get_success_response($data)
	{
		return array(
			'success' => 1,
			'message' => $this->_success_message,
			'log' => $this->driver_log,
			'data' => $data,
			'changed' => $this->_changed,
		);
	}

	protected function get_error_response()
	{
		return array(
			'success' => 0,
			'errors' => $this->_errors,
			'log' => $this->driver_log,
			'changed' => $this->_changed,
		);
	}

	/**
	 * @param Integration_Driver $driver
	 * @param $credentials
	 * @param $meta
	 * @param $params
	 */
	private function update_driver_data($driver, $credentials = NULL, $meta = NULL, $params = NULL)
	{
		if ( ! is_null($credentials) AND $driver->get_credentials() !== $credentials)
		{
			$this->_changed['credentials'] = $driver->get_credentials();
		}

		if ( ! is_null($credentials) AND $driver->get_meta() !== $meta)
		{
			$this->_changed['meta'] = $driver->get_meta();
		}

		if ( ! is_null($credentials) AND $driver->get_params() !== $params)
		{
			$this->_changed['params'] = $driver->get_params();
		}
	}

}