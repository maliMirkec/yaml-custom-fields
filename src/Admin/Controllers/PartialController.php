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
  private $schemaParser;

  public function __construct($templateCache, $schemaParser) {
    $this->templateCache = $templateCache;
    $this->schemaParser = $schemaParser;
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

    if (!isset($schemas[$template])) {
      wp_die(esc_html__('This template has no schema yet. Create one first.', 'yaml-custom-fields'));
    }

    $schema_yaml = $schemas[$template];
    $schema = $this->schemaParser->parse($schema_yaml);

    if (!$schema || !isset($schema['fields'])) {
      wp_die(esc_html__('The schema for this template has a mistake. Fix the schema first.', 'yaml-custom-fields'));
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

    // Localize the schema and which one it is, so that adding a section can ask
    // the server to render it (see YamlCF\Ajax\Handlers\RenderBlockItem)
    $this->localizeScript([
      'schema' => $schema,
      'schemaSource' => ['source' => 'partial', 'key' => $template],
    ]);

    // Pass page initialization data (replaces inline scripts)
    $page_data = [
      'formTracking' => [
        'enabled' => true,
        'container' => '#yaml-cf-partial-form',
        'fieldsSelector' => '.yaml-cf-fields',
        'message' => __('There are changes that are not saved yet.', 'yaml-custom-fields'),
        'submitSelector' => '#yaml-cf-partial-form',
        'storageKey' => 'originalPartialFormData',
        'hasChangesKey' => 'hasPartialFormChanges',
        'beforeUnloadMessage' => __('There are changes that are not saved yet. Leave anyway?', 'yaml-custom-fields'),
        'gutenbergSupport' => false,
        'captureDelay' => 500,
      ]
    ];
    $this->localizePageInit($page_data);

    // Load template
    $this->loadTemplate('edit-partial-page.php', compact(
      'template',
      'template_name',
      'schema',
      'template_data'
    ));
  }
}
