<?php

/**
 * Image Renderer
 * One image from the media library, or a gallery with "list: true"
 */

namespace YamlCF\Rendering\FieldRenderers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Rendering\BaseFieldRenderer;
use YamlCF\Rendering\BlockFieldRendererInterface;

class ImageRenderer extends BaseFieldRenderer implements BlockFieldRendererInterface {
  public function render($field, $value, $state) {
    $field_id = $state['field_id'];

    if (isset($field['list']) && $field['list']) {
      // A gallery: many images in one field, picked in one trip to the
      // media library and reordered by dragging.
      $this->root->media()->renderImageList($state['field_name'], $value, $state['readonly']);
      return;
    }

    echo '<input type="hidden" name="yaml_cf[' . esc_attr($state['field_name']) . ']" id="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" />';
    echo '<div class="yaml-cf-media-buttons">';
    echo '<button type="button" class="button yaml-cf-upload-image" data-target="' . esc_attr($field_id) . '">' . esc_html__('Choose image', 'yaml-custom-fields') . '</button>';
    if ($value) {
      echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($field_id) . '">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
    }
    echo '</div>';
    $this->printPreview($value);
  }

  public function renderInBlock($field, $value, $state) {
    $field_id = $state['field_id'];
    $readonly = $state['readonly'];

    if (!empty($field['list'])) {
      $this->root->media()->renderImageList($this->blockFieldPath($state), $value, $readonly);
      return;
    }

    echo '<input type="hidden" name="' . esc_attr($this->blockInputName($state)) . '" id="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '"' . disabled($readonly, true, false) . ' />';
    if (!$readonly) {
      echo '<div class="yaml-cf-media-buttons">';
      echo '<button type="button" class="button yaml-cf-upload-image" data-target="' . esc_attr($field_id) . '">' . esc_html__('Choose image', 'yaml-custom-fields') . '</button>';
      if ($value) {
        echo '<button type="button" class="button yaml-cf-clear-media" data-target="' . esc_attr($field_id) . '">' . esc_html__('Remove', 'yaml-custom-fields') . '</button>';
      }
      echo '</div>';
    }
    $this->printPreview($value);
  }

  /**
   * @param mixed $value Attachment ID
   * @return void
   */
  private function printPreview($value) {
    if (!$value) {
      return;
    }

    $image_url = wp_get_attachment_image_url($value, 'medium');
    if ($image_url) {
      echo '<div class="yaml-cf-image-preview"><img src="' . esc_url($image_url) . '" class="yaml-cf-image-preview-img" alt="" /></div>';
    }
  }
}
