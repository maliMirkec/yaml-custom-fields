<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for main template schema admin page
 */
class TemplateSchemaController extends AdminController {
  private $templateCache;
  private $schemaStorage;
  private $notificationManager;

  public function __construct($templateCache, $schemaStorage, $notificationManager) {
    $this->templateCache = $templateCache;
    $this->schemaStorage = $schemaStorage;
    $this->notificationManager = $notificationManager;
  }

  public function render() {
    $this->checkPermission();

    // Get notification message if any
    $notification = $this->notificationManager->get();

    // Get templates and data
    $theme_files = $this->templateCache->getThemeTemplates();
    $templates = $theme_files['templates'];
    $partials = $theme_files['partials'];
    $template_settings = get_option('yaml_cf_template_settings', []);
    $schemas = get_option('yaml_cf_schemas', []);
    $global_schema = get_option('yaml_cf_global_schema', '');

    // Parse global schema
    $global_schema_parsed = null;
    if (!empty($global_schema)) {
      $global_schema_parsed = $this->schemaStorage->parseSchema($global_schema);
    }

    $template_global_schemas = get_option('yaml_cf_template_global_schemas', []);

    // Pass page initialization data (replaces inline scripts)
    $page_data = [
      'hasRefreshButton' => true,
      'i18n' => [
        'refreshing' => __('Refreshing...', 'yaml-custom-fields'),
        'refreshFailed' => __('Failed to refresh template list', 'yaml-custom-fields')
      ]
    ];
    if (!empty($notification)) {
      $page_data['notification'] = $notification;
    }
    $this->localizePageInit($page_data);

    // Load template
    $this->loadTemplate('admin-page.php', compact(
      'notification',
      'templates',
      'partials',
      'template_settings',
      'schemas',
      'global_schema',
      'global_schema_parsed',
      'template_global_schemas'
    ));
  }
}
