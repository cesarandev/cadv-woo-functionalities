<?php
/**
 * Main plugin controller.
 *
 * @package CADVTailoredTo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the calculator, requests and CRM adapter.
 */
final class CADV_Tailored_To_Calculator {

	const SHORTCODE       = 'cadv_tailored_to_calculator';
	const REQUEST_TYPE    = 'cadv_tt_request';
	const PREVIEW_ACTION  = 'cadv_tt_preview_formula';
	const SUBMIT_ACTION   = 'cadv_tt_submit_request';
	const NONCE_ACTION    = 'cadv_tt_public_calculator';
	const CRM_FILTER      = 'cadv_woo_functionalities_ingest_lead';
	const MAX_PREVIEWS    = 50;
	const MAX_SUBMISSIONS = 10;

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Assets have been configured.
	 *
	 * @var bool
	 */
	private $assets_localized = false;

	/**
	 * Get singleton.
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
	 * Activation.
	 */
	public static function activate() {
		self::instance()->register_request_type();
		flush_rewrite_rules();
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_request_type' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_ACTION, array( $this, 'handle_preview' ) );
		add_action( 'wp_ajax_nopriv_' . self::PREVIEW_ACTION, array( $this, 'handle_preview' ) );
		add_action( 'wp_ajax_' . self::SUBMIT_ACTION, array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_' . self::SUBMIT_ACTION, array( $this, 'handle_submission' ) );
		add_filter( 'manage_' . self::REQUEST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::REQUEST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::REQUEST_TYPE, array( $this, 'add_request_meta_box' ) );
	}

	/**
	 * Register request storage owned by this plugin.
	 */
	public function register_request_type() {
		register_post_type(
			self::REQUEST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Solicitudes Tailored To', 'cadv-tailored-to' ),
					'singular_name' => __( 'Solicitud Tailored To', 'cadv-tailored-to' ),
					'menu_name'     => __( 'Tailored To', 'cadv-tailored-to' ),
					'edit_item'     => __( 'Ver solicitud Tailored To', 'cadv-tailored-to' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-chart-area',
				'supports'            => array( 'title' ),
				'capabilities'        => array(
					'edit_post'              => 'manage_woocommerce',
					'read_post'              => 'manage_woocommerce',
					'delete_post'            => 'manage_woocommerce',
					'edit_posts'             => 'manage_woocommerce',
					'edit_others_posts'      => 'manage_woocommerce',
					'publish_posts'          => 'manage_woocommerce',
					'read_private_posts'     => 'manage_woocommerce',
					'delete_posts'           => 'manage_woocommerce',
					'delete_private_posts'   => 'manage_woocommerce',
					'delete_published_posts' => 'manage_woocommerce',
					'delete_others_posts'    => 'manage_woocommerce',
					'edit_private_posts'     => 'manage_woocommerce',
					'edit_published_posts'   => 'manage_woocommerce',
					'create_posts'           => 'do_not_allow',
				),
				'map_meta_cap'         => false,
				'exclude_from_search'  => true,
				'publicly_queryable'   => false,
				'show_in_rest'         => false,
			)
		);
	}

	/**
	 * Register front-end assets.
	 */
	public function register_assets() {
		wp_register_style(
			'cadv-tt-fonts',
			'https://fonts.googleapis.com/css2?family=Exo:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap',
			array(),
			null
		);
		wp_register_style(
			'cadv-tailored-to',
			CADV_TT_URL . 'assets/css/cadv-tailored-to-calculator.css',
			array( 'cadv-tt-fonts' ),
			CADV_TT_VERSION
		);
		wp_register_script(
			'cadv-tailored-to',
			CADV_TT_URL . 'assets/js/cadv-tailored-to-calculator.js',
			array(),
			CADV_TT_VERSION,
			true
		);
	}

	/**
	 * Enqueue and configure assets.
	 */
	private function enqueue_assets() {
		if ( ! wp_style_is( 'cadv-tailored-to', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'cadv-tt-fonts' );
		wp_enqueue_style( 'cadv-tailored-to' );
		wp_enqueue_script( 'cadv-tailored-to' );

		if ( ! $this->assets_localized ) {
			$phone = preg_replace( '/\D+/', '', (string) get_option( 'cadv_woo_functionalities_whatsapp_phone', '573164781412' ) );
			$phone = (string) apply_filters( 'cadv_tt_whatsapp_phone', $phone );

			wp_localize_script(
				'cadv-tailored-to',
				'CADVTailoredTo',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'previewAction' => self::PREVIEW_ACTION,
					'submitAction' => self::SUBMIT_ACTION,
					'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
					'whatsappBase' => $phone ? 'https://wa.me/' . rawurlencode( $phone ) : '',
					'crmAvailable' => has_filter( self::CRM_FILTER ),
					'messages'     => array(
						'required' => __( 'Completa los campos obligatorios antes de continuar.', 'cadv-tailored-to' ),
						'loading'  => __( 'Construyendo la simulación...', 'cadv-tailored-to' ),
						'saving'   => __( 'Guardando la solicitud...', 'cadv-tailored-to' ),
						'error'    => __( 'No pudimos procesar la solicitud. Inténtalo de nuevo.', 'cadv-tailored-to' ),
					),
				)
			);
			$this->assets_localized = true;
		}

		if ( did_action( 'wp_head' ) ) {
			add_action( 'wp_footer', array( $this, 'print_late_styles' ), 1 );
		}
	}

	/**
	 * Support Elementor shortcodes resolved after wp_head.
	 */
	public function print_late_styles() {
		if ( wp_style_is( 'cadv-tailored-to', 'enqueued' ) && ! wp_style_is( 'cadv-tailored-to', 'done' ) ) {
			wp_print_styles( array( 'cadv-tt-fonts', 'cadv-tailored-to' ) );
		}
	}

	/**
	 * Render the Elementor-compatible calculator.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title'       => __( 'Construya su perfil Tailored To', 'cadv-tailored-to' ),
				'description' => __( 'Una simulación guiada para preparar una solicitud de fertilización personalizada.', 'cadv-tailored-to' ),
				'accent'      => '#203212',
			),
			$atts,
			self::SHORTCODE
		);

		$this->enqueue_assets();
		$instance_id = wp_unique_id( 'cadv-tt-' );
		$crops       = CADV_TT_Formula_Engine::get_crop_profiles();
		$stages      = CADV_TT_Formula_Engine::get_stage_profiles();
		$privacy_url = get_privacy_policy_url();
		$accent      = sanitize_hex_color( $atts['accent'] );
		$accent      = $accent ? $accent : '#203212';

		ob_start();
		?>
		<section class="cadv-tt" id="<?php echo esc_attr( $instance_id ); ?>" style="--cadv-tt-accent: <?php echo esc_attr( $accent ); ?>;" data-cadv-tt-calculator>
			<header class="cadv-tt__intro">
				<span class="cadv-tt__eyebrow"><?php esc_html_e( 'Tailored To · Simulador', 'cadv-tailored-to' ); ?></span>
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
				<p><?php echo esc_html( $atts['description'] ); ?></p>
				<div class="cadv-tt__notice">
					<strong><?php esc_html_e( 'Alcance de esta versión:', 'cadv-tailored-to' ); ?></strong>
					<?php esc_html_e( 'genera una fórmula N-P₂O₅-K₂O demostrativa. No calcula dosis, no reemplaza análisis ni constituye una recomendación agronómica.', 'cadv-tailored-to' ); ?>
				</div>
			</header>

			<form class="cadv-tt__wizard" data-cadv-tt-wizard novalidate>
				<nav class="cadv-tt__progress" aria-label="<?php esc_attr_e( 'Progreso del simulador', 'cadv-tailored-to' ); ?>">
					<?php foreach ( array( 1 => 'Cultivo', 2 => 'Lote', 3 => 'Diagnóstico', 4 => 'Aplicación', 5 => 'Manejo', 6 => 'Resultado' ) as $number => $label ) : ?>
						<div class="cadv-tt__progress-item<?php echo 1 === $number ? ' is-active' : ''; ?>" data-cadv-tt-progress="<?php echo esc_attr( $number ); ?>">
							<span><?php echo esc_html( $number ); ?></span>
							<strong><?php echo esc_html( $label ); ?></strong>
						</div>
					<?php endforeach; ?>
				</nav>

				<div class="cadv-tt__message" data-cadv-tt-message role="status" aria-live="polite"></div>

				<fieldset class="cadv-tt__step is-active" data-cadv-tt-step="1">
					<legend><?php esc_html_e( '¿Cuál es su cultivo?', 'cadv-tailored-to' ); ?></legend>
					<p><?php esc_html_e( 'Seleccione el cultivo para construir un perfil demostrativo.', 'cadv-tailored-to' ); ?></p>
					<div class="cadv-tt__crop-grid">
						<?php foreach ( $crops as $key => $profile ) : ?>
							<label class="cadv-tt__choice-card">
								<input type="radio" name="crop" value="<?php echo esc_attr( $key ); ?>" required />
								<span class="cadv-tt__leaf" aria-hidden="true">⌁</span>
								<strong><?php echo esc_html( $profile['label'] ); ?></strong>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="cadv-tt__field-grid cadv-tt__field-grid--spaced">
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Variedad o material', 'cadv-tailored-to' ); ?></span>
							<input type="text" name="variety" maxlength="120" placeholder="<?php esc_attr_e( 'Opcional', 'cadv-tailored-to' ); ?>" />
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Edad o fecha de siembra', 'cadv-tailored-to' ); ?></span>
							<input type="text" name="crop_age" maxlength="80" placeholder="<?php esc_attr_e( 'Ej. 4 años o marzo de 2024', 'cadv-tailored-to' ); ?>" />
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Etapa del cultivo *', 'cadv-tailored-to' ); ?></span>
							<select name="stage" required>
								<option value=""><?php esc_html_e( 'Seleccione', 'cadv-tailored-to' ); ?></option>
								<?php foreach ( $stages as $key => $profile ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $profile['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Sistema de producción *', 'cadv-tailored-to' ); ?></span>
							<select name="production_system" required>
								<option value=""><?php esc_html_e( 'Seleccione', 'cadv-tailored-to' ); ?></option>
								<option value="open_field"><?php esc_html_e( 'Campo abierto', 'cadv-tailored-to' ); ?></option>
								<option value="protected"><?php esc_html_e( 'Cultivo protegido', 'cadv-tailored-to' ); ?></option>
								<option value="perennial"><?php esc_html_e( 'Sistema perenne', 'cadv-tailored-to' ); ?></option>
								<option value="other"><?php esc_html_e( 'Otro sistema', 'cadv-tailored-to' ); ?></option>
							</select>
						</label>
					</div>
					<div class="cadv-tt__nav cadv-tt__nav--end">
						<button type="button" class="cadv-tt__button cadv-tt__button--primary" data-cadv-tt-next><?php esc_html_e( 'Siguiente', 'cadv-tailored-to' ); ?><span aria-hidden="true">→</span></button>
					</div>
				</fieldset>

				<fieldset class="cadv-tt__step" data-cadv-tt-step="2" hidden>
					<legend><?php esc_html_e( 'Lote y objetivo productivo', 'cadv-tailored-to' ); ?></legend>
					<p><?php esc_html_e( 'Defina el alcance del caso y qué espera mejorar con la formulación.', 'cadv-tailored-to' ); ?></p>
					<div class="cadv-tt__field-grid">
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Ubicación del lote *', 'cadv-tailored-to' ); ?></span>
							<input type="text" name="location" maxlength="160" placeholder="<?php esc_attr_e( 'Municipio, departamento', 'cadv-tailored-to' ); ?>" required />
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Área *', 'cadv-tailored-to' ); ?></span>
							<span class="cadv-tt__input-unit"><input type="number" name="area" min="0.1" max="100000" step="0.1" required /><em>ha</em></span>
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Meta de rendimiento *', 'cadv-tailored-to' ); ?></span>
							<span class="cadv-tt__input-unit"><input type="number" name="yield_goal" min="0.1" max="100000" step="0.1" required /><em>t/ha</em></span>
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Rendimiento actual', 'cadv-tailored-to' ); ?></span>
							<span class="cadv-tt__input-unit"><input type="number" name="current_yield" min="0" max="100000" step="0.1" /><em>t/ha</em></span>
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Objetivo principal *', 'cadv-tailored-to' ); ?></span>
							<select name="primary_goal" required>
								<option value=""><?php esc_html_e( 'Seleccione', 'cadv-tailored-to' ); ?></option>
								<option value="adapt_formula"><?php esc_html_e( 'Adaptar la relación nutricional', 'cadv-tailored-to' ); ?></option>
								<option value="correct_deficiency"><?php esc_html_e( 'Atender una deficiencia', 'cadv-tailored-to' ); ?></option>
								<option value="avoid_excess"><?php esc_html_e( 'Evitar un nutriente en exceso', 'cadv-tailored-to' ); ?></option>
								<option value="improve_efficiency"><?php esc_html_e( 'Mejorar eficiencia del programa', 'cadv-tailored-to' ); ?></option>
								<option value="increase_yield"><?php esc_html_e( 'Acompañar una mayor meta productiva', 'cadv-tailored-to' ); ?></option>
								<option value="other"><?php esc_html_e( 'Otro objetivo', 'cadv-tailored-to' ); ?></option>
							</select>
						</label>
						<label class="cadv-tt__field cadv-tt__field--full">
							<span><?php esc_html_e( 'Problema observado o condición que desea corregir', 'cadv-tailored-to' ); ?></span>
							<textarea name="observed_problem" maxlength="700" rows="3" placeholder="<?php esc_attr_e( 'Describa síntomas, variación del lote, antecedentes o restricciones conocidas.', 'cadv-tailored-to' ); ?>"></textarea>
						</label>
					</div>
					<div class="cadv-tt__nav">
						<button type="button" class="cadv-tt__button cadv-tt__button--outline" data-cadv-tt-back><span aria-hidden="true">←</span><?php esc_html_e( 'Atrás', 'cadv-tailored-to' ); ?></button>
						<button type="button" class="cadv-tt__button cadv-tt__button--primary" data-cadv-tt-next><?php esc_html_e( 'Siguiente', 'cadv-tailored-to' ); ?><span aria-hidden="true">→</span></button>
					</div>
				</fieldset>

				<fieldset class="cadv-tt__step" data-cadv-tt-step="3" hidden>
					<legend><?php esc_html_e( 'Diagnóstico disponible', 'cadv-tailored-to' ); ?></legend>
					<p><?php esc_html_e( 'Cuéntenos qué evidencia existe. Los campos técnicos aparecen únicamente cuando son pertinentes.', 'cadv-tailored-to' ); ?></p>

					<div class="cadv-tt__analysis-grid">
						<section class="cadv-tt__analysis-card">
							<div class="cadv-tt__analysis-heading">
								<span>01</span>
								<div><strong><?php esc_html_e( 'Análisis de suelo', 'cadv-tailored-to' ); ?></strong><small><?php esc_html_e( 'Vigencia, laboratorio y variables clave', 'cadv-tailored-to' ); ?></small></div>
							</div>
							<div class="cadv-tt__segmented cadv-tt__segmented--compact">
								<?php foreach ( array( 'current' => 'Vigente', 'old' => 'Anterior', 'none' => 'No tengo' ) as $value => $caption ) : ?>
									<label><input type="radio" name="soil_analysis_status" value="<?php echo esc_attr( $value ); ?>" required /><span><?php echo esc_html( $caption ); ?></span></label>
								<?php endforeach; ?>
							</div>
							<div class="cadv-tt__conditional" data-cadv-tt-conditional="soil" hidden>
								<div class="cadv-tt__field-grid">
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Fecha del análisis', 'cadv-tailored-to' ); ?></span><input type="date" name="soil_analysis_date" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Laboratorio', 'cadv-tailored-to' ); ?></span><input type="text" name="soil_laboratory" maxlength="120" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Profundidad de muestreo', 'cadv-tailored-to' ); ?></span><input type="text" name="soil_sampling_depth" maxlength="60" placeholder="<?php esc_attr_e( 'Ej. 0–20 cm', 'cadv-tailored-to' ); ?>" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Textura reportada', 'cadv-tailored-to' ); ?></span><input type="text" name="soil_texture" maxlength="80" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'pH', 'cadv-tailored-to' ); ?></span><input type="number" name="soil_ph" min="0" max="14" step="0.01" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Materia orgánica', 'cadv-tailored-to' ); ?></span><span class="cadv-tt__input-unit"><input type="number" name="soil_organic_matter" min="0" max="100" step="0.01" /><em>%</em></span></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'CIC reportada', 'cadv-tailored-to' ); ?></span><input type="number" name="soil_cic" min="0" max="1000" step="0.01" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Saturación de aluminio', 'cadv-tailored-to' ); ?></span><span class="cadv-tt__input-unit"><input type="number" name="soil_al_saturation" min="0" max="100" step="0.01" /><em>%</em></span></label>
								</div>
							</div>
						</section>

						<section class="cadv-tt__analysis-card">
							<div class="cadv-tt__analysis-heading">
								<span>02</span>
								<div><strong><?php esc_html_e( 'Análisis foliar', 'cadv-tailored-to' ); ?></strong><small><?php esc_html_e( 'Representatividad y hallazgos principales', 'cadv-tailored-to' ); ?></small></div>
							</div>
							<div class="cadv-tt__segmented cadv-tt__segmented--compact">
								<?php foreach ( array( 'current' => 'Vigente', 'old' => 'Anterior', 'none' => 'No tengo' ) as $value => $caption ) : ?>
									<label><input type="radio" name="foliar_analysis_status" value="<?php echo esc_attr( $value ); ?>" required /><span><?php echo esc_html( $caption ); ?></span></label>
								<?php endforeach; ?>
							</div>
							<div class="cadv-tt__conditional" data-cadv-tt-conditional="foliar" hidden>
								<div class="cadv-tt__field-grid">
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Fecha del análisis', 'cadv-tailored-to' ); ?></span><input type="date" name="foliar_analysis_date" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Laboratorio', 'cadv-tailored-to' ); ?></span><input type="text" name="foliar_laboratory" maxlength="120" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Tejido u hoja muestreada', 'cadv-tailored-to' ); ?></span><input type="text" name="foliar_tissue" maxlength="120" /></label>
									<label class="cadv-tt__field"><span><?php esc_html_e( 'Fecha o etapa del muestreo', 'cadv-tailored-to' ); ?></span><input type="text" name="foliar_sampling_stage" maxlength="100" /></label>
									<label class="cadv-tt__field cadv-tt__field--full"><span><?php esc_html_e( 'Hallazgo principal', 'cadv-tailored-to' ); ?></span><textarea name="foliar_notes" maxlength="500" rows="2" placeholder="<?php esc_attr_e( 'Ej. boro bajo, cobre alto o balance general adecuado.', 'cadv-tailored-to' ); ?>"></textarea></label>
								</div>
							</div>
						</section>
					</div>

					<div class="cadv-tt__section-heading">
						<strong><?php esc_html_e( 'Lectura declarada de macronutrientes', 'cadv-tailored-to' ); ?></strong>
						<span><?php esc_html_e( 'Si no conoce el dato, seleccione “Sin dato”.', 'cadv-tailored-to' ); ?></span>
					</div>
					<?php
					$nutrients = array(
						'n' => __( 'Nitrógeno (N)', 'cadv-tailored-to' ),
						'p' => __( 'Fósforo (P)', 'cadv-tailored-to' ),
						'k' => __( 'Potasio (K)', 'cadv-tailored-to' ),
					);
					foreach ( $nutrients as $key => $label ) :
						?>
						<div class="cadv-tt__nutrient">
							<strong><?php echo esc_html( $label ); ?></strong>
							<div class="cadv-tt__segmented cadv-tt__segmented--four">
								<?php foreach ( array( 'low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto', 'unknown' => 'Sin dato' ) as $value => $caption ) : ?>
									<label><input type="radio" name="<?php echo esc_attr( $key ); ?>_level" value="<?php echo esc_attr( $value ); ?>" required /><span><?php echo esc_html( $caption ); ?></span></label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="cadv-tt__micro-panel">
						<div class="cadv-tt__section-heading">
							<strong><?php esc_html_e( 'Micronutrientes sensibles', 'cadv-tailored-to' ); ?></strong>
							<span><?php esc_html_e( 'Especial prudencia con boro y cobre.', 'cadv-tailored-to' ); ?></span>
						</div>
						<div class="cadv-tt__field-grid cadv-tt__field-grid--three">
							<?php foreach ( array( 'boron_status' => 'Boro (B)', 'zinc_status' => 'Zinc (Zn)', 'copper_status' => 'Cobre (Cu)' ) as $name => $label ) : ?>
								<label class="cadv-tt__field">
									<span><?php echo esc_html( $label ); ?></span>
									<select name="<?php echo esc_attr( $name ); ?>" required>
										<option value="unknown"><?php esc_html_e( 'Sin dato', 'cadv-tailored-to' ); ?></option>
										<option value="low"><?php esc_html_e( 'Reportado bajo', 'cadv-tailored-to' ); ?></option>
										<option value="adequate"><?php esc_html_e( 'Adecuado', 'cadv-tailored-to' ); ?></option>
										<option value="high"><?php esc_html_e( 'Reportado alto', 'cadv-tailored-to' ); ?></option>
									</select>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="cadv-tt__nav">
						<button type="button" class="cadv-tt__button cadv-tt__button--outline" data-cadv-tt-back><span aria-hidden="true">←</span><?php esc_html_e( 'Atrás', 'cadv-tailored-to' ); ?></button>
						<button type="button" class="cadv-tt__button cadv-tt__button--primary" data-cadv-tt-next><?php esc_html_e( 'Siguiente', 'cadv-tailored-to' ); ?><span aria-hidden="true">→</span></button>
					</div>
				</fieldset>

				<fieldset class="cadv-tt__step" data-cadv-tt-step="4" hidden>
					<legend><?php esc_html_e( 'Aplicación, riego y agua', 'cadv-tailored-to' ); ?></legend>
					<p><?php esc_html_e( 'La fuente correcta depende también de cómo se aplica y de la calidad del agua.', 'cadv-tailored-to' ); ?></p>
					<div class="cadv-tt__field-grid">
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Programa de aplicación *', 'cadv-tailored-to' ); ?></span>
							<select name="application" required>
								<option value=""><?php esc_html_e( 'Seleccione', 'cadv-tailored-to' ); ?></option>
								<option value="soil"><?php esc_html_e( 'Aplicación al suelo', 'cadv-tailored-to' ); ?></option>
								<option value="fertigation"><?php esc_html_e( 'Fertirriego', 'cadv-tailored-to' ); ?></option>
								<option value="mixed"><?php esc_html_e( 'Programa mixto', 'cadv-tailored-to' ); ?></option>
							</select>
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Sistema de riego *', 'cadv-tailored-to' ); ?></span>
							<select name="irrigation_system" required>
								<option value=""><?php esc_html_e( 'Seleccione', 'cadv-tailored-to' ); ?></option>
								<option value="rainfed"><?php esc_html_e( 'Sin riego / secano', 'cadv-tailored-to' ); ?></option>
								<option value="drip"><?php esc_html_e( 'Goteo', 'cadv-tailored-to' ); ?></option>
								<option value="micro"><?php esc_html_e( 'Microaspersión', 'cadv-tailored-to' ); ?></option>
								<option value="sprinkler"><?php esc_html_e( 'Aspersión', 'cadv-tailored-to' ); ?></option>
								<option value="pivot"><?php esc_html_e( 'Pivote', 'cadv-tailored-to' ); ?></option>
								<option value="surface"><?php esc_html_e( 'Superficie o gravedad', 'cadv-tailored-to' ); ?></option>
								<option value="other"><?php esc_html_e( 'Otro', 'cadv-tailored-to' ); ?></option>
							</select>
						</label>
					</div>

					<div class="cadv-tt__conditional cadv-tt__conditional--panel" data-cadv-tt-conditional="irrigation" hidden>
						<div class="cadv-tt__section-heading">
							<strong><?php esc_html_e( 'Caracterización del agua', 'cadv-tailored-to' ); ?></strong>
							<span><?php esc_html_e( 'Datos básicos declarados; no determinan por sí solos la aptitud.', 'cadv-tailored-to' ); ?></span>
						</div>
						<div class="cadv-tt__field-grid">
							<label class="cadv-tt__field">
								<span><?php esc_html_e( 'Disponibilidad de análisis de agua', 'cadv-tailored-to' ); ?></span>
								<select name="water_analysis_status">
									<option value="none"><?php esc_html_e( 'No tengo', 'cadv-tailored-to' ); ?></option>
									<option value="current"><?php esc_html_e( 'Vigente', 'cadv-tailored-to' ); ?></option>
									<option value="old"><?php esc_html_e( 'Anterior', 'cadv-tailored-to' ); ?></option>
								</select>
							</label>
							<label class="cadv-tt__field"><span><?php esc_html_e( 'Fecha del análisis', 'cadv-tailored-to' ); ?></span><input type="date" name="water_analysis_date" /></label>
							<label class="cadv-tt__field"><span><?php esc_html_e( 'pH del agua', 'cadv-tailored-to' ); ?></span><input type="number" name="water_ph" min="0" max="14" step="0.01" /></label>
							<label class="cadv-tt__field"><span><?php esc_html_e( 'Conductividad eléctrica', 'cadv-tailored-to' ); ?></span><span class="cadv-tt__input-unit"><input type="number" name="water_ec" min="0" max="100" step="0.01" /><em>dS/m</em></span></label>
							<label class="cadv-tt__field"><span><?php esc_html_e( 'Bicarbonatos reportados', 'cadv-tailored-to' ); ?></span><input type="text" name="water_bicarbonates" maxlength="80" placeholder="<?php esc_attr_e( 'Incluya valor y unidad', 'cadv-tailored-to' ); ?>" /></label>
							<label class="cadv-tt__field"><span><?php esc_html_e( 'Frecuencia de riego', 'cadv-tailored-to' ); ?></span><input type="text" name="irrigation_frequency" maxlength="100" placeholder="<?php esc_attr_e( 'Ej. 3 veces por semana', 'cadv-tailored-to' ); ?>" /></label>
						</div>
					</div>

					<div class="cadv-tt__conditional cadv-tt__conditional--panel" data-cadv-tt-conditional="fertigation" hidden>
						<div class="cadv-tt__section-heading">
							<strong><?php esc_html_e( 'Información de fertirriego', 'cadv-tailored-to' ); ?></strong>
							<span><?php esc_html_e( 'Necesaria antes de plantear concentraciones o compatibilidades.', 'cadv-tailored-to' ); ?></span>
						</div>
						<div class="cadv-tt__field-grid">
							<label class="cadv-tt__field"><span><?php esc_html_e( 'Caudal del sistema', 'cadv-tailored-to' ); ?></span><input type="text" name="irrigation_flow" maxlength="100" placeholder="<?php esc_attr_e( 'Incluya valor y unidad', 'cadv-tailored-to' ); ?>" /></label>
							<label class="cadv-tt__field"><span><?php esc_html_e( 'Volumen o tiempo por evento', 'cadv-tailored-to' ); ?></span><input type="text" name="irrigation_event" maxlength="100" /></label>
							<label class="cadv-tt__field cadv-tt__field--full"><span><?php esc_html_e( 'Tanques, inyectores o restricciones conocidas', 'cadv-tailored-to' ); ?></span><textarea name="fertigation_notes" maxlength="500" rows="2"></textarea></label>
						</div>
					</div>

					<div class="cadv-tt__nav">
						<button type="button" class="cadv-tt__button cadv-tt__button--outline" data-cadv-tt-back><span aria-hidden="true">←</span><?php esc_html_e( 'Atrás', 'cadv-tailored-to' ); ?></button>
						<button type="button" class="cadv-tt__button cadv-tt__button--primary" data-cadv-tt-next><?php esc_html_e( 'Siguiente', 'cadv-tailored-to' ); ?><span aria-hidden="true">→</span></button>
					</div>
				</fieldset>

				<fieldset class="cadv-tt__step" data-cadv-tt-step="5" hidden>
					<legend><?php esc_html_e( 'Manejo actual y prioridades', 'cadv-tailored-to' ); ?></legend>
					<p><?php esc_html_e( 'Últimos datos para orientar las 4R y preparar la conversación con el equipo técnico.', 'cadv-tailored-to' ); ?></p>
					<div class="cadv-tt__field-grid">
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Fraccionamiento actual *', 'cadv-tailored-to' ); ?></span>
							<select name="fertilizer_frequency" required>
								<option value=""><?php esc_html_e( 'Seleccione', 'cadv-tailored-to' ); ?></option>
								<option value="once"><?php esc_html_e( 'Una aplicación por ciclo', 'cadv-tailored-to' ); ?></option>
								<option value="two"><?php esc_html_e( 'Dos aplicaciones', 'cadv-tailored-to' ); ?></option>
								<option value="three_plus"><?php esc_html_e( 'Tres o más aplicaciones', 'cadv-tailored-to' ); ?></option>
								<option value="continuous"><?php esc_html_e( 'Continuo por fertirriego', 'cadv-tailored-to' ); ?></option>
								<option value="unknown"><?php esc_html_e( 'Por definir', 'cadv-tailored-to' ); ?></option>
							</select>
						</label>
						<label class="cadv-tt__field">
							<span><?php esc_html_e( 'Lugar o método de aplicación *', 'cadv-tailored-to' ); ?></span>
							<select name="application_method" required>
								<option value=""><?php esc_html_e( 'Seleccione', 'cadv-tailored-to' ); ?></option>
								<option value="broadcast"><?php esc_html_e( 'Al voleo', 'cadv-tailored-to' ); ?></option>
								<option value="banded"><?php esc_html_e( 'En banda', 'cadv-tailored-to' ); ?></option>
								<option value="localized"><?php esc_html_e( 'Localizada por planta', 'cadv-tailored-to' ); ?></option>
								<option value="fertigation"><?php esc_html_e( 'Fertirriego', 'cadv-tailored-to' ); ?></option>
								<option value="mechanized"><?php esc_html_e( 'Aplicación mecanizada', 'cadv-tailored-to' ); ?></option>
								<option value="other"><?php esc_html_e( 'Otro', 'cadv-tailored-to' ); ?></option>
							</select>
						</label>
						<label class="cadv-tt__field"><span><?php esc_html_e( 'Fórmula o fuentes usadas actualmente', 'cadv-tailored-to' ); ?></span><input type="text" name="current_fertilizer" maxlength="200" /></label>
						<label class="cadv-tt__field"><span><?php esc_html_e( 'Última aplicación', 'cadv-tailored-to' ); ?></span><input type="text" name="last_application" maxlength="120" placeholder="<?php esc_attr_e( 'Fecha aproximada o etapa', 'cadv-tailored-to' ); ?>" /></label>
					</div>

					<div class="cadv-tt__risk-panel">
						<div class="cadv-tt__section-heading">
							<strong><?php esc_html_e( 'Riesgos o limitantes observados', 'cadv-tailored-to' ); ?></strong>
							<span><?php esc_html_e( 'Seleccione solo los que reconoce en el lote.', 'cadv-tailored-to' ); ?></span>
						</div>
						<div class="cadv-tt__check-grid">
							<?php foreach ( array( 'volatilization' => 'Volatilización', 'leaching' => 'Lixiviación', 'runoff' => 'Escorrentía', 'salinity' => 'Salinidad', 'acidity' => 'Acidez o aluminio', 'drainage' => 'Drenaje o compactación', 'erosion' => 'Erosión', 'unknown' => 'No identificado' ) as $value => $caption ) : ?>
								<label><input type="checkbox" name="management_risks[]" value="<?php echo esc_attr( $value ); ?>" /><span><?php echo esc_html( $caption ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</div>

					<label class="cadv-tt__field cadv-tt__field--full">
						<span><?php esc_html_e( 'Restricciones operativas o comerciales', 'cadv-tailored-to' ); ?></span>
						<textarea name="operational_constraints" maxlength="700" rows="3" placeholder="<?php esc_attr_e( 'Equipos disponibles, número máximo de aplicaciones, presentación, almacenamiento u otra condición relevante.', 'cadv-tailored-to' ); ?>"></textarea>
					</label>

					<details class="cadv-tt__converter">
						<summary><?php esc_html_e( 'Herramienta adicional: conversor elemental ↔ óxido', 'cadv-tailored-to' ); ?></summary>
						<p><?php esc_html_e( 'Convierte unidades; no determina requerimientos ni dosis.', 'cadv-tailored-to' ); ?></p>
						<div class="cadv-tt__converter-grid">
							<label><span><?php esc_html_e( 'Conversión', 'cadv-tailored-to' ); ?></span>
								<select data-cadv-tt-conversion>
									<option value="2.2914">P → P₂O₅</option>
									<option value="1.2046">K → K₂O</option>
									<option value="1.3992">Ca → CaO</option>
									<option value="1.6583">Mg → MgO</option>
								</select>
							</label>
							<label><span><?php esc_html_e( 'Valor elemental', 'cadv-tailored-to' ); ?></span><input type="number" min="0" step="0.01" data-cadv-tt-elemental /></label>
							<label><span><?php esc_html_e( 'Valor como óxido', 'cadv-tailored-to' ); ?></span><input type="text" data-cadv-tt-oxide readonly /></label>
						</div>
					</details>

					<div class="cadv-tt__nav">
						<button type="button" class="cadv-tt__button cadv-tt__button--outline" data-cadv-tt-back><span aria-hidden="true">←</span><?php esc_html_e( 'Atrás', 'cadv-tailored-to' ); ?></button>
						<button type="button" class="cadv-tt__button cadv-tt__button--primary" data-cadv-tt-next><?php esc_html_e( 'Crear simulación', 'cadv-tailored-to' ); ?><span aria-hidden="true">→</span></button>
					</div>
				</fieldset>

				<section class="cadv-tt__step cadv-tt__result" data-cadv-tt-step="6" hidden aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-result-title">
					<span class="cadv-tt__result-badge"><?php esc_html_e( 'Fórmula simulada', 'cadv-tailored-to' ); ?></span>
					<h3 id="<?php echo esc_attr( $instance_id ); ?>-result-title" data-cadv-tt-result-formula>--</h3>
					<p data-cadv-tt-result-context></p>
					<div class="cadv-tt__result-grid">
						<div><strong><?php esc_html_e( 'N', 'cadv-tailored-to' ); ?></strong><span data-cadv-tt-result-n>--</span></div>
						<div><strong><?php esc_html_e( 'P₂O₅', 'cadv-tailored-to' ); ?></strong><span data-cadv-tt-result-p>--</span></div>
						<div><strong><?php esc_html_e( 'K₂O', 'cadv-tailored-to' ); ?></strong><span data-cadv-tt-result-k>--</span></div>
					</div>
					<div class="cadv-tt__dose-block">
						<strong><?php esc_html_e( 'Dosis y cantidad total', 'cadv-tailored-to' ); ?></strong>
						<span><?php esc_html_e( 'Pendientes de análisis de suelo, análisis foliar y validación de la meta de rendimiento.', 'cadv-tailored-to' ); ?></span>
					</div>
					<div class="cadv-tt__readiness">
						<div class="cadv-tt__section-heading">
							<strong><?php esc_html_e( 'Preparación del expediente', 'cadv-tailored-to' ); ?></strong>
							<span data-cadv-tt-result-readiness></span>
						</div>
						<div class="cadv-tt__evidence-grid" data-cadv-tt-result-evidence></div>
					</div>
					<div class="cadv-tt__result-details">
						<div>
							<h4><?php esc_html_e( 'Por qué se construyó así', 'cadv-tailored-to' ); ?></h4>
							<ul data-cadv-tt-result-notes></ul>
						</div>
						<div>
							<h4><?php esc_html_e( 'Información pendiente', 'cadv-tailored-to' ); ?></h4>
							<ul data-cadv-tt-result-missing></ul>
						</div>
					</div>
					<p class="cadv-tt__disclaimer"><?php esc_html_e( 'La fórmula mostrada es un ejercicio demostrativo y no una composición garantizada de Tailored To. Debe validarse técnica y comercialmente por Agrobrokers.', 'cadv-tailored-to' ); ?></p>
					<div class="cadv-tt__result-actions">
						<button type="button" class="cadv-tt__button cadv-tt__button--gold" data-cadv-tt-open-contact><?php esc_html_e( 'Enviar al equipo técnico', 'cadv-tailored-to' ); ?></button>
						<a class="cadv-tt__button cadv-tt__button--whatsapp" href="#" target="_blank" rel="noopener noreferrer" data-cadv-tt-whatsapp><?php esc_html_e( 'Consultar por WhatsApp', 'cadv-tailored-to' ); ?></a>
					</div>
					<div class="cadv-tt__nav">
						<button type="button" class="cadv-tt__button cadv-tt__button--outline" data-cadv-tt-back><span aria-hidden="true">←</span><?php esc_html_e( 'Atrás', 'cadv-tailored-to' ); ?></button>
						<button type="reset" class="cadv-tt__button cadv-tt__button--outline" data-cadv-tt-restart><?php esc_html_e( 'Empezar de nuevo', 'cadv-tailored-to' ); ?></button>
					</div>
				</section>
			</form>

			<dialog class="cadv-tt__dialog" data-cadv-tt-dialog aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-dialog-title">
				<button type="button" class="cadv-tt__dialog-close" data-cadv-tt-close-contact aria-label="<?php esc_attr_e( 'Cerrar', 'cadv-tailored-to' ); ?>">×</button>
				<span class="cadv-tt__eyebrow"><?php esc_html_e( 'Revisión Tailored To', 'cadv-tailored-to' ); ?></span>
				<h3 id="<?php echo esc_attr( $instance_id ); ?>-dialog-title"><?php esc_html_e( 'Enviar la simulación al equipo técnico', 'cadv-tailored-to' ); ?></h3>
				<p><?php esc_html_e( 'Guardaremos el expediente y un asesor podrá solicitar los análisis necesarios.', 'cadv-tailored-to' ); ?></p>
				<form data-cadv-tt-contact-form novalidate>
					<input type="text" name="website" class="cadv-tt__honeypot" tabindex="-1" autocomplete="off" aria-hidden="true" />
					<input type="hidden" name="source_url" value="<?php echo esc_url( $this->current_url() ); ?>" />
					<div class="cadv-tt__field-grid">
						<label class="cadv-tt__field"><span><?php esc_html_e( 'Nombre completo *', 'cadv-tailored-to' ); ?></span><input type="text" name="full_name" maxlength="120" autocomplete="name" required /></label>
						<label class="cadv-tt__field"><span><?php esc_html_e( 'Correo *', 'cadv-tailored-to' ); ?></span><input type="email" name="email" maxlength="254" autocomplete="email" required /></label>
						<label class="cadv-tt__field"><span><?php esc_html_e( 'Teléfono *', 'cadv-tailored-to' ); ?></span><input type="tel" name="phone" maxlength="40" autocomplete="tel" required /></label>
						<label class="cadv-tt__field"><span><?php esc_html_e( 'Empresa', 'cadv-tailored-to' ); ?></span><input type="text" name="company" maxlength="160" autocomplete="organization" /></label>
						<label class="cadv-tt__field cadv-tt__field--full"><span><?php esc_html_e( 'Cargo', 'cadv-tailored-to' ); ?></span><input type="text" name="position" maxlength="120" autocomplete="organization-title" /></label>
					</div>
					<label class="cadv-tt__privacy">
						<input type="checkbox" name="privacy_acceptance" value="1" required />
						<span><?php esc_html_e( 'Acepto la Política de Privacidad y el tratamiento de datos.', 'cadv-tailored-to' ); ?>
						<?php if ( $privacy_url ) : ?><a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Consultar política', 'cadv-tailored-to' ); ?></a><?php endif; ?></span>
					</label>
					<div class="cadv-tt__message" data-cadv-tt-contact-message role="status" aria-live="polite"></div>
					<button type="submit" class="cadv-tt__button cadv-tt__button--primary cadv-tt__button--full"><?php esc_html_e( 'Guardar y enviar solicitud', 'cadv-tailored-to' ); ?></button>
				</form>
			</dialog>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Preview a formula without storing personal data.
	 */
	public function handle_preview() {
		$this->verify_nonce();
		$this->enforce_rate_limit( 'preview', self::MAX_PREVIEWS );
		$data       = $this->get_context_data();
		$validation = $this->validate_context( $data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'result' => CADV_TT_Formula_Engine::calculate( $data ) ) );
	}

