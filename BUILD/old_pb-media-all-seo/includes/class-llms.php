<?php
/**
 * llm.txt handler — serves /llm.txt as text/plain.
 *
 * Format follows the llmstxt.org spec (Markdown):
 *  H1 → blockquote summary → body → H2 file lists with [name](url) links.
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
		add_action( 'template_redirect', [ $this, 'maybe_serve_llms' ], 0 );
	}

	public static function register_rewrite_rules(): void {
		// Both /llm.txt and /llms.txt are accepted (spec uses llms.txt; legacy uses llm.txt).
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

	public function maybe_serve_llms(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=UTF-8' );

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
