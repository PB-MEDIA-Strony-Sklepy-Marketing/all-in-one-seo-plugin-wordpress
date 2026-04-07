<?php
/**
 * Custom XML sitemaps that coexist independently next to the
 * default WordPress wp-sitemap.xml.
 *
 * URLs:
 *  - {site_url}/sitemapa.xml         — all public post types
 *  - {site_url}/sitemapa-image.xml   — all media library attachments
 *
 * Compliant with the Sitemap protocol 0.90 (sitemaps.org) and
 * the Google Image Sitemap extension (image:image / image:loc).
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sitemap {

	private const QUERY_VAR        = 'pb_seo_sitemap';
	private const TRANSIENT_PAGES  = 'pb_seo_sitemap_pages';
	private const TRANSIENT_IMAGES = 'pb_seo_sitemap_images';
	private const CACHE_TTL        = 12 * HOUR_IN_SECONDS;

	public function register_hooks(): void {
		add_action( 'init', [ self::class, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );

		// EARLY hook — runs before WP send_headers fires text/html default.
		add_action( 'parse_request', [ $this, 'maybe_serve_sitemap_early' ], 0 );
		// Belt-and-braces fallback.
		add_action( 'template_redirect', [ $this, 'maybe_serve_sitemap' ], 0 );

		// Cache invalidation hooks.
		add_action( 'save_post', [ $this, 'flush_cache' ] );
		add_action( 'delete_post', [ $this, 'flush_cache' ] );
		add_action( 'transition_post_status', [ $this, 'flush_cache_on_status_change' ], 10, 3 );
		add_action( 'add_attachment', [ $this, 'flush_cache' ] );
		add_action( 'attachment_updated', [ $this, 'flush_cache' ] );
		add_action( 'delete_attachment', [ $this, 'flush_cache' ] );
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

	/**
	 * Detect sitemap requests as early as possible (parse_request).
	 * Falls back to URI-based detection if query vars are not yet populated.
	 */
	public function maybe_serve_sitemap_early( \WP $wp ): void {
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
		$this->serve( $kind );
	}

	public function maybe_serve_sitemap(): void {
		$kind = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $kind ) {
			return;
		}
		$this->serve( $kind );
	}

	/**
	 * Emit the sitemap with bullet-proof headers.
	 * Aggressively cleans output buffers and removes pre-set headers.
	 */
	private function serve( string $kind ): void {
		// 1. Discard ANY pending output buffers from other plugins/themes.
		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}

		// 2. Strip pre-set headers that would clash with our XML response.
		if ( ! headers_sent() ) {
			@header_remove( 'Content-Type' );
			@header_remove( 'X-Pingback' );
			@header_remove( 'Link' );
		}

		// 3. Send our headers with replace=true.
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
	 * Pages sitemap
	 * ------------------------------------------------------------------ */

	private function build_pages_sitemap(): string {
		$cached = get_transient( self::TRANSIENT_PAGES );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$post_types = Plugin::get_supported_post_types();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// Always include site front page.
		$xml .= $this->url_entry(
			(string) home_url( '/' ),
			gmdate( 'c' ),
			'daily',
			'1.0'
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
				// Skip pages explicitly noindexed.
				$robots = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageRobotsSEO', true );
				if ( 'noindex' === $robots ) {
					continue;
				}
				$lastmod = mysql2date( 'c', $post->post_modified_gmt, false );
				$xml    .= $this->url_entry( $loc, (string) $lastmod, 'weekly', '0.7' );
			}
			wp_reset_postdata();
		}

		$xml .= '</urlset>' . "\n";

		set_transient( self::TRANSIENT_PAGES, $xml, self::CACHE_TTL );
		return $xml;
	}

	/* ---------------------------------------------------------------------
	 * Images sitemap (Google image:image extension)
	 * ------------------------------------------------------------------ */

	private function build_images_sitemap(): string {
		$cached = get_transient( self::TRANSIENT_IMAGES );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		// Group attachments by their parent page URL — Google recommends
		// at most 1000 <image:image> per <url> entry.
		$grouped = $this->collect_images_grouped();

		foreach ( $grouped as $page_url => $images ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $page_url ) . "</loc>\n";
			if ( ! empty( $images[0]['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_html( $images[0]['lastmod'] ) . "</lastmod>\n";
			}
			foreach ( $images as $img ) {
				$xml .= "\t\t<image:image>\n";
				$xml .= "\t\t\t<image:loc>" . esc_url( $img['loc'] ) . "</image:loc>\n";
				if ( '' !== $img['title'] ) {
					$xml .= "\t\t\t<image:title>" . $this->xml_escape( $img['title'] ) . "</image:title>\n";
				}
				if ( '' !== $img['caption'] ) {
					$xml .= "\t\t\t<image:caption>" . $this->xml_escape( $img['caption'] ) . "</image:caption>\n";
				}
				$xml .= "\t\t</image:image>\n";
			}
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>' . "\n";

		set_transient( self::TRANSIENT_IMAGES, $xml, self::CACHE_TTL );
		return $xml;
	}

	/**
	 * @return array<string, array<int, array{loc:string,title:string,caption:string,lastmod:string}>>
	 */
	private function collect_images_grouped(): array {
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

		$grouped = [];

		foreach ( $query->posts as $att ) {
			if ( ! $att instanceof \WP_Post ) {
				continue;
			}

			$image_url = wp_get_attachment_url( $att->ID );
			if ( ! is_string( $image_url ) || '' === $image_url ) {
				continue;
			}

			$parent_url = '';
			if ( $att->post_parent > 0 ) {
				$pl = get_permalink( $att->post_parent );
				if ( is_string( $pl ) && '' !== $pl ) {
					$parent_url = $pl;
				}
			}
			if ( '' === $parent_url ) {
				$parent_url = (string) home_url( '/' );
			}

			$caption = trim( wp_strip_all_tags( (string) $att->post_excerpt ) );
			$title   = trim( wp_strip_all_tags( (string) $att->post_title ) );
			$lastmod = (string) mysql2date( 'c', $att->post_modified_gmt, false );

			$grouped[ $parent_url ][] = [
				'loc'     => $image_url,
				'title'   => $title,
				'caption' => $caption,
				'lastmod' => $lastmod,
			];
		}
		wp_reset_postdata();

		return $grouped;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	private function url_entry( string $loc, string $lastmod, string $changefreq, string $priority ): string {
		$out  = "\t<url>\n";
		$out .= "\t\t<loc>" . esc_url( $loc ) . "</loc>\n";
		$out .= "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
		$out .= "\t\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
		$out .= "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";
		$out .= "\t</url>\n";
		return $out;
	}

	private function xml_escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	public function flush_cache(): void {
		delete_transient( self::TRANSIENT_PAGES );
		delete_transient( self::TRANSIENT_IMAGES );
	}

	public function flush_cache_on_status_change( string $new_status, string $old_status, $post ): void {
		if ( $new_status !== $old_status ) {
			$this->flush_cache();
		}
	}
}
