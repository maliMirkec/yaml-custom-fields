<?php
/**
 * Manage Data Object Entries Page
 * CRUD interface for data object entries
 */

if (!defined('ABSPATH')) {
  exit;
}

// Variables passed from controller:
// - $type_id: The data object type slug
// - $type_name: The data object type name
// - $schema: The parsed schema
// - $entries: All entries for this type

$yaml_cf_type_slug = $type_id;
$yaml_cf_entry_id = YAML_Custom_Fields::get_param('entry', ''); // Use get_param to preserve periods in entry IDs
$yaml_cf_action = YAML_Custom_Fields::get_param_key('action', 'list');
$yaml_cf_type_name = $type_name;
$yaml_cf_schema = $schema;
$yaml_cf_entries = $entries;
$yaml_cf_plugin = YAML_Custom_Fields::get_instance();

// Get entry data for editing
$yaml_cf_entry_data = [];
if ($yaml_cf_action === 'edit') {
  // Try direct lookup first
  if (isset($yaml_cf_entries[$yaml_cf_entry_id])) {
    $yaml_cf_entry_data = $yaml_cf_entries[$yaml_cf_entry_id];
  } else {
    // Fallback: search for entries with periods (backward compatibility)
    // Old entries might have periods from uniqid('entry_', true)
    foreach ($yaml_cf_entries as $yaml_cf_stored_id => $yaml_cf_stored_data) {
      // Remove period from stored ID and compare
      if (str_replace('.', '', $yaml_cf_stored_id) === $yaml_cf_entry_id) {
        $yaml_cf_entry_data = $yaml_cf_stored_data;
        // Update the entry_id variable to match the stored ID for the form
        $yaml_cf_entry_id = $yaml_cf_stored_id;
        break;
      }
    }
  }

  // Migrate old data format (keys like 'yaml_cfname' to 'name')
  if (!empty($yaml_cf_entry_data)) {
    $yaml_cf_migrated_data = [];
    foreach ($yaml_cf_entry_data as $yaml_cf_key => $yaml_cf_value) {
      // Remove 'yaml_cf' prefix if present
      if (strpos($yaml_cf_key, 'yaml_cf') === 0) {
        $yaml_cf_new_key = substr($yaml_cf_key, 7); // Remove 'yaml_cf' prefix
        $yaml_cf_migrated_data[$yaml_cf_new_key] = $yaml_cf_value;
      } else {
        $yaml_cf_migrated_data[$yaml_cf_key] = $yaml_cf_value;
      }
    }
    $yaml_cf_entry_data = $yaml_cf_migrated_data;
  }
}
?>

<div id="yaml-cf-notifications">
  <?php
  // Display success messages (using transients - shown only once)
  $yaml_cf_success_key = 'yaml_cf_data_object_success_' . get_current_user_id();
  $yaml_cf_success_msg = get_transient($yaml_cf_success_key);
  if ($yaml_cf_success_msg) {
    $yaml_cf_success_messages = [
      'entry_saved' => __('Entry saved successfully!', 'yaml-custom-fields'),
      'entry_deleted' => __('Entry deleted successfully!', 'yaml-custom-fields'),
    ];
    $yaml_cf_message = isset($yaml_cf_success_messages[$yaml_cf_success_msg]) ? $yaml_cf_success_messages[$yaml_cf_success_msg] : __('Action completed successfully!', 'yaml-custom-fields');
    echo '<div class="yaml-cf-message success" data-type="success">' . esc_html($yaml_cf_message) . '</div>';
    delete_transient($yaml_cf_success_key);
  }
  ?>
