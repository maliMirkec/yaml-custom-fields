<?php

/**
 * Number Renderer
 * A number input, with the schema's min and max
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class NumberRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $this->printInput($field, $value, 'yaml_cf[' . $state['field_name'] . ']', $state['field_id'], false);
  }

  public function renderInBlock($field, $value, $state) {
    $this->printInput($field, $value, $this->blockInputName($state), $state['field_id'], $state['readonly']);
  }

  /**
   * @param array $field Field definition
   * @param mixed $value Stored value
   * @param string $name Input name
   * @param string $id Element ID
   * @param bool $disabled Whether to print the disabled attribute
   * @return void
   */
  private function printInput($field, $value, $name, $id, $disabled) {
    $options = isset($field['options']) ? $field['options'] : [];
    $attrs = [
      'type' => 'number',
      'name' => $name,
      'id' => $id,
      'value' => $value,
      'class' => 'small-text',
    ];
    if (isset($options['min'])) {
      $attrs['min'] = intval($options['min']);
    }
    if (isset($options['max'])) {
      $attrs['max'] = intval($options['max']);
    }
    if ($disabled) {
      $attrs['disabled'] = 'disabled';
    }
    echo '<input';
    $this->outputAttrs($attrs);
    echo ' />';
  }
}
