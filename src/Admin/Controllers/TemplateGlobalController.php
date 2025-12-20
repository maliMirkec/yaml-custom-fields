<?php
namespace YamlCF\Admin\Controllers;

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for editing and managing template-specific global schemas and data
 */
class TemplateGlobalController extends AdminController {
  private $templateCache;
  private $schemaStorage;

  public function __construct($templateCache, $schemaStorage) {
    $this->templateCache = $templateCache;
    $this->schemaStorage = $schemaStorage;
  }

  /**
   * Render schema editor for template global schema
   */
  public function renderSchemaEditor() {
    $this->checkPermission();

    $template = RequestHelper::getParam('template');
    if (!$template) {
      wp_die(esc_html__('No template specified.', 'yaml-custom-fields'));
    }

    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
    $template_global_schema = isset($template_global_schemas[$template]) ? $template_global_schemas[$template] : '';

    // Check if there's a validation error
    $error_message = '';
    if (RequestHelper::getParam('error') === '1') {
      $invalid_schema = get_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id());
      if ($invalid_schema !== false) {
        $template_global_schema = $invalid_schema;
        delete_transient('yaml_cf_invalid_template_global_schema_' . get_current_user_id());
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
      $success_message = __('Template global schema saved successfully!', 'yaml-custom-fields');
    }

    // Load template
    $this->loadTemplate('edit-template-global-schema-page.php', compact(
      'template',
      'template_name',
      'template_global_schema',
      'error_message',
      'success_message'
    ));
  }

  /**
   * Render data manager for template global data
   */
  public function renderDataManager() {
    $this->checkPermission();

    $template = RequestHelper::getParam('template');
    if (!$template) {
      wp_die(esc_html__('No template specified.', 'yaml-custom-fields'));
    }

    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
    $template_global_schema_yaml = isset($template_global_schemas[$template]) ? $template_global_schemas[$template] : '';
    $template_global_schema = $this->schemaStorage->parseSchema($template_global_schema_yaml);

    if (!$template_global_schema || !isset($template_global_schema['fields'])) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-template-global-schema&template=' . urlencode($template)));
      exit;
    }

    $template_global_data_all = get_option('yaml_cf_template_global_data', []);
    $template_global_data = isset($template_global_data_all[$template]) ? $template_global_data_all[$template] : [];

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
      $success_message = __('Template global data saved successfully!', 'yaml-custom-fields');
    }

    // Localize schema data for JavaScript
    $this->localizeScript(['schema' => $template_global_schema]);

    // Load template
    $this->loadTemplate('manage-template-global-data-page.php', compact(
      'template',
      'template_name',
      'template_global_schema',
      'template_global_data',
      'success_message'
    ));
  }

  /**
   * Render method - determines which view to show based on action
   */
  public function render() {
    $action = RequestHelper::getParam('action', 'schema');

    if ($action === 'data') {
      $this->renderDataManager();
    } else {
      $this->renderSchemaEditor();
    }
  }
}
