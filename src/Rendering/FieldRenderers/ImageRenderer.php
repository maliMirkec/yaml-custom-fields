<?php

/**
 * ImageRenderer - TODO: Complete implementation
 */
namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Rendering\BaseFieldRenderer;

class ImageRenderer extends BaseFieldRenderer {
  public function render($field, $value, $context) {
    // TODO: Phase 14 - Implement ImageRenderer
    // For now, delegates to old rendering logic
    echo '<!-- ImageRenderer: Placeholder - will be implemented in Phase 14 -->';
  }
}
