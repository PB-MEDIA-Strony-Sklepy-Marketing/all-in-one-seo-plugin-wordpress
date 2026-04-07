<?php
/**
 * Sitemap generator — physical file strategy.
 *
 * STRATEGY: Generate sitemapa.xml and sitemapa-image.xml as REAL files
 * in the WordPress document root. The web server (Apache/Nginx/LiteSpeed)
 * serves them directly with the correct Content-Type because of the .xml
 * extension — bypassing WordPress, PHP and all other plugins entirely.
 *
 * This is BULLETPROOF against:
 *  - Other plugins setting Content-Type: text/html early
 *  - LiteSpeed Cache / WP Rocket / Cloudflare cache
 *  - BOM / whitespace causing headers_sent() = true
 *  - Any plugin that hooks into parse_request before us
 *
 * Files are regenerated whenever:
 *  - A post is saved, deleted, or status-transitioned
 *  - An attachment is added, updated, or deleted
 *  - Plugin is activated
 *  - User clicks "Regenerate now" button on settings page
 *
 * Fallback: If physical file generation fails (e.g. unwritable doc root),
 * a rewrite rule + parse_request hook serves the XML dynamically.
 *
 * Files coexist independently with WordPress's built-in wp-sitemap.xml.
 *
 * Compliant with the Sitemap protocol 0.90 (sitemaps.org).
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sitemap {

	public const FILE_PAGES  = 'sitemapa.xml';
	public const FILE_IMAGES = 'sitemapa-image.xml';

	private const QUERY_VAR = 'pb_seo_sitemap';

	public const VALID_PRIORITIES = [
		'1.0', '0.9', '0.8', '0.7', '0.6', '0.5',
		'0.4', '0.3', '0.2', '0.1', '0.0',
	];

	public const VALID_CHANGEFREQ = [
		'always', 'hourly', 'daily', 'weekly',
		'monthly', 'yearly', 'never',
	];

	public function register_hooks(): void {
		add_action( 'init', [ self::class, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );

		// Dynamic fallback (only used when physical file is missing or unwritable).
		add_action( 'parse_request', [ $this, 'maybe_serve_dynamic_early' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_serve_dynamic' ], 0 );

		// Regenerate physical files on content changes.
		add_action( 'save_post', [ $this, 'regenerate_async' ], 20, 2 );
		add_action( 'delete_post', [ $this, 'regenerate_async' ] );
		add_action( 'transition_post_status', [ $this, 'on_status_change' ], 20, 3 );
		add_action( 'add_attachment', [ $this, 'regenerate_async' ] );
		add_action( 'attachment_updated', [ $this, 'regenerate_async' ] );
		add_action( 'delete_attachment', [ $this, 'regenerate_async' ] );

		// Manual regeneration trigger via admin-post.
		add_action( 'admin_post_pb_seo_regenerate_sitemap', [ $this, 'handle_manual_regenerate' ] );
	}

	public static function register_rewrite_rules(): void {
		add_rewrite_rule( '^sitemapa\.xml$', 'index.php?' . self::QUERY_VAR . '=pages', 'top' );
		add_rewrite_rule( '^sitemapa-image\.xml$', 'index.php?' . self::QUERY_VAR . '=images', 'top' );
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/* ---------------------------------------------------------------------
	 * PHYSICAL FILE GENERATION (primary strategy)
	 * ------------------------------------------------------------------ */

	/**
	 * Get the full filesystem path for a sitemap file.
	 */
	public static function get_file_path( string $filename ): string {
		return trailingslashit( ABSPATH ) . $filename;
	}

	/**
	 * Get the public URL for a sitemap file.
	 */
	public static function get_file_url( string $filename ): string {
		return trailingslashit( home_url() ) . $filename;
	}

	/**
	 * Check whether a sitemap file exists physically.
	 */
	public static function file_exists( string $filename ): bool {
		return is_file( self::get_file_path( $filename ) );
	}

	/**
	 * Async wrapper that schedules regeneration on the next request via
	 * a transient flag (avoids slowing down save_post handlers).
	 */
	public function regenerate_async(): void {
		set_transient( 'pb_seo_sitemap_dirty', 1, DAY_IN_SECONDS );
		// Also do an immediate sync regenerate so the file is fresh right away.
		// Suppress errors so save_post never breaks.
		try {
			$this->regenerate_now();
		} catch ( \Throwable $e ) {
			// Silent — file will be regenerated lazily next time.
		}
	}

	public function on_status_change( string $new_status, string $old_status, $post ): void {
		if ( $new_status !== $old_status ) {
			$this->regenerate_async();
		}
	}

	/**
	 * Regenerate both sitemap files NOW. Returns array of results per file.
	 *
	 * @return array{pages:bool,images:bool,errors:array<int,string>}
	 */
	public function regenerate_now(): array {
		$errors = [];

		$pages_xml = $this->build_pages_sitemap();
		$pages_ok  = $this->write_file( self::FILE_PAGES, $pages_xml );
		if ( ! $pages_ok ) {
			$errors[] = sprintf(
				/* translators: %s: file path */
				__( 'Nie udało się zapisać pliku %s — sprawdź uprawnienia katalogu document root.', 'pb-media-all-seo' ),
				self::get_file_path( self::FILE_PAGES )
			);
		}

		$images_xml = $this->build_images_sitemap();
		$images_ok  = $this->write_file( self::FILE_IMAGES, $images_xml );
		if ( ! $images_ok ) {
			$errors[] = sprintf(
				/* translators: %s: file path */
				__( 'Nie udało się zapisać pliku %s — sprawdź uprawnienia katalogu document root.', 'pb-media-all-seo' ),
				self::get_file_path( self::FILE_IMAGES )
			);
		}

		if ( $pages_ok && $images_ok ) {
			delete_transient( 'pb_seo_sitemap_dirty' );
		}

		return [
			'pages'  => $pages_ok,
			'images' => $images_ok,
			'errors' => $errors,
		];
	}

	/**
	 * Write XML content to a file in document root.
	 * Uses file_put_contents directly with LOCK_EX. Falls back gracefully.
	 */
	private function write_file( string $filename, string $content ): bool {
		$path = self::get_file_path( $filename );

		// Ensure target directory is writable.
		$dir = dirname( $path );
		if ( ! is_writable( $dir ) ) {
			return false;
		}

		// Write with exclusive lock to avoid race conditions.
		$result = @file_put_contents( $path, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $result ) {
			return false;
		}

		// Set readable permissions for the web server.
		@chmod( $path, 0644 );

		return true;
	}

	/**
	 * Manual regeneration handler (admin-post).
	 */
	public function handle_manual_regenerate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'pb-media-all-seo' ), 403 );
		}
		check_admin_referer( 'pb_seo_regenerate_sitemap' );

		$result = $this->regenerate_now();

		$msg_type = ( $result['pages'] && $result['images'] ) ? 'success' : 'error';
		if ( 'success' === $msg_type ) {
			$msg = __( 'Sitemapy zostały wygenerowane jako fizyczne pliki XML w document root.', 'pb-media-all-seo' );
		} else {
			$msg = implode( ' ', $result['errors'] );
		}

		set_transient(
			'pb_seo_sitemap_notice_' . get_current_user_id(),
			[ 'type' => $msg_type, 'msg' => $msg ],
			60
		);

		wp_safe_redirect( add_query_arg( 'page', Settings::MENU_SLUG, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * DYNAMIC FALLBACK (only used when physical file is missing)
	 * ------------------------------------------------------------------ */

	public function maybe_serve_dynamic_early( \WP $wp ): void {
		$kind = '';
		if ( isset( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			$kind = (string) $wp->query_vars[ self::QUERY_VAR ];
		}
		if ( '' === $kind && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
			if ( preg_match( '#/sitemapa\.xml/?$#', $uri ) ) {
				$kind = 'pages';
			} elseif ( preg_match( '#/sitemapa-image\.xml/?$#', $uri ) ) {
				$kind = 'images';
			}
		}
		if ( '' === $kind ) {
			return;
		}
		// Try regenerating physical file as a side-effect, then redirect.
		$this->regenerate_now();
		$file = ( 'pages' === $kind ) ? self::FILE_PAGES : self::FILE_IMAGES;
		if ( self::file_exists( $file ) ) {
			// Physical file now exists — redirect so the web server serves it.
			wp_safe_redirect( self::get_file_url( $file ), 302 );
			exit;
		}
		// Last-resort dynamic serve.
		$this->serve_dynamic( $kind );
	}

	public function maybe_serve_dynamic(): void {
		$kind = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $kind ) {
			return;
		}
		$file = ( 'pages' === $kind ) ? self::FILE_PAGES : self::FILE_IMAGES;
		if ( self::file_exists( $file ) ) {
			wp_safe_redirect( self::get_file_url( $file ), 302 );
			exit;
		}
		$this->serve_dynamic( $kind );
	}

	private function serve_dynamic( string $kind ): void {
		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}
		if ( ! headers_sent() ) {
			@header_remove( 'Content-Type' );
			@header_remove( 'X-Pingback' );
			@header_remove( 'Link' );
		}
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8', true );
		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'X-Content-Type-Options: nosniff', true );

		if ( 'pages' === $kind ) {
			echo $this->build_pages_sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'images' === $kind ) {
			echo $this->build_images_sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			status_header( 404 );
		}
		exit;
	}

	/* ---------------------------------------------------------------------
	 * BUILDERS
	 * ------------------------------------------------------------------ */

	/**
	 * Build the pages sitemap XML.
	 * Iterates over all published posts of all public post types,
	 * uses per-post priority/changefreq if set, otherwise defaults.
	 */
	public function build_pages_sitemap(): string {
		$post_types = Plugin::get_supported_post_types();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// Always include front page.
		$xml .= $this->url_entry(
			(string) home_url( '/' ),
			gmdate( 'c' ),
			'1.0',
			'daily'
		);

		if ( ! empty( $post_types ) ) {
			$query = new \WP_Query(
				[
					'post_type'              => $post_types,
					'post_status'            => 'publish',
					'posts_per_page'         => 5000,
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
				]
			);

			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				$loc = (string) get_permalink( $post );
				if ( '' === $loc ) {
					continue;
				}
				// Skip pages explicitly marked noindex.
				$robots = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageRobotsSEO', true );
				if ( 'noindex' === $robots ) {
					continue;
				}

				// ISO 8601 lastmod from post_modified_gmt.
				$lastmod = (string) get_post_modified_time( 'c', true, $post );

				// Per-post priority + changefreq with whitelisted defaults.
				$priority = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pagePrioritySitemap', true );
				if ( ! in_array( $priority, self::VALID_PRIORITIES, true ) ) {
					$priority = '0.7';
				}
				$changefreq = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageChangeFreqSitemap', true );
				if ( ! in_array( $changefreq, self::VALID_CHANGEFREQ, true ) ) {
					$changefreq = 'weekly';
				}

				$xml .= $this->url_entry( $loc, $lastmod, $priority, $changefreq );
			}
			wp_reset_postdata();
		}

		$xml .= '</urlset>' . "\n";
		return $xml;
	}

	/**
	 * Build a single <url> entry.
	 */
	private function url_entry( string $loc, string $lastmod, string $priority, string $changefreq ): string {
		$out  = "  <url>\n";
		$out .= "    <loc>" . esc_url( $loc ) . "</loc>\n";
		$out .= "    <lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
		$out .= "    <priority>" . esc_html( $priority ) . "</priority>\n";
		$out .= "    <changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
		$out .= "  </url>\n";
		return $out;
	}

	/**
	 * Build the images sitemap XML.
	 * Format follows the user-supplied example: one <url> per image with
	 * just <loc> pointing at the image source URL.
	 */
	public function build_images_sitemap(): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$query = new \WP_Query(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => 5000,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
			]
		);

		foreach ( $query->posts as $att ) {
			if ( ! $att instanceof \WP_Post ) {
				continue;
			}
			$src = wp_get_attachment_image_src( $att->ID, 'full' );
			$url = is_array( $src ) && isset( $src[0] ) ? (string) $src[0] : (string) wp_get_attachment_url( $att->ID );
			if ( '' === $url ) {
				continue;
			}
			$xml .= "  <url>\n";
			$xml .= "    <loc>" . esc_url( $url ) . "</loc>\n";
			$xml .= "  </url>\n";
		}
		wp_reset_postdata();

		$xml .= '</urlset>' . "\n";
		return $xml;
	}

	/* ---------------------------------------------------------------------
	 * Cleanup
	 * ------------------------------------------------------------------ */

	/**
	 * Delete physical files (used on plugin deactivation).
	 */
	public static function delete_files(): void {
		foreach ( [ self::FILE_PAGES, self::FILE_IMAGES ] as $f ) {
			$path = self::get_file_path( $f );
			if ( file_exists( $path ) ) {
				@unlink( $path );
			}
		}
	}
}
