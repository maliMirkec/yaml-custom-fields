<?php
/**
 * Edit Partial Data Page Template
 * File: templates/edit-partial-page.php
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
          <h1><?php echo esc_html($template_name); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Content for this part of the site', 'yaml-custom-fields'); ?></p>
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
        <?php
        $yaml_cf_edit_schema_url = admin_url('admin.php?page=yaml-cf-edit-schema&template=' . rawurlencode($template));
        ?>
        <a href="<?php echo esc_url($yaml_cf_edit_schema_url); ?>" class="button button-secondary yaml-cf-button-gap">
          <span class="dashicons dashicons-admin-generic"></span>
          <?php esc_html_e('Edit schema', 'yaml-custom-fields'); ?>
        </a>
      </p>
      <p><strong><?php esc_html_e('Theme file:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($template); ?></code></p>
      <p><?php esc_html_e('This content shows up everywhere this part appears on the website.', 'yaml-custom-fields'); ?></p>
    </div>

    <form id="yaml-cf-partial-form" method="post">
      <?php wp_nonce_field('yaml_cf_save_partial', 'yaml_cf_partial_nonce'); ?>
      <input type="hidden" name="template" value="<?php echo esc_attr($template); ?>" />

      <div class="yaml-cf-fields yaml-cf-surface">
        <?php
        if (!empty($schema['fields'])) {
          $yaml_cf_plugin = YAML_Custom_Fields::get_instance();
          $yaml_cf_context = ['type' => 'partial', 'template' => $template];
          $yaml_cf_plugin->render_schema_fields($schema['fields'], $template_data, '', $yaml_cf_context);
        } else {
          echo '<p>' . esc_html__('This schema has no fields yet. Add fields to the schema first.', 'yaml-custom-fields') . '</p>';
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
