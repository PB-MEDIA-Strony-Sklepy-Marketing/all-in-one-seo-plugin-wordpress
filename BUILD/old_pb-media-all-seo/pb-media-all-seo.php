<?php
/**
 * Plugin Name:       PB MEDIA ALL SEO
 * Plugin URI:        https://pb-media.pl/
 * Description:       Kompletny plugin SEO dla wszystkich post_type: meta tagi, OpenGraph, Schema JSON-LD, sitemapy XML, robots.txt oraz llm.txt. W pełni zgodny z Classic Editor i Gutenberg.
 * Version:           1.0.0
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

define( 'PB_MEDIA_ALL_SEO_VERSION', '1.0.0' );
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

/**
 * Plugin activation: register rewrite rules and flush.
 */
function pb_media_all_seo_activate(): void {
	\PB_Media_All_SEO\Sitemap::register_rewrite_rules();
	\PB_Media_All_SEO\Robots::register_rewrite_rules();
	\PB_Media_All_SEO\LLMs::register_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pb_media_all_seo_activate' );

/**
 * Plugin deactivation: flush rewrite rules.
 */
function pb_media_all_seo_deactivate(): void {
	flush_rewrite_rules();
	delete_transient( 'pb_seo_sitemap_pages' );
	delete_transient( 'pb_seo_sitemap_images' );
}
register_deactivation_hook( __FILE__, 'pb_media_all_seo_deactivate' );

/**
 * Bootstrap the plugin.
 */
function pb_media_all_seo_init(): void {
	\PB_Media_All_SEO\Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'pb_media_all_seo_init' );
