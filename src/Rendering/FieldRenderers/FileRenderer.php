<?php

/**
 * File Renderer
 * One file from the media library, or several with "list: true"
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class FileRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $field_name = $state['field_name'];
    $field_id = $state['field_id'];

    if (isset($field['list']) && $field['list']) {
      echo '<input type="hidden" class="yaml-cf-file-list-placeholder" name="yaml_cf[' . esc_attr($field_name) . '][]" value="" />';
      $this->printList($field_name, $value, true);
      return;
    }

    echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . ']" id="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" />';
    $this->printButtons($field_id, $value);
    $this->printFileName($value);
  }

  public function renderInBlock($field, $value, $state) {
    $field_id = $state['field_id'];
    $readonly = $state['readonly'];

    if (isset($field['list']) && $field['list']) {
      $path = $this->blockFieldPath($state);
      echo '<input type="hidden" class="yaml-cf-file-list-placeholder" name="yaml_cf[' . esc_attr($path) . '][]" value=""' . disabled($readonly, true, false) . ' />';
      $this->printList($path, $value, !$readonly);
      return;
    }

    echo '<input type="hidden" name="' . esc_attr($this->blockInputName($state)) . '" id="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '"' . disabled($readonly, true, false) . ' />';
    if (!$readonly) {
      $this->printButtons($field_id, $value);
    }
    $this->printFileName($value);
  }

  /**
   * @param string $field_name Path inside yaml_cf[...]
   * @param mixed $value Attachment IDs
   * @param bool $can_add Whether to offer the "Add files" button
   * @return void
   */
  private function printList($field_name, $value, $can_add) {
    $file_ids = is_array($value) ? $value : [];
    echo '<div class="yaml-cf-file-list" data-field-name="' . esc_attr($field_name) . '">';
    echo '<div class="yaml-cf-file-list-items">';
    foreach ($file_ids as $file_id) {
      $this->root->media()->renderFileListItem($field_name, $file_id);
    }
    echo '</div>';
    if ($can_add) {
      echo '<button type="button" class="button yaml-cf-add-file-list-item" data-target-name="' . esc_attr($field_name) . '">';
      \YamlCF\Helpers\Icons::render('file');
      echo esc_html__('Add files', 'yaml-custom-fields') . '</button>';
    }
    echo '</div>';
  }

  /**
   * @param string $field_id Element ID of the hidden input
   * @param mixed $value Attachment ID
   * @return void
   */
  private function printButtons($field_id, $value) {
    echo '<div class="yaml-cf-media-buttons">';
    echo '<button type="button" class="button yaml-cf-upload-file" data-target="' . esc_attr($field_id) . '">';
    \YamlCF\Helpers\Icons::render('file');
    echo esc_html__('Choose file', 'yaml-custom-fields') . '</button>';
    if ($value) {
      echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($field_id) . '">';
      \YamlCF\Helpers\Icons::render('remove');
      echo esc_html__('Remove', 'yaml-custom-fields') . '</button>';
    }
    echo '</div>';
  }

  /**
   * @param mixed $value Attachment ID
   * @return void
   */
  private function printFileName($value) {
    if (!$value) {
      return;
    }

    $file_path = get_attached_file($value);
    if ($file_path) {
      echo '<div class="yaml-cf-file-name">' . esc_html(basename($file_path)) . '</div>';
    }
  }
}
