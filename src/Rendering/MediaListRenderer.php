<?php

/**
 * Media List Renderer
 * Galleries (image fields with list: true) and file lists
 */

namespace YamlCF\Rendering;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MediaListRenderer {
  /**
   * Print a gallery field: an image field with "list: true"
   *
   * The images are picked in one trip to the media library and reordered by
   * dragging, or with the two buttons on each image for anyone not using a
   * mouse. Every image posts its attachment ID in yaml_cf[<field>][], so the
   * order in the markup is the order that is saved.
   *
   * @param string $field_name Field name, already namespaced for nested fields
   * @param mixed $field_value Stored value: an array of attachment IDs
   * @param bool $readonly Whether the field is display-only
   * @return void
   */
  public function renderImageList($field_name, $field_value, $readonly = false) {
    $image_ids = is_array($field_value) ? $field_value : [];

    echo '<div class="yaml-cf-image-list" data-field-name="' . esc_attr($field_name) . '">';

    // Keeps the field submittable after the last image is removed, so emptying
    // a gallery saves as empty instead of leaving the old images in place.
    if (!$readonly) {
      echo '<input type="hidden" class="yaml-cf-image-list-placeholder" name="yaml_cf[' . esc_attr($field_name) . '][]" value="" />';
    }

    echo '<ul class="yaml-cf-image-list-items' . ($readonly ? '' : ' is-sortable') . '">';
    foreach ($image_ids as $image_id) {
      if ($image_id === '' || $image_id === null) {
        continue;
      }
      $this->renderImageListItem($field_name, $image_id, $readonly);
    }
    echo '</ul>';

    if (!$readonly) {
      echo '<button type="button" class="button yaml-cf-add-image-list-item" data-target-name="' . esc_attr($field_name) . '">';
      \YamlCF\Helpers\Icons::render('gallery');
      echo esc_html__('Add images', 'yaml-custom-fields');
      echo '</button>';
      echo '<p class="yaml-cf-field-help">' . esc_html__('Drag the images to change their order.', 'yaml-custom-fields') . '</p>';
    }

    echo '</div>';
  }

  /**
   * Print one image inside a gallery field
   *
   * @param string $field_name Field name
   * @param int|string $image_id Attachment ID
   * @param bool $readonly Whether the field is display-only
   * @return void
   */
  public function renderImageListItem($field_name, $image_id, $readonly = false) {
    $thumb = wp_get_attachment_image_url($image_id, 'thumbnail');
    $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

    echo '<li class="yaml-cf-image-list-item" data-id="' . esc_attr($image_id) . '">';
    echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . '][]" value="' . esc_attr($image_id) . '" />';

    if ($thumb) {
      echo '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($alt) . '" class="yaml-cf-image-list-thumb" />';
    } else {
      // The attachment is gone from the media library, which is what an import
      // from another site looks like. Say so rather than showing a broken image.
      echo '<span class="yaml-cf-image-list-missing">';
      /* translators: %s: attachment ID */
      echo esc_html(sprintf(__('Image %s is missing', 'yaml-custom-fields'), $image_id));
      echo '</span>';
    }

    if (!$readonly) {
      echo '<span class="yaml-cf-image-list-actions">';
      echo '<button type="button" class="button button-small yaml-cf-move-image-earlier" title="' . esc_attr__('Move earlier', 'yaml-custom-fields') . '">';
      \YamlCF\Helpers\Icons::render('back');
      echo '<span class="screen-reader-text">' . esc_html__('Move earlier', 'yaml-custom-fields') . '</span>';
      echo '</button>';
      echo '<button type="button" class="button button-small yaml-cf-move-image-later" title="' . esc_attr__('Move later', 'yaml-custom-fields') . '">';
      \YamlCF\Helpers\Icons::render('next');
      echo '<span class="screen-reader-text">' . esc_html__('Move later', 'yaml-custom-fields') . '</span>';
      echo '</button>';
      echo '<button type="button" class="button button-small yaml-cf-remove-image-list-item" title="' . esc_attr__('Remove', 'yaml-custom-fields') . '">';
      \YamlCF\Helpers\Icons::render('remove');
      echo '<span class="screen-reader-text">' . esc_html__('Remove this image', 'yaml-custom-fields') . '</span>';
      echo '</button>';
      echo '</span>';
    }

    echo '</li>';
  }

  /**
   * Print one file inside a file list field
   *
   * @param string $field_name Field name
   * @param int|string $file_id Attachment ID
   * @return void
   */
  public function renderFileListItem($field_name, $file_id) {
    echo '<div class="yaml-cf-file-list-item">';
    echo '<input type="hidden" name="yaml_cf[' . esc_attr($field_name) . '][]" value="' . esc_attr($file_id) . '" />';
    $file_path = $file_id ? get_attached_file($file_id) : false;
    echo '<div class="yaml-cf-file-name">' . ($file_path ? esc_html(basename($file_path)) : '') . '</div>';
    echo '<button type="button" class="button button-small yaml-cf-remove-file-list-item">';
    \YamlCF\Helpers\Icons::render('remove');
    echo esc_html__('Remove', 'yaml-custom-fields') . '</button>';
    echo '</div>';
  }
}
