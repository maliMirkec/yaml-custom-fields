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
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Site details', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Fill in the details used across the whole website', 'yaml-custom-fields'); ?></p>
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
        <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-edit-global-schema')); ?>" class="button button-secondary yaml-cf-button-gap">
          <span class="dashicons dashicons-admin-generic"></span>
          <?php esc_html_e('Site details schema', 'yaml-custom-fields'); ?>
        </a>
      </p>
      <p><?php esc_html_e('This content shows up on every page that uses these fields.', 'yaml-custom-fields'); ?></p>
    </div>

    <form id="yaml-cf-global-data-form" method="post">
      <?php wp_nonce_field('yaml_cf_save_global_data', 'yaml_cf_global_data_nonce'); ?>

      <div class="yaml-cf-fields yaml-cf-surface">
        <?php
        if (!empty($global_schema['fields'])) {
          $yaml_cf_renderer = \YamlCF\Core\Plugin::getInstance()->get('field_renderer');
          $yaml_cf_context = ['type' => 'global'];
          $yaml_cf_renderer->renderFields($global_schema['fields'], $global_data, '', $yaml_cf_context);
        } else {
          echo '<p>' . esc_html__('There are no site details yet. Create the schema first.', 'yaml-custom-fields') . '</p>';
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
