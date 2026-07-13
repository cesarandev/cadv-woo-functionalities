<?php
/**
 * Protected package download endpoint for the private update server.
 *
 * Expected URL:
 * https://updates.example.com/download.php?token=YOUR_TOKEN
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

$token = isset( $_GET['token'] ) ? (string) $_GET['token'] : '';

if ( ! hash_equals( (string) ( $config['download_token'] ?? '' ), $token ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}

$package_file = realpath( (string) ( $config['package_file'] ?? '' ) );
$packages_dir = realpath( __DIR__ . '/packages' );

if ( false === $package_file || false === $packages_dir || 0 !== strpos( $package_file, $packages_dir ) || ! is_file( $package_file ) || ! is_readable( $package_file ) ) {
	http_response_code( 404 );
	exit( 'Package not found' );
}

$filename = basename( $package_file );

header( 'Content-Type: application/zip' );
header( 'Content-Length: ' . filesize( $package_file ) );
header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );

readfile( $package_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
exit;
