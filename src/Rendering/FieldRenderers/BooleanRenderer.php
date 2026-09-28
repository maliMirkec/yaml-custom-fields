<?php

/**
 * Boolean Renderer
 * A tick box
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class BooleanRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    // An unticked box sends nothing at all, so without this the field can
    // never be cleared: the save sees no value and leaves the old one in
    // place. Every other clearable field already pairs with a hidden empty
    // value this way; the tick box was the one that did not.
    if (!$state['readonly']) {
      echo '<input type="hidden" name="yaml_cf[' . esc_attr($state['field_name']) . ']" value="" />';
    }
    echo '<input type="checkbox" name="yaml_cf[' . esc_attr($state['field_name']) . ']" id="' . esc_attr($state['field_id']) . '" value="1" ' . checked($value, 1, false) . ' />';
  }

  public function renderInBlock($field, $value, $state) {
    echo '<input type="checkbox" name="' . esc_attr($this->blockInputName($state)) . '" id="' . esc_attr($state['field_id']) . '" value="1" ' . checked($value, 1, false) . disabled($state['readonly'], true, false) . ' />';
  }
}
