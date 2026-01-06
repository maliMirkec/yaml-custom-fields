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
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Documentation', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Complete guide to using YAML Custom Fields', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-docs-content">
      <?php include YAML_CF_PLUGIN_DIR . 'templates/docs-content.php'; ?>
    </div>
  </div>
</div>
