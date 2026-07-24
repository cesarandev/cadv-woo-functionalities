<?php
/**
 * Plugin Name: CADV Tailored To Calculator
 * Plugin URI: https://cesarandev.com/
 * Description: Simulador técnico-comercial para construir solicitudes de fórmulas Tailored To y enviarlas al equipo Agrobrokers.
 * Version: 0.2.0
 * Author: CADV
 * Author URI: https://cesarandev.com/
 * Text Domain: cadv-tailored-to
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package CADVTailoredTo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CADV_TT_VERSION', '0.2.0' );
define( 'CADV_TT_FILE', __FILE__ );
define( 'CADV_TT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CADV_TT_URL', plugin_dir_url( __FILE__ ) );

require_once CADV_TT_DIR . 'includes/class-cadv-tt-formula-engine.php';
require_once CADV_TT_DIR . 'includes/class-cadv-tailored-to-calculator.php';

/**
 * Return the plugin singleton.
 *
 * @return CADV_Tailored_To_Calculator
 */
function cadv_tailored_to_calculator() {
	return CADV_Tailored_To_Calculator::instance();
}

add_action( 'plugins_loaded', 'cadv_tailored_to_calculator', 20 );
register_activation_hook( CADV_TT_FILE, array( 'CADV_Tailored_To_Calculator', 'activate' ) );
