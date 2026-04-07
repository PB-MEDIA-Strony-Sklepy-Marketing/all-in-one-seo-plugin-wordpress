<?php
/**
 * Plugin Name:       PB MEDIA ALL SEO
 * Plugin URI:        https://pb-media.pl/
 * Description:       Kompletny plugin SEO: meta tagi, OpenGraph, Schema JSON-LD, fizyczne sitemapy XML, robots.txt, llm.txt, bulk edit, import/export, SEO score analyzer i auto-generator OG image.
 * Version:           1.2.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            PB MEDIA
 * Author URI:        https://pb-media.pl/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pb-media-all-seo
 * Domain Path:       /languages
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PB_MEDIA_ALL_SEO_VERSION', '1.2.0' );
define( 'PB_MEDIA_ALL_SEO_FILE', __FILE__ );
define( 'PB_MEDIA_ALL_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'PB_MEDIA_ALL_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'PB_MEDIA_ALL_SEO_BASENAME', plugin_basename( __FILE__ ) );
define( 'PB_MEDIA_ALL_SEO_META_PREFIX', '_pb_seo_' );
define( 'PB_MEDIA_ALL_SEO_OPTION_PREFIX', 'pb_seo_' );

require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-plugin.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-meta-boxes.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-frontend.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-settings.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-sitemap.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-robots.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-llms.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-seo-analyzer.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-bulk-edit.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-import-export.php';
require_once PB_MEDIA_ALL_SEO_PATH . 'includes/class-og-image.php';

/**
 * Plugin activation: register rewrite rules, flush, generate physical sitemap files.
 */
function pb_media_all_seo_activate(): void {
	\PB_Media_All_SEO\Sitemap::register_rewrite_rules();
	\PB_Media_All_SEO\Robots::register_rewrite_rules();
	\PB_Media_All_SEO\LLMs::register_rewrite_rules();
	flush_rewrite_rules();

	// Generate physical sitemap files immediately on activation.
	$sitemap = new \PB_Media_All_SEO\Sitemap();
	try {
		$sitemap->regenerate_now();
	} catch ( \Throwable $e ) {
		// Silent — will retry on first request.
	}

	// Reset upgrade flag so the upgrade hook re-runs after re-activation.
	delete_option( 'pb_seo_installed_version' );
}
register_activation_hook( __FILE__, 'pb_media_all_seo_activate' );

/**
 * Plugin deactivation: flush rewrite rules and delete physical sitemap files.
 */
function pb_media_all_seo_deactivate(): void {
	flush_rewrite_rules();
	\PB_Media_All_SEO\Sitemap::delete_files();
}
register_deactivation_hook( __FILE__, 'pb_media_all_seo_deactivate' );

/**
 * Bootstrap the plugin.
 */
function pb_media_all_seo_init(): void {
	\PB_Media_All_SEO\Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'pb_media_all_seo_init' );
