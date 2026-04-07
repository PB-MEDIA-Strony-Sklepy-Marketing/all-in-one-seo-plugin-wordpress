<?php
/**
 * llm.txt / llms.txt handler.
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LLMs {

	private const QUERY_VAR = 'pb_seo_llms';

	public function register_hooks(): void {
		add_action( 'init', [ self::class, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'parse_request', [ $this, 'maybe_serve_early' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_serve_llms' ], 0 );
	}

	public static function register_rewrite_rules(): void {
		add_rewrite_rule( '^llm\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
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
		$is_llms = ! empty( $wp->query_vars[ self::QUERY_VAR ] );
		if ( ! $is_llms && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
			if ( preg_match( '#/llms?\.txt/?$#', $uri ) ) {
				$is_llms = true;
			}
		}
		if ( $is_llms ) {
			$this->serve();
		}
	}

	public function maybe_serve_llms(): void {
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

		$content = (string) get_option( Settings::OPT_LLMS_TXT, '' );
		if ( '' === trim( $content ) ) {
			$content = $this->default_content();
		}
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private function default_content(): string {
		$site_name = (string) get_bloginfo( 'name' );
		$site_desc = (string) get_bloginfo( 'description' );
		$site_url  = trailingslashit( home_url() );
		return "# {$site_name}\n\n> {$site_desc}\n\n## Resources\n\n- [Strona główna]({$site_url})\n- [Sitemapa stron]({$site_url}sitemapa.xml)\n- [Sitemapa obrazów]({$site_url}sitemapa-image.xml)\n";
	}
}
