<?php
/**
 * Manage Template Global Data Page Template
 * File: templates/manage-template-global-data-page.php
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Shared content', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline">
            <?php
            /* translators: %s: template file name */
            printf(esc_html__('Template: %s', 'yaml-custom-fields'), '<strong>' . esc_html($template_name) . '</strong>');
            ?>
          </p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <div class="yaml-cf-intro">
      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button">
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <?php esc_html_e('Back to YAML Custom Fields', 'yaml-custom-fields'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-template-global&template=' . urlencode($template))); ?>" class="button button-secondary yaml-cf-button-gap">
          <span class="dashicons dashicons-admin-generic"></span>
          <?php esc_html_e('Shared fields schema', 'yaml-custom-fields'); ?>
        </a>
      </p>
      <p><strong><?php esc_html_e('Theme file:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($template); ?></code></p>
      <p><?php esc_html_e('This content shows up on every page that uses this template.', 'yaml-custom-fields'); ?></p>
    </div>

    <form id="yaml-cf-template-global-data-form" method="post" autocomplete="off">
      <?php wp_nonce_field('yaml_cf_save_template_global_data', 'yaml_cf_save_template_global_data_nonce'); ?>
      <input type="hidden" name="template" value="<?php echo esc_attr($template); ?>" />

      <div class="yaml-cf-fields yaml-cf-surface">
        <?php
        if (!empty($template_global_schema['fields'])) {
          $plugin = YAML_Custom_Fields::get_instance();
          // Add template-specific ID suffix to prevent browser autocomplete conflicts between templates
          $yaml_cf_template_id_suffix = '_' . sanitize_title($template);
          $yaml_cf_context = ['type' => 'template_global', 'template' => $template, 'id_suffix' => $yaml_cf_template_id_suffix];
          $plugin->render_schema_fields($template_global_schema['fields'], $template_global_data, '', $yaml_cf_context);
        } else {
          echo '<p>' . esc_html__('There are no shared fields yet. Create the schema first.', 'yaml-custom-fields') . '</p>';
        }
        ?>
      </div>

      <p class="submit yaml-cf-spaced-top">
        <button type="submit" class="button button-primary button-large">
          <?php esc_html_e('Save content', 'yaml-custom-fields'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button button-large">
          <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
        </a>
      </p>
    </form>
  </div>
</div>
