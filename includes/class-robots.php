<?php
/**
 * Custom robots.txt served at {site_url}/robots.txt.
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Robots {

	private const QUERY_VAR = 'pb_seo_robots';

	public function register_hooks(): void {
		add_action( 'init', [ self::class, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'parse_request', [ $this, 'maybe_serve_early' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_serve_robots' ], 0 );

		// Also filter the standard core robots.txt output for fallback compatibility.
		add_filter( 'robots_txt', [ $this, 'filter_core_robots_txt' ], 20, 2 );
	}

	public static function register_rewrite_rules(): void {
		add_rewrite_rule( '^robots\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_serve_early( \WP $wp ): void {
		$is_robots = ! empty( $wp->query_vars[ self::QUERY_VAR ] );
		if ( ! $is_robots && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
			if ( preg_match( '#/robots\.txt/?$#', $uri ) ) {
				$is_robots = true;
			}
		}
		if ( $is_robots ) {
			$this->serve();
		}
	}

	public function maybe_serve_robots(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		$this->serve();
	}

	private function serve(): void {
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
		header( 'Content-Type: text/plain; charset=UTF-8', true );
		header( 'X-Content-Type-Options: nosniff', true );

		echo $this->get_content(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Replace WP's default robots.txt body when our option is set.
	 *
	 * @param string $output
	 * @param bool   $public
	 */
	public function filter_core_robots_txt( $output, $public ): string {
		$custom = (string) get_option( Settings::OPT_ROBOTS_TXT, '' );
		if ( '' === trim( $custom ) ) {
			return (string) $output;
		}
		return $custom;
	}

	private function get_content(): string {
		$content = (string) get_option( Settings::OPT_ROBOTS_TXT, '' );
		if ( '' === trim( $content ) ) {
			$site_url = trailingslashit( home_url() );
			$content  = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . $site_url . "wp-sitemap.xml\nSitemap: " . $site_url . "sitemapa.xml\nSitemap: " . $site_url . "sitemapa-image.xml\n";
		}
		return $content;
	}
}
