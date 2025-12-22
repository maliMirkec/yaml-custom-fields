<?php
/**
 * Uninstall Script
 * Fired when the plugin is deleted (not deactivated)
 *
 * @package YamlCF
 * @since 1.0.0
 */

// Exit if accessed directly or not uninstalling
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options
delete_option( 'yaml_cf_schemas' );
delete_option( 'yaml_cf_global_schema' );
delete_option( 'yaml_cf_global_data' );
delete_option( 'yaml_cf_partial_data' );
delete_option( 'yaml_cf_template_settings' );
delete_option( 'yaml_cf_template_global_schemas' );
delete_option( 'yaml_cf_template_global_data' );
delete_option( 'yaml_cf_data_object_types' );

// Delete all data object entries (dynamic option names)
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk deletion on uninstall, caching not applicable
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yaml_cf_data_object_entries_%'" );

// Delete all post meta created by the plugin
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk deletion on uninstall, caching not applicable
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yaml_cf_%'" );

// For multisite, delete from all sites
if ( is_multisite() ) {
	$yaml_cf_sites = get_sites(
		array(
			'number' => 0,
			'fields' => 'ids',
		)
	);

	foreach ( $yaml_cf_sites as $yaml_cf_site_id ) {
		switch_to_blog( $yaml_cf_site_id );

		// Delete options for this site
		delete_option( 'yaml_cf_schemas' );
		delete_option( 'yaml_cf_global_schema' );
		delete_option( 'yaml_cf_global_data' );
		delete_option( 'yaml_cf_partial_data' );
		delete_option( 'yaml_cf_template_settings' );
		delete_option( 'yaml_cf_template_global_schemas' );
		delete_option( 'yaml_cf_template_global_data' );
		delete_option( 'yaml_cf_data_object_types' );

		// Delete data object entries
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk deletion on uninstall, caching not applicable
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yaml_cf_data_object_entries_%'" );

		// Delete post meta
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk deletion on uninstall, caching not applicable
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yaml_cf_%'" );

		restore_current_blog();
	}
}

// Clear any cached data
wp_cache_flush();
