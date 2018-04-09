<?php

interface Integration_Interface_BackendESP {

	/**
	 * Get endpoint URL for API calls
	 *
	 * @return string
	 */
	public function get_endpoint();

	/**
	 * Translate person data from standard convertful to integration format
	 *
	 * @param array $person_data Person data in standard convertful format
	 * @param bool $create_missing_fields
	 * @return array Integration-specified person data format
	 * @throws Integration_Exception
	 */
	public function translate_person_data_to_int_data(array $person_data, $create_missing_fields = FALSE);

	/**
	 * Translate person data from integration to standard convertful format
	 *
	 * @param array $int_data Person data in integration format
	 * @return array Person data in standard convertful format
	 */
	public function translate_int_data_to_person_data(array $int_data);

	/**
	 * Get person by email
	 * @param string $email
	 * @return array|NULL Person data or NULL, if person not found
	 */
	public function get_person($email);

	/**
	 * Create a person with given data
	 *
	 * @param string $email
	 * @param array $person_data
	 * @throws Integration_Exception If couldn't submit
	 */
	public function create_person($email, $person_data);

	/**
	 * Update a person with given data
	 *
	 * @param string $email
	 * @param array $person_data
	 * @throws Integration_Exception If couldn't submit
	 */
	public function update_person($email, $person_data);

}