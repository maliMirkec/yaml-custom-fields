<?php

/**
 * Block Item Renderer
 * One section in a block field: its header, controls and fields
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Helpers\HtmlHelper;

class BlockItemRenderer {
  /** @var FieldRenderer */
  private $root;

  /**
   * @param FieldRenderer $root The renderer that owns this one
   */
  public function __construct(FieldRenderer $root) {
    $this->root = $root;
  }

  /**
   * Print one empty block item, for the "Add section" button
   *
   * The admin script used to build this markup itself, in parallel with
   * render(). The two drifted: taxonomy, post_type and data_object fields
   * rendered differently in a section added on the page than in one that came
   * with it. Rendering both here means there is only one implementation.
   *
   * @param array|null $schema Parsed schema holding the block field
   * @param string $field_name Name of the block field
   * @param string $block_type Which block type to add
   * @param int $index Position of the new block in the list
   * @param array|null $context Rendering context
   * @return bool Whether the block field was found (and something may have been printed)
   */
  public function renderNew($schema, $field_name, $block_type, $index, $context = null) {
    if (!is_array($schema) || empty($schema['fields']) || !is_array($schema['fields'])) {
      return false;
    }

    $field = null;
    foreach ($this->root->normalizer()->normalizeAll($schema['fields']) as $candidate) {
      if (isset($candidate['name']) && $candidate['name'] === $field_name) {
        $field = $candidate;
        break;
      }
    }

    if (!$field || !isset($field['type']) || $field['type'] !== 'block') {
      return false;
    }

    $block_key = isset($field['blockKey']) ? $field['blockKey'] : 'type';

    // render() returns without printing when the block type is unknown
    $this->render(
      $field,
      isset($field['blocks']) ? $field['blocks'] : [],
      [$block_key => $block_type],
      $index,
      $block_key,
      $context
    );

    return true;
  }

  /**
   * Print one block item
   *
   * @param array $field The block field's definition
   * @param array $blocks Block types the schema offers
   * @param array $block_data This item's stored values
   * @param int|string $index Position of the item
   * @param string $block_key Key that names the item's block type
   * @param array|null $context Rendering context
   * @return void
   */
  public function render($field, $blocks, $block_data, $index, $block_key, $context = null) {
    $block_type = isset($block_data[$block_key]) ? $block_data[$block_key] : '';
    $block_def = null;

    // Display-only in the shared value next to a page's own
    $readonly = ($context && is_array($context) && isset($context['readonly']) && $context['readonly']);

    // Keeps IDs unique when the same field is printed twice (dual fields)
    $id_suffix = ($context && is_array($context) && isset($context['id_suffix'])) ? $context['id_suffix'] : '';

    foreach ($blocks as $block) {
      if ($block['name'] === $block_type) {
        $block_def = $block;
        break;
      }
    }

    if (!$block_def) {
      return;
    }

    // Which field's text stands in for the block in its header, so that a page
    // of twenty sections does not read "Feature Block" twenty times
    $summary_field = $this->summaryField($block_def);
    $summary = '';
    if ($summary_field !== '' && isset($block_data[$summary_field]) && is_string($block_data[$summary_field])) {
      $summary = wp_trim_words($block_data[$summary_field], 12);
    }

    $fields_id = 'ycf_block_' . sanitize_html_class($field['name'] . '_' . $index) . $id_suffix;

    $this->printHeader($block_def, $block_type, $summary_field, $summary, $fields_id, $readonly);

    $hidden_attrs = [
      'type' => 'hidden',
      'name' => 'yaml_cf[' . $field['name'] . '][' . $index . '][' . $block_key . ']',
      'value' => $block_type,
    ];
    if ($readonly) {
      $hidden_attrs['disabled'] = 'disabled';
    }
    echo '<input';
    HtmlHelper::outputAttrs($hidden_attrs);
    echo ' />';

    if (isset($block_def['fields']) && is_array($block_def['fields'])) {
      echo '<div class="yaml-cf-block-fields" id="' . esc_attr($fields_id) . '">';

      // Ids and snippets are built from each field's name, so shorthand has to
      // be expanded first: an "- info:" inside a section has no name until then.
      foreach ($this->root->normalizer()->normalizeAll($block_def['fields']) as $block_field) {
        if (!is_array($block_field) || !isset($block_field['name'])) {
          continue;
        }

        $this->renderField($field, $block_field, $block_data, $index, $id_suffix, $readonly);
      }
      echo '</div>';
    }

    echo '</div>';
  }

  /**
   * Pick the field whose text stands in for a block in its header
   *
   * The schema can name one with "summaryField"; otherwise the first string or
   * text field is used, which is the title in nearly every block.
   *
   * @param array $block_def Block definition
   * @return string Field name, or '' when the block has no text field
   */
  public function summaryField($block_def) {
    if (!empty($block_def['summaryField']) && is_string($block_def['summaryField'])) {
      return $block_def['summaryField'];
    }

    if (empty($block_def['fields']) || !is_array($block_def['fields'])) {
      return '';
    }

    foreach ($block_def['fields'] as $block_field) {
      $type = isset($block_field['type']) ? $block_field['type'] : 'string';
      if (($type === 'string' || $type === 'text') && !empty($block_field['name'])) {
        return $block_field['name'];
      }
    }

    return '';
  }

  /**
   * The item's header: fold toggle, type, summary and the move/remove buttons
   *
   * Opens the item's wrapper, which render() closes.
   *
   * @return void
   */
  private function printHeader($block_def, $block_type, $summary_field, $summary, $fields_id, $readonly) {
    echo '<div class="yaml-cf-block-item" data-block-type="' . esc_attr($block_type) . '"';
    if ($summary_field !== '') {
      echo ' data-summary-field="' . esc_attr($summary_field) . '"';
    }
    echo '>';
    echo '<div class="yaml-cf-block-header">';
    echo '<button type="button" class="yaml-cf-block-toggle" aria-expanded="true" aria-controls="' . esc_attr($fields_id) . '">';
    echo '<span class="yaml-cf-block-caret" aria-hidden="true"></span>';
    echo '<span class="screen-reader-text">';
    /* translators: %s: block type name */
    echo esc_html(sprintf(__('Hide or show %s', 'yaml-custom-fields'), $block_def['label']));
    echo '</span>';
    echo '</button>';
    echo '<strong>' . esc_html($block_def['label']) . '</strong>';
    echo '<span class="yaml-cf-block-summary">' . esc_html($summary) . '</span>';
    if (!$readonly) {
      echo '<span class="yaml-cf-block-actions">';
      echo '<button type="button" class="button button-small yaml-cf-move-up" title="' . esc_attr__('Move up', 'yaml-custom-fields') . '"><span class="dashicons dashicons-arrow-up-alt"></span></button>';
      echo '<button type="button" class="button button-small yaml-cf-move-down" title="' . esc_attr__('Move down', 'yaml-custom-fields') . '"><span class="dashicons dashicons-arrow-down-alt"></span></button>';
      echo '<button type="button" class="button button-small yaml-cf-remove-block">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
      echo '</span>';
    }
    echo '</div>';
  }

  /**
   * One field inside the item, with its label, help text and snippet
   *
   * @return void
   */
  private function renderField($field, $block_field, $block_data, $index, $id_suffix, $readonly) {
    $block_field_id = 'ycf_' . $field['name'] . '_' . $index . '_' . $block_field['name'] . $id_suffix;
    $block_field_value = isset($block_data[$block_field['name']]) ? $block_data[$block_field['name']] : '';
    $block_field_type = isset($block_field['type']) ? $block_field['type'] : 'string';

    echo '<div class="yaml-cf-field" data-type="' . esc_attr($block_field_type) . '">';

    // The same rules as a top-level field: no label of its own falls back to
    // the name, and the kinds that are not inputs at all get no label element.
    $block_field_label = isset($block_field['label']) ? $block_field['label'] : ucfirst($block_field['name']);

    if ($block_field_type !== 'block' && $block_field_type !== 'info' && $block_field_type !== 'section') {
      // Image and file fields have hidden inputs, groups have none: no "for"
      if ($block_field_type === 'image' || $block_field_type === 'file') {
        echo '<p class="yaml-cf-field-label">' . esc_html($block_field_label) . '</p>';
      } elseif ($block_field_type === 'object') {
        echo '<h4 class="yaml-cf-field-heading">' . esc_html($block_field_label) . '</h4>';
      } else {
        echo '<label for="' . esc_attr($block_field_id) . '">' . esc_html($block_field_label) . '</label>';
      }
      $this->root->renderHelp($block_field);
    }

    $state = [
      'parent' => $field['name'],
      'index' => $index,
      'name' => $block_field['name'],
      'field_id' => $block_field_id,
      'readonly' => $readonly,
      'id_suffix' => $id_suffix,
    ];

    $renderer = $this->root->rendererFor($block_field_type);
    if ($renderer instanceof BlockFieldRendererInterface) {
      $renderer->renderInBlock($block_field, $block_field_value, $state);
    } else {
      $this->renderFallbackInput($block_field_value, $state);
    }

    // A note or a section break holds nothing to read
    if ($block_field_type !== 'info' && $block_field_type !== 'section') {
      $snippets = $this->root->snippets();
      $snippets->render(
        $snippets->forBlockField($block_field, $block_field_type),
        'snippet-' . sanitize_html_class($block_field_id)
      );
    }

    echo '</div>';
  }

  /**
   * A plain text input, for a type with no input of its own inside a block
   *
   * @param mixed $value Stored value
   * @param array $state Block field state
   * @return void
   */
  private function renderFallbackInput($value, $state) {
    $attrs = [
      'type' => 'text',
      'name' => 'yaml_cf[' . $state['parent'] . '][' . $state['index'] . '][' . $state['name'] . ']',
      'id' => $state['field_id'],
      'value' => $value,
      'class' => 'regular-text',
    ];
    if ($state['readonly']) {
      $attrs['disabled'] = 'disabled';
    }
    echo '<input';
    HtmlHelper::outputAttrs($attrs);
    echo ' />';
  }
}
