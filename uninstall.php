<?php
/**
 * Uninstall handler — removes all plugin data on full uninstall.
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Delete all post meta with our prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_pb_seo_' ) . '%'
	)
);

// 2. Delete all options with our prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'pb_seo_' ) . '%'
	)
);

// 3. Delete transients.
delete_transient( 'pb_seo_sitemap_pages' );
delete_transient( 'pb_seo_sitemap_images' );
delete_transient( 'pb_seo_sitemap_dirty' );

// 4. Delete physical sitemap files from document root.
foreach ( [ 'sitemapa.xml', 'sitemapa-image.xml' ] as $file ) {
	$path = trailingslashit( ABSPATH ) . $file;
	if ( file_exists( $path ) ) {
		@unlink( $path );
	}
}

// 5. Flush rewrite rules.
flush_rewrite_rules();
