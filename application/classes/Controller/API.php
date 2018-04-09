<?php defined('SYSPATH') or die('No direct script access.');

class Controller_API extends Controller {

	/**
	 * Output formats supported by this controller
	 */
	protected $_supported_formats = array(
		'json',
	);

	/**
	 * @var array Output data that will be displayed through AJAX
	 */
	public $output = array();

	/**
	 * @var array success message title and text container
	 */
	protected $_success_message = array();

	/**
	 * @var array errors container
	 */
	protected $_errors = array();

	/**
	 * Returns formatted error response containing errors occured
	 *
	 * @return array formatted error response
	 */
	protected function get_error_response()
	{
		return array(
			'success' => 0,
			'errors' => $this->_errors,
		);
	}

	/**
	 * Returns formatted success response containing output data
	 *
	 * @param mixed $data output data
	 * @return array formatted success response
	 */
	protected function get_success_response($data)
	{
		return array(
			'success' => 1,
			'message' => $this->_success_message,
			'data' => $data,
		);
	}

	public function set_success_message($message)
	{
		$this->_success_message = $message;
	}

	/**
	 * Add cross-origin headers to provide cross-domain request for a certain action
	 * @link https://learn.javascript.ru/xhr-crossdomain
	 */
	protected function add_cross_domain_headers()
	{
		$this->response->headers('Access-Control-Allow-Origin', $this->request->headers('Origin'));
		$this->response->headers('Access-Control-Allow-Credentials', 'true');
		$this->response->headers('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
	}

	/**
	 * Checks if requested format is supported by API
	 * Sets parameter $this->_output_data_only from request to determine if API was called for testing purposes
	 */
	public function before()
	{
		parent::before();

		$this->_auth = Auth::instance();

		// Test to ensure the format requested is supported
		$format = $this->request->param('format');
		if ( ! empty($format) AND ! in_array($format, $this->_supported_formats))
		{
			throw new Kohana_Exception('controller_api:wrong_format', array(
				':format' => $this->request->param('format'),
			));
		}
	}

	public function after()
	{
		$this->response->headers('Cache-Control', 'private');

		if (count($this->_errors) > 0)
		{
			// If errors are present - echo them
			$output = $this->get_error_response();
		}
		else
		{
			// echo success response
			$output = $this->get_success_response($this->output);
		}

		$format = $this->request->param('format', 'json');

		if ($format == 'json')
		{
			$this->response->headers('Content-Type', 'application/json');
			$this->response->body(json_encode($output));
		}
		else
		{
			throw new Kohana_Exception('controller_api:wrong_format', array(':format' => $this->request->param('format')));
		}

		parent::after();
		$this->check_cache();
	}

	public function add_error($message, $field = NULL)
	{
		parent::add_error($message, $field);

		// In case of access restrictions showing the relevant screen
		if (preg_match('/access\.(?<code>\d+)/i', $message, $message_arr))
		{
			throw HTTP_Exception::factory($message_arr['code']);
		}

		return TRUE;
	}

	public function execute()
	{
		try
		{
			parent::execute();
		}
		catch (HTTP_Exception $e)
		{
			$this->after();
		}

		return $this->response;
	}

}
