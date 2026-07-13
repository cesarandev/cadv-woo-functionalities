<?php
/**
 * Plugin Name: CADV Woo Functionalities
 * Plugin URI: https://cesarandev.com/
 * Description: Agrega botones comerciales, solicitudes de ficha tecnica, CTAs y CRM para WooCommerce.
 * Version: 1.1.14
 * Author: CADV
 * Author URI: https://cesarandev.com/
 * Text Domain: cadv-woo-functionalities
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * @package CADVWooFunctionalities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CADV_WOO_FUNCTIONALITIES_VERSION' ) ) {
	define( 'CADV_WOO_FUNCTIONALITIES_VERSION', '1.1.14' );
}

if ( ! defined( 'CADV_WOO_FUNCTIONALITIES_FILE' ) ) {
	define( 'CADV_WOO_FUNCTIONALITIES_FILE', __FILE__ );
}

if ( ! defined( 'CADV_WOO_FUNCTIONALITIES_DIR' ) ) {
	define( 'CADV_WOO_FUNCTIONALITIES_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'CADV_WOO_FUNCTIONALITIES_URL' ) ) {
	define( 'CADV_WOO_FUNCTIONALITIES_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER' ) ) {
	define( 'CADV_WOO_FUNCTIONALITIES_UPDATE_SERVER', '' );
}

require_once CADV_WOO_FUNCTIONALITIES_DIR . 'includes/class-cadv-woo-functionalities-updater.php';
require_once CADV_WOO_FUNCTIONALITIES_DIR . 'includes/class-cadv-woo-functionalities.php';
require_once CADV_WOO_FUNCTIONALITIES_DIR . 'includes/class-cadv-woo-functionalities-marketplace.php';

CADV_Woo_Functionalities::instance();
CADV_Woo_Functionalities_Updater::instance();
CADV_Woo_Functionalities_Marketplace::instance();