</div>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php echo esc_html($yaml_cf_type_name); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Manage entries', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-intro">
      <p>
        <?php if ($yaml_cf_action === 'list') : ?>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-data-objects')); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php esc_html_e('Back to Data Objects', 'yaml-custom-fields'); ?>
          </a>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-data-object-type&type_id=' . urlencode($yaml_cf_type_slug))); ?>" class="button button-secondary" style="margin-left: 10px;">
            <span class="dashicons dashicons-edit"></span>
            <?php esc_html_e('Edit Schema', 'yaml-custom-fields'); ?>
          </a>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($yaml_cf_type_slug) . '&action=add')); ?>" class="button button-primary" style="margin-left: 10px;">
            <?php esc_html_e('Add New Entry', 'yaml-custom-fields'); ?>
          </a>
        <?php else : ?>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($yaml_cf_type_slug))); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php esc_html_e('Back to Entries', 'yaml-custom-fields'); ?>
          </a>
        <?php endif; ?>
      </p>
      <p><strong><?php esc_html_e('Type:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($yaml_cf_type_slug); ?></code></p>
    </div>

    <?php if ($yaml_cf_action === 'list') : ?>
      <!-- List View -->
      <?php if (empty($yaml_cf_entries)) : ?>
        <div class="notice notice-info inline">
          <p><?php esc_html_e('No entries yet. Click "Add New Entry" to create your first entry.', 'yaml-custom-fields'); ?></p>
        </div>
      <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th><?php esc_html_e('Entry ID', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Data', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Actions', 'yaml-custom-fields'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($yaml_cf_entries as $yaml_cf_entry_id => $yaml_cf_entry) : ?>
              <tr>
                <td><code><?php echo esc_html($yaml_cf_entry_id); ?></code></td>
                <td>
                  <?php
                  // Display first field value as preview
                  if (!empty($yaml_cf_schema['fields']) && !empty($yaml_cf_entry)) {
                    $yaml_cf_first_field = $yaml_cf_schema['fields'][0];
                    $yaml_cf_first_value = isset($yaml_cf_entry[$yaml_cf_first_field['name']]) ? $yaml_cf_entry[$yaml_cf_first_field['name']] : '';
                    if (is_string($yaml_cf_first_value)) {
                      echo esc_html(wp_trim_words($yaml_cf_first_value, 10));
                    } else {
                      echo '<em>' . esc_html__('(Complex data)', 'yaml-custom-fields') . '</em>';
                    }
                  }
                  ?>
                </td>
                <td>
                  <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($yaml_cf_type_slug) . '&action=edit&entry=' . urlencode($yaml_cf_entry_id))); ?>" class="button">
                    <?php esc_html_e('Edit', 'yaml-custom-fields'); ?>
                  </a>
                  <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this entry?', 'yaml-custom-fields'); ?>');">
                    <?php wp_nonce_field('yaml_cf_delete_entry', 'yaml_cf_delete_entry_nonce'); ?>
                    <input type="hidden" name="entry_id" value="<?php echo esc_attr($yaml_cf_entry_id); ?>" />
                    <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete', 'yaml-custom-fields'); ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    <?php else : ?>
      <!-- Add/Edit Form -->
      <form method="post">
        <?php wp_nonce_field('yaml_cf_save_entry', 'yaml_cf_save_entry_nonce'); ?>
        <?php if ($yaml_cf_action === 'edit') : ?>
          <input type="hidden" name="entry_id" value="<?php echo esc_attr($yaml_cf_entry_id); ?>" />
        <?php endif; ?>

        <div class="yaml-cf-fields" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
          <?php
          if (!empty($yaml_cf_schema['fields'])) {
            $yaml_cf_context = ['type' => 'data_object', 'object_type' => $yaml_cf_type_slug];
            $yaml_cf_plugin->render_schema_fields($yaml_cf_schema['fields'], $yaml_cf_entry_data, '', $yaml_cf_context);
          } else {
            echo '<p>' . esc_html__('No fields defined in schema.', 'yaml-custom-fields') . '</p>';
          }
          ?>
        </div>

        <p class="submit" style="margin-top: 20px;">
          <button type="submit" class="button button-primary button-large">
            <?php esc_html_e('Save Entry', 'yaml-custom-fields'); ?>
          </button>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . urlencode($yaml_cf_type_slug))); ?>" class="button button-large">
            <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
          </a>
        </p>
      </form>
    <?php endif; ?>
  </div>
</div>
