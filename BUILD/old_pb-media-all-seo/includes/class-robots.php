<?php
/**
 * Custom robots.txt served at {site_url}/robots.txt.
 *
 * Strategy:
 *  - Hook into core `robots_txt` filter (works when WP serves /robots.txt
 *    on a non-pretty-permalink install).
 *  - Additionally register a rewrite rule and template_redirect override
 *    so that on hosts where a static robots.txt does NOT exist, our content
 *    is served first with proper Content-Type.
 *
 * If a physical robots.txt file exists on disk, the web server (Apache/Nginx)
 * will serve that instead of WordPress; in that case the admin should remove
 * it to allow this plugin to take over.
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
		add_action( 'template_redirect', [ $this, 'maybe_serve_robots' ], 0 );

		// Filter the standard core robots.txt output.
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

	public function maybe_serve_robots(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=UTF-8' );

		$content = $this->get_content();
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
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
