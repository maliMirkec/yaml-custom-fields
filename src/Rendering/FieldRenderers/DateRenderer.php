<?php

/**
 * Date Renderer
 * A date input, or date and time with "time: true"
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class DateRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
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
    $has_time = isset($options['time']) && $options['time'];
    $attrs = [
      'type' => $has_time ? 'datetime-local' : 'date',
      'name' => $name,
      'id' => $id,
      'value' => $value,
    ];
    if ($disabled) {
      $attrs['disabled'] = 'disabled';
    }
    echo '<input';
    $this->outputAttrs($attrs);
    echo ' />';
  }
}
