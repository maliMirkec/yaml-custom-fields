<?php

/**
 * Data Sanitizer
 * Sanitize field data based on schema
 */

namespace YamlCF\Form;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class DataSanitizer {
  /**
   * Marker prefix for base64-encoded code field values
   */
  const CODE_FIELD_MARKER = '__YAMLCF_B64__';

  /**
   * Sanitize field data
   *
   * Field definitions are resolved level by level: top-level keys are looked up in
   * $schema['fields'], object children in the object's own fields, and block items
   * in the matching block's fields. A field name is never matched at another depth.
   *
   * @param mixed $data Data to sanitize
   * @param array|null $schema Schema node containing a 'fields' array for this level
   * @param string $field_name Field name within $schema that $data belongs to ('' for a set of fields)
   * @return mixed Sanitized data
   */
  public function sanitize($data, $schema = null, $field_name = '') {
    if ($field_name === '' || $field_name === null) {
      return is_array($data) ? $this->sanitizeFields($data, $schema) : $this->sanitizeValue($data, null);
    }

    return $this->sanitizeValue($data, $this->getFieldDefinition($schema, $field_name));
  }

  /**
   * Sanitize code field
   *
   * Users with unfiltered_html keep their code as-is (same model as the core Custom HTML block).
   * Everyone else gets wp_kses_post() for markup, or dangerous-pattern removal for CSS.
   *
   * @param string $code Code content
   * @param string $language Programming language
   * @return string Sanitized code (not encoded)
   */
  public function sanitizeCodeField($code, $language) {
    if (!is_string($code) || $code === '') {
      return '';
    }

    if (current_user_can('unfiltered_html')) {
      return $code;
    }

    if ($language === 'css') {
      return $this->sanitizeCss($code);
    }

    return wp_kses_post($code);
  }

  /**
   * Find a field definition among the direct fields of a schema node
   *
   * @param array|null $schema Schema node with a 'fields' array
   * @param string|int $field_name Field name
   * @return array|null Field definition or null if not found
   */
  public function getFieldDefinition($schema, $field_name) {
    if (!is_array($schema) || !isset($schema['fields']) || !is_array($schema['fields'])) {
      return null;
    }

    foreach ($schema['fields'] as $field) {
      if (is_array($field) && isset($field['name']) && (string) $field['name'] === (string) $field_name) {
        return $field;
      }
    }

    return null;
  }

  /**
   * Remove any leading code field marker so only code fields can carry encoded values
   *
   * @param string $value Value to clean
   * @return string Value without marker prefix
   */
  public function stripCodeMarker($value) {
    while (is_string($value) && strpos($value, self::CODE_FIELD_MARKER) === 0) {
      $value = substr($value, strlen(self::CODE_FIELD_MARKER));
    }
    return $value;
  }

  /**
   * Sanitize a set of fields described by a schema node
   *
   * @param array $data Field values keyed by field name
   * @param array|null $schema Schema node with a 'fields' array
   * @return array Sanitized values
   */
  private function sanitizeFields(array $data, $schema) {
    $sanitized = [];
    foreach ($data as $key => $value) {
      $sanitized[sanitize_text_field($key)] = $this->sanitizeValue($value, $this->getFieldDefinition($schema, $key));
    }
    return $sanitized;
  }

  /**
   * Sanitize a single field value according to its definition
   *
   * @param mixed $value Field value
   * @param array|null $field_def Field definition (null if unknown)
   * @return mixed Sanitized value
   */
  private function sanitizeValue($value, $field_def) {
    $type = is_array($field_def) && isset($field_def['type']) ? $field_def['type'] : '';

    if (is_string($value)) {
      return $this->sanitizeString($value, $field_def);
    }

    if (!is_array($value)) {
      return $value;
    }

    if ($type === 'object') {
      $fields = isset($field_def['fields']) && is_array($field_def['fields']) ? $field_def['fields'] : [];
      return $this->sanitizeFields($value, ['fields' => $fields]);
    }

    if ($type === 'block') {
      return $this->sanitizeBlocks($value, $field_def);
    }

    // Lists (multiple select, taxonomy, data_object, file list) or unknown nested data:
    // items are plain values, never code or rich text
    $sanitized = [];
    foreach ($value as $key => $item) {
      $sanitized[sanitize_text_field($key)] = $this->sanitizeValue($item, null);
    }

    // Drop the empty strings submitted by hidden inputs that keep checkbox/file lists clearable
    $is_multiple = !empty($field_def['multiple']) || ($type === 'data_object' && !empty($field_def['list']));
    $is_file_list = $type === 'file' && !empty($field_def['list']);
    if ((($type === 'taxonomy' || $type === 'data_object') && $is_multiple) || $is_file_list) {
      $sanitized = array_values(array_filter($sanitized, function($item) {
        return $item !== '';
      }));
    }

    return $sanitized;
  }

