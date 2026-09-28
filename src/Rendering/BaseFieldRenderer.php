<?php

/**
 * Base Field Renderer
 * What the field type renderers share
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\HtmlHelper;

abstract class BaseFieldRenderer implements FieldRendererInterface {
  /**
   * The renderer that owns this one: for nested fields, and the shared pieces
   * (picker, media lists, block items, schema parser, collections)
   *
   * @var FieldRenderer
   */
  protected $root;

  /**
   * @param FieldRenderer $root The renderer that owns this one
   */
  public function __construct(FieldRenderer $root) {
    $this->root = $root;
  }

  /**
   * Output HTML attributes, escaped
   *
   * @param array $attrs Attributes
   * @return void
   */
  protected function outputAttrs($attrs) {
    HtmlHelper::outputAttrs($attrs);
  }

  /**
   * The input name of a field inside a block item
   *
   * @param array $state Block field state
   * @return string yaml_cf[<block field>][<index>][<name>]
   */
  protected function blockInputName($state) {
    return 'yaml_cf[' . $state['parent'] . '][' . $state['index'] . '][' . $state['name'] . ']';
  }

  /**
   * The path inside yaml_cf[...] of a field inside a block item
   *
   * @param array $state Block field state
   * @return string <block field>][<index>][<name>
   */
  protected function blockFieldPath($state) {
    return $state['parent'] . '][' . $state['index'] . '][' . $state['name'];
  }
}
