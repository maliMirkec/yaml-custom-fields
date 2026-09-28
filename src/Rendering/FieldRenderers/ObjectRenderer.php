<?php

/**
 * Object Renderer
 * A group: fields nested under one name
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class ObjectRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    if (!isset($field['fields'])) {
      return;
    }

    echo '<div class="yaml-cf-object">';
    // A prefix ending in "[" nests the children: yaml_cf[group][child]
    $this->root->renderFields($field['fields'], is_array($value) ? $value : [], $state['field_name'] . '[', $state['context']);
    echo '</div>';
  }

  /**
   * A group inside a block: its fields post to yaml_cf[<block>][<index>][<group>][<child>]
   *
   * This used to fall back to a text input, and saving the page wrote the word
   * "Array" over everything the group held.
   *
   * The children get no "Copy code" snippets (they are read from the block's
   * $block, which the block field's own snippet explains), and a read-only copy
   * stays read-only through the disabled fieldset each child is wrapped in.
   *
   * @param array $field Field definition
   * @param mixed $value Stored value
   * @param array $state Block field state
   * @return void
   */
  public function renderInBlock($field, $value, $state) {
    if (!isset($field['fields'])) {
      return;
    }

    echo '<div class="yaml-cf-object">';
    $this->root->renderFields(
      $field['fields'],
      is_array($value) ? $value : [],
      $this->blockFieldPath($state) . '[',
      [
        'readonly' => $state['readonly'],
        'id_suffix' => isset($state['id_suffix']) ? $state['id_suffix'] : '',
      ]
    );
    echo '</div>';
  }
}
