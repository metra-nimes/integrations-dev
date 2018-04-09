<?php
/**
 * This is a DEVELOPMENT version of security config and it must be overwritten in a production version.
 */
return array(
	// Encryption method (a one from openssl_get_cipher_methods() list)
	'method' => 'aes-256-cbc',
	// A random 256-bit (or other method-defined length) encryption key. Can be obtained by:
	// bin2hex(openssl_random_pseudo_bytes(32))
	'encryption_key' => 'fcca8e8c3ef856d9de1cb1700c184058897f2cfa07ecb348a67dcc865df900ec',
);