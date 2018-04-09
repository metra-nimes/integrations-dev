<?php
/**
 * Available integrations providers.
 * How "ready_for" works:
 * 1. Konaha::DEVELOPMENT will allow to use it only locally on http://convertful.local/
 * 2.  Kohana::STAGING will allow all above and to use it on https://dev.convertful.com/
 * 3. Kohana::PRODUCTION will allow all above and to use it on https://app.convertful.com/
 */
return array(
	'MailChimp' => array(
		'title' => 'MailChimp',
		'ready_for' => Kohana::PRODUCTION,
	),
);