<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the main dashboard (templates, partials, global schema)
 */
class TemplateSchemaController extends AdminController {
  private $templateCache;
  private $schemaStorage;
  private $notificationManager;
  private $sorter;

  public function __construct($templateCache, $schemaStorage, $notificationManager, $sorter = null) {
    $this->templateCache = $templateCache;
    $this->schemaStorage = $schemaStorage;
    $this->notificationManager = $notificationManager;
    $this->sorter = $sorter ? $sorter : new \YamlCF\Template\TemplateSorter();
  }

  /**
   * Render the dashboard
   *
   * Notices (e.g. "Template list refreshed") are printed by the admin_notices hook.
   */
  public function render() {
    $this->checkPermission();

    $theme_files = $this->templateCache->getThemeTemplates();
    $template_settings = (array) get_option('yaml_cf_template_settings', []);
    $schemas = (array) get_option('yaml_cf_schemas', []);
    $template_global_schemas = (array) get_option('yaml_cf_template_global_schemas', []);

    // Most used first (standard page, blog post / header, footer, sidebar), then A–Z
    $theme_files['templates'] = $this->sorter->sortTemplates($theme_files['templates']);
    $theme_files['partials'] = $this->sorter->sortPartials($theme_files['partials']);

    $template_rows = [];
    foreach ($theme_files['templates'] as $template) {
      $template_rows[] = $this->buildRow($template, $template_settings, $schemas, $template_global_schemas);
    }

    $partial_rows = [];
    foreach ($theme_files['partials'] as $partial) {
      $partial_rows[] = $this->buildRow($partial, $template_settings, $schemas, []);
    }

    $global_schema = get_option('yaml_cf_global_schema', '');
    $global_field_count = $this->countFields($global_schema);

    // First-run guidance is shown until the first schema exists
    $show_getting_started = count(array_filter($schemas)) === 0 && $global_field_count === 0;

    $this->localizePageInit(['hasRefreshButton' => true]);

    $this->loadTemplate('admin-page.php', compact(
      'template_rows',
      'partial_rows',
      'global_field_count',
      'show_getting_started'
    ));
  }

  /**
   * Build the status of one template or partial row
   *
   * @param array $file Template info from the scanner ('file', 'name')
   * @param array $template_settings Enabled flags keyed by file
   * @param array $schemas YAML schemas keyed by file
   * @param array $template_global_schemas Shared (template global) YAML schemas keyed by file
   * @return array Row data
   */
  private function buildRow($file, $template_settings, $schemas, $template_global_schemas) {
    $key = $file['file'];
    return [
      'file' => $key,
      'name' => $file['name'],
      'enabled' => !empty($template_settings[$key]),
      'field_count' => $this->countFields(isset($schemas[$key]) ? $schemas[$key] : ''),
      'has_schema' => !empty($schemas[$key]),
      'shared_field_count' => $this->countFields(isset($template_global_schemas[$key]) ? $template_global_schemas[$key] : ''),
      'has_shared_schema' => !empty($template_global_schemas[$key]),
    ];
  }

  /**
   * Count the top-level fields defined by a YAML schema
   *
   * @param string $yaml YAML schema
   * @return int Number of fields (0 when empty or invalid)
   */
  private function countFields($yaml) {
    if (!is_string($yaml) || trim($yaml) === '') {
      return 0;
    }
    $parsed = $this->schemaStorage->parseSchema($yaml);
    if (!is_array($parsed) || !isset($parsed['fields']) || !is_array($parsed['fields'])) {
      return 0;
    }

    // Section breaks, row breaks and info boxes hold no content, so they are not fields
    $decorative = ['section', 'break', 'info'];
    $count = 0;
    foreach ($parsed['fields'] as $field) {
      if (is_array($field) && isset($field['type']) && in_array($field['type'], $decorative, true)) {
        continue;
      }
      // Shorthand ("- info: text" / "- section: Title" / "- break:") has no type
      if (is_array($field) && !isset($field['type']) && !isset($field['name'])
        && (array_key_exists('info', $field)
          || array_key_exists('section', $field)
          || array_key_exists('break', $field))) {
        continue;
      }
      $count++;
    }

    return $count;
  }
}
