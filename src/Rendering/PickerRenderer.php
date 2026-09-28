<?php

/**
 * Picker Renderer
 * The search box a taxonomy or collection field turns into when it has too
 * many choices to list
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PickerRenderer {
  private $parser;
  private $repository;

  /**
   * @param \YamlCF\Schema\SchemaParser $parser Reads collection schemas, for labels
   * @param \YamlCF\Data\Repositories\DataObjectRepository $repository Collection items
   */
  public function __construct($parser, $repository) {
    $this->parser = $parser;
    $this->repository = $repository;
  }

  /**
   * How many choices a taxonomy or collection may have before its field turns
   * into a search box instead of listing every option
   *
   * Listing 30 categories as checkboxes is friendlier than making someone type.
   * Listing 3,000 is a megabyte of markup nobody can read, so past this many
   * the field becomes a picker that searches over AJAX.
   *
   * @param string $type Field type: 'taxonomy' or 'data_object'
   * @param string $key Taxonomy name or collection slug
   * @return int Threshold
   */
  public static function threshold($type, $key) {
    /**
     * Filter the number of choices at which a field becomes a search box.
     *
     * @param int $threshold Default 50.
     * @param string $type Field type: 'taxonomy' or 'data_object'.
     * @param string $key Taxonomy name or collection slug.
     */
    return (int) apply_filters('yaml_cf_picker_threshold', 50, $type, $key);
  }

  /**
   * Print a search-backed picker for a taxonomy or collection field
   *
   * Only the chosen values are in the markup; everything else is searched over
   * AJAX. That keeps a field over a 5,000-term taxonomy the same size as one
   * over five.
   *
   * @param array $args {
   *   @type string $field_name Field name, already namespaced for nested fields
   *   @type string $field_id Element ID base
   *   @type string $source 'taxonomy' or 'data_object'
   *   @type string $key Taxonomy name or collection slug
   *   @type bool $multiple Whether more than one value can be chosen
   *   @type array $selected Chosen values as [id => label]
   *   @type bool $readonly Whether the field is display-only
   *   @type int $total How many choices exist in total
   * }
   * @return void
   */
  public function render($args) {
    $field_name = $args['field_name'];
    $field_id = $args['field_id'];
    $multiple = !empty($args['multiple']);
    $readonly = !empty($args['readonly']);
    $selected = isset($args['selected']) ? $args['selected'] : [];
    $results_id = $field_id . '_results';

    echo '<div class="yaml-cf-picker"';
    echo ' data-source="' . esc_attr($args['source']) . '"';
    echo ' data-key="' . esc_attr($args['key']) . '"';
    echo ' data-field-name="' . esc_attr($field_name) . '"';
    echo ' data-multiple="' . ($multiple ? '1' : '0') . '"';
    echo '>';

    // Keeps the field submittable once the last value is removed
    if (!$readonly && $multiple) {
      echo '<input type="hidden" class="yaml-cf-picker-placeholder" name="yaml_cf[' . esc_attr($field_name) . '][]" value="" />';
    }

    echo '<ul class="yaml-cf-picker-selected">';
    foreach ($selected as $value => $label) {
      $this->renderChip($field_name, $value, $label, $multiple, $readonly);
    }
    echo '</ul>';

    // A single-value picker still needs an input to post an empty value
    if (!$readonly && !$multiple && empty($selected)) {
      echo '<input type="hidden" class="yaml-cf-picker-value" name="yaml_cf[' . esc_attr($field_name) . ']" value="" />';
    }

    if (!$readonly) {
      echo '<div class="yaml-cf-picker-search-row">';
      echo '<input type="search" class="yaml-cf-picker-search" id="' . esc_attr($field_id) . '"';
      echo ' role="combobox" aria-expanded="false" aria-autocomplete="list"';
      echo ' aria-controls="' . esc_attr($results_id) . '"';
      echo ' autocomplete="off"';
      echo ' placeholder="' . esc_attr__('Type to search…', 'yaml-custom-fields') . '" />';
      echo '</div>';

      echo '<ul class="yaml-cf-picker-results" id="' . esc_attr($results_id) . '" role="listbox" hidden></ul>';

      echo '<p class="yaml-cf-field-help">';
      /* translators: %d: number of available choices */
      echo esc_html(sprintf(_n('%d choice, so search for the one you want.', '%d choices, so search for the one you want.', (int) $args['total'], 'yaml-custom-fields'), (int) $args['total']));
      echo '</p>';
    }

    echo '</div>';
  }

  /**
   * Print one chosen value inside a picker
   *
   * @param string $field_name Field name
   * @param string|int $value Stored value
   * @param string $label Human-readable label
   * @param bool $multiple Whether the field holds more than one value
   * @param bool $readonly Whether the field is display-only
   * @return void
   */
  public function renderChip($field_name, $value, $label, $multiple, $readonly = false) {
    $name = $multiple
      ? 'yaml_cf[' . $field_name . '][]'
      : 'yaml_cf[' . $field_name . ']';

    echo '<li class="yaml-cf-picker-chip" data-id="' . esc_attr($value) . '">';
    if (!$readonly) {
      echo '<input type="hidden" class="yaml-cf-picker-value" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
    }
    echo '<span class="yaml-cf-picker-chip-label">' . esc_html($label) . '</span>';
    if (!$readonly) {
      echo '<button type="button" class="yaml-cf-picker-remove">';
      echo '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
      echo '<span class="screen-reader-text">';
      /* translators: %s: the chosen value's name */
      echo esc_html(sprintf(__('Remove %s', 'yaml-custom-fields'), $label));
      echo '</span>';
      echo '</button>';
    }
    echo '</li>';
  }

  /**
   * Look up the names of the terms already chosen in a taxonomy field
   *
   * @param array $values Term IDs
   * @return array [term_id => name], skipping terms that no longer exist
   */
  public function selectedTermLabels($values) {
    $labels = [];
    foreach ($values as $value) {
      if ($value === '' || $value === null) {
        continue;
      }
      $term = get_term((int) $value);
      if ($term && !is_wp_error($term)) {
        $labels[$term->term_id] = $term->name;
      }
    }
    return $labels;
  }

  /**
   * Look up the labels of the collection entries already chosen
   *
   * @param array $values Entry IDs
   * @param string $object_type Collection slug
   * @return array [entry_id => label]
   */
  public function selectedDataObjectLabels($values, $object_type) {
    // Only the chosen items are read. This runs for collections too big to
    // list, and reading all of them to label two or three was most of the cost.
    $wanted = [];
    foreach ((array) $values as $value) {
      if (is_scalar($value) && $value !== '') {
        $wanted[] = (string) $value;
      }
    }
    $entries = $this->repository->getEntriesByIds($object_type, $wanted);
    $types = get_option('yaml_cf_data_object_types', []);

    $label_field = '';
    if (isset($types[$object_type]['schema'])) {
      $label_field = \YamlCF\Data\EntryStore::labelFieldOf($this->parser->parse($types[$object_type]['schema']));
    }

    $labels = [];
    foreach ($values as $value) {
      if ($value === '' || $value === null) {
        continue;
      }
      if (!isset($entries[$value])) {
        // An entry that was deleted, or came from another site with the import
        /* translators: %s: entry ID */
        $labels[$value] = sprintf(__('%s (missing)', 'yaml-custom-fields'), $value);
        continue;
      }
      $label = isset($entries[$value][$label_field]) ? $entries[$value][$label_field] : $value;
      $labels[$value] = is_string($label) && $label !== '' ? $label : $value;
    }
    return $labels;
  }
}
