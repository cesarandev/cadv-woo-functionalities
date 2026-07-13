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

		if ( empty( $endpoint ) ) {
			return array();
		}

		$cache_key = 'cadv_woo_functionalities_update_metadata_' . md5( $endpoint );
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
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$metadata = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $metadata ) ) {
			return array();
		}

		$metadata = $this->sanitize_metadata( $metadata );

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
		$endpoint = defined( 'CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER' ) ? CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER : '';

		/**
		 * Allows site owners to configure the private update metadata endpoint.
		 *
		 * @param string $endpoint Update endpoint URL.
		 */
		$endpoint = apply_filters( 'cadv_woo_functionalities_update_server', $endpoint );

		return esc_url_raw( $endpoint );
	}

	/**
	 * Sanitize remote update metadata.
	 *
	 * @param array $metadata Raw metadata.
	 * @return array
	 */
	private function sanitize_metadata( array $metadata ) {
		$version      = isset( $metadata['version'] ) ? sanitize_text_field( $metadata['version'] ) : '';
		$download_url = isset( $metadata['download_url'] ) ? esc_url_raw( $metadata['download_url'] ) : '';

		if ( empty( $version ) || empty( $download_url ) ) {
			return array();
		}

		return array(
			'version'      => $version,
			'download_url' => $download_url,
			'homepage'     => isset( $metadata['homepage'] ) ? esc_url_raw( $metadata['homepage'] ) : '',
			'requires'     => isset( $metadata['requires'] ) ? sanitize_text_field( $metadata['requires'] ) : '',
			'tested'       => isset( $metadata['tested'] ) ? sanitize_text_field( $metadata['tested'] ) : '',
			'requires_php' => isset( $metadata['requires_php'] ) ? sanitize_text_field( $metadata['requires_php'] ) : '',
			'description'  => isset( $metadata['description'] ) ? wp_kses_post( $metadata['description'] ) : '',
			'changelog'    => isset( $metadata['changelog'] ) ? wp_kses_post( $metadata['changelog'] ) : '',
		);
	}
}
