<?php
/**
 * Custom XML sitemaps that coexist independently next to the
 * default WordPress wp-sitemap.xml.
 *
 * URLs:
 *  - {site_url}/sitemapa.xml         — all public post types
 *  - {site_url}/sitemapa-image.xml   — all media library attachments
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

	private const QUERY_VAR        = 'pb_seo_sitemap';
	private const TRANSIENT_PAGES  = 'pb_seo_sitemap_pages';
	private const TRANSIENT_IMAGES = 'pb_seo_sitemap_images';
	private const CACHE_TTL        = 12 * HOUR_IN_SECONDS;

	public function register_hooks(): void {
		add_action( 'init', [ self::class, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_sitemap' ], 0 );

		// Cache invalidation hooks.
		add_action( 'save_post', [ $this, 'flush_cache' ] );
		add_action( 'delete_post', [ $this, 'flush_cache' ] );
		add_action( 'transition_post_status', [ $this, 'flush_cache_on_status_change' ], 10, 3 );
		add_action( 'add_attachment', [ $this, 'flush_cache' ] );
		add_action( 'attachment_updated', [ $this, 'flush_cache' ] );
		add_action( 'delete_attachment', [ $this, 'flush_cache' ] );
	}

	/**
	 * Register rewrite rules for /sitemapa.xml and /sitemapa-image.xml.
	 * Static so it can be invoked from the activation hook.
	 */
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
	 * Serve the proper sitemap if our query var is set.
	 */
	public function maybe_serve_sitemap(): void {
		$kind = get_query_var( self::QUERY_VAR );
		if ( '' === $kind || null === $kind ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		if ( 'pages' === $kind ) {
			echo $this->build_pages_sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'images' === $kind ) {
			echo $this->build_images_sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			status_header( 404 );
			exit;
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
			(string) current_time( 'c' ),
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
					'update_post_meta_cache' => false,
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
				$lastmod = mysql2date( 'c', $post->post_modified_gmt, false );
				$xml .= $this->url_entry( $loc, (string) $lastmod, 'weekly', '0.7' );
			}
			wp_reset_postdata();
		}

		$xml .= '</urlset>';

		set_transient( self::TRANSIENT_PAGES, $xml, self::CACHE_TTL );
		return $xml;
	}

	/* ---------------------------------------------------------------------
	 * Images sitemap
	 * ------------------------------------------------------------------ */

	private function build_images_sitemap(): string {
		$cached = get_transient( self::TRANSIENT_IMAGES );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

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

			$image_url = wp_get_attachment_url( $att->ID );
			if ( empty( $image_url ) || ! is_string( $image_url ) ) {
				continue;
			}

			$parent_url = '';
			if ( $att->post_parent > 0 ) {
				$pl = get_permalink( $att->post_parent );
				if ( is_string( $pl ) ) {
					$parent_url = $pl;
				}
			}
			if ( '' === $parent_url ) {
				$parent_url = (string) get_permalink( $att->ID );
			}
			if ( '' === $parent_url ) {
				$parent_url = (string) home_url( '/' );
			}

			$caption = trim( wp_strip_all_tags( (string) $att->post_excerpt ) );
			$title   = trim( wp_strip_all_tags( (string) $att->post_title ) );
			$lastmod = mysql2date( 'c', $att->post_modified_gmt, false );

			$xml .= "<url>\n";
			$xml .= "\t<loc>" . esc_url( $parent_url ) . "</loc>\n";
			$xml .= "\t<lastmod>" . esc_html( (string) $lastmod ) . "</lastmod>\n";
			$xml .= "\t<image:image>\n";
			$xml .= "\t\t<image:loc>" . esc_url( $image_url ) . "</image:loc>\n";
			if ( '' !== $title ) {
				$xml .= "\t\t<image:title>" . $this->xml_escape( $title ) . "</image:title>\n";
			}
			if ( '' !== $caption ) {
				$xml .= "\t\t<image:caption>" . $this->xml_escape( $caption ) . "</image:caption>\n";
			}
			$xml .= "\t</image:image>\n";
			$xml .= "</url>\n";
		}
		wp_reset_postdata();

		$xml .= '</urlset>';

		set_transient( self::TRANSIENT_IMAGES, $xml, self::CACHE_TTL );
		return $xml;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	private function url_entry( string $loc, string $lastmod, string $changefreq, string $priority ): string {
		$out  = "<url>\n";
		$out .= "\t<loc>" . esc_url( $loc ) . "</loc>\n";
		$out .= "\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
		$out .= "\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n";
		$out .= "\t<priority>" . esc_html( $priority ) . "</priority>\n";
		$out .= "</url>\n";
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
