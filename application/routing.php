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

Route::set('default', '(<controller>(/<action>(/<id>)))')
	->defaults(array(
		'controller' => 'welcome',
		'action'     => 'index',
	));
