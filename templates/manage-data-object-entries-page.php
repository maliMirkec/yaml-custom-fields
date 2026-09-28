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
// - $entries: The items on the page being shown (not every item)
// - $entries_total: How many items the collection holds in all
// - $entries_total_pages: Number of pages
// - $entries_paged: Which page is being shown

$yaml_cf_type_slug = $type_id;
$yaml_cf_entry_id = \YamlCF\Helpers\RequestHelper::getParam('entry', ''); // getParam() rather than getParamKey(): entry IDs can contain periods
$yaml_cf_action = \YamlCF\Helpers\RequestHelper::getParamKey('action', 'list');
$yaml_cf_type_name = $type_name;
$yaml_cf_schema = $schema;
$yaml_cf_entries = $entries;
$yaml_cf_renderer = \YamlCF\Core\Plugin::getInstance()->get('field_renderer');

// The list is paged, so every link away from it and back carries the page being
// worked on. Without this, editing or cancelling on page seven lands on page one.
$yaml_cf_paged_arg = $entries_paged > 1 ? '&paged=' . (int) $entries_paged : '';

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

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php echo esc_html($yaml_cf_type_name); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Add and edit the items in this collection', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <div class="yaml-cf-intro">
      <p>
        <?php if ($yaml_cf_action === 'list') : ?>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-data-objects')); ?>" class="button">
            <?php \YamlCF\Helpers\Icons::render('back'); ?>
            <?php esc_html_e('Back to Collections', 'yaml-custom-fields'); ?>
          </a>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-data-object-type&type_id=' . rawurlencode($yaml_cf_type_slug))); ?>" class="button button-secondary yaml-cf-button-gap">
            <?php \YamlCF\Helpers\Icons::render('schema'); ?>
            <?php esc_html_e('Edit schema', 'yaml-custom-fields'); ?>
          </a>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($yaml_cf_type_slug) . '&action=add')); ?>" class="button button-primary yaml-cf-button-gap">
            <?php \YamlCF\Helpers\Icons::render('add'); ?><?php esc_html_e('Add an item', 'yaml-custom-fields'); ?>
          </a>
        <?php else : ?>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($yaml_cf_type_slug) . $yaml_cf_paged_arg)); ?>" class="button">
            <?php \YamlCF\Helpers\Icons::render('back'); ?>
            <?php esc_html_e('Back to items', 'yaml-custom-fields'); ?>
          </a>
        <?php endif; ?>
      </p>
      <p><strong><?php esc_html_e('Collection:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($yaml_cf_type_slug); ?></code></p>
    </div>

    <?php if ($yaml_cf_action === 'list') : ?>
      <!-- List View -->
      <?php if (empty($yaml_cf_entries)) : ?>
        <div class="notice notice-info inline yaml-cf-notice">
          <p><?php esc_html_e('This collection is empty. Click "Add an item" to add the first one.', 'yaml-custom-fields'); ?></p>
        </div>
      <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th><?php esc_html_e('Item ID', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Content', 'yaml-custom-fields'); ?></th>
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
                      echo '<em>' . esc_html__('(more than text)', 'yaml-custom-fields') . '</em>';
                    }
                  }
                  ?>
                </td>
                <td>
                  <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($yaml_cf_type_slug) . '&action=edit&entry=' . rawurlencode($yaml_cf_entry_id) . $yaml_cf_paged_arg)); ?>" class="button">
                    <?php \YamlCF\Helpers\Icons::render('edit'); ?><?php esc_html_e('Edit', 'yaml-custom-fields'); ?>
                  </a>
                  <form method="post" class="yaml-cf-inline-form" data-confirm="<?php esc_attr_e('Delete this item? This cannot be undone.', 'yaml-custom-fields'); ?>">
                    <?php // One form per row: printed without wp_nonce_field()'s id, which would repeat on every row ?>
                    <input type="hidden" name="yaml_cf_delete_entry_nonce" value="<?php echo esc_attr(wp_create_nonce('yaml_cf_delete_entry')); ?>" />
                    <input type="hidden" name="entry_id" value="<?php echo esc_attr($yaml_cf_entry_id); ?>" />
                    <button type="submit" class="button button-link-delete"><?php \YamlCF\Helpers\Icons::render('delete'); ?><?php esc_html_e('Delete', 'yaml-custom-fields'); ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php
        // A collection of thousands is shown a page at a time now, rather than
        // every item being read to display fifty of them.
        $yaml_cf_entries_pagination = $entries_total_pages > 1 ? paginate_links([
          'base' => add_query_arg('paged', '%#%'),
          'format' => '',
          'current' => $entries_paged,
          'total' => $entries_total_pages,
          'prev_text' => __('&laquo;', 'yaml-custom-fields'),
          'next_text' => __('&raquo;', 'yaml-custom-fields'),
        ]) : '';
        ?>

        <?php if ($yaml_cf_entries_pagination) : ?>
          <div class="tablenav bottom">
            <div class="tablenav-pages">
              <span class="displaying-num">
                <?php
                echo esc_html(sprintf(
                  /* translators: %s: number of items */
                  _n('%s item', '%s items', $entries_total, 'yaml-custom-fields'),
                  number_format_i18n($entries_total)
                ));
                ?>
              </span>
              <?php echo wp_kses_post($yaml_cf_entries_pagination); ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php else : ?>
      <!-- Add/Edit Form -->
      <form method="post">
        <?php wp_nonce_field('yaml_cf_save_entry', 'yaml_cf_save_entry_nonce'); ?>
        <?php if ($yaml_cf_action === 'edit') : ?>
          <input type="hidden" name="entry_id" value="<?php echo esc_attr($yaml_cf_entry_id); ?>" />
        <?php endif; ?>

        <div class="yaml-cf-fields yaml-cf-surface">
          <?php
          if (!empty($yaml_cf_schema['fields'])) {
            $yaml_cf_context = ['type' => 'data_object', 'object_type' => $yaml_cf_type_slug];
            $yaml_cf_renderer->renderFields($yaml_cf_schema['fields'], $yaml_cf_entry_data, '', $yaml_cf_context);
          } else {
            echo '<p>' . esc_html__('This schema has no fields yet. Add fields to the schema first.', 'yaml-custom-fields') . '</p>';
          }
          ?>
        </div>

        <p class="submit yaml-cf-spaced-top">
          <button type="submit" class="button button-primary button-large">
            <?php esc_html_e('Save item', 'yaml-custom-fields'); ?>
          </button>
          <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($yaml_cf_type_slug) . $yaml_cf_paged_arg)); ?>" class="button button-large">
            <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
          </a>
        </p>
      </form>
    <?php endif; ?>
  </div>
</div>
