<?php

/**
 * Section Renderer
 * A section break that could not become a wrapper
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class SectionRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    // Sections are normally turned into wrappers by FieldRenderer::renderFields().
    // One that reaches here (inside a block, say) degrades to a plain break.
    echo '<hr class="yaml-cf-field-group-rule" />';
    if (!empty($field['label'])) {
      echo '<h4 class="yaml-cf-field-group-fallback-title">' . esc_html($field['label']) . '</h4>';
    }
    if (!empty($field['description'])) {
      echo '<p class="yaml-cf-field-group-fallback-description">' . esc_html($field['description']) . '</p>';
    }
  }

  /**
   * Inside a block a section break can only be a rule, as at the top level.
   * Without this it fell back to an empty text input.
   *
   * @param array $field Field definition
   * @param mixed $value Unused: this field holds no value
   * @param array $state Block field state
   * @return void
   */
  public function renderInBlock($field, $value, $state) {
    $this->render($field, $value, $state);
  }
}
