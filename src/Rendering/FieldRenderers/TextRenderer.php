<?php

/**
 * Text Renderer
 * A multi-line text box
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class TextRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $options = isset($field['options']) ? $field['options'] : [];
    $attrs = [
      'name' => 'yaml_cf[' . $state['field_name'] . ']',
      'id' => $state['field_id'],
      'rows' => 5,
      'class' => 'large-text',
    ];
    if (isset($options['maxlength'])) {
      $attrs['maxlength'] = intval($options['maxlength']);
    }
    echo '<textarea';
    $this->outputAttrs($attrs);
    echo '>' . esc_textarea($value) . '</textarea>';
  }

  public function renderInBlock($field, $value, $state) {
    $attrs = [
      'name' => $this->blockInputName($state),
      'id' => $state['field_id'],
      'rows' => 5,
      'class' => 'large-text',
    ];
    if ($state['readonly']) {
      $attrs['disabled'] = 'disabled';
    }
    echo '<textarea';
    $this->outputAttrs($attrs);
    echo '>' . esc_textarea($value) . '</textarea>';
  }
}
