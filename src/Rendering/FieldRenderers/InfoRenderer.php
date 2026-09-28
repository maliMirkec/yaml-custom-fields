<?php

/**
 * Info Renderer
 * A note for writers, with **bold**, _italic_ and [links](https://…)
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\MarkdownParser;
use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class InfoRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $info_text = isset($field['text']) ? $field['text'] : '';
    if (empty($info_text)) {
      return;
    }

    echo '<div class="yaml-cf-info-box">';
    \YamlCF\Helpers\Icons::render('status_info');
    echo '<div class="yaml-cf-info-content">';
    echo wp_kses_post(MarkdownParser::parse($info_text));
    echo '</div>';
    echo '</div>';
  }

  /**
   * A note reads the same inside a block. Without this it fell back to an empty
   * text input posting under the note's generated name.
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
