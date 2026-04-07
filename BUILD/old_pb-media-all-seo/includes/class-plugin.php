<?php
/**
 * Main plugin class.
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrapper for all plugin components.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public Meta_Boxes $meta_boxes;
	public Frontend $frontend;
	public Settings $settings;
	public Sitemap $sitemap;
	public Robots $robots;
	public LLMs $llms;

	private function __construct() {
		$this->meta_boxes = new Meta_Boxes();
		$this->frontend   = new Frontend();
		$this->settings   = new Settings();
		$this->sitemap    = new Sitemap();
		$this->robots     = new Robots();
		$this->llms       = new LLMs();
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook every component into WordPress.
	 */
	public function run(): void {
		load_plugin_textdomain(
			'pb-media-all-seo',
			false,
			dirname( PB_MEDIA_ALL_SEO_BASENAME ) . '/languages'
		);

		$this->meta_boxes->register_hooks();
		$this->frontend->register_hooks();
		$this->settings->register_hooks();
		$this->sitemap->register_hooks();
		$this->robots->register_hooks();
		$this->llms->register_hooks();
	}

	/**
	 * Returns list of supported public post types (default + custom).
	 *
	 * @return array<int,string>
	 */
	public static function get_supported_post_types(): array {
		$post_types = get_post_types(
			[
				'public' => true,
			],
			'names'
		);

		// Exclude attachments from meta-box editor screens (handled separately).
		unset( $post_types['attachment'] );

		/**
		 * Filter the list of supported post types.
		 *
		 * @param array<int,string> $post_types
		 */
		return apply_filters( 'pb_media_all_seo_post_types', array_values( $post_types ) );
	}
}
