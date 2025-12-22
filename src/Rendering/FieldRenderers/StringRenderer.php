<?php

/**
 * String Field Renderer
 * Renders text input fields
 */

namespace YamlCF\Rendering\FieldRenderers;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Rendering\BaseFieldRenderer;

class StringRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    $field_id = $this->getFieldId($field['name'], $context);
    $prefix = isset($context['prefix']) ? $context['prefix'] : '';

    $this->renderLabel($field, $context);

    $options = isset($field['options']) ? $field['options'] : [];
    $attrs = [
      'type' => 'text',
      'name' => 'yaml_cf[' . $field['name'] . ']',
      'id' => $field_id,
      'value' => $value,
      'class' => 'regular-text',
    ];

    if (isset($options['minlength'])) {
      $attrs['minlength'] = intval($options['minlength']);
    }
    if (isset($options['maxlength'])) {
      $attrs['maxlength'] = intval($options['maxlength']);
    }

    echo '<input';
    $this->outputAttrs($attrs);
    echo ' />';
  }
}
