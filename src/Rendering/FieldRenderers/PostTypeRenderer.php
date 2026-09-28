<?php

/**
 * Post Type Renderer
 * A dropdown of the site's public post types
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class PostTypeRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    echo '<select name="yaml_cf[' . esc_attr($state['field_name']) . ']" id="' . esc_attr($state['field_id']) . '" class="regular-text">';
    $this->printOptions($field, $value);
    echo '</select>';
  }

  public function renderInBlock($field, $value, $state) {
    echo '<select name="' . esc_attr($this->blockInputName($state)) . '" id="' . esc_attr($state['field_id']) . '" class="regular-text"' . disabled($state['readonly'], true, false) . '>';
    $this->printOptions($field, $value);
    echo '</select>';
  }

  /**
   * @param array $field Field definition
   * @param mixed $value Stored value
   * @return void
   */
  private function printOptions($field, $value) {
    echo '<option value="">' . esc_html(sprintf(
      /* translators: %s: field label */
      __('-- Select %s --', 'yaml-custom-fields'),
      $field['label']
    )) . '</option>';

    foreach (get_post_types(['public' => true], 'objects') as $post_type) {
      $selected = ($value === $post_type->name) ? 'selected' : '';
      echo '<option value="' . esc_attr($post_type->name) . '" ' . esc_attr($selected) . '>' . esc_html($post_type->label) . '</option>';
    }
  }
}
