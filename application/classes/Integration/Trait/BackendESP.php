<?php
/**
 * Created by PhpStorm.
 * User: metra
 * Date: 30.08.18
 * Time: 0:21
 */

trait Integration_Trait_BackendESP {

	/**
	 * Use for store current automation params
	 * @var
	 */
	private $_params;

	/** Wrapper function for get_person
	 * @param $email
	 * @return mixed
	 */
	public function get_subscriber($email)
	{
		return $this->get_person($email);
	}

	/** Get automation params
	 * @param $path
	 * @return mixed
	 */
	public function get_params($path)
	{
		return Arr::path($this->_params,$path,NULL);
	}

	/**
	 * Driver automations
	 * @return array
	 */
	public function describe_automations()
	{
		$params_fields = $this->describe_params_fields();

		return [
			'submit_person' => [
				'title' => 'Add contact to list',
				'params_fields' => $params_fields
			],
		];
	}

	/** Create or update subscriber
	 * @param $email
	 * @param $params
	 * @param array $subscriber_data
	 */
	public function submit_person($email, $params, $subscriber_data = [])
	{
		$this->_params = $params;
		$subscriber = $this->get_subscriber($email);

		if ($subscriber === NULL)
		{
			$this->create_person($email, $subscriber_data);
		}
		else
		{
			$this->update_person($email, $subscriber_data);
		}
	}
}