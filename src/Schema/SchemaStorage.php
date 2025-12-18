<?php
/**
 * Schema Storage
 * Save and load YAML schemas using the repository layer
 */

namespace YamlCF\Schema;

use YamlCF\Data\Repositories\TemplateSettingsRepository;
use YamlCF\Data\Repositories\GlobalDataRepository;

class SchemaStorage {
  private $templateSettingsRepo;
  private $globalDataRepo;
  private $schemaParser;

  public function __construct($schemaParser = null) {
    $this->templateSettingsRepo = new TemplateSettingsRepository();
    $this->globalDataRepo = new GlobalDataRepository();
    $this->schemaParser = $schemaParser ?: new SchemaParser();
  }

  /**
   * Get schema for a template
   *
   * @param string $template Template file name
   * @return string YAML schema
   */
  public function getSchema($template) {
    $key = $template . '_schema';
    return $this->templateSettingsRepo->getSetting($key, '');
  }

  /**
   * Save schema for a template
   *
   * @param string $template Template file name
   * @param string $yaml YAML schema
   * @return bool Success
   */
  public function saveSchema($template, $yaml) {
    $key = $template . '_schema';
    return $this->templateSettingsRepo->saveSetting($key, $yaml);
  }

  /**
   * Delete schema for a template
   *
   * @param string $template Template file name
   * @return bool Success
   */
  public function deleteSchema($template) {
    $key = $template . '_schema';
    return $this->templateSettingsRepo->deleteSetting($key);
  }

  /**
   * Get global schema
   *
   * @return string YAML schema
   */
  public function getGlobalSchema() {
    return $this->globalDataRepo->getSchema();
  }

  /**
   * Save global schema
   *
   * @param string $yaml YAML schema
   * @return bool Success
   */
  public function saveGlobalSchema($yaml) {
    return $this->globalDataRepo->saveSchema($yaml);
  }

  /**
   * Get all schemas (from old storage location)
   *
   * @return array All schemas
   */
  public function getAllSchemas() {
    return $this->templateSettingsRepo->getSchemas();
  }

  /**
   * Save all schemas (to old storage location)
   *
   * @param array $schemas All schemas
   * @return bool Success
   */
  public function saveAllSchemas($schemas) {
    return $this->templateSettingsRepo->saveSchemas($schemas);
  }

  /**
   * Parse YAML schema string to array
   *
   * @param string $yaml YAML schema string
   * @return array|null Parsed schema or null on error
   */
  public function parseSchema($yaml) {
    if (empty($yaml)) {
      return null;
    }
    return $this->schemaParser->parse($yaml);
  }
}
