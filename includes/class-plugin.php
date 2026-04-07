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

final class Plugin {

	private static ?Plugin $instance = null;

	public Meta_Boxes $meta_boxes;
	public Frontend $frontend;
	public Settings $settings;
	public Sitemap $sitemap;
	public Robots $robots;
	public LLMs $llms;
	public SEO_Analyzer $analyzer;
	public Bulk_Edit $bulk_edit;
	public Import_Export $import_export;
	public OG_Image $og_image;

	private function __construct() {
		$this->meta_boxes    = new Meta_Boxes();
		$this->frontend      = new Frontend();
		$this->settings      = new Settings();
		$this->sitemap       = new Sitemap();
		$this->robots        = new Robots();
		$this->llms          = new LLMs();
		$this->analyzer      = new SEO_Analyzer();
		$this->bulk_edit     = new Bulk_Edit();
		$this->import_export = new Import_Export();
		$this->og_image      = new OG_Image();
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

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
		$this->analyzer->register_hooks();
		$this->bulk_edit->register_hooks();
		$this->import_export->register_hooks();
		$this->og_image->register_hooks();

		// Auto-flush rewrite rules after plugin update — register_activation_hook
		// is NOT fired by WP when the plugin is replaced via the "Replace existing"
		// upload flow, so we use a versioned option to detect updates.
		add_action( 'init', [ $this, 'maybe_upgrade' ], 99 );
	}

	/**
	 * Detect plugin upgrade and trigger one-time housekeeping.
	 */
	public function maybe_upgrade(): void {
		$installed = (string) get_option( 'pb_seo_installed_version', '' );
		if ( PB_MEDIA_ALL_SEO_VERSION === $installed ) {
			return;
		}
		Sitemap::register_rewrite_rules();
		Robots::register_rewrite_rules();
		LLMs::register_rewrite_rules();
		flush_rewrite_rules();

		// Generate physical sitemap files for the new version.
		try {
			$this->sitemap->regenerate_now();
		} catch ( \Throwable $e ) {
			// Silent.
		}

		update_option( 'pb_seo_installed_version', PB_MEDIA_ALL_SEO_VERSION );
	}

	/**
	 * @return array<int,string>
	 */
	public static function get_supported_post_types(): array {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		unset( $post_types['attachment'] );

		/**
		 * @param array<int,string> $post_types
		 */
		return apply_filters( 'pb_media_all_seo_post_types', array_values( $post_types ) );
	}
}
