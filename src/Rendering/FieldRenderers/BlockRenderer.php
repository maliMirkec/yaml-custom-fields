<?php

/**
 * Block Renderer
 * Sections of a page: a list of blocks, each one of the types the schema offers
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class BlockRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $field_id = $state['field_id'];
    $readonly = $state['readonly'];
    $is_list = isset($field['list']) && $field['list'];
    $blocks = isset($field['blocks']) ? $field['blocks'] : [];
    $block_key = isset($field['blockKey']) ? $field['blockKey'] : 'type';

    if ($is_list) {
      echo '<div class="yaml-cf-block-heading-row">';
      echo '<h4 class="yaml-cf-block-heading">' . esc_html($state['field_label']) . '</h4>';
      // Folding every section at once is the difference between a wall of
      // fields and a list you can scan
      if (!$readonly && is_array($value) && count($value) > 1) {
        echo '<button type="button" class="button button-small yaml-cf-collapse-all" aria-pressed="false">';
        \YamlCF\Helpers\Icons::render('collapse');
        echo '<span class="yaml-cf-collapse-all-label">' . esc_html__('Collapse all', 'yaml-custom-fields') . '</span>';
        echo '</button>';
      }
      echo '</div>';
    }

    echo '<div class="yaml-cf-block-container" data-field-name="' . esc_attr($field['name']) . '">';

    if ($is_list) {
      echo '<div class="yaml-cf-block-list">';

      foreach ((is_array($value) ? $value : []) as $index => $block_data) {
        $this->root->blockItems()->render($field, $blocks, $block_data, $index, $block_key, $state['context']);
      }

      echo '</div>';
      if (!$readonly) {
        $this->printAddControls($blocks, $field_id);
      }
    }

    echo '</div>';

    if ($is_list) {
      $this->root->snippets()->render(
        $this->root->snippets()->forBlockList($field),
        'snippet-' . sanitize_html_class($field_id),
        'yaml-cf-snippet-code-block'
      );
    }
  }

  /**
   * Sections inside a section: kept, but not editable here
   *
   * There is no editor for sections nested in a section yet. They used to get
   * a text input, and saving the page replaced them all with the word "Array".
   * Now the stored value is posted back unchanged, as hidden inputs, and the
   * writer is told why there is nothing to edit.
   *
   * @param array $field Field definition
   * @param mixed $value Stored value
   * @param array $state Block field state
   * @return void
   */
  public function renderInBlock($field, $value, $state) {
    $label = isset($field['label']) ? $field['label'] : ucfirst($field['name']);

    echo '<p class="yaml-cf-field-label">' . esc_html($label) . '</p>';
    echo '<p class="yaml-cf-nested-blocks-notice notice notice-info inline yaml-cf-notice">';
    echo esc_html__('Sections inside a section cannot be edited here yet. What they hold is kept as it is when the page is saved.', 'yaml-custom-fields');
    echo '</p>';

    $this->printPreserved('yaml_cf[' . $this->blockFieldPath($state) . ']', $value, $state['readonly']);
  }

  /**
   * Post a stored value back exactly as it is, as hidden inputs
   *
   * @param string $name Input name for this level
   * @param mixed $value Value at this level
   * @param bool $readonly Whether to disable the inputs
   * @return void
   */
  private function printPreserved($name, $value, $readonly) {
    if (is_array($value)) {
      foreach ($value as $key => $item) {
        $this->printPreserved($name . '[' . $key . ']', $item, $readonly);
      }
      return;
    }

    if ($value === null || $value === '') {
      return;
    }

    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"' . disabled($readonly, true, false) . ' />';
  }

  /**
   * The "Add section" row: which type, and the button
   *
   * @param array $blocks Block types the schema offers
   * @param string $field_id Element ID of the block field
   * @return void
   */
  private function printAddControls($blocks, $field_id) {
    echo '<div class="yaml-cf-block-controls">';
    $block_select_id = $field_id . '_block_type_select';
    echo '<label for="' . esc_attr($block_select_id) . '" class="screen-reader-text">' . esc_html__('Choose a section type to add', 'yaml-custom-fields') . '</label>';
    echo '<select class="yaml-cf-block-type-select" id="' . esc_attr($block_select_id) . '" name="' . esc_attr($block_select_id) . '">';
    echo '<option value="">' . esc_html__('Choose a section type', 'yaml-custom-fields') . '</option>';
    foreach ($blocks as $block) {
      $block_name = isset($block['name']) ? $block['name'] : '';
      $block_label = isset($block['label']) ? $block['label'] : $block_name;
      if ($block_name) {
        echo '<option value="' . esc_attr($block_name) . '">' . esc_html($block_label) . '</option>';
      }
    }
    echo '</select>';
    echo '<button type="button" class="button yaml-cf-add-block">';
    \YamlCF\Helpers\Icons::render('add');
    echo esc_html__('Add section', 'yaml-custom-fields') . '</button>';
    echo '<span class="yaml-cf-status" data-slot="add-section"></span>';
    echo '</div>';
  }
}
