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
   * Sanitize field data
   * TODO: Phase 14 - Complete implementation with schema-aware sanitization
   *
   * @param mixed $data Data to sanitize
   * @param array|null $schema Schema for validation
   * @param string $field_name Field name
   * @return mixed Sanitized data
   */
  public function sanitize($data, $schema = null, $field_name = '') {
    // Placeholder - delegates to old logic for now
    if (is_array($data)) {
      return map_deep($data, 'sanitize_text_field');
    }
    return sanitize_text_field($data);
  }

  /**
   * Sanitize code field
   *
   * @param string $code Code content
   * @param string $language Programming language
   * @return string Sanitized code
   */
  public function sanitizeCodeField($code, $language) {
    // Preserve code formatting, just remove dangerous tags
    return wp_kses_post($code);
  }
}
