<?php
/**
 * Filterable blog post grid shortcode.
 *
 * @package CADVWooFunctionalities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CADV_Post_Grid {
	const AJAX_ACTION  = 'cadv_load_blog_posts';
	const NONCE_ACTION = 'cadv_blog_categorias';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Prevent duplicate late-style callbacks.
	 *
	 * @var bool
	 */
	private $late_styles_hooked = false;

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
	 * Register shortcode and AJAX endpoints.
	 */
	private function __construct() {
		add_shortcode( 'cadv_blog_categorias', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * Render the filterable post grid.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		$settings   = $this->sanitize_settings( shortcode_atts( $this->get_default_settings(), $atts, 'cadv_blog_categorias' ) );
		$categories = $this->get_categories( $settings['categories'] );

		if ( empty( $categories ) ) {
			return '';
		}

		$allowed_ids = wp_list_pluck( $categories, 'term_id' );
		$query       = $this->get_posts_query( $settings, 1, 0, $allowed_ids );

		if ( ! $query->have_posts() ) {
			return '';
		}

		$this->enqueue_assets();

		$instance_id = wp_unique_id( 'cadv-blog-' );
		$shown       = min( $settings['per_page'], (int) $query->found_posts );

		ob_start();
		?>
		<section
			id="<?php echo esc_attr( $instance_id ); ?>"
			class="cadv-blog"
			style="--cadv-blog-columns: <?php echo esc_attr( $settings['columns'] ); ?>;"
			data-cadv-blog
			data-page="1"
			data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>"
			data-category="0"
			data-per-page="<?php echo esc_attr( $settings['per_page'] ); ?>"
			data-excerpt-words="<?php echo esc_attr( $settings['excerpt_words'] ); ?>"
			data-order="<?php echo esc_attr( $settings['order'] ); ?>"
			data-orderby="<?php echo esc_attr( $settings['orderby'] ); ?>"
			data-categories="<?php echo esc_attr( implode( ',', $allowed_ids ) ); ?>"
			data-loading-label="<?php echo esc_attr__( 'Cargando artículos...', 'cadv-woo-functionalities' ); ?>"
			data-error-label="<?php echo esc_attr__( 'No fue posible cargar los artículos. Inténtalo de nuevo.', 'cadv-woo-functionalities' ); ?>"
		>
			<nav class="cadv-blog__filters" aria-label="<?php esc_attr_e( 'Filtrar artículos por categoría', 'cadv-woo-functionalities' ); ?>">
				<button class="cadv-blog__filter is-active" type="button" data-category="0" aria-pressed="true">
					<?php echo esc_html( $settings['all_label'] ); ?>
				</button>
				<?php foreach ( $categories as $category ) : ?>
					<button class="cadv-blog__filter" type="button" data-category="<?php echo esc_attr( $category->term_id ); ?>" aria-pressed="false">
						<?php echo esc_html( $category->name ); ?>
					</button>
				<?php endforeach; ?>
			</nav>

			<div class="cadv-blog__grid" data-cadv-blog-grid aria-live="polite">
				<?php echo $this->render_cards( $query, $allowed_ids, $settings['excerpt_words'], 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="cadv-blog__footer">
				<button class="cadv-blog__more" type="button" data-cadv-blog-more<?php echo $query->max_num_pages > 1 ? '' : ' hidden'; ?>>
					<span><?php echo esc_html( $settings['load_more_label'] ); ?></span>
					<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 3v10M3 8h10"/></svg>
				</button>
				<p class="cadv-blog__count" data-cadv-blog-count>
					<?php echo esc_html( $this->get_count_label( $shown, $query->found_posts ) ); ?>
				</p>
				<p class="cadv-blog__message" data-cadv-blog-message role="status" hidden></p>
			</div>
		</section>
		<?php

		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Return posts requested by a filter or "load more" action.
	 */
	public function handle_ajax() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'La sesión de carga expiró. Actualiza la página e inténtalo de nuevo.', 'cadv-woo-functionalities' ) ),
				403
			);
		}

		$settings = $this->sanitize_settings(
			array(
				'categories'       => '',
				'per_page'        => isset( $_POST['per_page'] ) ? wp_unslash( $_POST['per_page'] ) : 6,
				'columns'         => 3,
				'excerpt_words'   => isset( $_POST['excerpt_words'] ) ? wp_unslash( $_POST['excerpt_words'] ) : 18,
				'order'           => isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : 'DESC',
				'orderby'         => isset( $_POST['orderby'] ) ? wp_unslash( $_POST['orderby'] ) : 'date',
				'all_label'       => '',
				'load_more_label' => '',
			)
		);
		$page        = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$category_id = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		$allowed_ids = isset( $_POST['categories'] ) ? $this->sanitize_id_list( wp_unslash( $_POST['categories'] ) ) : array();

		if ( empty( $allowed_ids ) || ( $category_id && ! in_array( $category_id, $allowed_ids, true ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'La categoría solicitada no está disponible.', 'cadv-woo-functionalities' ) ),
				400
			);
		}

		$query = $this->get_posts_query( $settings, $page, $category_id, $allowed_ids );
		$shown = min( $page * $settings['per_page'], (int) $query->found_posts );
		$html  = $this->render_cards( $query, $allowed_ids, $settings['excerpt_words'], $category_id );

		wp_reset_postdata();

		wp_send_json_success(
			array(
				'html'      => $html,
				'page'      => $page,
				'maxPages'  => (int) $query->max_num_pages,
				'shown'     => $shown,
				'total'     => (int) $query->found_posts,
				'countText' => $this->get_count_label( $shown, $query->found_posts ),
			)
		);
	}

	/**
	 * Get supported shortcode defaults.
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'categories'       => '',
			'per_page'        => 6,
			'columns'         => 3,
			'excerpt_words'   => 18,
			'order'           => 'DESC',
			'orderby'         => 'date',
			'all_label'       => __( 'Todos', 'cadv-woo-functionalities' ),
			'load_more_label' => __( 'Cargar más artículos', 'cadv-woo-functionalities' ),
		);
	}

	/**
	 * Sanitize shortcode or AJAX settings.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	private function sanitize_settings( $settings ) {
		$allowed_orderby = array( 'date', 'title', 'menu_order', 'modified', 'rand' );
		$orderby         = sanitize_key( $settings['orderby'] );

		return array(
			'categories'       => sanitize_text_field( $settings['categories'] ),
			'per_page'        => min( 24, max( 1, absint( $settings['per_page'] ) ) ),
			'columns'         => min( 4, max( 1, absint( $settings['columns'] ) ) ),
			'excerpt_words'   => min( 60, max( 5, absint( $settings['excerpt_words'] ) ) ),
			'order'           => 'ASC' === strtoupper( sanitize_text_field( $settings['order'] ) ) ? 'ASC' : 'DESC',
			'orderby'         => in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'date',
			'all_label'       => sanitize_text_field( $settings['all_label'] ),
			'load_more_label' => sanitize_text_field( $settings['load_more_label'] ),
		);
	}

	/**
	 * Resolve visible category filters.
	 *
	 * Accepts a comma-separated list of slugs or IDs.
	 *
	 * @param string $requested Requested categories.
	 * @return WP_Term[]
	 */
	private function get_categories( $requested ) {
		$args = array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( '' !== trim( $requested ) ) {
			$tokens = array_filter( array_map( 'trim', explode( ',', $requested ) ) );
			$ids    = array_filter( array_map( 'absint', $tokens ) );

			if ( count( $ids ) === count( $tokens ) ) {
				$args['include'] = $ids;
				$args['orderby'] = 'include';
			} else {
				$requested_slugs = array_values( array_map( 'sanitize_title', $tokens ) );
				$args['slug']    = $requested_slugs;
				$args['orderby'] = 'none';
			}
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		if ( isset( $requested_slugs ) ) {
			usort(
				$terms,
				static function ( $first, $second ) use ( $requested_slugs ) {
					return array_search( $first->slug, $requested_slugs, true ) <=> array_search( $second->slug, $requested_slugs, true );
				}
			);
		}

		return $terms;
	}

	/**
	 * Build a paged post query.
	 *
	 * @param array $settings Settings.
	 * @param int   $page Current page.
	 * @param int   $category_id Active category, or zero for all.
	 * @param int[] $allowed_ids Categories configured for this grid.
	 * @return WP_Query
	 */
	private function get_posts_query( $settings, $page, $category_id, $allowed_ids ) {
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $settings['per_page'],
			'paged'               => $page,
			'orderby'             => $settings['orderby'],
			'order'               => $settings['order'],
			'ignore_sticky_posts' => true,
		);

		if ( $category_id ) {
			$query_args['cat'] = $category_id;
		} else {
			$query_args['category__in'] = $allowed_ids;
		}

		return new WP_Query( $query_args );
	}

	/**
	 * Render all cards in a query.
	 *
	 * @param WP_Query $query Query instance.
	 * @param int[]    $allowed_ids Allowed category IDs.
	 * @param int      $excerpt_words Excerpt length.
	 * @param int      $preferred_category_id Category selected by the visitor.
	 * @return string
	 */
	private function render_cards( $query, $allowed_ids, $excerpt_words, $preferred_category_id ) {
		if ( ! $query->have_posts() ) {
			return '<p class="cadv-blog__empty">' . esc_html__( 'No hay artículos en esta categoría.', 'cadv-woo-functionalities' ) . '</p>';
		}

		ob_start();

		while ( $query->have_posts() ) {
			$query->the_post();
			echo $this->render_card( get_the_ID(), $allowed_ids, $excerpt_words, $preferred_category_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return ob_get_clean();
	}

	/**
	 * Render a single post card.
	 *
	 * @param int   $post_id Post ID.
	 * @param int[] $allowed_ids Allowed category IDs.
	 * @param int   $excerpt_words Excerpt length.
	 * @param int   $preferred_category_id Category selected by the visitor.
	 * @return string
	 */
	private function render_card( $post_id, $allowed_ids, $excerpt_words, $preferred_category_id ) {
		$category  = $this->get_primary_category( $post_id, $allowed_ids, $preferred_category_id );
		$accent    = $this->get_category_color( $category );
		$title     = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		$excerpt   = get_the_excerpt( $post_id );
		$excerpt   = $excerpt ? $excerpt : wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $post_id ) ) );
		$reading   = $this->get_reading_minutes( $post_id );
		$image     = get_the_post_thumbnail(
			$post_id,
			'large',
			array(
				'class'   => 'cadv-blog-card__image',
				'loading' => 'lazy',
			)
		);

		ob_start();
		?>
		<article class="cadv-blog-card" style="--cadv-post-accent: <?php echo esc_attr( $accent ); ?>;">
			<a class="cadv-blog-card__media<?php echo $image ? '' : ' is-placeholder'; ?>" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
				<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WordPress. ?>
				<?php if ( $category ) : ?>
					<span class="cadv-blog-card__category"><?php echo esc_html( $category->name ); ?></span>
				<?php endif; ?>
				<?php if ( ! $image ) : ?>
					<span class="cadv-blog-card__placeholder-text" aria-hidden="true">
						[ <?php echo esc_html( $category ? sprintf( /* translators: %s: category name. */ __( 'foto %s', 'cadv-woo-functionalities' ), $category->name ) : __( 'foto del artículo', 'cadv-woo-functionalities' ) ); ?> ]
					</span>
				<?php endif; ?>
			</a>
			<div class="cadv-blog-card__body">
				<p class="cadv-blog-card__meta">
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post_id ) ); ?>"><?php echo esc_html( get_the_date( 'F Y', $post_id ) ); ?></time>
					<span aria-hidden="true">•</span>
					<?php
					printf(
						/* translators: %d: estimated reading time in minutes. */
						esc_html( _n( '%d min de lectura', '%d min de lectura', $reading, 'cadv-woo-functionalities' ) ),
						(int) $reading
					);
					?>
				</p>
				<h3 class="cadv-blog-card__title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3>
				<p class="cadv-blog-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, $excerpt_words, '…' ) ); ?></p>
				<a class="cadv-blog-card__link" href="<?php echo esc_url( $permalink ); ?>">
					<?php esc_html_e( 'Leer más', 'cadv-woo-functionalities' ); ?>
					<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 8h9M8.5 4.5 12 8l-3.5 3.5"/></svg>
				</a>
			</div>
		</article>
		<?php

		return ob_get_clean();
	}

	/**
	 * Pick the first category that belongs to the configured filter set.
	 *
	 * @param int   $post_id Post ID.
	 * @param int[] $allowed_ids Allowed category IDs.
	 * @param int   $preferred_category_id Category selected by the visitor.
	 * @return WP_Term|null
	 */
	private function get_primary_category( $post_id, $allowed_ids, $preferred_category_id = 0 ) {
		$categories = get_the_category( $post_id );

		if ( $preferred_category_id ) {
			foreach ( $categories as $category ) {
				if ( $preferred_category_id === (int) $category->term_id ) {
					return $category;
				}
			}
		}

		foreach ( $categories as $category ) {
			if ( in_array( (int) $category->term_id, $allowed_ids, true ) ) {
				return $category;
			}
		}

		return ! empty( $categories ) ? reset( $categories ) : null;
	}

	/**
	 * Return a stable color for each category.
	 *
	 * A valid `_cadv_post_grid_color` term meta value takes priority.
	 *
	 * @param WP_Term|null $category Category.
	 * @return string
	 */
	private function get_category_color( $category ) {
		$palette = array( '#23753a', '#d99b14', '#e45c18', '#765447', '#ac8616', '#456b31', '#8a5b32', '#49627d' );
		$presets = array(
			'palma-de-aceite' => '#23753a',
			'palma-aceite'    => '#23753a',
			'banano'          => '#d99b14',
			'arroz'           => '#b89a34',
			'maiz'            => '#e45c18',
			'suelo'           => '#765447',
			'suelos'          => '#765447',
		);

		if ( $category instanceof WP_Term ) {
			$custom_color = sanitize_hex_color( get_term_meta( $category->term_id, '_cadv_post_grid_color', true ) );

			if ( $custom_color ) {
				return $custom_color;
			}

			if ( isset( $presets[ $category->slug ] ) ) {
				return $presets[ $category->slug ];
			}

			return $palette[ abs( crc32( $category->slug ) ) % count( $palette ) ];
		}

		return $palette[0];
	}

	/**
	 * Estimate reading time at 200 words per minute.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function get_reading_minutes( $post_id ) {
		$content = wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $post_id ) ) );
		$words   = preg_split( '/\s+/u', trim( $content ), -1, PREG_SPLIT_NO_EMPTY );

		return max( 1, (int) ceil( count( $words ) / 200 ) );
	}

	/**
	 * Sanitize a comma-separated ID list.
	 *
	 * @param string $raw Raw IDs.
	 * @return int[]
	 */
	private function sanitize_id_list( $raw ) {
		return array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) ) ) );
	}

	/**
	 * Build the article count label.
	 *
	 * @param int $shown Visible count.
	 * @param int $total Total results.
	 * @return string
	 */
	private function get_count_label( $shown, $total ) {
		return sprintf(
			/* translators: 1: visible articles, 2: total articles. */
			__( 'Mostrando %1$d de %2$d artículos', 'cadv-woo-functionalities' ),
			(int) $shown,
			(int) $total
		);
	}

	/**
	 * Enqueue assets only when the shortcode is rendered.
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'cadv-post-grid-font',
			'https://fonts.googleapis.com/css2?family=Exo:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'cadv-post-grid',
			CADV_WOO_FUNCTIONALITIES_URL . 'assets/css/cadv-post-grid.css',
			array( 'cadv-post-grid-font' ),
			CADV_WOO_FUNCTIONALITIES_VERSION
		);

		wp_enqueue_script(
			'cadv-post-grid',
			CADV_WOO_FUNCTIONALITIES_URL . 'assets/js/cadv-post-grid.js',
			array(),
			CADV_WOO_FUNCTIONALITIES_VERSION,
			true
		);

		wp_localize_script(
			'cadv-post-grid',
			'CadvPostGrid',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);

		if ( did_action( 'wp_head' ) && ! $this->late_styles_hooked ) {
			add_action( 'wp_footer', array( $this, 'print_late_styles' ), 1 );
			$this->late_styles_hooked = true;
		}
	}

	/**
	 * Print styles when an Elementor shortcode runs after wp_head.
	 */
	public function print_late_styles() {
		if ( ! wp_style_is( 'cadv-post-grid', 'done' ) ) {
			wp_print_styles( array( 'cadv-post-grid-font', 'cadv-post-grid' ) );
		}
	}
}
