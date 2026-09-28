<?php

/**
 * Attachment Validator
 * Validate that attachment IDs exist and are valid
 */

namespace YamlCF\Form;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class AttachmentValidator {
  /**
   * Validate and clean attachment data
   *
   * Numeric values in image/file fields that no longer point to an existing post are
   * replaced with ''. Without a schema nothing is cleared: there is no telling an
   * attachment ID from a price, a year or a phone number, and those used to be
   * wiped whenever no post happened to have that ID. A stale attachment ID left in
   * place is harmless, since yaml_cf_get_image() and yaml_cf_get_file() return
   * null for it.
   *
   * @param mixed $data Data to validate
   * @param array|null $schema Parsed schema with a 'fields' array
   * @param string $parent_key Parent key for nested data
   * @return mixed Cleaned data
   */
  public function validateAndClean($data, $schema = null, $parent_key = '') {
    if (!is_array($data)) {
      return $data;
    }

    if (!is_array($schema) || !isset($schema['fields']) || !is_array($schema['fields'])) {
      return $data;
    }

    $attachment_fields = [];
    $this->buildAttachmentFieldMap($schema['fields'], $attachment_fields);
    if (empty($attachment_fields)) {
      return $data;
    }

    foreach ($data as $key => $value) {
      $field_path = $parent_key !== '' ? $parent_key . '.' . $key : (string) $key;

      if (is_array($value)) {
        $data[$key] = $this->validateAndClean($value, $schema, $field_path);
      } elseif (is_numeric($value) && intval($value) > 0) {
        $is_attachment_field = in_array((string) $key, $attachment_fields, true) || in_array($field_path, $attachment_fields, true);

        // Keep IDs of existing posts (attachments or otherwise); clear IDs that no longer exist
        if ($is_attachment_field && !get_post(intval($value))) {
          $data[$key] = '';
        }
      }
    }

    return $data;
  }

  /**
   * Collect names and dotted paths of image/file fields, including nested object and block fields
   *
   * @param array $fields Schema fields
   * @param array $attachment_fields Collected names/paths
   * @param string $prefix Path prefix
   */
  private function buildAttachmentFieldMap($fields, &$attachment_fields, $prefix = '') {
    foreach ($fields as $field) {
      if (!is_array($field) || !isset($field['name'])) {
        continue;
      }

      $field_path = $prefix !== '' ? $prefix . '.' . $field['name'] : (string) $field['name'];

      if (isset($field['type']) && in_array($field['type'], ['image', 'file'], true)) {
        $attachment_fields[] = (string) $field['name'];
        $attachment_fields[] = $field_path;
      }

      if (isset($field['blocks']) && is_array($field['blocks'])) {
        foreach ($field['blocks'] as $block) {
          if (is_array($block) && isset($block['fields']) && is_array($block['fields'])) {
            $this->buildAttachmentFieldMap($block['fields'], $attachment_fields, $field_path);
          }
        }
      }

      if (isset($field['fields']) && is_array($field['fields'])) {
        $this->buildAttachmentFieldMap($field['fields'], $attachment_fields, $field_path);
      }
    }
  }

  /**
   * Validate attachments recursively and return missing attachment IDs
   *
   * @param mixed $data Field data to validate
   * @param string $path Current field path (for error reporting)
   * @param array|null $schema Schema to determine which fields are attachments
   * @return array Array of missing attachments: [['field' => 'path', 'id' => 123], ...]
   */
  public function validateAttachments($data, $path = '', $schema = null) {
    $missing = [];

    if (!is_array($data)) {
      return $missing;
    }

    // If no schema provided, skip validation (can't determine which fields are attachments)
    if (empty($schema)) {
      return $missing;
    }

    foreach ($data as $key => $value) {
      $current_path = $path ? $path . ' > ' . $key : $key;

      // Find the field definition in the schema
      $field_schema = $this->findFieldInSchema($schema, $key);

      if (is_array($value)) {
        // Handle list fields and nested objects
        if ($field_schema && isset($field_schema['list']) && $field_schema['list']) {
          // This is a list field, validate each item
          foreach ($value as $index => $item) {
            $item_path = $current_path . ' > ' . $index;
            if (is_array($item)) {
              // Get the schema for list items (could be blocks)
              $item_schema = $field_schema;
              if (isset($field_schema['fields'])) {
                $item_schema = $field_schema['fields'];
              } elseif (isset($field_schema['blocks'])) {
                // For block fields, find the matching block type
                if (isset($item['type']) && isset($field_schema['blocks'])) {
                  foreach ($field_schema['blocks'] as $block) {
                    if (isset($block['name']) && $block['name'] === $item['type']) {
                      $item_schema = isset($block['fields']) ? $block['fields'] : [];
                      break;
                    }
                  }
                }
              }
              $nested_missing = $this->validateAttachments($item, $item_path, $item_schema);
              $missing = array_merge($missing, $nested_missing);
            } elseif (in_array($field_schema['type'], ['image', 'file'], true) && is_numeric($item) && intval($item) > 0) {
              // List of scalar attachment IDs (e.g. a file field with list: true)
              $attachment = get_post(intval($item));
              if (!$attachment || $attachment->post_type !== 'attachment') {
                $missing[] = [
                  'field' => $item_path,
                  'id' => intval($item)
                ];
              }
            }
          }
        } else {
          // A group, or a single section: pass the fields that describe it
          $nested_schema = null;
          if ($field_schema && isset($field_schema['fields'])) {
            $nested_schema = $field_schema['fields'];
          } elseif ($field_schema && isset($field_schema['blocks']) && is_array($field_schema['blocks'])) {
            $nested_schema = $this->blockFields($field_schema, $value);
          }
          $nested_missing = $this->validateAttachments($value, $current_path, $nested_schema);
          $missing = array_merge($missing, $nested_missing);
        }
      } elseif ($field_schema && in_array($field_schema['type'], ['image', 'file'], true)) {
        // Only validate if this field is defined as image or file type in schema
        if (is_numeric($value) && intval($value) > 0) {
          $attachment = get_post(intval($value));
          if (!$attachment || $attachment->post_type !== 'attachment') {
            $missing[] = [
              'field' => $current_path,
              'id' => intval($value)
            ];
          }
        }
      }
    }

    return $missing;
  }

  /**
   * The fields of the section type a single section value holds
   *
   * @param array $field_schema Sections field definition
   * @param array $value The section's value
   * @return array|null
   */
  private function blockFields($field_schema, $value) {
    $block_key = isset($field_schema['blockKey']) ? $field_schema['blockKey'] : 'type';
    if (!isset($value[$block_key]) || !is_scalar($value[$block_key])) {
      return null;
    }

    foreach ($field_schema['blocks'] as $block) {
      if (is_array($block) && isset($block['name']) && $block['name'] === $value[$block_key]) {
        return isset($block['fields']) && is_array($block['fields']) ? $block['fields'] : null;
      }
    }

    return null;
  }

  /**
   * Find a field definition in schema by field name
   *
   * @param array|null $schema Schema array
   * @param string $field_name Field name to find
   * @return array|null Field definition or null if not found
   */
  public function findFieldInSchema($schema, $field_name) {
    if (!is_array($schema)) {
      return null;
    }

    foreach ($schema as $field) {
      if (isset($field['name']) && $field['name'] === $field_name) {
        return $field;
      }
    }

    return null;
  }
}
