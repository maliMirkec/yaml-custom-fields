<?php

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accessor for file field data with WordPress attachment metadata
 */
class FileAccessor {
  use UnimplementedAccessor;

  private $fieldAccessor;

  public function __construct($fieldAccessor) {
    $this->fieldAccessor = $fieldAccessor;
  }

  /**
   * Get file field data with metadata
   *
   * @param string $field_name Field name
   * @param int|null $post_id Post ID (null for current post)
   * @param mixed $context_data Additional context data
   * @return array|null File data with url, filename, filesize, mime_type, etc.
   */
  public function getFile($field_name, $post_id = null, $context_data = null) {
    $this->notImplemented('getFile');
  }
}
