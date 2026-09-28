<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for editing template schemas
 */
class SchemaEditorController extends AdminController {
  private $templateCache;

  public function __construct($templateCache) {
    $this->templateCache = $templateCache;
  }

  public function render() {
    $this->checkPermission();

    // Get template parameter
    // NOTE: This is a read-only display page behind manage_options capability.
    // WordPress core doesn't require nonces for authenticated GET requests to admin pages.
    // The checkPermission() call above verifies current_user_can('manage_options').
    $template = RequestHelper::getParam('template');
    if (!$template) {
      wp_die(esc_html__('No template was chosen. Go back to the YAML Custom Fields page and pick one.', 'yaml-custom-fields'));
    }

    $schemas = get_option('yaml_cf_schemas', []);
    $schema_yaml = isset($schemas[$template]) ? $schemas[$template] : '';

    // A schema refused on save comes back into the editor
    if (RequestHelper::getParam('error') === '1') {
      $invalid_schema = get_transient('yaml_cf_invalid_schema_' . get_current_user_id());
      if ($invalid_schema !== false) {
        $schema_yaml = $invalid_schema;
        delete_transient('yaml_cf_invalid_schema_' . get_current_user_id());
      }
    }

    // Get template name from theme files
    $theme_files = $this->templateCache->getThemeTemplates();
    $template_name = $template;
    foreach ($theme_files['templates'] as $tmpl) {
      if ($tmpl['file'] === $template) {
        $template_name = $tmpl['name'];
        break;
      }
    }

    // Save results are shown as queued notices (see NotificationManager)

    // Load template
    $this->loadTemplate('edit-schema-page.php', compact(
      'template',
      'template_name',
      'schema_yaml'
    ));
  }
}
