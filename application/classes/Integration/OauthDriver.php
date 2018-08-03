<?php defined('SYSPATH') or die('No direct access allowed.');

abstract class Integration_OauthDriver extends Integration_Driver{

	/**
	 * Provide oauth access
	 */
	public function provide_oauth_access()
	{
		if ($this->get_credentials('oauth.access_token', NULL) == NULL)
		{
			$this->oauth_get_token();
		}
		elseif ($this->get_credentials('oauth.expires_in', 0) < time())
		{
			$this->oauth_refresh_token();
		}

		// access is OK!
	}

	abstract protected function oauth_get_token();

	abstract protected function oauth_refresh_token();

}
