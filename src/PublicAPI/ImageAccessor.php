<?php

/**
 * Image Accessor
 * yaml_cf_get_image()
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ImageAccessor {
  /** @var FieldAccessor */
  private $fields;

  /**
   * @param FieldAccessor $fields Reads the attachment IDs
   */
  public function __construct(FieldAccessor $fields) {
    $this->fields = $fields;
  }

  /**
   * An image field's image, or a gallery's images
   *
   * Each image is built by yaml_cf_resolve_image_data(), which a theme may
   * replace, so it is called by name rather than through resolveImageData().
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @param string|int[] $size Image size name, or [width, height]
   * @param array|null $context_data Values of a block item to read from instead
   * @return array|null Image data, a list of it for a gallery, or null
   */
  public function getImage($field_name, $post_id = null, $size = 'full', $context_data = null) {
    $attachment_ids = $this->fields->getField($field_name, $post_id, $context_data);

    if (!$attachment_ids) {
      return null;
    }

    // A gallery (image field with list: true)
    if (is_array($attachment_ids)) {
      $images = [];
      foreach ($attachment_ids as $attachment_id) {
        $image_data = yaml_cf_resolve_image_data($attachment_id, $size);
        if ($image_data) {
          $images[] = $image_data;
        }
      }
      return !empty($images) ? $images : null;
    }

    return yaml_cf_resolve_image_data($attachment_ids, $size);
  }

  /**
   * The image data for one attachment: what yaml_cf_resolve_image_data() returns
   *
   * @param int|string $attachment_id Attachment ID
   * @param string|int[] $size Image size name, or [width, height]
   * @return array|null Image data, or null when the ID is not an attachment
   */
  public function resolveImageData($attachment_id, $size = 'full') {
    if (!$attachment_id || !is_numeric($attachment_id)) {
      return null;
    }

    // The ID has to be an attachment that still has a file. Otherwise a deleted
    // image came back as data with a false url, which a theme prints as
    // <img src="">, and the ID of some other post, as an import from another
    // site leaves behind, came back with that post's title and content.
    $attachment = get_post((int) $attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
      return null;
    }

    $url = wp_get_attachment_image_url($attachment_id, $size);
    if (!$url) {
      return null;
    }

    $image_data = [
      'id' => $attachment_id,
      'url' => $url,
      'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
      'title' => get_the_title($attachment_id),
      'caption' => wp_get_attachment_caption($attachment_id),
      'description' => get_post_field('post_content', $attachment_id),
    ];

    $metadata = wp_get_attachment_metadata($attachment_id);
    if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
      $image_data['width'] = $metadata['width'];
      $image_data['height'] = $metadata['height'];
    }

    // The dimensions of the size asked for, when it is not the original. WordPress
    // also takes a size as [width, height]; used as an array key that is a TypeError.
    if (is_string($size) && $size !== 'full' && isset($metadata['sizes'][$size])) {
      $image_data['width'] = $metadata['sizes'][$size]['width'];
      $image_data['height'] = $metadata['sizes'][$size]['height'];
    }

    return $image_data;
  }
}
