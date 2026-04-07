<?php
/**
 * SEO content analyzer.
 *
 * Provides:
 *  - Server-side scoring API (REST endpoint) for title/description length,
 *    keyword density and readability hints.
 *  - Meta-box partial that hosts the JS-driven live preview.
 *  - Enqueue of admin JS that wires the live UI.
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SEO_Analyzer {

	public const REST_NAMESPACE = 'pb-media-all-seo/v1';

	public const TITLE_MIN = 30;
	public const TITLE_MAX = 60;
	public const DESC_MIN  = 120;
	public const DESC_MAX  = 160;

	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
	}

	public function add_meta_box(): void {
		foreach ( Plugin::get_supported_post_types() as $pt ) {
			add_meta_box(
				'pb_seo_analyzer',
				esc_html__( 'PB MEDIA — SEO Score Analyzer', 'pb-media-all-seo' ),
				[ $this, 'render_meta_box' ],
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		$initial = $this->analyze_post( $post );
		?>
		<div id="pb-seo-analyzer" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
			<div class="pb-seo-score-wrap">
				<div class="pb-seo-score-circle" data-score="<?php echo esc_attr( (string) $initial['score'] ); ?>">
					<span class="pb-seo-score-num"><?php echo esc_html( (string) $initial['score'] ); ?></span><span>/100</span>
				</div>
				<div class="pb-seo-score-label" data-grade="<?php echo esc_attr( $initial['grade'] ); ?>">
					<?php echo esc_html( $initial['grade_label'] ); ?>
				</div>
			</div>
			<ul class="pb-seo-checks">
				<?php foreach ( $initial['checks'] as $check ) : ?>
					<li class="pb-seo-check pb-seo-check--<?php echo esc_attr( $check['status'] ); ?>"
						data-key="<?php echo esc_attr( $check['key'] ); ?>">
						<span class="pb-seo-check-icon"></span>
						<span class="pb-seo-check-text"><?php echo esc_html( $check['message'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description">
				<?php esc_html_e( 'Score odświeża się na żywo gdy edytujesz pola SEO i OpenGraph.', 'pb-media-all-seo' ); ?>
			</p>
		</div>
		<?php
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		wp_enqueue_script(
			'pb-seo-analyzer',
			PB_MEDIA_ALL_SEO_URL . 'admin/js/seo-analyzer.js',
			[ 'jquery' ],
			PB_MEDIA_ALL_SEO_VERSION,
			true
		);
		wp_localize_script(
			'pb-seo-analyzer',
			'PBSeoAnalyzer',
			[
				'restUrl'  => esc_url_raw( rest_url( self::REST_NAMESPACE . '/analyze' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'postId'   => (int) ( get_the_ID() ?: 0 ),
				'limits'   => [
					'titleMin' => self::TITLE_MIN,
					'titleMax' => self::TITLE_MAX,
					'descMin'  => self::DESC_MIN,
					'descMax'  => self::DESC_MAX,
				],
				'i18n'     => [
					'excellent' => esc_html__( 'Świetnie', 'pb-media-all-seo' ),
					'good'      => esc_html__( 'Dobrze', 'pb-media-all-seo' ),
					'ok'        => esc_html__( 'OK', 'pb-media-all-seo' ),
					'poor'      => esc_html__( 'Słabo', 'pb-media-all-seo' ),
				],
			]
		);
	}

	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/analyze',
			[
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => [ $this, 'rest_analyze' ],
				'args'                => [
					'post_id'     => [ 'type' => 'integer', 'required' => false ],
					'title'       => [ 'type' => 'string', 'required' => false ],
					'description' => [ 'type' => 'string', 'required' => false ],
					'keywords'    => [ 'type' => 'string', 'required' => false ],
					'content'     => [ 'type' => 'string', 'required' => false ],
				],
			]
		);
	}

	public function rest_analyze( \WP_REST_Request $request ): \WP_REST_Response {
		$payload = [
			'title'       => (string) $request->get_param( 'title' ),
			'description' => (string) $request->get_param( 'description' ),
			'keywords'    => (string) $request->get_param( 'keywords' ),
			'content'     => (string) $request->get_param( 'content' ),
		];
		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 && '' === $payload['content'] ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				$payload['content'] = (string) $post->post_content;
			}
		}
		return new \WP_REST_Response( $this->analyze( $payload ), 200 );
	}

	/**
	 * Analyze a saved post (initial SSR render).
	 *
	 * @return array<string,mixed>
	 */
	public function analyze_post( \WP_Post $post ): array {
		$title       = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageTitleSEO', true );
		$description = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageDescriptionSEO', true );
		$keywords    = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageKeywordsSEO', true );

		if ( '' === $title ) {
			$title = (string) $post->post_title;
		}
		if ( '' === $description ) {
			$description = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 30, '' );
		}

		return $this->analyze(
			[
				'title'       => $title,
				'description' => $description,
				'keywords'    => $keywords,
				'content'     => (string) $post->post_content,
			]
		);
	}

	/**
	 * Run the analysis on raw input data.
	 *
	 * @param array{title:string,description:string,keywords:string,content:string} $data
	 * @return array<string,mixed>
	 */
	public function analyze( array $data ): array {
		$title       = trim( $data['title'] );
		$description = trim( $data['description'] );
		$keywords    = trim( $data['keywords'] );
		$content     = wp_strip_all_tags( $data['content'] );

		$checks = [];
		$score  = 0;
		$max    = 0;

		// --- Title length -----------------------------------------------
		$max += 20;
		$tlen = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
		if ( $tlen >= self::TITLE_MIN && $tlen <= self::TITLE_MAX ) {
			$score   += 20;
			$checks[] = [
				'key'     => 'title',
				'status'  => 'good',
				'message' => sprintf(
					/* translators: %d: length */
					__( 'Title SEO ma %d znaków — idealnie.', 'pb-media-all-seo' ),
					$tlen
				),
			];
		} elseif ( 0 === $tlen ) {
			$checks[] = [
				'key'     => 'title',
				'status'  => 'bad',
				'message' => __( 'Brak Title SEO. Dodaj tytuł 30–60 znaków.', 'pb-media-all-seo' ),
			];
		} else {
			$score   += 8;
			$checks[] = [
				'key'     => 'title',
				'status'  => 'warn',
				'message' => sprintf(
					/* translators: 1: current length, 2: min, 3: max */
					__( 'Title SEO ma %1$d znaków — zalecane %2$d–%3$d.', 'pb-media-all-seo' ),
					$tlen,
					self::TITLE_MIN,
					self::TITLE_MAX
				),
			];
		}

		// --- Description length -----------------------------------------
		$max += 20;
		$dlen = function_exists( 'mb_strlen' ) ? mb_strlen( $description ) : strlen( $description );
		if ( $dlen >= self::DESC_MIN && $dlen <= self::DESC_MAX ) {
			$score   += 20;
			$checks[] = [
				'key'     => 'description',
				'status'  => 'good',
				'message' => sprintf(
					/* translators: %d: length */
					__( 'Meta Description ma %d znaków — idealnie.', 'pb-media-all-seo' ),
					$dlen
				),
			];
		} elseif ( 0 === $dlen ) {
			$checks[] = [
				'key'     => 'description',
				'status'  => 'bad',
				'message' => __( 'Brak Meta Description. Dodaj opis 120–160 znaków.', 'pb-media-all-seo' ),
			];
		} else {
			$score   += 8;
			$checks[] = [
				'key'     => 'description',
				'status'  => 'warn',
				'message' => sprintf(
					/* translators: 1: current length, 2: min, 3: max */
					__( 'Meta Description ma %1$d znaków — zalecane %2$d–%3$d.', 'pb-media-all-seo' ),
					$dlen,
					self::DESC_MIN,
					self::DESC_MAX
				),
			];
		}

		// --- Keywords presence ------------------------------------------
		$max += 10;
		if ( '' !== $keywords ) {
			$score   += 10;
			$checks[] = [
				'key'     => 'keywords',
				'status'  => 'good',
				'message' => __( 'Meta Keywords zdefiniowane.', 'pb-media-all-seo' ),
			];
		} else {
			$checks[] = [
				'key'     => 'keywords',
				'status'  => 'warn',
				'message' => __( 'Brak Meta Keywords (opcjonalnie, ale przydatne).', 'pb-media-all-seo' ),
			];
		}

		// --- Keyword density (first keyword) ----------------------------
		$max         += 20;
		$first_kw     = '';
		$density      = 0.0;
		$content_words = preg_split( '/\s+/u', mb_strtolower( $content ) ) ?: [];
		$total_words   = count( array_filter( $content_words, static fn( $w ) => '' !== $w ) );
		if ( '' !== $keywords && $total_words > 0 ) {
			$kw_list  = array_filter( array_map( 'trim', explode( ',', $keywords ) ) );
			$first_kw = (string) reset( $kw_list );
			if ( '' !== $first_kw ) {
				$kw_lower = mb_strtolower( $first_kw );
				$count    = substr_count( ' ' . implode( ' ', $content_words ) . ' ', ' ' . $kw_lower . ' ' );
				$density  = ( $count / max( 1, $total_words ) ) * 100;
				if ( $density >= 0.5 && $density <= 2.5 ) {
					$score   += 20;
					$checks[] = [
						'key'     => 'density',
						'status'  => 'good',
						'message' => sprintf(
							/* translators: 1: keyword, 2: density percent */
							__( 'Gęstość frazy „%1$s": %2$.2f%% — w normie (0.5–2.5%%).', 'pb-media-all-seo' ),
							$first_kw,
							$density
						),
					];
				} else {
					$score   += 8;
					$checks[] = [
						'key'     => 'density',
						'status'  => 'warn',
						'message' => sprintf(
							/* translators: 1: keyword, 2: density */
							__( 'Gęstość frazy „%1$s": %2$.2f%% — poza zakresem 0.5–2.5%%.', 'pb-media-all-seo' ),
							$first_kw,
							$density
						),
					];
				}
			}
		} else {
			$checks[] = [
				'key'     => 'density',
				'status'  => 'warn',
				'message' => __( 'Nie można obliczyć gęstości — brak słów kluczowych lub treści.', 'pb-media-all-seo' ),
			];
		}

		// --- Content length ---------------------------------------------
		$max += 15;
		if ( $total_words >= 300 ) {
			$score   += 15;
			$checks[] = [
				'key'     => 'wordcount',
				'status'  => 'good',
				'message' => sprintf(
					/* translators: %d: word count */
					__( 'Treść ma %d słów (≥300).', 'pb-media-all-seo' ),
					$total_words
				),
			];
		} else {
			$score   += (int) ( $total_words / 300 * 15 );
			$checks[] = [
				'key'     => 'wordcount',
				'status'  => 'warn',
				'message' => sprintf(
					/* translators: %d: word count */
					__( 'Treść ma tylko %d słów (zalecane ≥300).', 'pb-media-all-seo' ),
					$total_words
				),
			];
		}

		// --- Title contains keyword ------------------------------------
		$max += 15;
		if ( '' !== $first_kw && '' !== $title ) {
			if ( false !== mb_stripos( $title, $first_kw ) ) {
				$score   += 15;
				$checks[] = [
					'key'     => 'kw_in_title',
					'status'  => 'good',
					'message' => __( 'Główna fraza występuje w Title SEO.', 'pb-media-all-seo' ),
				];
			} else {
				$checks[] = [
					'key'     => 'kw_in_title',
					'status'  => 'warn',
					'message' => __( 'Główna fraza nie występuje w Title SEO.', 'pb-media-all-seo' ),
				];
			}
		}

		// Normalize to 0–100.
		$normalized = (int) round( ( $score / max( 1, $max ) ) * 100 );

		[ $grade, $grade_label ] = $this->grade( $normalized );

		return [
			'score'       => $normalized,
			'grade'       => $grade,
			'grade_label' => $grade_label,
			'word_count'  => $total_words,
			'density'     => round( $density, 2 ),
			'checks'      => $checks,
		];
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private function grade( int $score ): array {
		if ( $score >= 85 ) {
			return [ 'excellent', __( 'Świetnie', 'pb-media-all-seo' ) ];
		}
		if ( $score >= 70 ) {
			return [ 'good', __( 'Dobrze', 'pb-media-all-seo' ) ];
		}
		if ( $score >= 50 ) {
			return [ 'ok', __( 'OK', 'pb-media-all-seo' ) ];
		}
		return [ 'poor', __( 'Słabo', 'pb-media-all-seo' ) ];
	}
}
