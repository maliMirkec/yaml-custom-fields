<?php

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accessor for image field data with WordPress attachment metadata
 */
class ImageAccessor {
  private $fieldAccessor;

  public function __construct($fieldAccessor) {
    $this->fieldAccessor = $fieldAccessor;
  }

  /**
   * Get image field data with metadata
   *
   * @param string $field_name Field name
   * @param int|null $post_id Post ID (null for current post)
   * @param string $size Image size (default: 'full')
   * @param mixed $context_data Additional context data
   * @return array|null Image data with url, width, height, alt, etc.
   */
  public function getImage($field_name, $post_id = null, $size = 'full', $context_data = null) {
    // TODO: Phase 14 - Implement ImageAccessor::getImage
    return null;
  }
}
