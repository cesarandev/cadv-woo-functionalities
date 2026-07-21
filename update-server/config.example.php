<?php
/**
 * Example configuration for the private update server.
 *
 * Copy this file to config.php and edit the values.
 */

return array(
	'plugin_slug'      => 'cesarandev-woo-func',
	'version'          => '1.1.47',
	'package_file'     => __DIR__ . '/packages/cadv-woo-functionalities-1.1.47.zip',
	'base_url'         => 'https://updates.example.com/update-server/',
	'homepage'         => 'https://cesarandev.com/',
	'requires'         => '6.0',
	'tested'           => '7.0',
	'requires_php'     => '7.4',
	'description'      => 'Actualizacion privada de CADV Woo Functionalities.',
	'changelog'        => '<ul><li>Integra la experiencia de Mi cuenta y protege sus formularios de autenticacion.</li></ul>',
	'download_token'   => 'replace-with-at-least-32-random-characters',
);
