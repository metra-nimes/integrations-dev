<?php

class Controller_Inttests extends Controller {

	protected $template = NULL;
	protected $assets = NULL;
	protected $after = NULL;

	public function action_index()
	{
		$this->template = View::factory('inttests/template', array(
			'after' => &$this->after,
			'assets' => &$this->assets,
		));

		$drivers = array();
		foreach (Kohana::$config->load('integrations')->as_array() as $driver_name => $driver)
		{
			$drivers[$driver_name] = $driver['title'];
		}
		$this->template->drivers = $drivers;
		$this->template->user = Auth::instance()->get_user();
	}

	public function after()
	{
		$this->response->body($this->template);

		parent::after();
	}

}