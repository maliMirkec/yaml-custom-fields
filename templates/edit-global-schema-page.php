<?php
/**
 * Edit Global Schema Page Template
 * File: templates/edit-global-schema-page.php
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
          <h1><?php esc_html_e('Global Schema', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Define reusable fields with global data', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-intro">
      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button">
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <?php esc_html_e('Back to Templates', 'yaml-custom-fields'); ?>
        </a>
        <?php if (!empty($global_schema)) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-global-data')); ?>" class="button button-secondary" style="margin-left: 10px;">
          <span class="dashicons dashicons-admin-generic"></span>
          <?php esc_html_e('Manage Global Data', 'yaml-custom-fields'); ?>
        </a>
        <?php endif; ?>
      </p>
      <p><?php esc_html_e('Global schema fields can be reused across multiple templates. The data is shared everywhere (not per-post).', 'yaml-custom-fields'); ?></p>
      <p><?php esc_html_e('After defining the global schema, enable it for specific templates in the template settings.', 'yaml-custom-fields'); ?></p>
    </div>

    <form method="post" action="">
      <?php wp_nonce_field('yaml_cf_save_global_schema', 'yaml_cf_save_global_schema_nonce'); ?>

      <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
        <p><?php esc_html_e('Enter your YAML schema below:', 'yaml-custom-fields'); ?></p>
        <textarea name="global_schema" id="yaml-cf-global-schema-editor" rows="20" class="large-text code" style="font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.6; width: 100%; border: 1px solid #ddd; padding: 10px;"><?php echo esc_textarea($global_schema); ?></textarea>
      </div>

      <p class="submit" style="margin-top: 20px;">
        <button type="submit" class="button button-primary button-large">
          <?php esc_html_e('Save Global Schema', 'yaml-custom-fields'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button button-large">
          <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
        </a>
      </p>
    </form>

    <div class="yaml-cf-schema-examples">
      <h2><?php esc_html_e('Global Schema Example', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Here\'s an example global schema in YAML format:', 'yaml-custom-fields'); ?></p>
      <pre>fields:
  - name: site_background
    label: Site Background Image
    type: image
  - name: announcement_bar
    label: Announcement Bar Text
    type: string
    options:
      maxlength: 200
  - name: site_settings
    label: Site Settings
    type: object
    fields:
      - name: show_header
        label: Show Header
        type: boolean
      - name: header_style
        label: Header Style
        type: select
        options:
          values:
            - value: minimal
              label: Minimal
            - value: full
              label: Full Width
  - name: global_sponsor
    label: Global Sponsor
    type: data_object
    options:
      object_type: companies</pre>

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
