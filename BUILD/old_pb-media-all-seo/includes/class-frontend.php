<?php
/**
 * Frontend output for <head> section.
 *
 * Renders the following on singular pages of supported post types:
 *  - Title + Meta Tags SEO          (priority 1)
 *  - OpenGraph                      (priority 2)
 *  - fb:app_id                      (priority 3)
 *  - Schema JSON-LD                 (priority 99 — late, near </head>)
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Frontend {

	public function register_hooks(): void {
		add_action( 'wp_head', [ $this, 'output_seo_meta' ], 1 );
		add_action( 'wp_head', [ $this, 'output_opengraph' ], 2 );
		add_action( 'wp_head', [ $this, 'output_fb_app_id' ], 3 );
		add_action( 'wp_head', [ $this, 'output_schema_jsonld' ], 99 );

		// Disable WP-generated <title> so ours wins (theme should use add_theme_support('title-tag')).
		add_filter( 'pre_get_document_title', [ $this, 'filter_document_title' ], 99 );
	}

	/**
	 * Returns the current singular post if it is a supported post type.
	 */
	private function current_post(): ?\WP_Post {
		if ( ! is_singular() ) {
			return null;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		if ( ! in_array( $post->post_type, Plugin::get_supported_post_types(), true ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Get a single meta value with fallback.
	 */
	private function meta( int $post_id, string $field ): string {
		return (string) get_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . $field, true );
	}

	/* ---------------------------------------------------------------------
	 * SEO Meta block
	 * ------------------------------------------------------------------ */

	public function output_seo_meta(): void {
		$post = $this->current_post();
		if ( null === $post ) {
			return;
		}

		$title = $this->meta( $post->ID, 'pageTitleSEO' );
		if ( '' === $title ) {
			$title = wp_strip_all_tags( get_the_title( $post ) );
		}

		$description = $this->meta( $post->ID, 'pageDescriptionSEO' );
		if ( '' === $description ) {
			$description = $this->generate_excerpt( $post );
		}

		$keywords = $this->meta( $post->ID, 'pageKeywordsSEO' );

		$author_id = (int) $this->meta( $post->ID, 'pageAuthorSEO' );
		if ( $author_id > 0 ) {
			$author_name = (string) get_the_author_meta( 'display_name', $author_id );
		} else {
			$author_name = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
		}

		$canonical = $this->meta( $post->ID, 'pageCanonicalURLSEO' );
		if ( '' === $canonical ) {
			$canonical = (string) get_permalink( $post );
		}

		$robots = $this->meta( $post->ID, 'pageRobotsSEO' );
		$custom = $this->meta( $post->ID, 'pageCustomMetaTagSEO' );

		echo "\n<!-- PB MEDIA ALL SEO :: Meta Tagi SEO -->\n";

		// We override the title with our own; only print if theme supports title-tag is missing.
		if ( ! current_theme_supports( 'title-tag' ) ) {
			printf( "<title>%s</title>\n", esc_html( $title ) );
		}

		if ( '' !== $description ) {
			printf(
				"<meta name=\"description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}
		if ( '' !== $keywords ) {
			printf(
				"<meta name=\"keywords\" content=\"%s\" />\n",
				esc_attr( $keywords )
			);
		}
		if ( '' !== $author_name ) {
			printf(
				"<meta name=\"author\" content=\"%s\" />\n",
				esc_attr( $author_name )
			);
		}
		if ( '' !== $canonical ) {
			printf(
				"<link rel=\"canonical\" href=\"%s\" />\n",
				esc_url( $canonical )
			);
		}
		if ( '' !== $robots ) {
			printf(
				"<meta name=\"robots\" content=\"%s\" />\n",
				esc_attr( $robots )
			);
		}
		if ( '' !== $custom ) {
			echo $this->render_custom_meta_lines( $custom ) . "\n"; // already wp_kses-cleaned at save time
		}

		echo "<!-- /PB MEDIA ALL SEO :: Meta Tagi SEO -->\n";
	}

	/**
	 * Filter document title used by `wp_get_document_title()` so theme `<title>` matches ours.
	 */
	public function filter_document_title( $title ) {
		$post = $this->current_post();
		if ( null === $post ) {
			return $title;
		}
		$custom = $this->meta( $post->ID, 'pageTitleSEO' );
		return '' !== $custom ? $custom : $title;
	}

	/* ---------------------------------------------------------------------
	 * OpenGraph block
	 * ------------------------------------------------------------------ */

	public function output_opengraph(): void {
		$post = $this->current_post();
		if ( null === $post ) {
			return;
		}

		$title = $this->meta( $post->ID, 'pageOGtitle' );
		if ( '' === $title ) {
			$title = wp_strip_all_tags( get_the_title( $post ) );
		}

		$description = $this->meta( $post->ID, 'pageOGdescription' );
		if ( '' === $description ) {
			$description = $this->generate_excerpt( $post );
		}

		$image = $this->meta( $post->ID, 'pageOGimage' );
		if ( '' === $image ) {
			$thumb = get_the_post_thumbnail_url( $post, 'full' );
			$image = is_string( $thumb ) ? $thumb : '';
		}

		$image_alt = $this->meta( $post->ID, 'pageOGimagealt' );
		if ( '' === $image_alt ) {
			$thumb_id = (int) get_post_thumbnail_id( $post );
			if ( $thumb_id > 0 ) {
				$image_alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
			}
		}

		$locale = get_locale();

		$type = $this->meta( $post->ID, 'pageOGtype' );
		if ( '' === $type ) {
			$type = 'article';
		}

		$url = $this->meta( $post->ID, 'pageOGurl' );
		if ( '' === $url ) {
			$url = (string) get_permalink( $post );
		}

		$site_name = $this->meta( $post->ID, 'pageOGsitename' );
		if ( '' === $site_name ) {
			$site_name = (string) get_bloginfo( 'name' );
		}

		$custom = $this->meta( $post->ID, 'pageOGCustomTag' );

		echo "\n<!-- PB MEDIA ALL SEO :: OpenGraph -->\n";

		if ( '' !== $title ) {
			printf( "<meta property=\"og:title\" content=\"%s\" />\n", esc_attr( $title ) );
		}
		if ( '' !== $description ) {
			printf( "<meta property=\"og:description\" content=\"%s\" />\n", esc_attr( $description ) );
		}
		if ( '' !== $image ) {
			printf( "<meta property=\"og:image\" content=\"%s\" />\n", esc_url( $image ) );
		}
		if ( '' !== $image_alt ) {
			printf( "<meta property=\"og:image:alt\" content=\"%s\" />\n", esc_attr( $image_alt ) );
		}
		if ( '' !== $locale ) {
			printf( "<meta property=\"og:locale\" content=\"%s\" />\n", esc_attr( $locale ) );
		}
		printf( "<meta property=\"og:type\" content=\"%s\" />\n", esc_attr( $type ) );
		if ( '' !== $url ) {
			printf( "<meta property=\"og:url\" content=\"%s\" />\n", esc_url( $url ) );
		}
		if ( '' !== $site_name ) {
			printf( "<meta property=\"og:site_name\" content=\"%s\" />\n", esc_attr( $site_name ) );
		}
		if ( '' !== $custom ) {
			echo $this->render_custom_meta_lines( $custom ) . "\n";
		}

		echo "<!-- /PB MEDIA ALL SEO :: OpenGraph -->\n";
	}

	/* ---------------------------------------------------------------------
	 * fb:app_id (global option)
	 * ------------------------------------------------------------------ */

	public function output_fb_app_id(): void {
		$fb_app_id = (string) get_option( PB_MEDIA_ALL_SEO_OPTION_PREFIX . 'fb_app_id', '' );
		if ( '' === $fb_app_id ) {
			return;
		}
		printf(
			"<meta property=\"fb:app_id\" content=\"%s\" />\n",
			esc_attr( $fb_app_id )
		);
	}

	/* ---------------------------------------------------------------------
	 * Schema JSON-LD (rendered just before </head>)
	 * ------------------------------------------------------------------ */

	public function output_schema_jsonld(): void {
		$post = $this->current_post();
		if ( null === $post ) {
			return;
		}
		$json = $this->meta( $post->ID, 'pageSchemaJSON' );
		if ( '' === $json ) {
			return;
		}

		// Re-validate just before render to avoid breaking the head if DB was tampered with.
		$decoded = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return;
		}
		$safe = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $safe ) {
			return;
		}

		echo "\n<!-- PB MEDIA ALL SEO :: Schema JSON-LD -->\n";
		echo '<script type="application/ld+json">' . $safe . "</script>\n";
		echo "<!-- /PB MEDIA ALL SEO :: Schema JSON-LD -->\n";
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Render textarea content line by line, ignoring blank lines.
	 * Content is already wp_kses-clean (saved that way).
	 */
	private function render_custom_meta_lines( string $raw ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		$out = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$out[] = $line;
		}
		return implode( "\n", $out );
	}

	/**
	 * Generate a usable description fallback from post content/excerpt.
	 */
	private function generate_excerpt( \WP_Post $post ): string {
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			return wp_strip_all_tags( (string) $post->post_excerpt );
		}
		$content = wp_strip_all_tags( (string) $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );
		return trim( wp_trim_words( (string) $content, 30, '' ) );
	}
}
