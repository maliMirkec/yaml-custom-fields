<?php

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accessor for post-specific custom fields
 */
class FieldAccessor {
  use UnimplementedAccessor;

  private $postDataRepository;
  private $templateResolver;
  private $schemaStorage;

  public function __construct($postDataRepository, $templateResolver, $schemaStorage) {
    $this->postDataRepository = $postDataRepository;
    $this->templateResolver = $templateResolver;
    $this->schemaStorage = $schemaStorage;
  }

  /**
   * Get a field value for a post
   *
   * @param string $field_name Field name
   * @param int|null $post_id Post ID (null for current post)
   * @param mixed $context_data Additional context data
   * @return mixed Field value
   */
  public function getField($field_name, $post_id = null, $context_data = null) {
    $this->notImplemented('getField');
  }

  /**
   * Get all fields for a post
   *
   * @param int|null $post_id Post ID (null for current post)
   * @return array All field values
   */
  public function getFields($post_id = null) {
    $this->notImplemented('getFields');
  }

  /**
   * Check if a field exists for a post
   *
   * @param string $field_name Field name
   * @param int|null $post_id Post ID (null for current post)
   * @return bool True if field exists
   */
  public function hasField($field_name, $post_id = null) {
    $this->notImplemented('hasField');
  }
}
