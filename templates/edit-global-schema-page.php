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
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Schema', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Details used across the whole website', 'yaml-custom-fields'); ?></p>
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
        <?php if (!empty($global_schema)) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-manage-global-data')); ?>" class="button button-secondary yaml-cf-button-gap">
          <span class="dashicons dashicons-edit"></span>
          <?php esc_html_e('Site details', 'yaml-custom-fields'); ?>
        </a>
        <?php endif; ?>
      </p>
      <p><?php esc_html_e('Site details are used across the whole website, like a phone number or social links. They are filled in once.', 'yaml-custom-fields'); ?></p>
      <p><?php esc_html_e('Once the schema is saved, the details are filled in under "Site details".', 'yaml-custom-fields'); ?></p>
    </div>

    <form method="post" action="">
      <?php wp_nonce_field('yaml_cf_save_global_schema', 'yaml_cf_save_global_schema_nonce'); ?>

      <div class="yaml-cf-surface">
        <p><?php esc_html_e('Write the schema below:', 'yaml-custom-fields'); ?></p>
        <textarea name="global_schema" id="yaml-cf-global-schema-editor" rows="20" class="large-text code yaml-cf-code-editor"><?php echo esc_textarea($global_schema); ?></textarea>
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
  - name: site_background
    label: Site Background Image
    type: image
  - name: announcement_bar
    label: Announcement Bar Text
    type: string
    options:
      maxlength: 200

  - break: true

  - section: Layout
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
      object_type: companies
YAML;
    include YAML_CF_PLUGIN_DIR . 'templates/partials/schema-reference.php';
    ?>
  </div>
</div>
