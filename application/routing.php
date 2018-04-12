<?php

// Modules assets (copied from app)
Route::set('modules-assets', 'assets/<file>', array(
	'file' => '.+\.(js|png|jpg|css|otf|svg|ttf|woff|woff2)',
))->defaults(array(
	'controller' => 'Assets',
	'action' => 'handle',
));

// Integrations tests
Route::set('inttests', 'inttests(/<action>)')->defaults(array(
	'controller' => 'Inttests',
	'action' => 'index',
));
Route::set('inttests.api', 'api/inttests/<action>')->defaults(array(
	'directory' => 'API',
	'controller' => 'Inttests',
));
Route::set('inttests.api_oauth_complete', 'api/integration/complete_oauth/<driver>(/<field>)')->defaults(array(
	'directory' => 'API',
	'controller' => 'Integration',
	'action' => 'complete_oauth',
	'driver' => 'HubSpot',
	'field' => 'oauth'
));
Route::set('inttests.api_oauth', 'api/integration/<action>', array('action' => 'describe_credentials_fields|filter_credentials|describe_params_fields'))
	->defaults(array(
		'directory' => 'API',
		'controller' => 'Integration',
	));

Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults(array(
		'controller' => 'welcome',
		'action'     => 'index',
	));
