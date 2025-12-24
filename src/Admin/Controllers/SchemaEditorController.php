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
      wp_die(esc_html__('No template specified.', 'yaml-custom-fields'));
    }

    $schemas = get_option('yaml_cf_schemas', []);
    $schema_yaml = isset($schemas[$template]) ? $schemas[$template] : '';

    // Check if there's a validation error and restore the invalid schema
    $error_message = '';
    if (RequestHelper::getParam('error') === '1') {
      $invalid_schema = get_transient('yaml_cf_invalid_schema_' . get_current_user_id());
      if ($invalid_schema !== false) {
        $schema_yaml = $invalid_schema;
        delete_transient('yaml_cf_invalid_schema_' . get_current_user_id());
      }

      $error_msg = RequestHelper::getParam('error_msg');
      if ($error_msg) {
        $error_message = $error_msg;
      } else {
        $error_message = __('Invalid YAML schema. Please check your syntax and try again.', 'yaml-custom-fields');
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

    // Check for success message
    $success_message = '';
    if (RequestHelper::getParam('saved') === '1') {
      $success_message = __('Schema saved successfully!', 'yaml-custom-fields');
    }

    // Pass messages to JavaScript (replaces inline scripts)
    $page_data = [];
    if (!empty($success_message)) {
      $page_data['successMessage'] = $success_message;
    }
    if (!empty($error_message)) {
      $page_data['errorMessage'] = $error_message;
    }
    if (!empty($page_data)) {
      $this->localizePageInit($page_data);
    }

    // Load template
    $this->loadTemplate('edit-schema-page.php', compact(
      'template',
      'template_name',
      'schema_yaml',
      'error_message',
      'success_message'
    ));
  }
}
