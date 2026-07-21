<?php
/**
 * Private plugin update metadata endpoint.
 *
 * Authenticate with Authorization: Bearer YOUR_TOKEN.
 * The legacy ?token=YOUR_TOKEN parameter remains accepted for old clients.
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

$token = get_bearer_token();

if ( '' === $token && isset( $_GET['token'] ) ) {
	$token = (string) $_GET['token'];
}

$configured_token = (string) ( $config['download_token'] ?? '' );

if ( strlen( $configured_token ) < 32 || '' === $token || ! hash_equals( $configured_token, $token ) ) {
	http_response_code( 403 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode_fallback( array( 'error' => 'Forbidden' ) );
	exit;
}

$package_file = realpath( (string) ( $config['package_file'] ?? '' ) );
$packages_dir = realpath( __DIR__ . '/packages' );
$package_root = false !== $packages_dir ? rtrim( $packages_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR : '';

if ( false === $package_file || false === $packages_dir || 0 !== strpos( $package_file, $package_root ) || ! is_file( $package_file ) || ! is_readable( $package_file ) ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode_fallback( array( 'error' => 'Package file is missing or unreadable' ) );
	exit;
}

$expires      = time() + 86400;
$filename     = basename( $package_file );
$signature    = hash_hmac( 'sha256', $expires . '|' . $filename, $configured_token );
$download_url = build_update_server_url( 'download.php', $config, array( 'expires' => $expires, 'signature' => $signature ) );

if ( '' === $download_url ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode_fallback( array( 'error' => 'A valid HTTPS base_url is required' ) );
	exit;
}

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

echo wp_json_encode_fallback(
	array(
		'slug'         => sanitize_update_value( $config['plugin_slug'] ?? 'cesarandev-woo-func' ),
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
 * @param array  $query  Signed query parameters.
 * @return string
 */
function build_update_server_url( $file, array $config, array $query ) {
	$base_url = sanitize_url_fallback( $config['base_url'] ?? '' );

	if ( 'https' !== strtolower( (string) parse_url( $base_url, PHP_URL_SCHEME ) ) ) {
		return '';
	}

	return rtrim( $base_url, '/' ) . '/' . rawurlencode( basename( $file ) ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
}

/**
 * Read a bearer credential without exposing it in access logs.
 *
 * @return string
 */
function get_bearer_token() {
	$header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? trim( (string) $_SERVER['HTTP_AUTHORIZATION'] ) : '';

	if ( '' === $header && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
		$header = trim( (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
	}

	if ( preg_match( '/^Bearer\s+([^\s]+)$/i', $header, $matches ) ) {
		return $matches[1];
	}

	return '';
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
