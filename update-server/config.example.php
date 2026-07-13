<?php
/**
 * Example configuration for the private update server.
 *
 * Copy this file to config.php and edit the values.
 */

return array(
	'plugin_slug'      => 'cadv-woo-functionalities',
	'version'          => '1.1.26',
	'package_file'     => __DIR__ . '/packages/cadv-woo-functionalities-1.1.26.zip',
	'homepage'         => 'https://cesarandev.com/',
	'requires'         => '6.0',
	'tested'           => '6.6',
	'requires_php'     => '7.4',
	'description'      => 'Actualizacion privada de CADV Woo Functionalities.',
	'changelog'        => '<ul><li>Mejora el buscador externo y amplia la busqueda del marketplace por categorias, etiquetas y datos comerciales.</li></ul>',
	'download_token'   => 'change-this-token',
	'force_https_urls' => true,
);
