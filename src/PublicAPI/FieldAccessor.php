<?php

/**
 * Field Accessor
 * yaml_cf_get_field(), yaml_cf_get_fields() and yaml_cf_has_field()
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads a field from a post, from a site part ('partial:header.php'), or from
 * the values of one block item when a theme loops over a block field.
 */
class FieldAccessor {
  const PARTIAL_PREFIX = 'partial:';

  /** @var FieldResolver */
  private $resolver;

  /**
   * @param FieldResolver $resolver Works out a post's values
   */
  public function __construct(FieldResolver $resolver) {
    $this->resolver = $resolver;
  }

  /**
   * A field's value, as the theme should see it
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @param array|null $context_data Values of a block item to read from instead
   * @return mixed The value, or null when the field has none
   */
  public function getField($field_name, $post_id = null, $context_data = null) {
    $value = $this->getFieldUnfiltered($field_name, $post_id, $context_data);

    /**
     * Filter a field's value on the way out to the theme.
     *
     * Runs for every way a value can be found: post data, template global data,
     * site details and block context alike, and so for every typed accessor
     * (images, files, terms and so on) as well.
     *
     * @param mixed $value The value, or null when the field has none.
     * @param string $field_name The field's name.
     * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post.
     * @param array|null $context_data Block data the value was read from, if any.
     */
    return apply_filters('yaml_cf_field_value', $value, $field_name, $post_id, $context_data);
  }

  /**
   * A field's value, before the yaml_cf_field_value filter runs
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @param array|null $context_data Values of a block item to read from instead
   * @return mixed The value, or null
   */
  public function getFieldUnfiltered($field_name, $post_id = null, $context_data = null) {
    if (is_array($context_data)) {
      $value = isset($context_data[$field_name]) ? $context_data[$field_name] : null;
      // A block item carries no schema of its own here, so any marked value is decoded
      return \YamlCF\Helpers\CodeFieldCodec::maybeDecode($value);
    }

    if ($this->isPartial($post_id)) {
      $partial_file = substr($post_id, strlen(self::PARTIAL_PREFIX));
      $partial_data = get_option('yaml_cf_partial_data', []);

      if (isset($partial_data[$partial_file][$field_name])) {
        // Site part schemas are kept with the template schemas
        $partial_schemas = get_option('yaml_cf_schemas', []);
        $schema = isset($partial_schemas[$partial_file]) ? $partial_schemas[$partial_file] : null;

        return $this->resolver->autoDecodeIfCodeField($partial_data[$partial_file][$field_name], $schema, $field_name);
      }

      return null;
    }

    $post_id = $this->currentPostIfNull($post_id);
    if (!$post_id) {
      return null;
    }

    $fields = $this->resolver->resolvePostFields($post_id);

    return array_key_exists($field_name, $fields) ? $fields[$field_name] : null;
  }

  /**
   * Every field of a post or site part
   *
   * Not passed through yaml_cf_field_value, which is per field.
   *
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @return array Values keyed by field name
   */
  public function getFields($post_id = null) {
    if ($this->isPartial($post_id)) {
      $partial_file = substr($post_id, strlen(self::PARTIAL_PREFIX));
      $partial_data = get_option('yaml_cf_partial_data', []);
      $data = isset($partial_data[$partial_file]) ? $partial_data[$partial_file] : [];

      $partial_schemas = get_option('yaml_cf_schemas', []);
      $schema = isset($partial_schemas[$partial_file]) ? $partial_schemas[$partial_file] : null;

      return $this->resolver->decodeAll($data, $schema);
    }

    $post_id = $this->currentPostIfNull($post_id);
    if (!$post_id) {
      return [];
    }

    return $this->resolver->resolvePostFields($post_id);
  }

  /**
   * Whether a field has a value (after the filter)
   *
   * What the documentation has always said, and what a theme means by
   * `if (ycf_has_field('cta')) { ... }`: a field cleared in the editor is
   * stored as '' (or [] for a list), and counting that as a value printed the
   * markup around a value that was not there. An unticked box (false) and a
   * zero are values. The same rule the [ycf] shortcode uses for its default.
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @return bool
   */
  public function hasField($field_name, $post_id = null) {
    $value = $this->getField($field_name, $post_id);

    return $value !== null && $value !== '' && $value !== [];
  }

  /**
   * @param mixed $post_id Post ID or 'partial:<file>'
   * @return bool Whether it names a site part
   */
  private function isPartial($post_id) {
    return is_string($post_id) && strpos($post_id, self::PARTIAL_PREFIX) === 0;
  }

  /**
   * @param mixed $post_id Post ID, or null for the post in the loop
   * @return mixed
   */
  private function currentPostIfNull($post_id) {
    return $post_id === null ? get_the_ID() : $post_id;
  }
}
