<?php

/**
 * String Renderer
 * A one-line text input
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class StringRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
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
      'type' => 'text',
      'name' => $name,
      'id' => $id,
      'value' => $value,
      'class' => 'regular-text',
    ];
    if (isset($options['minlength'])) {
      $attrs['minlength'] = intval($options['minlength']);
    }
    if (isset($options['maxlength'])) {
      $attrs['maxlength'] = intval($options['maxlength']);
    }
    if ($disabled) {
      $attrs['disabled'] = 'disabled';
    }
    echo '<input';
    $this->outputAttrs($attrs);
    echo ' />';
  }
}
