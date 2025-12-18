<?php
/**
 * Base Field Renderer
 * Common functionality for all field renderers
 */

namespace YamlCF\Rendering;

use YamlCF\Helpers\HtmlHelper;

abstract class BaseFieldRenderer implements FieldRendererInterface {
  /**
   * Get field ID from field name and context
   *
   * @param string $field_name Field name
   * @param array $context Context
   * @return string Field ID
   */
  protected function getFieldId($field_name, $context) {
    $prefix = isset($context['prefix']) ? $context['prefix'] : '';
    $id_suffix = isset($context['id_suffix']) ? $context['id_suffix'] : '';
    $full_name = $prefix . $field_name;
    return 'ycf_' . str_replace(['[', ']'], ['_', ''], $full_name) . $id_suffix;
  }

  /**
   * Get field label
   *
   * @param array $field Field configuration
   * @return string Label
   */
  protected function getFieldLabel($field) {
    return isset($field['label']) ? $field['label'] : ucfirst($field['name']);
  }

  /**
   * Check if field is readonly
   *
   * @param array $context Context
   * @return bool
   */
  protected function isReadonly($context) {
    return isset($context['readonly']) && $context['readonly'];
  }

  /**
   * Render field label
   *
   * @param array $field Field configuration
   * @param array $context Context
   * @return void
   */
  protected function renderLabel($field, $context) {
    $field_id = $this->getFieldId($field['name'], $context);
    $label = $this->getFieldLabel($field);
    echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
  }

  /**
   * Output HTML attributes
   *
   * @param array $attrs Attributes
   * @return void
   */
  protected function outputAttrs($attrs) {
    HtmlHelper::outputAttrs($attrs);
  }
}
