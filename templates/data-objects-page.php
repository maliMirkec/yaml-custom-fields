<?php
/**
 * Data Objects Page
 * Main page for managing data object types
 */

if (!defined('ABSPATH')) {
  exit;
}

// Get all data object types
$yaml_cf_data_object_types = get_option('yaml_cf_data_object_types', []);
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Collections', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Things filled in once and used on many pages', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <div class="yaml-cf-intro">
      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-data-object-type')); ?>" class="button button-primary">
          <?php esc_html_e('Add a collection', 'yaml-custom-fields'); ?>
        </a>
      </p>
      <p><?php esc_html_e('A collection holds items that are filled in once, like team members or partner companies. Pages can pick items from a collection.', 'yaml-custom-fields'); ?></p>
    </div>

  <?php if (empty($yaml_cf_data_object_types)) : ?>
    <div class="notice notice-info inline">
      <p><?php esc_html_e('No collections yet. Click "Add a collection" to make the first one.', 'yaml-custom-fields'); ?></p>
    </div>
  <?php else : ?>
    <table class="wp-list-table widefat striped">
      <thead>
        <tr>
          <th><?php esc_html_e('Collection name', 'yaml-custom-fields'); ?></th>
          <th><?php esc_html_e('Short name', 'yaml-custom-fields'); ?></th>
          <th><?php esc_html_e('Items', 'yaml-custom-fields'); ?></th>
          <th><?php esc_html_e('Schema', 'yaml-custom-fields'); ?></th>
          <th><?php esc_html_e('Actions', 'yaml-custom-fields'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($yaml_cf_data_object_types as $yaml_cf_slug => $yaml_cf_type) : ?>
          <?php
          $yaml_cf_entries = get_option('yaml_cf_data_object_entries_' . $yaml_cf_slug, []);
          $yaml_cf_entry_count = count($yaml_cf_entries);
          $yaml_cf_has_schema = !empty($yaml_cf_type['schema']);
          ?>
          <tr>
            <td><strong><?php echo esc_html($yaml_cf_type['name']); ?></strong></td>
            <td><code><?php echo esc_html($yaml_cf_slug); ?></code></td>
            <td><?php echo esc_html($yaml_cf_entry_count); ?></td>
            <td>
              <?php if ($yaml_cf_has_schema) : ?>
                <span class="dashicons dashicons-yes-alt yaml-cf-checkmark"></span>
                <?php esc_html_e('Ready', 'yaml-custom-fields'); ?>
              <?php else : ?>
                <span class="yaml-cf-status-warn"><?php esc_html_e('Missing', 'yaml-custom-fields'); ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-data-object-type&type_id=' . urlencode($yaml_cf_slug))); ?>" class="button">
                <?php esc_html_e('Edit schema', 'yaml-custom-fields'); ?>
              </a>
              <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($yaml_cf_slug))); ?>" class="button">
                <?php esc_html_e('Edit items', 'yaml-custom-fields'); ?>
              </a>
              <form method="post" class="yaml-cf-inline-form" onsubmit="return confirm('<?php
                echo esc_attr(sprintf(
                  /* translators: %1$s: collection name, %2$d: number of entries */
                  __('Delete the collection "%1$s" and all %2$d items? This cannot be undone.', 'yaml-custom-fields'),
                  $yaml_cf_type['name'],
                  $yaml_cf_entry_count
                ));
              ?>');">
                <?php wp_nonce_field('yaml_cf_delete_type', 'yaml_cf_delete_type_nonce'); ?>
                <input type="hidden" name="type_slug" value="<?php echo esc_attr($yaml_cf_slug); ?>" />
                <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete', 'yaml-custom-fields'); ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>
</div>
