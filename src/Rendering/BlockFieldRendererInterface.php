<?php

/**
 * Block Field Renderer Interface
 * A field type that has its own input inside a block (a section of a page)
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fields inside a block post to yaml_cf[<block field>][<index>][<name>] and are
 * made read-only with the disabled attribute rather than a disabled fieldset.
 * A type without this interface gets a plain text input inside a block; see
 * BlockItemRenderer::renderFallbackInput().
 */
interface BlockFieldRendererInterface {
  /**
   * Print the input for a field inside a block item
   *
   * @param array $field Field definition from the block's schema
   * @param mixed $value Stored value
   * @param array $state {
   *   @type string $parent Name of the block field the item belongs to
   *   @type int|string $index Position of the item
   *   @type string $name The field's own name
   *   @type string $field_id Element ID
   *   @type bool $readonly Whether the field is display-only
   *   @type string $id_suffix Appended to element IDs, so a second copy of the item does not clash
   * }
   * @return void Outputs HTML
   */
  public function renderInBlock($field, $value, $state);
}
