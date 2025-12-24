<?php
/**
 * Edit Schema Page Template
 * File: templates/edit-schema-page.php
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
          <p class="yaml-cf-tagline"><?php esc_html_e('Edit YAML schema for this template', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-intro">
      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button">
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <?php esc_html_e('Back to Templates', 'yaml-custom-fields'); ?>
        </a>
        <?php
        // Check if this is a partial (has a slash or matches partial patterns)
        $yaml_cf_is_partial = (strpos($template, '/') !== false) ||
                      preg_match('/^(header|footer|sidebar|content|comments|searchform)/', basename($template));

        // Also check if schema exists before showing Manage Data button
        if ($yaml_cf_is_partial && !empty($schema_yaml)) :
          $yaml_cf_manage_data_url = admin_url('admin.php?page=yaml-cf-edit-partial&template=' . urlencode($template));
        ?>
        <a href="<?php echo esc_url($yaml_cf_manage_data_url); ?>" class="button button-secondary" style="margin-left: 10px;">
          <span class="dashicons dashicons-admin-generic"></span>
          <?php esc_html_e('Manage Data', 'yaml-custom-fields'); ?>
        </a>
        <?php endif; ?>
      </p>
      <p><strong><?php esc_html_e('Template File:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($template); ?></code></p>
      <p><?php esc_html_e('Define the YAML schema that specifies which fields are available for this template.', 'yaml-custom-fields'); ?></p>
    </div>

    <form method="post" action="">
      <?php wp_nonce_field('yaml_cf_save_schema', 'yaml_cf_save_schema_nonce'); ?>
      <input type="hidden" name="template" value="<?php echo esc_attr($template); ?>" />

      <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1);">
        <p><?php esc_html_e('Enter your YAML schema below:', 'yaml-custom-fields'); ?></p>
        <textarea name="schema" id="yaml-cf-schema-editor" rows="20" class="large-text code" style="font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.6; width: 100%; border: 1px solid #ddd; padding: 10px;"><?php echo esc_textarea($schema_yaml); ?></textarea>
      </div>

      <p class="submit" style="margin-top: 20px;">
        <button type="submit" class="button button-primary button-large">
          <?php esc_html_e('Save Schema', 'yaml-custom-fields'); ?>
        </button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-custom-fields')); ?>" class="button button-large">
          <?php esc_html_e('Cancel', 'yaml-custom-fields'); ?>
        </a>
      </p>
    </form>

    <div class="yaml-cf-schema-examples">
      <h2><?php esc_html_e('Schema Example', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Here\'s an example schema in YAML format:', 'yaml-custom-fields'); ?></p>
      <pre>fields:
  - name: hero_title
    label: Hero Title
    type: string
    required: true
    options:
      maxlength: 100
  - name: hero_image
    label: Hero Image
    type: image
  - name: category
    label: Category
    type: taxonomy
    options:
      taxonomy: category
  - name: university
    label: University
    type: data_object
    options:
      object_type: universities
  - name: sponsors
    label: Sponsors
    type: data_object
    multiple: true
    options:
      object_type: companies
  - name: tags
    label: Tags
    type: taxonomy
    multiple: true
    options:
      taxonomy: post_tag
  - name: features
    label: Features
    type: block
    list: true
    blockKey: type
    blocks:
      - name: feature
        label: Feature Block
        fields:
          - name: title
            label: Title
            type: string
          - name: icon
            label: Icon
            type: image
          - name: description
            label: Description
            type: text</pre>

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
