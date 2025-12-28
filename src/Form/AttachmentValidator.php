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
   * TODO: Phase 14 - Complete implementation
   *
   * @param mixed $data Data to validate
   * @param array|null $schema Schema
   * @param string $parent_key Parent key for nested data
   * @return mixed Cleaned data
   */
  public function validateAndClean($data, $schema = null, $parent_key = '') {
    // Placeholder - will be fully implemented in Phase 14
    return $data;
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
            }
          }
        } else {
          // Regular nested object, pass the nested schema
          $nested_schema = null;
          if ($field_schema && isset($field_schema['fields'])) {
            $nested_schema = $field_schema['fields'];
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