  /**
   * Sanitize block field data (a single block or a list of blocks)
   *
   * @param array $value Block data
   * @param array $field_def Block field definition
   * @return array Sanitized block data
   */
  private function sanitizeBlocks(array $value, array $field_def) {
    $block_key = isset($field_def['blockKey']) ? $field_def['blockKey'] : 'type';

    // Single block: the block type key sits directly on the value
    if (isset($value[$block_key]) && !is_array($value[$block_key])) {
      return $this->sanitizeFields($value, $this->getBlockSchema($field_def, $value[$block_key]));
    }

    $sanitized = [];
    foreach ($value as $index => $item) {
      if (is_array($item)) {
        $block_type = isset($item[$block_key]) ? $item[$block_key] : '';
        $sanitized[sanitize_text_field($index)] = $this->sanitizeFields($item, $this->getBlockSchema($field_def, $block_type));
      } else {
        $sanitized[sanitize_text_field($index)] = $this->sanitizeValue($item, null);
      }
    }
    return $sanitized;
  }

  /**
   * Get the schema node for a block type
   *
   * @param array $field_def Block field definition
   * @param mixed $block_type Block type name
   * @return array Schema node (empty fields if the block type is unknown)
   */
  private function getBlockSchema(array $field_def, $block_type) {
    if ($block_type !== '' && isset($field_def['blocks']) && is_array($field_def['blocks'])) {
      foreach ($field_def['blocks'] as $block) {
        if (is_array($block) && isset($block['name']) && $block['name'] === $block_type) {
          return ['fields' => isset($block['fields']) && is_array($block['fields']) ? $block['fields'] : []];
        }
      }
    }
    return ['fields' => []];
  }

  /**
   * Sanitize a string value according to its field type
   *
   * @param string $value Raw value
   * @param array|null $field_def Field definition
   * @return string Sanitized value
   */
  private function sanitizeString($value, $field_def) {
    $type = is_array($field_def) && isset($field_def['type']) ? $field_def['type'] : '';

    if ($type === 'code') {
      $language = isset($field_def['options']['language']) ? $field_def['options']['language'] : 'html';
      return $this->encodeCodeField($this->decodeCodeField($value), $language);
    }

    $value = $this->stripCodeMarker($value);

    if ($type === 'rich-text') {
      return wp_kses_post($value);
    }

    // sanitize_textarea_field preserves newlines and structure
    return sanitize_textarea_field($value);
  }

  /**
   * Sanitize and base64-encode a code field value with the marker prefix
   *
   * @param string $code Raw code
   * @param string $language Code language
   * @return string Encoded value, or '' when empty
   */
  private function encodeCodeField($code, $language) {
    $code = $this->sanitizeCodeField($code, $language);
    if ($code === '') {
      return '';
    }
    return self::CODE_FIELD_MARKER . base64_encode($code);
  }

  /**
   * Decode an already-encoded code value (e.g. from an export file) so it can be re-sanitized
   *
   * @param string $value Possibly encoded value
   * @return string Decoded value
   */
  private function decodeCodeField($value) {
    if (strpos($value, self::CODE_FIELD_MARKER) !== 0) {
      return $value;
    }
    $decoded = base64_decode(substr($value, strlen(self::CODE_FIELD_MARKER)), true);
    return $decoded === false ? '' : $this->stripCodeMarker($decoded);
  }

  /**
   * Remove dangerous CSS constructs
   *
   * @param string $css Raw CSS
   * @return string Cleaned CSS
   */
  private function sanitizeCss($css) {
    $dangerous_patterns = [
      '/expression\s*\(/i',           // IE CSS expressions
      '/javascript\s*:/i',            // JavaScript protocol
      '/vbscript\s*:/i',              // VBScript protocol
      '/@import\s+/i',                // Prevent external CSS imports
      '/behavior\s*:/i',              // IE behaviors
      '/\-moz\-binding\s*:/i',        // Firefox XBL bindings
      '/data\s*:\s*text\/html/i',     // Data URI with HTML
    ];

    $cleaned_css = $css;
    foreach ($dangerous_patterns as $pattern) {
      $cleaned_css = preg_replace($pattern, '', $cleaned_css);
    }

    // Basic sanitization while preserving newlines (also strips any tags, e.g. </style>)
    return sanitize_textarea_field($cleaned_css);
  }
}
