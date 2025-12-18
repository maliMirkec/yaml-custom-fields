<?php
/**
 * Info Field Renderer
 * Renders read-only info banners with markdown support
 */

namespace YamlCF\Rendering\FieldRenderers;

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Helpers\MarkdownParser;

class InfoRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    // Info fields don't use labels
    $info_text = isset($field['text']) ? $field['text'] : '';

    if (!empty($info_text)) {
      echo '<div class="yaml-cf-info-box">';
      echo '<span class="dashicons dashicons-info"></span>';
      echo '<div class="yaml-cf-info-content">';
      echo MarkdownParser::parse($info_text);
      echo '</div>';
      echo '</div>';
    }
  }
}
