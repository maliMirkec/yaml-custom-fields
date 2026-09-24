<?php

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accessor for global custom fields
 */
class GlobalFieldAccessor {
  use UnimplementedAccessor;

  private $globalDataRepository;
  private $schemaStorage;

  public function __construct($globalDataRepository, $schemaStorage) {
    $this->globalDataRepository = $globalDataRepository;
    $this->schemaStorage = $schemaStorage;
  }

  /**
   * Get a global field value
   *
   * @param string $field_name Field name
   * @return mixed Field value
   */
  public function getField($field_name) {
    $this->notImplemented('getField');
  }

  /**
   * Get all global fields
   *
   * @return array All global field values
   */
  public function getFields() {
    $this->notImplemented('getFields');
  }

  /**
   * Check if a global field exists
   *
   * @param string $field_name Field name
   * @return bool True if field exists
   */
  public function hasField($field_name) {
    $this->notImplemented('hasField');
  }
}
