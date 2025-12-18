<?php
/**
 * Field Renderer Factory
 * Delegates rendering to specific field type renderers
 */

namespace YamlCF\Rendering;

class FieldRenderer {
  private $renderers = [];

  public function __construct() {
    // Register all field type renderers
    // TODO: In Phase 14, these will be instantiated via dependency injection
    $this->renderers = [
      'string' => new FieldRenderers\StringRenderer(),
      'text' => new FieldRenderers\TextRenderer(),
      'rich-text' => new FieldRenderers\RichTextRenderer(),
      'code' => new FieldRenderers\CodeRenderer(),
      'info' => new FieldRenderers\InfoRenderer(),
      'number' => new FieldRenderers\StringRenderer(), // Uses same as string with type="number"
      'date' => new FieldRenderers\StringRenderer(), // Uses same as string with type="date"
      'boolean' => new FieldRenderers\StringRenderer(), // Simplified for now
      'select' => new FieldRenderers\StringRenderer(), // Simplified for now
      'image' => new FieldRenderers\ImageRenderer(),
      'file' => new FieldRenderers\FileRenderer(),
      'taxonomy' => new FieldRenderers\TaxonomyRenderer(),
      'post_type' => new FieldRenderers\PostTypeRenderer(),
      'data_object' => new FieldRenderers\DataObjectRenderer(),
      'object' => new FieldRenderers\ObjectRenderer(),
      'block' => new FieldRenderers\BlockRenderer(),
    ];
  }

  /**
   * Render a field
   *
   * @param array $field Field configuration
   * @param mixed $value Field value
   * @param array $context Rendering context
   * @return void Outputs HTML
   */
  public function render($field, $value, $context = []) {
    $type = isset($field['type']) ? $field['type'] : 'string';

    if (isset($this->renderers[$type])) {
      $this->renderers[$type]->render($field, $value, $context);
    } else {
      // Fallback to string renderer
      $this->renderers['string']->render($field, $value, $context);
    }
  }
}
