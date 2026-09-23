<?php
/**
 * Edit Template Global Schema Page Template
 * File: templates/edit-template-global-schema-page.php
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
          <p class="yaml-cf-tagline"><?php esc_html_e('Fields with one value for every page that uses this template', 'yaml-custom-fields'); ?></p>
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
        <?php if (!empty($template_global_schema)) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . urlencode($template))); ?>" class="button button-secondary yaml-cf-button-gap">
          <span class="dashicons dashicons-edit"></span>
          <?php esc_html_e('Shared content', 'yaml-custom-fields'); ?>
        </a>
        <?php endif; ?>
      </p>
      <p><strong><?php esc_html_e('Theme file:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($template); ?></code></p>
      <p><?php esc_html_e('Shared fields have one value for every page that uses this template. They are filled in once.', 'yaml-custom-fields'); ?></p>
      <p><?php esc_html_e('If a page also has the same field, the writer can tick "Use the shared value on this page" in the editor.', 'yaml-custom-fields'); ?></p>
    </div>

    <form method="post" action="" autocomplete="off">
      <?php wp_nonce_field('yaml_cf_save_template_global_schema', 'yaml_cf_save_template_global_schema_nonce'); ?>
      <input type="hidden" name="template" value="<?php echo esc_attr($template); ?>" />

      <div class="yaml-cf-surface">
        <p><?php esc_html_e('Write the schema below:', 'yaml-custom-fields'); ?></p>
        <textarea name="template_global_schema" id="yaml-cf-template-global-schema-editor" rows="20" class="large-text code yaml-cf-code-editor"><?php echo esc_textarea($template_global_schema); ?></textarea>
      </div>

      <p class="submit yaml-cf-spaced-top">
        <button type="submit" class="button button-primary button-large">
          <?php esc_html_e('Save schema', 'yaml-custom-fields'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button button-large">
          <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
        </a>
      </p>
    </form>

    <?php
    $yaml_cf_reference_example = <<<'YAML'
fields:
  - section: Branding
  - name: default_background
    label: Default Background Image
    type: image
  - break: true
  - name: page_layout_settings
    label: Page Layout Settings
    type: object
    fields:
      - name: show_sidebar
        label: Show Sidebar
        type: boolean
      - name: sidebar_position
        label: Sidebar Position
        type: select
        options:
          values:
            - value: left
              label: Left
            - value: right
              label: Right
  - section: Defaults
  - name: default_category
    label: Default Category
    type: taxonomy
    options:
      taxonomy: category
YAML;
    include YAML_CF_PLUGIN_DIR . 'templates/partials/schema-reference.php';
    ?>
  </div>
</div>
