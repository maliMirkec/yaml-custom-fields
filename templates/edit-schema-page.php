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
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php echo esc_html($template_name); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Choose the fields for this template', 'yaml-custom-fields'); ?></p>
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
        // Check if this is a partial (has a slash or matches partial patterns)
        $yaml_cf_is_partial = (strpos($template, '/') !== false) ||
                      preg_match('/^(header|footer|sidebar|content|comments|searchform)/', basename($template));

        // Also check if schema exists before showing Manage Data button
        if ($yaml_cf_is_partial && !empty($schema_yaml)) :
          $yaml_cf_manage_data_url = admin_url('admin.php?page=yaml-cf-edit-partial&template=' . urlencode($template));
        ?>
        <a href="<?php echo esc_url($yaml_cf_manage_data_url); ?>" class="button button-secondary yaml-cf-button-gap">
          <span class="dashicons dashicons-edit"></span>
          <?php esc_html_e('Edit content', 'yaml-custom-fields'); ?>
        </a>
        <?php endif; ?>
      </p>
      <p><strong><?php esc_html_e('Theme file:', 'yaml-custom-fields'); ?></strong> <code><?php echo esc_html($template); ?></code></p>
      <p><?php esc_html_e('The schema says which fields pages with this template get. Each field needs a name, a label and a type.', 'yaml-custom-fields'); ?></p>
    </div>

    <form method="post" action="">
      <?php wp_nonce_field('yaml_cf_save_schema', 'yaml_cf_save_schema_nonce'); ?>
      <input type="hidden" name="template" value="<?php echo esc_attr($template); ?>" />

      <div class="yaml-cf-surface">
        <p><?php esc_html_e('Write the schema below:', 'yaml-custom-fields'); ?></p>
        <textarea name="schema" id="yaml-cf-schema-editor" rows="20" class="large-text code yaml-cf-code-editor"><?php echo esc_textarea($schema_yaml); ?></textarea>
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
  - section: Hero
  - name: hero_title
    label: Hero Title
    type: string
    required: true
    options:
      maxlength: 100
  - name: hero_image
    label: Hero Image
    type: image

  - break: true
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
  - section: Content
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
            type: text
YAML;
    include YAML_CF_PLUGIN_DIR . 'templates/partials/schema-reference.php';
    ?>
  </div>
</div>
