<?php

/**
 * File Accessor
 * yaml_cf_get_file()
 */

namespace YamlCF\PublicAPI;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FileAccessor {
  /** @var FieldAccessor */
  private $fields;

  /**
   * @param FieldAccessor $fields Reads the attachment IDs
   */
  public function __construct(FieldAccessor $fields) {
    $this->fields = $fields;
  }

  /**
   * A file field's file, or a list's files
   *
   * Each file is built by yaml_cf_resolve_file_data(), which a theme may
   * replace, so it is called by name rather than through resolveFileData().
   *
   * @param string $field_name The field's name
   * @param int|string|null $post_id Post ID, 'partial:<file>', or null for the current post
   * @param array|null $context_data Values of a block item to read from instead
   * @return array|null File data, a list of it, or null
   */
  public function getFile($field_name, $post_id = null, $context_data = null) {
    $attachment_ids = $this->fields->getField($field_name, $post_id, $context_data);

    if (!$attachment_ids) {
      return null;
    }

    // A list of files (file field with list: true)
    if (is_array($attachment_ids)) {
      $files = [];
      foreach ($attachment_ids as $attachment_id) {
        $file_data = yaml_cf_resolve_file_data($attachment_id);
        if ($file_data) {
          $files[] = $file_data;
        }
      }
      return !empty($files) ? $files : null;
    }

    return yaml_cf_resolve_file_data($attachment_ids);
  }

  /**
   * The file data for one attachment: what yaml_cf_resolve_file_data() returns
   *
   * @param mixed $attachment_id Attachment ID
   * @return array|null File data, or null when the ID is not an attachment
   */
  public function resolveFileData($attachment_id) {
    if (!$attachment_id || !is_numeric($attachment_id)) {
      return null;
    }

    $file_path = get_attached_file($attachment_id);
    $file_url = wp_get_attachment_url($attachment_id);

    if (!$file_path || !$file_url) {
      return null;
    }

    return [
      'id' => $attachment_id,
      'url' => $file_url,
      'path' => $file_path,
      'filename' => basename($file_path),
      // false when the file is gone from disk (an import from another site, a
      // cleaned-up uploads folder), as before, but without the PHP warning
      // filesize() prints onto the page when it cannot find the file
      'filesize' => is_file($file_path) ? filesize($file_path) : false,
      'mime_type' => get_post_mime_type($attachment_id),
      'title' => get_the_title($attachment_id),
    ];
  }
}
