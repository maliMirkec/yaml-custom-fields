<?php

/**
 * Field Renderer Interface
 * What every field type's renderer does
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * One implementation per field type. FieldRenderer prints everything a field
 * has in common (its wrapper, label, help text and "Copy code" button) and asks
 * the type's renderer for the input itself.
 */
interface FieldRendererInterface {
  /**
   * Print the input for a top-level or nested field
   *
   * @param array $field Field definition from the schema
   * @param mixed $value Stored value, or the field's default
   * @param array $state {
   *   @type string $field_name Path inside yaml_cf[...], e.g. "hero][title" for a nested field
   *   @type string $field_id Element ID
   *   @type string $field_label Label, falling back to the field name
   *   @type bool $readonly Whether the field is display-only
   *   @type array|null $context The render context the screen passed in
   * }
   * @return void Outputs HTML
   */
  public function render($field, $value, $state);
}
