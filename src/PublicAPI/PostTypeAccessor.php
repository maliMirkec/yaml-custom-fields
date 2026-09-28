<?php

/**
 * Post Type Accessor
 * yaml_cf_get_post_type()
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PostTypeAccessor {
  /** @var FieldAccessor */
  private $fields;

  /**
   * @param FieldAccessor $fields Reads the post type's name
   */
  public function __construct(FieldAccessor $fields) {
    $this->fields = $fields;
  }

  /**
   * A post_type field's post type
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @param array|null $context_data Values of a block item to read from instead
   * @return \WP_Post_Type|null
   */
  public function getPostType($field_name, $post_id = null, $context_data = null) {
    $post_type_slug = $this->fields->getField($field_name, $post_id, $context_data);

    if (!$post_type_slug || !is_string($post_type_slug)) {
      return null;
    }

    $post_type_object = get_post_type_object($post_type_slug);
    return $post_type_object ? $post_type_object : null;
  }
}
