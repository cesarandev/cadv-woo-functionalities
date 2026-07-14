<?php
/**
 * Example configuration for the private update server.
 *
 * Copy this file to config.php and edit the values.
 */

return array(
	'plugin_slug'      => 'cadv-woo-functionalities',
	'version'          => '1.1.33',
	'package_file'     => __DIR__ . '/packages/cadv-woo-functionalities-1.1.33.zip',
	'homepage'         => 'https://cesarandev.com/',
	'requires'         => '6.0',
	'tested'           => '6.6',
	'requires_php'     => '7.4',
	'description'      => 'Actualizacion privada de CADV Woo Functionalities.',
	'changelog'        => '<ul><li>Permite abrir el marketplace con filtros directos desde parametros de URL.</li></ul>',
	'download_token'   => 'change-this-token',
	'force_https_urls' => true,
);
