<?php

/**
 * Field Renderer Factory
 * Delegates rendering to specific field type renderers
 */

namespace YamlCF\Rendering;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class FieldRenderer {
  private $renderers = [];

  /**
   * Field types this factory can actually render end to end.
   *
   * Everything outside this list either has no renderer at all (boolean,
   * select, number, date) or has one that still refuses to render
   * (see BaseFieldRenderer::notImplemented()). Both cases throw rather than
   * quietly substituting a text input, which would drop the stored value.
   */
  const IMPLEMENTED = ['string', 'text', 'rich-text', 'code', 'info'];

  public function __construct() {
    // Registered but NOT yet usable as a whole: the post editor renders fields
    // through YAML_Custom_Fields::render_fields_flat(). Nothing resolves the
    // 'field_renderer' service, and render() below refuses any type that is not
    // in self::IMPLEMENTED so a partial migration cannot silently eat data.
    $this->renderers = [
      'string' => new FieldRenderers\StringRenderer(),
      'text' => new FieldRenderers\TextRenderer(),
      'rich-text' => new FieldRenderers\RichTextRenderer(),
      'code' => new FieldRenderers\CodeRenderer(),
      'info' => new FieldRenderers\InfoRenderer(),
      'image' => new FieldRenderers\ImageRenderer(),
      'file' => new FieldRenderers\FileRenderer(),
      'taxonomy' => new FieldRenderers\TaxonomyRenderer(),
      'post_type' => new FieldRenderers\PostTypeRenderer(),
      'data_object' => new FieldRenderers\DataObjectRenderer(),
      'object' => new FieldRenderers\ObjectRenderer(),
      'block' => new FieldRenderers\BlockRenderer(),
    ];
  }

  /**
   * Whether this factory can render a field type end to end
   *
   * @param string $type Field type
   * @return bool
   */
  public function supports($type) {
    return in_array($type, self::IMPLEMENTED, true);
  }

  /**
   * Render a field
   *
   * @param array $field Field configuration
   * @param mixed $value Field value
   * @param array $context Rendering context
   * @throws \LogicException When the field type has no finished renderer
   * @return void Outputs HTML
   */
  public function render($field, $value, $context = []) {
    $type = isset($field['type']) ? $field['type'] : 'string';

    if (!$this->supports($type)) {
      // No silent fallback: a text input in place of an image or a block field
      // submits a value that overwrites the real one.
      if (isset($this->renderers[$type])) {
        $this->renderers[$type]->render($field, $value, $context);
        return;
      }

      $message = sprintf(
        'YamlCF\Rendering\FieldRenderer has no renderer for the "%s" field type. Field rendering lives in YAML_Custom_Fields::render_fields_flat(); do not resolve the field_renderer service for this type.',
        $type
      );

      if (defined('WP_DEBUG') && WP_DEBUG) {
        // Escaped because an uncaught exception message is rendered to the page
        throw new \LogicException(esc_html($message));
      }

      do_action('yaml_cf_log_error', $message);
      return;
    }

    $this->renderers[$type]->render($field, $value, $context);
  }
}
