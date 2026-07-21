<?php
/**
 * Protected package download endpoint for the private update server.
 *
 * The metadata endpoint generates a short-lived signed URL for this endpoint.
 */

$config_path = __DIR__ . '/config.php';

if ( ! file_exists( $config_path ) ) {
	http_response_code( 500 );
	exit( 'Missing config.php' );
}

$config = require $config_path;

if ( ! is_array( $config ) ) {
	http_response_code( 500 );
	exit( 'Invalid config.php' );
}

$package_file = realpath( (string) ( $config['package_file'] ?? '' ) );
$packages_dir = realpath( __DIR__ . '/packages' );
$package_root = false !== $packages_dir ? rtrim( $packages_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR : '';

if ( false === $package_file || false === $packages_dir || 0 !== strpos( $package_file, $package_root ) || ! is_file( $package_file ) || ! is_readable( $package_file ) ) {
	http_response_code( 404 );
	exit( 'Package not found' );
}

$filename         = basename( $package_file );
$expires          = isset( $_GET['expires'] ) && is_string( $_GET['expires'] ) ? filter_var( $_GET['expires'], FILTER_VALIDATE_INT ) : false;
$signature        = isset( $_GET['signature'] ) && is_string( $_GET['signature'] ) ? strtolower( $_GET['signature'] ) : '';
$configured_token = (string) ( $config['download_token'] ?? '' );
$expected         = false !== $expires && strlen( $configured_token ) >= 32 ? hash_hmac( 'sha256', $expires . '|' . $filename, $configured_token ) : '';

if ( strlen( $configured_token ) < 32 || false === $expires || $expires < time() || $expires > time() + 86700 || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) || ! hash_equals( $expected, $signature ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}

header( 'Content-Type: application/zip' );
header( 'Content-Length: ' . filesize( $package_file ) );
header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );

readfile( $package_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
exit;
