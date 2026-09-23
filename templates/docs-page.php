<?php
/**
 * Documentation Page Template
 * File: templates/docs-page.php
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
          <h1><?php esc_html_e('Documentation', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('The whole plugin explained in plain words', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <div class="yaml-cf-docs-content">
      <?php include YAML_CF_PLUGIN_DIR . 'templates/docs-content.php'; ?>
    </div>
  </div>
</div>
