<?php

/**
 * Global Field Accessor
 * yaml_cf_get_global_field(), yaml_cf_get_global_fields() and yaml_cf_has_global_field()
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Site details: values that are the same on every page, read from anywhere,
 * header.php and footer.php included.
 */
class GlobalFieldAccessor {
  /** @var FieldResolver */
  private $resolver;

  /**
   * @param FieldResolver $resolver Decodes code fields against the schema
   */
  public function __construct(FieldResolver $resolver) {
    $this->resolver = $resolver;
  }

  /**
   * One of the site details
   *
   * @param string $field_name The field's name
   * @return mixed The value, or null when it has none
   */
  public function getField($field_name) {
    $global_data = get_option('yaml_cf_global_data', []);

    if (is_array($global_data) && isset($global_data[$field_name])) {
      return $this->resolver->autoDecodeIfCodeField($global_data[$field_name], get_option('yaml_cf_global_schema', ''), $field_name);
    }

    return null;
  }

  /**
   * All of the site details
   *
   * @return array Values keyed by field name
   */
  public function getFields() {
    $global_data = get_option('yaml_cf_global_data', []);
    $data = is_array($global_data) ? $global_data : [];

    return $this->resolver->decodeAll($data, get_option('yaml_cf_global_schema', ''));
  }

  /**
   * Whether one of the site details has a value
   *
   * @param string $field_name The field's name
   * @return bool
   */
  public function hasField($field_name) {
    return $this->getField($field_name) !== null;
  }
}
