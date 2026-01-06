<?php
/**
 * Manage Global Data Page Template
 * File: templates/manage-global-data-page.php
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Global Data', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Manage shared data values', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-intro">
      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button">
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <?php esc_html_e('Back to Templates', 'yaml-custom-fields'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-global-schema')); ?>" class="button button-secondary" style="margin-left: 10px;">
          <span class="dashicons dashicons-edit"></span>
          <?php esc_html_e('Edit Global Schema', 'yaml-custom-fields'); ?>
        </a>
      </p>
      <p><?php esc_html_e('This data is shared across all templates that have global schema enabled. Changes here will affect all posts/pages using global fields.', 'yaml-custom-fields'); ?></p>
    </div>

    <form id="yaml-cf-global-data-form" method="post">
      <?php wp_nonce_field('yaml_cf_save_global_data', 'yaml_cf_global_data_nonce'); ?>

      <div class="yaml-cf-fields" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
        <?php
        if (!empty($global_schema['fields'])) {
          $plugin = YAML_Custom_Fields::get_instance();
          $yaml_cf_context = ['type' => 'global'];
          $plugin->render_schema_fields($global_schema['fields'], $global_data, '', $yaml_cf_context);
        } else {
          echo '<p>' . esc_html__('No fields defined in global schema.', 'yaml-custom-fields') . '</p>';
        }
        ?>
      </div>

      <p class="submit" style="margin-top: 20px;">
        <button type="submit" class="button button-primary button-large">
          <?php esc_html_e('Save Global Data', 'yaml-custom-fields'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button button-large">
          <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
        </a>
      </p>
    </form>
  </div>
</div>
