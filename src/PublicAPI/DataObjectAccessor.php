<?php

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accessor for data object field data
 */
class DataObjectAccessor {
  use UnimplementedAccessor;

  private $fieldAccessor;
  private $dataObjectRepository;

  public function __construct($fieldAccessor, $dataObjectRepository) {
    $this->fieldAccessor = $fieldAccessor;
    $this->dataObjectRepository = $dataObjectRepository;
  }

  /**
   * Get data object entry for a field
   *
   * @param string $field_name Field name
   * @param int|null $post_id Post ID (null for current post)
   * @param mixed $context_data Additional context data
   * @return array|null Data object entry data
   */
  public function getDataObject($field_name, $post_id = null, $context_data = null) {
    $this->notImplemented('getDataObject');
  }

  /**
   * Get all entries for a data object type
   *
   * @param string $object_type Data object type slug
   * @return array All entries for the object type
   */
  public function getDataObjects($object_type) {
    $this->notImplemented('getDataObjects');
  }
}
