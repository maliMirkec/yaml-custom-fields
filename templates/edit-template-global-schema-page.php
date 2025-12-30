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
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php echo esc_html($template_name); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Edit template global schema - shared data for all posts using this template', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-intro">
      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button">
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <?php esc_html_e('Back to Templates', 'yaml-custom-fields'); ?>
        </a>
        <?php if (!empty($template_global_schema)) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-template-global&template=' . urlencode($template))); ?>" class="button button-secondary" style="margin-left: 10px;">
          <span class="dashicons dashicons-admin-generic"></span>
          <?php esc_html_e('Manage Template Global Data', 'yaml-custom-fields'); ?>
        </a>
        <?php endif; ?>
      </p>
      <p><strong><?php esc_html_e('Template File:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($template); ?></code></p>
      <p><?php esc_html_e('Template global schema fields can be shared across all posts/pages using this template. The data is the same for all content using this template.', 'yaml-custom-fields'); ?></p>
      <p><?php esc_html_e('Users can choose to include these fields in individual posts via the "Include Template Global" checkbox in the post editor.', 'yaml-custom-fields'); ?></p>
    </div>

    <form method="post" action="" autocomplete="off">
      <?php wp_nonce_field('yaml_cf_save_template_global_schema', 'yaml_cf_save_template_global_schema_nonce'); ?>
      <input type="hidden" name="template" value="<?php echo esc_attr($template); ?>" />

      <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
        <p><?php esc_html_e('Enter your YAML schema below:', 'yaml-custom-fields'); ?></p>
        <textarea name="template_global_schema" id="yaml-cf-template-global-schema-editor" rows="20" class="large-text code" style="font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.6; width: 100%; border: 1px solid #ddd; padding: 10px;"><?php echo esc_textarea($template_global_schema); ?></textarea>
      </div>

      <p class="submit" style="margin-top: 20px;">
        <button type="submit" class="button button-primary button-large">
          <?php esc_html_e('Save Template Global Schema', 'yaml-custom-fields'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button button-large">
          <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
        </a>
      </p>
    </form>

    <div class="yaml-cf-schema-examples">
      <h2><?php esc_html_e('Template Global Schema Example', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Here\'s an example template global schema in YAML format:', 'yaml-custom-fields'); ?></p>
      <pre>fields:
  - name: default_background
    label: Default Background Image
    type: image
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
  - name: default_category
    label: Default Category
    type: taxonomy
    options:
      taxonomy: category</pre>

      <h3><?php esc_html_e('Supported Field Types', 'yaml-custom-fields'); ?></h3>
      <ul>
        <li><strong>boolean</strong> - Checkbox</li>
        <li><strong>string</strong> - Single line text (supports minlength, maxlength)</li>
        <li><strong>text</strong> - Multi-line textarea (supports maxlength)</li>
        <li><strong>rich-text</strong> - WordPress WYSIWYG editor</li>
        <li><strong>code</strong> - Code editor (supports language option)</li>
        <li><strong>number</strong> - Number input (supports min, max)</li>
        <li><strong>date</strong> - Date picker (supports time option for datetime)</li>
        <li><strong>select</strong> - Dropdown (supports multiple and values options)</li>
        <li><strong>taxonomy</strong> - WordPress categories, tags, or custom taxonomies (supports multiple and taxonomy options)</li>
        <li><strong>data_object</strong> - Reference to data object entries (supports multiple and object_type options)</li>
        <li><strong>image</strong> - WordPress media uploader for images</li>
        <li><strong>file</strong> - WordPress media uploader for any file</li>
        <li><strong>object</strong> - Nested fields group</li>
        <li><strong>block</strong> - Repeater field with multiple block types (list: true for repeatable)</li>
      </ul>
    </div>
  </div>
</div>
