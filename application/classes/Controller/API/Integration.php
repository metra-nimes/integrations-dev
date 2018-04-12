<?php defined('SYSPATH') or die('No direct script access.');

class Controller_API_Integration extends Controller_API {

	/**
	 * Get COF credentials fields for a certain integration provider
	 * @docs http://docs.convertful.apiary.io/#reference/0/integrations/describe-credentials-fields
	 */
	public function action_describe_credentials_fields()
	{
		$driver_name = $this->request->query('driver');
		$drivers = Kohana::$config->load('integrations')->as_array();
		if ( ! isset($drivers[$driver_name]))
		{
			return $this->add_error('access.404');
		}
		$driver = Integration_Driver::factory($driver_name);
		$this->output = View::factory('cof/fieldset', array(
			'fields' => $driver->describe_credentials_fields(TRUE),
			'values' => array(),
			'id_prefix' => 'cof_'.$driver_name.'_',
		))->render();
	}

	/**
	 * Filter credentials fields (used as an API request for oAuth mechanics)
	 * @docs http://docs.convertful.apiary.io/#reference/0/integrations/filter-credentials
	 */
	public function action_filter_credentials()
	{
		$driver_name = $this->request->post('driver');
		$drivers = Kohana::$config->load('integrations')->as_array();
		if ( ! isset($drivers[$driver_name]))
		{
			return $this->add_error('access.404');
		}
		$driver = Integration_Driver::factory($driver_name);
		$credentials = is_array($this->request->post('credentials')) ? $this->request->post('credentials') : array();
		$this->output = $driver->filter_credentials($credentials);
	}

	/**
	 * Complete oAuth login
	 * @docs http://docs.convertful.apiary.io/#reference/0/integrations/complete-oauth-login
	 */
	public function action_complete_oauth()
	{
		$driver_name = $this->request->param('driver');
		$drivers = Kohana::$config->load('integrations')->as_array();
		if ( ! isset($drivers[$driver_name]))
		{
			return $this->add_error('access.404');
		}
		$view = View::factory('integration/complete_oauth', array(
			'driver_name' => $driver_name,
			'field_name' => $this->request->param('field'),
		));
		echo $view->render();
		die;
	}

}
