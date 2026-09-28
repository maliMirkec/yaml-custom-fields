<?php
/**
 * Uninstall Script
 * Fired when the plugin is deleted (not deactivated)
 *
 * This is the only uninstall path: WordPress runs this file instead of any
 * registered uninstall hook.
 *
 * @package YamlCF
 * @since 1.0.0
 */

// Exit if accessed directly or not uninstalling
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit;
}

/**
 * Delete all plugin data for the current site
 *
 * Uses the options/meta/transient APIs so object caches stay consistent
 * (no direct DELETE queries and no global cache flush).
 */
function yaml_cf_uninstall_site() {
  global $wpdb;

  // The field content is the site's own writing, not settings, and removing it
  // cannot be undone. An administrator can ask on the plugin's own page for it
  // to be left in place, in which case nothing here runs for this site.
  if ( get_option( 'yaml_cf_keep_data_on_uninstall' ) ) {
    return;
  }

  // Fixed options
  $yaml_cf_options = array(
    'yaml_cf_schemas',
    'yaml_cf_global_schema',
    'yaml_cf_global_data',
    'yaml_cf_partial_data',
    'yaml_cf_template_settings',
    'yaml_cf_template_global_schemas',
    'yaml_cf_template_global_data',
    'yaml_cf_data_object_types',
    'yaml_cf_tracked_posts',
    'yaml_cf_db_version',
    'yaml_cf_keep_data_on_uninstall',
    'yaml_cf_clear_post_caches_on_site_save',
    'yaml_cf_rest_public_read',
  );
  foreach ( $yaml_cf_options as $yaml_cf_option ) {
    delete_option( $yaml_cf_option );
  }

  // Collection items: one option per item since 2.0.0, plus the index beside
  // them, plus the single option each collection used to keep them all in, plus
  // any lock row a save that died never released.
  // Direct SQL is required to find dynamic option names (documented exception in Rule #5)
  $yaml_cf_entry_prefixes = array(
    'yaml_cf_do_entry_',
    'yaml_cf_do_index_',
    'yaml_cf_data_object_entries_',
    'yaml_cf_do_lock_',
  );
  foreach ( $yaml_cf_entry_prefixes as $yaml_cf_prefix ) {
    $yaml_cf_entry_options = $wpdb->get_col(
      $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( $yaml_cf_prefix ) . '%'
      )
    );
    foreach ( $yaml_cf_entry_options as $yaml_cf_option ) {
      delete_option( $yaml_cf_option );
    }
  }

  // Transients (template cache, notices, import status, invalid schema drafts)
  $yaml_cf_transients = $wpdb->get_col(
    $wpdb->prepare(
      "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
      $wpdb->esc_like( '_transient_yaml_cf_' ) . '%'
    )
  );
  foreach ( $yaml_cf_transients as $yaml_cf_transient ) {
    delete_transient( substr( $yaml_cf_transient, strlen( '_transient_' ) ) );
  }

  // Post meta
  $yaml_cf_meta_keys = array(
    '_yaml_cf_data',
    '_yaml_cf_schema',
    '_yaml_cf_imported',
    '_yaml_cf_use_template_global',
    '_yaml_cf_use_template_global_fields',
  );
  foreach ( $yaml_cf_meta_keys as $yaml_cf_meta_key ) {
    delete_post_meta_by_key( $yaml_cf_meta_key );
  }
}

if ( is_multisite() ) {
  $yaml_cf_sites = get_sites(
    array(
      'number' => 0,
      'fields' => 'ids',
    )
  );

  foreach ( $yaml_cf_sites as $yaml_cf_site_id ) {
    switch_to_blog( $yaml_cf_site_id );
    yaml_cf_uninstall_site();
    restore_current_blog();
  }
} else {
  yaml_cf_uninstall_site();
}
