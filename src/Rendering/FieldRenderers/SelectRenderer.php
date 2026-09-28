<?php

/**
 * Select Renderer
 * A dropdown of the values the schema lists
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class SelectRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $multiple = isset($field['multiple']) && $field['multiple'];

    echo '<select name="yaml_cf[' . esc_attr($state['field_name']) . ']' . ($multiple ? '[]' : '') . '" id="' . esc_attr($state['field_id']) . '" ' . ($multiple ? 'multiple' : '') . '>';
    $this->printOptions($field, $value, $multiple);
    echo '</select>';
  }

  public function renderInBlock($field, $value, $state) {
    $multiple = isset($field['multiple']) && $field['multiple'];

    echo '<select name="' . esc_attr($this->blockInputName($state)) . ($multiple ? '[]' : '') . '" id="' . esc_attr($state['field_id']) . '" ' . ($multiple ? 'multiple' : '') . disabled($state['readonly'], true, false) . '>';
    $this->printOptions($field, $value, $multiple);
    echo '</select>';
  }

  /**
   * @param array $field Field definition
   * @param mixed $value Stored value
   * @param bool $multiple Whether several values can be chosen
   * @return void
   */
  private function printOptions($field, $value, $multiple) {
    $options = isset($field['options']) ? $field['options'] : [];

    // Check for values in options.values first, then fallback to root level values
    $values = [];
    if (isset($options['values']) && is_array($options['values'])) {
      $values = $options['values'];
    } elseif (isset($field['values'])) {
      $values = $field['values'];
    }

    echo '<option value="">' . esc_html__('-- Select --', 'yaml-custom-fields') . '</option>';

    if (!is_array($values) || empty($values)) {
      return;
    }

    foreach ($values as $option) {
      // Handle both array format and simple values
      if (is_array($option)) {
        $opt_value = isset($option['value']) ? $option['value'] : '';
        $opt_label = isset($option['label']) ? $option['label'] : $opt_value;
      } else {
        $opt_value = $option;
        $opt_label = $option;
      }

      // Stored values are strings; values written in the schema can be numbers
      if ($multiple && is_array($value)) {
        $is_selected = in_array($opt_value, $value, false);
      } else {
        // For single select, use loose comparison
        $is_selected = ($value == $opt_value && $value !== '');
      }
      $opt_attrs = [
        'value' => $opt_value,
        'selected' => $is_selected ? 'selected' : false,
      ];
      echo '<option';
      $this->outputAttrs($opt_attrs);
      echo '>' . esc_html($opt_label) . '</option>';
    }
  }
}