	/**
	 * Persist a request and offer it to the CADV Woo CRM bridge.
	 */
	public function handle_submission() {
		$this->verify_nonce();
		$this->enforce_rate_limit( 'submit', self::MAX_SUBMISSIONS );

		if ( '' !== $this->post_string( 'website' ) ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo procesar la solicitud.', 'cadv-tailored-to' ) ), 400 );
		}

		$data       = $this->get_context_data();
		$contact    = $this->get_contact_data();
		$validation = $this->validate_context( $data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ), 400 );
		}

		$validation = $this->validate_contact( $contact );
		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ), 400 );
		}

		$result  = CADV_TT_Formula_Engine::calculate( $data );
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::REQUEST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $contact['email'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No fue posible guardar la solicitud.', 'cadv-tailored-to' ) ), 500 );
		}

		$request_code = sprintf( 'TT-%s-%06d', current_time( 'Ymd' ), $post_id );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $request_code ) );

		$meta = array_merge(
			$data,
			$contact,
			array(
				'request_code' => $request_code,
				'formula'      => $result['formula'],
				'engine_version' => $result['engine_version'],
				'technical_status' => 'simulation',
				'crm_status'   => 'pending',
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, '_cadv_tt_' . sanitize_key( $key ), $value );
		}
		update_post_meta( $post_id, '_cadv_tt_result', $result );

		$payload = $this->build_crm_payload( $post_id, $request_code, $data, $contact, $result );
		$default = new WP_Error( 'cadv_wf_bridge_unavailable', __( 'El puente con CADV Woo no está instalado.', 'cadv-tailored-to' ) );
		$crm     = apply_filters( self::CRM_FILTER, $default, $payload );
		$synced  = ! is_wp_error( $crm );

		if ( $synced ) {
			$lead_id = is_array( $crm ) && isset( $crm['lead_id'] ) ? absint( $crm['lead_id'] ) : absint( $crm );
			update_post_meta( $post_id, '_cadv_tt_crm_status', 'synced' );
			update_post_meta( $post_id, '_cadv_tt_crm_lead_id', $lead_id );
		} else {
			update_post_meta( $post_id, '_cadv_tt_crm_error', $crm->get_error_code() );
		}

		wp_send_json_success(
			array(
				'message'     => $synced
					? __( 'Solicitud guardada y enviada al CRM.', 'cadv-tailored-to' )
					: __( 'Solicitud guardada. La sincronización con el CRM queda pendiente.', 'cadv-tailored-to' ),
				'requestCode' => $request_code,
				'crmSynced'   => $synced,
				'result'      => $result,
			)
		);
	}

	/**
	 * Build the versioned payload expected by CADV Woo.
	 *
	 * @param int    $post_id      Request ID.
	 * @param string $request_code Public code.
	 * @param array  $data         Context.
	 * @param array  $contact      Contact.
	 * @param array  $result       Simulation.
	 * @return array
	 */
	private function build_crm_payload( $post_id, $request_code, array $data, array $contact, array $result ) {
		$crops = CADV_TT_Formula_Engine::get_crop_profiles();
		return array(
			'schema_version'      => 1,
			'source'              => 'cadv-tailored-to-calculator',
			'external_request_id' => $request_code,
			'cta_type'            => 'tailored_to',
			'full_name'           => $contact['full_name'],
			'email'               => $contact['email'],
			'phone'               => $contact['phone'],
			'company'             => $contact['company'],
			'position'            => $contact['position'],
			'product_interest'    => 'Tailored To',
			'crop_type'           => isset( $crops[ $data['crop'] ] ) ? $crops[ $data['crop'] ]['label'] : $data['crop'],
			'source_url'          => $contact['source_url'],
			'privacy_accepted_at' => current_time( 'mysql' ),
			'technical_status'    => 'simulation',
			'formula_summary'     => $result['formula'],
			'area_ha'             => $data['area'],
			'location'            => $data['location'],
			'yield_goal_t_ha'     => $data['yield_goal'],
			'variety'             => $data['variety'],
			'stage'               => $data['stage'],
			'primary_goal'        => $data['primary_goal'],
			'analysis_summary'    => sprintf(
				'Suelo: %s | Foliar: %s | Agua: %s',
				$data['soil_analysis_status'],
				$data['foliar_analysis_status'],
				$data['water_analysis_status']
			),
			'irrigation_system'   => $data['irrigation_system'],
			'readiness_label'     => $result['readiness_label'],
			'request_admin_url'   => admin_url( 'post.php?post=' . absint( $post_id ) . '&action=edit' ),
		);
	}

	/**
	 * Read calculator context.
	 *
	 * @return array
	 */
	private function get_context_data() {
		$raw_risks = isset( $_POST['management_risks'] ) && is_array( $_POST['management_risks'] ) ? wp_unslash( $_POST['management_risks'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$risks     = array_values(
			array_intersect(
				array_map( 'sanitize_key', array_filter( $raw_risks, 'is_scalar' ) ),
				array( 'volatilization', 'leaching', 'runoff', 'salinity', 'acidity', 'drainage', 'erosion', 'unknown' )
			)
		);

		return array(
			'crop'                   => sanitize_key( $this->post_string( 'crop' ) ),
			'variety'                => sanitize_text_field( $this->post_string( 'variety' ) ),
			'crop_age'               => sanitize_text_field( $this->post_string( 'crop_age' ) ),
			'stage'                  => sanitize_key( $this->post_string( 'stage' ) ),
			'production_system'      => sanitize_key( $this->post_string( 'production_system' ) ),
			'location'               => sanitize_text_field( $this->post_string( 'location' ) ),
			'area'                   => (float) $this->post_string( 'area' ),
			'yield_goal'             => (float) $this->post_string( 'yield_goal' ),
			'current_yield'          => (float) $this->post_string( 'current_yield' ),
			'primary_goal'           => sanitize_key( $this->post_string( 'primary_goal' ) ),
			'observed_problem'       => sanitize_textarea_field( $this->post_string( 'observed_problem' ) ),
			'soil_analysis_status'   => sanitize_key( $this->post_string( 'soil_analysis_status' ) ),
			'soil_analysis_date'     => sanitize_text_field( $this->post_string( 'soil_analysis_date' ) ),
			'soil_laboratory'        => sanitize_text_field( $this->post_string( 'soil_laboratory' ) ),
			'soil_sampling_depth'    => sanitize_text_field( $this->post_string( 'soil_sampling_depth' ) ),
			'soil_texture'           => sanitize_text_field( $this->post_string( 'soil_texture' ) ),
			'soil_ph'                => (float) $this->post_string( 'soil_ph' ),
			'soil_organic_matter'    => (float) $this->post_string( 'soil_organic_matter' ),
			'soil_cic'               => (float) $this->post_string( 'soil_cic' ),
			'soil_al_saturation'     => (float) $this->post_string( 'soil_al_saturation' ),
			'foliar_analysis_status' => sanitize_key( $this->post_string( 'foliar_analysis_status' ) ),
			'foliar_analysis_date'   => sanitize_text_field( $this->post_string( 'foliar_analysis_date' ) ),
			'foliar_laboratory'      => sanitize_text_field( $this->post_string( 'foliar_laboratory' ) ),
			'foliar_tissue'          => sanitize_text_field( $this->post_string( 'foliar_tissue' ) ),
			'foliar_sampling_stage'  => sanitize_text_field( $this->post_string( 'foliar_sampling_stage' ) ),
			'foliar_notes'           => sanitize_textarea_field( $this->post_string( 'foliar_notes' ) ),
			'n_level'                => sanitize_key( $this->post_string( 'n_level' ) ),
			'p_level'                => sanitize_key( $this->post_string( 'p_level' ) ),
			'k_level'                => sanitize_key( $this->post_string( 'k_level' ) ),
			'boron_status'           => sanitize_key( $this->post_string( 'boron_status' ) ),
			'zinc_status'            => sanitize_key( $this->post_string( 'zinc_status' ) ),
			'copper_status'          => sanitize_key( $this->post_string( 'copper_status' ) ),
			'exclude_boron'          => 'high' === sanitize_key( $this->post_string( 'boron_status' ) ),
			'exclude_copper'         => 'high' === sanitize_key( $this->post_string( 'copper_status' ) ),
			'application'            => sanitize_key( $this->post_string( 'application' ) ),
			'irrigation_system'      => sanitize_key( $this->post_string( 'irrigation_system' ) ),
			'water_analysis_status'  => sanitize_key( $this->post_string( 'water_analysis_status' ) ?: 'none' ),
			'water_analysis_date'    => sanitize_text_field( $this->post_string( 'water_analysis_date' ) ),
			'water_ph'               => (float) $this->post_string( 'water_ph' ),
			'water_ec'               => (float) $this->post_string( 'water_ec' ),
			'water_bicarbonates'     => sanitize_text_field( $this->post_string( 'water_bicarbonates' ) ),
			'irrigation_frequency'   => sanitize_text_field( $this->post_string( 'irrigation_frequency' ) ),
			'irrigation_flow'        => sanitize_text_field( $this->post_string( 'irrigation_flow' ) ),
			'irrigation_event'       => sanitize_text_field( $this->post_string( 'irrigation_event' ) ),
			'fertigation_notes'      => sanitize_textarea_field( $this->post_string( 'fertigation_notes' ) ),
			'fertilizer_frequency'   => sanitize_key( $this->post_string( 'fertilizer_frequency' ) ),
			'application_method'     => sanitize_key( $this->post_string( 'application_method' ) ),
			'current_fertilizer'     => sanitize_text_field( $this->post_string( 'current_fertilizer' ) ),
			'last_application'       => sanitize_text_field( $this->post_string( 'last_application' ) ),
			'management_risks'       => $risks,
			'operational_constraints' => sanitize_textarea_field( $this->post_string( 'operational_constraints' ) ),
		);
	}

	/**
	 * Read contact data.
	 *
	 * @return array
	 */
	private function get_contact_data() {
		return array(
			'full_name'          => sanitize_text_field( $this->post_string( 'full_name' ) ),
			'email'              => sanitize_email( $this->post_string( 'email' ) ),
			'phone'              => sanitize_text_field( $this->post_string( 'phone' ) ),
			'company'            => sanitize_text_field( $this->post_string( 'company' ) ),
			'position'           => sanitize_text_field( $this->post_string( 'position' ) ),
			'source_url'         => esc_url_raw( $this->post_string( 'source_url' ) ?: $this->current_url() ),
			'privacy_acceptance' => ! empty( $_POST['privacy_acceptance'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
	}

	/**
	 * Validate the simulation context.
	 *
	 * @param array $data Context.
	 * @return true|WP_Error
	 */
	private function validate_context( array $data ) {
		$crops        = CADV_TT_Formula_Engine::get_crop_profiles();
		$stages       = CADV_TT_Formula_Engine::get_stage_profiles();
		$applications = array( 'soil', 'fertigation', 'mixed' );
		$levels       = array( 'low', 'medium', 'high', 'unknown' );
		$statuses     = array( 'current', 'old', 'none' );
		$micro_status = array( 'unknown', 'low', 'adequate', 'high' );
		$systems      = array( 'open_field', 'protected', 'perennial', 'other' );
		$goals        = array( 'adapt_formula', 'correct_deficiency', 'avoid_excess', 'improve_efficiency', 'increase_yield', 'other' );
		$irrigation   = array( 'rainfed', 'drip', 'micro', 'sprinkler', 'pivot', 'surface', 'other' );
		$frequencies  = array( 'once', 'two', 'three_plus', 'continuous', 'unknown' );
		$methods      = array( 'broadcast', 'banded', 'localized', 'fertigation', 'mechanized', 'other' );

		if (
			! isset( $crops[ $data['crop'] ] )
			|| ! isset( $stages[ $data['stage'] ] )
			|| ! in_array( $data['production_system'], $systems, true )
			|| ! in_array( $data['primary_goal'], $goals, true )
			|| ! in_array( $data['application'], $applications, true )
			|| ! in_array( $data['irrigation_system'], $irrigation, true )
			|| ! in_array( $data['fertilizer_frequency'], $frequencies, true )
			|| ! in_array( $data['application_method'], $methods, true )
		) {
			return new WP_Error( 'cadv_tt_invalid_context', __( 'Revisa el cultivo, objetivo, aplicación y manejo seleccionados.', 'cadv-tailored-to' ) );
		}
		if ( '' === $data['location'] || strlen( $data['location'] ) > 160 ) {
			return new WP_Error( 'cadv_tt_invalid_location', __( 'Ingresa una ubicación válida.', 'cadv-tailored-to' ) );
		}
		if (
			$data['area'] <= 0
			|| $data['area'] > 100000
			|| $data['yield_goal'] <= 0
			|| $data['yield_goal'] > 100000
			|| $data['current_yield'] < 0
			|| $data['current_yield'] > 100000
		) {
			return new WP_Error( 'cadv_tt_invalid_numbers', __( 'Revisa el área y la meta de rendimiento.', 'cadv-tailored-to' ) );
		}
		if (
			! in_array( $data['soil_analysis_status'], $statuses, true )
			|| ! in_array( $data['foliar_analysis_status'], $statuses, true )
			|| ! in_array( $data['water_analysis_status'], $statuses, true )
		) {
			return new WP_Error( 'cadv_tt_invalid_analysis_status', __( 'Indica la disponibilidad de los análisis técnicos.', 'cadv-tailored-to' ) );
		}
		foreach ( array( 'n_level', 'p_level', 'k_level' ) as $field ) {
			if ( ! in_array( $data[ $field ], $levels, true ) ) {
				return new WP_Error( 'cadv_tt_invalid_levels', __( 'Selecciona una lectura para N, P y K.', 'cadv-tailored-to' ) );
			}
		}
		foreach ( array( 'boron_status', 'zinc_status', 'copper_status' ) as $field ) {
			if ( ! in_array( $data[ $field ], $micro_status, true ) ) {
				return new WP_Error( 'cadv_tt_invalid_micro_status', __( 'Revisa el estado declarado de B, Zn y Cu.', 'cadv-tailored-to' ) );
			}
		}
		if (
			$data['soil_ph'] < 0
			|| $data['soil_ph'] > 14
			|| $data['water_ph'] < 0
			|| $data['water_ph'] > 14
			|| $data['soil_organic_matter'] < 0
			|| $data['soil_organic_matter'] > 100
			|| $data['soil_al_saturation'] < 0
			|| $data['soil_al_saturation'] > 100
			|| $data['soil_cic'] < 0
			|| $data['soil_cic'] > 1000
			|| $data['water_ec'] < 0
			|| $data['water_ec'] > 100
		) {
			return new WP_Error( 'cadv_tt_invalid_analysis_values', __( 'Uno de los valores técnicos está fuera del rango permitido.', 'cadv-tailored-to' ) );
		}
		if (
			strlen( $data['observed_problem'] ) > 700
			|| strlen( $data['foliar_notes'] ) > 500
			|| strlen( $data['fertigation_notes'] ) > 500
			|| strlen( $data['operational_constraints'] ) > 700
		) {
			return new WP_Error( 'cadv_tt_fields_too_long', __( 'Una de las descripciones supera la longitud permitida.', 'cadv-tailored-to' ) );
		}
		return true;
	}

	/**
	 * Validate contact and consent.
	 *
	 * @param array $contact Contact.
	 * @return true|WP_Error
	 */
	private function validate_contact( array $contact ) {
		if ( '' === $contact['full_name'] || '' === $contact['phone'] || ! is_email( $contact['email'] ) ) {
			return new WP_Error( 'cadv_tt_invalid_contact', __( 'Completa nombre, correo válido y teléfono.', 'cadv-tailored-to' ) );
		}
		if ( empty( $contact['privacy_acceptance'] ) ) {
			return new WP_Error( 'cadv_tt_privacy_required', __( 'Debes aceptar la Política de Privacidad.', 'cadv-tailored-to' ) );
		}
		return true;
	}

	/**
	 * Verify public nonce.
	 */
	private function verify_nonce() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La sesión del formulario venció. Recarga la página.', 'cadv-tailored-to' ) ), 403 );
		}
	}

	/**
	 * Simple IP rate limit.
	 *
	 * @param string $scope Scope.
	 * @param int    $limit Attempts/hour.
	 */
	private function enforce_rate_limit( $scope, $limit ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'cadv_tt_' . sanitize_key( $scope ) . '_' . substr( md5( $ip ), 0, 20 );
		$hit = (int) get_transient( $key );
		if ( $hit >= $limit ) {
			wp_send_json_error( array( 'message' => __( 'Alcanzaste el límite temporal de solicitudes. Inténtalo más tarde.', 'cadv-tailored-to' ) ), 429 );
		}
		set_transient( $key, $hit + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Get one POST string.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function post_string( $key ) {
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? trim( (string) wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Current URL.
	 *
	 * @return string
	 */
	private function current_url() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		return home_url( is_string( $path ) && '' !== $path ? $path : '/' );
	}

	/**
	 * Admin columns.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public function admin_columns( array $columns ) {
		return array(
			'cb'      => $columns['cb'],
			'title'   => __( 'Código', 'cadv-tailored-to' ),
			'contact' => __( 'Contacto', 'cadv-tailored-to' ),
			'crop'    => __( 'Caso', 'cadv-tailored-to' ),
			'formula' => __( 'Simulación', 'cadv-tailored-to' ),
			'crm'     => __( 'CRM', 'cadv-tailored-to' ),
			'date'    => $columns['date'],
		);
	}

	/**
	 * Render admin column.
	 *
	 * @param string $column Column.
	 * @param int    $post_id Request ID.
	 */
	public function render_admin_column( $column, $post_id ) {
		if ( 'contact' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_cadv_tt_full_name', true ) );
			echo '<br /><a href="mailto:' . esc_attr( get_post_meta( $post_id, '_cadv_tt_email', true ) ) . '">' . esc_html( get_post_meta( $post_id, '_cadv_tt_email', true ) ) . '</a>';
		} elseif ( 'crop' === $column ) {
			$crops = CADV_TT_Formula_Engine::get_crop_profiles();
			$crop  = get_post_meta( $post_id, '_cadv_tt_crop', true );
			echo esc_html( isset( $crops[ $crop ] ) ? $crops[ $crop ]['label'] : $crop );
			echo '<br /><span>' . esc_html( get_post_meta( $post_id, '_cadv_tt_location', true ) ) . '</span>';
		} elseif ( 'formula' === $column ) {
			echo '<strong>' . esc_html( get_post_meta( $post_id, '_cadv_tt_formula', true ) ) . '</strong><br /><span>' . esc_html__( 'No aprobada', 'cadv-tailored-to' ) . '</span>';
		} elseif ( 'crm' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_cadv_tt_crm_status', true ) ?: 'pending' );
		}
	}

	/**
	 * Register the read-only detail box.
	 */
	public function add_request_meta_box() {
		add_meta_box(
			'cadv-tt-request-details',
			__( 'Detalle de la solicitud', 'cadv-tailored-to' ),
			array( $this, 'render_request_meta_box' ),
			self::REQUEST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render request details.
	 *
	 * @param WP_Post $post Request.
	 */
	public function render_request_meta_box( $post ) {
		$fields = array(
			'full_name'        => __( 'Nombre', 'cadv-tailored-to' ),
			'email'            => __( 'Correo', 'cadv-tailored-to' ),
			'phone'            => __( 'Teléfono', 'cadv-tailored-to' ),
			'company'          => __( 'Empresa', 'cadv-tailored-to' ),
			'position'         => __( 'Cargo', 'cadv-tailored-to' ),
			'crop'             => __( 'Cultivo', 'cadv-tailored-to' ),
			'variety'          => __( 'Variedad', 'cadv-tailored-to' ),
			'crop_age'         => __( 'Edad o fecha de siembra', 'cadv-tailored-to' ),
			'location'         => __( 'Ubicación', 'cadv-tailored-to' ),
			'area'             => __( 'Área (ha)', 'cadv-tailored-to' ),
			'stage'            => __( 'Etapa', 'cadv-tailored-to' ),
			'production_system' => __( 'Sistema de producción', 'cadv-tailored-to' ),
			'yield_goal'       => __( 'Meta (t/ha)', 'cadv-tailored-to' ),
			'current_yield'    => __( 'Rendimiento actual (t/ha)', 'cadv-tailored-to' ),
			'primary_goal'     => __( 'Objetivo', 'cadv-tailored-to' ),
			'observed_problem' => __( 'Problema observado', 'cadv-tailored-to' ),
			'soil_analysis_status' => __( 'Análisis de suelo', 'cadv-tailored-to' ),
			'foliar_analysis_status' => __( 'Análisis foliar', 'cadv-tailored-to' ),
			'boron_status'     => __( 'Estado de boro', 'cadv-tailored-to' ),
			'zinc_status'      => __( 'Estado de zinc', 'cadv-tailored-to' ),
			'copper_status'    => __( 'Estado de cobre', 'cadv-tailored-to' ),
			'application'      => __( 'Aplicación', 'cadv-tailored-to' ),
			'irrigation_system' => __( 'Sistema de riego', 'cadv-tailored-to' ),
			'water_analysis_status' => __( 'Análisis de agua', 'cadv-tailored-to' ),
			'fertilizer_frequency' => __( 'Fraccionamiento', 'cadv-tailored-to' ),
			'application_method' => __( 'Método de aplicación', 'cadv-tailored-to' ),
			'current_fertilizer' => __( 'Fertilización actual', 'cadv-tailored-to' ),
			'operational_constraints' => __( 'Restricciones', 'cadv-tailored-to' ),
			'formula'          => __( 'Fórmula simulada', 'cadv-tailored-to' ),
			'technical_status' => __( 'Estado técnico', 'cadv-tailored-to' ),
			'crm_status'       => __( 'Estado CRM', 'cadv-tailored-to' ),
		);
		$value_labels = array(
			'open_field'         => __( 'Campo abierto', 'cadv-tailored-to' ),
			'protected'          => __( 'Cultivo protegido', 'cadv-tailored-to' ),
			'perennial'          => __( 'Sistema perenne', 'cadv-tailored-to' ),
			'adapt_formula'      => __( 'Adaptar la relación nutricional', 'cadv-tailored-to' ),
			'correct_deficiency' => __( 'Atender una deficiencia', 'cadv-tailored-to' ),
			'avoid_excess'       => __( 'Evitar un nutriente en exceso', 'cadv-tailored-to' ),
			'improve_efficiency' => __( 'Mejorar eficiencia', 'cadv-tailored-to' ),
			'increase_yield'     => __( 'Acompañar meta productiva', 'cadv-tailored-to' ),
			'current'            => __( 'Vigente', 'cadv-tailored-to' ),
			'old'                => __( 'Anterior', 'cadv-tailored-to' ),
			'none'               => __( 'No disponible', 'cadv-tailored-to' ),
			'unknown'            => __( 'Sin dato', 'cadv-tailored-to' ),
			'low'                => __( 'Reportado bajo', 'cadv-tailored-to' ),
			'adequate'           => __( 'Adecuado', 'cadv-tailored-to' ),
			'high'               => __( 'Reportado alto', 'cadv-tailored-to' ),
			'soil'               => __( 'Aplicación al suelo', 'cadv-tailored-to' ),
			'fertigation'        => __( 'Fertirriego', 'cadv-tailored-to' ),
			'mixed'              => __( 'Programa mixto', 'cadv-tailored-to' ),
			'rainfed'            => __( 'Sin riego / secano', 'cadv-tailored-to' ),
			'drip'               => __( 'Goteo', 'cadv-tailored-to' ),
			'micro'              => __( 'Microaspersión', 'cadv-tailored-to' ),
			'sprinkler'          => __( 'Aspersión', 'cadv-tailored-to' ),
			'pivot'              => __( 'Pivote', 'cadv-tailored-to' ),
			'surface'            => __( 'Superficie o gravedad', 'cadv-tailored-to' ),
			'once'               => __( 'Una aplicación', 'cadv-tailored-to' ),
			'two'                => __( 'Dos aplicaciones', 'cadv-tailored-to' ),
			'three_plus'         => __( 'Tres o más aplicaciones', 'cadv-tailored-to' ),
			'continuous'         => __( 'Continuo por fertirriego', 'cadv-tailored-to' ),
			'broadcast'          => __( 'Al voleo', 'cadv-tailored-to' ),
			'banded'             => __( 'En banda', 'cadv-tailored-to' ),
			'localized'          => __( 'Localizada por planta', 'cadv-tailored-to' ),
			'mechanized'         => __( 'Aplicación mecanizada', 'cadv-tailored-to' ),
			'simulation'         => __( 'Simulación', 'cadv-tailored-to' ),
			'pending'            => __( 'Pendiente', 'cadv-tailored-to' ),
			'synced'             => __( 'Sincronizado', 'cadv-tailored-to' ),
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, '_cadv_tt_' . $key, true );
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			} elseif ( isset( $value_labels[ $value ] ) ) {
				$value = $value_labels[ $value ];
			}
			echo '<tr><th style="width:220px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html__( 'Advertencia:', 'cadv-tailored-to' ) . '</strong> ' . esc_html__( 'la fórmula es una simulación y no debe cotizarse ni aplicarse sin validación agronómica y productiva.', 'cadv-tailored-to' ) . '</p>';
	}
}
