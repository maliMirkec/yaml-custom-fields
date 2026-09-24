<?php

/**
 * Base Field Renderer
 * Common functionality for all field renderers
 */

namespace YamlCF\Rendering;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Helpers\HtmlHelper;

abstract class BaseFieldRenderer implements FieldRendererInterface {
  /**
   * Get field ID from field name and context
   *
   * @param string $field_name Field name
   * @param array $context Context
   * @return string Field ID
   */
  protected function getFieldId($field_name, $context) {
    $prefix = isset($context['prefix']) ? $context['prefix'] : '';
    $id_suffix = isset($context['id_suffix']) ? $context['id_suffix'] : '';
    $full_name = $prefix . $field_name;
    return 'ycf_' . str_replace(['[', ']'], ['_', ''], $full_name) . $id_suffix;
  }

  /**
   * Get the field path used inside the yaml_cf[...] input name
   *
   * Matches the convention in YAML_Custom_Fields::render_fields_flat(): a prefix
   * ending in '[' means a nested field, so "hero[" + "title" becomes
   * "hero][title" and the full input name becomes yaml_cf[hero][title].
   *
   * @param string $field_name Field name
   * @param array $context Context
   * @return string Field path
   */
  protected function getFieldPath($field_name, $context) {
    $prefix = isset($context['prefix']) ? $context['prefix'] : '';

    if ($prefix !== '' && substr($prefix, -1) === '[') {
      return substr($prefix, 0, -1) . '][' . $field_name;
    }

    return $prefix . $field_name;
  }

  /**
   * Get the input name for a field, honouring any nesting prefix
   *
   * @param string $field_name Field name
   * @param array $context Context
   * @return string Input name
   */
  protected function getInputName($field_name, $context) {
    return 'yaml_cf[' . $this->getFieldPath($field_name, $context) . ']';
  }

  /**
   * Refuse to render a field type this renderer has not implemented yet
   *
   * These renderers are registered but unreachable: nothing resolves
   * 'field_renderer' from the container, and the post editor renders fields
   * through YAML_Custom_Fields::render_fields_flat(). Wiring the container up
   * before a renderer is finished used to print an HTML comment and drop the
   * field's value silently, so say so loudly instead.
   *
   * @param string $type Field type that was asked for
   * @throws \LogicException When WP_DEBUG is on
   * @return void
   */
  protected function notImplemented($type) {
    $message = sprintf(
      '%s cannot render the "%s" field type yet. Field rendering lives in YAML_Custom_Fields::render_fields_flat(); do not resolve the field_renderer service until this renderer is finished.',
      static::class,
      $type
    );

    if (defined('WP_DEBUG') && WP_DEBUG) {
      // Escaped because an uncaught exception message is rendered to the page
      throw new \LogicException(esc_html($message));
    }

    do_action('yaml_cf_log_error', $message);
  }

  /**
   * Get field label
   *
   * @param array $field Field configuration
   * @return string Label
   */
  protected function getFieldLabel($field) {
    return isset($field['label']) ? $field['label'] : ucfirst($field['name']);
  }

  /**
   * Check if field is readonly
   *
   * @param array $context Context
   * @return bool
   */
  protected function isReadonly($context) {
    return isset($context['readonly']) && $context['readonly'];
  }

  /**
   * Render field label
   *
   * @param array $field Field configuration
   * @param array $context Context
   * @return void
   */
  protected function renderLabel($field, $context) {
    $field_id = $this->getFieldId($field['name'], $context);
    $label = $this->getFieldLabel($field);
    echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label>';
  }

  /**
   * Output HTML attributes
   *
   * @param array $attrs Attributes
   * @return void
   */
  protected function outputAttrs($attrs) {
    HtmlHelper::outputAttrs($attrs);
  }
}
