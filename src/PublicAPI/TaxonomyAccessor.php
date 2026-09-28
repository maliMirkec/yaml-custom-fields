<?php

/**
 * Taxonomy Accessor
 * yaml_cf_get_term()
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TaxonomyAccessor {
  /** @var FieldAccessor */
  private $fields;

  /**
   * @param FieldAccessor $fields Reads the term IDs
   */
  public function __construct(FieldAccessor $fields) {
    $this->fields = $fields;
  }

  /**
   * A taxonomy field's term, or its terms when it holds several
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @param array|null $context_data Values of a block item to read from instead
   * @return \WP_Term|\WP_Term[]|null
   */
  public function getTerm($field_name, $post_id = null, $context_data = null) {
    $term_ids = $this->fields->getField($field_name, $post_id, $context_data);

    if (!$term_ids) {
      return null;
    }

    if (is_numeric($term_ids)) {
      $term = get_term($term_ids);
      return (!is_wp_error($term) && $term) ? $term : null;
    }

    if (is_array($term_ids)) {
      $terms = [];
      foreach ($term_ids as $term_id) {
        if (is_numeric($term_id)) {
          $term = get_term($term_id);
          if (!is_wp_error($term) && $term) {
            $terms[] = $term;
          }
        }
      }
      return !empty($terms) ? $terms : null;
    }

    return null;
  }
}
