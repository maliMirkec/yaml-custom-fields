<?php
namespace YamlCF\Admin\Controllers;

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
