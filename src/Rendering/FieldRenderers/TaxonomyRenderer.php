<?php

/**
 * Taxonomy Renderer
 * Categories, tags or any other taxonomy: a dropdown, tick boxes, or a search
 * box once there are too many terms to list
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;
use YamlCF\Rendering\PickerRenderer;

class TaxonomyRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $field_name = $state['field_name'];
    $field_id = $state['field_id'];
    $taxonomy = $this->taxonomy($field);
    $multiple = isset($field['multiple']) && $field['multiple'];

    // Count first: a big taxonomy gets a search box instead of every term,
    // so the page does not carry thousands of checkboxes or options.
    if ($this->renderPickerIfBig($field_name, $field_id, $taxonomy, $multiple, $value, $state['readonly'])) {
      return;
    }

    $terms = $this->terms($taxonomy);

    if ($multiple) {
      // Tick boxes read better than a multi-select for choosing several
      $value = is_array($value) ? $value : ($value ? [$value] : []);

      // Hidden field to ensure the field is submitted even when no checkboxes are checked
      // This allows users to clear all selections
      echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" value="" />';

      echo '<div class="yaml-cf-taxonomy-checkboxes">';

      if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
          $checked = in_array($term->term_id, $value, false) ? 'checked' : '';
          $checkbox_id = $field_id . '_' . $term->term_id;
          echo '<label>';
          echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field_name) . '][]" id="' . esc_attr($checkbox_id) . '" value="' . esc_attr($term->term_id) . '" ' . esc_attr($checked) . ' />';
          echo ' ' . esc_html($term->name);
          echo '</label>';
        }
      } else {
        echo '<p class="yaml-cf-checkbox-empty">' . esc_html__('There are no categories or tags yet.', 'yaml-custom-fields') . '</p>';
      }

      echo '</div>';
      return;
    }

    echo '<select name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" class="regular-text">';
    echo '<option value="">' . esc_html(sprintf(
      /* translators: %s: field label */
      __('-- Select %s --', 'yaml-custom-fields'),
      $field['label']
    )) . '</option>';

    if (!is_wp_error($terms) && !empty($terms)) {
      foreach ($terms as $term) {
        // Term IDs are integers, the stored value a string: use loose comparison
        $selected = ($value == $term->term_id) ? 'selected' : '';
        echo '<option value="' . esc_attr($term->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($term->name) . '</option>';
      }
    }

    echo '</select>';
  }

  public function renderInBlock($field, $value, $state) {
    $readonly = $state['readonly'];
    $taxonomy = $this->taxonomy($field);
    $multiple = isset($field['multiple']) && $field['multiple'];

    // It matters more inside a block: the options would otherwise be repeated
    // for every block on the page.
    if ($this->renderPickerIfBig($this->blockFieldPath($state), $state['field_id'], $taxonomy, $multiple, $value, $readonly)) {
      return;
    }

    $terms = $this->terms($taxonomy);

    if ($multiple) {
      $value = is_array($value) ? $value : ($value ? [$value] : []);
      echo '<select name="' . esc_attr($this->blockInputName($state)) . '[]" id="' . esc_attr($state['field_id']) . '" multiple class="regular-text yaml-cf-multi-select"' . disabled($readonly, true, false) . '>';
    } else {
      echo '<select name="' . esc_attr($this->blockInputName($state)) . '" id="' . esc_attr($state['field_id']) . '" class="regular-text"' . disabled($readonly, true, false) . '>';
      echo '<option value="">' . esc_html(sprintf(
        /* translators: %s: field label */
        __('-- Select %s --', 'yaml-custom-fields'),
        $field['label']
      )) . '</option>';
    }

    if (!is_wp_error($terms) && !empty($terms)) {
      foreach ($terms as $term) {
        if ($multiple) {
          $selected = in_array($term->term_id, $value, false) ? 'selected' : '';
        } else {
          // Term IDs are integers, the stored value a string: use loose comparison
          $selected = ($value == $term->term_id) ? 'selected' : '';
        }
        echo '<option value="' . esc_attr($term->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($term->name) . '</option>';
      }
    }

    echo '</select>';
  }

  /**
   * @param array $field Field definition
   * @return string The taxonomy named in the schema, category by default
   */
  private function taxonomy($field) {
    $options = isset($field['options']) ? $field['options'] : [];
    return isset($options['taxonomy']) ? $options['taxonomy'] : 'category';
  }

  /**
   * @param string $taxonomy Taxonomy name
   * @return \WP_Term[]|\WP_Error
   */
  private function terms($taxonomy) {
    return get_terms([
      'taxonomy' => $taxonomy,
      'hide_empty' => false,
    ]);
  }

  /**
   * Print a search box instead, when the taxonomy has too many terms to list
   *
   * @param string $field_name Path inside yaml_cf[...]
   * @param string $field_id Element ID
   * @param string $taxonomy Taxonomy name
   * @param bool $multiple Whether several terms can be chosen
   * @param mixed $value Stored value
   * @param bool $readonly Whether the field is display-only
   * @return bool Whether the picker was printed
   */
  private function renderPickerIfBig($field_name, $field_id, $taxonomy, $multiple, $value, $readonly) {
    $term_count = (int) wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

    if ($term_count <= PickerRenderer::threshold('taxonomy', $taxonomy)) {
      return false;
    }

    $picker = $this->root->picker();
    $selected_values = is_array($value) ? $value : ($value !== '' ? [$value] : []);
    $picker->render([
      'field_name' => $field_name,
      'field_id' => $field_id,
      'source' => 'taxonomy',
      'key' => $taxonomy,
      'multiple' => $multiple,
      'selected' => $picker->selectedTermLabels($selected_values),
      'readonly' => $readonly,
      'total' => $term_count,
    ]);

    return true;
  }
}
