<?php
/**
 * Main plugin class.
 *
 * @package CADVWooFunctionalities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CADV_Woo_Functionalities {
	const OPTION_PHONE            = 'cadv_woo_functionalities_whatsapp_phone';
	const OPTION_MESSAGE_TEMPLATE = 'cadv_woo_functionalities_message_template';
	const AJAX_ACTION             = 'cesarandev_wf_request_technical_sheet';
	const NONCE_ACTION            = 'cesarandev_wf_request_technical_sheet';
	const CTA_ACTION              = 'cesarandev_wf_submit_cta';
	const CTA_NONCE_ACTION        = 'cesarandev_wf_submit_cta';
	const EXPORT_ACTION           = 'cesarandev_wf_export_requests';
	const CRM_UPDATE_ACTION       = 'cesarandev_wf_update_crm_request';
	const CRM_LEAD_UPDATE_ACTION  = 'cesarandev_wf_update_crm_lead';
	const DELETE_ACCOUNT_ACTION   = 'cesarandev_wf_request_account_deletion';
	const DELETE_ACCOUNT_NONCE    = 'cesarandev_wf_request_account_deletion';
	const ORDER_SOURCE            = 'cesarandev_technical_sheet_request';
	const LEAD_POST_TYPE          = 'cesarandev_wf_lead';
	const MARKETPLACE_TERM_COLOR_META = '_cadv_marketplace_color';
	const DEFAULT_LINE_COLOR      = '#2f7d3a';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Products whose frontend action buttons were already rendered in this request.
	 *
	 * @var array
	 */
	private $rendered_action_products = array();

	/**
	 * Products whose modal was already rendered in this request.
	 *
	 * @var array
	 */
	private $rendered_modal_products = array();

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
	 * Hook plugin behavior.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_lead_post_type' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_migrate_settings' ), 5 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp', array( $this, 'replace_single_add_to_cart' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_technical_sheet_request' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_technical_sheet_request' ) );
		add_action( 'wp_ajax_' . self::CTA_ACTION, array( $this, 'handle_cta_submission' ) );
		add_action( 'wp_ajax_nopriv_' . self::CTA_ACTION, array( $this, 'handle_cta_submission' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'export_requests_csv' ) );
		add_action( 'admin_post_' . self::CRM_UPDATE_ACTION, array( $this, 'handle_crm_update' ) );
		add_action( 'admin_post_' . self::CRM_LEAD_UPDATE_ACTION, array( $this, 'handle_crm_lead_update' ) );
		add_action( 'admin_post_' . self::DELETE_ACCOUNT_ACTION, array( $this, 'handle_account_deletion_request' ) );
		add_shortcode( 'cesarandev_ficha_tecnica', array( $this, 'render_actions_shortcode' ) );
		add_shortcode( 'cesarandev_crm_cta', array( $this, 'render_crm_cta_shortcode' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE ), array( $this, 'add_plugin_action_links' ) );
		add_action( 'admin_notices', array( $this, 'render_woocommerce_notice' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'filter_restricted_account_menu' ), 20 );
		add_action( 'template_redirect', array( $this, 'redirect_restricted_account_endpoints' ) );
		add_action( 'woocommerce_account_edit-account_endpoint', array( $this, 'prepare_readonly_account_details' ), 1 );
		add_action( 'woocommerce_account_edit-account_endpoint', array( $this, 'render_readonly_account_details' ), 10 );
	}

	/**
	 * Copy settings from the previous internal option keys to the new CADV keys.
	 */
	public function maybe_migrate_settings() {
		$legacy_options = array(
			self::OPTION_PHONE            => $this->get_legacy_settings_key( 'whatsapp_phone' ),
			self::OPTION_MESSAGE_TEMPLATE => $this->get_legacy_settings_key( 'message_template' ),
		);

		foreach ( $legacy_options as $new_key => $legacy_key ) {
			$current_value = get_option( $new_key, null );

			if ( null !== $current_value && '' !== $current_value ) {
				continue;
			}

			$legacy_value = get_option( $legacy_key, null );

			if ( null !== $legacy_value && false !== $legacy_value ) {
				update_option( $new_key, $legacy_value );
			}
		}
	}

	/**
	 * Build the legacy option key without keeping the retired identifier as a literal.
	 *
	 * @param string $suffix Option suffix.
	 * @return string
	 */
	private function get_legacy_settings_key( $suffix ) {
		return implode( '_', array( 'cesarandev', 'woo', 'func', $suffix ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cadv-woo-functionalities', false, dirname( plugin_basename( CADV_WOO_FUNCTIONALITIES_FILE ) ) . '/languages' );
	}

	/**
	 * Register hidden CRM lead entity.
	 */
	public function register_lead_post_type() {
		register_post_type(
			self::LEAD_POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Leads CRM', 'cadv-woo-functionalities' ),
					'singular_name' => __( 'Lead CRM', 'cadv-woo-functionalities' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_menu'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Add admin settings page.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'CADV Woo Functionalities', 'cadv-woo-functionalities' ),
			__( 'CADV Woo Functionalities', 'cadv-woo-functionalities' ),
			'manage_woocommerce',
			'cadv-woo-functionalities',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'woocommerce',
			__( 'CRM Fichas Tecnicas', 'cadv-woo-functionalities' ),
			__( 'CRM Fichas Tecnicas', 'cadv-woo-functionalities' ),
			'manage_woocommerce',
			'cesarandev-wf-crm',
			array( $this, 'render_crm_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'cadv_woo_functionalities_settings',
			self::OPTION_PHONE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_phone' ),
				'default'           => '',
			)
		);

		register_setting(
			'cadv_woo_functionalities_settings',
			self::OPTION_MESSAGE_TEMPLATE,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $this->get_default_message_template(),
			)
		);

		add_settings_section(
			'cadv_woo_functionalities_whatsapp_section',
			__( 'Configuracion de WhatsApp', 'cadv-woo-functionalities' ),
			array( $this, 'render_settings_intro' ),
			'cadv-woo-functionalities'
		);

		add_settings_field(
			self::OPTION_PHONE,
			__( 'Numero de WhatsApp', 'cadv-woo-functionalities' ),
			array( $this, 'render_phone_field' ),
			'cadv-woo-functionalities',
			'cadv_woo_functionalities_whatsapp_section'
		);

		add_settings_field(
			self::OPTION_MESSAGE_TEMPLATE,
			__( 'Mensaje automatico', 'cadv-woo-functionalities' ),
			array( $this, 'render_message_template_field' ),
			'cadv-woo-functionalities',
			'cadv_woo_functionalities_whatsapp_section'
		);
	}

	/**
	 * Add settings shortcut in plugins page.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=cadv-woo-functionalities' );

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Ajustes', 'cadv-woo-functionalities' )
			)
		);

		return $links;
	}

	/**
	 * Render admin intro copy.
	 */
	public function render_settings_intro() {
		echo '<p>' . esc_html__( 'Configura el numero receptor y el texto que se enviara al consultar un producto.', 'cadv-woo-functionalities' ) . '</p>';
	}

	/**
	 * Render phone field.
	 */
	public function render_phone_field() {
		$value = get_option( self::OPTION_PHONE, '' );

		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="573001234567" />',
			esc_attr( self::OPTION_PHONE ),
			esc_attr( $value )
		);

		echo '<p class="description">' . esc_html__( 'Incluye codigo de pais y solo numeros. Ejemplo para Colombia: 573001234567.', 'cadv-woo-functionalities' ) . '</p>';
	}

	/**
	 * Render message template field.
	 */
	public function render_message_template_field() {
		$value = get_option( self::OPTION_MESSAGE_TEMPLATE, $this->get_default_message_template() );

		printf(
			'<textarea id="%1$s" name="%1$s" rows="4" class="large-text">%2$s</textarea>',
			esc_attr( self::OPTION_MESSAGE_TEMPLATE ),
			esc_textarea( $value )
		);

		echo '<p class="description">' . esc_html__( 'Puedes usar las variables {product_name} y {product_url}.', 'cadv-woo-functionalities' ) . '</p>';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CADV Woo Functionalities', 'cadv-woo-functionalities' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'cadv_woo_functionalities_settings' );
				do_settings_sections( 'cadv-woo-functionalities' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the CRM admin page.
	 */
	public function render_crm_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs    = array(
			'dashboard' => __( 'Dashboard', 'cadv-woo-functionalities' ),
			'requests'  => __( 'Solicitudes', 'cadv-woo-functionalities' ),
			'leads'     => __( 'Leads / CTAs', 'cadv-woo-functionalities' ),
			'customers' => __( 'Clientes', 'cadv-woo-functionalities' ),
			'deletions' => __( 'Eliminaciones', 'cadv-woo-functionalities' ),
		);
		$tab     = isset( $tabs[ $tab ] ) ? $tab : 'dashboard';
		$filters = $this->get_crm_filters();
		$rows    = $this->get_request_report_rows( -1, $filters );
		$leads   = $this->get_crm_lead_rows( $filters );

		if ( isset( $_GET['customer_id'] ) && 'customers' === $tab ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_crm_customer_detail( absint( $_GET['customer_id'] ), $rows ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$this->render_crm_styles();
		?>
		<div class="wrap cesarandev-wf-crm">
			<h1><?php esc_html_e( 'CRM Fichas Tecnicas', 'cadv-woo-functionalities' ); ?></h1>
			<?php $this->render_crm_notices(); ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $label ) : ?>
					<a class="nav-tab <?php echo $tab_key === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=' . $tab_key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'dashboard' === $tab ) {
				$this->render_crm_dashboard( $rows, $leads );
			} elseif ( 'customers' === $tab ) {
				$this->render_crm_customers( $rows );
			} elseif ( 'leads' === $tab ) {
				$this->render_crm_leads( $leads, $filters );
			} elseif ( 'deletions' === $tab ) {
				$this->render_crm_deletions( $this->get_request_report_rows( -1, array( 'delete_status' => 'pending' ) ) );
			} else {
				$this->render_crm_requests( $rows, $filters );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render small admin-only CRM styles.
	 */
	private function render_crm_styles() {
		?>
		<style>
			.cesarandev-wf-crm .cesarandev-wf-kpis{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin:18px 0}
			.cesarandev-wf-crm .cesarandev-wf-kpi{background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:14px}
			.cesarandev-wf-crm .cesarandev-wf-kpi strong{display:block;font-size:24px;line-height:1.2}
			.cesarandev-wf-crm .cesarandev-wf-filters{background:#fff;border:1px solid #dcdcde;display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin:16px 0;padding:14px}
			.cesarandev-wf-crm .cesarandev-wf-filters label{font-weight:600}
			.cesarandev-wf-crm .cesarandev-wf-filters input,.cesarandev-wf-crm .cesarandev-wf-filters select{margin-top:4px;width:100%}
			.cesarandev-wf-crm .cesarandev-wf-actions{display:flex;flex-wrap:wrap;gap:8px}
			.cesarandev-wf-crm .cesarandev-wf-inline-form{display:grid;gap:8px;min-width:220px}
			.cesarandev-wf-crm textarea{min-height:58px;width:100%}
			.cesarandev-wf-crm .cesarandev-wf-muted{color:#646970}
			.cesarandev-wf-crm .cesarandev-wf-badge{background:#f0f0f1;border-radius:999px;display:inline-block;padding:2px 8px}
			.cesarandev-wf-crm .cesarandev-wf-danger{color:#b32d2e;font-weight:700}
			.cesarandev-wf-crm .widefat td{vertical-align:top}
		</style>
		<?php
	}

	/**
	 * Render saved CRM notices.
	 */
	private function render_crm_notices() {
		if ( empty( $_GET['cesarandev_wf_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['cesarandev_wf_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$text   = 'saved' === $notice ? __( 'Cambios guardados.', 'cadv-woo-functionalities' ) : __( 'Accion registrada.', 'cadv-woo-functionalities' );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Render CRM dashboard metrics.
	 *
	 * @param array $rows  Report rows.
	 * @param array $leads Lead rows.
	 */
	private function render_crm_dashboard( array $rows, array $leads = array() ) {
		$stats = $this->get_crm_stats( $rows, $leads );
		?>
		<div class="cesarandev-wf-kpis">
			<?php foreach ( $stats as $label => $value ) : ?>
				<div class="cesarandev-wf-kpi">
					<strong><?php echo esc_html( $value ); ?></strong>
					<span><?php echo esc_html( $label ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="cesarandev-wf-actions">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=requests' ) ); ?>"><?php esc_html_e( 'Ver solicitudes', 'cadv-woo-functionalities' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=leads' ) ); ?>"><?php esc_html_e( 'Ver leads CTA', 'cadv-woo-functionalities' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Render requests tab.
	 *
	 * @param array $rows    Report rows.
	 * @param array $filters Active filters.
	 */
	private function render_crm_requests( array $rows, array $filters ) {
		$this->render_crm_filters( $filters );
		$this->render_crm_export_button( array_merge( $filters, array( 'export_scope' => 'requests' ) ) );
		$this->render_crm_requests_table( $rows, true );
	}

	/**
	 * Render CTA leads tab.
	 *
	 * @param array $leads   Lead rows.
	 * @param array $filters Active filters.
	 */
	private function render_crm_leads( array $leads, array $filters ) {
		$this->render_crm_lead_filters( $filters );
		$this->render_crm_export_button( array_merge( $filters, array( 'export_scope' => 'leads' ) ) );
		$this->render_crm_leads_table( $leads );
	}

	/**
	 * Render lead filters.
	 *
	 * @param array $filters Active filters.
	 */
	private function render_crm_lead_filters( array $filters ) {
		?>
		<form class="cesarandev-wf-filters" method="get">
			<input type="hidden" name="page" value="cesarandev-wf-crm" />
			<input type="hidden" name="tab" value="leads" />
			<label><?php esc_html_e( 'Nombre, correo o empresa', 'cadv-woo-functionalities' ); ?><input type="search" name="s" value="<?php echo esc_attr( $filters['s'] ); ?>" /></label>
			<label><?php esc_html_e( 'Tipo CTA', 'cadv-woo-functionalities' ); ?>
				<select name="cta_type">
					<option value=""><?php esc_html_e( 'Todos', 'cadv-woo-functionalities' ); ?></option>
					<option value="quote" <?php selected( $filters['cta_type'], 'quote' ); ?>><?php esc_html_e( 'Cotizacion', 'cadv-woo-functionalities' ); ?></option>
					<option value="newsletter" <?php selected( $filters['cta_type'], 'newsletter' ); ?>><?php esc_html_e( 'Newsletter', 'cadv-woo-functionalities' ); ?></option>
				</select>
			</label>
			<label><?php esc_html_e( 'Estado CRM', 'cadv-woo-functionalities' ); ?>
				<select name="crm_status">
					<option value=""><?php esc_html_e( 'Todos', 'cadv-woo-functionalities' ); ?></option>
					<?php foreach ( $this->get_crm_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['crm_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Producto de interes', 'cadv-woo-functionalities' ); ?><input type="text" name="product" value="<?php echo esc_attr( $filters['product'] ); ?>" /></label>
			<label><?php esc_html_e( 'Tipo de cultivo', 'cadv-woo-functionalities' ); ?><input type="text" name="crop_type" value="<?php echo esc_attr( $filters['crop_type'] ); ?>" /></label>
			<label><?php esc_html_e( 'Fuente', 'cadv-woo-functionalities' ); ?><input type="text" name="source" value="<?php echo esc_attr( $filters['source'] ); ?>" /></label>
			<label><?php esc_html_e( 'Convertido', 'cadv-woo-functionalities' ); ?>
				<select name="converted">
					<option value=""><?php esc_html_e( 'Todos', 'cadv-woo-functionalities' ); ?></option>
					<option value="yes" <?php selected( $filters['converted'], 'yes' ); ?>><?php esc_html_e( 'Si', 'cadv-woo-functionalities' ); ?></option>
					<option value="no" <?php selected( $filters['converted'], 'no' ); ?>><?php esc_html_e( 'No', 'cadv-woo-functionalities' ); ?></option>
				</select>
			</label>
			<label><?php esc_html_e( 'Desde', 'cadv-woo-functionalities' ); ?><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" /></label>
			<label><?php esc_html_e( 'Hasta', 'cadv-woo-functionalities' ); ?><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" /></label>
			<div class="cesarandev-wf-actions">
				<button class="button button-primary" type="submit"><?php esc_html_e( 'Filtrar', 'cadv-woo-functionalities' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=leads' ) ); ?>"><?php esc_html_e( 'Limpiar', 'cadv-woo-functionalities' ); ?></a>
			</div>
		</form>
		<?php
	}

	/**
	 * Render CTA leads table.
	 *
	 * @param array $leads Lead rows.
	 */
	private function render_crm_leads_table( array $leads ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Fecha', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Lead', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Contacto', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Empresa / Cargo', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Interes', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'CTA / Fuente', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'CRM', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Acciones', 'cadv-woo-functionalities' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $leads ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No hay leads con estos filtros.', 'cadv-woo-functionalities' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $leads as $lead ) : ?>
						<tr>
							<td><?php echo esc_html( $lead['last_contact_at_formatted'] ); ?></td>
							<td>
								<strong><?php echo esc_html( $lead['full_name'] ); ?></strong>
								<?php if ( $lead['converted_user_id'] ) : ?>
									<br /><span class="cesarandev-wf-badge"><?php esc_html_e( 'Convertido a usuario', 'cadv-woo-functionalities' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $lead['email'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $lead['phone'] ); ?></span></td>
							<td><?php echo esc_html( $lead['company'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $lead['position'] ); ?></span></td>
							<td><?php echo esc_html( $lead['product_interest'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $lead['crop_type'] ); ?></span></td>
							<td>
								<?php echo esc_html( $lead['cta_types_label'] ); ?>
								<?php if ( $lead['source_url'] ) : ?>
									<br /><a href="<?php echo esc_url( $lead['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver fuente', 'cadv-woo-functionalities' ); ?></a>
								<?php endif; ?>
								<br /><span class="cesarandev-wf-muted"><?php echo esc_html( sprintf( __( '%d interacciones', 'cadv-woo-functionalities' ), (int) $lead['interaction_count'] ) ); ?></span>
							</td>
							<td>
								<strong><?php echo esc_html( $lead['crm_status_label'] ); ?></strong>
								<?php if ( $lead['crm_note'] ) : ?>
									<br /><span class="cesarandev-wf-muted"><?php echo esc_html( wp_trim_words( $lead['crm_note'], 14 ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php $this->render_crm_lead_update_form( $lead ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render lead update form.
	 *
	 * @param array $lead Lead row.
	 */
	private function render_crm_lead_update_form( array $lead ) {
		?>
		<form class="cesarandev-wf-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CRM_LEAD_UPDATE_ACTION ); ?>" />
			<input type="hidden" name="lead_id" value="<?php echo esc_attr( $lead['lead_id'] ); ?>" />
			<?php wp_nonce_field( self::CRM_LEAD_UPDATE_ACTION ); ?>
			<select name="crm_status">
				<?php foreach ( $this->get_crm_statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $lead['crm_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<textarea name="crm_note" placeholder="<?php esc_attr_e( 'Nota interna', 'cadv-woo-functionalities' ); ?>"><?php echo esc_textarea( $lead['crm_note'] ); ?></textarea>
			<button class="button" type="submit"><?php esc_html_e( 'Guardar', 'cadv-woo-functionalities' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render CRM filters.
	 *
	 * @param array $filters Active filters.
	 */
	private function render_crm_filters( array $filters ) {
		?>
		<form class="cesarandev-wf-filters" method="get">
			<input type="hidden" name="page" value="cesarandev-wf-crm" />
			<input type="hidden" name="tab" value="requests" />
			<label><?php esc_html_e( 'Cliente, correo o texto', 'cadv-woo-functionalities' ); ?><input type="search" name="s" value="<?php echo esc_attr( $filters['s'] ); ?>" /></label>
			<label><?php esc_html_e( 'Empresa', 'cadv-woo-functionalities' ); ?><input type="text" name="company" value="<?php echo esc_attr( $filters['company'] ); ?>" /></label>
			<label><?php esc_html_e( 'Cargo', 'cadv-woo-functionalities' ); ?><input type="text" name="position" value="<?php echo esc_attr( $filters['position'] ); ?>" /></label>
			<label><?php esc_html_e( 'Telefono', 'cadv-woo-functionalities' ); ?><input type="text" name="phone" value="<?php echo esc_attr( $filters['phone'] ); ?>" /></label>
			<label><?php esc_html_e( 'Producto/ficha', 'cadv-woo-functionalities' ); ?><input type="text" name="product" value="<?php echo esc_attr( $filters['product'] ); ?>" /></label>
			<label><?php esc_html_e( 'Desde', 'cadv-woo-functionalities' ); ?><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" /></label>
			<label><?php esc_html_e( 'Hasta', 'cadv-woo-functionalities' ); ?><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" /></label>
			<label><?php esc_html_e( 'Estado CRM', 'cadv-woo-functionalities' ); ?>
				<select name="crm_status">
					<option value=""><?php esc_html_e( 'Todos', 'cadv-woo-functionalities' ); ?></option>
					<?php foreach ( $this->get_crm_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['crm_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Descarga', 'cadv-woo-functionalities' ); ?>
				<select name="download_status">
					<option value=""><?php esc_html_e( 'Todas', 'cadv-woo-functionalities' ); ?></option>
					<option value="downloaded" <?php selected( $filters['download_status'], 'downloaded' ); ?>><?php esc_html_e( 'Descargada', 'cadv-woo-functionalities' ); ?></option>
					<option value="not_downloaded" <?php selected( $filters['download_status'], 'not_downloaded' ); ?>><?php esc_html_e( 'No descargada', 'cadv-woo-functionalities' ); ?></option>
				</select>
			</label>
			<label><?php esc_html_e( 'Descargas minimas', 'cadv-woo-functionalities' ); ?><input type="number" min="0" name="min_downloads" value="<?php echo esc_attr( $filters['min_downloads'] ); ?>" /></label>
			<label><?php esc_html_e( 'Eliminacion', 'cadv-woo-functionalities' ); ?>
				<select name="delete_status">
					<option value=""><?php esc_html_e( 'Todas', 'cadv-woo-functionalities' ); ?></option>
					<option value="pending" <?php selected( $filters['delete_status'], 'pending' ); ?>><?php esc_html_e( 'Pendiente', 'cadv-woo-functionalities' ); ?></option>
					<option value="resolved" <?php selected( $filters['delete_status'], 'resolved' ); ?>><?php esc_html_e( 'Resuelta', 'cadv-woo-functionalities' ); ?></option>
				</select>
			</label>
			<div class="cesarandev-wf-actions">
				<button class="button button-primary" type="submit"><?php esc_html_e( 'Filtrar', 'cadv-woo-functionalities' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=requests' ) ); ?>"><?php esc_html_e( 'Limpiar', 'cadv-woo-functionalities' ); ?></a>
			</div>
		</form>
		<?php
	}

	/**
	 * Render export button preserving filters.
	 *
	 * @param array $filters Active filters.
	 */
	private function render_crm_export_button( array $filters ) {
		$args       = array_merge( array( 'action' => self::EXPORT_ACTION ), array_filter( $filters, 'strlen' ) );
		$export_url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::EXPORT_ACTION );
		?>
		<p><a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Descargar CSV filtrado', 'cadv-woo-functionalities' ); ?></a></p>
		<?php
	}

	/**
	 * Render requests table.
	 *
	 * @param array $rows         Report rows.
	 * @param bool  $show_actions Whether to include CRM forms.
	 */
	private function render_crm_requests_table( array $rows, $show_actions = false ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Fecha', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Cliente', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Contacto', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Empresa / Cargo', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Producto / Ficha', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Pedido', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Descarga', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'CRM', 'cadv-woo-functionalities' ); ?></th>
					<?php if ( $show_actions ) : ?>
						<th><?php esc_html_e( 'Acciones', 'cadv-woo-functionalities' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="<?php echo $show_actions ? '9' : '8'; ?>"><?php esc_html_e( 'No hay solicitudes con estos filtros.', 'cadv-woo-functionalities' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['request_date'] ); ?></td>
							<td>
								<strong><?php echo esc_html( $row['customer_name'] ); ?></strong>
								<?php if ( ! empty( $row['customer_id'] ) ) : ?>
									<br /><a href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=customers&customer_id=' . absint( $row['customer_id'] ) ) ); ?>"><?php esc_html_e( 'Ver cliente', 'cadv-woo-functionalities' ); ?></a>
								<?php endif; ?>
								<?php if ( 'pending' === $row['delete_request_status'] ) : ?>
									<br /><span class="cesarandev-wf-danger"><?php esc_html_e( 'Eliminacion solicitada', 'cadv-woo-functionalities' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['email'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $row['phone'] ); ?></span></td>
							<td><?php echo esc_html( $row['company'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $row['position'] ); ?></span></td>
							<td><?php echo esc_html( $row['product_name'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $row['download_name'] ); ?></span></td>
							<td>
								<?php if ( ! empty( $row['order_edit_url'] ) ) : ?>
									<a href="<?php echo esc_url( $row['order_edit_url'] ); ?>">#<?php echo esc_html( $row['order_id'] ); ?></a>
								<?php else : ?>
									#<?php echo esc_html( $row['order_id'] ); ?>
								<?php endif; ?>
							</td>
							<td>
								<span class="cesarandev-wf-badge"><?php echo esc_html( $row['status'] ); ?></span>
								<br /><?php echo esc_html( sprintf( __( '%d descargas', 'cadv-woo-functionalities' ), (int) $row['download_count'] ) ); ?>
								<?php if ( $row['last_download'] ) : ?>
									<br /><span class="cesarandev-wf-muted"><?php echo esc_html( $row['last_download'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( $row['crm_status_label'] ); ?></strong>
								<?php if ( $row['follow_up_at'] ) : ?>
									<br /><span class="cesarandev-wf-muted"><?php echo esc_html( sprintf( __( 'Seguimiento: %s', 'cadv-woo-functionalities' ), $row['follow_up_at'] ) ); ?></span>
								<?php endif; ?>
								<?php if ( $row['crm_note'] ) : ?>
									<br /><span class="cesarandev-wf-muted"><?php echo esc_html( wp_trim_words( $row['crm_note'], 14 ) ); ?></span>
								<?php endif; ?>
							</td>
							<?php if ( $show_actions ) : ?>
								<td><?php $this->render_crm_update_form( $row ); ?></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render per-order CRM update form.
	 *
	 * @param array $row Report row.
	 */
	private function render_crm_update_form( array $row ) {
		?>
		<form class="cesarandev-wf-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CRM_UPDATE_ACTION ); ?>" />
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $row['order_id'] ); ?>" />
			<?php wp_nonce_field( self::CRM_UPDATE_ACTION ); ?>
			<select name="crm_status">
				<?php foreach ( $this->get_crm_statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row['crm_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="follow_up_at" value="<?php echo esc_attr( $row['follow_up_at_raw'] ); ?>" />
			<select name="delete_request_status">
				<option value=""><?php esc_html_e( 'Sin solicitud de eliminacion', 'cadv-woo-functionalities' ); ?></option>
				<option value="pending" <?php selected( $row['delete_request_status'], 'pending' ); ?>><?php esc_html_e( 'Eliminacion pendiente', 'cadv-woo-functionalities' ); ?></option>
				<option value="resolved" <?php selected( $row['delete_request_status'], 'resolved' ); ?>><?php esc_html_e( 'Eliminacion resuelta', 'cadv-woo-functionalities' ); ?></option>
				<option value="rejected" <?php selected( $row['delete_request_status'], 'rejected' ); ?>><?php esc_html_e( 'Eliminacion rechazada', 'cadv-woo-functionalities' ); ?></option>
			</select>
			<textarea name="crm_note" placeholder="<?php esc_attr_e( 'Nota interna', 'cadv-woo-functionalities' ); ?>"><?php echo esc_textarea( $row['crm_note'] ); ?></textarea>
			<button class="button" type="submit"><?php esc_html_e( 'Guardar', 'cadv-woo-functionalities' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render customers tab.
	 *
	 * @param array $rows Report rows.
	 */
	private function render_crm_customers( array $rows ) {
		$customers = $this->group_rows_by_customer( $rows );
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Cliente', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Contacto', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Empresa / Cargo', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Fichas solicitadas', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Descargas', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Ultima solicitud', 'cadv-woo-functionalities' ); ?></th>
					<th><?php esc_html_e( 'Acciones', 'cadv-woo-functionalities' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $customers ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Aun no hay clientes registrados por fichas.', 'cadv-woo-functionalities' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $customers as $customer ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $customer['name'] ); ?></strong></td>
							<td><?php echo esc_html( $customer['email'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $customer['phone'] ); ?></span></td>
							<td><?php echo esc_html( $customer['company'] ); ?><br /><span class="cesarandev-wf-muted"><?php echo esc_html( $customer['position'] ); ?></span></td>
							<td><?php echo esc_html( $customer['requests'] ); ?></td>
							<td><?php echo esc_html( $customer['downloads'] ); ?></td>
							<td><?php echo esc_html( $customer['last_request'] ); ?></td>
							<td>
								<?php if ( $customer['customer_id'] ) : ?>
									<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=customers&customer_id=' . absint( $customer['customer_id'] ) ) ); ?>"><?php esc_html_e( 'Ver detalle', 'cadv-woo-functionalities' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render customer detail view.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $filtered_rows Current rows.
	 */
	private function render_crm_customer_detail( $customer_id, array $filtered_rows ) {
		$rows = array_filter(
			$this->get_request_report_rows( -1 ),
			function ( $row ) use ( $customer_id ) {
				return (int) $row['customer_id'] === (int) $customer_id;
			}
		);
		$user = get_userdata( $customer_id );
		$this->render_crm_styles();
		?>
		<div class="wrap cesarandev-wf-crm">
			<h1><?php esc_html_e( 'Detalle de cliente', 'cadv-woo-functionalities' ); ?></h1>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=customers' ) ); ?>"><?php esc_html_e( 'Volver a clientes', 'cadv-woo-functionalities' ); ?></a></p>
			<?php if ( $user instanceof WP_User ) : ?>
				<div class="cesarandev-wf-kpis">
					<div class="cesarandev-wf-kpi"><strong><?php echo esc_html( $user->display_name ); ?></strong><span><?php echo esc_html( $user->user_email ); ?></span></div>
					<div class="cesarandev-wf-kpi"><strong><?php echo esc_html( count( $rows ) ); ?></strong><span><?php esc_html_e( 'Registros de ficha', 'cadv-woo-functionalities' ); ?></span></div>
					<div class="cesarandev-wf-kpi"><strong><?php echo esc_html( array_sum( wp_list_pluck( $rows, 'download_count' ) ) ); ?></strong><span><?php esc_html_e( 'Descargas totales', 'cadv-woo-functionalities' ); ?></span></div>
				</div>
			<?php endif; ?>
			<?php $this->render_crm_requests_table( array_values( $rows ), true ); ?>
		</div>
		<?php
	}

	/**
	 * Render deletion requests tab.
	 *
	 * @param array $rows Deletion rows.
	 */
	private function render_crm_deletions( array $rows ) {
		echo '<p>' . esc_html__( 'Solicitudes de eliminacion creadas desde Mi cuenta. Se dejan pendientes para revision manual.', 'cadv-woo-functionalities' ) . '</p>';
		$this->render_crm_requests_table( $rows, true );
	}

	/**
	 * Replace WooCommerce add to cart on single product pages.
	 */
	public function replace_single_add_to_cart() {
		if ( ! $this->is_woocommerce_active() || ! is_product() ) {
			return;
		}

		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_single_product_buttons' ), 20 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_single_product_modal' ), 20 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_actions' ), 31 );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_woocommerce_active() || ! is_product() ) {
			return;
		}

		$this->enqueue_frontend_assets();
	}

	/**
	 * Enqueue frontend assets used by the buttons and modal.
	 */
	private function enqueue_frontend_assets() {
		wp_enqueue_style(
			'cadv-woo-functionalities-font',
			'https://fonts.googleapis.com/css2?family=Exo:wght@600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'cadv-woo-functionalities',
			CADV_WOO_FUNCTIONALITIES_URL . 'assets/css/cadv-woo-functionalities.css',
			array( 'cadv-woo-functionalities-font' ),
			CADV_WOO_FUNCTIONALITIES_VERSION
		);

		wp_enqueue_script(
			'cadv-woo-functionalities',
			CADV_WOO_FUNCTIONALITIES_URL . 'assets/js/cadv-woo-functionalities.js',
			array(),
			CADV_WOO_FUNCTIONALITIES_VERSION,
			true
		);

		wp_localize_script(
			'cadv-woo-functionalities',
			'CesarandevWooFunc',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => self::AJAX_ACTION,
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
				'ctaAction'   => self::CTA_ACTION,
				'ctaNonce'    => wp_create_nonce( self::CTA_NONCE_ACTION ),
				'isLoggedIn' => is_user_logged_in(),
				'messages' => array(
					'required' => __( 'Completa todos los campos obligatorios.', 'cadv-woo-functionalities' ),
					'email'    => __( 'Ingresa un correo electronico valido.', 'cadv-woo-functionalities' ),
					'loading'  => __( 'Enviando solicitud...', 'cadv-woo-functionalities' ),
					'error'    => __( 'No se pudo registrar la solicitud. Intentalo de nuevo.', 'cadv-woo-functionalities' ),
					'privacy'  => __( 'Debes aceptar la politica de privacidad.', 'cadv-woo-functionalities' ),
				),
			)
		);
	}

	/**
	 * Render custom actions on single product pages.
	 */
	public function render_single_product_actions() {
		$this->render_single_product_buttons();
		$this->render_single_product_modal();
	}

	/**
	 * Render product action buttons via shortcode.
	 *
	 * Usage: [cesarandev_ficha_tecnica]
	 * Optional fallback: [cesarandev_ficha_tecnica product_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_actions_shortcode( $atts = array() ) {
		if ( ! $this->is_woocommerce_active() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'cesarandev_ficha_tecnica'
		);

		$product = $this->resolve_shortcode_product( absint( $atts['product_id'] ) );

		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$this->enqueue_frontend_assets();

		ob_start();
		$this->render_product_buttons( $product, false );
		$this->render_product_modal( $product );
		return ob_get_clean();
	}

	/**
	 * Render CRM CTA form via shortcode.
	 *
	 * Usage: [cesarandev_crm_cta type="quote"]
	 * Usage: [cesarandev_crm_cta type="newsletter"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_crm_cta_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'type'        => 'quote',
				'title'       => '',
				'eyebrow'     => '',
				'description' => '',
				'privacy_url' => '',
			),
			$atts,
			'cesarandev_crm_cta'
		);

		$type = $this->normalize_cta_type( $atts['type'] );

		if ( ! $type ) {
			return '';
		}

		$this->enqueue_frontend_assets();

		$is_newsletter = 'newsletter' === $type;
		$eyebrow       = $atts['eyebrow'] ? $atts['eyebrow'] : ( $is_newsletter ? __( 'Newsletter', 'cadv-woo-functionalities' ) : __( 'Cotizacion', 'cadv-woo-functionalities' ) );
		$title         = $atts['title'] ? $atts['title'] : ( $is_newsletter ? __( 'Registrate al newsletter', 'cadv-woo-functionalities' ) : __( 'Solicitar cotizacion', 'cadv-woo-functionalities' ) );
		$description   = $atts['description'] ? $atts['description'] : ( $is_newsletter ? __( 'Recibe informacion tecnica y novedades comerciales.', 'cadv-woo-functionalities' ) : __( 'Cuentenos sobre su cultivo y nuestro equipo tecnico-comercial le respondera.', 'cadv-woo-functionalities' ) );
		$privacy_url   = $atts['privacy_url'] ? esc_url_raw( $atts['privacy_url'] ) : get_privacy_policy_url();
		$categories    = $this->get_product_interest_options();

		ob_start();
		?>
		<div class="cesarandev-wf-cta" data-cesarandev-wf-cta>
			<div class="cesarandev-wf-cta__header">
				<span class="cesarandev-wf-cta__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $description ); ?></p>
			</div>
			<form class="cesarandev-wf-cta__form" data-cesarandev-wf-cta-form novalidate>
				<input type="hidden" name="cta_type" value="<?php echo esc_attr( $type ); ?>" />
				<input type="hidden" name="source_url" value="<?php echo esc_url( $this->get_current_url() ); ?>" />

				<label class="cesarandev-wf-cta__field cesarandev-wf-cta__field--full">
					<span><?php esc_html_e( 'Nombre completo *', 'cadv-woo-functionalities' ); ?></span>
					<input type="text" name="full_name" autocomplete="name" placeholder="<?php esc_attr_e( 'Nombre y apellidos', 'cadv-woo-functionalities' ); ?>" required />
				</label>

				<?php if ( ! $is_newsletter ) : ?>
					<label class="cesarandev-wf-cta__field">
						<span><?php esc_html_e( 'Empresa *', 'cadv-woo-functionalities' ); ?></span>
						<input type="text" name="company" autocomplete="organization" placeholder="<?php esc_attr_e( 'Razon social', 'cadv-woo-functionalities' ); ?>" required />
					</label>
					<label class="cesarandev-wf-cta__field">
						<span><?php esc_html_e( 'Cargo *', 'cadv-woo-functionalities' ); ?></span>
						<input type="text" name="position" autocomplete="organization-title" placeholder="<?php esc_attr_e( 'Ej. Gerente de compras', 'cadv-woo-functionalities' ); ?>" required />
					</label>
					<label class="cesarandev-wf-cta__field">
						<span><?php esc_html_e( 'Telefono *', 'cadv-woo-functionalities' ); ?></span>
						<input type="tel" name="phone" autocomplete="tel" placeholder="+57 ___ ___ ____" required />
					</label>
				<?php else : ?>
					<label class="cesarandev-wf-cta__field">
						<span><?php esc_html_e( 'Empresa', 'cadv-woo-functionalities' ); ?></span>
						<input type="text" name="company" autocomplete="organization" placeholder="<?php esc_attr_e( 'Razon social', 'cadv-woo-functionalities' ); ?>" />
					</label>
				<?php endif; ?>

				<label class="cesarandev-wf-cta__field">
					<span><?php esc_html_e( 'Correo electronico *', 'cadv-woo-functionalities' ); ?></span>
					<input type="email" name="email" autocomplete="email" placeholder="correo@empresa.com" required />
				</label>

				<?php if ( ! $is_newsletter ) : ?>
					<label class="cesarandev-wf-cta__field">
						<span><?php esc_html_e( 'Producto de interes', 'cadv-woo-functionalities' ); ?></span>
						<select name="product_interest">
							<option value=""><?php esc_html_e( 'Seleccione una familia', 'cadv-woo-functionalities' ); ?></option>
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( $category['value'] ); ?>"><?php echo esc_html( $category['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>

				<label class="cesarandev-wf-cta__field">
					<span><?php esc_html_e( 'Tipo de cultivo', 'cadv-woo-functionalities' ); ?></span>
					<input type="text" name="crop_type" placeholder="<?php esc_attr_e( 'Palma, banano, arroz, maiz, otro', 'cadv-woo-functionalities' ); ?>" />
				</label>

				<label class="cesarandev-wf-cta__privacy cesarandev-wf-cta__field--full">
					<input type="checkbox" name="privacy_acceptance" value="1" required />
					<span>
						<?php esc_html_e( 'Al enviar acepto la', 'cadv-woo-functionalities' ); ?>
						<?php if ( $privacy_url ) : ?>
							<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Politica de Privacidad', 'cadv-woo-functionalities' ); ?></a>
						<?php else : ?>
							<?php esc_html_e( 'Politica de Privacidad', 'cadv-woo-functionalities' ); ?>
						<?php endif; ?>
						<?php esc_html_e( 'y el Tratamiento de Datos conforme a la Ley 1581 de 2012.', 'cadv-woo-functionalities' ); ?>
					</span>
				</label>

				<div class="cesarandev-wf-cta__message cesarandev-wf-form__message" data-cesarandev-wf-cta-message role="status" aria-live="polite"></div>
				<button class="cesarandev-wf-cta__submit" type="submit"><?php echo esc_html( $is_newsletter ? __( 'Registrarme', 'cadv-woo-functionalities' ) : __( 'Enviar solicitud', 'cadv-woo-functionalities' ) ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Normalize CTA type aliases.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	private function normalize_cta_type( $type ) {
		$type = sanitize_key( $type );

		if ( in_array( $type, array( 'quote', 'cotizacion', 'cotizar' ), true ) ) {
			return 'quote';
		}

		if ( in_array( $type, array( 'newsletter', 'news' ), true ) ) {
			return 'newsletter';
		}

		return '';
	}

	/**
	 * Get current URL for lead source tracking.
	 *
	 * @return string
	 */
	private function get_current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		return $host ? $scheme . $host . $uri : home_url( '/' );
	}

	/**
	 * Get WooCommerce product categories as product interest options.
	 *
	 * @return array
	 */
	private function get_product_interest_options() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			$options[] = array(
				'value' => $term->name,
				'label' => $term->name,
			);
		}

		return $options;
	}

	/**
	 * Render custom action buttons on single product pages.
	 */
	public function render_single_product_buttons() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$this->render_product_buttons( $product, true );
	}

	/**
	 * Render buttons for a product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @param bool       $dedupe  Whether to avoid repeated rendering.
	 */
	private function render_product_buttons( WC_Product $product, $dedupe = true ) {
		$product_id = $product->get_id();

		if ( $dedupe && isset( $this->rendered_action_products[ $product_id ] ) ) {
			return;
		}

		if ( $dedupe ) {
			$this->rendered_action_products[ $product_id ] = true;
		}

		echo '<style>.single-product form.cart .quantity,.single-product form.cart .single_add_to_cart_button{display:none!important}.single-product form.cart{margin-bottom:0}</style>';
		echo '<div class="cesarandev-wf-product-actions" style="clear:both;display:flex;flex-wrap:wrap;gap:10px;margin:14px 0 20px;width:100%;">';
		$this->render_whatsapp_button( $product );
		$this->render_technical_sheet_button( $product );
		echo '</div>';
	}

	/**
	 * Render custom modal on single product pages.
	 */
	public function render_single_product_modal() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$this->render_product_modal( $product );
	}

	/**
	 * Render modal for a product once per request.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function render_product_modal( WC_Product $product ) {
		$product_id = $product->get_id();

		if ( isset( $this->rendered_modal_products[ $product_id ] ) ) {
			return;
		}

		$this->rendered_modal_products[ $product_id ] = true;
		$this->render_technical_sheet_modal( $product );
	}

	/**
	 * Resolve the product for the shortcode.
	 *
	 * @param int $fallback_product_id Optional product ID.
	 * @return WC_Product|null
	 */
	private function resolve_shortcode_product( $fallback_product_id = 0 ) {
		global $product, $post;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		if ( is_product() ) {
			$current_product = wc_get_product( get_the_ID() );

			if ( $current_product instanceof WC_Product ) {
				return $current_product;
			}
		}

		if ( $post instanceof WP_Post && 'product' === $post->post_type ) {
			$current_product = wc_get_product( $post->ID );

			if ( $current_product instanceof WC_Product ) {
				return $current_product;
			}
		}

		if ( $fallback_product_id ) {
			$current_product = wc_get_product( $fallback_product_id );

			if ( $current_product instanceof WC_Product ) {
				return $current_product;
			}
		}

		return null;
	}

	/**
	 * Render WhatsApp button for a product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function render_whatsapp_button( WC_Product $product ) {
		$url = $this->get_whatsapp_url( $product );

		if ( empty( $url ) ) {
			return;
		}

		printf(
			'<a class="cesarandev-wf-button cesarandev-wf-whatsapp-button" style="align-items:center;background:#25d366;border:0;border-radius:6px;box-shadow:none;color:#fff;display:inline-flex;font-size:15px;font-weight:700;gap:8px;justify-content:center;line-height:1.2;min-height:44px;padding:12px 16px;text-decoration:none;width:auto;" href="%1$s" target="_blank" rel="noopener noreferrer" aria-label="%2$s">%3$s<span>%4$s</span></a>',
			esc_url( $url ),
			esc_attr__( 'Consultar este producto por WhatsApp', 'cadv-woo-functionalities' ),
			$this->get_whatsapp_icon_svg(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Consultar aqui', 'cadv-woo-functionalities' )
		);
	}

	/**
	 * Render technical sheet trigger button.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function render_technical_sheet_button( WC_Product $product ) {
		printf(
			'<button type="button" class="cesarandev-wf-button cesarandev-wf-sheet-button" style="align-items:center;background:#fff;border:1px solid #1f4f46;border-radius:6px;box-shadow:none;color:#1f4f46;cursor:pointer;display:inline-flex;font-size:15px;font-weight:700;gap:8px;justify-content:center;line-height:1.2;min-height:44px;padding:12px 16px;text-decoration:none;width:auto;" data-cesarandev-wf-open-modal data-product-id="%1$d">%2$s<span>%3$s</span></button>',
			absint( $product->get_id() ),
			$this->get_document_icon_svg(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Ver ficha tecnica', 'cadv-woo-functionalities' )
		);
	}

	/**
	 * Render technical sheet request modal.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function render_technical_sheet_modal( WC_Product $product ) {
		$product_id   = $product->get_id();
		$product_name = $product->get_name();
		$defaults     = $this->get_current_user_form_defaults();
		$privacy_url  = get_privacy_policy_url();
		$whatsapp_url = $this->get_whatsapp_url( $product );
		$line_color   = $this->get_product_line_color( $product );
		?>
		<div class="cesarandev-wf-modal" data-cesarandev-wf-modal style="--cesarandev-wf-accent: <?php echo esc_attr( $line_color ); ?>;" hidden>
			<div class="cesarandev-wf-modal__overlay" data-cesarandev-wf-close-modal></div>
			<div class="cesarandev-wf-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cesarandev-wf-modal-title">
				<button type="button" class="cesarandev-wf-modal__close" data-cesarandev-wf-close-modal aria-label="<?php esc_attr_e( 'Cerrar', 'cadv-woo-functionalities' ); ?>">&times;</button>
				<div class="cesarandev-wf-modal__header">
					<p class="cesarandev-wf-modal__eyebrow"><?php esc_html_e( 'Descargue la ficha tecnica de:', 'cadv-woo-functionalities' ); ?></p>
					<h2 id="cesarandev-wf-modal-title"><?php echo esc_html( $product_name ); ?></h2>
					<p><?php esc_html_e( 'Para enviarle el documento necesitamos sus datos de contacto.', 'cadv-woo-functionalities' ); ?></p>
				</div>
				<form class="cesarandev-wf-form" data-cesarandev-wf-form novalidate>
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />

					<label>
						<span><?php esc_html_e( 'Nombre completo *', 'cadv-woo-functionalities' ); ?></span>
						<input type="text" name="full_name" autocomplete="name" value="<?php echo esc_attr( $defaults['full_name'] ); ?>" required />
					</label>

					<label>
						<span><?php esc_html_e( 'Empresa *', 'cadv-woo-functionalities' ); ?></span>
						<input type="text" name="company" autocomplete="organization" value="<?php echo esc_attr( $defaults['company'] ); ?>" required />
					</label>

					<label>
						<span><?php esc_html_e( 'Cargo *', 'cadv-woo-functionalities' ); ?></span>
						<input type="text" name="position" autocomplete="organization-title" value="<?php echo esc_attr( $defaults['position'] ); ?>" required />
					</label>

					<label>
						<span><?php esc_html_e( 'Correo electronico *', 'cadv-woo-functionalities' ); ?></span>
						<input type="email" name="email" autocomplete="email" placeholder="correo@empresa.com" value="<?php echo esc_attr( $defaults['email'] ); ?>" <?php echo $defaults['logged_in'] ? 'readonly="readonly"' : ''; ?> required />
					</label>

					<label>
						<span><?php esc_html_e( 'Telefono *', 'cadv-woo-functionalities' ); ?></span>
						<input type="tel" name="phone" autocomplete="tel" value="<?php echo esc_attr( $defaults['phone'] ); ?>" required />
					</label>

					<label class="cesarandev-wf-form__privacy">
						<input type="checkbox" name="privacy_acceptance" value="1" required />
						<span>
							<?php esc_html_e( 'Acepto la', 'cadv-woo-functionalities' ); ?>
							<?php if ( $privacy_url ) : ?>
								<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Politica de Privacidad', 'cadv-woo-functionalities' ); ?></a>
							<?php else : ?>
								<?php esc_html_e( 'Politica de Privacidad', 'cadv-woo-functionalities' ); ?>
							<?php endif; ?>
							<?php esc_html_e( 'y el tratamiento de mis datos conforme a la Ley 1581 de 2012. *', 'cadv-woo-functionalities' ); ?>
						</span>
					</label>

					<div class="cesarandev-wf-form__message" data-cesarandev-wf-message role="status" aria-live="polite"></div>
					<button type="submit" class="cesarandev-wf-form__submit"><?php esc_html_e( 'Enviar y descargar ficha', 'cadv-woo-functionalities' ); ?><span aria-hidden="true">-&gt;</span></button>
					<?php if ( $whatsapp_url ) : ?>
						<div class="cesarandev-wf-modal__divider"><span><?php esc_html_e( 'Prefiere hablar directamente?', 'cadv-woo-functionalities' ); ?></span></div>
						<a class="cesarandev-wf-modal__whatsapp" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo $this->get_whatsapp_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php esc_html_e( 'Escribir por WhatsApp', 'cadv-woo-functionalities' ); ?></span>
						</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Get form defaults from the current logged-in user.
	 *
	 * @return array
	 */
	private function get_current_user_form_defaults() {
		if ( ! is_user_logged_in() ) {
			return array(
				'logged_in'  => false,
				'full_name'  => '',
				'company'    => '',
				'position'   => '',
				'email'      => '',
				'phone'      => '',
			);
		}

		$user      = wp_get_current_user();
		$full_name = trim( trim( (string) $user->first_name ) . ' ' . trim( (string) $user->last_name ) );

		if ( '' === $full_name ) {
			$full_name = $user->display_name;
		}

		return array(
			'logged_in' => true,
			'full_name' => $full_name,
			'company'   => (string) ( get_user_meta( $user->ID, 'billing_company', true ) ?: get_user_meta( $user->ID, 'cesarandev_wf_company', true ) ),
			'position'  => (string) get_user_meta( $user->ID, 'cesarandev_wf_position', true ),
			'email'     => $user->user_email,
			'phone'     => (string) get_user_meta( $user->ID, 'billing_phone', true ),
		);
	}

	/**
	 * Handle technical sheet request.
	 */
	public function handle_technical_sheet_request() {
		if ( ! $this->is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce no esta activo.', 'cadv-woo-functionalities' ) ), 400 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La solicitud no es valida. Recarga la pagina e intentalo de nuevo.', 'cadv-woo-functionalities' ) ), 403 );
		}

		$data       = $this->normalize_request_data_for_user( $this->hydrate_technical_sheet_data_from_lead( $this->get_request_data() ) );
		$validation = $this->validate_request_data( $data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ), 400 );
		}

		$product = wc_get_product( $data['product_id'] );

		if ( ! $product || ! $product->exists() ) {
			wp_send_json_error( array( 'message' => __( 'El producto no existe.', 'cadv-woo-functionalities' ) ), 404 );
		}

		if ( ! $this->product_has_downloads( $product ) ) {
			wp_send_json_error( array( 'message' => __( 'Este producto no tiene una ficha tecnica descargable configurada.', 'cadv-woo-functionalities' ) ), 400 );
		}

		$user_id = $this->get_or_create_customer( $data );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 400 );
		}

		$this->update_customer_b2b_data( $user_id, $data );
		$this->mark_lead_converted_by_email( $data['email'], $user_id );

		if ( $this->customer_has_product_download_access( $user_id, $product ) ) {
			wp_send_json_success(
				array(
					'message'      => __( 'Esta ficha tecnica ya esta disponible para este correo en tu zona de cliente.', 'cadv-woo-functionalities' ),
					'downloadsUrl' => wc_get_account_endpoint_url( 'downloads' ),
					'existing'     => true,
				)
			);
		}

		$order_id = $this->create_technical_sheet_order( $user_id, $product, $data );

		if ( is_wp_error( $order_id ) ) {
			wp_send_json_error( array( 'message' => $order_id->get_error_message() ), 500 );
		}

		if ( is_user_logged_in() ) {
			$this->send_logged_in_sheet_notification( $user_id, $product );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Tu solicitud fue registrada. Ya puedes acceder a la ficha tecnica desde tu zona de cliente.', 'cadv-woo-functionalities' ),
				'downloadsUrl' => wc_get_account_endpoint_url( 'downloads' ),
			)
		);
	}

	/**
	 * Handle public CTA submissions.
	 */
	public function handle_cta_submission() {
		if ( ! check_ajax_referer( self::CTA_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La solicitud no es valida. Recarga la pagina e intentalo de nuevo.', 'cadv-woo-functionalities' ) ), 403 );
		}

		$data       = $this->get_cta_request_data();
		$validation = $this->validate_cta_request_data( $data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ), 400 );
		}

		$lead_id = $this->upsert_crm_lead( $data );

		if ( is_wp_error( $lead_id ) ) {
			wp_send_json_error( array( 'message' => $lead_id->get_error_message() ), 500 );
		}

		wp_send_json_success(
			array(
				'message' => 'newsletter' === $data['cta_type'] ? __( 'Registro recibido. Gracias por suscribirte.', 'cadv-woo-functionalities' ) : __( 'Solicitud recibida. Nuestro equipo tecnico-comercial te contactara pronto.', 'cadv-woo-functionalities' ),
			)
		);
	}

	/**
	 * Get sanitized CTA request data.
	 *
	 * @return array
	 */
	private function get_cta_request_data() {
		return array(
			'cta_type'           => isset( $_POST['cta_type'] ) ? $this->normalize_cta_type( wp_unslash( $_POST['cta_type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'full_name'          => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'company'            => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'position'           => isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'phone'              => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email'              => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'product_interest'   => isset( $_POST['product_interest'] ) ? sanitize_text_field( wp_unslash( $_POST['product_interest'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'crop_type'          => isset( $_POST['crop_type'] ) ? sanitize_text_field( wp_unslash( $_POST['crop_type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'source_url'         => isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : $this->get_current_url(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'privacy_acceptance' => ! empty( $_POST['privacy_acceptance'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
	}

	/**
	 * Validate CTA request data.
	 *
	 * @param array $data CTA data.
	 * @return true|WP_Error
	 */
	private function validate_cta_request_data( array $data ) {
		if ( empty( $data['cta_type'] ) ) {
			return new WP_Error( 'cesarandev_wf_invalid_cta_type', __( 'El tipo de formulario no es valido.', 'cadv-woo-functionalities' ) );
		}

		foreach ( array( 'full_name', 'email' ) as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'cesarandev_wf_cta_missing_data', __( 'Completa todos los campos obligatorios.', 'cadv-woo-functionalities' ) );
			}
		}

		if ( 'quote' === $data['cta_type'] ) {
			foreach ( array( 'company', 'position', 'phone' ) as $field ) {
				if ( empty( $data[ $field ] ) ) {
					return new WP_Error( 'cesarandev_wf_cta_missing_quote_data', __( 'Completa todos los campos obligatorios.', 'cadv-woo-functionalities' ) );
				}
			}
		}

		if ( ! is_email( $data['email'] ) ) {
			return new WP_Error( 'cesarandev_wf_cta_invalid_email', __( 'Ingresa un correo electronico valido.', 'cadv-woo-functionalities' ) );
		}

		if ( empty( $data['privacy_acceptance'] ) ) {
			return new WP_Error( 'cesarandev_wf_cta_privacy_required', __( 'Debes aceptar la politica de privacidad.', 'cadv-woo-functionalities' ) );
		}

		return true;
	}

	/**
	 * Create or update a CRM lead by email.
	 *
	 * @param array $data Lead data.
	 * @return int|WP_Error
	 */
	private function upsert_crm_lead( array $data ) {
		$lead_id = $this->find_crm_lead_id_by_email( $data['email'] );
		$is_new  = ! $lead_id;

		if ( $is_new ) {
			$lead_id = wp_insert_post(
				array(
					'post_type'   => self::LEAD_POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $data['email'],
				),
				true
			);

			if ( is_wp_error( $lead_id ) ) {
				return $lead_id;
			}

			update_post_meta( $lead_id, '_cesarandev_wf_crm_status', 'new' );
			update_post_meta( $lead_id, '_cesarandev_wf_created_at', current_time( 'mysql' ) );
		}

		$existing_types = (array) get_post_meta( $lead_id, '_cesarandev_wf_cta_types', true );
		$existing_types[] = $data['cta_type'];
		$existing_types = array_values( array_unique( array_filter( $existing_types ) ) );
		$interactions   = (array) get_post_meta( $lead_id, '_cesarandev_wf_interactions', true );
		$interactions[] = array(
			'type'             => $data['cta_type'],
			'date'             => current_time( 'mysql' ),
			'source_url'       => $data['source_url'],
			'product_interest' => $data['product_interest'],
			'crop_type'        => $data['crop_type'],
		);

		$fields = array(
			'_cesarandev_wf_full_name'        => $data['full_name'],
			'_cesarandev_wf_company'          => $data['company'],
			'_cesarandev_wf_position'         => $data['position'],
			'_cesarandev_wf_phone'            => $data['phone'],
			'_cesarandev_wf_email'            => $data['email'],
			'_cesarandev_wf_product_interest' => $data['product_interest'],
			'_cesarandev_wf_crop_type'        => $data['crop_type'],
			'_cesarandev_wf_source_url'       => $data['source_url'],
			'_cesarandev_wf_last_cta_type'    => $data['cta_type'],
			'_cesarandev_wf_last_contact_at'  => current_time( 'mysql' ),
			'_cesarandev_wf_cta_types'        => $existing_types,
			'_cesarandev_wf_interactions'     => $interactions,
		);

		foreach ( $fields as $meta_key => $value ) {
			update_post_meta( $lead_id, $meta_key, $value );
		}

		return (int) $lead_id;
	}

	/**
	 * Find CRM lead by email.
	 *
	 * @param string $email Email address.
	 * @return int
	 */
	private function find_crm_lead_id_by_email( $email ) {
		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return 0;
		}

		$leads = get_posts(
			array(
				'post_type'      => self::LEAD_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_cesarandev_wf_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return ! empty( $leads ) ? (int) $leads[0] : 0;
	}

	/**
	 * Get lead data by email.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	private function get_crm_lead_data_by_email( $email ) {
		$lead_id = $this->find_crm_lead_id_by_email( $email );

		if ( ! $lead_id ) {
			return array();
		}

		return array(
			'lead_id'          => $lead_id,
			'full_name'        => (string) get_post_meta( $lead_id, '_cesarandev_wf_full_name', true ),
			'company'          => (string) get_post_meta( $lead_id, '_cesarandev_wf_company', true ),
			'position'         => (string) get_post_meta( $lead_id, '_cesarandev_wf_position', true ),
			'phone'            => (string) get_post_meta( $lead_id, '_cesarandev_wf_phone', true ),
			'email'            => (string) get_post_meta( $lead_id, '_cesarandev_wf_email', true ),
			'product_interest' => (string) get_post_meta( $lead_id, '_cesarandev_wf_product_interest', true ),
			'crop_type'        => (string) get_post_meta( $lead_id, '_cesarandev_wf_crop_type', true ),
		);
	}

	/**
	 * Fill missing technical-sheet data from a prior CRM lead.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	private function hydrate_technical_sheet_data_from_lead( array $data ) {
		if ( empty( $data['email'] ) || is_user_logged_in() ) {
			return $data;
		}

		$lead = $this->get_crm_lead_data_by_email( $data['email'] );

		if ( empty( $lead ) ) {
			return $data;
		}

		foreach ( array( 'full_name', 'company', 'position', 'phone' ) as $field ) {
			if ( empty( $data[ $field ] ) && ! empty( $lead[ $field ] ) ) {
				$data[ $field ] = $lead[ $field ];
			}
		}

		return $data;
	}

	/**
	 * Mark a lead as converted after a WordPress customer exists.
	 *
	 * @param string $email   Email address.
	 * @param int    $user_id User ID.
	 */
	private function mark_lead_converted_by_email( $email, $user_id ) {
		$lead_id = $this->find_crm_lead_id_by_email( $email );

		if ( ! $lead_id ) {
			return;
		}

		update_post_meta( $lead_id, '_cesarandev_wf_converted_user_id', absint( $user_id ) );
		update_post_meta( $lead_id, '_cesarandev_wf_converted_at', current_time( 'mysql' ) );
		update_post_meta( $lead_id, '_cesarandev_wf_crm_status', 'converted' );
	}

	/**
	 * Get sanitized request data.
	 *
	 * @return array
	 */
	private function get_request_data() {
		return array(
			'product_id' => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'full_name'  => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'company'    => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'position'   => isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'phone'      => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'privacy_acceptance' => ! empty( $_POST['privacy_acceptance'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
	}

	/**
	 * Normalize submitted data when a customer is already logged in.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	private function normalize_request_data_for_user( array $data ) {
		if ( ! is_user_logged_in() ) {
			return $data;
		}

		$user = wp_get_current_user();

		if ( $user instanceof WP_User && $user->exists() ) {
			$data['email'] = $user->user_email;

			if ( empty( $data['full_name'] ) ) {
				$data['full_name'] = $user->display_name;
			}
		}

		return $data;
	}

	/**
	 * Validate request data.
	 *
	 * @param array $data Request data.
	 * @return true|WP_Error
	 */
	private function validate_request_data( array $data ) {
		foreach ( array( 'product_id', 'full_name', 'company', 'position', 'email', 'phone' ) as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'cesarandev_wf_missing_data', __( 'Completa todos los campos obligatorios.', 'cadv-woo-functionalities' ) );
			}
		}

		if ( ! is_email( $data['email'] ) ) {
			return new WP_Error( 'cesarandev_wf_invalid_email', __( 'Ingresa un correo electronico valido.', 'cadv-woo-functionalities' ) );
		}

		if ( empty( $data['privacy_acceptance'] ) ) {
			return new WP_Error( 'cesarandev_wf_privacy_required', __( 'Debes aceptar la politica de privacidad.', 'cadv-woo-functionalities' ) );
		}

		return true;
	}

	/**
	 * Get an existing customer by email or create a new one.
	 *
	 * @param array $data Request data.
	 * @return int|WP_Error
	 */
	private function get_or_create_customer( array $data ) {
		if ( is_user_logged_in() ) {
			return get_current_user_id();
		}

		$existing_user = get_user_by( 'email', $data['email'] );

		if ( $existing_user instanceof WP_User ) {
			return (int) $existing_user->ID;
		}

		$name_parts = $this->split_full_name( $data['full_name'] );
		$password   = wp_generate_password( 16, true );
		$user_id    = wc_create_new_customer(
			$data['email'],
			'',
			$password,
			array(
				'first_name'   => $name_parts['first_name'],
				'last_name'    => $name_parts['last_name'],
				'display_name' => $data['full_name'],
				'source'       => self::ORDER_SOURCE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, '_cesarandev_wf_created_by_plugin', '1' );
		update_user_option( $user_id, 'default_password_nag', true, true );

		do_action(
			'woocommerce_created_customer_notification',
			$user_id,
			array(
				'user_login' => get_userdata( $user_id )->user_login,
				'user_email' => $data['email'],
				'user_pass'  => $password,
			),
			true
		);

		return (int) $user_id;
	}

	/**
	 * Update customer data with the B2B fields from the request.
	 *
	 * @param int   $user_id Customer user ID.
	 * @param array $data    Request data.
	 */
	private function update_customer_b2b_data( $user_id, array $data ) {
		$name_parts = $this->split_full_name( $data['full_name'] );

		wp_update_user(
			array(
				'ID'           => $user_id,
				'first_name'   => $name_parts['first_name'],
				'last_name'    => $name_parts['last_name'],
				'display_name' => $data['full_name'],
			)
		);

		update_user_meta( $user_id, 'billing_first_name', $name_parts['first_name'] );
		update_user_meta( $user_id, 'billing_last_name', $name_parts['last_name'] );
		update_user_meta( $user_id, 'billing_company', $data['company'] );
		update_user_meta( $user_id, 'billing_email', $data['email'] );
		update_user_meta( $user_id, 'billing_phone', $data['phone'] );
		update_user_meta( $user_id, 'cesarandev_wf_company', $data['company'] );
		update_user_meta( $user_id, 'cesarandev_wf_position', $data['position'] );
		update_user_meta( $user_id, '_cesarandev_wf_full_name', $data['full_name'] );
		update_user_meta( $user_id, '_cesarandev_wf_company', $data['company'] );
		update_user_meta( $user_id, '_cesarandev_wf_position', $data['position'] );
		update_user_meta( $user_id, '_cesarandev_wf_phone', $data['phone'] );
	}

	/**
	 * Check if a customer already has active access to all product downloads.
	 *
	 * @param int        $user_id Customer user ID.
	 * @param WC_Product $product WooCommerce product.
	 * @return bool
	 */
	private function customer_has_product_download_access( $user_id, WC_Product $product ) {
		$product_downloads = array_keys( $product->get_downloads() );

		if ( empty( $product_downloads ) ) {
			return false;
		}

		$data_store  = WC_Data_Store::load( 'customer-download' );
		$permissions = $data_store->get_downloads(
			array(
				'user_id'    => $user_id,
				'product_id' => $product->get_id(),
				'return'     => 'objects',
			)
		);

		if ( empty( $permissions ) ) {
			return false;
		}

		$active_downloads = array();

		foreach ( $permissions as $permission ) {
			if ( ! $permission instanceof WC_Customer_Download || ! $this->download_permission_is_active( $permission ) ) {
				continue;
			}

			$download_id = $permission->get_download_id();

			if ( $product->has_file( $download_id ) ) {
				$active_downloads[ $download_id ] = true;
			}
		}

		foreach ( $product_downloads as $download_id ) {
			if ( empty( $active_downloads[ $download_id ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether a WooCommerce download permission is active.
	 *
	 * @param WC_Customer_Download $permission Download permission.
	 * @return bool
	 */
	private function download_permission_is_active( WC_Customer_Download $permission ) {
		$remaining = $permission->get_downloads_remaining();

		if ( '' !== $remaining && 0 >= (int) $remaining ) {
			return false;
		}

		$expires = $permission->get_access_expires();

		if ( $expires instanceof WC_DateTime && $expires->getTimestamp() < current_time( 'timestamp' ) ) {
			return false;
		}

		return $permission->get_order_id() > 0;
	}

	/**
	 * Notify a logged-in customer that a new sheet is available.
	 *
	 * @param int        $user_id Customer user ID.
	 * @param WC_Product $product WooCommerce product.
	 */
	private function send_logged_in_sheet_notification( $user_id, WC_Product $product ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User || empty( $user->user_email ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: product name. */
			__( 'Tu ficha tecnica de %s ya esta disponible', 'cadv-woo-functionalities' ),
			$product->get_name()
		);

		$message = sprintf(
			/* translators: 1: customer name, 2: product name, 3: downloads URL. */
			__( "Hola %1\$s,\n\nTu ficha tecnica de %2\$s ya esta disponible en tu zona de cliente.\n\nPuedes descargarla aqui: %3\$s", 'cadv-woo-functionalities' ),
			$user->display_name,
			$product->get_name(),
			wc_get_account_endpoint_url( 'downloads' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Create a completed zero-value order for the technical sheet request.
	 *
	 * @param int        $user_id Customer user ID.
	 * @param WC_Product $product WooCommerce product.
	 * @param array      $data    Request data.
	 * @return int|WP_Error
	 */
	private function create_technical_sheet_order( $user_id, WC_Product $product, array $data ) {
		$order = wc_create_order(
			array(
				'customer_id' => $user_id,
				'created_via' => self::ORDER_SOURCE,
				'status'      => 'pending',
			)
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$item_id = $order->add_product(
			$product,
			1,
			array(
				'subtotal' => 0,
				'total'    => 0,
			)
		);

		if ( ! $item_id ) {
			$order->delete( true );
			return new WP_Error( 'cesarandev_wf_order_item_error', __( 'No se pudo asociar el producto a la solicitud.', 'cadv-woo-functionalities' ) );
		}

		$name_parts = $this->split_full_name( $data['full_name'] );

		$order->set_address(
			array(
				'first_name' => $name_parts['first_name'],
				'last_name'  => $name_parts['last_name'],
				'company'    => $data['company'],
				'email'      => $data['email'],
				'phone'      => $data['phone'],
			),
			'billing'
		);

		$order->update_meta_data( '_cesarandev_wf_request_type', 'technical_sheet' );
		$order->update_meta_data( '_cesarandev_wf_company', $data['company'] );
		$order->update_meta_data( '_cesarandev_wf_position', $data['position'] );
		$order->update_meta_data( '_cesarandev_wf_requested_product_id', $product->get_id() );
		$order->update_meta_data( '_cesarandev_wf_origin', is_user_logged_in() ? 'logged_in_customer' : 'guest_form' );
		$order->update_meta_data( '_cesarandev_wf_crm_status', 'new' );
		$order->update_meta_data( '_cesarandev_wf_crm_note', '' );
		$order->update_meta_data( '_cesarandev_wf_follow_up_at', '' );
		$order->update_meta_data( '_cesarandev_wf_delete_request_status', '' );
		$order->add_order_note( __( 'Solicitud de ficha tecnica registrada desde la pagina del producto.', 'cadv-woo-functionalities' ) );
		$order->calculate_totals( false );
		$order->set_total( 0 );
		$order->save();
		$order->update_status( 'completed', __( 'Solicitud de ficha tecnica completada automaticamente.', 'cadv-woo-functionalities' ), true );

		return $order->get_id();
	}

	/**
	 * Export technical sheet request rows as CSV.
	 */
	public function export_requests_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para exportar estas solicitudes.', 'cadv-woo-functionalities' ) );
		}

		check_admin_referer( self::EXPORT_ACTION );

		$filters = $this->get_crm_filters();
		$scope   = $filters['export_scope'] ? $filters['export_scope'] : 'all';
		$rows    = in_array( $scope, array( 'all', 'requests' ), true ) ? $this->get_request_report_rows( -1, $filters ) : array();
		$leads   = in_array( $scope, array( 'all', 'leads' ), true ) ? $this->get_crm_lead_rows( $filters ) : array();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=crm-fichas-y-leads.csv' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		fputcsv(
			$output,
			array(
				'Tipo de registro',
				'Fecha de solicitud',
				'Nombre',
				'Correo',
				'Telefono',
				'Empresa',
				'Cargo',
				'Producto',
				'Archivo descargable',
				'Tipo de cultivo',
				'Tipo CTA',
				'Fuente',
				'Pedido',
				'Estado de descarga',
				'Total de descargas',
				'Primera descarga',
				'Ultima descarga',
				'Estado CRM',
				'Nota CRM',
				'Proximo seguimiento',
				'Solicitud de eliminacion',
				'Fecha solicitud eliminacion',
				'Convertido a usuario',
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					'Ficha tecnica',
					$row['request_date'],
					$row['customer_name'],
					$row['email'],
					$row['phone'],
					$row['company'],
					$row['position'],
					$row['product_name'],
					$row['download_name'],
					'',
					'',
					'',
					$row['order_id'],
					$row['status'],
					$row['download_count'],
					$row['first_download'],
					$row['last_download'],
					$row['crm_status_label'],
					$row['crm_note'],
					$row['follow_up_at'],
					$row['delete_request_status'],
					$row['delete_requested_at'],
					$row['customer_id'],
				)
			);
		}

		foreach ( $leads as $lead ) {
			fputcsv(
				$output,
				array(
					'Lead CTA',
					$lead['last_contact_at_formatted'],
					$lead['full_name'],
					$lead['email'],
					$lead['phone'],
					$lead['company'],
					$lead['position'],
					$lead['product_interest'],
					'',
					$lead['crop_type'],
					$lead['cta_types_label'],
					$lead['source_url'],
					'',
					'',
					'',
					'',
					'',
					$lead['crm_status_label'],
					$lead['crm_note'],
					'',
					'',
					'',
					$lead['converted_user_id'],
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Build report rows for plugin-created technical sheet requests.
	 *
	 * @param int   $limit   Maximum number of orders. Use -1 for all.
	 * @param array $filters Optional CRM filters.
	 * @return array
	 */
	private function get_request_report_rows( $limit = 100, array $filters = array() ) {
		if ( ! $this->is_woocommerce_active() || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$order_ids = wc_get_orders(
			array(
				'limit'      => $limit,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'ids',
				'status'     => array( 'completed', 'processing', 'pending', 'on-hold', 'cancelled', 'refunded', 'failed' ),
				'meta_key'   => '_cesarandev_wf_request_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'technical_sheet', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$rows = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order instanceof WC_Order ) {
				$this->maybe_backfill_technical_sheet_customer( $order );
				$rows = array_merge( $rows, $this->get_order_report_rows( $order ) );
			}
		}

		return $this->filter_request_report_rows( $rows, $filters );
	}

	/**
	 * Build report rows for one order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array
	 */
	private function get_order_report_rows( WC_Order $order ) {
		$rows          = array();
		$request_date  = $this->format_report_date( $order->get_date_created() );
		$customer_id   = $order->get_customer_id();
		$customer_name = trim( $order->get_formatted_billing_full_name() );
		$user          = $customer_id ? get_userdata( $customer_id ) : false;
		$crm_status    = (string) ( $order->get_meta( '_cesarandev_wf_crm_status' ) ?: 'new' );
		$follow_up_raw = (string) $order->get_meta( '_cesarandev_wf_follow_up_at' );

		if ( '' === $customer_name && $user instanceof WP_User ) {
			$customer_name = $user->display_name;
		}

		$base = array(
			'request_timestamp'     => $order->get_date_created() instanceof WC_DateTime ? $order->get_date_created()->getTimestamp() : 0,
			'request_date'   => $request_date,
			'customer_id'    => $customer_id,
			'customer_name'  => $customer_name,
			'email'          => $order->get_billing_email(),
			'phone'          => $order->get_billing_phone(),
			'company'        => (string) ( $order->get_meta( '_cesarandev_wf_company' ) ?: $order->get_billing_company() ),
			'position'       => (string) $order->get_meta( '_cesarandev_wf_position' ),
			'order_id'       => $order->get_id(),
			'order_edit_url' => $order->get_edit_order_url(),
			'crm_status'            => $crm_status,
			'crm_status_label'      => $this->get_crm_status_label( $crm_status ),
			'crm_note'              => (string) $order->get_meta( '_cesarandev_wf_crm_note' ),
			'follow_up_at_raw'      => $follow_up_raw,
			'follow_up_at'          => $follow_up_raw ? date_i18n( get_option( 'date_format' ), strtotime( $follow_up_raw ) ) : '',
			'delete_request_status' => (string) $order->get_meta( '_cesarandev_wf_delete_request_status' ),
			'delete_requested_at'   => (string) $order->get_meta( '_cesarandev_wf_delete_requested_at' ),
		);

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$downloads = $product->get_downloads();

			if ( empty( $downloads ) ) {
				$rows[] = array_merge(
					$base,
					array(
						'product_name'   => $product->get_name(),
						'product_id'     => $product->get_id(),
						'download_id'    => '',
						'download_name'  => '',
						'status'         => __( 'Solicitada', 'cadv-woo-functionalities' ),
						'download_count' => 0,
						'first_download' => '',
						'last_download'  => '',
					)
				);
				continue;
			}

			foreach ( $downloads as $download_id => $download ) {
				$stats  = $this->get_download_permission_stats( $order->get_id(), $customer_id, $product->get_id(), $download_id );
				$rows[] = array_merge(
					$base,
					array(
						'product_name'   => $product->get_name(),
						'product_id'     => $product->get_id(),
						'download_id'    => $download_id,
						'download_name'  => $this->get_download_display_name( $download ),
						'status'         => $stats['download_count'] > 0 ? __( 'Descargada', 'cadv-woo-functionalities' ) : __( 'Solicitada', 'cadv-woo-functionalities' ),
						'download_count' => $stats['download_count'],
						'first_download' => $stats['first_download'],
						'last_download'  => $stats['last_download'],
					)
				);
			}
		}

		return $rows;
	}

	/**
	 * Get download count and timestamps for a specific granted file.
	 *
	 * @param int    $order_id    Order ID.
	 * @param int    $user_id     User ID.
	 * @param int    $product_id  Product ID.
	 * @param string $download_id Download ID.
	 * @return array
	 */
	private function get_download_permission_stats( $order_id, $user_id, $product_id, $download_id ) {
		$download_store = WC_Data_Store::load( 'customer-download' );
		$log_store      = WC_Data_Store::load( 'customer-download-log' );
		$permissions    = $download_store->get_downloads(
			array(
				'order_id'    => $order_id,
				'user_id'     => $user_id,
				'product_id'  => $product_id,
				'download_id' => $download_id,
				'return'      => 'objects',
			)
		);

		$count      = 0;
		$timestamps = array();

		foreach ( $permissions as $permission ) {
			if ( ! $permission instanceof WC_Customer_Download ) {
				continue;
			}

			$count += (int) $permission->get_download_count();
			$logs   = $log_store->get_download_logs_for_permission( $permission->get_id() );

			foreach ( $logs as $log ) {
				if ( ! $log instanceof WC_Customer_Download_Log ) {
					continue;
				}

				$timestamp = $log->get_timestamp();

				if ( $timestamp instanceof WC_DateTime ) {
					$timestamps[] = $timestamp->getTimestamp();
				}
			}
		}

		if ( ! empty( $timestamps ) ) {
			sort( $timestamps );
			$count = max( $count, count( $timestamps ) );
		}

		return array(
			'download_count' => $count,
			'first_download' => ! empty( $timestamps ) ? $this->format_report_timestamp( reset( $timestamps ) ) : '',
			'last_download'  => ! empty( $timestamps ) ? $this->format_report_timestamp( end( $timestamps ) ) : '',
		);
	}

	/**
	 * Get a readable downloadable file name.
	 *
	 * @param WC_Product_Download $download Download object.
	 * @return string
	 */
	private function get_download_display_name( $download ) {
		if ( is_object( $download ) && method_exists( $download, 'get_name' ) && '' !== $download->get_name() ) {
			return $download->get_name();
		}

		if ( is_object( $download ) && method_exists( $download, 'get_file' ) ) {
			return wc_get_filename_from_url( $download->get_file() );
		}

		return '';
	}

	/**
	 * Format a WooCommerce date for reports.
	 *
	 * @param WC_DateTime|null $date Date object.
	 * @return string
	 */
	private function format_report_date( $date ) {
		if ( ! $date instanceof WC_DateTime ) {
			return '';
		}

		return $this->format_report_timestamp( $date->getTimestamp() );
	}

	/**
	 * Format a timestamp for reports.
	 *
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	private function format_report_timestamp( $timestamp ) {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Get allowed CRM statuses.
	 *
	 * @return array
	 */
	private function get_crm_statuses() {
		return array(
			'new'             => __( 'Nuevo', 'cadv-woo-functionalities' ),
			'contacted'       => __( 'Contactado', 'cadv-woo-functionalities' ),
			'interested'      => __( 'Interesado', 'cadv-woo-functionalities' ),
			'follow_up'       => __( 'Seguimiento', 'cadv-woo-functionalities' ),
			'closed'          => __( 'Cerrado', 'cadv-woo-functionalities' ),
			'not_interested'  => __( 'No interesado', 'cadv-woo-functionalities' ),
			'converted'       => __( 'Convertido', 'cadv-woo-functionalities' ),
			'delete_request'  => __( 'Solicitud de eliminacion', 'cadv-woo-functionalities' ),
		);
	}

	/**
	 * Get readable CRM status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_crm_status_label( $status ) {
		$statuses = $this->get_crm_statuses();

		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['new'];
	}

	/**
	 * Get sanitized CRM filters from the current request.
	 *
	 * @return array
	 */
	private function get_crm_filters() {
		$filters = array(
			's'               => '',
			'company'         => '',
			'position'        => '',
			'phone'           => '',
			'product'         => '',
			'date_from'       => '',
			'date_to'         => '',
			'crm_status'      => '',
			'download_status' => '',
			'min_downloads'   => '',
			'delete_status'   => '',
			'cta_type'        => '',
			'crop_type'       => '',
			'source'          => '',
			'converted'       => '',
			'export_scope'    => '',
		);

		foreach ( $filters as $key => $value ) {
			if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}

			$raw             = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters[ $key ] = 'min_downloads' === $key ? (string) max( 0, absint( $raw ) ) : sanitize_text_field( $raw );
		}

		if ( $filters['crm_status'] && ! isset( $this->get_crm_statuses()[ $filters['crm_status'] ] ) ) {
			$filters['crm_status'] = '';
		}

		if ( ! in_array( $filters['download_status'], array( '', 'downloaded', 'not_downloaded' ), true ) ) {
			$filters['download_status'] = '';
		}

		if ( ! in_array( $filters['delete_status'], array( '', 'pending', 'resolved' ), true ) ) {
			$filters['delete_status'] = '';
		}

		if ( ! in_array( $filters['cta_type'], array( '', 'quote', 'newsletter' ), true ) ) {
			$filters['cta_type'] = '';
		}

		if ( ! in_array( $filters['converted'], array( '', 'yes', 'no' ), true ) ) {
			$filters['converted'] = '';
		}

		if ( ! in_array( $filters['export_scope'], array( '', 'requests', 'leads', 'all' ), true ) ) {
			$filters['export_scope'] = '';
		}

		return $filters;
	}

	/**
	 * Filter report rows for CRM views.
	 *
	 * @param array $rows    Report rows.
	 * @param array $filters Active filters.
	 * @return array
	 */
	private function filter_request_report_rows( array $rows, array $filters ) {
		$filters = array_merge(
			array(
				's'               => '',
				'company'         => '',
				'position'        => '',
				'phone'           => '',
				'product'         => '',
				'date_from'       => '',
				'date_to'         => '',
				'crm_status'      => '',
				'download_status' => '',
				'min_downloads'   => '',
				'delete_status'   => '',
				'cta_type'        => '',
				'crop_type'       => '',
				'source'          => '',
				'converted'       => '',
				'export_scope'    => '',
			),
			$filters
		);

		if ( empty( $filters ) ) {
			return $rows;
		}

		$date_from = ! empty( $filters['date_from'] ) ? strtotime( $filters['date_from'] . ' 00:00:00' ) : 0;
		$date_to   = ! empty( $filters['date_to'] ) ? strtotime( $filters['date_to'] . ' 23:59:59' ) : 0;

		return array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $filters, $date_from, $date_to ) {
					$haystack = strtolower( implode( ' ', array( $row['customer_name'], $row['email'], $row['phone'], $row['company'], $row['position'], $row['product_name'], $row['download_name'] ) ) );

					if ( $filters['s'] && false === strpos( $haystack, strtolower( $filters['s'] ) ) ) {
						return false;
					}

					foreach ( array( 'company', 'position', 'phone' ) as $field ) {
						if ( $filters[ $field ] && false === stripos( (string) $row[ $field ], $filters[ $field ] ) ) {
							return false;
						}
					}

					if ( $filters['product'] && false === stripos( $row['product_name'] . ' ' . $row['download_name'], $filters['product'] ) ) {
						return false;
					}

					if ( $date_from && (int) $row['request_timestamp'] < $date_from ) {
						return false;
					}

					if ( $date_to && (int) $row['request_timestamp'] > $date_to ) {
						return false;
					}

					if ( $filters['crm_status'] && $row['crm_status'] !== $filters['crm_status'] ) {
						return false;
					}

					if ( 'downloaded' === $filters['download_status'] && 0 >= (int) $row['download_count'] ) {
						return false;
					}

					if ( 'not_downloaded' === $filters['download_status'] && 0 < (int) $row['download_count'] ) {
						return false;
					}

					if ( '' !== $filters['min_downloads'] && (int) $row['download_count'] < (int) $filters['min_downloads'] ) {
						return false;
					}

					if ( 'pending' === $filters['delete_status'] && 'pending' !== $row['delete_request_status'] ) {
						return false;
					}

					if ( 'resolved' === $filters['delete_status'] && ! in_array( $row['delete_request_status'], array( 'resolved', 'rejected' ), true ) ) {
						return false;
					}

					return true;
				}
			)
		);
	}

	/**
	 * Build CRM lead rows.
	 *
	 * @param array $filters Active filters.
	 * @return array
	 */
	private function get_crm_lead_rows( array $filters = array() ) {
		$lead_ids = get_posts(
			array(
				'post_type'      => self::LEAD_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$rows = array();

		foreach ( $lead_ids as $lead_id ) {
			$cta_types    = (array) get_post_meta( $lead_id, '_cesarandev_wf_cta_types', true );
			$interactions = (array) get_post_meta( $lead_id, '_cesarandev_wf_interactions', true );
			$status       = (string) ( get_post_meta( $lead_id, '_cesarandev_wf_crm_status', true ) ?: 'new' );
			$last_contact = (string) get_post_meta( $lead_id, '_cesarandev_wf_last_contact_at', true );
			$converted_id = absint( get_post_meta( $lead_id, '_cesarandev_wf_converted_user_id', true ) );

			$rows[] = array(
				'lead_id'                   => (int) $lead_id,
				'request_timestamp'         => $last_contact ? strtotime( $last_contact ) : get_post_timestamp( $lead_id ),
				'last_contact_at'           => $last_contact,
				'last_contact_at_formatted' => $last_contact ? $this->format_report_timestamp( strtotime( $last_contact ) ) : '',
				'full_name'                 => (string) get_post_meta( $lead_id, '_cesarandev_wf_full_name', true ),
				'email'                     => (string) get_post_meta( $lead_id, '_cesarandev_wf_email', true ),
				'phone'                     => (string) get_post_meta( $lead_id, '_cesarandev_wf_phone', true ),
				'company'                   => (string) get_post_meta( $lead_id, '_cesarandev_wf_company', true ),
				'position'                  => (string) get_post_meta( $lead_id, '_cesarandev_wf_position', true ),
				'product_interest'          => (string) get_post_meta( $lead_id, '_cesarandev_wf_product_interest', true ),
				'crop_type'                 => (string) get_post_meta( $lead_id, '_cesarandev_wf_crop_type', true ),
				'source_url'                => (string) get_post_meta( $lead_id, '_cesarandev_wf_source_url', true ),
				'last_cta_type'             => (string) get_post_meta( $lead_id, '_cesarandev_wf_last_cta_type', true ),
				'cta_types'                 => $cta_types,
				'cta_types_label'           => $this->format_cta_types_label( $cta_types ),
				'interaction_count'         => count( $interactions ),
				'crm_status'                => $status,
				'crm_status_label'          => $this->get_crm_status_label( $status ),
				'crm_note'                  => (string) get_post_meta( $lead_id, '_cesarandev_wf_crm_note', true ),
				'converted_user_id'         => $converted_id,
				'converted_at'              => (string) get_post_meta( $lead_id, '_cesarandev_wf_converted_at', true ),
			);
		}

		return $this->filter_crm_lead_rows( $rows, $filters );
	}

	/**
	 * Filter CRM lead rows.
	 *
	 * @param array $rows    Lead rows.
	 * @param array $filters Active filters.
	 * @return array
	 */
	private function filter_crm_lead_rows( array $rows, array $filters ) {
		$filters = array_merge(
			array(
				's'          => '',
				'product'    => '',
				'crop_type'  => '',
				'source'     => '',
				'crm_status' => '',
				'cta_type'   => '',
				'converted'  => '',
				'date_from'  => '',
				'date_to'    => '',
			),
			$filters
		);
		$date_from = ! empty( $filters['date_from'] ) ? strtotime( $filters['date_from'] . ' 00:00:00' ) : 0;
		$date_to   = ! empty( $filters['date_to'] ) ? strtotime( $filters['date_to'] . ' 23:59:59' ) : 0;

		return array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $filters, $date_from, $date_to ) {
					$haystack = strtolower( implode( ' ', array( $row['full_name'], $row['email'], $row['phone'], $row['company'], $row['position'], $row['product_interest'], $row['crop_type'] ) ) );

					if ( $filters['s'] && false === strpos( $haystack, strtolower( $filters['s'] ) ) ) {
						return false;
					}

					if ( $filters['product'] && false === stripos( $row['product_interest'], $filters['product'] ) ) {
						return false;
					}

					if ( $filters['crop_type'] && false === stripos( $row['crop_type'], $filters['crop_type'] ) ) {
						return false;
					}

					if ( $filters['source'] && false === stripos( $row['source_url'], $filters['source'] ) ) {
						return false;
					}

					if ( $filters['crm_status'] && $row['crm_status'] !== $filters['crm_status'] ) {
						return false;
					}

					if ( $filters['cta_type'] && ! in_array( $filters['cta_type'], $row['cta_types'], true ) ) {
						return false;
					}

					if ( 'yes' === $filters['converted'] && ! $row['converted_user_id'] ) {
						return false;
					}

					if ( 'no' === $filters['converted'] && $row['converted_user_id'] ) {
						return false;
					}

					if ( $date_from && (int) $row['request_timestamp'] < $date_from ) {
						return false;
					}

					if ( $date_to && (int) $row['request_timestamp'] > $date_to ) {
						return false;
					}

					return true;
				}
			)
		);
	}

	/**
	 * Format CTA type labels.
	 *
	 * @param array $types CTA type keys.
	 * @return string
	 */
	private function format_cta_types_label( array $types ) {
		$labels = array(
			'quote'      => __( 'Cotizacion', 'cadv-woo-functionalities' ),
			'newsletter' => __( 'Newsletter', 'cadv-woo-functionalities' ),
		);
		$output = array();

		foreach ( array_unique( $types ) as $type ) {
			$output[] = isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
		}

		return implode( ', ', array_filter( $output ) );
	}

	/**
	 * Build CRM KPI values.
	 *
	 * @param array $rows  Report rows.
	 * @param array $leads Lead rows.
	 * @return array
	 */
	private function get_crm_stats( array $rows, array $leads = array() ) {
		$customers = array();
		$products  = array();
		$downloads = 0;
		$pending   = 0;
		$deletions = 0;
		$converted = 0;

		foreach ( $rows as $row ) {
			if ( $row['customer_id'] ) {
				$customers[ $row['customer_id'] ] = true;
			} else {
				$customers[ $row['email'] ] = true;
			}

			$products[ $row['product_name'] ] = true;
			$downloads += (int) $row['download_count'];

			if ( 0 >= (int) $row['download_count'] ) {
				$pending++;
			}

			if ( 'pending' === $row['delete_request_status'] ) {
				$deletions++;
			}
		}

		foreach ( $leads as $lead ) {
			if ( ! empty( $lead['converted_user_id'] ) ) {
				$converted++;
			}
		}

		return array(
			__( 'Solicitudes registradas', 'cadv-woo-functionalities' ) => count( $rows ),
			__( 'Leads CTA', 'cadv-woo-functionalities' )               => count( $leads ),
			__( 'Clientes', 'cadv-woo-functionalities' )                => count( $customers ),
			__( 'Fichas diferentes', 'cadv-woo-functionalities' )       => count( $products ),
			__( 'Descargas totales', 'cadv-woo-functionalities' )       => $downloads,
			__( 'Sin descargar', 'cadv-woo-functionalities' )           => $pending,
			__( 'Leads convertidos', 'cadv-woo-functionalities' )       => $converted,
			__( 'Eliminaciones pendientes', 'cadv-woo-functionalities' ) => $deletions,
		);
	}

	/**
	 * Group report rows by customer.
	 *
	 * @param array $rows Report rows.
	 * @return array
	 */
	private function group_rows_by_customer( array $rows ) {
		$customers = array();

		foreach ( $rows as $row ) {
			$key = $row['customer_id'] ? 'u_' . $row['customer_id'] : 'e_' . strtolower( $row['email'] );

			if ( ! isset( $customers[ $key ] ) ) {
				$customers[ $key ] = array(
					'customer_id'  => $row['customer_id'],
					'name'         => $row['customer_name'],
					'email'        => $row['email'],
					'phone'        => $row['phone'],
					'company'      => $row['company'],
					'position'     => $row['position'],
					'requests'     => 0,
					'downloads'    => 0,
					'last_request' => $row['request_date'],
					'last_ts'      => (int) $row['request_timestamp'],
				);
			}

			$customers[ $key ]['requests']++;
			$customers[ $key ]['downloads'] += (int) $row['download_count'];

			if ( (int) $row['request_timestamp'] > $customers[ $key ]['last_ts'] ) {
				$customers[ $key ]['last_ts']      = (int) $row['request_timestamp'];
				$customers[ $key ]['last_request'] = $row['request_date'];
			}
		}

		usort(
			$customers,
			function ( $a, $b ) {
				return $b['last_ts'] <=> $a['last_ts'];
			}
		);

		return $customers;
	}

	/**
	 * Save CRM changes for a request order.
	 */
	public function handle_crm_update() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para editar el CRM.', 'cadv-woo-functionalities' ) );
		}

		check_admin_referer( self::CRM_UPDATE_ACTION );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order || 'technical_sheet' !== $order->get_meta( '_cesarandev_wf_request_type' ) ) {
			wp_die( esc_html__( 'La solicitud no existe.', 'cadv-woo-functionalities' ) );
		}

		$status = isset( $_POST['crm_status'] ) ? sanitize_key( wp_unslash( $_POST['crm_status'] ) ) : 'new'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$note   = isset( $_POST['crm_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['crm_note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$date   = isset( $_POST['follow_up_at'] ) ? sanitize_text_field( wp_unslash( $_POST['follow_up_at'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$delete_status = isset( $_POST['delete_request_status'] ) ? sanitize_key( wp_unslash( $_POST['delete_request_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! isset( $this->get_crm_statuses()[ $status ] ) ) {
			$status = 'new';
		}

		if ( $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = '';
		}

		if ( ! in_array( $delete_status, array( '', 'pending', 'resolved', 'rejected' ), true ) ) {
			$delete_status = '';
		}

		$order->update_meta_data( '_cesarandev_wf_crm_status', $status );
		$order->update_meta_data( '_cesarandev_wf_crm_note', $note );
		$order->update_meta_data( '_cesarandev_wf_follow_up_at', $date );
		$order->update_meta_data( '_cesarandev_wf_delete_request_status', $delete_status );
		$order->add_order_note( sprintf( __( 'CRM actualizado: %s', 'cadv-woo-functionalities' ), $this->get_crm_status_label( $status ) ) );
		$order->save();

		$user_id = $order->get_customer_id();

		if ( $user_id ) {
			update_user_meta( $user_id, '_cesarandev_wf_delete_request_status', $delete_status );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=requests&cesarandev_wf_notice=saved' ) );
		exit;
	}

	/**
	 * Save CRM changes for a CTA lead.
	 */
	public function handle_crm_lead_update() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permisos para editar el CRM.', 'cadv-woo-functionalities' ) );
		}

		check_admin_referer( self::CRM_LEAD_UPDATE_ACTION );

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$lead    = $lead_id ? get_post( $lead_id ) : null;

		if ( ! $lead instanceof WP_Post || self::LEAD_POST_TYPE !== $lead->post_type ) {
			wp_die( esc_html__( 'El lead no existe.', 'cadv-woo-functionalities' ) );
		}

		$status = isset( $_POST['crm_status'] ) ? sanitize_key( wp_unslash( $_POST['crm_status'] ) ) : 'new'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$note   = isset( $_POST['crm_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['crm_note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! isset( $this->get_crm_statuses()[ $status ] ) ) {
			$status = 'new';
		}

		update_post_meta( $lead_id, '_cesarandev_wf_crm_status', $status );
		update_post_meta( $lead_id, '_cesarandev_wf_crm_note', $note );

		wp_safe_redirect( admin_url( 'admin.php?page=cesarandev-wf-crm&tab=leads&cesarandev_wf_notice=saved' ) );
		exit;
	}

	/**
	 * Maybe mark legacy customers created by sheet requests.
	 *
	 * @param WC_Order $order Request order.
	 */
	private function maybe_backfill_technical_sheet_customer( WC_Order $order ) {
		$user_id = $order->get_customer_id();

		if ( ! $user_id || get_user_meta( $user_id, '_cesarandev_wf_created_by_plugin', true ) ) {
			return;
		}

		if ( 'guest_form' !== $order->get_meta( '_cesarandev_wf_origin' ) || $this->customer_has_paid_non_sheet_orders( $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, '_cesarandev_wf_created_by_plugin', '1' );
	}

	/**
	 * Check if a customer has paid orders outside this plugin.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function customer_has_paid_non_sheet_orders( $user_id ) {
		$order_ids = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 20,
				'return'      => 'ids',
				'status'      => array( 'completed', 'processing', 'on-hold' ),
			)
		);

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order instanceof WC_Order && 'technical_sheet' !== $order->get_meta( '_cesarandev_wf_request_type' ) && (float) $order->get_total() > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether current or given user belongs to the restricted sheet portal.
	 *
	 * @param int $user_id Optional user ID.
	 * @return bool
	 */
	private function is_restricted_sheet_customer( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		return $user_id && '1' === (string) get_user_meta( $user_id, '_cesarandev_wf_created_by_plugin', true );
	}

	/**
	 * Restrict WooCommerce account menu for plugin-created customers.
	 *
	 * @param array $items Account items.
	 * @return array
	 */
	public function filter_restricted_account_menu( $items ) {
		if ( ! $this->is_restricted_sheet_customer() ) {
			return $items;
		}

		$allowed = array( 'downloads', 'edit-account', 'customer-logout' );

		return array_intersect_key( $items, array_flip( $allowed ) );
	}

	/**
	 * Redirect hidden account endpoints for restricted sheet customers.
	 */
	public function redirect_restricted_account_endpoints() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! $this->is_restricted_sheet_customer() ) {
			return;
		}

		$current = '';

		if ( function_exists( 'WC' ) && WC()->query ) {
			$current = WC()->query->get_current_endpoint();
		}

		if ( '' === $current && ! is_wc_endpoint_url() ) {
			$current = 'dashboard';
		}

		if ( in_array( $current, array( 'dashboard', 'orders', 'view-order', 'edit-address', 'payment-methods', 'add-payment-method' ), true ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'downloads' ) );
			exit;
		}
	}

	/**
	 * Remove WooCommerce editable account template for restricted customers.
	 */
	public function prepare_readonly_account_details() {
		if ( $this->is_restricted_sheet_customer() ) {
			remove_action( 'woocommerce_account_edit-account_endpoint', 'woocommerce_account_edit_account' );
		}
	}

	/**
	 * Render readonly account details for restricted customers.
	 */
	public function render_readonly_account_details() {
		if ( ! $this->is_restricted_sheet_customer() ) {
			return;
		}

		$user_id  = get_current_user_id();
		$user     = get_userdata( $user_id );
		$fields   = array(
			__( 'Nombre completo', 'cadv-woo-functionalities' ) => get_user_meta( $user_id, '_cesarandev_wf_full_name', true ) ?: ( $user instanceof WP_User ? $user->display_name : '' ),
			__( 'Empresa', 'cadv-woo-functionalities' )         => get_user_meta( $user_id, '_cesarandev_wf_company', true ) ?: get_user_meta( $user_id, 'billing_company', true ),
			__( 'Cargo', 'cadv-woo-functionalities' )           => get_user_meta( $user_id, '_cesarandev_wf_position', true ) ?: get_user_meta( $user_id, 'cesarandev_wf_position', true ),
			__( 'Correo electronico', 'cadv-woo-functionalities' ) => $user instanceof WP_User ? $user->user_email : '',
			__( 'Telefono', 'cadv-woo-functionalities' )        => get_user_meta( $user_id, '_cesarandev_wf_phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true ),
		);
		$delete_status = get_user_meta( $user_id, '_cesarandev_wf_delete_request_status', true );
		?>
		<div class="cesarandev-wf-account-readonly">
			<h2><?php esc_html_e( 'Detalles de la cuenta', 'cadv-woo-functionalities' ); ?></h2>
			<p><?php esc_html_e( 'Estos datos corresponden al formulario con el que solicitaste tu ficha tecnica.', 'cadv-woo-functionalities' ); ?></p>
			<table class="shop_table shop_table_responsive">
				<tbody>
					<?php foreach ( $fields as $label => $value ) : ?>
						<tr>
							<th><?php echo esc_html( $label ); ?></th>
							<td><?php echo esc_html( $value ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Eliminar cuenta', 'cadv-woo-functionalities' ); ?></h3>
			<?php if ( 'pending' === $delete_status ) : ?>
				<p><?php esc_html_e( 'Tu solicitud de eliminacion ya fue recibida y esta pendiente de revision.', 'cadv-woo-functionalities' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Confirmas que deseas solicitar la eliminacion de tu cuenta?', 'cadv-woo-functionalities' ) ); ?>');">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_ACCOUNT_ACTION ); ?>" />
					<?php wp_nonce_field( self::DELETE_ACCOUNT_NONCE ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Solicitar eliminacion de cuenta', 'cadv-woo-functionalities' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Register an account deletion request from the customer portal.
	 */
	public function handle_account_deletion_request() {
		if ( ! is_user_logged_in() || ! $this->is_restricted_sheet_customer() ) {
			wp_die( esc_html__( 'No tienes permisos para solicitar esta accion.', 'cadv-woo-functionalities' ) );
		}

		check_admin_referer( self::DELETE_ACCOUNT_NONCE );

		$user_id = get_current_user_id();
		$now     = current_time( 'mysql' );

		update_user_meta( $user_id, '_cesarandev_wf_delete_request_status', 'pending' );
		update_user_meta( $user_id, '_cesarandev_wf_delete_requested_at', $now );

		$order_ids = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'return'      => 'ids',
				'meta_key'    => '_cesarandev_wf_request_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'technical_sheet', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$order->update_meta_data( '_cesarandev_wf_delete_request_status', 'pending' );
			$order->update_meta_data( '_cesarandev_wf_delete_requested_at', $now );
			$order->update_meta_data( '_cesarandev_wf_crm_status', 'delete_request' );
			$order->add_order_note( __( 'El cliente solicito la eliminacion de su cuenta desde Mi cuenta.', 'cadv-woo-functionalities' ) );
			$order->save();
		}

		$this->send_account_deletion_admin_notification( $user_id );

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Tu solicitud de eliminacion fue recibida. Nuestro equipo la revisara desde el CRM.', 'cadv-woo-functionalities' ), 'success' );
		}

		wp_safe_redirect( wc_get_account_endpoint_url( 'edit-account' ) );
		exit;
	}

	/**
	 * Send deletion request notification to site admin.
	 *
	 * @param int $user_id User ID.
	 */
	private function send_account_deletion_admin_notification( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$subject = __( 'Nueva solicitud de eliminacion de cuenta', 'cadv-woo-functionalities' );
		$message = sprintf(
			/* translators: 1: customer name, 2: email, 3: CRM URL. */
			__( "Un cliente solicito eliminar su cuenta.\n\nCliente: %1\$s\nCorreo: %2\$s\n\nRevisar en CRM: %3\$s", 'cadv-woo-functionalities' ),
			$user->display_name,
			$user->user_email,
			admin_url( 'admin.php?page=cesarandev-wf-crm&tab=deletions' )
		);

		wp_mail( get_option( 'admin_email' ), $subject, $message );
	}

	/**
	 * Check whether a product has downloadable files.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return bool
	 */
	private function product_has_downloads( WC_Product $product ) {
		return $product->is_downloadable() && ! empty( $product->get_downloads() );
	}

	/**
	 * Split full name into first and last name.
	 *
	 * @param string $full_name Full name.
	 * @return array
	 */
	private function split_full_name( $full_name ) {
		$parts      = preg_split( '/\s+/', trim( $full_name ) );
		$first_name = array_shift( $parts );

		return array(
			'first_name' => $first_name ? $first_name : '',
			'last_name'  => implode( ' ', $parts ),
		);
	}

	/**
	 * Build WhatsApp URL for a product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string
	 */
	private function get_whatsapp_url( WC_Product $product ) {
		$phone = get_option( self::OPTION_PHONE, '' );
		$phone = $this->sanitize_phone( $phone );

		if ( empty( $phone ) ) {
			return '';
		}

		$message = $this->build_product_message( $product );

		return sprintf(
			'https://wa.me/%1$s?text=%2$s',
			rawurlencode( $phone ),
			rawurlencode( $message )
		);
	}

	/**
	 * Build product message from configured template.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string
	 */
	private function build_product_message( WC_Product $product ) {
		$template = get_option( self::OPTION_MESSAGE_TEMPLATE, $this->get_default_message_template() );

		if ( '' === trim( $template ) ) {
			$template = $this->get_default_message_template();
		}

		$replacements = array(
			'{product_name}' => wp_strip_all_tags( $product->get_name() ),
			'{product_url}'  => get_permalink( $product->get_id() ),
		);

		return strtr( $template, $replacements );
	}

	/**
	 * Default WhatsApp message template.
	 *
	 * @return string
	 */
	private function get_default_message_template() {
		return __( 'Hola, estoy viendo el producto {product_name} en la pagina web y quisiera mas informacion. {product_url}', 'cadv-woo-functionalities' );
	}

	/**
	 * Get the configured marketplace line color for a product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string
	 */
	private function get_product_line_color( WC_Product $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return self::DEFAULT_LINE_COLOR;
		}

		foreach ( $terms as $term ) {
			$line = $this->normalize_product_line_term( $term );

			if ( $line instanceof WP_Term ) {
				$color = sanitize_hex_color( get_term_meta( $line->term_id, self::MARKETPLACE_TERM_COLOR_META, true ) );

				if ( $color ) {
					return $color;
				}
			}
		}

		return self::DEFAULT_LINE_COLOR;
	}

	/**
	 * Convert a product category to its top-level marketplace line.
	 *
	 * @param WP_Term $term Product category.
	 * @return WP_Term|null
	 */
	private function normalize_product_line_term( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		if ( 0 === absint( $term->parent ) ) {
			return $term;
		}

		$ancestors = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

		if ( empty( $ancestors ) ) {
			return $term;
		}

		$parent = get_term( end( $ancestors ), 'product_cat' );

		return $parent instanceof WP_Term ? $parent : $term;
	}

	/**
	 * Sanitize WhatsApp phone.
	 *
	 * @param string $phone Raw phone value.
	 * @return string
	 */
	public function sanitize_phone( $phone ) {
		return preg_replace( '/[^0-9]/', '', (string) $phone );
	}

	/**
	 * Show notice when WooCommerce is inactive.
	 */
	public function render_woocommerce_notice() {
		if ( $this->is_woocommerce_active() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'CADV Woo Functionalities requiere WooCommerce activo para mostrar botones de consulta y ficha tecnica.', 'cadv-woo-functionalities' );
		echo '</p></div>';
	}

	/**
	 * Check WooCommerce availability.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Product' );
	}

	/**
	 * WhatsApp icon SVG.
	 *
	 * @return string
	 */
	private function get_whatsapp_icon_svg() {
		return '<svg class="cesarandev-wf-button__icon" width="22" height="22" style="display:inline-block;flex:0 0 auto;height:22px;width:22px;max-height:22px;max-width:22px;vertical-align:middle;" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.1 17.2c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-.9 1.1-.2.2-.3.2-.6.1-.3-.2-1.2-.4-2.3-1.4-.8-.7-1.4-1.7-1.6-1.9-.2-.3 0-.4.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.2-.7-1.7-1-2.3-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4 0-.1-.3-.2-.6-.4z"/><path fill="currentColor" d="M16 3C8.8 3 3 8.8 3 16c0 2.4.7 4.7 1.9 6.7L3.7 29l6.5-1.7c1.9 1 4 1.6 6.2 1.6 7.2 0 13-5.8 13-13S23.2 3 16 3zm0 23.7c-2 0-3.9-.5-5.6-1.5l-.4-.2-3.9 1 1-3.8-.2-.4c-1.1-1.7-1.6-3.8-1.6-5.8C5.3 10.1 10.1 5.3 16 5.3S26.7 10.1 26.7 16 21.9 26.7 16 26.7z"/></svg>';
	}

	/**
	 * Document icon SVG.
	 *
	 * @return string
	 */
	private function get_document_icon_svg() {
		return '<svg class="cesarandev-wf-button__icon" width="22" height="22" style="display:inline-block;flex:0 0 auto;height:22px;width:22px;max-height:22px;max-width:22px;vertical-align:middle;" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6 2h8l5 5v15H6V2zm7 1.8V8h4.2L13 3.8zM8 11v1.5h8V11H8zm0 4v1.5h8V15H8zm0 4v1.5h5V19H8z"/></svg>';
	}
}
