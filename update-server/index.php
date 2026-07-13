<?php
/**
 * Private plugin update metadata endpoint.
 *
 * Expected URL:
 * https://updates.example.com/index.php?token=YOUR_TOKEN
 */

$config_path = __DIR__ . '/config.php';

if ( ! file_exists( $config_path ) ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode_fallback( array( 'error' => 'Missing config.php' ) );
	exit;
}

$config = require $config_path;

if ( ! is_array( $config ) ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode_fallback( array( 'error' => 'Invalid config.php' ) );
	exit;
}

$token = isset( $_GET['token'] ) ? (string) $_GET['token'] : '';

if ( ! hash_equals( (string) ( $config['download_token'] ?? '' ), $token ) ) {
	http_response_code( 403 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode_fallback( array( 'error' => 'Forbidden' ) );
	exit;
}

$package_file = (string) ( $config['package_file'] ?? '' );
$download_url = build_update_server_url( 'download.php', $config, $token );

if ( '' === $package_file || ! is_file( $package_file ) || ! is_readable( $package_file ) ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode_fallback( array( 'error' => 'Package file is missing or unreadable' ) );
	exit;
}

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

echo wp_json_encode_fallback(
	array(
		'slug'         => sanitize_update_value( $config['plugin_slug'] ?? 'cadv-woo-functionalities' ),
		'version'      => sanitize_update_value( $config['version'] ?? '' ),
		'download_url' => $download_url,
		'homepage'     => sanitize_url_fallback( $config['homepage'] ?? '' ),
		'requires'     => sanitize_update_value( $config['requires'] ?? '' ),
		'tested'       => sanitize_update_value( $config['tested'] ?? '' ),
		'requires_php' => sanitize_update_value( $config['requires_php'] ?? '' ),
		'description'  => (string) ( $config['description'] ?? '' ),
		'changelog'    => (string) ( $config['changelog'] ?? '' ),
		'package_sha256' => hash_file( 'sha256', $package_file ),
	)
);

/**
 * Build an absolute URL to a server endpoint.
 *
 * @param string $file   Endpoint file.
 * @param array  $config Server config.
 * @param string $token  Download token.
 * @return string
 */
function build_update_server_url( $file, array $config, $token ) {
	$https  = ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] );
	$scheme = $https || ! empty( $config['force_https_urls'] ) ? 'https' : 'http';
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/[^A-Za-z0-9.\-:]/', '', (string) $_SERVER['HTTP_HOST'] ) : '';
	$path   = isset( $_SERVER['SCRIPT_NAME'] ) ? str_replace( basename( (string) $_SERVER['SCRIPT_NAME'] ), $file, (string) $_SERVER['SCRIPT_NAME'] ) : '/' . $file;

	return $scheme . '://' . $host . $path . '?token=' . rawurlencode( $token );
}

/**
 * Minimal value sanitizer independent from WordPress.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_update_value( $value ) {
	return trim( preg_replace( '/[^\w.\- ]/', '', (string) $value ) );
}

/**
 * Minimal URL sanitizer independent from WordPress.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_url_fallback( $value ) {
	$value = filter_var( (string) $value, FILTER_SANITIZE_URL );

	return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
}

/**
 * JSON encoder fallback independent from WordPress.
 *
 * @param mixed $data Response data.
 * @return string
 */
function wp_json_encode_fallback( $data ) {
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
