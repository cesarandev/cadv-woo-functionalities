<?php
/**
 * Marketplace shortcode and admin fields.
 *
 * @package CADVWooFunctionalities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a filterable WooCommerce marketplace via shortcode.
 */
final class CADV_Woo_Functionalities_Marketplace {
	const SHORTCODE            = 'cadv_marketplace';
	const AJAX_ACTION          = 'cadv_marketplace_products';
	const NONCE_ACTION         = 'cadv_marketplace_products';
	const TERM_COLOR_META      = '_cadv_marketplace_color';
	const PRODUCT_SEGMENT_META = '_cadv_marketplace_segment';
	const PRODUCT_TYPE_META    = '_cadv_marketplace_product_type';
	const PRODUCT_DESC_META    = '_cadv_marketplace_commercial_technical_description';
	const PRODUCT_ICA_META     = '_cadv_marketplace_ica_registration';
	const DEFAULT_LINE_COLOR   = '#2f7d3a';
	const DEFAULT_PER_PAGE     = 12;
	const DEFAULT_COLUMNS      = 3;
	const OPTION_PHONE         = 'cadv_woo_functionalities_whatsapp_phone';
	const OPTION_MESSAGE       = 'cadv_woo_functionalities_message_template';

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
	 * Hook marketplace behavior.
	 */
	private function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_products' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_ajax_products' ) );

		add_action( 'product_cat_add_form_fields', array( $this, 'render_category_color_add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'render_category_color_edit_field' ) );
		add_action( 'created_product_cat', array( $this, 'save_category_color' ) );
		add_action( 'edited_product_cat', array( $this, 'save_category_color' ) );

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_product_ica_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_marketplace_fields' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'add_csv_mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'add_csv_mapping_default_columns' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'import_product_marketplace_fields' ), 10, 2 );
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'add_export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_export_columns' ) );
		add_filter( 'woocommerce_product_export_product_column_cadv_segment', array( $this, 'export_segment_column' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_cadv_line', array( $this, 'export_line_column' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_cadv_type', array( $this, 'export_type_column' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_cadv_commercial_description', array( $this, 'export_commercial_description_column' ), 10, 2 );
		add_filter( 'woocommerce_product_export_product_column_cadv_ica_registration', array( $this, 'export_ica_column' ), 10, 2 );
	}

	/**
	 * Render marketplace shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		if ( ! $this->is_woocommerce_active() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'per_page'        => self::DEFAULT_PER_PAGE,
				'columns'         => self::DEFAULT_COLUMNS,
				'show_ica_filter' => 'yes',
			),
			$atts,
			self::SHORTCODE
		);

		$per_page        = $this->sanitize_per_page( $atts['per_page'] );
		$columns         = $this->sanitize_columns( $atts['columns'] );
		$show_ica_filter = $this->is_truthy( $atts['show_ica_filter'] );
		$categories      = $this->get_line_categories();
		$search_id       = wp_unique_id( 'cadv-marketplace-search-' );
		$response        = $this->get_products_response(
			array(
				'category' => 0,
				'search'   => '',
				'has_ica'  => false,
				'page'     => 1,
				'per_page' => $per_page,
			)
		);
		$empty_message    = '' === $response['html'] ? __( 'No encontramos productos con estos filtros.', 'cadv-woo-functionalities' ) : '';

		$this->enqueue_assets();

		ob_start();
		$this->print_late_styles();
		?>
		<div class="cadv-marketplace" data-cadv-marketplace data-per-page="<?php echo esc_attr( $per_page ); ?>" data-columns="<?php echo esc_attr( $columns ); ?>" data-show-ica="<?php echo $show_ica_filter ? '1' : '0'; ?>" style="--cadv-marketplace-columns: <?php echo esc_attr( $columns ); ?>;">
			<aside class="cadv-marketplace__filters" aria-label="<?php esc_attr_e( 'Filtros de marketplace', 'cadv-woo-functionalities' ); ?>">
				<div class="cadv-marketplace__filters-header">
					<h2><?php esc_html_e( 'Filtrar por linea', 'cadv-woo-functionalities' ); ?></h2>
					<button type="button" class="cadv-marketplace__clear" data-cadv-marketplace-clear><?php esc_html_e( 'Limpiar filtros', 'cadv-woo-functionalities' ); ?></button>
				</div>

				<div class="cadv-marketplace__line-list" data-cadv-marketplace-lines>
					<?php foreach ( $categories as $category ) : ?>
						<button type="button" class="cadv-marketplace__line" data-cadv-marketplace-line="<?php echo esc_attr( $category['id'] ); ?>" style="--line-color: <?php echo esc_attr( $category['color'] ); ?>;">
							<span class="cadv-marketplace__line-dot" aria-hidden="true"></span>
							<span class="cadv-marketplace__line-name"><?php echo esc_html( $category['name'] ); ?></span>
							<span class="cadv-marketplace__line-count">(<?php echo esc_html( $category['count'] ); ?>)</span>
						</button>
					<?php endforeach; ?>
				</div>

				<?php if ( $show_ica_filter ) : ?>
					<label class="cadv-marketplace__toggle">
						<input type="checkbox" data-cadv-marketplace-ica />
						<span><?php esc_html_e( 'Con Registro ICA', 'cadv-woo-functionalities' ); ?></span>
					</label>
				<?php endif; ?>

				<div class="cadv-marketplace__search">
					<label for="<?php echo esc_attr( $search_id ); ?>"><?php esc_html_e( 'Buscar producto', 'cadv-woo-functionalities' ); ?></label>
					<div class="cadv-marketplace__search-control">
						<span class="cadv-marketplace__search-icon" aria-hidden="true"></span>
						<input id="<?php echo esc_attr( $search_id ); ?>" type="search" data-cadv-marketplace-search placeholder="<?php esc_attr_e( 'Nombre, formula o cultivo...', 'cadv-woo-functionalities' ); ?>" />
					</div>
				</div>
			</aside>

			<section class="cadv-marketplace__results" aria-live="polite">
				<p class="cadv-marketplace__summary" data-cadv-marketplace-summary><?php echo esc_html( $response['summary'] ); ?></p>
				<div class="cadv-marketplace__status <?php echo $empty_message ? 'is-empty' : ''; ?>" data-cadv-marketplace-status <?php echo $empty_message ? '' : 'hidden'; ?>><?php echo esc_html( $empty_message ); ?></div>
				<div class="cadv-marketplace__grid" data-cadv-marketplace-grid>
					<?php echo $response['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<button type="button" class="cadv-marketplace__load-more" data-cadv-marketplace-load-more <?php echo $response['has_more'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Cargar mas', 'cadv-woo-functionalities' ); ?></button>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle product filtering AJAX.
	 */
	public function handle_ajax_products() {
		if ( ! $this->is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce no esta activo.', 'cadv-woo-functionalities' ) ), 400 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$filters  = $this->get_request_filters();
		$response = $this->get_products_response( $filters );

		wp_send_json_success( $response );
	}

	/**
	 * Render color field on new category form.
	 */
	public function render_category_color_add_field() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="form-field term-cadv-marketplace-color-wrap">
			<label for="cadv-marketplace-color"><?php esc_html_e( 'Color en marketplace', 'cadv-woo-functionalities' ); ?></label>
			<input type="color" id="cadv-marketplace-color" name="cadv_marketplace_color" value="<?php echo esc_attr( self::DEFAULT_LINE_COLOR ); ?>" />
			<?php wp_nonce_field( 'cadv_marketplace_category_color', 'cadv_marketplace_category_color_nonce' ); ?>
			<p><?php esc_html_e( 'Este color identifica la linea en el filtro y en las tarjetas.', 'cadv-woo-functionalities' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render color field on edit category form.
	 *
	 * @param WP_Term $term Product category term.
	 */
	public function render_category_color_edit_field( $term ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$color = $this->get_term_color( $term->term_id );
		?>
		<tr class="form-field term-cadv-marketplace-color-wrap">
			<th scope="row"><label for="cadv-marketplace-color"><?php esc_html_e( 'Color en marketplace', 'cadv-woo-functionalities' ); ?></label></th>
			<td>
				<input type="color" id="cadv-marketplace-color" name="cadv_marketplace_color" value="<?php echo esc_attr( $color ); ?>" />
				<?php wp_nonce_field( 'cadv_marketplace_category_color', 'cadv_marketplace_category_color_nonce' ); ?>
				<p class="description"><?php esc_html_e( 'Este color identifica la linea en el filtro y en las tarjetas.', 'cadv-woo-functionalities' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save product category color.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_category_color( $term_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$nonce = isset( $_POST['cadv_marketplace_category_color_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cadv_marketplace_category_color_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cadv_marketplace_category_color' ) ) {
			return;
		}

		$color = isset( $_POST['cadv_marketplace_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['cadv_marketplace_color'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $color ) {
			$color = self::DEFAULT_LINE_COLOR;
		}

		update_term_meta( $term_id, self::TERM_COLOR_META, $color );
	}

	/**
	 * Render marketplace fields in product data.
	 */
	public function render_product_ica_field() {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) || ! function_exists( 'woocommerce_wp_textarea_input' ) ) {
			return;
		}

		echo '<div class="options_group">';

		woocommerce_wp_text_input(
			array(
				'id'          => self::PRODUCT_SEGMENT_META,
				'label'       => __( 'Segmento', 'cadv-woo-functionalities' ),
				'desc_tip'    => true,
				'description' => __( 'Segmento comercial usado para ordenar cargas masivas y reportes del marketplace.', 'cadv-woo-functionalities' ),
				'placeholder' => __( 'Ej. Complejos mezclados', 'cadv-woo-functionalities' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::PRODUCT_TYPE_META,
				'label'       => __( 'Tipo', 'cadv-woo-functionalities' ),
				'desc_tip'    => true,
				'description' => __( 'Tipo tecnico o familia descriptiva del producto.', 'cadv-woo-functionalities' ),
				'placeholder' => __( 'Ej. Complejo NPK', 'cadv-woo-functionalities' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::PRODUCT_ICA_META,
				'label'       => __( 'Registro ICA', 'cadv-woo-functionalities' ),
				'desc_tip'    => true,
				'description' => __( 'Se mostrara en el marketplace si el producto cuenta con registro ICA.', 'cadv-woo-functionalities' ),
				'placeholder' => __( 'Ej. Reg. ICA 9263', 'cadv-woo-functionalities' ),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => self::PRODUCT_DESC_META,
				'label'       => __( 'Descripcion comercial-tecnica', 'cadv-woo-functionalities' ),
				'desc_tip'    => true,
				'description' => __( 'Descripcion breve que se mostrara en el marketplace. Tambien se puede importar por CSV.', 'cadv-woo-functionalities' ),
				'placeholder' => __( 'Ej. Complejo NPK balanceado para programas de nutricion...', 'cadv-woo-functionalities' ),
			)
		);

		echo '</div>';
	}

	/**
	 * Save marketplace fields.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save_product_marketplace_fields( $product ) {
		if ( ! $product instanceof WC_Product || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		$this->save_text_meta_from_post( $product, self::PRODUCT_SEGMENT_META );
		$this->save_text_meta_from_post( $product, self::PRODUCT_TYPE_META );
		$this->save_text_meta_from_post( $product, self::PRODUCT_ICA_META );
		$this->save_textarea_meta_from_post( $product, self::PRODUCT_DESC_META );

		$commercial_description = $product->get_meta( self::PRODUCT_DESC_META );

		if ( $commercial_description && ! $product->get_short_description() ) {
			$product->set_short_description( $commercial_description );
		}
	}

	/**
	 * Add marketplace columns to the WooCommerce CSV importer mapping UI.
	 *
	 * @param array $options Mapping options.
	 * @return array
	 */
	public function add_csv_mapping_options( $options ) {
		return array_merge( $options, $this->get_csv_columns() );
	}

	/**
	 * Auto-map common CSV headers.
	 *
	 * @param array $columns Default mappings.
	 * @return array
	 */
	public function add_csv_mapping_default_columns( $columns ) {
		return array_merge(
			$columns,
			array(
				'Segmento'                        => 'cadv_segment',
				'Linea comercial'                => 'cadv_line',
				'Linea'                          => 'cadv_line',
				'Tipo'                            => 'cadv_type',
				'Descripcion comercial-tecnica'  => 'cadv_commercial_description',
				'Descripcion comercial tecnica'  => 'cadv_commercial_description',
				'Registro ICA'                    => 'cadv_ica_registration',
				'ICA'                             => 'cadv_ica_registration',
			)
		);
	}

	/**
	 * Persist marketplace columns during WooCommerce CSV import.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $data    Imported data.
	 * @return WC_Product
	 */
	public function import_product_marketplace_fields( $product, $data ) {
		if ( ! $product instanceof WC_Product ) {
			return $product;
		}

		$segment     = isset( $data['cadv_segment'] ) ? sanitize_text_field( $data['cadv_segment'] ) : '';
		$type        = isset( $data['cadv_type'] ) ? sanitize_text_field( $data['cadv_type'] ) : '';
		$description = isset( $data['cadv_commercial_description'] ) ? sanitize_textarea_field( $data['cadv_commercial_description'] ) : '';
		$ica         = isset( $data['cadv_ica_registration'] ) ? sanitize_text_field( $data['cadv_ica_registration'] ) : '';
		$line        = isset( $data['cadv_line'] ) ? sanitize_text_field( $data['cadv_line'] ) : '';

		$product->update_meta_data( self::PRODUCT_SEGMENT_META, $segment );
		$product->update_meta_data( self::PRODUCT_TYPE_META, $type );
		$product->update_meta_data( self::PRODUCT_DESC_META, $description );
		$product->update_meta_data( self::PRODUCT_ICA_META, $ica );

		if ( $description && ! $product->get_short_description() ) {
			$product->set_short_description( $description );
		}

		if ( $line ) {
			$this->assign_imported_line_category( $product, $line );
		}

		return $product;
	}

	/**
	 * Add marketplace columns to product CSV export.
	 *
	 * @param array $columns Export columns.
	 * @return array
	 */
	public function add_export_columns( $columns ) {
		return array_merge( $columns, $this->get_csv_columns() );
	}

	/**
	 * Export segment column.
	 *
	 * @param string     $value   Current value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_segment_column( $value, $product ) {
		return $product instanceof WC_Product ? $product->get_meta( self::PRODUCT_SEGMENT_META ) : $value;
	}

	/**
	 * Export commercial line column.
	 *
	 * @param string     $value   Current value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_line_column( $value, $product ) {
		$line = $product instanceof WC_Product ? $this->get_product_line( $product ) : null;

		return $line ? $line['name'] : $value;
	}

	/**
	 * Export type column.
	 *
	 * @param string     $value   Current value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_type_column( $value, $product ) {
		return $product instanceof WC_Product ? $product->get_meta( self::PRODUCT_TYPE_META ) : $value;
	}

	/**
	 * Export commercial description column.
	 *
	 * @param string     $value   Current value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_commercial_description_column( $value, $product ) {
		return $product instanceof WC_Product ? $this->get_product_commercial_description( $product ) : $value;
	}

	/**
	 * Export ICA column.
	 *
	 * @param string     $value   Current value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function export_ica_column( $value, $product ) {
		return $product instanceof WC_Product ? $product->get_meta( self::PRODUCT_ICA_META ) : $value;
	}

	/**
	 * Get marketplace CSV column definitions.
	 *
	 * @return array
	 */
	private function get_csv_columns() {
		return array(
			'cadv_segment'                => __( 'Segmento', 'cadv-woo-functionalities' ),
			'cadv_line'                   => __( 'Linea comercial', 'cadv-woo-functionalities' ),
			'cadv_type'                   => __( 'Tipo', 'cadv-woo-functionalities' ),
			'cadv_commercial_description' => __( 'Descripcion comercial-tecnica', 'cadv-woo-functionalities' ),
			'cadv_ica_registration'       => __( 'Registro ICA', 'cadv-woo-functionalities' ),
		);
	}

	/**
	 * Save a text product meta value from POST.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $meta_key Meta key.
	 */
	private function save_text_meta_from_post( WC_Product $product, $meta_key ) {
		$value = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( $meta_key, $value );
	}

	/**
	 * Save a textarea product meta value from POST.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $meta_key Meta key.
	 */
	private function save_textarea_meta_from_post( WC_Product $product, $meta_key ) {
		$value = isset( $_POST[ $meta_key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $meta_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( $meta_key, $value );
	}

	/**
	 * Assign imported commercial line as a parent product category.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $line_name Commercial line name.
	 */
	private function assign_imported_line_category( WC_Product $product, $line_name ) {
		$line_name = trim( wp_strip_all_tags( $line_name ) );

		if ( '' === $line_name || ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}

		$term = term_exists( $line_name, 'product_cat', 0 );

		if ( 0 === $term || null === $term ) {
			$term = wp_insert_term( $line_name, 'product_cat', array( 'parent' => 0 ) );
		}

		if ( is_wp_error( $term ) || empty( $term['term_id'] ) ) {
			return;
		}

		$category_ids = array_map( 'absint', $product->get_category_ids() );
		$category_ids[] = absint( $term['term_id'] );
		$product->set_category_ids( array_values( array_unique( array_filter( $category_ids ) ) ) );
	}

	/**
	 * Enqueue marketplace assets.
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'cadv-woo-marketplace-font',
			'https://fonts.googleapis.com/css2?family=Exo:wght@600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'cadv-woo-marketplace',
			CADV_WOO_FUNCTIONALITIES_URL . 'assets/css/cadv-woo-marketplace.css',
			array( 'cadv-woo-marketplace-font' ),
			CADV_WOO_FUNCTIONALITIES_VERSION
		);

		wp_enqueue_script(
			'cadv-woo-marketplace',
			CADV_WOO_FUNCTIONALITIES_URL . 'assets/js/cadv-woo-marketplace.js',
			array(),
			CADV_WOO_FUNCTIONALITIES_VERSION,
			true
		);

		wp_localize_script(
			'cadv-woo-marketplace',
			'CADVWooMarketplace',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'messages' => array(
					'loading' => __( 'Cargando productos...', 'cadv-woo-functionalities' ),
					'empty'   => __( 'No encontramos productos con estos filtros.', 'cadv-woo-functionalities' ),
					'error'   => __( 'No se pudieron cargar los productos. Intentalo de nuevo.', 'cadv-woo-functionalities' ),
				),
			)
		);
	}

	/**
	 * Print styles when a page builder renders the shortcode after wp_head.
	 */
	private function print_late_styles() {
		if ( ! did_action( 'wp_head' ) || wp_style_is( 'cadv-woo-marketplace', 'done' ) ) {
			return;
		}

		wp_print_styles( array( 'cadv-woo-marketplace-font', 'cadv-woo-marketplace' ) );
	}

	/**
	 * Build filters from AJAX request.
	 *
	 * @return array
	 */
	private function get_request_filters() {
		return array(
			'category' => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
			'search'   => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'has_ica'  => isset( $_POST['has_ica'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['has_ica'] ) ),
			'page'     => isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1,
			'per_page' => isset( $_POST['per_page'] ) ? $this->sanitize_per_page( $_POST['per_page'] ) : self::DEFAULT_PER_PAGE,
		);
	}

	/**
	 * Query products and render response data.
	 *
	 * @param array $filters Product filters.
	 * @return array
	 */
	private function get_products_response( array $filters ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'category' => 0,
				'search'   => '',
				'has_ica'  => false,
				'page'     => 1,
				'per_page' => self::DEFAULT_PER_PAGE,
			)
		);

		$query = $this->get_products_query( $filters );
		$html  = '';

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$product = wc_get_product( $post->ID );

				if ( $product instanceof WC_Product ) {
					$html .= $this->render_product_card( $product );
				}
			}
		}

		$page      = max( 1, absint( $filters['page'] ) );
		$max_pages = max( 1, absint( $query->max_num_pages ) );
		$total     = absint( $query->found_posts );

		wp_reset_postdata();

		return array(
			'html'      => $html,
			'total'     => $total,
			'page'      => $page,
			'maxPages'  => $max_pages,
			'has_more'  => $page < $max_pages,
			'summary'   => $this->get_results_summary( $total, absint( $filters['category'] ) ),
			'lineLabel' => $this->get_line_label( absint( $filters['category'] ) ),
		);
	}

	/**
	 * Build products query.
	 *
	 * @param array $filters Product filters.
	 * @return WP_Query
	 */
	private function get_products_query( array $filters ) {
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $this->sanitize_per_page( $filters['per_page'] ),
			'paged'               => max( 1, absint( $filters['page'] ) ),
			'orderby'             => 'menu_order title',
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $filters['search'] ) ) {
			$args['s'] = sanitize_text_field( $filters['search'] );
		}

		if ( ! empty( $filters['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => absint( $filters['category'] ),
					'include_children' => true,
				),
			);
		}

		if ( ! empty( $filters['has_ica'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => self::PRODUCT_ICA_META,
					'value'   => '',
					'compare' => '!=',
				),
			);
		}

		return new WP_Query( $args );
	}

	/**
	 * Render one product card.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function render_product_card( WC_Product $product ) {
		$line        = $this->get_product_line( $product );
		$line_name   = $line ? $line['name'] : __( 'Producto', 'cadv-woo-functionalities' );
		$line_color  = $line ? $line['color'] : self::DEFAULT_LINE_COLOR;
		$type        = sanitize_text_field( $product->get_meta( self::PRODUCT_TYPE_META ) );
		$ica         = sanitize_text_field( $product->get_meta( self::PRODUCT_ICA_META ) );
		$description = wp_trim_words( wp_strip_all_tags( $this->get_product_commercial_description( $product ) ), 16 );
		$whatsapp    = $this->get_whatsapp_url( $product );

		ob_start();
		?>
		<article class="cadv-marketplace-card" style="--line-color: <?php echo esc_attr( $line_color ); ?>;">
			<div class="cadv-marketplace-card__media">
				<span class="cadv-marketplace-card__badge"><?php echo esc_html( $line_name ); ?></span>
				<?php if ( $product->get_image_id() ) : ?>
					<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'cadv-marketplace-card__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<div class="cadv-marketplace-card__placeholder">
						<span aria-hidden="true">&#9633;</span>
						<small><?php echo esc_html( $product->get_name() ); ?></small>
					</div>
				<?php endif; ?>
			</div>
			<div class="cadv-marketplace-card__body">
				<h3><?php echo esc_html( $product->get_name() ); ?></h3>
				<?php if ( $type ) : ?>
					<p class="cadv-marketplace-card__type"><?php echo esc_html( $type ); ?></p>
				<?php endif; ?>
				<?php if ( $ica ) : ?>
					<p class="cadv-marketplace-card__ica"><?php echo esc_html( $this->format_ica_registration( $ica ) ); ?></p>
				<?php endif; ?>
				<?php if ( $description ) : ?>
					<p class="cadv-marketplace-card__description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
				<div class="cadv-marketplace-card__actions">
					<a class="cadv-marketplace-card__button cadv-marketplace-card__button--primary" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php esc_html_e( 'Ver producto', 'cadv-woo-functionalities' ); ?></a>
					<?php if ( $whatsapp ) : ?>
						<a class="cadv-marketplace-card__button cadv-marketplace-card__button--whatsapp" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener noreferrer">
							<span aria-hidden="true">WA</span>
							<?php esc_html_e( 'WhatsApp', 'cadv-woo-functionalities' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get parent line categories.
	 *
	 * @return array
	 */
	private function get_line_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'pad_counts' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'    => absint( $term->term_id ),
				'name'  => $term->name,
				'count' => absint( $term->count ),
				'color' => $this->get_term_color( $term->term_id ),
			);
		}

		return $categories;
	}

	/**
	 * Get commercial-technical description with WooCommerce fallbacks.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_product_commercial_description( WC_Product $product ) {
		$description = $product->get_meta( self::PRODUCT_DESC_META );

		if ( ! $description ) {
			$description = $product->get_short_description();
		}

		if ( ! $description ) {
			$description = $product->get_description();
		}

		return (string) $description;
	}

	/**
	 * Get the line category for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	private function get_product_line( WC_Product $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			$line = $this->normalize_line_term( $term );

			if ( $line ) {
				return array(
					'id'    => absint( $line->term_id ),
					'name'  => $line->name,
					'color' => $this->get_term_color( $line->term_id ),
				);
			}
		}

		return null;
	}

	/**
	 * Convert any category to its parent line category.
	 *
	 * @param WP_Term $term Product category.
	 * @return WP_Term|null
	 */
	private function normalize_line_term( $term ) {
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
	 * Get configured category color.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	private function get_term_color( $term_id ) {
		$color = sanitize_hex_color( get_term_meta( $term_id, self::TERM_COLOR_META, true ) );

		return $color ? $color : self::DEFAULT_LINE_COLOR;
	}

	/**
	 * Build results summary.
	 *
	 * @param int $total       Total results.
	 * @param int $category_id Selected category.
	 * @return string
	 */
	private function get_results_summary( $total, $category_id ) {
		$summary = sprintf(
			/* translators: %d product count. */
			_n( 'Mostrando %d producto', 'Mostrando %d productos', $total, 'cadv-woo-functionalities' ),
			$total
		);

		$line_label = $this->get_line_label( $category_id );

		if ( $line_label ) {
			$summary .= ' - ' . sprintf(
				/* translators: %s selected line. */
				__( 'Linea: %s', 'cadv-woo-functionalities' ),
				$line_label
			);
		}

		return $summary;
	}

	/**
	 * Get selected line label.
	 *
	 * @param int $category_id Selected category.
	 * @return string
	 */
	private function get_line_label( $category_id ) {
		if ( ! $category_id ) {
			return '';
		}

		$term = get_term( $category_id, 'product_cat' );

		return $term instanceof WP_Term && ! is_wp_error( $term ) ? $term->name : '';
	}

	/**
	 * Format ICA registration display.
	 *
	 * @param string $value Raw ICA value.
	 * @return string
	 */
	private function format_ica_registration( $value ) {
		$value = trim( $value );

		if ( preg_match( '/^reg\.?\s*ica/i', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^ica/i', $value ) ) {
			return 'Reg. ' . $value;
		}

		return 'Reg. ICA ' . $value;
	}

	/**
	 * Build WhatsApp URL for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_whatsapp_url( WC_Product $product ) {
		$phone = preg_replace( '/[^0-9]/', '', (string) get_option( self::OPTION_PHONE, '' ) );

		if ( ! $phone ) {
			return '';
		}

		$template = get_option( self::OPTION_MESSAGE, $this->get_default_message_template() );

		if ( '' === trim( $template ) ) {
			$template = $this->get_default_message_template();
		}

		$message = strtr(
			$template,
			array(
				'{product_name}' => wp_strip_all_tags( $product->get_name() ),
				'{product_url}'  => get_permalink( $product->get_id() ),
			)
		);

		return sprintf( 'https://wa.me/%1$s?text=%2$s', rawurlencode( $phone ), rawurlencode( $message ) );
	}

	/**
	 * Default WhatsApp message.
	 *
	 * @return string
	 */
	private function get_default_message_template() {
		return __( 'Hola, estoy viendo el producto {product_name} en la pagina web y quisiera mas informacion. {product_url}', 'cadv-woo-functionalities' );
	}

	/**
	 * Sanitize products per page.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function sanitize_per_page( $value ) {
		return max( 1, min( 48, absint( $value ) ) );
	}

	/**
	 * Sanitize column count.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function sanitize_columns( $value ) {
		return max( 1, min( 4, absint( $value ) ) );
	}

	/**
	 * Parse truthy shortcode attribute.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function is_truthy( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on', 'si' ), true );
	}

	/**
	 * Check WooCommerce availability.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Product' );
	}
}
