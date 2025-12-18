<?php
/**
 * Field Renderer Interface
 * All field renderers must implement this interface
 */

namespace YamlCF\Rendering;

interface FieldRendererInterface {
  /**
   * Render the field
   *
   * @param array $field Field configuration
   * @param mixed $value Current field value
   * @param array $context Rendering context (prefix, readonly, id_suffix, etc.)
   * @return void Outputs HTML directly
   */
  public function render($field, $value, $context);
}
