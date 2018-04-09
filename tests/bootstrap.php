<?php

if ( ! class_exists('\PHPUnit_Framework_TestCase') AND class_exists('\PHPUnit\Framework\TestCase'))
{
	class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}
elseif ( ! class_exists('\PHPUnit\Framework\TestCase') AND class_exists('\PHPUnit_Framework_TestCase'))
{
	class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

if (is_dir(__DIR__.'/../vendor/koseven/koseven'))
{
	$koseven_dir = realpath(__DIR__.'/../vendor/koseven/koseven').'/';
}
elseif (is_dir(__DIR__.'/../../koseven'))
{
	$koseven_dir = realpath(__DIR__.'/../../koseven').'/';
}
else
{
	die('Koseven is not found. Use "composer install" to install dependencies');
}

function cf_load_class($class_name)
{
	if (strpos($class_name, '\\') !== FALSE)
	{
		return FALSE;
	}
	if (preg_match('~Test$~', $class_name))
	{
		// Unit Tests files
		$filename = __DIR__.'/'.str_replace('_', '/', $class_name).'.php';
	}
	else
	{
		// Shorthands for custom dependencies of application and cforms
		$filename = __DIR__.'/dependencies/'.$class_name.'.php';
	}
	if (file_exists($filename))
	{
		require $filename;

		return TRUE;
	}

	return FALSE;
}

spl_autoload_register('cf_load_class');

define('EXT', '.php');
define('SYSPATH', realpath($koseven_dir.'system').'/');
define('MODPATH', realpath($koseven_dir.'modules').'/');
define('APPPATH', realpath(__DIR__.'/../application').'/');
define('DOCROOT', realpath($koseven_dir.'public').'/');
define('KOHANA_START_TIME', microtime(TRUE));
define('KOHANA_START_MEMORY', memory_get_usage());

// Load the core Kohana class
require SYSPATH.'classes/Kohana/Core'.EXT;
require SYSPATH.'classes/Kohana'.EXT;

date_default_timezone_set('GMT');
setlocale(LC_ALL, 'en_US.utf-8');
spl_autoload_register(array('Kohana', 'auto_load'));
ini_set('unserialize_callback_func', 'spl_autoload_call');
mb_substitute_character('none');

I18n::lang('en-us');
if ($kohana_env = getenv('KOHANA_ENV'))
{
	Kohana::$environment = constant('Kohana::'.strtoupper($kohana_env));
}
else
{
	Kohana::$environment = Kohana::DEVELOPMENT;
}

Kohana::init(array(
	'base_url' => '/',
	'index_file' => '',
	'profile' => FALSE,
	'cache_life' => 60,
	'caching' => FALSE,
	'errors' => FALSE,
));

Kohana::$config->attach(new Config_File);

Kohana::modules(array(
	//'integrations' => realpath(__DIR__.'/../'),
	'unittest' => MODPATH.'unittest',
));

// Disable output buffering
if (($ob_len = ob_get_length()) !== FALSE)
{
	// flush_end on an empty buffer causes headers to be sent. Only flush if needed.
	if ($ob_len > 0)
	{
		ob_end_flush();
	}
	else
	{
		ob_end_clean();
	}
}