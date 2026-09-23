<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports plugin settings
 *
 * Schemas are validated like schemas saved in the editor, and all data is sanitized
 * against the schemas in effect on this site after the import.
 */
class SettingsImporter {
  private $sanitizer;
  private $validator;
  private $parser;
  private $cacheManager;

  public function __construct($sanitizer = null, $validator = null, $parser = null, $cacheManager = null) {
    $this->sanitizer = $sanitizer ?: new \YamlCF\Form\DataSanitizer();
    $this->validator = $validator ?: new \YamlCF\Schema\SchemaValidator();
    $this->parser = $parser ?: new \YamlCF\Schema\SchemaParser();
    $this->cacheManager = $cacheManager ?: new \YamlCF\Cache\CacheManager();
  }

  /**
   * Validate the structure of a settings import
   *
   * @param mixed $data Decoded import file
   * @return array ['valid' => bool, 'message' => string]
   */
  public function validate($data) {
    if (!is_array($data) || !isset($data['plugin']) || $data['plugin'] !== 'yaml-custom-fields') {
      return ['valid' => false, 'message' => __('This is not a YAML Custom Fields export file.', 'yaml-custom-fields')];
    }
    if (!isset($data['settings']) || !is_array($data['settings'])) {
      return ['valid' => false, 'message' => __('The file contains no settings.', 'yaml-custom-fields')];
    }
    return ['valid' => true, 'message' => ''];
  }

  /**
   * Import settings
   *
   * @param array $data Decoded import file
   * @param bool $merge Merge with existing settings instead of replacing them
   * @return array ['success' => bool, 'message' => string, 'errors' => string[]]
   */
  public function import($data, $merge = false) {
    $validation = $this->validate($data);
    if (!$validation['valid']) {
      return ['success' => false, 'message' => $validation['message'], 'errors' => []];
    }

    $settings = $data['settings'];
    $errors = [];

    // Template settings (template name => enabled flag)
    if (isset($settings['template_settings']) && is_array($settings['template_settings'])) {
      $template_settings = [];
      foreach ($settings['template_settings'] as $key => $value) {
        $template_settings[sanitize_text_field($key)] = (bool) $value;
      }
      if ($merge) {
        $template_settings = array_merge((array) get_option('yaml_cf_template_settings', []), $template_settings);
      }
      update_option('yaml_cf_template_settings', $template_settings);
    }

    if (isset($settings['schemas']) && is_array($settings['schemas'])) {
      $this->importSchemaMap($settings['schemas'], 'yaml_cf_schemas', $merge, $errors);
    }

    if (isset($settings['template_global_schemas']) && is_array($settings['template_global_schemas'])) {
      $this->importSchemaMap($settings['template_global_schemas'], 'yaml_cf_template_global_schemas', $merge, $errors);
    }

    if (isset($settings['global_schema']) && is_string($settings['global_schema'])) {
      $global_schema = sanitize_textarea_field($settings['global_schema']);
      $validation_result = $global_schema === '' ? ['valid' => true] : $this->validator->validate($global_schema);
      if ($validation_result['valid']) {
        update_option('yaml_cf_global_schema', $global_schema);
      } else {
        /* translators: %s: schema validation error */
        $errors[] = sprintf(__('Skipped the site details schema: %s', 'yaml-custom-fields'), $validation_result['message']);
      }
    }

    // Data is sanitized against the schemas now in effect on this site
    if (isset($settings['partial_data']) && is_array($settings['partial_data'])) {
      $this->importDataMap($settings['partial_data'], 'yaml_cf_partial_data', (array) get_option('yaml_cf_schemas', []), $merge);
    }

    if (isset($settings['template_global_data']) && is_array($settings['template_global_data'])) {
      $this->importDataMap($settings['template_global_data'], 'yaml_cf_template_global_data', (array) get_option('yaml_cf_template_global_schemas', []), $merge);
    }

    if (isset($settings['global_data']) && is_array($settings['global_data'])) {
      $global_data = $this->sanitizer->sanitize($settings['global_data'], $this->parse(get_option('yaml_cf_global_schema', '')));
      if ($merge) {
        $global_data = array_merge((array) get_option('yaml_cf_global_data', []), $global_data);
      }
      update_option('yaml_cf_global_data', $global_data);
    }

    $this->cacheManager->clearTemplateCache();
    $this->cacheManager->clearDataCaches();

    return [
      'success' => true,
      'message' => empty($errors)
        ? __('Settings imported.', 'yaml-custom-fields')
        /* translators: %s: list of import errors */
        : sprintf(__('Settings imported, but some parts were skipped: %s', 'yaml-custom-fields'), implode('; ', $errors)),
      'errors' => $errors,
    ];
  }

  /**
   * Import a map of template => YAML schema into an option, skipping invalid schemas
   *
   * @param array $schemas Imported schemas keyed by template
   * @param string $option Option name
   * @param bool $merge Merge with existing schemas
   * @param array $errors Collected error messages
   */
  private function importSchemaMap($schemas, $option, $merge, &$errors) {
    $result = [];
    foreach ($schemas as $template => $yaml) {
      $template = sanitize_text_field($template);
      if (!is_string($yaml)) {
        /* translators: %s: template file name */
        $errors[] = sprintf(__('Skipped the schema for %s: it is not valid text.', 'yaml-custom-fields'), $template);
        continue;
      }
      $yaml = sanitize_textarea_field($yaml);
      if ($yaml !== '') {
        $validation_result = $this->validator->validate($yaml, $template);
        if (!$validation_result['valid']) {
          /* translators: 1: template file name, 2: schema validation error */
          $errors[] = sprintf(__('Skipped the schema for %1$s: %2$s', 'yaml-custom-fields'), $template, $validation_result['message']);
          continue;
        }
      }
      $result[$template] = $yaml;
    }
    if ($merge) {
      $result = array_merge((array) get_option($option, []), $result);
    }
    update_option($option, $result);
  }

  /**
   * Import a map of template => field data into an option, sanitized per template schema
   *
   * @param array $data_map Imported data keyed by template
   * @param string $option Option name
   * @param array $schema_map YAML schemas keyed by template
   * @param bool $merge Merge with existing data
   */
  private function importDataMap($data_map, $option, $schema_map, $merge) {
    $result = [];
    foreach ($data_map as $template => $data) {
      $template = sanitize_text_field($template);
      $schema = !empty($schema_map[$template]) ? $this->parse($schema_map[$template]) : null;
      $result[$template] = $this->sanitizer->sanitize(is_array($data) ? $data : [], $schema);
    }
    if ($merge) {
      $result = array_merge((array) get_option($option, []), $result);
    }
    update_option($option, $result);
  }

  /**
   * Parse a YAML schema
   *
   * @param mixed $yaml YAML schema
   * @return array|null Parsed schema or null
   */
  private function parse($yaml) {
    if (!is_string($yaml) || $yaml === '') {
      return null;
    }
    $parsed = $this->parser->parse($yaml);
    return is_array($parsed) ? $parsed : null;
  }
}
