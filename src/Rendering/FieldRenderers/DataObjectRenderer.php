<?php

/**
 * Data Object Renderer
 * An item, or several, from one of the site's collections
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;
use YamlCF\Rendering\PickerRenderer;

class DataObjectRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $field_name = $state['field_name'];
    $field_id = $state['field_id'];
    $options = isset($field['options']) ? $field['options'] : [];
    $object_type = isset($options['object_type']) ? $options['object_type'] : '';
    $multiple = isset($field['multiple']) && $field['multiple'];

    if (empty($object_type)) {
      $this->printSchemaProblem(__('This field needs a collection to choose from. Add object_type to the schema.', 'yaml-custom-fields'));
      return;
    }

    $data_types = get_option('yaml_cf_data_object_types', []);

    if (!isset($data_types[$object_type])) {
      /* translators: %s: collection short name */
      $this->printSchemaProblem(sprintf(__('There is no collection with the short name "%s". Check the name in the schema.', 'yaml-custom-fields'), $object_type));
      return;
    }

    // A big collection gets a search box instead of every entry. Counted from
    // the collection's index first: reading every item only to find there are
    // too many to list read thousands of options on each editor load.
    $entry_count = $this->root->repository()->countEntries($object_type);
    if ($entry_count > PickerRenderer::threshold('data_object', $object_type)) {
      $this->renderPicker($field_name, $field_id, $object_type, $multiple, $value, $state['readonly'], $entry_count);
      return;
    }

    $data_entries = $this->root->repository()->getEntries($object_type);

    if ($multiple) {
      // Tick boxes read better than a multi-select for choosing several
      $value = is_array($value) ? $value : ($value ? [$value] : []);

      // Hidden field to ensure the field is submitted even when no checkboxes are checked
      echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" value="" />';

      if (!empty($data_entries)) {
        $label_field = $this->labelField($data_types[$object_type]['schema']);

        echo '<div class="yaml-cf-data-object-checkboxes">';

        foreach ($data_entries as $entry_id => $entry_data) {
          $entry_label = isset($entry_data[$label_field]) ? $entry_data[$label_field] : $entry_id;
          if (is_array($entry_label)) {
            $entry_label = $entry_id; // Fallback for complex data
          }

          $checked = in_array($entry_id, $value, false) ? 'checked' : '';
          $checkbox_id = $field_id . '_' . sanitize_html_class($entry_id);
          echo '<label>';
          echo '<input type="checkbox" name="yaml_cf[' . esc_attr($field_name) . '][]" id="' . esc_attr($checkbox_id) . '" value="' . esc_attr($entry_id) . '" ' . esc_attr($checked) . ' />';
          echo ' ' . esc_html($entry_label);
          echo '</label>';
        }

        echo '</div>';
      } else {
        echo '<p class="description">' . sprintf(
          // translators: %1$s is the data object type name, %2$s is the URL to manage entries
          esc_html__('The list %1$s is empty. %2$sAdd items%3$s first.', 'yaml-custom-fields'),
          esc_html($data_types[$object_type]['name']),
          '<a href="' . esc_url(admin_url('admin.php?page=yaml-cf-manage-data-object-entries&type_id=' . rawurlencode($object_type))) . '">',
          '</a>'
        ) . '</p>';
      }
      return;
    }

    echo '<select name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" class="regular-text">';
    echo '<option value="">' . esc_html(sprintf(
      /* translators: %s: field label */
      __('-- Select %s --', 'yaml-custom-fields'),
      $field['label']
    )) . '</option>';

    if (!empty($data_entries)) {
      $label_field = $this->labelField($data_types[$object_type]['schema']);

      foreach ($data_entries as $entry_id => $entry_data) {
        $entry_label = isset($entry_data[$label_field]) ? $entry_data[$label_field] : $entry_id;
        if (is_array($entry_label)) {
          $entry_label = $entry_id; // Fallback for complex data
        }

        $selected = ($value === $entry_id) ? 'selected' : '';
        echo '<option value="' . esc_attr($entry_id) . '" ' . esc_attr($selected) . '>' . esc_html($entry_label) . '</option>';
      }
    } else {
      echo '<option value="" disabled>' . sprintf(
        /* translators: %s: data object type name (e.g., "Universities", "Companies") */
        esc_html__('The list %s is empty', 'yaml-custom-fields'),
        esc_html($data_types[$object_type]['name'])
      ) . '</option>';
    }

    echo '</select>';
  }

  public function renderInBlock($field, $value, $state) {
    $readonly = $state['readonly'];
    $options = isset($field['options']) ? $field['options'] : [];
    $object_type = isset($options['object_type']) ? $options['object_type'] : '';

    if (empty($object_type)) {
      $this->printSchemaProblem(__('This field needs a collection to choose from. Add object_type to the schema.', 'yaml-custom-fields'));
      return;
    }

    $data_object_types = get_option('yaml_cf_data_object_types', []);
    $type_name = isset($data_object_types[$object_type]) ? $data_object_types[$object_type]['name'] : ucfirst($object_type);
    $type_schema_yaml = isset($data_object_types[$object_type]) ? $data_object_types[$object_type]['schema'] : '';

    $label_field = $this->labelField($type_schema_yaml);

    // A big collection gets a search box rather than an option per entry
    // repeated in every block on the page. Counted before any item is read.
    $entry_count = $this->root->repository()->countEntries($object_type);
    if ($entry_count > PickerRenderer::threshold('data_object', $object_type)) {
      $this->renderPicker($this->blockFieldPath($state), $state['field_id'], $object_type, !empty($field['multiple']), $value, $readonly, $entry_count);
      return;
    }

    $data_object_entries = $this->root->repository()->getEntries($object_type);

    echo '<select name="' . esc_attr($this->blockInputName($state)) . '" id="' . esc_attr($state['field_id']) . '" class="regular-text"' . disabled($readonly, true, false) . '>';
    echo '<option value="">' . esc_html(sprintf(
      /* translators: %s: field label */
      __('-- Select %s --', 'yaml-custom-fields'),
      $type_name
    )) . '</option>';

    if (!empty($data_object_entries) && is_array($data_object_entries)) {
      foreach ($data_object_entries as $entry_id => $entry_data) {
        $selected = ($value === $entry_id) ? 'selected' : '';
        $label = $entry_id;
        // An item whose first field holds more than text is named by its ID, as
        // at the top level. Printing the array itself was a PHP warning.
        if ($label_field && isset($entry_data[$label_field]) && !is_array($entry_data[$label_field])) {
          $label = $entry_data[$label_field];
        }
        echo '<option value="' . esc_attr($entry_id) . '" ' . esc_attr($selected) . '>' . esc_html($label) . '</option>';
      }
    }

    echo '</select>';
  }

  /**
   * The collection's first field, whose value labels each item
   *
   * @param string $schema_yaml The collection's schema
   * @return string Field name, or '' when there is none
   */
  private function labelField($schema_yaml) {
    return \YamlCF\Data\EntryStore::labelFieldOf($this->root->parser()->parse($schema_yaml));
  }

  /**
   * @param string $field_name Path inside yaml_cf[...]
   * @param string $field_id Element ID
   * @param string $object_type Collection slug
   * @param bool $multiple Whether several items can be chosen
   * @param mixed $value Stored value
   * @param bool $readonly Whether the field is display-only
   * @param int $total How many items the collection holds
   * @return void
   */
  private function renderPicker($field_name, $field_id, $object_type, $multiple, $value, $readonly, $total) {
    $picker = $this->root->picker();
    $selected_values = is_array($value) ? $value : ($value !== '' ? [$value] : []);
    $picker->render([
      'field_name' => $field_name,
      'field_id' => $field_id,
      'source' => 'data_object',
      'key' => $object_type,
      'multiple' => $multiple,
      'selected' => $picker->selectedDataObjectLabels($selected_values, $object_type),
      'readonly' => $readonly,
      'total' => $total,
    ]);
  }

  /**
   * Say that the schema is set up wrong, as the plugin's other notices do
   *
   * @param string $message What is wrong
   * @return void
   */
  private function printSchemaProblem($message) {
    echo '<div class="notice notice-error inline yaml-cf-notice">';
    echo '<p><span class="screen-reader-text">' . esc_html(\YamlCF\Core\NotificationManager::typeLabel('error')) . ' </span>' . esc_html($message) . '</p>';
    echo '</div>';
  }
}
