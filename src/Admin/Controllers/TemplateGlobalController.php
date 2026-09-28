<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for editing and managing template-specific global schemas and data
 */
class TemplateGlobalController extends AdminController {
  private $templateCache;
  private $schemaParser;

  public function __construct($templateCache, $schemaParser) {
    $this->templateCache = $templateCache;
    $this->schemaParser = $schemaParser;
  }

  /**
   * Render schema editor for template global schema
   */
  public function renderSchemaEditor() {
    $this->checkPermission();

    $template = RequestHelper::getParam('template');
    if (!$template) {
      wp_die(esc_html__('No template was chosen. Go back to the YAML Custom Fields page and pick one.', 'yaml-custom-fields'));
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

    // Save results are shown as queued admin notices (see NotificationManager)
    $success_message = '';

    // Pass messages to JavaScript via localization
    $page_config = [];
    if ($success_message) {
      $page_config['successMessage'] = $success_message;
    }
    if ($error_message) {
      $page_config['errorMessage'] = $error_message;
    }

    if (!empty($page_config)) {
      $this->localizePageInit($page_config);
    }

    // Load template
    $this->loadTemplate('edit-template-global-schema-page.php', compact(
      'template',
      'template_name',
      'template_global_schema'
    ));
  }

  /**
   * Render data manager for template global data
   */
  public function renderDataManager() {
    $this->checkPermission();

    $template = RequestHelper::getParam('template');
    if (!$template) {
      wp_die(esc_html__('No template was chosen. Go back to the YAML Custom Fields page and pick one.', 'yaml-custom-fields'));
    }

    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);
    $template_global_schema_yaml = isset($template_global_schemas[$template]) ? $template_global_schemas[$template] : '';
    $template_global_schema = $this->schemaParser->parse($template_global_schema_yaml);

    if (!$template_global_schema || !isset($template_global_schema['fields'])) {
      wp_safe_redirect(admin_url('admin.php?page=yaml-cf-edit-template-global-schema&template=' . rawurlencode($template)));
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

    // Save results are shown as queued admin notices (see NotificationManager)
    $success_message = '';

    // Localize the schema and which one it is, so that adding a section can ask
    // the server to render it (see YamlCF\Ajax\Handlers\RenderBlockItem)
    $this->localizeScript([
      'schema' => $template_global_schema,
      'schemaSource' => ['source' => 'template_global', 'key' => $template],
    ]);

    // Pass messages and form tracking config to JavaScript
    $page_config = [];
    if ($success_message) {
      $page_config['successMessage'] = $success_message;
    }
    $page_config['formTracking'] = [
      'enabled' => true,
      'container' => '#yaml-cf-template-global-data-form',
      'fieldsSelector' => '.yaml-cf-fields',
      'message' => __('There are changes that are not saved yet.', 'yaml-custom-fields'),
      'submitSelector' => '#yaml-cf-template-global-data-form',
      'storageKey' => 'originalTemplateGlobalDataFormData',
      'hasChangesKey' => 'hasTemplateGlobalDataFormChanges',
      'beforeUnloadMessage' => __('There are changes that are not saved yet. Leave anyway?', 'yaml-custom-fields'),
      'gutenbergSupport' => false,
      'captureDelay' => 500
    ];
    $this->localizePageInit($page_config);

    // Load template
    $this->loadTemplate('manage-template-global-data-page.php', compact(
      'template',
      'template_name',
      'template_global_schema',
      'template_global_data'
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
