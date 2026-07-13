<?php
/**
 * Example configuration for the private update server.
 *
 * Copy this file to config.php and edit the values.
 */

return array(
	'plugin_slug'      => 'cadv-woo-functionalities',
	'version'          => '1.1.19',
	'package_file'     => __DIR__ . '/packages/cadv-woo-functionalities-1.1.19.zip',
	'homepage'         => 'https://cesarandev.com/',
	'requires'         => '6.0',
	'tested'           => '6.6',
	'requires_php'     => '7.4',
	'description'      => 'Actualizacion privada de CADV Woo Functionalities.',
	'changelog'        => '<ul><li>Modal de ficha tecnica usa el color configurado de la categoria.</li></ul>',
	'download_token'   => 'change-this-token',
	'force_https_urls' => true,
);
