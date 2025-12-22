<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\RequestHelper;

/**
 * Controller for editing partial template data
 */
class PartialController extends AdminController {
  private $templateCache;
  private $schemaStorage;

  public function __construct($templateCache, $schemaStorage) {
    $this->templateCache = $templateCache;
    $this->schemaStorage = $schemaStorage;
  }

  public function render() {
    $this->checkPermission();

    $template = RequestHelper::getParam('template');
    if (!$template) {
      wp_die(esc_html__('No template specified.', 'yaml-custom-fields'));
    }

    $schemas = get_option('yaml_cf_schemas', []);

    if (!isset($schemas[$template])) {
      wp_die(esc_html__('No schema found for this template.', 'yaml-custom-fields'));
    }

    $schema_yaml = $schemas[$template];
    $schema = $this->schemaStorage->parseSchema($schema_yaml);

    if (!$schema || !isset($schema['fields'])) {
      wp_die(esc_html__('Invalid schema for this template.', 'yaml-custom-fields'));
    }

    // Get partial data
    $partial_data = get_option('yaml_cf_partial_data', []);
    $template_data = isset($partial_data[$template]) ? $partial_data[$template] : [];

    // Get template name from theme files
    $theme_files = $this->templateCache->getThemeTemplates();
    $template_name = $template;
    foreach ($theme_files['partials'] as $partial) {
      if ($partial['file'] === $template) {
        $template_name = $partial['name'];
        break;
      }
    }

    // Check for success message
    $success_message = '';
    if (RequestHelper::getParam('saved') === '1') {
      $success_message = __('Partial data saved successfully!', 'yaml-custom-fields');
    }

    // Localize schema data for JavaScript
    $this->localizeScript(['schema' => $schema]);

    // Load template
    $this->loadTemplate('edit-partial-page.php', compact(
      'template',
      'template_name',
      'schema',
      'template_data',
      'success_message'
    ));
  }
}
