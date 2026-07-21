<?php
/**
 * Custom update checker for private plugin releases.
 *
 * @package CADVWooFunctionalities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates a private update server with WordPress plugin updates.
 */
final class CADV_Woo_Functionalities_Updater {
	const UPDATE_TRANSIENT_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook updater behavior.
	 */
	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_package_download' ), 10, 4 );
	}

	/**
	 * Add plugin update data to the WordPress update transient.
	 *
	 * @param stdClass|false $transient Update transient.
	 * @return stdClass|false
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = $this->get_remote_metadata();

		if ( empty( $remote ) || empty( $remote['version'] ) || empty( $remote['download_url'] ) ) {
			return $transient;
		}

		if ( ! version_compare( $remote['version'], CADV_WOO_FUNCTIONALITIES_VERSION, '>' ) ) {
			return $transient;
		}

		$plugin_file = plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE );

		$transient->response[ $plugin_file ] = (object) array(
			'id'          => $plugin_file,
			'slug'        => dirname( $plugin_file ),
			'plugin'      => $plugin_file,
			'new_version' => $remote['version'],
			'url'         => ! empty( $remote['homepage'] ) ? $remote['homepage'] : 'https://cesarandev.com/',
			'package'     => $remote['download_url'],
			'tested'      => ! empty( $remote['tested'] ) ? $remote['tested'] : '',
			'requires'    => ! empty( $remote['requires'] ) ? $remote['requires'] : '',
			'requires_php' => ! empty( $remote['requires_php'] ) ? $remote['requires_php'] : '',
		);

		return $transient;
	}

	/**
	 * Provide plugin details in the WordPress "View details" modal.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action Requested action.
	 * @param object             $args   API args.
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE ) ) !== $args->slug ) {
			return $result;
		}

		$remote = $this->get_remote_metadata();

		if ( empty( $remote ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'CADV Woo Functionalities',
			'slug'          => dirname( plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE ) ),
			'version'       => ! empty( $remote['version'] ) ? $remote['version'] : CADV_WOO_FUNCTIONALITIES_VERSION,
			'author'        => '<a href="https://cesarandev.com/">CADV</a>',
			'homepage'      => ! empty( $remote['homepage'] ) ? $remote['homepage'] : 'https://cesarandev.com/',
			'requires'      => ! empty( $remote['requires'] ) ? $remote['requires'] : '',
			'tested'        => ! empty( $remote['tested'] ) ? $remote['tested'] : '',
			'requires_php'  => ! empty( $remote['requires_php'] ) ? $remote['requires_php'] : '',
			'download_link' => ! empty( $remote['download_url'] ) ? $remote['download_url'] : '',
			'sections'      => array(
				'description' => ! empty( $remote['description'] ) ? wp_kses_post( $remote['description'] ) : 'Actualizaciones privadas de CADV Woo Functionalities.',
				'changelog'   => ! empty( $remote['changelog'] ) ? wp_kses_post( $remote['changelog'] ) : '',
			),
		);
	}

	/**
	 * Fetch and cache update metadata.
	 *
	 * @return array
	 */
	private function get_remote_metadata() {
		$endpoint = $this->get_update_endpoint();
		$token    = $this->get_update_token();

		if ( empty( $endpoint ) || empty( $token ) ) {
			return array();
		}

		$cache_key = 'cadv_woo_functionalities_update_metadata_' . md5( $endpoint . '|' . $token );
		$cached    = get_site_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'slug'    => dirname( plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE ) ),
					'version' => CADV_WOO_FUNCTIONALITIES_VERSION,
					'site'    => home_url(),
				),
				$endpoint
			),
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				'headers'     => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$metadata = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $metadata ) ) {
			return array();
		}

		$metadata = $this->sanitize_metadata( $metadata, $endpoint );

		if ( ! empty( $metadata ) ) {
			set_site_transient( $cache_key, $metadata, self::UPDATE_TRANSIENT_TTL );
		}

		return $metadata;
	}

	/**
	 * Get configured update endpoint.
	 *
	 * @return string
	 */
	private function get_update_endpoint() {
		$endpoint = $this->get_configured_update_server();
		$endpoint = remove_query_arg( 'token', $endpoint );
		$endpoint = esc_url_raw( $endpoint, array( 'https' ) );

		if ( 'https' !== wp_parse_url( $endpoint, PHP_URL_SCHEME ) ) {
			return '';
		}

		return $endpoint;
	}

	/**
	 * Get the configured endpoint before credentials are removed from its URL.
	 *
	 * @return string
	 */
	private function get_configured_update_server() {
		$endpoint = defined( 'CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER' ) ? CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER : '';

		/**
		 * Allows site owners to configure the private update metadata endpoint.
		 *
		 * @param string $endpoint Update endpoint URL.
		 */
		return (string) apply_filters( 'cadv_woo_functionalities_update_server', $endpoint );
	}

	/**
	 * Get the update API token, supporting the legacy query-string setting.
	 *
	 * @return string
	 */
	private function get_update_token() {
		$token = defined( 'CADV_WOO_FUNCTIONALITIES_UPDATE_TOKEN' ) ? CADV_WOO_FUNCTIONALITIES_UPDATE_TOKEN : '';

		if ( '' === $token ) {
			$query = wp_parse_url( $this->get_configured_update_server(), PHP_URL_QUERY );
			$args  = array();
			parse_str( (string) $query, $args );
			$token = isset( $args['token'] ) ? $args['token'] : '';
		}

		$token = trim( (string) $token );

		return strlen( $token ) >= 32 && ! preg_match( '/[\x00-\x20\x7F]/', $token ) ? $token : '';
	}

	/**
	 * Sanitize remote update metadata.
	 *
	 * @param array  $metadata Raw metadata.
	 * @param string $endpoint Trusted metadata endpoint.
	 * @return array
	 */
	private function sanitize_metadata( array $metadata, $endpoint ) {
		$version       = isset( $metadata['version'] ) ? sanitize_text_field( $metadata['version'] ) : '';
		$download_url  = isset( $metadata['download_url'] ) ? esc_url_raw( $metadata['download_url'], array( 'https' ) ) : '';
		$package_hash  = isset( $metadata['package_sha256'] ) ? strtolower( sanitize_text_field( $metadata['package_sha256'] ) ) : '';
		$expected_slug = dirname( plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE ) );
		$remote_slug   = isset( $metadata['slug'] ) ? sanitize_key( $metadata['slug'] ) : '';
		$allowed_slugs = (array) apply_filters( 'cadv_woo_functionalities_update_allowed_slugs', array( $expected_slug, 'cadv-woo-functionalities' ), $metadata );
		$allowed_slugs = array_filter( array_map( 'sanitize_key', $allowed_slugs ) );
		$endpoint_host = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_HOST ) );
		$download_host = strtolower( (string) wp_parse_url( $download_url, PHP_URL_HOST ) );
		$allowed_hosts = (array) apply_filters( 'cadv_woo_functionalities_update_allowed_hosts', array( $endpoint_host ), $metadata );
		$allowed_hosts = array_map( 'strtolower', array_filter( array_map( 'sanitize_text_field', $allowed_hosts ) ) );

		if (
			empty( $version ) ||
			empty( $download_url ) ||
			'' === $endpoint_host ||
			'https' !== wp_parse_url( $download_url, PHP_URL_SCHEME ) ||
			! in_array( $download_host, $allowed_hosts, true ) ||
			( '' !== $remote_slug && ! in_array( $remote_slug, $allowed_slugs, true ) ) ||
			! preg_match( '/^[a-f0-9]{64}$/', $package_hash )
		) {
			return array();
		}

		return array(
			'version'        => $version,
			'download_url'   => $download_url,
			'package_sha256' => $package_hash,
			'homepage'     => isset( $metadata['homepage'] ) ? esc_url_raw( $metadata['homepage'] ) : '',
			'requires'     => isset( $metadata['requires'] ) ? sanitize_text_field( $metadata['requires'] ) : '',
			'tested'       => isset( $metadata['tested'] ) ? sanitize_text_field( $metadata['tested'] ) : '',
			'requires_php' => isset( $metadata['requires_php'] ) ? sanitize_text_field( $metadata['requires_php'] ) : '',
			'description'  => isset( $metadata['description'] ) ? wp_kses_post( $metadata['description'] ) : '',
			'changelog'    => isset( $metadata['changelog'] ) ? wp_kses_post( $metadata['changelog'] ) : '',
		);
	}

	/**
	 * Download and verify this plugin package before WordPress extracts it.
	 *
	 * @param bool|WP_Error|string $reply      Existing short-circuit value.
	 * @param string               $package    Package URL.
	 * @param WP_Upgrader          $upgrader   Upgrader instance.
	 * @param array                $hook_extra Upgrade context.
	 * @return bool|WP_Error|string
	 */
	public function verify_package_download( $reply, $package, $upgrader, $hook_extra ) {
		unset( $upgrader );

		$plugin_file = plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE );
		$is_target   = isset( $hook_extra['plugin'] ) && $plugin_file === $hook_extra['plugin'];

		if ( ! $is_target && ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$is_target = in_array( $plugin_file, $hook_extra['plugins'], true );
		}

		if ( ! $is_target ) {
			return $reply;
		}

		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		$metadata = $this->get_remote_metadata();

		if ( empty( $metadata['download_url'] ) || empty( $metadata['package_sha256'] ) || ! hash_equals( $metadata['download_url'], (string) $package ) ) {
			return new WP_Error( 'cadv_update_untrusted_package', __( 'El paquete de actualizacion no coincide con los metadatos verificados.', 'cadv-woo-functionalities' ) );
		}

		$downloaded_by_plugin = ! is_string( $reply );

		if ( ! $downloaded_by_plugin ) {
			$temporary_file = $reply;
		} else {
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$temporary_file = download_url( $package, 300 );
		}

		if ( is_wp_error( $temporary_file ) || ! is_string( $temporary_file ) || ! is_file( $temporary_file ) ) {
			if ( is_wp_error( $temporary_file ) ) {
				return $temporary_file;
			}

			return new WP_Error( 'cadv_update_missing_package', __( 'No se pudo leer el paquete de actualizacion descargado.', 'cadv-woo-functionalities' ) );
		}

		$actual_hash = hash_file( 'sha256', $temporary_file );

		if ( ! is_string( $actual_hash ) || ! hash_equals( $metadata['package_sha256'], strtolower( $actual_hash ) ) ) {
			if ( $downloaded_by_plugin ) {
				wp_delete_file( $temporary_file );
			}
			return new WP_Error( 'cadv_update_hash_mismatch', __( 'La actualizacion fue rechazada porque la firma del paquete no coincide.', 'cadv-woo-functionalities' ) );
		}

		return $temporary_file;
	}
}
